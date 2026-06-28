<?php
/*
 * This file:
 * - Registers a hidden WP admin page for OAuth authorize screen
 * - Claude sends its own URL as client_id (CIMD approach)
 * - We fetch that URL and verify it is a valid Claude client
 * - Shows "Allow Claude?" screen to logged in admin
 * - On Allow — generates auth code, stores it with PKCE, redirects Claude back
 * - On Deny — redirects Claude back with error
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ oauth authorize class ---------*/
class Sky_Connect_OAuth_Authorize {

    /* ------------------------------ hook into wordpress admin ---------*/
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_init', array( $this, 'handle_decision' ) );
    }

    /* ------------------------------ register hidden admin page ---------*/
    public function register_page() {
        add_submenu_page(
            null,
            'Sky Connect — Allow Access',
            '',
            'manage_options',
            'sky-connect-authorize',
            array( $this, 'render_screen' )
        );
    }

    /* ------------------------------ handle allow or deny form submission ---------*/
    public function handle_decision() {

        if ( ! isset( $_POST['sky_connect_decision'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        /* ------------------------------ verify nonce ---------*/
        check_admin_referer( 'sky_connect_oauth_authorize' );

        $decision       = sanitize_text_field( $_POST['sky_connect_decision'] );
        $redirect_uri   = esc_url_raw( $_POST['redirect_uri'] );
        $state          = sanitize_text_field( $_POST['state'] );
        $code_challenge = sanitize_text_field( $_POST['code_challenge'] );
        $client_id      = sanitize_text_field( $_POST['client_id'] );

        /* ------------------------------ if admin clicked deny ---------*/
        if ( $decision !== 'allow' ) {
            wp_redirect( add_query_arg(
                array( 'error' => 'access_denied', 'state' => $state ),
                $redirect_uri
            ) );
            exit;
        }

        /* ------------------------------ admin clicked allow — generate auth code ---------*/
        $auth_code = bin2hex( random_bytes( 16 ) );

        // store auth code with PKCE challenge, redirect URI, and client_id
        set_transient(
            'sky_connect_auth_code_' . $auth_code,
            array(
                'code_challenge' => $code_challenge,
                'redirect_uri'   => $redirect_uri,
                'client_id'      => $client_id,
            ),
            5 * MINUTE_IN_SECONDS
        );

        /* ------------------------------ redirect Claude back with auth code ---------*/
        wp_redirect( add_query_arg(
            array( 'code' => $auth_code, 'state' => $state ),
            $redirect_uri
        ) );
        exit;
    }

    /* ------------------------------ fetch and verify CIMD client document ---------*/
    private function verify_client_id( $client_id ) {

        // client_id must be a valid HTTPS URL
        if ( strpos( $client_id, 'https://' ) !== 0 ) {
            return false;
        }

        // fetch the client metadata document
        $response = wp_remote_get( $client_id, array( 'timeout' => 5 ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'SKY CONNECT CIMD - fetch failed: ' . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) ) {
            error_log( 'SKY CONNECT CIMD - empty or invalid JSON' );
            return false;
        }

        // document must be self-referential — its client_id must match the URL we fetched
        if ( ! isset( $body['client_id'] ) || $body['client_id'] !== $client_id ) {
            error_log( 'SKY CONNECT CIMD - client_id mismatch in document' );
            return false;
        }

        error_log( 'SKY CONNECT CIMD - verified OK: ' . $client_id );
        return true;
    }

    /* ------------------------------ render allow/deny screen ---------*/
    public function render_screen() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        /* ------------------------------ grab parameters Claude sent ---------*/
        $client_id      = sanitize_text_field( $_GET['client_id'] ?? '' );
        $redirect_uri   = esc_url_raw( $_GET['redirect_uri'] ?? '' );
        $code_challenge = sanitize_text_field( $_GET['code_challenge'] ?? '' );
        $state          = sanitize_text_field( $_GET['state'] ?? '' );

        /* ------------------------------ verify required params present ---------*/
        if ( empty( $client_id ) || empty( $redirect_uri ) || empty( $code_challenge ) ) {
            wp_die( 'Missing required parameters' );
        }

        /* ------------------------------ verify CIMD client document ---------*/
        if ( ! $this->verify_client_id( $client_id ) ) {
            wp_die( 'Invalid client_id — could not verify client document' );
        }

        ?>
        <div class="wrap">
            <h1>Sky Connect — Allow Access</h1>
            <div style="max-width:500px; border:1px solid #ddd; border-radius:8px; padding:30px; margin-top:20px;">
                <h2>Allow Claude to connect?</h2>
                <p>Claude is requesting access to read and edit plugin files on this site.</p>
                <p><small>Client: <code><?php echo esc_html( $client_id ); ?></code></small></p>
                <form method="POST">
                    <?php wp_nonce_field( 'sky_connect_oauth_authorize' ); ?>
                    <input type="hidden" name="client_id"            value="<?php echo esc_attr( $client_id ); ?>">
                    <input type="hidden" name="redirect_uri"         value="<?php echo esc_attr( $redirect_uri ); ?>">
                    <input type="hidden" name="code_challenge"       value="<?php echo esc_attr( $code_challenge ); ?>">
                    <input type="hidden" name="state"                value="<?php echo esc_attr( $state ); ?>">
                    <input type="hidden" name="sky_connect_decision" value="">
                    <button type="submit" onclick="this.form.sky_connect_decision.value='allow'" class="button button-primary" style="margin-right:10px;">
                        Allow
                    </button>
                    <button type="submit" onclick="this.form.sky_connect_decision.value='deny'" class="button">
                        Deny
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}