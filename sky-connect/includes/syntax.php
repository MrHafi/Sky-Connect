<?php
/*
 * This file:
 * - Checks if PHP code is valid BEFORE we save it
 * - Writes the code to a temp file, runs PHP's syntax checker on it
 * - Deletes the temp file right after
 * - Returns true if the code is fine, or the error message if it is broken
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ syntax check class ---------*/
class Sky_Connect_Syntax {

    /* ------------------------------ check if new php code is valid ---------*/
    public static function check( $code, $file_path ) {

        // only PHP files need a syntax check — css, js, txt etc. pass straight through
        if ( pathinfo( $file_path, PATHINFO_EXTENSION ) !== 'php' ) {
            return true;
        }

        // make a temporary file to test the code in (never touches the real file)
        $temp = wp_tempnam( 'sky-connect-check' );

        if ( ! $temp ) {
            return 'Could not create temp file for syntax check';
        }

        file_put_contents( $temp, $code );

        /* ------------------------------ run PHP's own syntax checker on the temp file ---------*/
        // "php -l" means "lint" — it only checks the code, it never runs it
        $command = 'php -l ' . escapeshellarg( $temp ) . ' 2>&1'; //  safety wrap, stops hackers injecting extra commands here.

        $output = shell_exec( $command );

        
        unlink( $temp ); // clean up the temp file no matter what happened

        /* ------------------------------ shell_exec may be disabled on some hosts ---------*/
        if ( $output === null ) {
            return 'Syntax check unavailable on this server';
        }

        /* ------------------------------ PHP says "No syntax errors detected" when the code is fine ---------*/
        if ( strpos( $output, 'No syntax errors' ) !== false ) {
            return true;
        }

        // otherwise the output IS the error message — send it back so Claude can fix it
        return trim( $output );
    }
}