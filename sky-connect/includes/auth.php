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
        // no header sent — return 401 with resource metadata pointer
if ( empty( $auth_header ) ) {
    $response = new WP_REST_Response(
        array( 'error' => 'Missing authorization header' ),
        401
    );
    // $response->header( 'WWW-Authenticate', 'Bearer resource_metadata="' . home_url( '/.well-known/oauth-protected-resource' ) . '"' );
    $response->header( 'WWW-Authenticate', 'Bearer resource_metadata="' . home_url( '/.well-known/oauth-protected-resource/wp-json/sky-connect/v1/mcp' ) . '"' );
    return $response;
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

       /* ------------------------------ check against warp token first ---------*/
        $warp_hash  = get_option( 'sky_connect_token_hash' );
        $oauth_hash = get_option( 'sky_connect_oauth_token_hash' ); //web claude
        
        $warp_valid = ! empty( $warp_hash ) && hash_equals( $warp_hash, wp_hash( $plain_token ) );
        $oauth_valid = ! empty( $oauth_hash ) && hash_equals( $oauth_hash, wp_hash( $plain_token ) );



        // neither matched — block
        if ( ! $warp_valid && ! $oauth_valid ) {
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





