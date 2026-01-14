<?php
/*
Plugin Name: Roles RRHH
Description: Gestión de roles y usuarios RRHH
Version: 1.1
Author: Marcelo Soto
*/

defined('ABSPATH') or exit;

function rrhh_create_roles() {

    // EMPLEADO
    add_role('empleado', 'Empleado', [
        'read' => true,
        'ver_hr_management' => true, // 👈 PERMISO
    ]);

    // EDITOR DE VACACIONES
    add_role('editor_vacaciones', 'Editor de Vacaciones', [
        'read' => true,
        'ver_hr_management' => true, // 👈 PERMISO
    ]);

    // SUPERVISOR
    add_role('supervisor', 'Supervisor', [
        'read' => true,
        'ver_hr_management' => true, // 👈 PERMISO
    ]);
}
register_activation_hook(__FILE__, 'rrhh_create_roles');

/**
 * Menú RRHH
 */
function rrhh_admin_menu() {
    add_menu_page(
        'RRHH',
        'RRHH',
        'create_users',
        'rrhh-panel',
        'rrhh_add_user_page',
        'dashicons-groups',
        30
    );

    add_submenu_page(
        'rrhh-panel',
        'Añadir Usuario',
        'Añadir Usuario',
        'create_users',
        'rrhh-add-user',
        'rrhh_add_user_page'
    );
}
add_action('admin_menu', 'rrhh_admin_menu');

/**
 * Cargar vista
 */
function rrhh_add_user_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/add-user.php';
}

// Exponer vista RRHH (usuarios / roles)
add_action('hrm_render_usuarios_rrhh', function () {

    if (!current_user_can('create_users')) {
        wp_die('No tienes permisos.');
    }

    require_once plugin_dir_path(__FILE__) . 'admin/add-user.php';
});

function hrm_add_capabilities() {

    $roles = ['empleado'];

    foreach ($roles as $role_slug) {
        $role = get_role($role_slug);
        if ($role) {
            $role->add_cap('ver_hr_management');
        }
    }
}
register_activation_hook(__FILE__, 'hrm_add_capabilities');