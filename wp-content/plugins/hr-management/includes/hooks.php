<?php
/**
 * Hooks y filtros globales del plugin HR Management
 * Manejo de notificaciones, redirecciones, admin bar, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CRÍTICO: Filtro map_meta_cap para permitir acceso a páginas de documentos
 * Este es el filtro que WordPress usa ANTES de bloquear el acceso a admin pages
 * DEBE definirse ANTES de ser registrado en hooks
 */
function hrm_map_document_capabilities( $caps, $cap, $user_id, $args ) {
    // Obtener página solicitada
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

    // Debug: registrar llamada a map_meta_cap para inspección de accesos
    error_log( '[HRM-DEBUG] map_meta_cap check - cap=' . $cap . ' page=' . $current_page . ' user_id=' . intval( $user_id ) . ' args=' . json_encode( $args ) );
    if ( function_exists( 'hrm_local_debug_log' ) ) {
        hrm_local_debug_log( '[HRM-DEBUG] map_meta_cap check - cap=' . $cap . ' page=' . $current_page . ' user_id=' . intval( $user_id ) . ' args=' . json_encode( $args ) );
    }

    // Páginas de documentos que deben ser accesibles con 'read'
    $document_pages = array(
        'hrm-mi-documentos',
        'hrm-mi-documentos-contratos',
        'hrm-mi-documentos-liquidaciones',
        'hrm-mi-documentos-licencias',
        'hrm-convivencia',
        'hrm-anaconda-documents',
    );

    // Permitir explicitamente que la capability 'view_hrm_admin_views' sea satisfecha
    // por usuarios con rol 'administrator' o 'administrador_anaconda' cuando acceden
    // a la página de Documentos empresa (evita el WP die por falta de capability).
    if ( $cap === 'view_hrm_admin_views' && $current_page === 'hrm-anaconda-documents' ) {
        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            $allowed_roles = array( 'administrator', 'administrador_anaconda' );
            foreach ( (array) $user->roles as $role ) {
                if ( in_array( $role, $allowed_roles, true ) ) {
                    // Mapeamos a 'read' para permitir acceso si el usuario está autenticado
                    return array( 'read' );
                }
            }
        }
    }
    
    // Si se está validando 'read' capability en una página de documentos
    if ( in_array( $current_page, $document_pages, true ) && $cap === 'read' ) {
        // Obtener roles del usuario
        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            $allowed_roles = array( 'empleado', 'supervisor', 'editor_vacaciones', 'administrador_anaconda' );
            $user_roles = (array) $user->roles;
            
            // Si el usuario tiene alguno de estos roles, permitir access (capability 'read' es suficiente)
            foreach ( $user_roles as $role ) {
                if ( in_array( $role, $allowed_roles, true ) ) {
                    // No requerir ninguna capability adicional
                    return array( 'read' );
                }
            }
        }
    }
    
    return $caps;
}

/**
 * Filtro user_has_cap como respaldo para asegurar que 'read' siempre sea true
 * DEBE definirse ANTES de ser registrado en hooks
 */
function hrm_ensure_read_capability_for_documents( $allcaps, $caps, $args, $user ) {
    // Obtener página solicitada
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

    // Debug: registrar user roles y caps incoming
    error_log( '[HRM-DEBUG] user_has_cap check - page=' . $current_page . ' user_id=' . intval( $user->ID ) . ' roles=' . json_encode( $user->roles ) . ' incoming_caps=' . json_encode( $caps ) );
    if ( function_exists( 'hrm_local_debug_log' ) ) {
        hrm_local_debug_log( '[HRM-DEBUG] user_has_cap check - page=' . $current_page . ' user_id=' . intval( $user->ID ) . ' roles=' . json_encode( $user->roles ) . ' incoming_caps=' . json_encode( $caps ) );
    }

    // Páginas de documentos que deben ser accesibles con 'read'
    $document_pages = array(
        'hrm-mi-documentos',
        'hrm-mi-documentos-contratos',
        'hrm-mi-documentos-liquidaciones',
        'hrm-mi-documentos-licencias',
        'hrm-convivencia',
        'hrm-anaconda-documents',
    );
    
    // Si estamos en una página de documentos
    if ( in_array( $current_page, $document_pages, true ) ) {
        $allowed_roles = array( 'empleado', 'supervisor', 'editor_vacaciones', 'administrador_anaconda' );
        
        // Si el usuario tiene alguno de estos roles, asegurar que tiene 'read'
        if ( ! empty( $user->roles ) ) {
            foreach ( $user->roles as $role ) {
                if ( in_array( $role, $allowed_roles, true ) ) {
                    // Forzar que 'read' sea true
                    $allcaps['read'] = true;
                    break;
                }
            }
        }
    }
    
    return $allcaps;
}

