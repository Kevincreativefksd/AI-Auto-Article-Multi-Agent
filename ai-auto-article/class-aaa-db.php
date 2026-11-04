<?php
if (!defined('ABSPATH')) exit;

class AAA_DB {
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'aaa_tasks';
    }

    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            extra_info text,
            author_id bigint(20) unsigned NOT NULL DEFAULT 0,
            category_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL DEFAULT 'pending',
            agent_data longtext,
            post_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}