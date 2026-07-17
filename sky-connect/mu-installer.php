<?php
/*
 * This file:
 * - Writes a tiny "must-use" plugin into wp-content/mu-plugins/
 * - That mu-plugin loads BEFORE normal plugins, so it still runs even when
 *   a bad edit has crashed the regular plugins
 * - It only acts when it sees ?sky_restore=KEY in the URL
 * - Runs on activation
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ mu-plugin installer ---------*/
class Sky_Connect_MU_Installer {

    /* ------------------------------ write the mu-plugin file ---------*/
    public static function install() {

        $mu_dir = WPMU_PLUGIN_DIR; // wp-content/mu-plugins

        // make the folder if it doesn't exist
        if ( ! is_dir( $mu_dir ) ) {
            wp_mkdir_p( $mu_dir );
        }

        // if we can't write there, quietly give up (some hosts lock it)
        if ( ! is_writable( $mu_dir ) ) {
            return false;
        }

        $target = $mu_dir . '/sky-connect-restore.php';

        // the actual code the mu-plugin will contain
        $code = self::mu_code();

        return file_put_contents( $target, $code ) !== false;
    }

    /* ------------------------------ the code that goes INTO the mu-plugin ---------*/
    private static function mu_code() {

        // NOTE: this string becomes its own standalone file.
        // it is kept tiny and bulletproof — a bug here could affect every page.
        return <<<'PHP'
<?php
/*
 * Sky Connect — Emergency Restore (must-use)
 * Auto-generated. Loads before normal plugins so it works even during a crash.
 * Trigger:  /?sky_restore=KEY&file=plugin/file.php
 */

// only do anything if the restore parameter is present — otherwise stay invisible
if ( empty( $_GET['sky_restore'] ) ) {
    return;
}

// run our restore very early, before the broken plugin loads
add_action( 'muplugins_loaded', function () {

    $provided = isset( $_GET['sky_restore'] ) ? $_GET['sky_restore'] : '';
    $real     = get_option( 'sky_connect_emergency_key' );

    // wrong or missing key = do nothing
    if ( empty( $real ) || ! hash_equals( $real, $provided ) ) {
        return;
    }

    $file = isset( $_GET['file'] ) ? $_GET['file'] : '';

    // no file given — list available backups
    $backup_dir = WP_CONTENT_DIR . '/sky-connect-backups';

    if ( $file === '' ) {
        echo '<h2>Sky Connect — Emergency Restore</h2>';
        echo '<p>Add &file=PLUGIN/FILE.php to the URL to restore that file.</p><ul>';
        foreach ( glob( $backup_dir . '/*.bak' ) ?: array() as $b ) {
            echo '<li>' . htmlspecialchars( basename( $b ) ) . '</li>';
        }
        echo '</ul>';
        exit;
    }

    // build safe names (mirror of the main plugin's backup naming)
    $safe_name = str_replace( array( '/', '\\' ), '_', ltrim( $file, '/' ) );
    $matches   = glob( $backup_dir . '/' . $safe_name . '.*.bak' ) ?: array();

    if ( empty( $matches ) ) {
        exit( 'No backup found for ' . htmlspecialchars( $file ) );
    }

    // newest backup wins
    sort( $matches );
    $newest = end( $matches );

    // the live file to overwrite
    $live = WP_PLUGIN_DIR . '/' . ltrim( $file, '/' );

    if ( copy( $newest, $live ) ) {
        exit( 'Restored newest backup of ' . htmlspecialchars( $file ) . '. Check your site now.' );
    }

    exit( 'Restore failed — could not write the file.' );
} );
PHP;
    }

    /* ------------------------------ remove the mu-plugin (on deactivation) ---------*/
    public static function remove() {
        $target = WPMU_PLUGIN_DIR . '/sky-connect-restore.php';
        if ( file_exists( $target ) ) {
            @unlink( $target );
        }
    }
}