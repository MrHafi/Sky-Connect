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


        /* ------------------------------ regenerate emergency key ---------*/
        if (
            isset( $_POST['sky_connect_regen_emergency'] ) &&
            check_admin_referer( 'sky_connect_regen_emergency' )
        ) {
            update_option( 'sky_connect_emergency_key', bin2hex( random_bytes( 16 ) ) );
            wp_redirect( admin_url( 'admin.php?page=sky-connect' ) );
            exit;
        }
    }
        

   /* ------------------------------ render the admin page ---------*/
/* ------------------------------ render the admin page ---------*/
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

            <?php /* ------------------------------ warp bearer token section ---------*/ ?>
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

            <?php /* ------------------------------ emergency restore section ---------*/ ?>
            <h2>Emergency Restore</h2>
            <p>If a bad edit ever breaks your site, open this URL in your browser to restore the newest backup. <strong>Bookmark it now.</strong></p>
            <?php
            $key      = get_option( 'sky_connect_emergency_key', '' );
            $base_url = home_url( '/?sky_restore=' . $key );
            ?>
            <p><strong>Your emergency URL:</strong></p>
            <code style="font-size:12px; word-break:break-all;"><?php echo esc_html( $base_url ); ?></code>
            <p style="color:#d63638;"><small>Keep this private — anyone with this URL can restore your files.</small></p>

            <p style="color:#d63638;"><small>Keep this private. The key changes automatically after each use.</small></p>

            <form method="post">
                <?php wp_nonce_field( 'sky_connect_regen_emergency' ); ?>
                <button type="submit" name="sky_connect_regen_emergency" class="button">
                    Regenerate Emergency Key
                </button>
            </form>
            <hr>

            <?php /* ------------------------------ activity log section ---------*/ ?>
            <h2>Activity Log</h2>
            <p>The last 50 things Claude did on this site.</p>

            <?php
            require_once SKY_CONNECT_DIR . 'includes/logger.php';
            $logs = Sky_Connect_Logger::get_recent( 50 );
            ?>

            <?php if ( empty( $logs ) ) : ?>
                <p>Nothing logged yet.</p>
            <?php else : ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Tool</th>
                            <th>File</th>
                            <th>Result</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( $log->created_at ); ?></td>
                            <td><code><?php echo esc_html( $log->tool ); ?></code></td>
                            <td><?php echo esc_html( $log->file_path ); ?></td>
                            <td>
                                <?php
                                $colour = '#666';
                                if ( $log->status === 'success' )        { $colour = 'green'; }
                                if ( $log->status === 'blocked' )        { $colour = '#d63638'; }
                                if ( $log->status === 'rolled_back' )    { $colour = '#dba617'; }
                                if ( $log->status === 'restore_failed' ) { $colour = '#d63638'; }
                                ?>
                                <strong style="color:<?php echo esc_attr( $colour ); ?>">
                                    <?php echo esc_html( $log->status ); ?>
                                </strong>
                            </td>
                            <td><?php echo esc_html( $log->message ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>
        <?php
    }
}