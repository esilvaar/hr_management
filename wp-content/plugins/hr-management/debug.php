<?php
/**
 * ARCHIVO DE DEBUG - Temporal
 * Accede a: /wp-admin/admin.php?page=hrm-debug-vacaciones-check
 */

// Agregar esta función al hr-managment.php
function hrm_debug_vacaciones_check() {
    echo '<div class="wrap">';
    echo '<h1>🔍 Debug de Vacaciones - Editor</h1>';
    
    $user = wp_get_current_user();
    echo '<h2>👤 Usuario Actual</h2>';
    echo '<pre>';
    echo 'ID: ' . $user->ID . "\n";
    echo 'Email: ' . $user->user_email . "\n";
    echo 'Roles: ' . implode(', ', $user->roles) . "\n";
    echo '</pre>';
    
    echo '<h2>🔐 Capacidades</h2>';
    echo '<pre>';
    echo 'manage_options: ' . (current_user_can('manage_options') ? 'YES ✅' : 'NO ❌') . "\n";
    echo 'manage_hrm_vacaciones: ' . (current_user_can('manage_hrm_vacaciones') ? 'YES ✅' : 'NO ❌') . "\n";
    echo 'edit_hrm_employees: ' . (current_user_can('edit_hrm_employees') ? 'YES ✅' : 'NO ❌') . "\n";
    echo 'view_hrm_own_profile: ' . (current_user_can('view_hrm_own_profile') ? 'YES ✅' : 'NO ❌') . "\n";
    echo '</pre>';
    
    echo '<h2>📋 Solicitudes de Vacaciones</h2>';
    if (function_exists('hrm_get_all_vacaciones')) {
        $solicitudes = hrm_get_all_vacaciones();
        echo '<pre>';
        echo 'Total solicitudes: ' . count($solicitudes) . "\n";
        if (!empty($solicitudes)) {
            echo "Primeras 3:\n";
            foreach (array_slice($solicitudes, 0, 3) as $s) {
                echo json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        echo '</pre>';
    } else {
        echo '<p style="color: red;">❌ Función hrm_get_all_vacaciones NO existe</p>';
    }
    
    echo '<h2>🎯 URLs Importantes</h2>';
    echo '<ul>';
    echo '<li><a href="' . esc_url(admin_url('admin.php?page=hrm-vacaciones')) . '">Vacaciones Admin Page</a></li>';
    echo '<li><a href="' . esc_url(admin_url('admin.php?page=hrm-mi-perfil-info')) . '">Mi Perfil Info</a></li>';
    echo '<li><a href="' . esc_url(admin_url('admin.php?page=hrm-mi-perfil-vacaciones')) . '">Mis Vacaciones</a></li>';
    echo '</ul>';
    
    echo '<h2>🔍 Sidebar que se carga</h2>';
    if (current_user_can('manage_options')) {
        echo '<p>✅ Cargando: <strong>sidebar-admin.php</strong></p>';
    } elseif (current_user_can('edit_hrm_employees')) {
        echo '<p>✅ Cargando: <strong>sidebar-supervisor.php</strong></p>';
    } elseif (current_user_can('manage_hrm_vacaciones')) {
        echo '<p>✅ Cargando: <strong>sidebar-editor-vacaciones.php</strong></p>';
    } elseif (current_user_can('view_hrm_own_profile') || is_user_logged_in()) {
        echo '<p>✅ Cargando: <strong>sidebar-empleado.php</strong></p>';
    } else {
        echo '<p>❌ No se carga sidebar - Sin permisos</p>';
    }
    
    echo '<h2>📁 Archivos CSS encolados</h2>';
    global $wp_styles;
    echo '<pre>';
    foreach ($wp_styles->queue as $handle) {
        if (strpos($handle, 'hrm') !== false) {
            echo '✅ ' . $handle . "\n";
        }
    }
    echo '</pre>';
    
    echo '</div>';
}

// Registrar página de debug
add_action('admin_menu', function() {
    add_submenu_page(
        'hrm-empleados',
        '[DEBUG] Vacaciones Check',
        '[DEBUG] Vacaciones Check',
        'manage_options',
        'hrm-debug-vacaciones-check',
        'hrm_debug_vacaciones_check'
    );
}, 999);
