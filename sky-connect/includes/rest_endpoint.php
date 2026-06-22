<?php
/*
 * This file:
 * - Registers one REST route (the MCP door)
 * - Accepts POST only, forces HTTPS
 * - Reads the JSON-RPC message Claude/Warp sends
 * - Replies with our 4 tools list (tools/list)
 * - Tool actions + auth are added in later steps
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ rest endpoint class ---------*/
class Sky_Connect_Rest {

    /* ------------------------------ hook the route registration ---------*/
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_route' ) );
    }

    /* ------------------------------ register our single MCP route ---------*/
    public function register_route() {
        register_rest_route(
            'sky-connect/v1',
            '/mcp',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_request' ),
                'permission_callback' => '__return_true', // real auth added in Step 5
            )
        );
    }

    /* ------------------------------ handle every request that hits the door ---------*/
    public function handle_request( $request ) {

        // HTTPS only (block insecure calls)
        if ( ! is_ssl() ) {
            return new WP_REST_Response(
                array( 'error' => 'HTTPS required' ),
                403
            );
        }

        // read the JSON-RPC message
        $body   = $request->get_json_params();
        $method = isset( $body['method'] ) ? $body['method'] : '';
        $id     = isset( $body['id'] ) ? $body['id'] : null;

        // when Claude asks for the tools menu
        if ( $method === 'tools/list' ) {
            return $this->tools_list( $id );
        }

        // anything else for now: simple empty reply
        return new WP_REST_Response(
            array(
                'jsonrpc' => '2.0',
                'id'      => $id,
                'result'  => array(),
            ),
            200
        );
    }

    /* ------------------------------ return our 4 tools (the menu) ---------*/
    private function tools_list( $id ) {
        return new WP_REST_Response(
            array(
                'jsonrpc' => '2.0',
                'id'      => $id,
                'result'  => array(
                    'tools' => array(
                        array(
                            'name'        => 'list_plugins',
                            'description' => 'List plugin folders',
                        ),
                        array(
                            'name'        => 'list_files',
                            'description' => 'List files inside one plugin',
                        ),
                        array(
                            'name'        => 'read_file',
                            'description' => 'Read a file',
                        ),
                        array(
                            'name'        => 'write_file',
                            'description' => 'Save a file (after checks)',
                        ),
                    ),
                ),
            ),
            200
        );
    }
}