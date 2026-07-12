<?php

if (! defined('ABSPATH')) {
    exit;
}

/* ------------------------------ activator class ---------*/
class Sky_Connect_Activator
{

    /* ------------------------------ run on activation ---------*/
    public static function activate()
    {

        // master switch OFF by default (safe start, nothing exposed yet)
        if (get_option('sky_connect_enabled') === false) {
            add_option('sky_connect_enabled', 0);
        }

        /* ------------------------------ generate and store bearer token on first activate ---------*/
        if (! get_option('sky_connect_token_hash')) {

            // generate a strong random plain token
            $plain_token = bin2hex(random_bytes(32));

  
        }



        /* ------------------------------ add rewrite rule for well-known oauth URL ---------*/
        add_rewrite_rule(
            '\.well-known/oauth-authorization-server$',
            'index.php?rest_route=/sky-connect/v1/.well-known/oauth-authorization-server',
            'top'
        );

        add_rewrite_rule(
    '\.well-known/oauth-protected-resource$',
    'index.php?rest_route=/sky-connect/v1/.well-known/oauth-protected-resource',
    'top'
);

add_rewrite_rule(
    '\.well-known/oauth-protected-resource/wp-json/sky-connect/v1/mcp$',
    'index.php?rest_route=/sky-connect/v1/.well-known/oauth-protected-resource',
    'top'
);

add_rewrite_rule(
    '\.well-known/oauth-authorization-server(.*)$',
    'index.php?rest_route=/sky-connect/v1/.well-known/oauth-authorization-server',
    'top'
);


        flush_rewrite_rules();

/* ------------------------------ create the log table ---------*/
        require_once SKY_CONNECT_DIR . 'includes/logger.php';
        Sky_Connect_Logger::create_table();
        
        }
}
