<?php
if (!defined('ABSPATH')) exit;

/**
 * Menu pengaturan "Visual Content Agent" (submenu di bawah "AI Article Agents").
 *
 * Memakai WordPress Settings API: register_setting() + settings_fields() sehingga nonce,
 * pemeriksaan capability, dan penyimpanan ditangani oleh options.php. Callback sanitasi juga
 * memeriksa current_user_can(). Tidak ada pengaturan lama yang diubah atau dihapus.
 */
class AAA_Visual_Admin {

    const GROUP = 'aaa_visual_group';
    const SLUG  = 'aaa-visual-agent';

    public static function init() {
        // Prioritas 20: menu induk 'ai-auto-article' (prioritas 10) harus sudah ada.
        add_action('admin_menu', [__CLASS__, 'add_menu'], 20);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function add_menu() {
        add_submenu_page(
            'ai-auto-article',
            'Visual Content Agent',
            'Visual Content Agent',
            'manage_options',
            self::SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function register() {
        register_setting(self::GROUP, AAA_Visual_Agent::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => ['AAA_Visual_Agent', 'sanitize_settings'],
            'default'           => AAA_Visual_Agent::defaults(),
        ]);
    }

    private static function badge($ok, $yes, $no) {
        return $ok
            ? '<span style="color:#1a7f37;font-weight:600;">&#10004; ' . esc_html($yes) . '</span>'
            : '<span style="color:#b32d2e;font-weight:600;">&#10008; ' . esc_html($no) . '</span>';
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak memiliki izin untuk membuka halaman ini.'));
        }

        $s    = AAA_Visual_Agent::get_settings();
        $name = AAA_Visual_Agent::OPTION;
        $caps = AAA_Visual_Renderer::capabilities();
        $effective_brand = AAA_Visual_Agent::brand_name();
        ?>
        <div class="wrap">
            <h1>Visual Content Agent</h1>
            <p>Membuat gambar editorial 16:9 untuk setiap artikel secara lokal dengan PHP (tanpa image generation, tanpa API key tambahan, tanpa gambar stok). Gemini hanya menyusun data visual dan metadata SEO gambar.</p>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Visual Content Agent</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[enabled]" value="1" <?php checked($s['enabled'], '1'); ?>> Aktifkan (berjalan setelah SEO Editor/Humanizer, sebelum Quality Gate)</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_vb_name">Nama Brand</label></th>
                        <td>
                            <input type="text" id="aaa_vb_name" class="regular-text" maxlength="60" name="<?php echo esc_attr($name); ?>[brand_name]" value="<?php echo esc_attr($s['brand_name']); ?>">
                            <p class="description">Kosongkan untuk memakai Brand pada panel Konfigurasi utama (saat ini: <strong><?php echo esc_html($effective_brand); ?></strong>).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_vb_desc">Deskripsi Brand</label></th>
                        <td><input type="text" id="aaa_vb_desc" class="regular-text" maxlength="80" name="<?php echo esc_attr($name); ?>[brand_description]" value="<?php echo esc_attr($s['brand_description']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_vb_bg">Warna Background</label></th>
                        <td><input type="color" id="aaa_vb_bg" name="<?php echo esc_attr($name); ?>[bg_color]" value="<?php echo esc_attr($s['bg_color']); ?>"> <code><?php echo esc_html($s['bg_color']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_vb_text">Warna Teks</label></th>
                        <td><input type="color" id="aaa_vb_text" name="<?php echo esc_attr($name); ?>[text_color]" value="<?php echo esc_attr($s['text_color']); ?>"> <code><?php echo esc_html($s['text_color']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_vb_accent">Warna Aksen</label></th>
                        <td><input type="color" id="aaa_vb_accent" name="<?php echo esc_attr($name); ?>[accent_color]" value="<?php echo esc_attr($s['accent_color']); ?>"> <code><?php echo esc_html($s['accent_color']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">Featured Image</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[featured]" value="1" <?php checked($s['featured'], '1'); ?>> Jadikan gambar sebagai Featured Image artikel</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Gambar di awal artikel</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[insert]" value="1" <?php checked($s['insert'], '1'); ?>> Sisipkan gambar yang sama di bagian paling awal isi artikel</label>
                            <p class="description">Memakai attachment yang sama dengan Featured Image (tidak ada gambar kedua). Bila tema Anda sudah menampilkan Featured Image di atas artikel, matikan opsi ini agar gambar tidak tampil dua kali.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_vb_format">Format Gambar</label></th>
                        <td>
                            <select id="aaa_vb_format" name="<?php echo esc_attr($name); ?>[format]">
                                <option value="auto" <?php selected($s['format'], 'auto'); ?>>Otomatis (WebP bila didukung, jika tidak JPEG)</option>
                                <option value="webp" <?php selected($s['format'], 'webp'); ?>>WebP (JPEG bila tidak didukung)</option>
                                <option value="jpeg" <?php selected($s['format'], 'jpeg'); ?>>JPEG</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aaa_vb_kb">Batas Ukuran File (KB)</label></th>
                        <td>
                            <input type="number" id="aaa_vb_kb" min="50" max="2000" step="10" name="<?php echo esc_attr($name); ?>[max_kb]" value="<?php echo esc_attr($s['max_kb']); ?>">
                            <p class="description">Kualitas diturunkan bertahap hingga di bawah batas ini; resolusi tidak diturunkan.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Rasio dan Resolusi</th>
                        <td>16:9, 1920 &times; 1080 piksel (tetap).</td>
                    </tr>
                </table>
                <?php submit_button('Simpan Pengaturan'); ?>
            </form>

            <h2>Kemampuan Server</h2>
            <table class="widefat striped" style="max-width:640px;">
                <tbody>
                    <tr><td>PHP GD + FreeType (renderer utama)</td><td><?php echo self::badge($caps['gd'], 'Tersedia', 'Tidak tersedia'); ?></td></tr>
                    <tr><td>WebP via GD</td><td><?php echo self::badge($caps['gd_webp'], 'Didukung', 'Tidak didukung'); ?></td></tr>
                    <tr><td>WebP diizinkan WordPress</td><td><?php echo self::badge($caps['wp_webp'], 'Ya', 'Tidak'); ?></td></tr>
                    <tr><td>Imagick (cadangan)</td><td><?php echo self::badge($caps['imagick'], 'Tersedia', 'Tidak tersedia'); ?></td></tr>
                    <tr><td>Font lokal</td><td><?php echo self::badge($caps['fonts'], 'Lengkap', 'Tidak ditemukan'); ?></td></tr>
                </tbody>
            </table>
            <p class="description">Bila WebP tidak didukung, JPEG dipakai otomatis. Bila GD maupun Imagick tidak tersedia, gambar dilewati dan artikel tetap diproses.</p>
        </div>
        <?php
    }
}
