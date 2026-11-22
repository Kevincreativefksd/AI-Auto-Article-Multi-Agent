<?php
if (!defined('ABSPATH')) exit;

/**
 * Template prompt per agent yang bisa diedit dari admin.
 * - Bagian yang bisa diedit: "template" (opsi aaa_prompt_{tahap}); kosong = prompt bawaan.
 * - Bagian "kontrak" (format output JSON, pemisah ###) selalu ditambahkan otomatis agar parser tidak rusak.
 * - Placeholder berbentuk {nama} diganti saat dijalankan.
 */
class AAA_Prompts {

    const OPT = 'aaa_prompt_';

    public static function defs() {
        return [
            'serp' => [
                'label'    => 'Riset SERP',
                'vars'     => ['{keyword}' => 'kata kunci target'],
                'required' => ['{keyword}'],
                'contract' => '',
            ],
            'agent_1' => [
                'label'    => 'Agent 1: Outline',
                'vars'     => ['{keyword}' => 'kata kunci', '{extra_info}' => 'brief tambahan', '{brand}' => 'nama brand', '{audience}' => 'target audiens', '{serp}' => 'data riset SERP (kosong bila riset mati atau gagal)'],
                'required' => ['{keyword}'],
                'contract' => self::contract_agent_1(),
            ],
            'agent_2' => [
                'label'    => 'Agent 2: Penulis',
                'vars'     => ['{keyword}' => 'kata kunci', '{outline}' => 'hasil Agent 1 (JSON)', '{extra_info}' => 'brief tambahan', '{brand}' => 'nama brand', '{audience}' => 'target audiens', '{tone}' => 'tone of voice', '{home_url}' => 'URL beranda'],
                'required' => ['{keyword}', '{outline}'],
                'contract' => 'Setelah artikel, tambahkan tepat 5 tag SEO pada baris terpisah dengan format ### tag1, tag2, tag3, tag4, tag5.',
            ],
            'agent_3' => [
                'label'    => 'Agent 3: SEO Editor',
                'vars'     => ['{keyword}' => 'kata kunci', '{html}' => 'artikel HTML dari Agent 2'],
                'required' => ['{html}'],
                'contract' => self::contract_agent_3(),
            ],
            'humanizer' => [
                'label'    => 'Humanizer',
                'vars'     => ['{keyword}' => 'kata kunci', '{brand}' => 'nama brand', '{tone}' => 'tone of voice'],
                'required' => [],
                'contract' => self::contract_humanizer(),
            ],
            'cluster' => [
                'label'    => 'Generator Cluster',
                'vars'     => ['{keyword}' => 'topik utama', '{count}' => 'jumlah turunan yang dipilih'],
                'required' => ['{keyword}'],
                'contract' => self::contract_cluster(),
            ],
        ];
    }

    public static function default_for($stage) {
        switch ($stage) {
            case 'serp':      return self::default_serp();
            case 'agent_1':   return self::default_agent_1();
            case 'agent_2':   return self::default_agent_2();
            case 'agent_3':   return self::default_agent_3();
            case 'humanizer': return "Anda adalah Agen Humanizer, editor bahasa Indonesia berpengalaman.\n\n" . AAA_Humanizer::default_rules();
            case 'cluster':   return self::default_cluster();
        }
        return '';
    }

    public static function get($stage) {
        $v = trim((string) get_option(self::OPT . $stage, ''));
        return $v !== '' ? $v : self::default_for($stage);
    }

    public static function is_custom($stage) {
        return trim((string) get_option(self::OPT . $stage, '')) !== '';
    }