/**
 * Configurar filtros de capability en plugins_loaded
 * Necesitamos que se ejecute ANTES de que WordPress valide capabilities
 */
function hrm_setup_document_access_filters() {
    // Registrar los filtros de capability tempranamente
    add_filter( 'map_meta_cap', 'hrm_map_document_capabilities', 10, 4 );
    add_filter( 'user_has_cap', 'hrm_ensure_read_capability_for_documents', 10, 4 );
}
add_action( 'plugins_loaded', 'hrm_setup_document_access_filters', 1 );

/**
 * En admin_init TEMPRANO, asegurar que todos los usuarios con roles HRM 
 * tengan las capabilities necesarias. Esto es un workaround para el 403.
 */
function hrm_ensure_capabilities_on_admin_init() {
    // Obtener página solicitada
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    
    // Páginas de documentos que deben ser accesibles con 'read'
    $document_pages = array(
        'hrm-mi-documentos',
        'hrm-mi-documentos-contratos',
        'hrm-mi-documentos-liquidaciones',
        'hrm-mi-documentos-licencias',
        'hrm-convivencia',
        'hrm-anaconda-documents',
    );
    
    // Si estamos intentando acceder a una página de documentos
    if ( in_array( $current_page, $document_pages, true ) ) {
        $current_user = wp_get_current_user();
        $allowed_roles = array( 'empleado', 'supervisor', 'editor_vacaciones', 'administrador_anaconda' );
        
        // Debug logging
        error_log( '[HRM-DEBUG] Intento de acceso a documento: user_id=' . $current_user->ID . ', roles=' . json_encode( $current_user->roles ) . ', page=' . $current_page . ', GET=' . json_encode( $_GET ) );
        if ( function_exists( 'hrm_local_debug_log' ) ) {
            hrm_local_debug_log( '[HRM-DEBUG] Intento de acceso a documento: user_id=' . $current_user->ID . ', roles=' . json_encode( $current_user->roles ) . ', page=' . $current_page . ', GET=' . json_encode( $_GET ) );
        }
        error_log( '[HRM-DEBUG] Has read capability: ' . ( $current_user->has_cap( 'read' ) ? 'YES' : 'NO' ) );
        if ( $current_page === 'hrm-anaconda-documents' && isset( $_GET['fullscreen'] ) ) {
            error_log( '[HRM-DEBUG] Access includes fullscreen param for user_id=' . $current_user->ID . ' fullscreen=' . esc_attr( $_GET['fullscreen'] ) );
            if ( function_exists( 'hrm_local_debug_log' ) ) {
                hrm_local_debug_log( '[HRM-DEBUG] Access includes fullscreen param for user_id=' . $current_user->ID . ' fullscreen=' . esc_attr( $_GET['fullscreen'] ) );
            }
        }
        
        // Si el usuario tiene uno de estos roles, asegurar que tenga 'read'
        if ( ! empty( $current_user->roles ) ) {
            foreach ( $current_user->roles as $role ) {
                if ( in_array( $role, $allowed_roles, true ) ) {
                    // Forzar agregar la capability 'read' si no existe
                    if ( ! $current_user->has_cap( 'read' ) ) {
                        $current_user->add_cap( 'read' );
                        error_log( '[HRM-DEBUG] Added read capability to user' );
                    }

                    // Además forzar view_hrm_admin_views para usuarios con rol administrador_anaconda
                    if ( 'administrador_anaconda' === $role ) {
                        if ( ! $current_user->has_cap( 'view_hrm_admin_views' ) ) {
                            $current_user->add_cap( 'view_hrm_admin_views' );
                            error_log( '[HRM-DEBUG] Added view_hrm_admin_views capability to user with role administrador_anaconda' );
                        }
                    }

                    break;
                }
            }
        }
    }
}
add_action( 'admin_init', 'hrm_ensure_capabilities_on_admin_init', 1 );

