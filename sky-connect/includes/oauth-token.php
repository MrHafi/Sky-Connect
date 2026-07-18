<?php
/*
 * This file:
 1 Verify — check 4 different things (method, ticket alive, IDs match, secret matches puzzle)
 2 Destroy — delete the 5-min ticket so it can never be reused
 3 Issue — create and hand over the real, long-lasting access token
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ oauth token class ---------*/
class Sky_Connect_OAuth_Token {

    /* ------------------------------ hook the route registration ---------*/
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_route' ) );
    }

    /* ------------------------------ register token endpoint route ---------*/
    // Only accepts POST requests. No login required (__return_true) — Claude isn't logged in as a WP user,/
    public function register_route() {
        register_rest_route(
            'sky-connect/v1',
            '/oauth/token',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_token_request' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /* ------------------------------ handle token exchange request from Claude ---------*/
    public function handle_token_request( $request ) {


        /* ------------------------------ grab parameters ---------*/
        $client_id     = sanitize_text_field( $request->get_param( 'client_id' ) );     
        $auth_code     = sanitize_text_field( $request->get_param( 'code' ) );          // the 5 min ticket from file 3
        $code_verifier = sanitize_text_field( $request->get_param( 'code_verifier' ) ); // original secret, revealed now
        $redirect_uri  = esc_url_raw( $request->get_param( 'redirect_uri' ) );          // must match earlier saved one
        $grant_type    = sanitize_text_field( $request->get_param( 'grant_type' ) );    // confirms login method used



        /* ------------------------------ verify grant type ---------*/
        if ( $grant_type !== 'authorization_code' ) {
            return new WP_REST_Response(
                array( 'error' => 'unsupported_grant_type' ),
                400
            );
        }

        /* ------------------------------ verify auth code exists and not expired ---------*/
        $stored = get_transient( 'sky_connect_auth_code_' . $auth_code );

        if ( ! $stored ) {
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }

        /* ------------------------------ verify client_id matches what was stored ---------*/
        if ( $client_id !== $stored['client_id'] ) {
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }

       /* ------------------------------ verify redirect_uri matches ---------*/
        if ( $redirect_uri !== $stored['redirect_uri'] ) {
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }

        /* ------------------------------ verify resource (audience) matches ---------*/
        // the resource was saved when the auth code was created. if the request
        // now asks for a different resource, reject it — the token must only be
        // valid for the server it was approved for.
        $req_resource = esc_url_raw( $request->get_param( 'resource' ) );
        if ( ! empty( $stored['resource'] ) && $req_resource !== $stored['resource'] ) {
            return new WP_REST_Response(
                array( 'error' => 'invalid_target' ),
                400
            );
        }

        /* ------------------------------ verify PKCE ---------*/
        $expected_challenge = rtrim(
            strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ),
            '='
        );

        if ( ! hash_equals( $stored['code_challenge'], $expected_challenge ) ) {
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }

        /* ------------------------------ delete auth code — one time use only ---------*/
        delete_transient( 'sky_connect_auth_code_' . $auth_code );

        /* ------------------------------ generate long lived access token ---------*/
        $access_token = bin2hex( random_bytes( 32 ) );
        update_option( 'sky_connect_oauth_token_hash', wp_hash( $access_token ) );
        /* ------------------------------ return token to Claude ---------*/
   
        return new WP_REST_Response(
            array(
                'access_token' => $access_token,
                'token_type'   => 'Bearer',
            ),
            200
        );
    }
}