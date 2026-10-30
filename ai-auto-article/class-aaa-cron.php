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
                $res = AAA_Agents::run_agent_1($task->keyword, $task->extra_info);
                if (is_wp_error($res)) { self::fail($task->id, $res->get_error_message()); return; }
                $data['agent_1'] = $res;
                self::update_task($task->id, 'agent_2', $data);
                break;
                
            case 'agent_2':
                $res = AAA_Agents::run_agent_2($task->keyword, $data['agent_1'], $task->extra_info);
                if (is_wp_error($res)) { self::fail($task->id, $res->get_error_message()); return; }
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
                if (is_wp_error($res)) { self::fail($task->id, $res->get_error_message()); return; }
                $data['agent_3'] = $res;
                // Humanizer opsional: jika dimatikan, langsung ke Quality Gate.
                $next = AAA_Humanizer::is_enabled() && !empty($res['revised_html']) ? 'humanizer' : 'quality_gate';
                self::update_task($task->id, $next, $data);
                break;

            case 'humanizer':
                $source_html = $data['agent_3']['revised_html'] ?? '';
                $res = AAA_Humanizer::run($source_html, $task->keyword);
                if (is_wp_error($res)) {
                    // Kegagalan humanizer tidak menggagalkan artikel: lanjut dengan HTML SEO Editor.
                    $data['humanizer'] = ['used' => false, 'note' => 'API error: ' . $res->get_error_message()];
                } else {
                    $check = AAA_Humanizer::validate($source_html, $res);
                    $data['humanizer'] = ['used' => $check['used'], 'note' => $check['note']];
                    if ($check['used']) {
                        $data['humanized_html'] = $check['html'];
                    }
                }
                self::update_task($task->id, 'quality_gate', $data);
                break;

            case 'quality_gate':
                $res = AAA_Agents::run_agent_4_quality_gate($task->id, $data, $task);
                if (is_wp_error($res)) { 
                    self::fail($task->id, $res->get_error_message()); 
                } else {
                    $wpdb->update($table, ['status' => 'completed', 'post_id' => $res], ['id' => $task->id]);
                }
                break;
        }
    }

    private static function update_task($id, $status, $data) {
        global $wpdb;
        $wpdb->update(AAA_DB::get_table_name(), ['status' => $status, 'agent_data' => json_encode($data)], ['id' => $id]);
    }

    private static function fail($id, $reason) {
        global $wpdb;
        $data = json_encode(['error_reason' => $reason]);
        $wpdb->update(AAA_DB::get_table_name(), ['status' => 'failed', 'agent_data' => $data], ['id' => $id]);
    }
}