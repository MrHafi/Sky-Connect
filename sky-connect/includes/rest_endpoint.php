<?php
/*
 * This file:
 * - Registers one REST route (the MCP door)
 * - Accepts POST only, forces HTTPS
 * - Reads the JSON-RPC message Claude/Warp sends
 * - Replies with our 4 tools list (tools/list)
 * - Runs tools when Claude calls them (tools/call)
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
                'permission_callback' => '__return_true',  // MCP needs the request to reach us so we can send the 401 discovery signal; real auth runs inside handle_request() via Sky_Connect_Auth::check()
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

        /* ------------------------------ run token check before anything else ---------*/
        require_once SKY_CONNECT_DIR . 'includes/auth.php';
        $auth = Sky_Connect_Auth::check( $request );

        // if auth did not return true — send back the error (e.g. 401 with WWW-Authenticate)
        if ( $auth !== true ) {
            return $auth;
        }

        // read the JSON-RPC message Claude/Warp sends
        $body   = $request->get_json_params();
        $method = isset( $body['method'] ) ? $body['method'] : '';
        $id     = isset( $body['id'] ) ? $body['id'] : null;
        $params = isset( $body['params'] ) ? $body['params'] : array(); // params holds the tool name + its inputs

        /* ------------------------------ notifications have no id — accept with 202, no body ---------*/
        if ( $id === null && strpos( $method, 'notifications/' ) === 0 ) {
            return new WP_REST_Response( null, 202 );
        }

        /* ------------------------------ initialize handshake — must return Mcp-Session-Id header ---------*/
        if ( $method === 'initialize' ) {
            $session_id = bin2hex( random_bytes( 16 ) );

            $response = new WP_REST_Response(
                array(
                    'jsonrpc' => '2.0',
                    'id'      => $id,
                    'result'  => array(
                        'protocolVersion' => '2024-11-05',
                        'serverInfo'      => array(
                            'name'    => 'Sky Connect',
                            'version' => SKY_CONNECT_VERSION,
                        ),
                        'capabilities' => array(
                            'tools' => array( 'listChanged' => false ),
                        ),
                    ),
                ),
                200
            );
            $response->header( 'Mcp-Session-Id', $session_id );
            return $response;
        }

        /* ------------------------------ when Claude asks for the tools menu ---------*/
        if ( $method === 'tools/list' ) {
            return $this->tools_list( $id );
        }

        /* ------------------------------ when Claude actually RUNS a tool ---------*/
        if ( $method === 'tools/call' ) {

            require_once SKY_CONNECT_DIR . 'includes/jail.php';
            require_once SKY_CONNECT_DIR . 'tools/tools.php';

            $tool_name = isset( $params['name'] ) ? $params['name'] : '';
            $args      = isset( $params['arguments'] ) ? $params['arguments'] : array();

            $result = null;

            /* ------------------------------ pick which tool to run ---------*/
            if ( $tool_name === 'list_plugins' ) {
                $result = Sky_Connect_Tools::list_plugins();

            } elseif ( $tool_name === 'list_files' ) {
                $plugin = isset( $args['plugin'] ) ? $args['plugin'] : '';
                $result = Sky_Connect_Tools::list_files( $plugin );

            } elseif ( $tool_name === 'read_file' ) {
                $path   = isset( $args['path'] ) ? $args['path'] : '';
                $result = Sky_Connect_Tools::read_file( $path );

            } elseif ( $tool_name === 'write_file' ) {
                $path    = isset( $args['path'] ) ? $args['path'] : '';
                $content = isset( $args['content'] ) ? $args['content'] : '';
                $result  = Sky_Connect_Tools::write_file( $path, $content );

           } elseif ( $tool_name === 'read_error_log' ) {
                $result = Sky_Connect_Tools::read_error_log();

            } elseif ( $tool_name === 'multi-file-write' ) {
                require_once SKY_CONNECT_DIR . 'tools/multi-file-write.php';
                $files  = isset( $args['files'] ) ? $args['files'] : array();
                $result = Sky_Connect_Multi_File::write_multiple_files( $files );

            } else {
                $result = array( 'error' => 'Unknown tool' );
            }

            /* ------------------------------ send the result back to Claude ---------*/
            return new WP_REST_Response(
                array(
                    'jsonrpc' => '2.0',
                    'id'      => $id,
                    'result'  => array(
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => is_string( $result ) ? $result : wp_json_encode( $result ),
                            ),
                        ),
                    ),
                ),
                200
            );
        }

        /* ------------------------------ any other method — empty result ---------*/
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

                        /* ------------------------------ tool 1: no input needed ---------*/
                        array(
                            'name'        => 'list_plugins',
                            'description' => 'List all plugin folders',
                            'inputSchema' => array(
                                'type'       => 'object',
                                'properties' => (object) array(),
                            ),
                        ),

                        /* ------------------------------ tool 2: needs plugin folder name ---------*/
                        array(
                            'name'        => 'list_files',
                            'description' => 'List files inside one plugin folder',
                            'inputSchema' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'plugin' => array(
                                        'type'        => 'string',
                                        'description' => 'Plugin folder name',
                                    ),
                                ),
                                'required'   => array( 'plugin' ),
                            ),
                        ),

                        /* ------------------------------ tool 3: needs file path ---------*/
                        array(
                            'name'        => 'read_file',
                            'description' => 'Read a file inside a plugin',
                            'inputSchema' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'path' => array(
                                        'type'        => 'string',
                                        'description' => 'File path relative to plugins folder',
                                    ),
                                ),
                                'required'   => array( 'path' ),
                            ),
                        ),

                        /* ------------------------------ tool 4: needs path + new content ---------*/
                        array(
                            'name'        => 'write_file',
                            'description' => 'Save a file after safety checks',
                            'inputSchema' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'path'    => array(
                                        'type'        => 'string',
                                        'description' => 'File path relative to plugins folder',
                                    ),
                                    'content' => array(
                                        'type'        => 'string',
                                        'description' => 'New file content',
                                    ),
                                ),
                                'required'   => array( 'path', 'content' ),
                            ),
                        ),
                        /* ------------------------------ tool 5: no input needed ---------*/
                        array(
                            'name'        => 'read_error_log',
                            'description' => 'Read the last 50 lines of the WordPress error log',
                            'inputSchema' => array(
                                'type'       => 'object',
                                'properties' => (object) array(),
                            ),
                        ),
                        
                        /* ------------------------------ tool 6: write several files at once (all-or-nothing) ---------*/
                        array(
                            'name'        => 'multi-file-write',
                            'description' => 'Write several plugin files together as one atomic change. Use this when edits span multiple files that must work together (e.g. renaming a function used across files). If any file fails or the site breaks, ALL files roll back — no half-applied changes. For a single file, use write_file instead.',
                            'inputSchema' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'files' => array(
                                        'type'        => 'array',
                                        'description' => 'List of files to write together',
                                        'items'       => array(
                                            'type'       => 'object',
                                            'properties' => array(
                                                'path'    => array(
                                                    'type'        => 'string',
                                                    'description' => 'File path relative to plugins folder',
                                                ),
                                                'content' => array(
                                                    'type'        => 'string',
                                                    'description' => 'New file content',
                                                ),
                                            ),
                                            'required'   => array( 'path', 'content' ),
                                        ),
                                    ),
                                ),
                                'required'   => array( 'files' ),
                            ),
                        ),

                    ),
                ),
            ),
            200
        );


        
    }
}