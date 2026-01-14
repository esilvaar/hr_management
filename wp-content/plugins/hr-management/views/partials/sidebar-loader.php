<?php
/**
 * Sidebar Loader - Carga la sidebar correcta según el rol del usuario
 * 
 * Roles soportados:
 * - administrator (manage_options)
 * - administrador_anaconda (manage_options)
 * - supervisor (edit_hrm_employees)
 * - editor_vacaciones (manage_hrm_vacaciones)
 * - empleado (view_hrm_own_profile)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Determinar qué sidebar cargar según las capabilities del usuario
if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_hrm_employees' ) ) {
    // Administrador o Administrador Anaconda - Acceso completo
    require_once __DIR__ . '/sidebar-admin.php';
    
} elseif ( current_user_can( 'edit_hrm_employees' ) ) {
    // Supervisor - Gestión de empleados sin crear nuevos
    require_once __DIR__ . '/sidebar-supervisor.php';
    
} elseif ( current_user_can( 'manage_hrm_vacaciones' ) ) {
    // Editor de Vacaciones - Solo gestión de vacaciones
    require_once __DIR__ . '/sidebar-editor-vacaciones.php';
    
} elseif ( current_user_can( 'view_hrm_own_profile' ) || is_user_logged_in() ) {
    // Empleado - Solo su perfil y convivencia
    require_once __DIR__ . '/sidebar-empleado.php';
    
} else {
    // Fallback: Sin permisos
    echo '<aside class="hrm-sidebar d-flex flex-column">';
    echo '<div class="hrm-sidebar-header p-3">';
    echo '<p class="text-muted">Sin permisos de acceso</p>';
    echo '</div>';
    echo '</aside>';
}
