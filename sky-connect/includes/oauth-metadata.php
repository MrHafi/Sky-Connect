<?php
/*
 * This file:
 * - Registers a public URL at /.well-known/oauth-authorization-server
 * - Returns JSON telling Claude where to authorize and get tokens
 * - No auth needed — Claude hits this first before anything else
 * - Acts like a directory board for the OAuth flow
 * 
 * FILE TO TELL CLAUDE WHERE TO GO FOR AUTHORIZATION AND TOKENS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ oauth metadata class ---------*/
class Sky_Connect_OAuth_Metadata {

    /* ------------------------------ hook the route registration ---------*/
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_route' ) );
    }

    /* ------------------------------ register the well-known metadata route ---------*/
    public function register_route() {
        register_rest_route(
            'sky-connect/v1',
            '/.well-known/oauth-authorization-server',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_request' ),
                'permission_callback' => '__return_true', // public — no auth needed
            )
        );
    }

    /* ------------------------------ return the metadata JSON ---------*/
    public function handle_request() {

        $base_url = home_url();

        return new WP_REST_Response(
            array(
                'issuer'                                => $base_url,
                'authorization_endpoint'                => $base_url . '/wp-json/sky-connect/v1/oauth/authorize',
                'token_endpoint'                        => $base_url . '/wp-json/sky-connect/v1/oauth/token',
                'response_types_supported'              => array( 'code' ),
                'grant_types_supported'                 => array( 'authorization_code' ),
                'code_challenge_methods_supported'      => array( 'S256' ), // PKCE method
            ),
            200
        );
    }
}