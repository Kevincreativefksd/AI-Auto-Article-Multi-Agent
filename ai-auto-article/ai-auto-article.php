<?php
/*
Plugin Name: AI Auto Article Multi Agent
Description: Multi-agent article generation with Gemini, humanizer stage, category selection, author preservation, and SEO prompt context.
Version: 2.2.0
Author: Kevin
*/
if (!defined('ABSPATH')) exit;

define('AAA_PLUGIN_VERSION', '2.2.0');
$aaa_files = ['class-aaa-db.php', 'class-aaa-api.php', 'class-aaa-agents.php', 'class-aaa-humanizer.php', 'class-aaa-cron.php', 'class-aaa-admin.php'];
foreach ($aaa_files as $aaa_file) {
    require_once plugin_dir_path(__FILE__) . $aaa_file;
}
register_activation_hook(__FILE__, ['AAA_DB', 'create_table']);
add_action('plugins_loaded', function () {
    AAA_Admin::init();
    AAA_Cron::init();
});