/**
 * Evitar envío de email al cambiar contraseña para roles HR.
 */
function hrm_filter_password_change_email( $send, $user, $userdata = null ) {
    if ( ! $user || ! is_object( $user ) ) {
        return $send;
    }

    $blocked_roles = array( 'empleado', 'supervisor', 'editor_vacaciones' );
    foreach ( $blocked_roles as $role ) {
        if ( in_array( $role, (array) $user->roles, true ) ) {
            return false;
        }
    }

    return $send;
}
add_filter( 'send_password_change_email', 'hrm_filter_password_change_email', 10, 3 );

/**
 * Ocultar la admin bar de WordPress para administrador_anaconda
 * Este usuario solo debe ver las páginas del plugin.
 */
function hrm_hide_admin_bar_for_anaconda() {
    $current_user = wp_get_current_user();
    if ( in_array( 'administrador_anaconda', (array) $current_user->roles ) ) {
        show_admin_bar( false );
    }
}
add_action( 'init', 'hrm_hide_admin_bar_for_anaconda' );

/**
 * Añadir enlace en la Admin Bar para acceder al perfil del usuario.
 */
function hrm_add_admin_bar_edit_profile_link( $wp_admin_bar ) {
    if ( ! is_user_logged_in() ) {
        return;
    }

    if ( ! current_user_can( 'view_hrm_employee_admin' ) ) {
        return;
    }

    // Para admins y usuarios con view_hrm_admin_views, enviar al menú de empleados; para otros, a su perfil
    if ( current_user_can( 'manage_options' ) || current_user_can( 'view_hrm_admin_views' ) ) {
        $url = admin_url( 'admin.php?page=hrm-empleados' );
        $title = 'Gestión de Empleados';
    } else {
        $url = admin_url( 'admin.php?page=hrm-mi-perfil-info' );
        $title = 'Mi Perfil';
    }

    $wp_admin_bar->add_node( array(
        'id'    => 'hrm-profile',
        'title' => $title,
        'href'  => $url,
        'meta'  => array( 'class' => 'hrm-profile' ),
    ) );
}
add_action( 'admin_bar_menu', 'hrm_add_admin_bar_edit_profile_link', 80 );

/**
 * Redirigir usuarios no-admin que intenten acceder a WP admin pages (excepto su profile.php).
 * También bloquea a administrador_anaconda del acceso a WP admin excepto páginas del plugin.
 * Permite que accedan a su profile.php para editar avatar/contraseña.
 */
