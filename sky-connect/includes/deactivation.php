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


        /* ------------------------------ clean up rewrite rules on deactivate ---------*/
flush_rewrite_rules();
    }
}