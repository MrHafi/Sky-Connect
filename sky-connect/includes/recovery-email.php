<?php
/*
 * This file:
 * - Hooks into WordPress's automatic "critical error" email
 * - Adds our one-click emergency restore link to that email
 * - So when the site crashes, the rescue link arrives in the admin's inbox
 * - This is the only channel that works when the dashboard is down
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------ recovery email class ---------*/
class Sky_Connect_Recovery_Email {

    /* ------------------------------ hook into the fatal-error email ---------*/
    public function init() {
        // WordPress runs this filter right before sending the recovery email
        add_filter( 'recovery_mode_email', array( $this, 'add_restore_link' ), 10, 2 );
    }

    /* ------------------------------ add our restore link to the email ---------*/
    public function add_restore_link( $email, $url ) {

        $key = get_option( 'sky_connect_emergency_key', '' );

        // no key = nothing we can safely offer
        if ( empty( $key ) ) {
            return $email;
        }

        /* ------------------------------ build our emergency restore URL ---------*/
$restore_url = home_url( '/?sky_restore=' . $key );
        /* ------------------------------ append a clear section to the email body ---------*/
        // $email['message'] is the plain-text body WordPress already wrote.
        // we add our own block underneath it.
        $extra  = "\n\n";
        $extra .= "----------------------------------------\n";
        $extra .= "SKY CONNECT — EMERGENCY RESTORE\n";
        $extra .= "----------------------------------------\n";
        $extra .= "If a recent file edit broke your site, open this link to\n";
        $extra .= "restore the newest backup. Add the broken file at the end,\n";
        $extra .= "for example:  &file=my-plugin/broken-file.php\n\n";
        $extra .= "Restore page: " . $restore_url . "\n";
        $extra .= "(Opening it with no file shows a list of available backups.)\n";

        $email['message'] .= $extra;

        return $email;
    }
}