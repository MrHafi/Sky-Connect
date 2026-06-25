<?php
/*
 * This file:
 * - Registers "Sky Connect" page under WP Settings menu
 * - Shows plain token once for copying, then clears it from DB
 * - Handles regenerate button to make a new token
 * - Shows master ON/OFF switch to enable/disable plugin
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

    /* ------------------------------ add page under settings menu ---------*/
    public function register_menu() {
        add_options_page(
            'Sky Connect',
            'Sky Connect',
            'manage_options',
            'sky-connect',
            array( $this, 'render_page' )
        );
    }

    /* ------------------------------ handle regenerate + ON/OFF form submissions ---------*/
    public function handle_actions() {

        // only admins can do this
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        /* ------------------------------ handle token regeneration ---------*/
        if (
            isset( $_POST['sky_connect_regenerate'] ) &&
            check_admin_referer( 'sky_connect_regenerate_token' )
        ) {
            // generate new plain token
            $plain_token = bin2hex( random_bytes( 32 ) );

            // save plain once so page can show it
            update_option( 'sky_connect_token_plain', $plain_token );

            // save new hash (replaces old one)
            update_option( 'sky_connect_token_hash', wp_hash( $plain_token ) );

            wp_redirect( admin_url( 'options-general.php?page=sky-connect&regenerated=1' ) );
            exit;
        }

        /* ------------------------------ handle master ON/OFF toggle ---------*/
        if (
            isset( $_POST['sky_connect_toggle'] ) &&
            check_admin_referer( 'sky_connect_toggle_switch' )
        ) {
            $current = get_option( 'sky_connect_enabled', 0 );
            update_option( 'sky_connect_enabled', $current ? 0 : 1 );

            wp_redirect( admin_url( 'options-general.php?page=sky-connect' ) );
            exit;
        }
    }

    /* ------------------------------ render the admin page HTML ---------*/
    public function render_page() {

        $enabled     = get_option( 'sky_connect_enabled', 0 );
        $plain_token = get_option( 'sky_connect_token_plain', '' );

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

            <?php /* ------------------------------ token section ---------*/ ?>
            <h2>Warp Bearer Token</h2>

            <?php if ( ! empty( $plain_token ) ) : ?>

                <p><strong>Copy this token now — it will not be shown again:</strong></p>
                <code style="font-size:14px;"><?php echo esc_html( $plain_token ); ?></code>

                <?php
                // clear plain token from DB right after showing it
                delete_option( 'sky_connect_token_plain' );
                ?>

            <?php else : ?>
                <p>Token is set. Use regenerate to get a new one.</p>
            <?php endif; ?>

            <br><br>
            <form method="post">
                <?php wp_nonce_field( 'sky_connect_regenerate_token' ); ?>
                <button type="submit" name="sky_connect_regenerate" class="button button-primary">
                    Regenerate Token
                </button>
            </form>

        </div>
        <?php
    }
}