<?php
/*
 * This file:
 * - Holds the multi-file atomic write tool
 * - Writes several files as ONE unit: all succeed, or all roll back
 * - Prevents half-applied changes (e.g. 2 of 3 files edited, 1 reverted)
 * - Flow: validate all → back up all → write all → ONE health check → keep or roll back all
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ multi-file write class ---------*/
class Sky_Connect_Multi_File {

    /* ------------------------------ write MANY files atomically (all-or-nothing) ---------*/
    public static function write_multiple_files( $files ) {

        require_once SKY_CONNECT_DIR . 'includes/backup.php';
        require_once SKY_CONNECT_DIR . 'includes/syntax.php';
        require_once SKY_CONNECT_DIR . 'includes/health.php';
        require_once SKY_CONNECT_DIR . 'includes/logger.php';

        // must be a non-empty list
        if ( empty( $files ) || ! is_array( $files ) ) {
            return array( 'error' => 'No files provided' );
        }

        $prepared = array(); // validated {safe, path, content, backup}

        /* ============================== PHASE 1: validate EVERYTHING first ============================== */
        // we touch NO live file until every single one passes these checks.
        foreach ( $files as $item ) {

            $path    = isset( $item['path'] ) ? $item['path'] : '';
            $content = isset( $item['content'] ) ? $item['content'] : '';

            // jail check
            $safe = Sky_Connect_Jail::safe_path( $path );
            if ( $safe === false || ! is_file( $safe ) ) {
                Sky_Connect_Logger::add( 'multi-file-write', $path, 'blocked', 'File not found or outside jail' );
                return array( 'error' => 'Aborted — file not found or not allowed: ' . $path );
            }

            // self-protection — never edit Sky Connect itself
            if ( ! Sky_Connect_Jail::is_writable_path( $safe ) ) {
                Sky_Connect_Logger::add( 'multi-file-write', $path, 'blocked', 'Tried to edit Sky Connect itself' );
                return array( 'error' => 'Aborted — cannot edit the Sky Connect plugin itself: ' . $path );
            }

            // empty check
            if ( trim( $content ) === '' ) {
                return array( 'error' => 'Aborted — empty content would wipe: ' . $path );
            }

            // size check
            if ( strlen( $content ) > Sky_Connect_Tools::MAX_FILE_SIZE ) {
                return array( 'error' => 'Aborted — content too large (over 2MB): ' . $path );
            }

            // syntax check — BEFORE writing anything
            $syntax = Sky_Connect_Syntax::check( $content, $safe );
            if ( $syntax !== true ) {
                Sky_Connect_Logger::add( 'multi-file-write', $path, 'blocked', 'Syntax error: ' . $syntax );
                return array( 'error' => 'Aborted — syntax error in ' . $path, 'detail' => $syntax );
            }

            $prepared[] = array(
                'safe'    => $safe,
                'path'    => $path,
                'content' => $content,
            );
        }

        /* ============================== PHASE 2: back up ALL files ============================== */
        foreach ( $prepared as $i => $p ) {
            $backup = Sky_Connect_Backup::create( $p['safe'], $p['path'] );
            if ( $backup === false ) {
                // a backup failed before we wrote anything — safe to abort, nothing changed
                return array( 'error' => 'Aborted — could not back up ' . $p['path'] . '. Nothing was changed.' );
            }
            $prepared[ $i ]['backup'] = $backup;
        }

        /* ============================== PHASE 3: write ALL files ============================== */
        foreach ( $prepared as $p ) {
            file_put_contents( $p['safe'], $p['content'] );

            // make PHP forget the old compiled copy so the health check sees new code
            if ( function_exists( 'opcache_invalidate' ) ) {
                @opcache_invalidate( $p['safe'], true );
            }
            clearstatcache( true, $p['safe'] );
        }

        /* ============================== PHASE 4: ONE health check for the whole batch ============================== */
        $health = Sky_Connect_Health::check();

        if ( $health !== true ) {

            /* ------------------------------ ROLL BACK EVERY FILE ---------*/
            $all_restored = true;
            foreach ( $prepared as $p ) {
                $ok = Sky_Connect_Backup::restore( $p['safe'], $p['path'] );
                if ( $ok === false ) {
                    $ok = Sky_Connect_Backup::restore( $p['safe'], $p['path'] ); // one retry
                }
                if ( $ok === false ) {
                    $all_restored = false;
                }
            }

            if ( ! $all_restored ) {
                Sky_Connect_Logger::add( 'multi-file-write', '(batch)', 'restore_failed', 'CRITICAL: batch broke site AND a rollback failed. ' . $health );
                return array( 'error' => 'URGENT — batch broke the site AND a rollback failed. Manual fix needed.', 'detail' => $health );
            }

            Sky_Connect_Logger::add( 'multi-file-write', '(batch)', 'rolled_back', 'All ' . count( $prepared ) . ' files rolled back. ' . $health );
            return array( 'error' => 'Batch broke the site — all files rolled back together.', 'detail' => $health );
        }

        /* ============================== PHASE 5: all good — keep everything ============================== */
        $names = array();
        foreach ( $prepared as $p ) {
            $names[] = $p['path'];
        }
        Sky_Connect_Logger::add( 'multi-file-write', '(batch)', 'success', 'Saved ' . count( $prepared ) . ' files: ' . implode( ', ', $names ) );

        return array(
            'success'     => true,
            'files_saved' => $names,
            'count'       => count( $prepared ),
        );
    }
}