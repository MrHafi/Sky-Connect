<?php
/*
 * This file:
 * - Saves a copy of a file BEFORE it gets edited
 * - Stores backups in wp-content/sky-connect-backups/
 * - Each backup has a timestamp, so we keep a history
 * - Can restore a file from its newest backup
 * - Auto-deletes backups older than 30 days
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ backup class ---------*/
class Sky_Connect_Backup {

    /* ------------------------------ where all backups live ---------*/
    private static function backup_dir() {

        $dir = WP_CONTENT_DIR . '/sky-connect-backups';

       // make the folder the first time it is needed
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        /* ------------------------------ block web access to backups ---------*/
        // an empty index.php stops folder listing; the .htaccess denies direct
        // downloads on Apache/LiteSpeed. together they keep old source code private.
        if ( ! file_exists( $dir . '/index.php' ) ) {
            file_put_contents( $dir . '/index.php', "<?php // silence is golden" );
        }
        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            file_put_contents( $dir . '/.htaccess', "Deny from all" );
        }

        return $dir;
    }

    /* ------------------------------ turn a file path into a safe flat name ---------*/
    private static function safe_name( $relative_path ) {

        // slashes are not allowed in file names, so swap them for underscores
        // "woocommerce/cart.php" becomes "woocommerce_cart.php"
        return str_replace( array( '/', '\\' ), '_', ltrim( $relative_path, '/' ) );
    }

    /* ------------------------------ make a backup before editing ---------*/
   /* ------------------------------ make a backup before editing (and verify it) ---------*/
    public static function create( $full_path, $relative_path ) {

        // nothing to back up if the file does not exist yet
        if ( ! is_file( $full_path ) ) {
            return false;
        }

        $dir  = self::backup_dir();
        $name = self::safe_name( $relative_path );
        $stamp = gmdate( 'Y-m-d-H-i-s' );

        $backup_path = $dir . '/' . $name . '.' . $stamp . '.bak';

        // copy the current file into the backup folder
        $done = copy( $full_path, $backup_path );

        if ( ! $done ) {
            return false;
        }

        /* ------------------------------ VERIFY the backup is a real, exact copy ---------*/
        // if the copy is truncated or corrupted, restoring it later would fail or
        // damage the file. we compare both files byte-for-byte before trusting it.
        $original_hash = md5_file( $full_path );
        $backup_hash   = md5_file( $backup_path );

        if ( $original_hash === false || $backup_hash === false || $original_hash !== $backup_hash ) {
            // backup is bad — delete it and report failure so the save is cancelled
            @unlink( $backup_path );
            return false;
        }

        /* ------------------------------ backup is good ---------*/
        return $backup_path;
    }

    /* ------------------------------ put the newest backup back (and verify it) ---------*/
    public static function restore( $full_path, $relative_path ) {

        $dir  = self::backup_dir();
        $name = self::safe_name( $relative_path );

        // find every backup that belongs to this one file
        $matches = glob( $dir . '/' . $name . '.*.bak' );

        if ( empty( $matches ) ) {
            return false;
        }

        // names end with the timestamp, so sorting puts the newest last
        sort( $matches );
        $newest = end( $matches );

        // copy the backup back over the live file
        $done = copy( $newest, $full_path );

        if ( ! $done ) {
            return false;
        }

        /* ------------------------------ VERIFY the restore is an exact copy ---------*/
        // copy() can report success but still leave a truncated file (disk full).
        // compare fingerprints — only trust the restore if they match exactly.
        $backup_hash   = md5_file( $newest );
        $restored_hash = md5_file( $full_path );

        if ( $backup_hash === false || $restored_hash === false || $backup_hash !== $restored_hash ) {
            return false; // restore did not fully succeed
        }

        return true;
    }

    /* ------------------------------ delete backups older than 30 days ---------*/
    public static function clean_old() {

        $dir   = self::backup_dir();
        $files = glob( $dir . '/*.bak' );

        if ( empty( $files ) ) {
            return;
        }

        $cutoff = time() - ( 30 * DAY_IN_SECONDS );

        foreach ( $files as $file ) {

            // filemtime = when the file was last changed
            if ( filemtime( $file ) < $cutoff ) {
                unlink( $file );
            }
        }
    }
}