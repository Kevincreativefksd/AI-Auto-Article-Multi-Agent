<?php
if (!defined('ABSPATH')) exit;

/**
 * Dashboard antrean (coba ulang / batalkan / hapus), pengaturan model per tahap + riset SERP + batas retry,
 * dan tema "blueprint minimalism" untuk semua halaman admin plugin.
 */
class AAA_Queue_Admin {

    const SLUG  = 'aaa-queue';
    const GROUP = 'aaa_models';

    public static function stages() {
        return [
            'serp'      => 'Riset SERP (butuh model dengan Google Search)',
            'agent_1'   => 'Agent 1: Outline',
            'agent_2'   => 'Agent 2: Penulis',
            'agent_3'   => 'Agent 3: SEO Editor',
            'humanizer' => 'Humanizer',
            'visual'    => 'Visual Agent',
        ];
    }

    private static function plugin_pages() {
        return ['ai-auto-article', 'aaa-visual-agent', self::SLUG];
    }

    private static function on_plugin_page() {
        return isset($_GET['page']) && in_array(sanitize_key($_GET['page']), self::plugin_pages(), true);
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu'], 20);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_post_aaa_task', [__CLASS__, 'handle_task']);
        add_action('admin_head', [__CLASS__, 'print_css']);
        add_filter('admin_body_class', function ($c) { return self::on_plugin_page() ? $c . ' aaa-bp-page' : $c; });
    }

    public static function add_menu() {
        add_submenu_page('ai-auto-article', 'Antrean & Model', 'Antrean & Model', 'manage_options', self::SLUG, [__CLASS__, 'render_page']);
    }

    public static function register() {
        foreach (array_keys(self::stages()) as $k) {
            register_setting(self::GROUP, "aaa_model_$k", ['type' => 'string', 'sanitize_callback' => function ($v) {
                return preg_replace('/[^A-Za-z0-9._\-]/', '', (string) $v);
            }]);
        }
        register_setting(self::GROUP, 'aaa_serp_enabled', ['type' => 'string', 'sanitize_callback' => function ($v) { return $v === '1' ? '1' : '0'; }]);
        register_setting(self::GROUP, 'aaa_max_retries', ['type' => 'integer', 'sanitize_callback' => function ($v) { return min(10, max(1, absint($v))); }]);
    }

    public static function handle_task() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('aaa_task_' . $id);
        global $wpdb;
        $t = AAA_DB::get_table_name();
        $do = sanitize_key($_GET['do'] ?? '');

        if ($do === 'retry') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT agent_data FROM $t WHERE id = %d", $id));
            if ($row) {
                $d = json_decode($row->agent_data, true) ?: [];
                $to = !empty($d['failed_at']) ? $d['failed_at'] : 'pending';
                unset($d['error_reason'], $d['failed_at']);
                $wpdb->update($t, ['status' => $to, 'attempts' => 0, 'last_error' => null, 'agent_data' => json_encode($d)], ['id' => $id]);
            }
        } elseif ($do === 'cancel') {
            $wpdb->update($t, ['status' => 'failed', 'last_error' => 'Dibatalkan manual'], ['id' => $id]);
        } elseif ($do === 'delete') {
            $wpdb->delete($t, ['id' => $id]);
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
        exit;
    }

    private static function action_link($do, $id, $label) {
        $url = wp_nonce_url(admin_url('admin-post.php?action=aaa_task&do=' . $do . '&id=' . (int) $id), 'aaa_task_' . (int) $id);
        return '<a class="aaa-btn" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        global $wpdb;
        $t = AAA_DB::get_table_name();
        $rows = $wpdb->get_results("SELECT id, keyword, status, attempts, last_error, post_id, created_at FROM $t ORDER BY id DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1>// Antrean &amp; Model</h1>
            <?php settings_errors(); ?>

            <h2>Antrean (100 terbaru)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:50px;">ID</th><th>Keyword</th><th style="width:110px;">Status</th><th style="width:60px;">Coba</th><th>Error terakhir</th><th style="width:240px;">Aksi</th></tr></thead>
                <tbody>
                <?php if (!$rows) : ?>
                    <tr><td colspan="6">Belum ada tugas.</td></tr>
                <?php else : foreach ($rows as $r) :
                    $cls = $r->status === 'failed' ? 'aaa-st-failed' : ($r->status === 'completed' ? 'aaa-st-ok' : 'aaa-st-run'); ?>
                    <tr>
                        <td><?php echo (int) $r->id; ?></td>
                        <td><?php echo esc_html($r->keyword); ?></td>
                        <td class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($r->status); ?></td>
                        <td><?php echo (int) $r->attempts; ?></td>
                        <td><?php echo esc_html($r->last_error); ?></td>
                        <td>
                            <?php
                            if ($r->status === 'failed') echo self::action_link('retry', $r->id, 'Coba ulang');
                            if (!in_array($r->status, ['failed', 'completed'], true)) echo self::action_link('cancel', $r->id, 'Batalkan');
                            echo self::action_link('delete', $r->id, 'Hapus');
                            ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2>Model &amp; Riset</h2>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Riset SERP</th>
                        <td><label><input type="checkbox" name="aaa_serp_enabled" value="1" <?php checked(get_option('aaa_serp_enabled', '1'), '1'); ?>> Aktifkan riset SERP sebelum Agent 1 (memakai API key Gemini yang sama)</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_max_retries">Maks. percobaan</label></th>
                        <td><input type="number" id="aaa_max_retries" min="1" max="10" name="aaa_max_retries" value="<?php echo esc_attr(get_option('aaa_max_retries', 3)); ?>">
                            <p class="description">Untuk error sementara (timeout, API error, JSON rusak). Setelah batas tercapai, tugas ditandai gagal dan bisa dicoba ulang dari tabel di atas.</p></td>
                    </tr>
                    <?php foreach (self::stages() as $k => $label) : ?>
                    <tr>
                        <th scope="row"><label for="aaa_model_<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input type="text" class="regular-text" id="aaa_model_<?php echo esc_attr($k); ?>" name="aaa_model_<?php echo esc_attr($k); ?>"
                                   value="<?php echo esc_attr(get_option("aaa_model_$k", '')); ?>"
                                   placeholder="<?php echo $k === 'serp' ? 'gemini-2.5-flash (bawaan)' : 'kosong = model global'; ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button('Simpan Pengaturan'); ?>
            </form>
        </div>
        <?php
    }

    // Tema blueprint minimalism: biru gambar teknik, grid tipis, garis 1px, monospace, tanpa bayangan/sudut bulat.
    public static function print_css() {
        if (!self::on_plugin_page()) return;
        ?>
        <style id="aaa-blueprint">
        body.aaa-bp-page{--aaa-bg:#0b2545;--aaa-ln:rgba(143,184,255,.35);--aaa-tx:#e6f0ff;--aaa-mut:#8fb8ff;--aaa-ac:#5ce1e6;--aaa-er:#ff6b6b;--aaa-ok:#7ee787;--aaa-wr:#ffd166}
        body.aaa-bp-page #wpcontent,body.aaa-bp-page #wpbody-content{background-color:var(--aaa-bg);background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:24px 24px}
        body.aaa-bp-page #wpbody-content{min-height:100vh;padding-bottom:40px}
        body.aaa-bp-page .wrap{color:var(--aaa-tx);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px}
        body.aaa-bp-page .wrap h1,body.aaa-bp-page .wrap h2,body.aaa-bp-page .wrap h3{color:var(--aaa-tx);font-weight:400;text-transform:uppercase;letter-spacing:.12em}
        body.aaa-bp-page .wrap h1{font-size:18px;border-bottom:1px solid var(--aaa-ln);padding-bottom:10px}
        body.aaa-bp-page .wrap h2{font-size:14px;margin-top:28px}
        body.aaa-bp-page .wrap h3{font-size:13px;color:var(--aaa-ac)}
        body.aaa-bp-page .wrap p,body.aaa-bp-page .wrap label,body.aaa-bp-page .wrap td,body.aaa-bp-page .wrap th{color:var(--aaa-tx)}
        body.aaa-bp-page .wrap .description{color:var(--aaa-mut)}
        body.aaa-bp-page .wrap a{color:var(--aaa-ac)}
        body.aaa-bp-page .wrap code{background:transparent;color:var(--aaa-ac);border:1px solid var(--aaa-ln);border-radius:0}
        body.aaa-bp-page .wrap .card,body.aaa-bp-page .wrap div[style*="background:#fff"]{background:rgba(11,37,69,.75)!important;border:1px solid var(--aaa-ln)!important;border-left:1px solid var(--aaa-ln)!important;border-radius:0!important;box-shadow:none!important;max-width:none}
        body.aaa-bp-page .wrap table.widefat,body.aaa-bp-page .wrap table.widefat td,body.aaa-bp-page .wrap table.widefat th{background:transparent;border-color:var(--aaa-ln);box-shadow:none;border-radius:0}
        body.aaa-bp-page .wrap table.widefat{border:1px solid var(--aaa-ln)}
        body.aaa-bp-page .wrap table.widefat thead th{color:var(--aaa-ac);text-transform:uppercase;letter-spacing:.1em;font-weight:400;border-bottom:1px solid var(--aaa-ln)}
        body.aaa-bp-page .wrap .striped>tbody>:nth-child(odd){background:rgba(255,255,255,.03)}
        body.aaa-bp-page .wrap input[type=text],body.aaa-bp-page .wrap input[type=password],body.aaa-bp-page .wrap input[type=number],body.aaa-bp-page .wrap select,body.aaa-bp-page .wrap textarea{background:transparent;color:var(--aaa-tx);border:1px solid var(--aaa-ln);border-radius:0;box-shadow:none;font-family:inherit}
        body.aaa-bp-page .wrap select option{background:var(--aaa-bg);color:var(--aaa-tx)}
        body.aaa-bp-page .wrap input:focus,body.aaa-bp-page .wrap select:focus,body.aaa-bp-page .wrap textarea:focus{border-color:var(--aaa-ac);box-shadow:none;outline:none}
        body.aaa-bp-page .wrap input[type=checkbox]{background:transparent;border:1px solid var(--aaa-mut);border-radius:0;box-shadow:none}
        body.aaa-bp-page .wrap input[type=checkbox]:checked::before{filter:invert(1) brightness(2)}
        body.aaa-bp-page .wrap .button,body.aaa-bp-page .wrap .aaa-btn{background:transparent;color:var(--aaa-ac);border:1px solid var(--aaa-ac);border-radius:0;box-shadow:none;text-shadow:none;text-decoration:none;font-family:inherit;display:inline-block;padding:2px 10px;line-height:2;min-height:0}
        body.aaa-bp-page .wrap .button:hover,body.aaa-bp-page .wrap .aaa-btn:hover{background:var(--aaa-ac);color:var(--aaa-bg)}
        body.aaa-bp-page .wrap .button-primary{background:var(--aaa-ac);color:var(--aaa-bg);font-weight:600}
        body.aaa-bp-page .wrap .button-primary:hover{background:transparent;color:var(--aaa-ac)}
        body.aaa-bp-page .wrap .updated,body.aaa-bp-page .wrap .notice{background:rgba(11,37,69,.85);border:1px solid var(--aaa-ln);border-left:3px solid var(--aaa-ac);box-shadow:none;color:var(--aaa-tx)}
        body.aaa-bp-page .wrap .notice-error{border-left-color:var(--aaa-er)}
        body.aaa-bp-page .wrap .notice-info{border-left-color:var(--aaa-wr)}
        body.aaa-bp-page .wrap .aaa-st-failed{color:var(--aaa-er)}
        body.aaa-bp-page .wrap .aaa-st-ok{color:var(--aaa-ok)}
        body.aaa-bp-page .wrap .aaa-st-run{color:var(--aaa-wr)}
        </style>
        <?php
    }
}
