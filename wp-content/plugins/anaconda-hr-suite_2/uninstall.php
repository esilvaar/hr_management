<?php
/**
 * Archivo de desinstalación del plugin
 * Se ejecuta cuando se elimina el plugin desde WordPress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/**
 * Opción: Limpiar todas las tablas de la BD
 * Cambiar esto a 'true' si quieres eliminar datos al desinstalar
 */
$delete_data = false;

if ( $delete_data ) {
    // Eliminar tablas
    $tables = [
        $wpdb->prefix . 'rrhh_empleados',
        $wpdb->prefix . 'rrhh_solicitudes_ausencia',
        $wpdb->prefix . 'rrhh_documentos',
        $wpdb->prefix . 'rrhh_historial_ausencias',
        $wpdb->prefix . 'rrhh_tipo_ausencia',
    ];

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }

    // Eliminar opciones
    delete_option( 'anaconda_hrsuite_activated' );
    delete_option( 'anaconda_hrsuite_migrated' );
}

// Eliminar roles (mantener datos)
remove_role( 'hr_admin' );
remove_role( 'hr_supervisor' );
remove_role( 'hr_employee' );
