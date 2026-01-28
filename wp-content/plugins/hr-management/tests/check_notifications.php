<?php
require_once __DIR__ . '/../../../../wp-load.php';

$email = 'comercial@rrhh.anacondaweb.in';
$user = get_user_by('email', $email);
if (!$user) {
    echo "No user for $email\n";
    exit(1);
}
wp_set_current_user($user->ID);

$all = get_option('hrm_notifications', array());
echo "Total hrm_notifications stored: " . count($all) . "\n";

if (function_exists('hrm_get_notifications_for_current_user')) {
    $unread = hrm_get_notifications_for_current_user();
    echo "Unread notifications for user {$user->ID} ({$email}): " . count($unread) . "\n";
    foreach ($unread as $n) {
        echo "- uid={$n['uid']} id_solicitud={$n['id_solicitud']} estado={$n['estado']} departamento={$n['departamento']} created={$n['created']}\n";
    }
} else {
    echo "Function hrm_get_notifications_for_current_user not found\n";
}

// also dump user meta read
$read = get_user_meta($user->ID, 'hrm_notifications_read', true);
if (!is_array($read)) $read = array();
echo "User meta hrm_notifications_read count: " . count($read) . "\n";

return 0;
