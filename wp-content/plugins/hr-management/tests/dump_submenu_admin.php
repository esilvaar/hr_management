<?php
require_once __DIR__ . '/../../../../wp-load.php';

// Use admin user id 1
$user_id = 1;
wp_set_current_user( $user_id );

// Load admin menu functions if available
if ( ! function_exists('add_menu_page') ) {
    require_once ABSPATH . 'wp-admin/includes/menu.php';
}

// Trigger admin_menu hooks
 do_action('admin_menu');

global $submenu;
if ( empty( $submenu ) ) {
    echo "No submenu built\n";
    exit(0);
}

$found = false;
foreach ( $submenu as $parent => $items ) {
    foreach ( $items as $it ) {
        if ( isset( $it[2] ) && in_array($it[2], array('hr-management-vacaciones','hrm-vacaciones','hrm-vacaciones-formulario'), true) ) {
            echo "Parent: $parent\n";
            echo "Menu label: " . $it[0] . "\n";
            $countBadges = preg_match_all('/<span class="hrm-badge">(.*?)<\/span>/', $it[0], $m);
            echo "Found badges: " . intval($countBadges) . "\n";
            if ($countBadges) {
                echo "Badge value(s): " . implode(', ', $m[1]) . "\n";
            }
            $found = true;
        }
    }
}
if ( ! $found ) {
    echo "hr-management-vacaciones submenu entry not found\n";
}

return 0;
