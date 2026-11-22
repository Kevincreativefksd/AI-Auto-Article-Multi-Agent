<?php
/*
Plugin Name: AI Auto Article Gemini Pro
Description: Multi-agent article generation with Gemini, SERP research, per-stage models, retry and queue dashboard, humanizer stage, visual content agent (local editorial image), category selection, author preservation, and SEO prompt context.
Version: 2.7.0
Author: Kevin
*/
if (!defined('ABSPATH')) exit;

define('AAA_PLUGIN_VERSION', '2.7.0');
$aaa_files = ['class-aaa-db.php', 'class-aaa-api.php', 'class-aaa-agents.php', 'class-aaa-humanizer.php', 'class-aaa-cron.php', 'class-aaa-admin.php', 'class-aaa-queue-admin.php', 'class-aaa-prompts.php', 'class-aaa-news.php', 'class-aaa-agent-settings.php', 'class-aaa-news-settings.php', 'class-aaa-visual-renderer.php', 'class-aaa-visual-agent.php', 'class-aaa-visual-admin.php'];
foreach ($aaa_files as $aaa_file) {
    require_once plugin_dir_path(__FILE__) . $aaa_file;
}
register_activation_hook(__FILE__, ['AAA_DB', 'create_table']);
add_action('plugins_loaded', function () {
    AAA_DB::maybe_upgrade(); // harus sebelum AAA_Cron::init() agar kolom baru sudah ada
    AAA_Admin::init();
    AAA_Queue_Admin::init();
    AAA_Agent_Settings::init();
    AAA_News_Settings::init();
    AAA_Cron::init();
    AAA_Visual_Admin::init();
});