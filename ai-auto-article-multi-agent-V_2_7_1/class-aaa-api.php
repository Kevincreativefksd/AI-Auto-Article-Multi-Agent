<?php
if (!defined('ABSPATH')) exit;

class AAA_API {
    public static function call_gemini($prompt, $is_json = false, $model = null, $grounded = false) {
        $api_key = get_option('aaa_gemini_api');
        $model_name = $model ?: get_option('aaa_gemini_model', 'gemini-1.5-flash');
        
        if (!$api_key) return new WP_Error('no_api_key', 'API Key belum diatur.');

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $api_key;
        
        $body = [
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => [
                "temperature" => 0.7,
                "maxOutputTokens" => 8000
            ]
        ];

        // Paksa Gemini API menggunakan skema JSON murni
        // Mode grounding (Google Search) tidak dipakai bersamaan dengan mode JSON paksa.
        if ($is_json && !$grounded) {
            $body["generationConfig"]["responseMimeType"] = "application/json";
        }
        if ($grounded) {
            $body["tools"] = [["google_search" => new stdClass()]];
        }

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body),
            'timeout' => 60, 
            'sslverify' => true
        ]);

        if (is_wp_error($response)) return $response;

        $response_code = wp_remote_retrieve_response_code($response);
        $body_res = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $msg = isset($body_res['error']['message']) ? $body_res['error']['message'] : 'Error ' . $response_code;
            return new WP_Error('api_error', $msg);
        }

        if (isset($body_res['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($body_res['candidates'][0]['content']['parts'][0]['text']);
            
            if ($is_json) {
                // Bersihkan tag markdown jika AI masih ngeyel
                $text = preg_replace('/^```json\s*/i', '', $text);$text = preg_replace('/```\s*$/i', '', $text);
                
                $json_decoded = json_decode($text, true);
                if ($json_decoded === null) {
                    return new WP_Error('json_error', 'Validasi gagal: Gemini tidak memberikan struktur JSON yang dapat dibaca.');
                }
                return $json_decoded;
            }
            
            return $text;
        }

        return new WP_Error('empty_response', 'Respons Gemini kosong.');
    }

    // ---------- Daftar model untuk dropdown ----------
    public static function static_models() {
        return ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'];
    }

    public static function get_models() {
        $cache = get_option('aaa_model_list');
        $list = (is_array($cache) && !empty($cache['models'])) ? $cache['models'] : self::static_models();
        return array_values(array_unique(array_filter(array_map('strval', $list))));
    }

    // Ambil daftar model yang mendukung generateContent dari API; mengembalikan daftar atau WP_Error.
    public static function refresh_models() {
        $api_key = get_option('aaa_gemini_api');
        if (!$api_key) return new WP_Error('no_api_key', 'API key Gemini belum diisi.');
        $res = wp_remote_get('https://generativelanguage.googleapis.com/v1beta/models?pageSize=200', [
            'timeout' => 30,
            'headers' => ['x-goog-api-key' => $api_key],
        ]);
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if ($code !== 200 || empty($body['models'])) {
            return new WP_Error('list_failed', 'Respons API tidak valid (HTTP ' . (int) $code . ').');
        }
        $ids = [];
        foreach ($body['models'] as $m) {
            $name = isset($m['name']) ? preg_replace('#^models/#', '', $m['name']) : '';
            $methods = $m['supportedGenerationMethods'] ?? [];
            if ($name !== '' && strpos($name, 'gemini') === 0 && in_array('generateContent', $methods, true)) $ids[] = $name;
        }
        $ids = array_values(array_unique($ids));
        rsort($ids, SORT_NATURAL);
        if (empty($ids)) return new WP_Error('list_empty', 'Tidak ada model Gemini yang mendukung generateContent.');
        update_option('aaa_model_list', ['time' => time(), 'models' => $ids], false);
        return $ids;
    }
}