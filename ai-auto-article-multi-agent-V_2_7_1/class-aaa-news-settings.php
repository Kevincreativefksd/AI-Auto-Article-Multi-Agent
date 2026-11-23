<?php
if (!defined('ABSPATH')) exit;

/**
 * Halaman "Pengaturan Berita": semua pengaturan khusus agent berita di satu tempat.
 * Gaya bahasa memakai Tone of Voice dari halaman utama plugin (diterapkan apa adanya).
 */
class AAA_News_Settings {

    const SLUG  = 'aaa-news';
    const GROUP = 'aaa_news';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu'], 16);
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function add_menu() {
        add_submenu_page('ai-auto-article', 'Pengaturan Berita', 'Pengaturan Berita', 'manage_options', self::SLUG, [__CLASS__, 'render_page']);
    }

    public static function register() {
        register_setting(self::GROUP, 'aaa_model_news', ['type' => 'string', 'sanitize_callback' => ['AAA_Agent_Settings', 'sanitize_model']]);
        register_setting(self::GROUP, AAA_Prompts::OPT . 'news', ['type' => 'string', 'sanitize_callback' => function ($v) {
            return AAA_Prompts::sanitize('news', $v);
        }]);
        foreach (['aaa_news_min_words', 'aaa_news_max_words'] as $opt) {
            register_setting(self::GROUP, $opt, ['type' => 'integer', 'sanitize_callback' => function ($v) { return min(3000, max(100, absint($v))); }]);
        }
        register_setting(self::GROUP, 'aaa_news_structure', ['type' => 'string', 'sanitize_callback' => function ($v) {
            return array_key_exists($v, AAA_News::structures()) ? $v : 'pyramid';
        }]);
        register_setting(self::GROUP, 'aaa_news_subheads', ['type' => 'string', 'sanitize_callback' => function ($v) { return $v === '1' ? '1' : '0'; }]);
        register_setting(self::GROUP, 'aaa_news_structure_extra', ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field']);
        for ($i = 1; $i <= 3; $i++) {
            register_setting(self::GROUP, "aaa_news_angle_$i", ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field']);
            register_setting(self::GROUP, "aaa_news_angle_{$i}_label", ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        }
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $d = AAA_Prompts::defs()['news'];
        $tone = trim((string) get_option('aaa_tone_voice', ''));
        ?>
        <div class="wrap">
            <h1>Pengaturan berita</h1>
            <?php settings_errors('aaa_agents'); ?>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <div class="aaa-card">
                    <h2>Model</h2>
                    <p><?php echo AAA_Agent_Settings::model_select('aaa_model_news', get_option('aaa_model_news', ''), true, 'aaa_model_news'); ?></p>
                </div>

                <div class="aaa-card">
                    <h2>Gaya bahasa</h2>
                    <p>Tone of Voice saat ini:</p>
                    <pre><?php echo $tone !== '' ? esc_html($tone) : '(belum diisi)'; ?></pre>
                    <p class="description">Diambil dari kolom Tone of Voice di <a href="<?php echo esc_url(admin_url('admin.php?page=ai-auto-article')); ?>">halaman utama plugin</a> dan diterapkan apa adanya pada berita (placeholder <code>{tone}</code>). Fakta tetap hanya dari artikel mentah.</p>
                </div>

                <div class="aaa-card">
                    <h2>Panjang dan struktur</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="aaa_news_min_words">Panjang (kata)</label></th>
                            <td>
                                <input type="number" id="aaa_news_min_words" name="aaa_news_min_words" min="100" max="3000" value="<?php echo esc_attr(AAA_News::min_words()); ?>"> sampai
                                <input type="number" id="aaa_news_max_words" name="aaa_news_max_words" min="100" max="3000" value="<?php echo esc_attr(AAA_News::max_words()); ?>">
                                <p class="description">Menjadi arahan di prompt, bukan batas yang dipaksa oleh plugin. Berita tidak akan diperpanjang dengan fakta di luar artikel mentah, jadi sumber pendek menghasilkan berita pendek.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aaa_news_structure">Struktur</label></th>
                            <td>
                                <select id="aaa_news_structure" name="aaa_news_structure">
                                    <?php foreach (AAA_News::structures() as $key => $st) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected(get_option('aaa_news_structure', 'pyramid'), $key); ?>><?php echo esc_html($st[0]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Subjudul</th>
                            <td><label><input type="checkbox" name="aaa_news_subheads" value="1" <?php checked(get_option('aaa_news_subheads', '0'), '1'); ?>> Pakai subjudul H2 untuk membagi isi berita</label></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="aaa_news_structure_extra">Catatan struktur</label></th>
                            <td><textarea id="aaa_news_structure_extra" name="aaa_news_structure_extra" rows="3" style="width:100%;"><?php echo esc_textarea(get_option('aaa_news_structure_extra', '')); ?></textarea>
                                <p class="description">Opsional, misalnya cara membuka paragraf pertama atau cara menutup berita.</p></td>
                        </tr>
                    </table>
                </div>

                <div class="aaa-card">
                    <h2>Tiga sudut pandang</h2>
                    <p class="description">Setiap artikel mentah menghasilkan tiga berita, satu per sudut pandang, masing-masing dengan judul berbeda.</p>
                    <?php foreach (AAA_News::angles() as $i => $ang) : ?>
                        <p><input type="text" name="aaa_news_angle_<?php echo (int) $i; ?>_label" value="<?php echo esc_attr($ang[0]); ?>" style="width:100%;" aria-label="Nama sudut <?php echo (int) $i; ?>">
                        <textarea name="aaa_news_angle_<?php echo (int) $i; ?>" rows="2" style="width:100%;" aria-label="Instruksi sudut <?php echo (int) $i; ?>"><?php echo esc_textarea($ang[1]); ?></textarea></p>
                    <?php endforeach; ?>
                </div>

                <div class="aaa-card">
                    <h2>Prompt</h2>
                    <p><?php if (AAA_Prompts::is_custom('news')) : ?><span class="aaa-tag aaa-tag-custom">disesuaikan</span><?php else : ?><span class="aaa-tag">bawaan</span><?php endif; ?></p>
                    <textarea name="<?php echo esc_attr(AAA_Prompts::OPT . 'news'); ?>" id="aaa_prompt_news" class="aaa-prompt" rows="16"
                              data-default="<?php echo esc_attr(AAA_Prompts::default_for('news')); ?>"><?php echo esc_textarea(AAA_Prompts::get('news')); ?></textarea>
                    <p class="aaa-vars"><strong>Placeholder:</strong>
                        <?php foreach ($d['vars'] as $ph => $desc) echo '<code title="' . esc_attr($desc) . '">' . esc_html($ph) . '</code> '; ?>
                    </p>
                    <p class="description">Wajib ada: <?php echo esc_html(implode(', ', $d['required'])); ?>. Format output berikut ditambahkan otomatis di akhir prompt:</p>
                    <pre class="aaa-contract"><?php echo esc_html($d['contract']); ?></pre>
                    <p><button type="button" class="button" onclick="var t=document.getElementById('aaa_prompt_news');if(confirm('Ganti isi kolom dengan prompt bawaan? Perubahan baru tersimpan setelah klik Simpan.')){t.value=t.dataset.default;}">Pakai prompt bawaan</button></p>
                </div>

                <?php submit_button('Simpan pengaturan berita'); ?>
            </form>
        </div>
        <?php
    }
}
