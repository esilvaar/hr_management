<?php
/**
 * HRM Document Access Fix - MU Plugin
 * 
 * Este archivo se carga ANTES que los plugins normales, permitiendo interceptar
 * la validación de capacidades de WordPress a tiempo para las páginas de documentos.
 * 
 * Crítico para permitir acceso a usuarios con rol 'empleado', 'supervisor', etc.
 * a las páginas de documentos registradas con capability 'read'.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hook CRÍTICO: user_has_cap se dispara ANTES de que WordPress bloquee el acceso
 * Este es el momento perfecto para interceptar y permitir acceso.
 */
add_filter( 'user_has_cap', 'hrm_mu_ensure_document_access', 1, 4 );
function hrm_mu_ensure_document_access( $allcaps, $caps, $args, $user ) {
    // Obtener página solicitada
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    
    // Páginas de documentos que deben ser accesibles
    $document_pages = array(
        'hrm-mi-documentos',
        'hrm-mi-documentos-contratos',
        'hrm-mi-documentos-liquidaciones',
        'hrm-mi-documentos-licencias',
    );
    
    // Si estamos intentando acceder a una página de documentos
    if ( in_array( $current_page, $document_pages, true ) && ! empty( $user->roles ) ) {
        // Roles que deben tener acceso a documentos
        $allowed_roles = array( 'empleado', 'supervisor', 'editor_vacaciones', 'administrador_anaconda' );
        
        // Verificar si el usuario tiene uno de estos roles
        foreach ( $user->roles as $role ) {
            if ( in_array( $role, $allowed_roles, true ) ) {
                // ¡CRÍTICO! Asegurar que 'read' capability sea true
                $allcaps['read'] = true;
                
                // También agregar otras capabilities relevantes
                if ( $role === 'supervisor' || $role === 'editor_vacaciones' ) {
                    $allcaps['edit_hrm_employees'] = true;
                }
                
                error_log( '[HRM-MU-PLUGIN] Granted document access to user ID ' . $user->ID . ' with role ' . $role );
                break;
            }
        }
    }
    
    return $allcaps;
}

/**
 * Hook map_meta_cap: Interceptar validaciones de capability mapping
 */
add_filter( 'map_meta_cap', 'hrm_mu_map_read_capability', 1, 4 );
function hrm_mu_map_read_capability( $caps, $cap, $user_id, $args ) {
    // Obtener página solicitada
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    
    // Páginas de documentos
    $document_pages = array(
        'hrm-mi-documentos',
        'hrm-mi-documentos-contratos',
        'hrm-mi-documentos-liquidaciones',
        'hrm-mi-documentos-licencias',
    );
    
    // Si estamos en una página de documentos y se pide validar 'read'
    if ( in_array( $current_page, $document_pages, true ) && $cap === 'read' ) {
        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            $allowed_roles = array( 'empleado', 'supervisor', 'editor_vacaciones', 'administrador_anaconda' );
            
            foreach ( $user->roles as $role ) {
                if ( in_array( $role, $allowed_roles, true ) ) {
                    // Para usuarios con estos roles, 'read' NO requiere capabilities adicionales
                    return array(); // Array vacío = no requiere nada más
                }
            }
        }
    }
    
    return $caps;
}
