<?php
if (!defined('ABSPATH')) exit;

class AAA_Cron {
    public static function init() {
        // Eksekusi langsung setiap kali wp-cron.php dipanggil dari luar (cron-job.org)
        if (isset($_GET['doing_wp_cron']) || (defined('DOING_CRON') && DOING_CRON) || wp_doing_cron()) {
            self::process_queue();
        }

        // Pertahankan jadwal internal WordPress sebagaicadangan
        add_filter('cron_schedules', [__CLASS__, 'add_cron_interval']);
        if (!wp_next_scheduled('aaa_process_queue_hook')) {
            wp_schedule_event(time(), 'aaa_every_two_minutes', 'aaa_process_queue_hook');
        }
        add_action('aaa_process_queue_hook', [__CLASS__, 'process_queue']);
    }

    public static function add_cron_interval($schedules) {
        $schedules['aaa_every_two_minutes'] = [
            'interval' => 120,
            'display'  => 'Setiap 2 Menit'
        ];
        return $schedules;
    }

    public static function clear_cron() {
        $timestamp = wp_next_scheduled('aaa_process_queue_hook');
        if ($timestamp) wp_unschedule_event($timestamp, 'aaa_process_queue_hook');
    }

    public static function process_queue() {
        global $wpdb;
        $table = AAA_DB::get_table_name();
        
        // Ambil 1 task yang belum selesai
        $task = $wpdb->get_row("SELECT * FROM $table WHERE status NOT IN ('completed', 'failed') ORDER BY created_at ASC LIMIT 1");
        if (!$task) return;

        $data = json_decode($task->agent_data, true) ?: [];
        $data['keyword'] = $task->keyword;

        // Kompatibilitas tugas lama yang masih berjalan: dulu hasil SEO Editor disimpan
        // di 'agent_4' (Agent 3 lama = Fact Checker sudah dihapus). Pindahkan ke 'agent_3'.
        if (isset($data['agent_4'])) {
            $data['agent_3'] = $data['agent_4'];
            unset($data['agent_4']);
        }
        $status = ($task->status === 'agent_4') ? 'agent_3' : $task->status;

        switch ($status) {
            case 'pending':
                // Riset SERP (opsional). Gagal riset tidak menggagalkan artikel.
                if (get_option('aaa_serp_enabled', '1') === '1') {
                    $serp = AAA_Agents::run_serp_research($task->keyword);
                    if (is_wp_error($serp)) {
                        $data['serp'] = '';
                        $data['serp_note'] = 'SERP dilewati: ' . $serp->get_error_message();
                    } else {
                        $data['serp'] = $serp;
                    }
                }
                self::update_task($task->id, 'agent_1', $data);
                break;

            case 'agent_1':
                $res = AAA_Agents::run_agent_1($task->keyword, $task->extra_info, $data['serp'] ?? '');
                if (is_wp_error($res)) { self::handle_error($task, $data, $res); return; }
                $data['agent_1'] = $res;
                self::update_task($task->id, 'agent_2', $data);
                break;

            case 'agent_2':
                $res = AAA_Agents::run_agent_2($task->keyword, $data['agent_1'], $task->extra_info);
                if (is_wp_error($res)) { self::handle_error($task, $data, $res); return; }
                // Pisahkan tag dari keluaran prompt lama agar tidak ikut tampil di isi artikel.
                $parts = preg_split('/###/', $res, 2);
                $data['agent_2_html'] = trim($parts[0]);
                if (!empty($parts[1])) {
                    $data['legacy_tags'] = array_values(array_filter(array_map('trim', preg_split('/[,|]/', $parts[1]))));
                }
                self::update_task($task->id, 'agent_3', $data);
                break;
                
            case 'agent_3':
                $res = AAA_Agents::run_agent_3($data['agent_2_html'], $task->keyword);
                if (is_wp_error($res)) { self::handle_error($task, $data, $res); return; }
                $data['agent_3'] = $res;
                // Humanizer opsional: jika dimatikan, langsung ke Quality Gate.
                $next = AAA_Humanizer::is_enabled() && !empty($res['revised_html']) ? 'humanizer' : self::after_editing_status();
                self::update_task($task->id, $next, $data);
                break;

            case 'news_write':
                // Mode Berita: satu sudut pandang per tugas; hasil dipakai langsung (tanpa SEO Editor dan Humanizer).
                $res = AAA_News::run_writer($task, $data);
                if (is_wp_error($res)) { self::handle_error($task, $data, $res); return; }
                $data['agent_3'] = ['revised_html' => $res['html'], 'meta_desc' => $res['meta_desc'], 'tags' => $res['tags']];
                $data['news']['title'] = $res['title'];
                $data['news']['unverified'] = $res['unverified'];
                unset($data['news']['raw']); // hemat ruang database
                // Humanizer opsional, sama seperti artikel biasa.
                $next = AAA_Humanizer::is_enabled() && !empty($res['html']) ? 'humanizer' : self::after_editing_status();
                self::update_task($task->id, $next, $data);
                break;

            case 'humanizer':
                $source_html = $data['agent_3']['revised_html'] ?? '';
                $is_news = !empty($data['news']) && is_array($data['news']);
                $kw = ($is_news && !empty($data['news']['keyword'])) ? $data['news']['keyword'] : (($is_news && !empty($data['news']['topic'])) ? $data['news']['topic'] : $task->keyword);
                $res = AAA_Humanizer::run($source_html, $kw, $is_news ? 'news' : '');
                if (is_wp_error($res)) {
                    // Kegagalan humanizer tidak menggagalkan artikel: lanjut dengan HTML SEO Editor.
                    $data['humanizer'] = ['used' => false, 'note' => 'API error: ' . $res->get_error_message()];
                } else {
                    $check = AAA_Humanizer::validate($source_html, $res);
                    $data['humanizer'] = ['used' => $check['used'], 'note' => $check['note']];
                    if ($check['used'] && $is_news) {
                        // Berita: tolak hasil humanizer yang menambah angka baru.
                        $added = AAA_News::numbers_not_in($source_html, $check['html']);
                        if (!empty($added)) {
                            $check['used'] = false;
                            $data['humanizer'] = ['used' => false, 'note' => 'Ditolak: menambah angka baru (' . implode(', ', array_slice($added, 0, 5)) . ')'];
                        }
                    }
                    if ($check['used']) {
                        $data['humanized_html'] = $check['html'];
                    }
                }
                self::update_task($task->id, self::after_editing_status(), $data);
                break;

            case 'visual_agent':
                // Visual Content Agent: berjalan per sub-tahap (plan, repair, render) agar request singkat.
                // Kegagalan gambar tidak pernah menggagalkan artikel; selalu berakhir di Quality Gate.
                $out = AAA_Visual_Agent::process($task, $data);
                if ($out === null) break; // sedang dikerjakan request lain
                self::update_task($task->id, $out['next'], $out['data']);
                break;

            case 'quality_gate':
                $res = AAA_Agents::run_agent_4_quality_gate($task->id, $data, $task);
                if (is_wp_error($res)) { 
                    self::handle_error($task, $data, $res); 
                } else {
                    $done = ['status' => 'completed', 'post_id' => $res];
                    if (!empty($data['news']['unverified'])) {
                        $done['last_error'] = 'Perlu verifikasi: angka ini tidak ada di artikel mentah (' . implode(', ', array_slice($data['news']['unverified'], 0, 8)) . '). Artikel disimpan sebagai draft.';
                    }
                    $wpdb->update($table, $done, ['id' => $task->id]);
                }
                break;
        }
    }

