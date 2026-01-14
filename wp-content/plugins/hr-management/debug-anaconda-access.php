<?php
/**
 * Script de Debug: Verificar acceso de administrador_anaconda
 * Verificar por qué administrador_anaconda no puede acceder a documentos
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Hook para ejecutar en admin_init
add_action( 'admin_init', function() {
    // Solo ejecutar si está en la página de documentos
    if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'hrm-mis-documentos' ) === 0 ) {
        $user = wp_get_current_user();
        
        error_log( '=== DEBUG ANACONDA ACCESS ===' );
        error_log( 'Usuario ID: ' . $user->ID );
        error_log( 'Usuario: ' . $user->user_login );
        error_log( 'Roles: ' . implode( ', ', $user->roles ) );
        error_log( '--- CAPACIDADES ---' );
        error_log( 'manage_options: ' . ( $user->has_cap( 'manage_options' ) ? 'SI' : 'NO' ) );
        error_log( 'view_hrm_admin_views: ' . ( $user->has_cap( 'view_hrm_admin_views' ) ? 'SI' : 'NO' ) );
        error_log( 'view_hrm_employee_admin: ' . ( $user->has_cap( 'view_hrm_employee_admin' ) ? 'SI' : 'NO' ) );
        error_log( 'read: ' . ( $user->has_cap( 'read' ) ? 'SI' : 'NO' ) );
        error_log( 'view_hrm_own_profile: ' . ( $user->has_cap( 'view_hrm_own_profile' ) ? 'SI' : 'NO' ) );
        error_log( '--- PÁGINA ---' );
        error_log( 'Page requested: ' . sanitize_text_field( $_GET['page'] ) );
        error_log( '=== FIN DEBUG ===' );
    }
});

// Hook para verificar en admin_menu
add_action( 'admin_menu', function() {
    global $menu;
    $user = wp_get_current_user();
    
    if ( in_array( 'administrador_anaconda', (array) $user->roles ) ) {
        error_log( '--- ADMIN MENU HOOK: administrador_anaconda detectado ---' );
        error_log( 'Menú global existe: ' . ( isset( $menu ) ? 'SI' : 'NO' ) );
        
        if ( isset( $menu ) ) {
            foreach ( $menu as $index => $menu_item ) {
                if ( is_array( $menu_item ) && isset( $menu_item[2] ) ) {
                    if ( strpos( $menu_item[2], 'hrm' ) !== false ) {
                        error_log( "Menú {$index}: " . $menu_item[0] . ' (' . $menu_item[2] . ')' );
                    }
                }
            }
        }
    }
}, 999 );
