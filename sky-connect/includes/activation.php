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

            // save plain token temporarily so admin page can show it once
            update_option('sky_connect_token_plain', $plain_token);

            // save hashed version for comparing later (this stays forever)
            update_option('sky_connect_token_hash', wp_hash($plain_token));
        }




        /* ------------------------------ generate and store client ID and secret on first activate ---------*/
        if (! get_option('sky_connect_client_id')) {

            // client ID is public — store as plain
            $client_id = 'sky-connect-' . parse_url(home_url(), PHP_URL_HOST); //client id : sky-connect-devbuggs.com

            // client secret is private — store hashed, show once
            $client_secret = bin2hex(random_bytes(32));

            update_option('sky_connect_client_id', $client_id);
            update_option('sky_connect_client_secret_plain', $client_secret);
            update_option('sky_connect_client_secret_hash', wp_hash($client_secret));
        }



        /* ------------------------------ add rewrite rule for well-known oauth URL ---------*/
        add_rewrite_rule(
            '\.well-known/oauth-authorization-server$',
            'index.php?rest_route=/sky-connect/v1/.well-known/oauth-authorization-server',
            'top'
        );
        flush_rewrite_rules();
    }
}
