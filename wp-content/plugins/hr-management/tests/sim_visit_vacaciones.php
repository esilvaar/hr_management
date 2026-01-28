<?php
// Simulate visiting the Vacaciones page to trigger admin_init marking read and then dump menu badges
// Call with: php sim_visit_vacaciones.php

require_once __DIR__ . '/../../../../wp-load.php';

// set current user to manager
$user = get_user_by('email', 'comercial@rrhh.anacondaweb.in');
if (!$user) { echo "No user\n"; exit(1); }

// Simulate GET param page=hrm-vacaciones
$_GET['page'] = 'hrm-vacaciones';

wp_set_current_user( $user->ID );

// Directly call the marking function to avoid triggering other plugins on admin_init
if ( function_exists( 'hrm_mark_notifications_read_on_vacaciones' ) ) {
    hrm_mark_notifications_read_on_vacaciones();
} else {
    // Fallback: try to run admin_init but ignore fatal plugin issues
    try {
        do_action('admin_init');
    } catch ( Throwable $e ) {
        // ignore
    }
}

// Now run admin_menu to let badges append
if ( ! function_exists('add_menu_page') ) {
    require_once ABSPATH . 'wp-admin/includes/menu.php';
}

do_action('admin_menu');

// Dump user meta read and submenu/menu badge state
$read = get_user_meta($user->ID, 'hrm_notifications_read', true);
if (!is_array($read)) $read = array();

echo "User read notifications count: " . count($read) . "\n";

// Dump main menu and submenu labels
global $menu, $submenu;

foreach ( $menu as $m ) {
    if ( isset($m[2]) && $m[2] === 'hrm-empleados' ) {
        echo "Main menu label: " . $m[0] . "\n";
        break;
    }
}

if ( ! empty( $submenu ) && is_array( $submenu ) ) {
    foreach ( $submenu as $parent => $items ) {
        foreach ( $items as $it ) {
            if ( isset( $it[2] ) && in_array( $it[2], array('hr-management-vacaciones','hrm-vacaciones'), true ) ) {
                echo "Submenu label: " . $it[0] . "\n";
            }
        }
    }
}

return 0;
