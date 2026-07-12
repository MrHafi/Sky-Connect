<?php
/*
 * This file:
 * - Checks if PHP code is valid BEFORE we save it
 * - Uses PHP's own tokenizer (works even when shell_exec is blocked)
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

        /* ------------------------------ parse the code with PHP's own tokenizer ---------*/
        // token_get_all() reads the code the same way PHP does when it loads a file.
        // With the TOKEN_PARSE flag it throws a ParseError on bad syntax — but it
        // NEVER runs the code. This works on hosts where shell_exec is blocked.
        try {

            token_get_all( $code, TOKEN_PARSE );

        } catch ( ParseError $e ) {

            // the code is broken — send the reason back so Claude can fix it
            return 'Parse error: ' . $e->getMessage() . ' on line ' . $e->getLine();

        } catch ( Error $e ) {

            // any other compile-level problem
            return 'Error: ' . $e->getMessage();
        }

        /* ------------------------------ code parsed cleanly ---------*/
        return true;
    }
}