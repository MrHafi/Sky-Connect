<?php
/*
 * This file:
 * - Checks the site still works after a file was changed
 * - Tests homepage, a real post, and the REST API root
 * - Uses POST so no cache serves a stale "healthy" page
 * - Logs the result of each page so one test run shows everything
 * - If ANY page is broken, the whole site counts as broken
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ site health class ---------*/
class Sky_Connect_Health {

    /* ------------------------------ check the site still loads fine ---------*/
    public static function check() {

        $bust = wp_rand();
        $urls = array();

        // 1. homepage — front-end crashes
        $urls['homepage'] = home_url( '/?sky_health=' . $bust );

        // 2. a real published post — theme + most plugins
        $recent = get_posts( array(
            'numberposts' => 1,
            'post_status' => 'publish',
            'fields'      => 'ids',
        ) );
        if ( ! empty( $recent ) ) {
            $urls['post'] = add_query_arg( 'sky_health', $bust, get_permalink( $recent[0] ) );
        }

        // 3. REST API root — API/plugin crashes
        $urls['rest'] = add_query_arg( 'sky_health', $bust, rest_url() );

        /* ------------------------------ test each page, log each result ---------*/
        foreach ( $urls as $label => $url ) {

            $result = self::test_one( $url );

            // log every page so one run tells us the full picture
            error_log( 'SKY HEALTH - ' . $label . ': ' . ( $result === true ? 'OK' : $result ) );

            // first broken page = whole site broken
            if ( $result !== true ) {
                return $result;
            }
        }

        return true;
    }

    /* ------------------------------ test a single url ---------*/
    private static function test_one( $url ) {

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

        if ( is_wp_error( $response ) ) {
            return 'could not load: ' . $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code >= 500 ) {
            return 'returned error ' . $code;
        }

        if (
            stripos( $body, 'There has been a critical error' ) !== false ||
            stripos( $body, 'Fatal error' ) !== false ||
            stripos( $body, 'Uncaught Error' ) !== false ||
            stripos( $body, 'recovery mode' ) !== false
        ) {
            return 'fatal error detected';
        }

        return true;
    }
}