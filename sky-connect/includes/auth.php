<?php 

// Read the header — grab Authorization: Bearer <token> from the request
// Extract the token — strip the Bearer  part, keep only the plain token
// Hash it — hash what we received using wp_hash()
// Compare — match it against the stored hash in database
// Block or allow — if no match, return 401 error and stop. If match, let request continue.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ token auth class ---------*/
class Sky_Connect_Auth {

    /* ------------------------------ check bearer token from request header ---------*/
    public static function check( $request ) {

        /* ------------------------------ grab the authorization header ---------*/
        $auth_header = $request->get_header( 'authorization' );

        // no header sent — block
        if ( empty( $auth_header ) ) {
            return new WP_REST_Response(
                array( 'error' => 'Missing authorization header' ),
                401
            );
        }

        /* ------------------------------ extract plain token from "Bearer <token>" ---------*/
        $plain_token = trim( str_replace( 'Bearer ', '', $auth_header ) );

        // empty after stripping — block
        if ( empty( $plain_token ) ) {
            return new WP_REST_Response(
                array( 'error' => 'Empty token' ),
                401
            );
        }

        /* ------------------------------ hash received token and compare with stored hash ---------*/
        $received_hash = wp_hash( $plain_token );
        $stored_hash   = get_option( 'sky_connect_token_hash' );

        // no match — block
        if ( ! hash_equals( $stored_hash, $received_hash ) ) {
            return new WP_REST_Response(
                array( 'error' => 'Invalid token' ),
                401
            );
        }

        /* ------------------------------ block everything if master switch is OFF ---------*/
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





