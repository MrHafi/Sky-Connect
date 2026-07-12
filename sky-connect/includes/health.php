<?php
/*
 * This file:
 * - Checks the site still works after a file was changed
 * - Sends a POST request to the homepage (not GET)
 * - POST is NEVER cached by any cache plugin, so this always hits real PHP
 * - If the site is broken, this request will show it
 * - Returns true if healthy, or an error message if broken
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ site health class ---------*/
class Sky_Connect_Health {

    /* ------------------------------ check the site still loads fine ---------*/
    public static function check() {

        /* ------------------------------ build a fresh homepage url ---------*/
        $bust = wp_rand();
        $url  = home_url( '/?sky_health=' . $bust );

        /* ------------------------------ POST, not GET — this is the key ---------*/
        // Every page cache (LiteSpeed, WP Rocket, W3TC, WP Super Cache, Cloudflare)
        // only caches GET requests. A POST is never served from cache, so it forces
        // WordPress to actually run PHP — which means a broken plugin really crashes here.
        // This is an HTTP standard, so it works on every host, not just one.
        $response = wp_remote_post( $url, array(
            'timeout'     => 20,
            'redirection' => 0,
            'sslverify'   => false,
            'body'        => array( 'sky_health' => 1 ),
            'headers'     => array(
                'Cache-Control' => 'no-cache, no-store',
                'Pragma'        => 'no-cache',
            ),
        ) );

        /* ------------------------------ request failed = treat as broken ---------*/
        // if we cannot even load the homepage, something is badly wrong.
        // roll back — better safe than sorry.
        if ( is_wp_error( $response ) ) {
            return 'Site is broken — could not load homepage: ' . $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );        

        /* ------------------------------ a 500 is a hard crash ---------*/
        if ( $code >= 500 ) {
            return 'Site is broken — homepage returned error ' . $code;
        }

        /* ------------------------------ WordPress prints these when it dies ---------*/
        // WP can still return 200 while showing the white "critical error" page
        if (
            stripos( $body, 'There has been a critical error' ) !== false ||
            stripos( $body, 'Fatal error' ) !== false ||
            stripos( $body, 'Uncaught Error' ) !== false ||
            stripos( $body, 'recovery mode' ) !== false
        ) {
            return 'Site is broken — fatal error detected on homepage';
        }

        /* ------------------------------ a near-empty page means it died ---------*/
        if ( strlen( trim( $body ) ) < 100 ) {
            return 'Site is broken — homepage returned an empty page';
        }

        /* ------------------------------ site is fine ---------*/
        return true;
    }
}