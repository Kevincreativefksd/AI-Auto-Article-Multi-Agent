<?php
if (!defined('ABSPATH')) exit;

class AAA_Agents {

    // Model per tahap (opsi aaa_model_{tahap}); kosong = model global. Riset SERP butuh model yang mendukung Google Search.
    public static function model_for($stage) {
        $m = trim((string) get_option("aaa_model_$stage", ''));
        if ($m === '' && $stage === 'serp') $m = 'gemini-2.5-flash';
        return $m !== '' ? $m : null;
    }

    // Riset SERP sebelum Agent 1 (Gemini + Google Search grounding). Kegagalan tidak menggagalkan artikel.
    public static function run_serp_research($keyword) {
        $prompt = AAA_Prompts::render('serp', ['keyword' => $keyword]);
        return AAA_API::call_gemini($prompt, false, self::model_for('serp'), true);
    }

    // Agent 1: SEO Researcher
    public static function run_agent_1($keyword, $extra_info, $serp = '') {
        $serp_block = empty($serp) ? '' : "Data riset SERP (gunakan sebagai acuan outline dan celah konten, jangan disalin mentah):\n{$serp}\n";
        $prompt = AAA_Prompts::render('agent_1', [
            'brand'      => get_option('aaa_brand_name', ''),
            'audience'   => get_option('aaa_target_audience', ''),
            'keyword'    => $keyword,
            'extra_info' => $extra_info,
            'serp'       => $serp_block,
        ]);
        return AAA_API::call_gemini($prompt, true, self::model_for('agent_1'));
    }

    // Agent 2: Content Writer
    public static function run_agent_2($keyword, $agent_1_data, $extra_info = '') {
        $prompt = AAA_Prompts::render('agent_2', [
            'brand'      => get_option('aaa_brand_name', ''),
            'audience'   => get_option('aaa_target_audience', ''),
            'tone'       => get_option('aaa_tone_voice', ''),
            'extra_info' => trim((string) $extra_info),
            'keyword'    => $keyword,
            'outline'    => wp_json_encode($agent_1_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'home_url'   => home_url('/'),
        ]);
        return AAA_API::call_gemini($prompt, false, self::model_for('agent_2'));
    }

    // Agent 3: SEO Editor
    public static function run_agent_3($html_content, $keyword) {
        $prompt = AAA_Prompts::render('agent_3', ['keyword' => $keyword, 'html' => $html_content]);
        return AAA_API::call_gemini($prompt, true, self::model_for('agent_3'));
    }

    // Kategori tugas: daftar category_ids (koma) bila ada, jika tidak category_id tunggal lama.
    private static function task_categories($task) {
        $cats = [];
        if ($task && !empty($task->category_ids)) {
            $cats = array_filter(array_map('absint', explode(',', $task->category_ids)));
        }
        if (empty($cats) && $task && !empty($task->category_id)) {
            $cats = [absint($task->category_id)];
        }
        return array_values(array_unique($cats));
    }

    // Agent 4: Quality Gate (PHP Logic)
    public static function run_agent_4_quality_gate($task_id, $agent_data, $task = null) {
        $seo_edit = $agent_data['agent_3'] ?? [];
        $keyword = $agent_data['keyword'];

        // Kriteria Kelulusan
        if (empty($seo_edit['revised_html'])) {
            return new WP_Error('qa_failed', 'Gagal Publikasi: Konten HTML hasil revisi kosong.');
        }

        // Gunakan versi humanizer jika tersedia dan lolos validasi; jika tidak, pakai hasil SEO Editor.
        $final_html = !empty($agent_data['humanized_html']) ? $agent_data['humanized_html'] : $seo_edit['revised_html'];

        // Visual Content Agent: sisipkan gambar di awal artikel bila diaktifkan (aman; kegagalan tidak menghentikan publikasi).
        if (class_exists('AAA_Visual_Agent')) { $final_html = AAA_Visual_Agent::maybe_prepend_image($final_html, $agent_data); }

        // Publikasi Otomatis (Atau Draft)
        $post_status = get_option('aaa_autopublish', 'draft');
        $title = ucfirst($keyword);
        $schedule = [];
        if (!empty($agent_data['news']) && is_array($agent_data['news'])) {
            $news = $agent_data['news'];
            // Keyword semantik = judul post (satu sumber). Tugas lama tanpa keyword memakai judul tersimpan.
            if (!empty($news['keyword'])) { $title = $news['keyword']; } elseif (!empty($news['title'])) { $title = $news['title']; }
            $ps = $news['status'] ?? 'draft';
            $post_status = in_array($ps, ['draft', 'publish', 'future'], true) ? $ps : 'draft';
            // Angka yang tidak ada di artikel mentah: jangan terbit otomatis, minta tinjauan manusia.
            if (!empty($news['unverified'])) $post_status = 'draft';
            if ($post_status === 'future' && !empty($news['date'])) {
                $schedule = ['post_date' => $news['date'], 'post_date_gmt' => get_gmt_from_date($news['date'])];
            }
        }
        
        $postarr = [
            'post_title'   => $title,
            'post_content' => $final_html,
            'post_status'  => $post_status,
            'post_author'  => ($task && !empty($task->author_id)) ? absint($task->author_id) : (get_current_user_id() ?: absint(get_option('aaa_default_author', 1))),
            'post_excerpt' => isset($seo_edit['meta_desc']) ? sanitize_text_field($seo_edit['meta_desc']) : '',
            'post_category' => self::task_categories($task)
        ];
        $post_id = wp_insert_post(array_merge($postarr, $schedule), true);

        // Visual Content Agent: hubungkan attachment ke artikel dan jadikan Featured Image.
        if (class_exists('AAA_Visual_Agent') && $post_id && !is_wp_error($post_id)) { AAA_Visual_Agent::finalize_post($post_id, $agent_data); }

        if ($post_id && !empty($seo_edit['tags'])) {
            // Memastikan tidak ada sisa karakter # jika AI masih bandel
            $clean_tags = array_map(function($tag) {
                return trim(str_replace('#', '', $tag));
            }, $seo_edit['tags']);
            wp_set_post_tags($post_id, array_filter($clean_tags), false);
        } elseif ($post_id && !empty($agent_data['legacy_tags'])) {
            wp_set_post_tags($post_id, array_filter(array_map('sanitize_text_field', $agent_data['legacy_tags'])), false);
        }

        return $post_id;
    }
    
        // Agent Khusus: Cluster Keyword Generator
    public static function generate_cluster_keywords($keyword, $count = 5) {
        $count = min(5, max(2, (int) $count));
        $prompt = AAA_Prompts::render('cluster', ['keyword' => $keyword, 'count' => $count]);
        return AAA_API::call_gemini($prompt, true, self::model_for('cluster'));
    }

}