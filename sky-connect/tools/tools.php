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

        require_once SKY_CONNECT_DIR . 'includes/logger.php';
        Sky_Connect_Logger::add( 'read_file', $path, 'success' );
        return file_get_contents( $safe );
    }

  /* ------------------------------ tool 4: write a file (with full safety net) -------------------*/
    public static function write_file( $path, $content ) {

        // load the safety helpers we need
        require_once SKY_CONNECT_DIR . 'includes/backup.php';
        require_once SKY_CONNECT_DIR . 'includes/syntax.php';
        require_once SKY_CONNECT_DIR . 'includes/health.php';
        require_once SKY_CONNECT_DIR . 'includes/logger.php'; 


        /* ------------------------------ STEP 1: jail check — must be inside plugins folder ---------*/
        $safe = Sky_Connect_Jail::safe_path( $path );

       if ( $safe === false || ! is_file( $safe ) ) {
            require_once SKY_CONNECT_DIR . 'includes/logger.php';
            Sky_Connect_Logger::add( 'read_file', $path, 'blocked', 'File not found or outside jail' );
            return array( 'error' => 'File not found or not allowed' );
        }

       /* ------------------------------ STEP 1.5: refuse to wipe a file to empty ---------*/
        // empty PHP is technically valid and won't crash the homepage, so it would
        // slip through every other check and silently blank a real file. block it.
        if ( trim( $content ) === '' ) {
            return array( 'error' => 'Refused — empty content would wipe the file' );
        }

        /* ------------------------------ STEP 2: never let Claude edit its own plugin ---------*/
        if ( ! Sky_Connect_Jail::is_writable_path( $safe ) ) {
                        Sky_Connect_Logger::add( 'write_file', $path, 'blocked', 'File not found or outside jail' );

            return array( 'error' => 'Cannot edit the Sky Connect plugin itself' );
        }

        /* ------------------------------ STEP 3: syntax check the NEW code before saving ---------*/
        $syntax = Sky_Connect_Syntax::check( $content, $safe ); //jail.php

        // check() returns true if valid, or the error text if broken
        if ( $syntax !== true ) {
                        Sky_Connect_Logger::add( 'write_file', $path, 'blocked', 'Syntax error: ' . $syntax );
            return array(
                'error'  => 'Syntax error — file not saved',
                'detail' => $syntax,
            );
        }

        /* ------------------------------ STEP 4: back up the old file before touching it ---------*/
        $backup = Sky_Connect_Backup::create( $safe, $path ); //backup.php

        if ( $backup === false ) {
            return array( 'error' => 'Could not create backup — save cancelled' );
        }

        /* ------------------------------ STEP 5: save the new content ---------*/
        $written = file_put_contents( $safe, $content );

      /* ------------------------------ make PHP forget the old compiled copy ---------*/
        // WordPress's wp_opcache_invalidate() only exists in wp-admin, not in REST
        // requests — so we call PHP's native function directly, guarded by a check
        // in case the host has OPcache disabled.
        if ( function_exists( 'opcache_invalidate' ) ) {
            @opcache_invalidate( $safe, true );
        }

        // also clear PHP's cached file info (size, modified time)
        clearstatcache( true, $safe );
        if ( $written === false ) {
            return array( 'error' => 'Could not write file' );
        }

        /* ------------------------------ STEP 6: is the site still alive? ---------*/
        $health = Sky_Connect_Health::check();

        // check() returns true if healthy, or the error text if the site broke
     if ( $health !== true ) {

            /* ------------------------------ try to restore, and CHECK it worked ---------*/
            // restore() returns true on success, false if the copy failed
            // (disk full, permissions). we must not tell Claude "safe" unless it is.
            $restored = Sky_Connect_Backup::restore( $safe, $path );

            /* ------------------------------ restore FAILED — this is an emergency ---------*/
            if ( $restored === false ) {

                // try one more time before giving up
                $restored = Sky_Connect_Backup::restore( $safe, $path );
            }

            if ( $restored === false ) {

                // both attempts failed — the broken file is STILL LIVE
                Sky_Connect_Logger::add( 'write_file', $path, 'restore_failed', 'CRITICAL: could not restore backup — site may be broken. ' . $health );

                return array(
                    'error'  => 'URGENT — site broke AND backup restore failed. Manual fix needed.',
                    'detail' => $health,
                );
            }

            /* ------------------------------ restore worked — site is safe again ---------*/
            Sky_Connect_Logger::add( 'write_file', $path, 'rolled_back', $health );

            return array(
                'error'  => 'Site broke after saving — old file restored',
                'detail' => $health,
            );
        }

        /* ------------------------------ all checks passed — the save is final ---------------------*/
       Sky_Connect_Logger::add( 'write_file', $path, 'success', 'Saved. Backup: ' . basename( $backup ) );
        return array(
            'success' => true,
            'bytes'   => $written,
            'backup'  => basename( $backup ),
        );
    }





    /* ------------------------------ tool 5: read the last lines of the error log ---------*/
    public static function read_error_log() {

        // WordPress writes its errors here when WP_DEBUG_LOG is on
        $log_file = WP_CONTENT_DIR . '/debug.log';

        if ( ! is_file( $log_file ) ) {
            return array( 'error' => 'No debug.log found — is WP_DEBUG_LOG turned on?' );
        }

        /* ------------------------------ read the file into lines ---------*/
        $lines = file( $log_file, FILE_IGNORE_NEW_LINES );

        if ( $lines === false ) {
            return array( 'error' => 'Could not read the log file' );
        }

        /* ------------------------------ keep only the last 50 lines ---------*/
        // logs get huge, and only the newest errors matter for fixing a bug
        $last = array_slice( $lines, -50 );

        return implode( "\n", $last );
    }
}