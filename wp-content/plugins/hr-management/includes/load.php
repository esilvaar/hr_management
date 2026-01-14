<?php
/**
 * HR Management Plugin - Carga Modular
 * 
 * Archivo centralizado para cargar todos los módulos del plugin.
 * Facilita el mantenimiento y control de dependencias.
 * 
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Módulos a cargar en orden de dependencias
 */
$modules = array(
    // FIX CRÍTICO: Cargar primero para AJAX de supervisores
    'ajax-supervisor-fix.php',
    
    // Capa de Base de Datos (sin dependencias)
    'db/class-hrm-db-table.php',
    'db/class-hrm-db-empleados.php',
    'db/class-hrm-db-documentos.php',
    'db/class-hrm-db-solicitudes-ausencia.php',
    'db/class-hrm-db-historial-ausencias.php',
    'db/class-hrm-db-tipo-ausencia.php',
    'db/class-hrm-db-vacaciones-restantes.php',
    'db/hrm-db-helper.php',
    
    // Funciones Globales y Helpers (utilizadas por otros módulos)
    'helpers.php',
    'functions.php',
    'roles-capabilities.php',
    'roles.php',
    'hooks.php',
    'shortcodes.php',
    'ajax.php',
    
    // Módulos Funcionales (dependen de funciones globales)
    'employees.php',
    'vacaciones.php',
    'cron-helper.php',
);

/**
 * Cargar cada módulo
 */
foreach ( $modules as $module ) {
    $file_path = HRM_PLUGIN_DIR . 'includes/' . $module;
    
    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    } else {
        // Registro de módulos faltantes en debug mode
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( function_exists( 'hrm_debug_log' ) ) {
                hrm_debug_log( 'Módulo faltante: ' . $module );
            } else {
                error_log( 'HRM: Módulo faltante: ' . $module );
            }
        }
    }
}

// FIX TEMPORAL: Forzar carga de vacaciones.php en ADMIN
// TODO: Evaluar si es realmente necesario en próxima versión
add_action( 'admin_init', function () {
    if ( function_exists( 'hrm_debug_log' ) ) {
        hrm_debug_log( 'vacaciones.php force-loaded on admin_init' );
    }
});
