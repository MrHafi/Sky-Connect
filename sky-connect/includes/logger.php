<?php
/*
 * This file:
 * - Creates a database table to store what Claude does
 * - Records every tool call: which tool, which file, worked or failed, when
 * - Logs blocked attempts too (jail escapes, syntax errors, site breaks)
 * - Reads the logs back for the admin viewer
 * - Auto-deletes logs older than 30 days
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ logger class ---------*/
class Sky_Connect_Logger {

    /* ------------------------------ the name of our log table ---------*/
    private static function table_name() {
        global $wpdb;
        // wp sites can use a custom table prefix, so we never hardcode "wp_"
        return $wpdb->prefix . 'sky_connect_logs';
    }

    /* ------------------------------ make the table (runs on activation) ---------*/
    public static function create_table() {

        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        // dbDelta is WordPress's safe way to create/update tables
        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tool VARCHAR(50) NOT NULL,
            file_path TEXT NULL,
            status VARCHAR(20) NOT NULL,
            message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ------------------------------ write one line to the log ---------*/
    public static function add( $tool, $file_path, $status, $message = '' ) {

        self::ensure_table();  
        global $wpdb;

        $wpdb->insert(
            self::table_name(),
            array(
                'tool'       => $tool,                    // e.g. "write_file"
                'file_path'  => $file_path,               // which file was touched
                'status'     => $status,                  // "success" or "blocked" or "failed"
                'message'    => $message,                 // why it failed, if it did
                'created_at' => current_time( 'mysql' ),  // site's local time
            )
        );
    }

    /* ------------------------------ read the newest logs (for the admin page) ---------*/
    public static function get_recent( $limit = 50 ) {

    self::ensure_table();
        global $wpdb;
        $table = self::table_name();

        // newest first
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
    }

    /* ------------------------------ delete logs older than 30 days ---------*/
    public static function clean_old() {

        global $wpdb;
        $table = self::table_name();

        $wpdb->query(
            "DELETE FROM $table WHERE created_at < DATE_SUB( NOW(), INTERVAL 30 DAY )"
        );
    }



    /* ------------------------------ make sure the table exists ---------*/
    private static function ensure_table() {

        global $wpdb;
        $table = self::table_name();

        // check if our table is present
        $found = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        );

        // not there — create it now (covers plugin-updated-while-active case)
        if ( $found !== $table ) {
            self::create_table();
        }
    }
}