<?php


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ deactivator class ---------*/
class Sky_Connect_Deactivator {

    /* ------------------------------ run on deactivation ---------*/
    public static function deactivate() {

        // flip master switch OFF (stop all access immediately)
        update_option( 'sky_connect_enabled', 0 );


        /* ------------------------------ stop the daily cleanup schedule ---------*/
        wp_clear_scheduled_hook( 'sky_connect_daily' );
        

        /* ------------------------------ remove the emergency mu-plugin ---------*/
        require_once SKY_CONNECT_DIR . 'mu-installer.php';
        Sky_Connect_MU_Installer::remove();
        /* ------------------------------ clean up rewrite rules on deactivate ---------*/
flush_rewrite_rules();
    }
}