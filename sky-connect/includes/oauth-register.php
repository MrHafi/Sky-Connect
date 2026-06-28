<?php
/*
 * This file:
 * - Registers the DCR (Dynamic Client Registration) endpoint
 * - Claude POSTs here first to register itself
 * - Claude sends its name + redirect_uris
 * - We create a fresh client_id, store it with the redirect_uris
 * - We send the client_id back to Claude
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

    /* ------------------------------ register the DCR endpoint route ---------*/
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

        $body = $request->get_json_params();

        /* ------------------------------ grab redirect URIs Claude sends ---------*/
        $redirect_uris = isset( $body['redirect_uris'] ) ? $body['redirect_uris'] : array();
        $client_name   = isset( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : 'Claude';

        /* ------------------------------ redirect URIs are required ---------*/
        if ( empty( $redirect_uris ) || ! is_array( $redirect_uris ) ) {
            return new WP_REST_Response(
                array( 'error' => 'invalid_redirect_uri' ),
                400
            );
        }

        /* ------------------------------ create a fresh client_id ---------*/
        $client_id = 'sky-client-' . bin2hex( random_bytes( 16 ) );

        /* ------------------------------ store this client's details ---------*/
        $clients = get_option( 'sky_connect_dcr_clients', array() );
        $clients[ $client_id ] = array(
            'client_name'   => $client_name,
            'redirect_uris' => array_map( 'esc_url_raw', $redirect_uris ),
            'created'       => time(),
        );
        update_option( 'sky_connect_dcr_clients', $clients );


        /* ------------------------------ return client_id to Claude (DCR response) ---------*/
        return new WP_REST_Response(
            array(
                'client_id'                  => $client_id,
                'redirect_uris'              => $redirect_uris,
                'client_name'                => $client_name,
                'grant_types'                => array( 'authorization_code' ),
                'response_types'             => array( 'code' ),
                'token_endpoint_auth_method' => 'none',
            ),
            201
        );
    }
}