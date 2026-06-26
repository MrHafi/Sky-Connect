<?php
/**
 * Plugin Name: Sky Connect
 * Description: Turns this WordPress site into an MCP server so Claude can read and safely edit plugin files (locked to the plugins folder).
 * Version: 1.0.0
 * Author: Hafi
 * Site: https://devbuggs.com
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

    }


} //end of main class





/* ------------------------------ create and run the plugin ---------*/
$sky_connect = new Sky_Connect();
$sky_connect->run();