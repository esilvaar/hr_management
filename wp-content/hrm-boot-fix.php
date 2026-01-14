<?php
/**
 * HRM Boot Fixer - Loads before everything to fix admin access
 * 
 * NOTA: Este archivo se carga ANTES que wp-settings.php, por lo que
 * NO podemos usar funciones de WordPress directamente. En su lugar, 
 * registramos hooks que WordPress ejecutará después de cargar.
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

/**
 * Flag para asegurar que solo registramos una vez
 */
$GLOBALS['hrm_boot_filter_registered'] = false;

/**
 * Setup function que se llamará cuando WordPress esté listo
 */
if ( ! function_exists( 'hrm_boot_fix_setup' ) ) {
    function hrm_boot_fix_setup() {
        // Solo registrar una vez
        if ( ! empty( $GLOBALS['hrm_boot_filter_registered'] ) ) {
            return;
        }
        $GLOBALS['hrm_boot_filter_registered'] = true;
        
        // Registrar los filtros
        add_filter( 'user_has_cap', 'hrm_boot_fix_user_has_cap', 0, 4 );
        add_filter( 'map_meta_cap', 'hrm_boot_fix_map_meta_cap', 0, 4 );
    }
}

/**
 * Filter para user_has_cap
 */
if ( ! function_exists( 'hrm_boot_fix_user_has_cap' ) ) {
    function hrm_boot_fix_user_has_cap( $allcaps, $caps, $args, $user ) {
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
                    
                    error_log( '[HRM-BOOT-FIX] Granted document access to user ID ' . $user->ID . ' with role ' . $role );
                    break;
                }
            }
        }
        
        return $allcaps;
    }
}

/**
 * Filter para map_meta_cap
 */
if ( ! function_exists( 'hrm_boot_fix_map_meta_cap' ) ) {
    function hrm_boot_fix_map_meta_cap( $caps, $cap, $user_id, $args ) {
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
                        return array();
                    }
                }
            }
        }
        
        return $caps;
    }
}

// Registrar setup en hooks de WordPress cuando estén disponibles
// Usamos múltiples hooks para asegurar cobertura
if ( function_exists( 'add_action' ) ) {
    add_action( 'plugins_loaded', 'hrm_boot_fix_setup', -999 );
    add_action( 'wp_loaded', 'hrm_boot_fix_setup', -999 );
}
