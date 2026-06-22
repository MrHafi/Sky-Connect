<?php


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ activator class ---------*/
class Sky_Connect_Activator {

    /* ------------------------------ run on activation ---------*/
    public static function activate() {

        // master switch OFF by default (safe start, nothing exposed yet)
        if ( get_option( 'sky_connect_enabled' ) === false ) {
            add_option( 'sky_connect_enabled', 0 );
        }

        // (later) we will create the log table here
    }
}