    // Status setelah penyuntingan selesai (SEO Editor atau Humanizer): Visual Content Agent bila aktif, jika tidak Quality Gate.
    private static function after_editing_status() {
        return (class_exists('AAA_Visual_Agent') && AAA_Visual_Agent::is_enabled()) ? 'visual_agent' : 'quality_gate';
    }

    private static function update_task($id, $status, $data) {
        global $wpdb;
        $wpdb->update(AAA_DB::get_table_name(), ['status' => $status, 'agent_data' => json_encode($data), 'attempts' => 0, 'last_error' => null], ['id' => $id]);
    }

    // Retry otomatis untuk error sementara; data tahap sebelumnya dipertahankan agar bisa dicoba ulang dari dashboard.
    private static function handle_error($task, $data, $err) {
        global $wpdb;
        $table = AAA_DB::get_table_name();
        $max = max(1, (int) get_option('aaa_max_retries', 3));
        $n = (int) $task->attempts + 1;
        $msg = $err->get_error_message();
        $retryable = in_array($err->get_error_code(), ['http_request_failed', 'api_error', 'empty_response', 'json_error'], true);
        if ($retryable && $n < $max) {
            $wpdb->update($table, ['attempts' => $n, 'last_error' => $msg], ['id' => $task->id]);
            return; // status tetap; dicoba lagi pada siklus cron berikutnya
        }
        $data['error_reason'] = $msg;
        $data['failed_at'] = $task->status;
        $wpdb->update($table, ['status' => 'failed', 'attempts' => $n, 'last_error' => $msg, 'agent_data' => json_encode($data)], ['id' => $task->id]);
    }

    private static function fail($id, $reason) {
        global $wpdb;
        $data = json_encode(['error_reason' => $reason]);
        $wpdb->update(AAA_DB::get_table_name(), ['status' => 'failed', 'agent_data' => $data], ['id' => $id]);
    }
}