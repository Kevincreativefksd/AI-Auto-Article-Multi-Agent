<?php
/**
 * Uji mandiri Visual Content Agent.
 *
 * Jalankan di STAGING (bukan produksi) dengan WP-CLI dari folder WordPress:
 *
 *   wp eval-file wp-content/plugins/ai-auto-article/tests/visual-selftest.php
 *
 * Skrip membuat attachment dan post uji, lalu menghapusnya kembali. Pengaturan plugin dipulihkan di akhir.
 * Skrip TIDAK memanggil Gemini sungguhan: respons Gemini disimulasikan lewat filter pre_http_request.
 * Keluaran: satu baris PASS/FAIL per pemeriksaan dan ringkasan di akhir.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('AAA_Visual_Agent') || !class_exists('AAA_Visual_Renderer')) {
    echo "Plugin AI Auto Article belum aktif.\n";
    return;
}

$GLOBALS['aaa_t'] = ['pass' => 0, 'fail' => 0];
function aaa_t($ok, $label, $extra = '') {
    $GLOBALS['aaa_t'][$ok ? 'pass' : 'fail']++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($extra !== '' ? '  -> ' . $extra : '') . "\n";
}
function aaa_call($method, $args = []) {
    $m = new ReflectionMethod('AAA_Visual_Agent', $method);
    $m->setAccessible(true);
    return $m->invokeArgs(null, $args);
}

$original_settings = get_option(AAA_Visual_Agent::OPTION, null);
$created_attachments = [];
$created_posts = [];
$caps = AAA_Visual_Renderer::capabilities();
echo "Kemampuan: " . wp_json_encode($caps) . "\n\n";

$base_visual = [
    'brand_name' => 'Zona Ekonomi', 'category_label' => 'Analisis Pasar',
    'article_title' => 'Iran Buka Peluang Negosiasi dengan AS', 'brand_description' => 'Ruang Literasi Ekonomi',
    'accent_color' => '#B92E32', 'background_color' => '#F4F2EE', 'text_color' => '#171717',
    'image' => ['width' => 1920, 'height' => 1080, 'format' => 'auto', 'max_kb' => 200],
];

// ---- 1. Renderer ---------------------------------------------------------
$r = AAA_Visual_Renderer::render_visual_image($base_visual);
aaa_t(!is_wp_error($r), 'Render judul pendek', is_wp_error($r) ? $r->get_error_message() : '');
if (!is_wp_error($r)) {
    $size = getimagesizefromstring($r['binary']);
    aaa_t($size && $size[0] === 1920 && $size[1] === 1080, 'Dimensi 1920x1080 (16:9)', $size ? $size[0] . 'x' . $size[1] : 'tidak terbaca');
    aaa_t($r['bytes'] <= 200 * 1024, 'Ukuran <= 200 KB', $r['bytes'] . ' byte, kualitas ' . $r['quality']);
    if ($caps['gd_webp'] && $caps['wp_webp']) aaa_t($r['format'] === 'webp', 'Format otomatis = WebP bila didukung', $r['format']);
    else aaa_t($r['format'] === 'jpeg', 'Format otomatis = JPEG bila WebP tidak didukung', $r['format']);
}

$long = $base_visual;
$long['article_title'] = 'Pemerintah Menaikkan Proyeksi Pertumbuhan Ekonomi Nasional Tahun Depan Seiring Menguatnya Konsumsi Rumah Tangga, Investasi Swasta, dan Ekspor Komoditas Unggulan Indonesia';
$r2 = AAA_Visual_Renderer::render_visual_image($long);
aaa_t(!is_wp_error($r2), 'Render judul panjang (160 karakter)', is_wp_error($r2) ? $r2->get_error_message() : $r2['bytes'] . ' byte');

$jp = $base_visual; $jp['image']['format'] = 'jpeg';
$r3 = AAA_Visual_Renderer::render_visual_image($jp);
aaa_t(!is_wp_error($r3) && $r3['mime'] === 'image/jpeg', 'Format JPEG dipaksa', is_wp_error($r3) ? $r3->get_error_message() : $r3['bytes'] . ' byte');

$tiny = $base_visual; $tiny['image']['max_kb'] = 20;
$r4 = AAA_Visual_Renderer::render_visual_image($tiny);
aaa_t(!is_wp_error($r4), 'Batas 20 KB: tetap menghasilkan gambar terbaik', is_wp_error($r4) ? $r4->get_error_message() : ($r4['optimized'] ? 'muat' : 'melebihi batas, dicatat: ' . $r4['note']));

$bad = $base_visual; $bad['accent_color'] = 'url(javascript:1)'; $bad['article_title'] = '<b>Judul</b> <script>x</script> 你好';
$r5 = AAA_Visual_Renderer::render_visual_image($bad);
aaa_t(!is_wp_error($r5), 'Warna tidak valid dan teks berisiko disaring tanpa error');

// ---- 2. Validasi JSON ----------------------------------------------------
$ctx = ['title' => 'Judul Asli Artikel', 'category' => '', 'brand' => 'Zona Ekonomi', 'desc' => 'Ruang Literasi Ekonomi',
        'bg' => '#F4F2EE', 'fg' => '#171717', 'accent' => '#B92E32', 'format' => 'auto', 'max_kb' => 200];
$good = ['brand_name' => 'HACK', 'category_label' => 'analisis pasar', 'article_title' => 'Judul Diubah Gemini',
         'accent_color' => '#000', 'evil_field' => 'rm -rf', 'seo' => ['title' => 'T', 'description' => 'D', 'alt_text' => 'A', 'caption' => 'C']];
$plan = aaa_call('validate_plan', [$good, $ctx]);
aaa_t(!is_wp_error($plan), 'validate_plan menerima JSON valid');
if (!is_wp_error($plan)) {
    aaa_t($plan['article_title'] === 'Judul Asli Artikel', 'Judul tetap judul asli');
    aaa_t($plan['brand_name'] === 'Zona Ekonomi' && $plan['brand_description'] === 'Ruang Literasi Ekonomi', 'Brand dan deskripsi mengikuti pengaturan');
    aaa_t($plan['category_label'] === 'ANALISIS PASAR', 'Label kategori dari Gemini (kategori kosong), huruf kapital', $plan['category_label']);
    aaa_t(!isset($plan['evil_field']), 'Field tak dikenal tidak diteruskan');
    aaa_t($plan['accent_color'] === '#B92E32', 'Warna berasal dari pengaturan, bukan dari Gemini');
}
$ctx_cat = $ctx; $ctx_cat['category'] = 'Makroekonomi';
$plan_c = aaa_call('validate_plan', [$good, $ctx_cat]);
aaa_t(!is_wp_error($plan_c) && $plan_c['category_label'] === 'MAKROEKONOMI', 'Kategori manual tersimpan diutamakan');
aaa_t(is_wp_error(aaa_call('validate_plan', [['category_label' => 'X'], $ctx])), 'JSON tanpa objek seo ditolak');
aaa_t(is_wp_error(aaa_call('validate_plan', [['seo' => ['title' => '', 'description' => '']], $ctx])), 'seo kosong ditolak');
aaa_t(aaa_call('parse_json', ["```json\n{\"seo\":{\"title\":\"a\"}}\n```"]) !== null, 'parse_json membuang pagar Markdown');
aaa_t(aaa_call('parse_json', ['bukan json']) === null, 'parse_json menolak teks non-JSON');
$fb = aaa_call('fallback_plan', [$ctx]);
aaa_t($fb['article_title'] === 'Judul Asli Artikel' && $fb['seo']['alt_text'] !== '', 'Fallback PHP mengisi judul dan metadata');

// ---- 3. Nama berkas ------------------------------------------------------
aaa_t(aaa_call('filename_base', ['Iran Buka Peluang Negosiasi dengan AS', 1]) === 'iran-buka-peluang-negosiasi-dengan-as', 'Nama berkas SEO-friendly');
aaa_t(aaa_call('filename_base', ['你好 世界', 7]) === 'visual-editorial-7', 'Judul non-Latin memakai nama cadangan');
aaa_t(preg_match('/^[a-z0-9\-]+$/', aaa_call('filename_base', ['Éxito: 100% "aman" & <b>baik</b>!', 1])) === 1, 'Nama berkas tanpa karakter khusus');

// ---- 4. Media Library, metadata, Featured Image, penyisipan --------------
update_option(AAA_Visual_Agent::OPTION, array_merge(AAA_Visual_Agent::defaults(), ['insert' => '1', 'featured' => '1']));
$task_id = 990000001;
$plan_ok = aaa_call('fallback_plan', [array_merge($ctx, ['title' => 'Tes Visual Agent ' . wp_generate_password(6, false)])]);
$img = AAA_Visual_Renderer::render_visual_image($plan_ok);
if (is_wp_error($img)) {
    aaa_t(false, 'Render untuk uji Media Library', $img->get_error_message());
} else {
    $att = aaa_call('save_to_media_library', [$img, $plan_ok, $task_id]);
    aaa_t(!is_wp_error($att) && $att > 0, 'Simpan ke Media Library', is_wp_error($att) ? $att->get_error_message() : 'ID ' . $att);
    if (!is_wp_error($att)) {
        $created_attachments[] = $att;
        $p = get_post($att);
        aaa_t($p && $p->post_title !== '', 'Image Title tersimpan', $p ? $p->post_title : '');
        aaa_t($p && $p->post_content !== '', 'Image Description tersimpan');
        aaa_t(get_post_meta($att, '_wp_attachment_image_alt', true) !== '', 'Alt Text tersimpan', get_post_meta($att, '_wp_attachment_image_alt', true));
        aaa_t($p && $p->post_excerpt !== '', 'Caption tersimpan');
        aaa_t(preg_match('/\.(webp|jpg)$/', (string) get_attached_file($att)) === 1, 'Ekstensi berkas webp/jpg', basename((string) get_attached_file($att)));
        aaa_t(aaa_call('find_existing_attachment', [$task_id]) === $att, 'Pencegah duplikasi menemukan attachment yang sama');

        $agent_data = ['visual' => ['attachment_id' => $att]];
        $post_id = wp_insert_post(['post_title' => 'Post uji visual', 'post_content' => '<h1>Judul</h1><p>Isi.</p>', 'post_status' => 'draft'], true);
        if (!is_wp_error($post_id)) {
            $created_posts[] = $post_id;
            $html = AAA_Visual_Agent::maybe_prepend_image('<h1>Judul</h1><p>Isi.</p>', $agent_data);
            aaa_t(strpos($html, 'wp-image-' . $att) !== false && strpos($html, 'alt=') !== false, 'Gambar disisipkan di awal dengan alt text');
            aaa_t(strpos($html, '<figure') === 0, 'Gambar berada di posisi paling awal');
            aaa_t(AAA_Visual_Agent::maybe_prepend_image($html, $agent_data) === $html, 'Penyisipan kedua tidak menggandakan gambar');
            AAA_Visual_Agent::finalize_post($post_id, $agent_data);
            aaa_t((int) get_post_thumbnail_id($post_id) === (int) $att, 'Featured Image ditetapkan');
            aaa_t((int) get_post($att)->post_parent === (int) $post_id, 'Attachment terhubung ke post_id');

            update_option(AAA_Visual_Agent::OPTION, array_merge(AAA_Visual_Agent::defaults(), ['insert' => '0', 'featured' => '0']));
            aaa_t(AAA_Visual_Agent::maybe_prepend_image('<p>x</p>', $agent_data) === '<p>x</p>', 'Opsi sisip dimatikan: HTML tidak berubah');
        }
    }
}

// ---- 5. Alur tugas lengkap dengan Gemini disimulasikan --------------------
function aaa_fake_gemini($text) {
    return function () use ($text) {
        return ['headers' => [], 'cookies' => [], 'filename' => null, 'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]])];
    };
}
function aaa_run_task($task, $data, $max = 6) {
    for ($i = 0; $i < $max; $i++) {
        $out = AAA_Visual_Agent::process($task, $data);
        if ($out === null) return ['locked' => true];
        $data = $out['data'];
        if ($out['next'] !== 'visual_agent') return ['data' => $data, 'ticks' => $i + 1];
    }
    return ['data' => $data, 'ticks' => $max, 'stuck' => true];
}
update_option(AAA_Visual_Agent::OPTION, AAA_Visual_Agent::defaults());
$mk_task = function ($id) { return (object) ['id' => $id, 'keyword' => 'iran negosiasi', 'category_id' => 0, 'status' => 'visual_agent']; };
$mk_data = function () { return ['agent_3' => ['revised_html' => '<h1>Iran Buka Peluang Negosiasi dengan AS</h1><p>Minyak Brent turun ke sekitar US$105 per barel.</p>']]; };
add_filter('pre_option_aaa_gemini_api', function () { return 'TEST-KEY'; });

// 5a. Gemini sukses
$json_ok = wp_json_encode(['category_label' => 'ANALISIS PASAR', 'seo' => [
    'title' => 'Peluang Negosiasi Iran dengan AS', 'description' => 'Sampul editorial tentang peluang negosiasi Iran dengan Amerika Serikat.',
    'alt_text' => 'Sampul editorial Zona Ekonomi tentang peluang negosiasi Iran dan AS', 'caption' => 'Iran Buka Peluang Negosiasi dengan AS — Zona Ekonomi']]);
add_filter('pre_http_request', $f = aaa_fake_gemini($json_ok), 10, 3);
$res = aaa_run_task($mk_task(990000002), $mk_data());
remove_filter('pre_http_request', $f, 10);
$v = isset($res['data']['visual']) ? $res['data']['visual'] : [];
aaa_t(!empty($v['attachment_id']) && $v['plan_source'] === 'gemini', 'Alur penuh dengan Gemini sukses', wp_json_encode(['status' => isset($v['status']) ? $v['status'] : null, 'ticks' => isset($res['ticks']) ? $res['ticks'] : null]));
if (!empty($v['attachment_id'])) $created_attachments[] = $v['attachment_id'];

// 5b. Gemini mengembalikan JSON rusak lalu perbaikan berhasil
$calls = 0;
add_filter('pre_http_request', $f = function () use (&$calls, $json_ok) { $calls++; return aaa_fake_gemini($calls === 1 ? '{rusak' : $json_ok)(); }, 10, 3);
$res = aaa_run_task($mk_task(990000003), $mk_data());
remove_filter('pre_http_request', $f, 10);
$v = isset($res['data']['visual']) ? $res['data']['visual'] : [];
aaa_t(isset($v['plan_source']) && $v['plan_source'] === 'gemini_repair' && $calls === 2, 'JSON rusak: tepat satu kali perbaikan oleh Gemini', 'panggilan=' . $calls);
if (!empty($v['attachment_id'])) $created_attachments[] = $v['attachment_id'];

// 5c. Gemini gagal total (error jaringan): fallback PHP, artikel tidak gagal
add_filter('pre_http_request', $f = function () { return new WP_Error('http_request_failed', 'simulasi gagal'); }, 10, 3);
$res = aaa_run_task($mk_task(990000004), $mk_data());
remove_filter('pre_http_request', $f, 10);
$v = isset($res['data']['visual']) ? $res['data']['visual'] : [];
aaa_t(isset($res['data']) && empty($res['stuck']) && isset($v['plan_source']) && $v['plan_source'] === 'fallback', 'Gemini gagal: fallback PHP dipakai', isset($v['status']) ? $v['status'] : '');
aaa_t(!empty($v['attachment_id']), 'Gemini gagal: gambar tetap dibuat dari fallback');
if (!empty($v['attachment_id'])) $created_attachments[] = $v['attachment_id'];

// 5d. Agen dinonaktifkan
update_option(AAA_Visual_Agent::OPTION, array_merge(AAA_Visual_Agent::defaults(), ['enabled' => '0']));
$out = AAA_Visual_Agent::process($mk_task(990000005), $mk_data());
aaa_t($out !== null && $out['next'] === 'quality_gate' && $out['data']['visual']['status'] === 'skipped', 'Agen dinonaktifkan: dilewati menuju Quality Gate');
update_option(AAA_Visual_Agent::OPTION, AAA_Visual_Agent::defaults());

// 5e. Tugas yang sudah selesai tidak diproses ulang; kunci mencegah pemrosesan ganda
$done = $mk_data(); $done['visual'] = ['stage' => 'done', 'status' => 'ok'];
$out = AAA_Visual_Agent::process($mk_task(990000006), $done);
aaa_t($out !== null && $out['next'] === 'quality_gate', 'Tahap yang sudah done dilewati (tugas dijalankan ulang)');
$lock = new ReflectionMethod('AAA_Visual_Agent', 'acquire_lock'); $lock->setAccessible(true);
$rel  = new ReflectionMethod('AAA_Visual_Agent', 'release_lock'); $rel->setAccessible(true);
$first = $lock->invoke(null, 990000007); $second = $lock->invoke(null, 990000007);
$rel->invoke(null, 990000007);
aaa_t($first === true && $second === false, 'Kunci: pemrosesan kedua pada tugas yang sama ditolak');

// ---- Pembersihan ---------------------------------------------------------
remove_all_filters('pre_option_aaa_gemini_api');
foreach (array_unique($created_attachments) as $id) wp_delete_attachment($id, true);
foreach ($created_posts as $id) wp_delete_post($id, true);
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aaa_visual_lock_99000%'");
if ($original_settings === null) delete_option(AAA_Visual_Agent::OPTION); else update_option(AAA_Visual_Agent::OPTION, $original_settings);

echo "\nRingkasan: " . $GLOBALS['aaa_t']['pass'] . " lulus, " . $GLOBALS['aaa_t']['fail'] . " gagal.\n";
