<?php
/*
 * This file:
 * - Registers "Sky Connect" as its own menu item in WP admin
 * - Shows master ON/OFF switch
 * - Shows plain token once for copying, clears only after user confirms
 * - Shows Client ID always (public)
 * - Shows plain Client Secret once, clears only after user confirms
 * - Handles regenerate for token and secret
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ admin page class ---------*/
class Sky_Connect_Admin {

    /* ------------------------------ hook into wordpress admin ---------*/
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    /* ------------------------------ add sky connect as its own menu item ---------*/
    public function register_menu() {
        add_menu_page(
            'Sky Connect',
            'Sky Connect',
            'manage_options',
            'sky-connect',
            array( $this, 'render_page' ),
            'dashicons-cloud',
            30
        );
    }

    /* ------------------------------ handle all form submissions ---------*/
    public function handle_actions() {

        // only admins can do this
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        /* ------------------------------ handle master ON/OFF toggle ---------*/
        if (
            isset( $_POST['sky_connect_toggle'] ) &&
            check_admin_referer( 'sky_connect_toggle_switch' )
        ) {
            $current = get_option( 'sky_connect_enabled', 0 );
            update_option( 'sky_connect_enabled', $current ? 0 : 1 );

            wp_redirect( admin_url( 'admin.php?page=sky-connect' ) );
            exit;
        }

        /* ------------------------------ handle token regeneration ---------*/
        if (
            isset( $_POST['sky_connect_regenerate'] ) &&
            check_admin_referer( 'sky_connect_regenerate_token' )
        ) {
            $plain_token = bin2hex( random_bytes( 32 ) );
            update_option( 'sky_connect_token_plain', $plain_token );
            update_option( 'sky_connect_token_hash', wp_hash( $plain_token ) );

            wp_redirect( admin_url( 'admin.php?page=sky-connect' ) );
            exit;
        }

        /* ------------------------------ delete plain token after user confirms copy ---------*/
        if (
            isset( $_POST['sky_connect_token_copied'] ) &&
            check_admin_referer( 'sky_connect_confirm_token_copied' )
        ) {
            delete_option( 'sky_connect_token_plain' );
            wp_redirect( admin_url( 'admin.php?page=sky-connect' ) );
            exit;
        }

        /* ------------------------------ handle client secret regeneration ---------*/
        if (
            isset( $_POST['sky_connect_regenerate_secret'] ) &&
            check_admin_referer( 'sky_connect_regenerate_secret' )
        ) {
            $client_secret = bin2hex( random_bytes( 32 ) );
            update_option( 'sky_connect_client_secret_plain', $client_secret );
            update_option( 'sky_connect_client_secret_hash', wp_hash( $client_secret ) );

            wp_redirect( admin_url( 'admin.php?page=sky-connect' ) );
            exit;
        }

        /* ------------------------------ delete plain secret after user confirms copy ---------*/
        if (
            isset( $_POST['sky_connect_secret_copied'] ) &&
            check_admin_referer( 'sky_connect_confirm_secret_copied' )
        ) {
            delete_option( 'sky_connect_client_secret_plain' );
            wp_redirect( admin_url( 'admin.php?page=sky-connect' ) );
            exit;
        }
    }

    /* ------------------------------ render the admin page ---------*/
    public function render_page() {

        $enabled      = get_option( 'sky_connect_enabled', 0 );
        $plain_token  = get_option( 'sky_connect_token_plain', '' );
        $plain_secret = get_option( 'sky_connect_client_secret_plain', '' );

        ?>
        <div class="wrap">
            <h1>Sky Connect</h1>

            <?php /* ------------------------------ master switch section ---------*/ ?>
            <h2>Master Switch</h2>
            <form method="post">
                <?php wp_nonce_field( 'sky_connect_toggle_switch' ); ?>
                <p>Status: <strong><?php echo $enabled ? 'ON' : 'OFF'; ?></strong></p>
                <button type="submit" name="sky_connect_toggle" class="button">
                    <?php echo $enabled ? 'Turn OFF' : 'Turn ON'; ?>
                </button>
            </form>

            <hr>

            <?php /* ------------------------------ warp bearer token section ---------*/ ?>
            <!-- Token exists → show it with "I copied it" button. Token cleared → show "Regenerate" button instead -->
            <h2>Warp Bearer Token</h2>

            <?php if ( ! empty( $plain_token ) ) : ?>
                <p><strong>Copy this token now — it will not be shown again:</strong></p>
                <code style="font-size:14px;"><?php echo esc_html( $plain_token ); ?></code>
                <br><br>
                <form method="post">
                    <?php wp_nonce_field( 'sky_connect_confirm_token_copied' ); ?>
                    <button type="submit" name="sky_connect_token_copied" class="button">
                        I copied it ✓
                    </button>
                </form>
            <?php else : ?>
                <p>Token is set. Use regenerate to get a new one.</p>
                <form method="post">
                    <?php wp_nonce_field( 'sky_connect_regenerate_token' ); ?>
                    <button type="submit" name="sky_connect_regenerate" class="button button-primary">
                        Regenerate Token
                    </button>
                </form>
            <?php endif; ?>

            <hr>

            <?php /* ------------------------------ client credentials section ---------*/ ?>
            <h2>Claude Web — Client Credentials</h2>

            <p><strong>Client ID:</strong></p>
            <code><?php echo esc_html( get_option( 'sky_connect_client_id', '—' ) ); ?></code>

            <br><br>

            <?php if ( ! empty( $plain_secret ) ) : ?>
                <p><strong>Client Secret — copy now, will not show again:</strong></p>
                <code style="font-size:14px;"><?php echo esc_html( $plain_secret ); ?></code>
                <br><br>
                <form method="post">
                    <?php wp_nonce_field( 'sky_connect_confirm_secret_copied' ); ?>
                    <button type="submit" name="sky_connect_secret_copied" class="button">
                        I copied it ✓
                    </button>
                </form>
            <?php else : ?>
                <p>Client Secret is set. Use regenerate to get a new one.</p>
                <form method="post">
                    <?php wp_nonce_field( 'sky_connect_regenerate_secret' ); ?>
                    <button type="submit" name="sky_connect_regenerate_secret" class="button button-primary">
                        Regenerate Client Secret
                    </button>
                </form>
            <?php endif; ?>

        </div>
        <?php
    }
}