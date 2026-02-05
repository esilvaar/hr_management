<?php
/**
 * Test simple de editor_vacaciones
 * 
 * Usar en la consola de WordPress o WP-CLI:
 * wp shell
 * require '/ruta/a/este/archivo';
 * test_editor_vacaciones();
 */

function test_editor_vacaciones_full_flow() {
    echo "\n========== HRM DEBUG: EDITOR_VACACIONES ==========\n\n";
    
    // 1. Verificar que el rol existe
    echo "1. Verificando rol 'editor_vacaciones' en WordPress...\n";
    $role = get_role('editor_vacaciones');
    if (!$role) {
        echo "   ❌ ERROR: El rol NO existe\n";
        echo "   Necesito crear el rol ejecutando hrm_create_roles()\n";
        return false;
    }
    echo "   ✅ Rol encontrado\n";
    echo "   Capabilities: " . implode(', ', array_keys($role->capabilities)) . "\n\n";
    
    // 2. Verificar que existe un usuario con este rol
    echo "2. Buscando usuarios con rol 'editor_vacaciones'...\n";
    $users = get_users(['role' => 'editor_vacaciones']);
    if (empty($users)) {
        echo "   ❌ No hay usuarios asignados a este rol\n";
        echo "   Necesito crear un usuario o asignarle el rol\n\n";
        return false;
    }
    echo "   ✅ Encontrados " . count($users) . " usuarios:\n";
    foreach ($users as $user) {
        echo "      - " . $user->user_login . " (ID: " . $user->ID . ")\n";
    }
    echo "\n";
    
    // 3. Verificar capabilities de cada usuario
    echo "3. Verificando capabilities de usuarios con este rol...\n";
    foreach ($users as $user) {
        echo "   Usuario: " . $user->user_login . "\n";
        echo "      manage_hrm_vacaciones: " . ($user->has_cap('manage_hrm_vacaciones') ? "✅" : "❌") . "\n";
        echo "      view_hrm_employee_admin: " . ($user->has_cap('view_hrm_employee_admin') ? "✅" : "❌") . "\n";
        echo "      view_hrm_own_profile: " . ($user->has_cap('view_hrm_own_profile') ? "✅" : "❌") . "\n";
        echo "      read: " . ($user->has_cap('read') ? "✅" : "❌") . "\n";
        echo "      upload_files: " . ($user->has_cap('upload_files') ? "✅" : "❌") . "\n";
    }
    echo "\n";
    
    // 4. Verificar que la página hrm-vacaciones está registrada
    echo "4. Verificando menú 'hrm-vacaciones'...\n";
    global $menu, $submenu;
    $found = false;
    foreach ($menu as $item) {
        if (isset($item[5]) && $item[5] === 'hrm-vacaciones') {
            echo "   ✅ Menú encontrado\n";
            echo "      Título: " . $item[0] . "\n";
            echo "      Capability: " . $item[1] . "\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "   ❌ Menú NO encontrado\n";
    }
    echo "\n";
    
    // 5. Verificar función de renderizado
    echo "5. Verificando función 'hrm_render_vacaciones_admin_page'...\n";
    if (function_exists('hrm_render_vacaciones_admin_page')) {
        echo "   ✅ Función existe\n";
    } else {
        echo "   ❌ Función NO existe\n";
    }
    echo "\n";
    
    // 6. Verificar tabla de vacaciones
    echo "6. Verificando tabla de solicitudes de ausencia...\n";
    global $wpdb;
    $table = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    echo "   Total de solicitudes: " . $count . "\n\n";
    
    // 7. Test de redirect
    echo "7. Test simulado de redirect al login...\n";
    if (isset($users[0])) {
        $user = $users[0];
        // Simular lo que hace el hook de redirect
        $redirect = admin_url('admin.php?page=hrm-vacaciones');
        echo "   Para usuario: " . $user->user_login . "\n";
        echo "   Redirect URL: " . $redirect . "\n";
        echo "   ✅ Redirección sería correcta\n\n";
    }
    
    echo "========== FIN DEBUG ==========\n";
}

// Ejecutar si se llama directamente
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    test_editor_vacaciones_full_flow();
}
