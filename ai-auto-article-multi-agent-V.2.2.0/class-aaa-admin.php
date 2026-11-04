<?php
if (!defined('ABSPATH')) exit;

class AAA_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('wp_ajax_aaa_get_task_status', [__CLASS__, 'ajax_get_status']);
    }

    public static function add_menu() {
        add_menu_page('AI Auto Article', 'AI Article Agents', 'manage_options', 'ai-auto-article', [__CLASS__, 'render_page'], 'dashicons-edit');
    }

    public static function ajax_get_status() {
        check_ajax_referer('aaa_status_nonce', 'security');
        global $wpdb;
        $table = AAA_DB::get_table_name();
        
        $tasks = $wpdb->get_results("SELECT id, status, post_id, agent_data FROM $table ORDER BY created_at DESC LIMIT 10", ARRAY_A);
        
        foreach ($tasks as &$t) {
            if ($t['status'] === 'failed') {
                $data = json_decode($t['agent_data'], true);
                $t['error_msg'] = isset($data['error_reason']) ? $data['error_reason'] : 'Koneksi API / Validasi Ditolak';
            }
            unset($t['agent_data']);
        }
        
        wp_send_json_success($tasks);
    }

    public static function render_page() {
        global $wpdb;
        $table = AAA_DB::get_table_name();

        if (isset($_POST['save_settings']) && check_admin_referer('aaa_save_settings')) {
            update_option('aaa_gemini_api', sanitize_text_field(wp_unslash($_POST['aaa_gemini_api'] ?? '')));
            update_option('aaa_gemini_model', sanitize_text_field(wp_unslash($_POST['aaa_gemini_model'] ?? 'gemini-1.5-flash')));
            update_option('aaa_brand_name', sanitize_text_field(wp_unslash($_POST['aaa_brand_name'] ?? '')));
            update_option('aaa_target_audience', sanitize_text_field(wp_unslash($_POST['aaa_target_audience'] ?? '')));
            update_option('aaa_tone_voice', sanitize_text_field(wp_unslash($_POST['aaa_tone_voice'] ?? '')));
            update_option('aaa_autopublish', sanitize_text_field(wp_unslash($_POST['aaa_autopublish'] ?? 'draft')));
            update_option('aaa_humanizer_enabled', isset($_POST['aaa_humanizer_enabled']) ? '1' : '0');
            echo "<div class='updated'><p>Pengaturan tersimpan.</p></div>";
        }

        if (isset($_POST['aaa_add_task']) && check_admin_referer('aaa_add_task')) {
            $keyword = sanitize_text_field($_POST['aaa_keyword']);
            $extra = sanitize_textarea_field(wp_unslash($_POST['aaa_extra_info'] ?? ''));
            $category_id = absint($_POST['aaa_category_id'] ?? 0);
            $post_type = sanitize_text_field($_POST['aaa_post_type'] ?? 'single');
            $author_id = get_current_user_id();
            $valid_category = term_exists($category_id, 'category');

            if (!$valid_category || !$category_id) {
                echo "<div class='notice notice-error'><p>Silakan pilih kategori artikel yang tersedia.</p></div>";
            } else {
                if ($post_type === 'cluster') {
                    // Logika Cluster Post
                    $cluster_keywords = AAA_Agents::generate_cluster_keywords($keyword);
                    
                    if (is_wp_error($cluster_keywords)) {
                         echo "<div class='notice notice-error'><p>Gagal memproses Cluster Post: " . esc_html($cluster_keywords->get_error_message()) . "</p></div>";
                    } elseif (is_array($cluster_keywords)) {
                        $inserted_count = 0;
                        foreach ($cluster_keywords as $ck) {
                            $clean_ck = sanitize_text_field($ck);
                            if (!empty($clean_ck)) {
                                $cluster_extra = "Turunan dari Topik Utama: {$keyword}\n\n" . $extra;
                                $wpdb->insert($table, ['keyword' => $clean_ck, 'extra_info' => $cluster_extra, 'author_id' => $author_id, 'category_id' => $category_id, 'status' => 'pending']);
                                $inserted_count++;
                            }
                        }
                        echo "<div class='updated'><p>Berhasil membuat Cluster Post! <strong>{$inserted_count} tugas</strong> telah dimasukkan ke dalam antrean.</p></div>";
                    } else {
                        echo "<div class='notice notice-error'><p>Format respons dari Gemini tidak valid. Silakan coba lagi.</p></div>";
                    }
                } else {
                    // Logika Single Post (Bawaan)
                    $inserted = $wpdb->insert($table, ['keyword' => $keyword, 'extra_info' => $extra, 'author_id' => $author_id, 'category_id' => $category_id, 'status' => 'pending']);
                    if ($inserted) {
                        echo "<div class='updated'><p>Tugas berhasil dimasukkan ke dalam antrean.</p></div>";
                    } else {
                        echo "<div class='notice notice-error'><p>Tugas gagal disimpan. Silakan coba lagi.</p></div>";
                    }
                }
            }
        }


        if (isset($_POST['aaa_force_run']) && isset($_POST['aaa_force_nonce']) && wp_verify_nonce($_POST['aaa_force_nonce'], 'aaa_force_action')) {
            echo "<div class='notice notice-info'><p>Menjalankan 1 antrean secara manual...</p></div>";
            $start_time = microtime(true);
            if (class_exists('AAA_Cron')) AAA_Cron::process_queue();
            $execution_time = round(microtime(true) - $start_time, 2);
            echo "<div class='updated'><p><strong>Selesai!</strong> Eksekusi tahap ini memakan waktu {$execution_time} detik.</p></div>";
        }

        $tasks = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 10");
        $ajax_nonce = wp_create_nonce('aaa_status_nonce');
        ?>
        <div class="wrap">
            <h1>Sistem Multi-Agent AI Article</h1>
            <div style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;">
                
                <div class="card" style="flex:1; min-width:300px; padding:20px;">
                    <h3>Konfigurasi</h3>
                    <form method="post">
                        <?php wp_nonce_field('aaa_save_settings'); ?>
                        <p>API Key: <input type="password" name="aaa_gemini_api" value="<?php echo esc_attr(get_option('aaa_gemini_api')); ?>" style="width:100%;" required></p>
                        <p>Model: <input type="text" name="aaa_gemini_model" value="<?php echo esc_attr(get_option('aaa_gemini_model', 'gemini-1.5-flash')); ?>" style="width:100%;"></p>
                        <p>Brand: <input type="text" name="aaa_brand_name" value="<?php echo esc_attr(get_option('aaa_brand_name')); ?>" style="width:100%;"></p>
                        <p>Audience: <input type="text" name="aaa_target_audience" value="<?php echo esc_attr(get_option('aaa_target_audience')); ?>" style="width:100%;"></p>
                        <p>Tone of Voice: <input type="text" name="aaa_tone_voice" value="<?php echo esc_attr(get_option('aaa_tone_voice')); ?>" style="width:100%;"></p>
                        <p>Mode Publikasi: 
                            <select name="aaa_autopublish" style="width:100%;">
                                <option value="draft" <?php selected(get_option('aaa_autopublish'), 'draft'); ?>>Simpan Draft</option>
                                <option value="publish" <?php selected(get_option('aaa_autopublish'), 'publish'); ?>>Auto Publish</option>
                            </select>
                        </p>
                        <p><label><input type="checkbox" name="aaa_humanizer_enabled" value="1" <?php checked(get_option('aaa_humanizer_enabled', '1'), '1'); ?>> Aktifkan Humanizer (hapus pola tulisan khas AI sebelum Quality Gate)</label></p>
                        <input type="submit" name="save_settings" class="button button-primary" value="Simpan Pengaturan">
                    </form>
                </div>

                <div class="card" style="flex:1; min-width:300px; padding:20px;">
                    <h3>Buat Artikel Baru</h3>
                    <form method="post">
                        <?php wp_nonce_field('aaa_add_task'); ?>
                        <p>Kata Kunci Utama: <input type="text" name="aaa_keyword" required style="width:100%;"></p>
                        
                        <p>Tipe Eksekusi:
                            <select name="aaa_post_type" style="width:100%;">
                                <option value="single">Single Post (1 Artikel untuk Kata Kunci ini)</option>
                                <option value="cluster">Cluster Post (Otomatis Buat 5 Artikel dari Turunan Kata Kunci ini)</option>
                            </select>
                        </p>

                        <p>Kategori Artikel:
                            <select name="aaa_category_id" required style="width:100%;">
                                <option value="">— Pilih kategori yang sudah ada —</option>
                                <?php foreach (get_categories(['hide_empty' => false, 'taxonomy' => 'category']) as $category) : ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>Informasi Tambahan / Brief: <textarea name="aaa_extra_info" rows="4" style="width:100%;"></textarea></p>
                        <input type="submit" name="aaa_add_task" class="button button-primary button-large" value="Masukkan Antrean" style="width:100%; height:40px; font-weight:bold;">
                    </form>
                </div>

            <!-- FITUR DEBUG -->
            <div style="margin-top:40px; padding:15px; background:#fff; border-left:4px solid #ffba00; box-shadow:0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin-top:0;">Mode Debugging</h3>
                <form method="post">
                    <?php wp_nonce_field('aaa_force_action', 'aaa_force_nonce'); ?>
                    <button type="submit" name="aaa_force_run" class="button button-secondary">
                        &#9658; Paksa Eksekusi 1 Tahap Sekarang
                    </button>
                </form>
            </div>

            <h2 style="margin-top:30px;">Status Antrean Live <span class="spinner" id="aaa_global_spinner" style="float:none; margin:0; visibility:hidden;"></span></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th style="width:50px;">ID</th><th style="width:30%;">Keyword</th><th>Status Multi-Agent</th><th style="width:150px;">Tanggal</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($tasks)): ?>
                        <tr><td colspan="4">Belum ada tugas.</td></tr>
                    <?php else: ?>
                        <?php foreach($tasks as $t): 
                            $error_msg = '';
                            if ($t->status === 'failed') {
                                $dt = json_decode($t->agent_data, true);
                                $error_msg = isset($dt['error_reason']) ? $dt['error_reason'] : 'Koneksi API / Validasi Ditolak';
                            }
                        ?>
                        <tr id="task-row-<?php echo $t->id; ?>">
                            <td><?php echo $t->id; ?></td>
                            <td><strong><?php echo esc_html($t->keyword); ?></strong></td>
                            <td class="status-cell" data-task="<?php echo esc_attr(json_encode(['status' => $t->status, 'post_id' => $t->post_id, 'error_msg' => $error_msg])); ?>">
                                Menunggu inisialisasi...
                            </td>
                            <td><?php echo $t->created_at; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const nonce = '<?php echo $ajax_nonce; ?>';
            const globalSpinner = document.getElementById('aaa_global_spinner');
            
            function renderStatusUI(task) {
                const spinner = '<span class="spinner is-active" style="float:none; margin:0; width:16px; height:16px; min-width:16px; display:inline-block; vertical-align:middle;"></span>';
                switch(task.status) {
                    case 'pending': return `<span style="color:#666;">&#9202; Menunggu giliran...</span>`;
                    case 'agent_1': return `<span style="color:#0073aa;">${spinner} Agent 1 Sedang menyusun outline</span>`;
                    case 'agent_2': return `<span style="color:#0073aa;">${spinner} Agent 2 sedang menulis artikel</span>`;
                    case 'agent_3':
                    case 'agent_4': /* 'agent_4' = status lama dari tugas yang masih berjalan */
                        return `<span style="color:#0073aa;">${spinner} Agent 3 sedang mengecek kualitas SEO</span>`;
                    case 'humanizer': return `<span style="color:#0073aa;">${spinner} Agent mengerjakan revisi</span>`;
                    case 'quality_gate': return `<span style="color:#0073aa;">${spinner} Agent 4 meninjau kualitas akhir</span>`;
                    case 'completed': return `<span style="color:green; font-weight:bold;">&#10004; Lulus & Selesai${task.post_id ? ` | <a href="<?php echo admin_url('post.php?action=edit&post='); ?>${task.post_id}" target="_blank">Edit Artikel</a>` : ''}</span>`;
                    case 'failed': return `<span style="color:red; font-weight:bold;">&#10008; Gagal: ${task.error_msg || 'Validasi Ditolak'}</span>`;
                    default: return `<span style="color:blue;">${spinner} ${task.status}</span>`;
                }
            }

            document.querySelectorAll('.status-cell').forEach(cell => {
                try {
                    const taskData = JSON.parse(cell.getAttribute('data-task'));
                    cell.innerHTML = renderStatusUI(taskData);
                } catch(e) {}
            });

            setInterval(() => {
                globalSpinner.style.visibility = 'visible';
                fetch(`${ajaxurl}?action=aaa_get_task_status&security=${nonce}`)
                    .then(res => res.json())
                    .then(res => {
                        globalSpinner.style.visibility = 'hidden';
                        if(res.success && res.data) {
                            res.data.forEach(task => {
                                const row = document.getElementById(`task-row-${task.id}`);
                                if(row) {
                                    const cell = row.querySelector('.status-cell');
                                    let currentData = {};
                                    try { currentData = JSON.parse(cell.getAttribute('data-task')); } catch(e) {}
                                    
                                    if(currentData.status !== task.status) {
                                        cell.setAttribute('data-task', JSON.stringify(task));
                                        cell.innerHTML = renderStatusUI(task);
                                        row.style.transition = "background-color 0.5s";
                                        row.style.backgroundColor = "#fffbcc";
                                        setTimeout(() => { row.style.backgroundColor = ""; }, 1500);
                                    }
                                }
                            });
                        }
                    }).catch(() => globalSpinner.style.visibility = 'hidden');
            }, 5000);
        });
        </script>
        <?php
    }
}