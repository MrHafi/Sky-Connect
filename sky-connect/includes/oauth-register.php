<?php
/*
 * This file:
 * - Registers a Dynamic Client Registration (DCR) endpoint
 * - Claude Web hits this automatically before starting OAuth flow
 * - Accepts Claude's registration request (client_name, redirect_uris)
 * - Returns our static Client ID back to Claude
 * - Stores redirect URI Claude sends for later verification
 * - No auth needed — this is a public registration endpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ oauth register class ---------*/
class Sky_Connect_OAuth_Register {

    /* ------------------------------ hook the route registration ---------*/
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_route' ) );
    }

    /* ------------------------------ register DCR endpoint route ---------*/
    public function register_route() {
        register_rest_route(
            'sky-connect/v1',
            '/oauth/register',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_registration' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /* ------------------------------ handle Claude's registration request ---------*/
    public function handle_registration( $request ) {

        /* ------------------------------ grab what Claude sends ---------*/
        $body          = $request->get_json_params();
        $redirect_uris = isset( $body['redirect_uris'] ) ? $body['redirect_uris'] : array();
        $client_name   = isset( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : 'Claude';

        error_log( 'SKY CONNECT DCR - registration request from: ' . $client_name );
        error_log( 'SKY CONNECT DCR - redirect_uris: ' . print_r( $redirect_uris, true ) );

        /* ------------------------------ validate redirect_uri is present ---------*/
        if ( empty( $redirect_uris ) ) {
            return new WP_REST_Response(
                array( 'error' => 'invalid_redirect_uri' ),
                400
            );
        }

        /* ------------------------------ store redirect URIs Claude sent ---------*/
        update_option( 'sky_connect_registered_redirect_uris', $redirect_uris );

        /* ------------------------------ return our static client ID back to Claude ---------*/
        return new WP_REST_Response(
            array(
                'client_id'              => get_option( 'sky_connect_client_id' ),
                'client_secret'          => get_option( 'sky_connect_client_secret_plain', '' ),
                'redirect_uris'          => $redirect_uris,
                'grant_types'            => array( 'authorization_code' ),
                'response_types'         => array( 'code' ),
                'token_endpoint_auth_method' => 'client_secret_post',
            ),
            201
        );
    }
}