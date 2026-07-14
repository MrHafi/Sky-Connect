<?php
/*
 * This file:
 * - Takes a file path Claude sends
 * - Turns it into a real full path
 * - Checks it stays inside the plugins folder
 * - Blocks ../ tricks and symlinks
 * - Returns the safe path, or false if outside
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ safety jail class ---------*/
class Sky_Connect_Jail {

    /* ------------------------------ check a path is inside the plugins folder ---------*/
    public static function safe_path( $requested_path ) {

        /* ------------------------------ the only folder allowed (Full path of plugin folder) ---------*/
        $base = realpath( SKY_CONNECT_JAIL ); ///www/plugins/....

        /* ------------------------------ build the full path Claude is asking for ---------*/
        $full = realpath( $base . '/' . ltrim( $requested_path, '/' ) );// 

        // False if file not exists
        if ( $full === false ) {
            return false;
        }

        /* ------------------------------ must start inside the base folder ---------*/
        // add a trailing slash to both sides so "plugins" can't match "plugins-backup".
        // without this, a folder whose name just STARTS with the real one would pass.
        $base_with_slash = rtrim( $base, '/' ) . '/';
        $full_with_slash = $full . '/';

        if ( strpos( $full_with_slash, $base_with_slash ) !== 0 ) {
            return false; // outside the jail — block
        }

        /* ------------------------------ safe — return the real path ---------*/
        return $full;
    }




    /* ------------------------------ block CLAUDE WRITES to our own plugin SKY CONNECT ---------*/
    public static function is_writable_path( $full_path ) {

        // the folder this plugin lives in
        $own_folder = realpath( SKY_CONNECT_DIR );

        // add a trailing slash to both sides so "sky-connect" can't match
        // "sky-connect-pro". without it, any folder starting with our name
        // would be wrongly blocked from editing.
        $own_with_slash  = rtrim( $own_folder, '/' ) . '/';
        $full_with_slash = $full_path . '/';

        // if the file sits inside our own plugin — refuse to write
        // (Claude could break its own connection and then be unable to fix it)
        if ( strpos( $full_with_slash, $own_with_slash ) === 0 ) {
            return false;
        }

        return true;
    }
}