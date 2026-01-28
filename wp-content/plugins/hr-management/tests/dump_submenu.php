<?php
// Quick test: bootstrap WP, set current user, run admin_menu hooks and dump submenu item for hr-management-vacaciones
require_once __DIR__ . '/../../../../wp-load.php';

// manager email to test
$email = 'comercial@rrhh.anacondaweb.in';
$user = get_user_by( 'email', $email );
if ( ! $user ) {
    echo "No WP user found for $email\n";
    exit(1);
}

wp_set_current_user( $user->ID );

// Ensure roles/capabilities loaded
if ( ! function_exists( 'current_user_can' ) ) {
    echo "current_user_can not available\n";
    exit(1);
}

// Trigger admin_menu hooks
do_action( 'admin_menu' );

global $submenu;
if ( empty( $submenu ) ) {
    echo "No submenu built\n";
    exit(0);
}

$found = false;
foreach ( $submenu as $parent => $items ) {
    foreach ( $items as $it ) {
        if ( isset( $it[2] ) && $it[2] === 'hr-management-vacaciones' ) {
            echo "Menu label: " . $it[0] . "\n";
            // show raw HTML and count occurrences of hrm-badge
            $countBadges = preg_match_all('/<span class="hrm-badge">(.*?)<\/span>/', $it[0], $m);
            echo "Found badges: " . intval($countBadges) . "\n";
            if ($countBadges) {
                echo "Badge value(s): " . implode(', ', $m[1]) . "\n";
            }
            $found = true;
            break 2;
        }
    }
}
if ( ! $found ) {
    echo "hr-management-vacaciones submenu entry not found\n";
}

return 0;
