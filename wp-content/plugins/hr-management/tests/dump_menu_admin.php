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

global $menu;
if ( empty( $menu ) ) {
    echo "No menu built\n";
    exit(0);
}

$found = false;
foreach ( $menu as $m ) {
    if ( isset( $m[2] ) && $m[2] === 'hrm-empleados' ) {
        echo "Main menu label: " . $m[0] . "\n";
        $countDots = preg_match_all('/<span class="hrm-badge-dot">(.*?)<\/span>/', $m[0], $d);
        $countBadges = preg_match_all('/<span class="hrm-badge">(.*?)<\/span>/', $m[0], $b);
        echo "Found dots: " . intval($countDots) . "\n";
        echo "Found numeric badges: " . intval($countBadges) . "\n";
        if ($countDots) echo "Dot content: " . implode(', ', $d[1]) . "\n";
        if ($countBadges) echo "Badge content: " . implode(', ', $b[1]) . "\n";
        $found = true;
    }
}
if ( ! $found ) {
    echo "hrm-empleados main menu entry not found\n";
}

return 0;
