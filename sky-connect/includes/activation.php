<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ activator class ---------*/
class Sky_Connect_Activator {

    /* ------------------------------ run on activation ---------*/
    public static function activate() {

        /* ------------------------------ master switch OFF by default ---------*/
        // safe start — nothing exposed until you turn it on
        if ( get_option( 'sky_connect_enabled' ) === false ) {
            add_option( 'sky_connect_enabled', 0 );
        }

        /* ------------------------------ generate and store bearer token on first activate ---------*/
        if ( ! get_option( 'sky_connect_token_hash' ) ) {

            // generate a strong random plain token
            $plain_token = bin2hex( random_bytes( 32 ) );

            // save plain token temporarily so the admin page can show it once
            update_option( 'sky_connect_token_plain', $plain_token );

            // save hashed version for comparing later
            update_option( 'sky_connect_token_hash', wp_hash( $plain_token ) );
        }

        /* ------------------------------ emergency restore key (create BEFORE mu-plugin uses it) ---------*/
        if ( ! get_option( 'sky_connect_emergency_key' ) ) {
            update_option( 'sky_connect_emergency_key', bin2hex( random_bytes( 16 ) ) );
        }

        /* ------------------------------ rewrite rules for well-known oauth URLs ---------*/
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

        /* ------------------------------ turn on the daily cleanup schedule ---------*/
        if ( ! wp_next_scheduled( 'sky_connect_daily' ) ) {
            wp_schedule_event( time(), 'daily', 'sky_connect_daily' );
        }

        /* ------------------------------ install the emergency mu-plugin ---------*/
        require_once SKY_CONNECT_DIR . 'mu-installer.php';
        Sky_Connect_MU_Installer::install();
    }
}