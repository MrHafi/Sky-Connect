<?php
/*
 * This file:
 * - Holds the 4 tools Claude can run
 * - Every tool runs the jail check first (locked to plugins folder)
 * - list_plugins: show all plugin folders
 * - list_files: show files inside one plugin
 * - read_file: read a file
 * - write_file: save a file (safety checks come in Step 9)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ tools class ---------*/
class Sky_Connect_Tools {

    /* ------------------------------ tool 1: list all plugin folders ---------*/
    public static function list_plugins() {

        $base  = realpath( SKY_CONNECT_JAIL ); // get real path of plugins folder
        $items = scandir( $base ); //everything ionside plugin 

        $plugins = array();

        foreach ( $items as $item ) {

            // skip . and ..
            if ( $item === '.' || $item === '..' ) {
                continue;
            }

            // keep only folders
            if ( is_dir( $base . '/' . $item ) ) {
                $plugins[] = $item;
            }
        }

        return $plugins;
    }

    /* ------------------------------ tool 2: list files inside one plugin ---------*/
    public static function list_files( $plugin ) {

        // jail check — must stay inside plugins folder
        $safe = Sky_Connect_Jail::safe_path( $plugin );

        if ( $safe === false || ! is_dir( $safe ) ) {
            return array( 'error' => 'Invalid plugin folder' );
        }

        $files = array();

        // walk through all files and sub-folders
        $iterator = new RecursiveIteratorIterator( //  // walk into ALL sub-folders too, not just top
            new RecursiveDirectoryIterator( $safe, RecursiveDirectoryIterator::SKIP_DOTS ) // save short path, not full server path
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                // show path relative to the plugins folder
                $files[] = str_replace( realpath( SKY_CONNECT_JAIL ) . '/', '', $file->getPathname() );
            }
        }

        return $files;
    }

    /* ------------------------------ tool 3: read a file ---------*/
    public static function read_file( $path ) {

        // jail check
        $safe = Sky_Connect_Jail::safe_path( $path );

        if ( $safe === false || ! is_file( $safe ) ) {
            return array( 'error' => 'File not found or not allowed' );
        }

        return file_get_contents( $safe );
    }

    /* ------------------------------ tool 4: write a file ---------*/
    public static function write_file( $path, $content ) {

        // jail check
        $safe = Sky_Connect_Jail::safe_path( $path );

        if ( $safe === false || ! is_file( $safe ) ) {
            return array( 'error' => 'File not found or not allowed' );
        }

        // save the new content
        $written = file_put_contents( $safe, $content );

        if ( $written === false ) {
            return array( 'error' => 'Could not write file' );
        }

        return array( 'success' => true, 'bytes' => $written );
    }
}