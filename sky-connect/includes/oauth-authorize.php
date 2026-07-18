<?php
/*
 * This file:
 * File purpose: Shows you the Allow/Deny screen, and creates the 5-min temporary code when you click Allow.
 * 
 * - Registers a hidden WP admin page for OAuth authorize screen
 * - Claude sends its own URL as client_id (CIMD approach)
 * - We fetch that URL and verify it is a valid Claude client
 * - Shows "Allow Claude?" screen to logged in admin
 * - On Allow — generates auth code, stores it with PKCE, redirects Claude back
 * - On Deny — redirects Claude back with error
 * 
 * 
 * VAR:
secret = code_verifier → exact same thing, just plain text
code_challenge = the hashed version, sits in the middle
auth_code = totally separate, just a 5-min safe bridge through the browser
 */

if (! defined('ABSPATH')) {
    exit;
}

/* ------------------------------ oauth authorize class ---------*/
class Sky_Connect_OAuth_Authorize
{

    /* ------------------------------ hook into wordpress admin ---------*/
    public function init()
    {
        add_action('admin_menu', array($this, 'register_page'));
        add_action('admin_init', array($this, 'handle_decision'));
    }

    /* ------------------------------ register hidden admin page ---------*/
    public function register_page()
    {
        add_submenu_page(
            null,
            'Sky Connect — Allow Access',
            '',
            'manage_options',
            'sky-connect-authorize',
            array($this, 'render_screen')
        );
    }

    /* ------------------------------ handle allow or deny form submission ---------*/
    public function handle_decision()
    {

        if (! isset($_POST['sky_connect_decision'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        /* ------------------------------ verify nonce ---------*/
        check_admin_referer('sky_connect_oauth_authorize');

        $decision       = sanitize_text_field($_POST['sky_connect_decision']); // did click alloww or deny
        $redirect_uri   = esc_url_raw($_POST['redirect_uri']); //claude back
        $state          = sanitize_text_field($_POST['state']);
        $code_challenge = sanitize_text_field($_POST['code_challenge']); //Hashed puzzle sent by claude 
        $client_id      = sanitize_text_field($_POST['client_id']);
        $resource = esc_url_raw($_POST['resource']);

        /* ------------------------------ re-check client + redirect_uri on submit ---------*/
        // the GET side validated these, but the POST form could be tampered with.
        // we re-verify here so a forged form can't sneak a different redirect_uri
        // (which would send the auth code to an attacker's URL).
        $clients = get_option('sky_connect_dcr_clients', array());

        if (! isset($clients[$client_id])) {
            wp_die('Invalid client_id');
        }

        $allowed = $clients[$client_id]['redirect_uris'] ?? array();

        if (! in_array($redirect_uri, $allowed, true)) {
            wp_die('Invalid redirect_uri');
        }

        /* ------------------------------ if admin clicked deny ---------*/
        if ($decision !== 'allow') {
            wp_redirect(add_query_arg(
                array('error' => 'access_denied', 'state' => $state),
                $redirect_uri
            ));
            exit;
        }

        /* ------------------------------ admin clicked allow — generate auth code ---------*/
        $auth_code = bin2hex(random_bytes(16));

        // store auth code with PKCE challenge, redirect URI, and client_id
        set_transient(
            'sky_connect_auth_code_' . $auth_code,
            array(
                'code_challenge' => $code_challenge,
                'redirect_uri'   => $redirect_uri,
                'client_id'      => $client_id,
                'resource'       => $resource,
            ),
            5 * MINUTE_IN_SECONDS
        );

        /* ------------------------------ redirect Claude back with auth code ---------*/
        wp_redirect(add_query_arg(
            array('code' => $auth_code, 'state' => $state),
            $redirect_uri
        ));
        exit;
    }

    /* ------------------------------ fetch and verify CIMD client document ---------*/
    /* ------------------------------ verify client_id is a registered DCR client ---------*/
    private function verify_client_id($client_id)
    {

        $clients = get_option('sky_connect_dcr_clients', array());

        // client must exist in our registered list
        if (! isset($clients[$client_id])) {
            return false;
        }

        return true;
    }

    /* ------------------------------ render allow/deny screen ---------*/
    public function render_screen()
    {

        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        /* ------------------------------ grab parameters Claude sent ---------*/
        $client_id      = sanitize_text_field($_GET['client_id'] ?? '');
        $redirect_uri   = esc_url_raw($_GET['redirect_uri'] ?? '');
        $code_challenge = sanitize_text_field($_GET['code_challenge'] ?? '');
        $state          = sanitize_text_field($_GET['state'] ?? '');
        $resource = esc_url_raw($_GET['resource'] ?? '');

        /* ------------------------------ verify required params present ---------*/
        if (empty($client_id) || empty($redirect_uri) || empty($code_challenge)) {
            wp_die('Missing required parameters');
        }

        /* ------------------------------ verify CIMD client document ---------*/
        if (! $this->verify_client_id($client_id)) {
            wp_die('Invalid client_id — could not verify client document');
        }

        /* ------------------------------ check redirect_uri matches what was registered ---------*/
        $clients = get_option('sky_connect_dcr_clients', array());
        $allowed = $clients[$client_id]['redirect_uris'] ?? array();

        if (! in_array($redirect_uri, $allowed, true)) {
            wp_die('Invalid redirect_uri');
        }

        /* ------------------------------ get the app's registered name to show ---------*/
        // we collected client_name at registration but never showed it.
        // showing it lets you see WHO is asking before you approve.
        $app_name = $clients[$client_id]['client_name'] ?? 'Unknown app';

?>

        ?>
        <div class="wrap">
            <h1>Sky Connect — Allow Access</h1>
            <div style="max-width:500px; border:1px solid #ddd; border-radius:8px; padding:30px; margin-top:20px;">
                <h2>Allow Claude to connect?</h2>
                <p>Claude is requesting access to read and edit plugin files on this site.</p>
                <div style="background:#f6f7f7; border-radius:6px; padding:15px; margin:15px 0;">
                    <p style="margin:0 0 8px;"><strong>App requesting access:</strong> <?php echo esc_html($app_name); ?></p>
                    <p style="margin:0 0 8px;"><strong>Will send login to:</strong><br><code style="font-size:11px; word-break:break-all;"><?php echo esc_html($redirect_uri); ?></code></p>
                    <p style="margin:0;"><small style="color:#666;">Client ID: <?php echo esc_html($client_id); ?></small></p>
                </div>
                <p style="color:#d63638;"><small>⚠️ Only approve if you recognize this app and the URL above looks correct.</small></p>
                <form method="POST">
                    <?php wp_nonce_field('sky_connect_oauth_authorize'); ?>
                    <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>">
                    <input type="hidden" name="redirect_uri" value="<?php echo esc_attr($redirect_uri); ?>">
                    <input type="hidden" name="code_challenge" value="<?php echo esc_attr($code_challenge); ?>">
                    <input type="hidden" name="state" value="<?php echo esc_attr($state); ?>">
                    <input type="hidden" name="sky_connect_decision" value="">
                    <input type="hidden" name="resource" value="<?php echo esc_attr($resource); ?>">
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
