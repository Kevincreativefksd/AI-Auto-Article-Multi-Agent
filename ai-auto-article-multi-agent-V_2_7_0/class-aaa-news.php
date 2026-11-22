<?php
if (!defined('ABSPATH')) exit;

/**
 * Mode Berita: satu artikel mentah menjadi tiga berita dengan sudut pandang berbeda.
 * Hanya fakta dari artikel mentah yang boleh dipakai; angka di hasil tulisan diperiksa terhadap sumber.
 */
class AAA_News {

    const MAX_RAW = 30000;

    public static function default_angles() {
        return [
            1 => ['Ringkasan', 'Berita inti yang menjawab apa, siapa, kapan, di mana, mengapa, dan bagaimana secara padat dan berurutan.'],
            2 => ['Dampak', 'Apa arti peristiwa ini bagi pihak yang terlibat atau bagi pembaca, hanya berdasarkan fakta yang disebut di artikel mentah.'],
            3 => ['Latar belakang', 'Konteks dan rangkaian peristiwa yang disebut di artikel mentah supaya pembaca memahami situasinya.'],
        ];
    }

    public static function structures() {
        return [
            'pyramid'    => ['Piramida terbalik (fakta terpenting dulu)', 'Struktur piramida terbalik: paragraf pertama memuat fakta terpenting (apa, siapa, kapan, di mana), lalu detail pendukung, dan informasi tambahan paling akhir.'],
            'chronology' => ['Kronologis (urut waktu)', 'Struktur kronologis: paragraf pembuka merangkum inti berita, lalu peristiwa diurutkan sesuai urutan waktu.'],
            'topical'    => ['Per topik (satu aspek per bagian)', 'Struktur per topik: paragraf pembuka merangkum inti berita, lalu tiap paragraf atau bagian membahas satu aspek berita.'],
        ];
    }

    public static function min_words() {
        return min(3000, max(100, (int) get_option('aaa_news_min_words', 350)));
    }

    public static function max_words() {
        return max(self::min_words(), min(3000, (int) get_option('aaa_news_max_words', 500)));
    }

    public static function length_text() {
        return 'Panjang tulisan sekitar ' . self::min_words() . ' sampai ' . self::max_words() . ' kata, selama fakta di artikel mentah mencukupi. Jangan menambah isi hanya untuk memenuhi panjang.';
    }

    public static function structure_text() {
        $all = self::structures();
        $key = (string) get_option('aaa_news_structure', 'pyramid');
        if (!isset($all[$key])) $key = 'pyramid';
        $t = $all[$key][1];
        $t .= get_option('aaa_news_subheads', '0') === '1'
            ? ' Gunakan 2 sampai 4 subjudul <h2> untuk membagi isi.'
            : ' Jangan memakai subjudul; gunakan paragraf <p> saja.';
        $extra = trim((string) get_option('aaa_news_structure_extra', ''));
        return $extra !== '' ? $t . ' ' . $extra : $t;
    }

    // Tone of Voice dari pengaturan utama plugin, diterapkan apa adanya.
    public static function tone() {
        $t = trim((string) get_option('aaa_tone_voice', ''));
        return $t !== '' ? $t : 'Bahasa Indonesia yang jelas dan wajar (Tone of Voice belum diisi).';
    }

    // Tiga sudut pandang: [1 => [label, instruksi], 2 => ..., 3 => ...]
    public static function angles() {
        $out = [];
        foreach (self::default_angles() as $i => $d) {
            $label = trim((string) get_option("aaa_news_angle_{$i}_label", ''));
            $instr = trim((string) get_option("aaa_news_angle_{$i}", ''));
            $out[$i] = [$label !== '' ? $label : $d[0], $instr !== '' ? $instr : $d[1]];
        }
        return $out;
    }

