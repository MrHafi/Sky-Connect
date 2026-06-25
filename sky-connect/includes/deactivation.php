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

        // we keep saved data and logs (do not delete on deactivate)
    }
}