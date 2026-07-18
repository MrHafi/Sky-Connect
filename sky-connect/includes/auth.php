<?php

// Read the header — grab Authorization: Bearer <token> from the request
// Extract the token — strip the Bearer part, keep only the plain token
// Compare — match it against the stored hash in database
// Block or allow — if no match, return 401 and stop. If match, let request continue.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ token auth class ---------*/
class Sky_Connect_Auth {

    /* ------------------------------ check bearer token from request header ---------*/
    public static function check( $request ) {

        require_once SKY_CONNECT_DIR . 'includes/logger.php';

        /* ------------------------------ grab the authorization header ---------*/
        $auth_header = $request->get_header( 'authorization' );

        /* ------------------------------ no header = normal MCP probe, NOT logged ---------*/
        // Claude's first request always has no token — that's how it discovers it
        // must log in. logging this would flood the activity log with noise, so we
        // reply with the 401 signal but do NOT record it as a failed attempt.
        if ( empty( $auth_header ) ) {
            $response = new WP_REST_Response(
                array( 'error' => 'Missing authorization header' ),
                401
            );
            $response->header( 'WWW-Authenticate', 'Bearer resource_metadata="' . home_url( '/.well-known/oauth-protected-resource/wp-json/sky-connect/v1/mcp' ) . '"' );
            return $response;
        }

        /* ------------------------------ extract plain token from "Bearer <token>" ---------*/
        $plain_token = trim( str_replace( 'Bearer ', '', $auth_header ) );

        /* ------------------------------ empty after stripping = suspicious, LOG it ---------*/
        if ( empty( $plain_token ) ) {
            Sky_Connect_Logger::add( 'auth', '', 'blocked', 'Empty token after Bearer' );
            return new WP_REST_Response(
                array( 'error' => 'Empty token' ),
                401
            );
        }

        /* ------------------------------ check against warp token and oauth token ---------*/
        $warp_hash  = get_option( 'sky_connect_token_hash' );
        $oauth_hash = get_option( 'sky_connect_oauth_token_hash' ); // web claude

        $warp_valid  = ! empty( $warp_hash )  && hash_equals( $warp_hash,  wp_hash( $plain_token ) );
        $oauth_valid = ! empty( $oauth_hash ) && hash_equals( $oauth_hash, wp_hash( $plain_token ) );

        /* ------------------------------ wrong token = real failed attempt, LOG it ---------*/
        if ( ! $warp_valid && ! $oauth_valid ) {
            Sky_Connect_Logger::add( 'auth', '', 'blocked', 'Invalid token used' );
            return new WP_REST_Response(
                array( 'error' => 'Invalid token' ),
                401
            );
        }

        /* ------------------------------ block everything if master switch is OFF ---------*/
        // not an attack, just disabled — no need to log
        if ( ! get_option( 'sky_connect_enabled', 0 ) ) {
            return new WP_REST_Response(
                array( 'error' => 'Sky Connect is disabled' ),
                403
            );
        }

        /* ------------------------------ token is valid — allow request to continue ---------*/
        return true;
    }
}