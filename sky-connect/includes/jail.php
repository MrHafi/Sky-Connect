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
        if ( strpos( $full, $base ) !== 0 ) { //if ful path got plugin directory in path?
            return false; // outside the jail — block
        }

        /* ------------------------------ safe — return the real path ---------*/
        return $full;
    }
}