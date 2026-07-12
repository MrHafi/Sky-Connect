<?php
/*
 * This file:
 * - Checks the site still works after a file was changed
 * - Loads the homepage AND the admin page in the background
 * - If either one errors, the site is broken
 * - Returns true if healthy, or an error message if broken
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ site health class ---------*/
class Sky_Connect_Health {

    /* ------------------------------ check the site still loads fine ---------*/
    public static function check() {

        /* ------------------------------ the two pages we test ---------*/
        // homepage catches front-end crashes, admin catches dashboard crashes
        $urls = array(
            home_url( '/' ),
            admin_url(),
        );

        foreach ( $urls as $url ) {

            /* ------------------------------ load the page quietly in the background ---------*/
            $response = wp_remote_get( $url, array(
                'timeout'   => 10,
                // don't follow redirects — we want the real status of THIS url
                'redirection' => 0,
                // skip SSL check so a local/staging cert can't cause a false alarm
                'sslverify' => false,
            ) );

            /* ------------------------------ could not reach the page at all ---------*/
            if ( is_wp_error( $response ) ) {
                return 'Site check failed: ' . $response->get_error_message();
            }

            $code = wp_remote_retrieve_response_code( $response );

            /* ------------------------------ 500 = fatal error (the "critical error" screen) ---------*/
            if ( $code >= 500 ) {
                return 'Site is broken — ' . $url . ' returned error ' . $code;
            }

            /* ------------------------------ WordPress prints this text on a fatal error ---------*/
            $body = wp_remote_retrieve_body( $response );

            if ( strpos( $body, 'critical error' ) !== false ) {
                return 'Site is broken — critical error detected on ' . $url;
            }
        }

        /* ------------------------------ both pages loaded fine ---------*/
        return true;
    }
}