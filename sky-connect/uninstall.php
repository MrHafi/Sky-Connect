<?php
/*
 * Runs ONLY when the plugin is deleted from WordPress (not on deactivate).
 * Cleans up everything Sky Connect created: options, the log table,
 * and the backups folder — so no leftovers remain.
 */

// security — WordPress sets this when it calls uninstall. if it's missing, stop.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/* ------------------------------ delete all our options ---------*/
$options = array(
    'sky_connect_enabled',
    'sky_connect_token_hash',
    'sky_connect_token_plain',
    'sky_connect_oauth_token_hash',
    'sky_connect_dcr_clients',
    'sky_connect_emergency_key',
);
foreach ( $options as $opt ) {
    delete_option( $opt );
}

/* ------------------------------ drop the log table ---------*/
$table = $wpdb->prefix . 'sky_connect_logs';
$wpdb->query( "DROP TABLE IF EXISTS $table" );

/* ------------------------------ delete the backups folder ---------*/
$backup_dir = WP_CONTENT_DIR . '/sky-connect-backups';
if ( is_dir( $backup_dir ) ) {
    foreach ( glob( $backup_dir . '/*' ) ?: array() as $f ) {
        @unlink( $f );
    }
    @rmdir( $backup_dir );
}