    private static function headline_from($raw) {
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);
            if ($line !== '') return mb_strlen($line) > 80 ? mb_substr($line, 0, 80) . '...' : $line;
        }
        return 'Berita';
    }

    // Membuat 3 tugas antrean (satu per sudut pandang). Mengembalikan jumlah tugas yang tersimpan.
    public static function enqueue(array $a) {
        global $wpdb;
        $table = AAA_DB::get_table_name();
        $raw = mb_substr((string) $a['raw'], 0, self::MAX_RAW);
        $topic = $a['topic'] !== '' ? $a['topic'] : self::headline_from($raw);
        $count = 0;
        foreach (self::angles() as $i => $angle) {
            $date = '';
            if ($a['status'] === 'future' && !empty($a['date'])) {
                $date = gmdate('Y-m-d H:i:s', strtotime($a['date'] . ' UTC') + ($i - 1) * max(0, (int) $a['gap']) * 60);
            }
            $payload = ['news' => [
                'raw'         => $raw,
                'topic'       => $topic,
                'angle_label' => $angle[0],
                'angle'       => $angle[1],
                'status'      => $a['status'],
                'date'        => $date,
            ]];
            $ok = $wpdb->insert($table, [
                'keyword'      => mb_substr($topic, 0, 200) . ' [' . $angle[0] . ']',
                'extra_info'   => (string) $a['extra'],
                'author_id'    => (int) $a['author_id'],
                'category_id'  => (int) $a['category_id'],
                'category_ids' => (string) $a['category_ids'],
                'status'       => 'news_write',
                'agent_data'   => wp_json_encode($payload),
            ]);
            if ($ok) $count++;
        }
        return $count;
    }

    // Menulis satu berita. Mengembalikan array [title, html, meta_desc, tags, unverified] atau WP_Error.
    public static function run_writer($task, array $data) {
        $n = isset($data['news']) && is_array($data['news']) ? $data['news'] : [];
        $raw = (string) ($n['raw'] ?? '');
        if ($raw === '') {
            return new WP_Error('qa_failed', 'Artikel mentah tidak ditemukan pada tugas ini.');
        }
        $prompt = AAA_Prompts::render('news', [
            'raw'         => $raw,
            'topic'       => $n['topic'] ?? '',
            'angle_label' => $n['angle_label'] ?? '',
            'angle'       => $n['angle'] ?? '',
            'tone'        => self::tone(),
            'length'      => self::length_text(),
            'structure'   => self::structure_text(),
            'style'       => self::tone(), // alias untuk prompt lama yang masih memakai {style}
            'brand'       => get_option('aaa_brand_name', ''),
            'audience'    => get_option('aaa_target_audience', ''),
            'extra_info'  => trim((string) $task->extra_info),
        ]);
        $res = AAA_API::call_gemini($prompt, true, AAA_Agents::model_for('news'));
        if (is_wp_error($res)) return $res;
        if (!is_array($res) || empty($res['title']) || empty($res['html'])) {
            return new WP_Error('json_error', 'Respons penulis berita tidak lengkap (judul atau isi kosong).');
        }

        $title = sanitize_text_field((string) $res['title']);
        $html  = wp_kses_post((string) $res['html']);
        $meta  = mb_substr(sanitize_text_field((string) ($res['meta_desc'] ?? '')), 0, 160);
        $tags = [];
        foreach ((array) ($res['tags'] ?? []) as $t) {
            $t = trim(str_replace('#', '', sanitize_text_field((string) $t)));
            if ($t !== '') $tags[] = $t;
        }
        return [
            'title'      => $title,
            'html'       => $html,
            'meta_desc'  => $meta,
            'tags'       => array_slice($tags, 0, 8),
            'unverified' => self::unverified_numbers($raw, $title . ' ' . wp_strip_all_tags($html)),
        ];
    }

    // Angka di $candidate_html yang tidak ada di $source_html (keduanya HTML).
    public static function numbers_not_in($source_html, $candidate_html) {
        return self::unverified_numbers(wp_strip_all_tags($source_html), wp_strip_all_tags($candidate_html));
    }

    // Pemeriksaan ringan: angka (2 digit atau lebih) di hasil yang tidak ada di artikel mentah.
    // Tidak menangkap nama atau klaim non-angka; hanya penanda untuk ditinjau manusia.
    private static function unverified_numbers($raw, $text) {
        $extract = function ($s) {
            preg_match_all('/\d+(?:[.,]\d+)*/u', $s, $m);
            return $m[0];
        };
        $norm = function ($n) { return str_replace(['.', ','], '', $n); };
        $source = [];
        foreach ($extract($raw) as $n) $source[$norm($n)] = true;
        $missing = [];
        foreach ($extract($text) as $n) {
            $k = $norm($n);
            if (strlen($k) >= 2 && !isset($source[$k])) $missing[$n] = true;
        }
        return array_keys($missing);
    }
}
