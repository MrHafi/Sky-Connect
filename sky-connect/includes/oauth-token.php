<?php
/*
 * This file:
 * - Registers the token endpoint URL
 * - Claude hits this with the auth code from authorize screen
 * - No client secret needed — CIMD clients are public by definition
 * - We verify auth code is valid and not expired
 * - We verify PKCE — code_verifier must match stored code_challenge
 * - We generate a long lived access token
 * - We store token hash in DB
 * - We return plain token to Claude
 * - Auth code deleted after use — one time only
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

        error_log( '--- SKY CONNECT TOKEN START ---' );

        /* ------------------------------ grab parameters ---------*/
        $client_id     = sanitize_text_field( $request->get_param( 'client_id' ) );
        $auth_code     = sanitize_text_field( $request->get_param( 'code' ) );
        $code_verifier = sanitize_text_field( $request->get_param( 'code_verifier' ) );
        $redirect_uri  = esc_url_raw( $request->get_param( 'redirect_uri' ) );
        $grant_type    = sanitize_text_field( $request->get_param( 'grant_type' ) );

        error_log( 'SKY CONNECT - grant_type: ' . $grant_type );
        error_log( 'SKY CONNECT - client_id: ' . $client_id );
        error_log( 'SKY CONNECT - auth_code: ' . $auth_code );

        /* ------------------------------ verify grant type ---------*/
        if ( $grant_type !== 'authorization_code' ) {
            error_log( 'SKY CONNECT - FAILED: wrong grant_type' );
            return new WP_REST_Response(
                array( 'error' => 'unsupported_grant_type' ),
                400
            );
        }
        error_log( 'SKY CONNECT - grant_type OK' );

        /* ------------------------------ verify auth code exists and not expired ---------*/
        $stored = get_transient( 'sky_connect_auth_code_' . $auth_code );
        error_log( 'SKY CONNECT - auth code lookup: ' . print_r( $stored, true ) );

        if ( ! $stored ) {
            error_log( 'SKY CONNECT - FAILED: auth code not found or expired' );
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }
        error_log( 'SKY CONNECT - auth code OK' );

        /* ------------------------------ verify client_id matches what was stored ---------*/
        if ( $client_id !== $stored['client_id'] ) {
            error_log( 'SKY CONNECT - FAILED: client_id mismatch' );
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }
        error_log( 'SKY CONNECT - client_id OK' );

        /* ------------------------------ verify redirect_uri matches ---------*/
        if ( $redirect_uri !== $stored['redirect_uri'] ) {
            error_log( 'SKY CONNECT - FAILED: redirect_uri mismatch' );
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }
        error_log( 'SKY CONNECT - redirect_uri OK' );

        /* ------------------------------ verify PKCE ---------*/
        $expected_challenge = rtrim(
            strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ),
            '='
        );
        error_log( 'SKY CONNECT - PKCE expected: ' . $expected_challenge );
        error_log( 'SKY CONNECT - PKCE stored: ' . $stored['code_challenge'] );

        if ( ! hash_equals( $stored['code_challenge'], $expected_challenge ) ) {
            error_log( 'SKY CONNECT - FAILED: PKCE mismatch' );
            return new WP_REST_Response(
                array( 'error' => 'invalid_grant' ),
                400
            );
        }
        error_log( 'SKY CONNECT - PKCE OK' );

        /* ------------------------------ delete auth code — one time use only ---------*/
        delete_transient( 'sky_connect_auth_code_' . $auth_code );

        /* ------------------------------ generate long lived access token ---------*/
        $access_token = bin2hex( random_bytes( 32 ) );
        update_option( 'sky_connect_oauth_token_hash', wp_hash( $access_token ) );
        error_log( 'SKY CONNECT - token generated and stored' );

        /* ------------------------------ return token to Claude ---------*/
        error_log( '--- SKY CONNECT TOKEN SUCCESS ---' );
        return new WP_REST_Response(
            array(
                'access_token' => $access_token,
                'token_type'   => 'Bearer',
            ),
            200
        );
    }
}