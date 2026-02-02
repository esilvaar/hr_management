<?php
/**
 * mu-plugin: HRM AJAX shutdown logger
 * Runs early on every request (mu-plugins load before normal plugins) and registers
 * a shutdown handler that logs fatal errors for AJAX requests, helping to capture
 * errors that happen before normal plugin code runs.
 */
if ( ! defined( 'ABSPATH' ) ) {
    // Not running within WordPress bootstrap; still allow simple execution
    return;
}

// Register shutdown handler to capture fatal errors (always active, but log only for admin-ajax or hrm_ actions)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( "HRM MU-PLUGIN INIT - REQUEST_URI=" . ( $_SERVER['REQUEST_URI'] ?? '(none)' ) . ' ACTION=' . ( isset($_REQUEST['action']) ? $_REQUEST['action'] : '(none)' ) );
}

register_shutdown_function( function() {
    $err = error_get_last();
    // Decide whether to log based on whether this looks like an admin AJAX call or HRM action
    $is_ajax_uri = isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' );
    $is_hrm_action = isset( $_REQUEST['action'] ) && strpos( $_REQUEST['action'], 'hrm_' ) === 0;
    if ( ( $is_ajax_uri || $is_hrm_action ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        if ( $err && in_array( $err['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
            error_log( "HRM MU-PLUGIN AJAX SHUTDOWN ERROR: " . print_r( $err, true ) );
            error_log( 'HRM MU-PLUGIN AJAX SHUTDOWN - ACTION SNIPPET: ' . print_r( array_intersect_key( $_REQUEST, array( 'action' => 1, 'email' => 1, 'email_b64' => 1, 'nonce' => 1 ) ), true ) );
            error_log( 'HRM MU-PLUGIN AJAX SHUTDOWN - SERVER SNIPPET: ' . print_r( array_intersect_key( $_SERVER, array( 'REQUEST_METHOD' => 1, 'REQUEST_URI' => 1, 'REMOTE_ADDR' => 1, 'HTTP_USER_AGENT' => 1 ) ), true ) );
        }
    }
} );
