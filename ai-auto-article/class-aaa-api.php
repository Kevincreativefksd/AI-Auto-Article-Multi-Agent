<?php
if (!defined('ABSPATH')) exit;

class AAA_API {
    public static function call_gemini($prompt, $is_json = false) {
        $api_key = get_option('aaa_gemini_api');
        $model_name = get_option('aaa_gemini_model', 'gemini-1.5-flash');
        
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
        if ($is_json) {
            $body["generationConfig"]["responseMimeType"] = "application/json";
        }

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body),
            'timeout' => 60, 
            'sslverify' => false
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
}