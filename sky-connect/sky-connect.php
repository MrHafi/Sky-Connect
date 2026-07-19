<?php
/**
 * Plugin Name: Sky Connect
 * Description: Turns this WordPress site into an MCP server so Claude can read and safely edit plugin files (locked to the plugins folder).
 * Version: 1.0.0
 * Author: Hafi
 * Site: https://devbuggs.io
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ define plugin paths and the jail folder ---------*/
define( 'SKY_CONNECT_FILE', __FILE__ );                       // this file
define( 'SKY_CONNECT_DIR', plugin_dir_path( __FILE__ ) );     // our plugin folder path
define( 'SKY_CONNECT_URL', plugin_dir_url( __FILE__ ) );      // our plugin folder url
define( 'SKY_CONNECT_JAIL', WP_PLUGIN_DIR );                  // the ONLY folder Claude can touch
define( 'SKY_CONNECT_VERSION', '1.0.0' );                     //Version  

/* ------------------------------ register activation + deactivation hooks (load file only when needed) ---------*/
register_activation_hook( SKY_CONNECT_FILE, function () {
    require_once SKY_CONNECT_DIR . 'includes/activation.php';
    Sky_Connect_Activator::activate();
} );

register_deactivation_hook( SKY_CONNECT_FILE, function () {
    require_once SKY_CONNECT_DIR . 'includes/deactivation.php';
    Sky_Connect_Deactivator::deactivate();
} );

/* ------------------------------ main class: boots the whole plugin ---------*/
final class Sky_Connect {

    /* ------------------------------ start the plugin ---------*/
    public function run() {


    /* ------------------------------ load + start the admin page ---------*/
        if ( is_admin() ) {
            require_once SKY_CONNECT_DIR . 'admin/admin-page.php';
            $admin = new Sky_Connect_Admin();
            $admin->init();
        }

        /* ------------------------------ load + start the REST endpoint ---------*/
        require_once SKY_CONNECT_DIR . 'includes/rest_endpoint.php';
        $rest = new Sky_Connect_Rest();
        $rest->init();

        /* ------------------------------ load + start oauth metadata ---------*/
        require_once SKY_CONNECT_DIR . 'includes/oauth-metadata.php';
        $metadata = new Sky_Connect_OAuth_Metadata();
        $metadata->init();


        /* ------------------------------ load + start oauth authorize ---------*/
        require_once SKY_CONNECT_DIR . 'includes/oauth-authorize.php';
        $authorize = new Sky_Connect_OAuth_Authorize();
        $authorize->init();


        /* ------------------------------ load + start oauth token endpoint ---------*/
        require_once SKY_CONNECT_DIR . 'includes/oauth-token.php';
        $token = new Sky_Connect_OAuth_Token();
        $token->init();

        /* ------------------------------ load + start oauth register (DCR) ---------*/
        require_once SKY_CONNECT_DIR . 'includes/oauth-register.php';
        $register = new Sky_Connect_OAuth_Register();
        $register->init();

        /* ------------------------------ load the safety jail ---------*/
        require_once SKY_CONNECT_DIR . 'includes/jail.php';

        /* ------------------------------ load the tools ---------*/
require_once SKY_CONNECT_DIR . 'tools/tools.php';
        


/* ------------------------------ add restore link to WP's recovery email ---------*/
        require_once SKY_CONNECT_DIR . 'includes/recovery-email.php';
        $recovery = new Sky_Connect_Recovery_Email();
        $recovery->init();

        
/* ------------------------------ schedule daily cleanup ---------*/
        // WordPress fires 'sky_connect_daily' once a day. we hook our two cleanup
        // jobs to it so old backups and old logs delete themselves automatically.
        add_action( 'sky_connect_daily', function () {
            require_once SKY_CONNECT_DIR . 'includes/backup.php';
            require_once SKY_CONNECT_DIR . 'includes/logger.php';
            Sky_Connect_Backup::clean_old();
            Sky_Connect_Logger::clean_old();
        } );



        
    }


} //end of main class





/* ------------------------------ create and run the plugin ---------*/
$sky_connect = new Sky_Connect();
$sky_connect->run();
