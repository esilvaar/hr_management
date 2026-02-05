<?php
/**
 * Script de debug: Verificar estado del rol editor_vacaciones
 * Acceder a: admin.php?page=hrm-debug-editor-vacaciones
 */

if (!defined('ABSPATH')) exit;

// Solo accesible para admins
if (!current_user_can('manage_options')) {
    wp_die('Solo administradores pueden ver esta página');
}

echo '<div class="wrap">';
echo '<h1>Debug: Editor de Vacaciones</h1>';

// Buscar usuarios con rol editor_vacaciones
global $wpdb;
$users = get_users([
    'role' => 'editor_vacaciones'
]);

echo '<h2>Usuarios con rol editor_vacaciones:</h2>';
if (empty($users)) {
    echo '<p style="color: red;"><strong>NO HAY USUARIOS CON ESTE ROL</strong></p>';
} else {
    foreach ($users as $user) {
        echo '<div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
        echo '<h3>' . esc_html($user->user_login) . ' (ID: ' . $user->ID . ')</h3>';
        echo '<p><strong>Roles:</strong> ' . implode(', ', $user->roles) . '</p>';
        echo '<p><strong>Capabilities directas:</strong></p>';
        echo '<ul>';
        foreach ($user->caps as $cap => $has) {
            if ($has) {
                echo '<li>' . esc_html($cap) . ': ' . ($has ? 'YES' : 'NO') . '</li>';
            }
        }
        echo '</ul>';
        
        // Verificar capabilities clave
        echo '<p><strong>Capabilities verificadas:</strong></p>';
        echo '<ul>';
        echo '<li>manage_hrm_vacaciones: ' . ($user->has_cap('manage_hrm_vacaciones') ? 'YES' : 'NO') . '</li>';
        echo '<li>view_hrm_employee_admin: ' . ($user->has_cap('view_hrm_employee_admin') ? 'YES' : 'NO') . '</li>';
        echo '<li>view_hrm_own_profile: ' . ($user->has_cap('view_hrm_own_profile') ? 'YES' : 'NO') . '</li>';
        echo '<li>edit_hrm_employees: ' . ($user->has_cap('edit_hrm_employees') ? 'YES' : 'NO') . '</li>';
        echo '</ul>';
        
        // Botón para simular login
        $login_url = wp_login_url(admin_url('admin.php?page=hrm-vacaciones'));
        // Usar user_switching si está disponible, sino mostrar instrucciones
        echo '<p><a href="' . esc_url(admin_url('admin.php?action=switch_to_user&user_id=' . $user->ID . '&_wpnonce=' . wp_create_nonce('switch_to_user_' . $user->ID))) . '" class="button button-primary">Simular como este usuario</a> (requiere User Switching plugin)</p>';
        
        echo '</div>';
    }
}

// Verificar rol editor_vacaciones en la base de datos
echo '<h2>Verificar rol en la base de datos:</h2>';
$table = $wpdb->prefix . 'usermeta';
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT user_id, meta_value FROM $table WHERE meta_key = %s AND meta_value LIKE %s",
    $wpdb->prefix . 'capabilities',
    '%editor_vacaciones%'
), ARRAY_A);

echo '<p>Usuarios en usermeta con editor_vacaciones: ' . count($results) . '</p>';
if (!empty($results)) {
    echo '<ul>';
    foreach ($results as $r) {
        $caps = unserialize($r['meta_value']);
        if (is_array($caps) && isset($caps['editor_vacaciones'])) {
            $user = get_user_by('id', $r['user_id']);
            echo '<li>User ID ' . $r['user_id'] . ' (' . $user->user_login . '): editor_vacaciones=' . ($caps['editor_vacaciones'] ? 'YES' : 'NO') . '</li>';
        }
    }
    echo '</ul>';
}

// Verificar definición del rol
echo '<h2>Verificar rol en roles de WordPress:</h2>';
$role = get_role('editor_vacaciones');
if (!$role) {
    echo '<p style="color: red;"><strong>EL ROL NO EXISTE EN WORDPRESS</strong></p>';
} else {
    echo '<p><strong>Rol encontrado</strong></p>';
    echo '<p><strong>Capabilities del rol:</strong></p>';
    echo '<ul>';
    foreach ($role->capabilities as $cap => $has) {
        if ($has) {
            echo '<li>' . esc_html($cap) . '</li>';
        }
    }
    echo '</ul>';
}

// Información de menú
echo '<h2>Menú registrado:</h2>';
global $menu, $submenu;
echo '<p>Menú "Vacaciones" está registrado: ';
$found = false;
foreach ($menu as $item) {
    if (isset($item[5]) && $item[5] === 'hrm-vacaciones') {
        echo 'YES';
        $found = true;
        break;
    }
}
if (!$found) echo 'NO';
echo '</p>';

echo '</div>';
