<?php
if (!defined('ABSPATH')) exit;

/**
 * Dashboard antrean (coba ulang / batalkan / hapus) dan tema "blueprint minimalism" untuk semua halaman admin plugin.
 */
class AAA_Queue_Admin {

    const SLUG  = 'aaa-queue';
    const GROUP = 'aaa_models';

    private static function plugin_pages() {
        return ['ai-auto-article', 'aaa-visual-agent', 'aaa-agents', self::SLUG];
    }

    private static function on_plugin_page() {
        return isset($_GET['page']) && in_array(sanitize_key($_GET['page']), self::plugin_pages(), true);
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu'], 20);
        add_action('admin_post_aaa_task', [__CLASS__, 'handle_task']);
        add_action('admin_head', [__CLASS__, 'print_css']);
        add_filter('admin_body_class', function ($c) { return self::on_plugin_page() ? $c . ' aaa-bp-page' : $c; });
    }

    public static function add_menu() {
        add_submenu_page('ai-auto-article', 'Antrean', 'Antrean', 'manage_options', self::SLUG, [__CLASS__, 'render_page']);
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
            <h1>Antrean artikel</h1>
            <?php settings_errors(); ?>

            <h2>100 tugas terbaru</h2>
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

        </div>
        <?php
    }

    // Tema blueprint minimalism: biru gambar teknik, grid tipis, garis 1px, monospace, tanpa bayangan/sudut bulat.
    public static function print_css() {
        if (!self::on_plugin_page()) return;
        ?>
        <style id="aaa-blueprint">
body.aaa-bp-page{--bp-bg:#0b2a5b;--bp-panel:#123a75;--bp-field:#06183a;--bp-line:#9cc3ff;--bp-soft:#4f7fc9;--bp-tx:#f2f7ff;--bp-mut:#b4cdf7;--bp-ac:#3de8ff;--bp-ink:#04132e;--bp-hi:#ffc233;--bp-er:#ff7a7a;--bp-ok:#62e69a}
body.aaa-bp-page #wpcontent,body.aaa-bp-page #wpbody-content{background-color:var(--bp-bg);background-image:linear-gradient(rgba(255,255,255,.14) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.14) 1px,transparent 1px),linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);background-size:120px 120px,120px 120px,24px 24px,24px 24px}
body.aaa-bp-page #wpbody-content{min-height:100vh;padding-bottom:48px}
body.aaa-bp-page .wrap{color:var(--bp-tx);font-size:14px;line-height:1.55;max-width:1100px}
body.aaa-bp-page .wrap h1,body.aaa-bp-page .wrap h2,body.aaa-bp-page .wrap h3,body.aaa-bp-page .wrap th{font-family:"Bahnschrift","DIN Alternate","Segoe UI",system-ui,sans-serif}
body.aaa-bp-page .wrap h1{color:#fff;font-size:26px;font-weight:700;margin:20px 0 22px;padding-bottom:12px;border-bottom:2px solid var(--bp-ac)}
body.aaa-bp-page .wrap h2{display:flex;align-items:center;gap:14px;color:var(--bp-tx);font-size:16px;font-weight:700;margin:0 0 14px;padding:0}
body.aaa-bp-page .wrap h2::after{content:"";flex:1;height:11px;border-left:1px solid var(--bp-line);border-right:1px solid var(--bp-line);background:linear-gradient(var(--bp-line),var(--bp-line)) center/100% 1px no-repeat}
body.aaa-bp-page .wrap h3{color:var(--bp-ac);font-size:15px;font-weight:700;margin:0 0 12px}
body.aaa-bp-page .wrap p,body.aaa-bp-page .wrap label,body.aaa-bp-page .wrap td,body.aaa-bp-page .wrap th,body.aaa-bp-page .wrap li{color:var(--bp-tx)}
body.aaa-bp-page .wrap .description,body.aaa-bp-page .wrap p.description{color:var(--bp-mut)}
body.aaa-bp-page .wrap a{color:var(--bp-ac)}
body.aaa-bp-page .wrap a:hover{color:var(--bp-hi)}
body.aaa-bp-page .wrap .card,body.aaa-bp-page .wrap .aaa-card,body.aaa-bp-page .wrap div[style*="background:#fff"]{background:var(--bp-panel)!important;border:1px solid var(--bp-line)!important;border-radius:0!important;box-shadow:none!important;max-width:none;padding:20px!important;margin:0 0 20px}
body.aaa-bp-page .wrap code,body.aaa-bp-page .wrap pre{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:var(--bp-field);color:var(--bp-hi);border:1px solid var(--bp-soft);border-radius:0;padding:1px 6px}
body.aaa-bp-page .wrap pre{padding:10px 12px;white-space:pre-wrap;color:var(--bp-hi);border:1px dashed var(--bp-hi)}
body.aaa-bp-page .wrap .aaa-vars code{margin-right:4px;cursor:help}
body.aaa-bp-page .wrap table.widefat,body.aaa-bp-page .wrap table.widefat td,body.aaa-bp-page .wrap table.widefat th,body.aaa-bp-page .wrap .form-table th,body.aaa-bp-page .wrap .form-table td{background:transparent;border-radius:0;box-shadow:none}
body.aaa-bp-page .wrap table.widefat{border:1px solid var(--bp-line);background:var(--bp-panel)}
body.aaa-bp-page .wrap table.widefat td,body.aaa-bp-page .wrap table.widefat th{border-color:var(--bp-soft)}
body.aaa-bp-page .wrap table.widefat thead th{background:var(--bp-field);color:var(--bp-ac);font-weight:700;border-bottom:2px solid var(--bp-ac)}
body.aaa-bp-page .wrap .striped>tbody>:nth-child(odd){background:rgba(6,24,58,.45)}
body.aaa-bp-page .wrap input[type=text],body.aaa-bp-page .wrap input[type=password],body.aaa-bp-page .wrap input[type=number],body.aaa-bp-page .wrap select,body.aaa-bp-page .wrap textarea{background:var(--bp-field);color:#fff;border:1px solid #cfe3ff;border-radius:0;box-shadow:none;min-height:34px}
body.aaa-bp-page .wrap select{max-width:100%}
body.aaa-bp-page .wrap select option{background:var(--bp-field);color:#fff}
body.aaa-bp-page .wrap textarea.aaa-prompt,body.aaa-bp-page .wrap .aaa-model-select{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px}
body.aaa-bp-page .wrap textarea.aaa-prompt{width:100%;min-height:240px;line-height:1.55}
body.aaa-bp-page .wrap .aaa-model-select{min-width:320px}
body.aaa-bp-page .wrap input:focus,body.aaa-bp-page .wrap select:focus,body.aaa-bp-page .wrap textarea:focus,body.aaa-bp-page .wrap .button:focus,body.aaa-bp-page .wrap .aaa-btn:focus{border-color:var(--bp-hi);outline:2px solid var(--bp-hi);outline-offset:1px;box-shadow:none}
body.aaa-bp-page .wrap input[type=checkbox]{accent-color:var(--bp-ac);background:var(--bp-field);border:1px solid #cfe3ff;border-radius:0;box-shadow:none}
body.aaa-bp-page .wrap input[type=checkbox]:checked::before{filter:brightness(0) invert(1)}
body.aaa-bp-page .wrap .aaa-checklist{background:var(--bp-field);border:1px solid #cfe3ff;max-height:190px;overflow:auto;padding:6px 12px;margin:0 0 16px}
body.aaa-bp-page .wrap .aaa-checklist label{display:block;padding:5px 0;border-bottom:1px solid rgba(156,195,255,.25)}
body.aaa-bp-page .wrap .aaa-checklist label:last-child{border-bottom:0}
body.aaa-bp-page .wrap .button,body.aaa-bp-page .wrap .aaa-btn{background:transparent;color:var(--bp-tx);border:1px solid var(--bp-tx);border-radius:0;box-shadow:none;text-shadow:none;text-decoration:none;display:inline-block;padding:2px 12px;line-height:2;min-height:0;font-weight:600}
body.aaa-bp-page .wrap .button:hover,body.aaa-bp-page .wrap .aaa-btn:hover{background:var(--bp-tx);color:var(--bp-bg)}
body.aaa-bp-page .wrap .button-primary{background:var(--bp-ac);border-color:var(--bp-ac);color:var(--bp-ink);font-weight:700}
body.aaa-bp-page .wrap .button-primary:hover{background:var(--bp-hi);border-color:var(--bp-hi);color:var(--bp-ink)}
body.aaa-bp-page .wrap .aaa-btn[href*="do=retry"]{color:var(--bp-ac);border-color:var(--bp-ac)}
body.aaa-bp-page .wrap .aaa-btn[href*="do=retry"]:hover{background:var(--bp-ac);color:var(--bp-ink)}
body.aaa-bp-page .wrap .aaa-btn[href*="do=cancel"]{color:var(--bp-hi);border-color:var(--bp-hi)}
body.aaa-bp-page .wrap .aaa-btn[href*="do=cancel"]:hover{background:var(--bp-hi);color:var(--bp-ink)}
body.aaa-bp-page .wrap .aaa-btn[href*="do=delete"]{color:var(--bp-er);border-color:var(--bp-er)}
body.aaa-bp-page .wrap .aaa-btn[href*="do=delete"]:hover{background:var(--bp-er);color:var(--bp-ink)}
body.aaa-bp-page .wrap .updated,body.aaa-bp-page .wrap .notice{background:var(--bp-field);border:1px solid var(--bp-line);border-left:5px solid var(--bp-ok);box-shadow:none;color:var(--bp-tx)}
body.aaa-bp-page .wrap .notice-error{border-left-color:var(--bp-er)}
body.aaa-bp-page .wrap .notice-info{border-left-color:var(--bp-ac)}
body.aaa-bp-page .wrap .notice-warning{border-left-color:var(--bp-hi)}
body.aaa-bp-page .wrap .aaa-tag{display:inline-block;margin-left:8px;padding:0 8px;border:1px solid var(--bp-soft);color:var(--bp-mut);font-size:12px}
body.aaa-bp-page .wrap .aaa-tag-custom{border-color:var(--bp-hi);color:var(--bp-hi)}
body.aaa-bp-page .wrap .aaa-st-failed{color:var(--bp-er);font-weight:700}
body.aaa-bp-page .wrap .aaa-st-ok{color:var(--bp-ok);font-weight:700}
body.aaa-bp-page .wrap .aaa-st-run{color:var(--bp-hi);font-weight:700}
        </style>
        <?php
    }
}
