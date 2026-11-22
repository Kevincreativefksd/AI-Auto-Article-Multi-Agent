<?php
if (!defined('ABSPATH')) exit;

/**
 * Halaman "Pengaturan Agent": model hybrid per agent (dropdown), edit prompt per agent,
 * riset SERP dan batas retry.
 */
class AAA_Agent_Settings {

    const SLUG  = 'aaa-agents';
    const GROUP = 'aaa_agents';

    // tahap => [judul, deskripsi, punya prompt?]
    public static function agents() {
        return [
            'serp'      => ['Riset SERP', 'Meriset hasil pencarian Google untuk kata kunci sebelum outline dibuat. Model harus mendukung Google Search.', true],
            'agent_1'   => ['Agent 1: Outline', 'Menganalisis search intent dan menyusun outline serta kata kunci LSI.', true],
            'agent_2'   => ['Agent 2: Penulis', 'Menulis artikel HTML lengkap dari outline.', true],
            'agent_3'   => ['Agent 3: SEO Editor', 'Merapikan artikel, membuat meta description dan tag.', true],
            'humanizer' => ['Humanizer', 'Menulis ulang agar tidak terdengar seperti tulisan AI (jika diaktifkan).', true],
            'cluster'   => ['Generator Cluster', 'Membuat turunan kata kunci untuk mode Cluster Post.', true],
            'visual'    => ['Visual Agent', 'Merancang gambar artikel. Prompt tidak bisa diedit karena formatnya terikat renderer gambar.', false],
        ];
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu'], 15);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_post_aaa_refresh_models', [__CLASS__, 'handle_refresh']);
    }

    public static function add_menu() {
        add_submenu_page('ai-auto-article', 'Pengaturan Agent', 'Pengaturan Agent', 'manage_options', self::SLUG, [__CLASS__, 'render_page']);
    }

    public static function register() {
        foreach (self::agents() as $k => $a) {
            register_setting(self::GROUP, "aaa_model_$k", ['type' => 'string', 'sanitize_callback' => [__CLASS__, 'sanitize_model']]);
            if ($a[2]) {
                register_setting(self::GROUP, AAA_Prompts::OPT . $k, ['type' => 'string', 'sanitize_callback' => function ($v) use ($k) {
                    return AAA_Prompts::sanitize($k, $v);
                }]);
            }
        }
        register_setting(self::GROUP, 'aaa_serp_enabled', ['type' => 'string', 'sanitize_callback' => function ($v) { return $v === '1' ? '1' : '0'; }]);
        register_setting(self::GROUP, 'aaa_max_retries', ['type' => 'integer', 'sanitize_callback' => function ($v) { return min(10, max(1, absint($v))); }]);
    }

    public static function sanitize_model($v) {
        return preg_replace('/[^A-Za-z0-9._\-]/', '', (string) $v);
    }

    public static function handle_refresh() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('aaa_refresh_models');
        $res = AAA_API::refresh_models();
        $args = is_wp_error($res) ? ['aaa_refresh_error' => rawurlencode($res->get_error_message())] : ['aaa_refreshed' => count($res)];
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }

    // Dropdown model; $allow_inherit menambah pilihan "ikuti model global".
    public static function model_select($name, $current, $allow_inherit = true, $id = '') {
        $current = (string) $current;
        $models = AAA_API::get_models();
        if ($current !== '' && !in_array($current, $models, true)) array_unshift($models, $current);
        $global = get_option('aaa_gemini_model', 'gemini-1.5-flash');
        $id = $id !== '' ? $id : $name;
        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" class="aaa-model-select">';
        if ($allow_inherit) {
            $html .= '<option value=""' . selected($current, '', false) . '>Ikuti model global (' . esc_html($global) . ')</option>';
        }
        foreach ($models as $m) {
            $html .= '<option value="' . esc_attr($m) . '"' . selected($current, $m, false) . '>' . esc_html($m) . '</option>';
        }
        return $html . '</select>';
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $defs = AAA_Prompts::defs();
        $cache = get_option('aaa_model_list');
        $refresh_url = wp_nonce_url(admin_url('admin-post.php?action=aaa_refresh_models'), 'aaa_refresh_models');
        ?>
        <div class="wrap">
            <h1>Pengaturan agent</h1>
            <?php
            settings_errors('aaa_agents');
            if (isset($_GET['aaa_refreshed'])) {
                echo '<div class="notice notice-success"><p>Daftar model diperbarui: ' . (int) $_GET['aaa_refreshed'] . ' model tersedia.</p></div>';
            }
            if (isset($_GET['aaa_refresh_error'])) {
                echo '<div class="notice notice-error"><p>Gagal memuat daftar model: ' . esc_html(rawurldecode(wp_unslash($_GET['aaa_refresh_error']))) . '. Daftar bawaan tetap dipakai.</p></div>';
            }
            ?>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <div class="aaa-card">
                    <h2>Umum</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Riset SERP</th>
                            <td><label><input type="checkbox" name="aaa_serp_enabled" value="1" <?php checked(get_option('aaa_serp_enabled', '1'), '1'); ?>> Aktifkan riset SERP sebelum Agent 1</label></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aaa_max_retries">Maksimal percobaan</label></th>
                            <td><input type="number" id="aaa_max_retries" min="1" max="10" name="aaa_max_retries" value="<?php echo esc_attr(get_option('aaa_max_retries', 3)); ?>">
                                <p class="description">Untuk error sementara (timeout, API error, JSON rusak). Setelah batas tercapai, tugas ditandai gagal dan bisa dicoba ulang dari halaman Antrean.</p></td>
                        </tr>
                        <tr>
                            <th scope="row">Daftar model</th>
                            <td><a class="button" href="<?php echo esc_url($refresh_url); ?>">Muat daftar model dari API</a>
                                <p class="description"><?php
                                    echo is_array($cache) && !empty($cache['time'])
                                        ? 'Terakhir diperbarui ' . esc_html(human_time_diff((int) $cache['time'])) . ' lalu (' . count($cache['models']) . ' model).'
                                        : 'Belum pernah dimuat. Memakai daftar bawaan plugin.';
                                ?> Model global diatur di halaman utama plugin.</p></td>
                        </tr>
                    </table>
                </div>

                <?php foreach (self::agents() as $k => $a) :
                    $has_prompt = $a[2];
                    $field_id = 'aaa_prompt_' . $k; ?>
                    <div class="aaa-card aaa-agent">
                        <h2><?php echo esc_html($a[0]); ?></h2>
                        <p class="description"><?php echo esc_html($a[1]); ?></p>

                        <p><label for="aaa_model_<?php echo esc_attr($k); ?>"><strong>Model</strong></label><br>
                            <?php echo self::model_select("aaa_model_$k", get_option("aaa_model_$k", ''), true, "aaa_model_$k"); ?>
                            <?php if ($k === 'serp') : ?><span class="description"> Kosong memakai gemini-2.5-flash.</span><?php endif; ?></p>

                        <?php if ($has_prompt) :
                            $d = $defs[$k]; ?>
                            <p><label for="<?php echo esc_attr($field_id); ?>"><strong>Prompt</strong></label>
                                <?php if (AAA_Prompts::is_custom($k)) : ?><span class="aaa-tag aaa-tag-custom">disesuaikan</span><?php else : ?><span class="aaa-tag">bawaan</span><?php endif; ?></p>
                            <textarea name="<?php echo esc_attr(AAA_Prompts::OPT . $k); ?>" id="<?php echo esc_attr($field_id); ?>" class="aaa-prompt" rows="12"
                                      data-default="<?php echo esc_attr(AAA_Prompts::default_for($k)); ?>"><?php echo esc_textarea(AAA_Prompts::get($k)); ?></textarea>
                            <p class="aaa-vars"><strong>Placeholder:</strong>
                                <?php foreach ($d['vars'] as $ph => $desc) echo '<code title="' . esc_attr($desc) . '">' . esc_html($ph) . '</code> '; ?>
                            </p>
                            <?php if ($d['required']) : ?><p class="description">Wajib ada: <?php echo esc_html(implode(', ', $d['required'])); ?>. Prompt yang tidak memenuhi syarat ditolak.</p><?php endif; ?>
                            <?php if ($d['contract'] !== '') : ?>
                                <p class="description">Format output berikut ditambahkan otomatis di akhir prompt agar hasilnya bisa diproses plugin:</p>
                                <pre class="aaa-contract"><?php echo esc_html($d['contract']); ?></pre>
                            <?php endif; ?>
                            <p><button type="button" class="button" onclick="var t=document.getElementById('<?php echo esc_js($field_id); ?>');if(confirm('Ganti isi kolom dengan prompt bawaan? Perubahan baru tersimpan setelah klik Simpan.')){t.value=t.dataset.default;}">Pakai prompt bawaan</button></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php submit_button('Simpan pengaturan agent'); ?>
            </form>
        </div>
        <?php
    }
}
