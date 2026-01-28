<?php
require_once __DIR__ . '/../../../../wp-load.php';

$user = get_user_by('email','comercial@rrhh.anacondaweb.in');
if (!$user) { echo "No user\n"; exit(1); }

$uids = array(
    'hrm_notif_216_RECHAZADA_1769620519',
    'hrm_notif_215_APROBADA_1769606773',
    'hrm_notif_214_RECHAZADA_1769606566',
    'hrm_notif_213_RECHAZADA_1769605973',
    'hrm_notif_212_RECHAZADA_1769538723',
    'hrm_notif_210_APROBADA_1769523061',
    'hrm_notif_209_RECHAZADA_1769522944'
);

update_user_meta($user->ID, 'hrm_notifications_read', $uids);

$read = get_user_meta($user->ID, 'hrm_notifications_read', true);
if (!is_array($read)) $read = array();
echo "Wrote read meta count: " . count($read) . "\n";

// Now simulate visiting Vacaciones (call marking function then admin_menu)
wp_set_current_user($user->ID);

if ( function_exists('hrm_mark_notifications_read_on_vacaciones') ) {
    hrm_mark_notifications_read_on_vacaciones();
}

if ( ! function_exists('add_menu_page') ) {
    require_once ABSPATH . 'wp-admin/includes/menu.php';
}

do_action('admin_menu');

global $menu, $submenu;

foreach ( $menu as $m ) {
    if ( isset($m[2]) && $m[2] === 'hrm-empleados' ) {
        echo "Main menu label: " . $m[0] . "\n";
        break;
    }
}

foreach ( $submenu as $items ) {
    foreach ( $items as $it ) {
        if ( isset( $it[2] ) && in_array($it[2], array('hr-management-vacaciones','hrm-vacaciones'), true) ) {
            echo "Submenu label: " . $it[0] . "\n";
        }
    }
}

return 0;