    // Template + kontrak, dengan placeholder diganti.
    public static function render($stage, array $vars) {
        $defs = self::defs();
        $map = [];
        foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string) $v;
        $body = strtr(self::get($stage), $map);
        $contract = $defs[$stage]['contract'] ?? '';
        if ($contract !== '') {
            $body = rtrim($body) . "\n" . strtr($contract, $map);
        }
        return $body;
    }

    // Validasi saat disimpan: placeholder wajib harus ada; sama dengan bawaan = kosongkan (ikut pembaruan bawaan).
    public static function sanitize($stage, $value) {
        $defs = self::defs();
        $v = trim(str_replace(["\0", "\r\n", "\r"], ['', "\n", "\n"], (string) $value));
        if ($v === '' || $v === trim(self::default_for($stage))) return '';
        if (function_exists('mb_substr') && mb_strlen($v) > 20000) $v = mb_substr($v, 0, 20000);
        foreach ($defs[$stage]['required'] as $ph) {
            if (strpos($v, $ph) === false) {
                add_settings_error('aaa_agents', 'aaa_prompt_' . $stage,
                    'Prompt "' . $defs[$stage]['label'] . '" tidak disimpan: wajib memuat ' . $ph . '. Prompt sebelumnya dipertahankan.', 'error');
                return (string) get_option(self::OPT . $stage, '');
            }
        }
        return $v;
    }

    // ---------- Prompt bawaan (sama dengan versi 2.4.x) ----------

    private static function default_serp() {
        return <<<'TXT'
Gunakan Google Search. Untuk keyword '{keyword}' (pasar Indonesia), ringkas dalam bahasa Indonesia: (1) 5 judul teratas di hasil pencarian, (2) heading H2 yang umum dipakai pesaing, (3) 5 pertanyaan terkait yang sering dicari, (4) celah konten yang belum dibahas pesaing. Format poin singkat, tanpa kalimat pembuka, jangan mengarang angka atau sumber.
TXT;
    }

    private static function default_agent_1() {
        return <<<'TXT'
Anda adalah Agen SEO Researcher untuk blog ekonomi '{brand}'. Audiens: '{audience}'.
Tugas: Analisis keyword '{keyword}' dan info tambahan '{extra_info}'.
{serp}
TXT;
    }

    private static function contract_agent_1() {
        return <<<'TXT'
Berikan output berformat JSON murni dengan struktur: {"search_intent":"...", "target_pembaca":"...", "outline_h2_h3":["..."], "lsi_keywords":["..."]}
TXT;
    }

    private static function default_agent_2() {
        return <<<'TXT'
[ROLE]
Anda adalah Konsultan Senior SEO (Topical Authority Specialist), Pakar Digital Marketing, dan Psikolog Perilaku Konsumen. Buat konten natural, kredibel, bermanfaat, human-written, tidak AI-sounding, dan tidak keyword stuffing.

[BRAND CONTEXT]
Nama Brand: {brand}
Target Audience: {audience}
Tone of Voice: {tone}
Informasi tambahan: {extra_info}

[TASK]
Tulis artikel SEO mendalam minimal 800 kata untuk keyword utama '{keyword}'. Gunakan riset dan outline berikut sebagai panduan: {outline}.
Wajib memasang hyperlink HTML <a href="{home_url}">{keyword}</a> pada penyebutan pertama keyword di paragraf pembuka. Gunakan juga tautan internal yang relevan hanya bila konteks mendukung. Jangan menghapus atau mengubah tujuan hyperlink tersebut.

[SEMANTIC SEO & E-E-A-T]
Integrasikan entitas dan variasi semantik yang relevan, jawab pertanyaan People Also Ask secara natural, gunakan struktur yang membantu topical relevance, sertakan konteks faktual dan insight praktis tanpa mengarang angka/sumber.

[STRUCTURE]
Mulai dengan <h1> yang memuat keyword utama. Gunakan <h2>, <h3>, paragraf ringkas, kalimat aktif, dan bullet list bila berguna. Akhiri dengan CTA natural ke {home_url} dan FAQ minimal 3 pertanyaan.

[TECHNICAL RULES]
Output hanya HTML artikel WordPress: <h1>, <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>. Dilarang memakai <html>, <head>, <body>, <style>, CSS, atau komentar di luar artikel. Pertahankan semua tag <a href> dan atribut href secara utuh. Jangan mengubah hyperlink menjadi teks biasa.
TXT;
    }

    private static function default_agent_3() {
        return <<<'TXT'
Anda adalah Agen SEO Editor. Perbaiki keterbacaan HTML ini untuk keyword '{keyword}':

{html}

PENTING: DILARANG menggunakan simbol hashtag/pagar (#) pada array tags. Format tag hanya berupa kata atau frasa biasa. Pertahankan seluruh hyperlink HTML <a href=...> beserta href persis seperti sumber; jangan pernah menghapus atau mengubah link. Pertahankan struktur SEO, FAQ minimal 3 pertanyaan, CTA ke beranda, dan heading H1/H2/H3.
TXT;
    }

    private static function contract_agent_3() {
        return <<<'TXT'
Berikan output JSON murni dengan struktur:
{
"revised_html": "...",
"meta_desc": "...",
"tags": ["tag1", "tag2"]
}
TXT;
    }

    private static function contract_humanizer() {
        return <<<'TXT'
Keyword utama artikel: '{keyword}'.

ARTIKEL YANG HARUS DIHUMANISASI:
<<<ARTIKEL
{html}
ARTIKEL>>>

Keluarkan HANYA HTML artikel final yang sudah ditulis ulang, tanpa penjelasan, tanpa daftar pola, tanpa markdown.
TXT;
    }

    private static function default_cluster() {
        return <<<'TXT'
Anda adalah Ahli SEO. Berikan tepat {count} turunan kata kunci (cluster/LSI) yang relevan, spesifik, dan memiliki search intent yang baik untuk topik utama: '{keyword}'.
TXT;
    }

    private static function contract_cluster() {
        return <<<'TXT'
PENTING: Berikan output WAJIB dalam format JSON murni berupa array string tanpa format markdown apapun. Contoh format output: ["keyword 1", "keyword 2"] (jumlah elemen harus {count}).
TXT;
    }
}
