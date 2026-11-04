<?php
if (!defined('ABSPATH')) exit;

class AAA_Agents {
    
    // Agent 1: SEO Researcher
    public static function run_agent_1($keyword, $extra_info) {
        $brand = get_option('aaa_brand_name');
        $audience = get_option('aaa_target_audience');
        $prompt = "Anda adalah Agen SEO Researcher untuk blog ekonomi '$brand'. Audiens: '$audience'.\n";
        $prompt .= "Tugas: Analisis keyword '$keyword' dan info tambahan '$extra_info'.\n";
        $prompt .= "Berikan output berformat JSON murni dengan struktur: {\"search_intent\":\"...\", \"target_pembaca\":\"...\", \"outline_h2_h3\":[\"...\"], \"lsi_keywords\":[\"...\"]}";
        
        return AAA_API::call_gemini($prompt, true);
    }

    // Agent 2: Content Writer — retains the original AI Auto Article SEO prompt.
    public static function run_agent_2($keyword, $agent_1_data, $extra_info = '') {
        $outline = wp_json_encode($agent_1_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $brand = get_option('aaa_brand_name', '');
        $audience = get_option('aaa_target_audience', '');
        $tone = get_option('aaa_tone_voice', '');
        $home_url = home_url('/');
        $extra_info = trim((string) $extra_info);
        $prompt = "[ROLE]\nAnda adalah Konsultan Senior SEO (Topical Authority Specialist), Pakar Digital Marketing, dan Psikolog Perilaku Konsumen. Buat konten natural, kredibel, bermanfaat, human-written, tidak AI-sounding, dan tidak keyword stuffing.\n\n";
        $prompt .= "[BRAND CONTEXT]\nNama Brand: {$brand}\nTarget Audience: {$audience}\nTone of Voice: {$tone}\nInformasi tambahan: {$extra_info}\n\n";
        $prompt .= "[TASK]\nTulis artikel SEO mendalam minimal 800 kata untuk keyword utama '{$keyword}'. Gunakan riset dan outline berikut sebagai panduan: {$outline}.\n";
        $prompt .= "Wajib memasang hyperlink HTML <a href=\"{$home_url}\">{$keyword}</a> pada penyebutan pertama keyword di paragraf pembuka. Gunakan juga tautan internal yang relevan hanya bila konteks mendukung. Jangan menghapus atau mengubah tujuan hyperlink tersebut.\n\n";
        $prompt .= "[SEMANTIC SEO & E-E-A-T]\nIntegrasikan entitas dan variasi semantik yang relevan, jawab pertanyaan People Also Ask secara natural, gunakan struktur yang membantu topical relevance, sertakan konteks faktual dan insight praktis tanpa mengarang angka/sumber.\n\n";
        $prompt .= "[STRUCTURE]\nMulai dengan <h1> yang memuat keyword utama. Gunakan <h2>, <h3>, paragraf ringkas, kalimat aktif, dan bullet list bila berguna. Akhiri dengan CTA natural ke {$home_url} dan FAQ minimal 3 pertanyaan.\n\n";
        $prompt .= "[TECHNICAL RULES]\nOutput hanya HTML artikel WordPress: <h1>, <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>. Dilarang memakai <html>, <head>, <body>, <style>, CSS, atau komentar di luar artikel. Pertahankan semua tag <a href> dan atribut href secara utuh. Jangan mengubah hyperlink menjadi teks biasa.\n";
        $prompt .= "Setelah artikel, tambahkan tepat 5 tag SEO pada baris terpisah dengan format ### tag1, tag2, tag3, tag4, tag5.";
        return AAA_API::call_gemini($prompt, false);
    }

    // Agent 3: SEO Editor
    public static function run_agent_3($html_content, $keyword) {
        $prompt = "Anda adalah Agen SEO Editor. Perbaiki keterbacaan HTML ini untuk keyword '$keyword':\n\n$html_content\n\n";
        $prompt .= "Berikan output JSON murni dengan struktur:\n";
        $prompt .= "{\n\"revised_html\": \"...\",\n\"meta_desc\": \"...\",\n\"tags\": [\"tag1\", \"tag2\"]\n}\n";
        $prompt .= "PENTING: DILARANG menggunakan simbol hashtag/pagar (#) pada array tags. Format tag hanya berupa kata atau frasa biasa. Pertahankan seluruh hyperlink HTML <a href=...> beserta href persis seperti sumber; jangan pernah menghapus atau mengubah link. Pertahankan struktur SEO, FAQ minimal 3 pertanyaan, CTA ke beranda, dan heading H1/H2/H3.";
        
        return AAA_API::call_gemini($prompt, true);
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

        // Publikasi Otomatis (Atau Draft)
        $post_status = get_option('aaa_autopublish', 'draft'); 
        
        $post_id = wp_insert_post([
            'post_title'   => ucfirst($keyword),
            'post_content' => $final_html,
            'post_status'  => $post_status,
            'post_author'  => ($task && !empty($task->author_id)) ? absint($task->author_id) : (get_current_user_id() ?: absint(get_option('aaa_default_author', 1))),
            'post_excerpt' => isset($seo_edit['meta_desc']) ? sanitize_text_field($seo_edit['meta_desc']) : '',
            'post_category' => ($task && !empty($task->category_id)) ? [absint($task->category_id)] : []
        ], true);

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
    public static function generate_cluster_keywords($keyword) {
        $prompt = "Anda adalah Ahli SEO. Berikan tepat 5 turunan kata kunci (cluster/LSI) yang relevan, spesifik, dan memiliki search intent yang baik untuk topik utama: '{$keyword}'.\n";
        $prompt .= "PENTING: Berikan output WAJIB dalam format JSON murni berupa array string tanpa format markdown apapun. Contoh output: [\"keyword 1\", \"keyword 2\", \"keyword 3\", \"keyword 4\", \"keyword 5\"]";
        
        return AAA_API::call_gemini($prompt, true);
    }

}