function hrm_redirect_wp_profile_page() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    // Permitir completamente las peticiones AJAX (admin-ajax.php)
    if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'admin-ajax.php' ) ) {
        return;
    }

    $current_user = wp_get_current_user();
    $is_admin_anaconda = in_array( 'administrador_anaconda', (array) $current_user->roles );

    // Permitir acceso completo a admins de WordPress (pero bloquear administrador_anaconda)
    if ( current_user_can( 'manage_options' ) && ! $is_admin_anaconda ) {
        return;
    }

    global $pagenow;
    $current_user_id = get_current_user_id();

    // PERMITIR admin-post.php para procesar formularios
    if ( $pagenow === 'admin-post.php' ) {
        return;
    }

    // No redirigir si ya estamos en una página autorizada del plugin
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

    // Permitir dinámicamente páginas por tipo (ej: hrm-mi-documentos-type-10)
    if ( strpos( $current_page, 'hrm-mi-documentos-type-' ) === 0 ) {
        error_log( '[HRM-DEBUG] hrm_redirect_wp_profile_page - allowing dynamic doc type page: ' . $current_page );
        return;
    }

    $allowed_pages = array(
        'hrm-mi-perfil-info',
        'hrm-mi-perfil',
        'hrm-mi-perfil-vacaciones',
        'hrm-mi-documentos',
        'hrm-mi-documentos-contratos',
        'hrm-mi-documentos-liquidaciones',
        'hrm-mi-documentos-licencias',
        'hrm-vacaciones',
        'hrm-vacaciones-formulario',
        'hrm-convivencia',
        'hrm-anaconda-documents',
        'hrm-empleados'
    );
    if ( in_array( $current_page, $allowed_pages, true ) ) {
        return;
    }

    // Para administrador_anaconda, BLOQUEAR acceso a profile.php y redirigir a página del plugin
    if ( $is_admin_anaconda && $pagenow === 'profile.php' ) {
        wp_safe_redirect( admin_url( 'admin.php?page=hrm-empleados' ) );
        exit;
    }

    // PERMITIR acceso a profile.php para otros usuarios (su propio perfil para editar avatar/contraseña)
    if ( $pagenow === 'profile.php' && ! $is_admin_anaconda ) {
        return;
    }

    // Permitir acceso limitado a user-edit.php solo si es el usuario actual (y no es administrador_anaconda)
    if ( $pagenow === 'user-edit.php' ) {
        $user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
        if ( ! $is_admin_anaconda && ( $user_id === $current_user_id || $user_id === 0 ) ) {
            return;
        }
    }

    // Para usuarios con capability del plugin (incluyendo administrador_anaconda), redirigir si intentan acceder a WP admin
    if ( current_user_can( 'view_hrm_employee_admin' ) ) {
        // Redirigir a la página apropiada según el rol
        if ( $is_admin_anaconda ) {
            wp_safe_redirect( admin_url( 'admin.php?page=hrm-empleados' ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=hrm-mi-perfil-info' ) );
        }
        exit;
    }
}
add_action( 'admin_init', 'hrm_redirect_wp_profile_page' );

/**
 * Handler para crear Documentos Empresa enviado desde el formulario admin (admin-post)
 */
function hrm_handle_anaconda_documents_create() {
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para realizar esta acción.', 'Acceso denegado', array( 'response' => 403 ) );
    }

    // Permisos: admin o administrador_anaconda (view_hrm_admin_views o rol administrador_anaconda)
    $current_user = wp_get_current_user();
    $is_anaconda = in_array( 'administrador_anaconda', (array) $current_user->roles, true );
    if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'view_hrm_admin_views' ) || $is_anaconda ) ) {
        wp_die( 'No tienes permisos para crear documentos de empresa.', 'Acceso denegado', array( 'response' => 403 ) );
    }

    // Verificar nonce
    if ( empty( $_POST['anaconda_documents_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['anaconda_documents_nonce'] ) ), 'anaconda_documents_create' ) ) {
        wp_die( 'Nonce inválido.', 'Acceso denegado', array( 'response' => 403 ) );
    }

    if ( empty( $_FILES['doc_file'] ) || ! isset( $_FILES['doc_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['doc_file']['tmp_name'] ) ) {
        wp_safe_redirect( add_query_arg( 'result', 'no_file', wp_get_referer() ?: admin_url( 'admin.php?page=hrm-anaconda-documents' ) ) );
        exit;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $file = $_FILES['doc_file'];
    $overrides = array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) );
    $move = wp_handle_upload( $file, $overrides );

    if ( isset( $move['error'] ) ) {
        error_log( '[HRM-DEBUG] anaconda upload error: ' . $move['error'] );
        wp_safe_redirect( add_query_arg( 'result', 'upload_error', wp_get_referer() ?: admin_url( 'admin.php?page=hrm-anaconda-documents' ) ) );
        exit;
    }

    // TODO: Aquí se puede insertar un registro en la tabla HRM de documentos si se requiere.
    // Por ahora redirigimos a la página con resultado de éxito.
    wp_safe_redirect( add_query_arg( 'result', 'success', wp_get_referer() ?: admin_url( 'admin.php?page=hrm-anaconda-documents' ) ) );
    exit;
}
add_action( 'admin_post_anaconda_documents_create', 'hrm_handle_anaconda_documents_create' );
