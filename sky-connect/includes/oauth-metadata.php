<?php
/*
 * This file:
 * - Registers /.well-known/oauth-authorization-server (tells Claude where to authorize, get tokens, and register)
 * - Registers /.well-known/oauth-protected-resource (tells Claude which server the token is for)
 * - No auth needed — Claude hits these first before anything else
 * - Uses DCR — advertises a registration_endpoint so Claude registers itself
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ oauth metadata class ---------*/
class Sky_Connect_OAuth_Metadata {

    /* ------------------------------ hook both metadata routes ---------*/
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_route' ) );
        add_action( 'rest_api_init', array( $this, 'register_protected_resource_route' ) );
    }

    /* ------------------------------ register the authorization-server metadata route ---------*/
    public function register_route() {
        register_rest_route(
            'sky-connect/v1',
            '/.well-known/oauth-authorization-server',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_request' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /* ------------------------------ return authorization-server metadata (DCR) ---------*/
    public function handle_request() {

        $base_url = home_url();

        return new WP_REST_Response(
            array(
                'issuer'                                 => $base_url,
                'authorization_endpoint'                 => admin_url( 'admin.php?page=sky-connect-authorize' ),
                'token_endpoint'                         => $base_url . '/wp-json/sky-connect/v1/oauth/token',
                'registration_endpoint'                  => $base_url . '/wp-json/sky-connect/v1/oauth/register',
                'response_types_supported'               => array( 'code' ),
                'grant_types_supported'                  => array( 'authorization_code' ),
                'code_challenge_methods_supported'       => array( 'S256' ),
                'token_endpoint_auth_methods_supported'  => array( 'none' ),
            ),
            200
        );
    }

    /* ------------------------------ register the protected-resource metadata route ---------*/
    public function register_protected_resource_route() {
        register_rest_route(
            'sky-connect/v1',
            '/.well-known/oauth-protected-resource',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_protected_resource' ),
                'permission_callback' => '__return_true',
            )
        );
    }
    
    /* ------------------------------ return protected-resource metadata ---------*/
    public function handle_protected_resource() {

        $base_url = home_url();

        return new WP_REST_Response(
            array(
                'resource'                 => $base_url . '/wp-json/sky-connect/v1/mcp',
                'authorization_servers'    => array( $base_url ),
                'bearer_methods_supported' => array( 'header' ),
            ),
            200
        );
    }
}