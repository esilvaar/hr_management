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

    // CRÍTICO: Mapear 'manage_hrm_vacaciones' a 'read' para acceso a páginas de vacaciones
    if ( in_array( $current_page, array( 'hrm-vacaciones', 'hrm-vacaciones-formulario' ), true ) && $cap === 'manage_hrm_vacaciones' ) {
        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            // Verificar directamente en la tabla usermeta sin llamar a has_cap() para evitar loop infinito
            $user_meta = get_user_meta( $user_id, $GLOBALS['wpdb']->prefix . 'capabilities', true );
            if ( is_array( $user_meta ) && isset( $user_meta['manage_hrm_vacaciones'] ) && $user_meta['manage_hrm_vacaciones'] ) {
                // Usuario tiene la capability, permitir acceso directo
                return array(); // array vacío = permitir
            }
        }
    }

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
    
    // CRÍTICO: Asegurar que usuarios con manage_hrm_vacaciones pueden acceder a páginas de vacaciones
    $vacaciones_pages = array( 'hrm-vacaciones', 'hrm-vacaciones-formulario' );
    if ( in_array( $current_page, $vacaciones_pages, true ) ) {
        // Si el usuario tiene manage_hrm_vacaciones, permitir acceso
        if ( $user->has_cap( 'manage_hrm_vacaciones' ) ) {
            $allcaps['manage_hrm_vacaciones'] = true;
            return $allcaps;
        }
    }
    
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

    // Para editor_vacaciones, BLOQUEAR acceso a profile.php y redirigir a página de vacaciones
    $is_editor_vacaciones = in_array( 'editor_vacaciones', (array) $current_user->roles );
    if ( $is_editor_vacaciones && $pagenow === 'profile.php' ) {
        wp_safe_redirect( admin_url( 'admin.php?page=hrm-vacaciones' ) );
        exit;
    }

    // PERMITIR acceso a profile.php para otros usuarios EXCEPTO editor_vacaciones (su propio perfil para editar avatar/contraseña)
    if ( $pagenow === 'profile.php' && ! $is_admin_anaconda && ! $is_editor_vacaciones ) {
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

// The anaconda documents create form and its standalone handler were removed.
// Related admin-post handling is no longer required here.

/**
 * Manejar subidas de documentos desde el formulario del panel (hrm_action = upload_document)
 * Guarda archivos en uploads/hrm_docs/{anio}/{rut}/{tipo_slug}/ y los registra en la tabla de documentos.
 */
function hrm_handle_upload_document() {
    if ( ! isset( $_POST['hrm_action'] ) || $_POST['hrm_action'] !== 'upload_document' ) {
        return;
    }

    // Solo gestionar POST
    if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        return;
    }

    // Evitar doble procesamiento si ya fue manejado
    if ( defined( 'HRM_UPLOAD_DOCUMENT_HANDLED' ) && HRM_UPLOAD_DOCUMENT_HANDLED ) {
        return;
    }
    define( 'HRM_UPLOAD_DOCUMENT_HANDLED', true );

    // Verificar nonce
    $nonce = isset( $_POST['hrm_upload_nonce'] ) ? wp_unslash( $_POST['hrm_upload_nonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'hrm_upload_file' ) ) {
        wp_send_json_error( array( 'message' => 'Token de seguridad inválido' ), 403 );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Usuario no autenticado' ), 401 );
    }

    // Permisos: admin o editar empleados
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_hrm_employees' ) ) {
        wp_send_json_error( array( 'message' => 'No tienes permisos para subir documentos' ), 403 );
    }

    // Validar campos necesarios
    $employee_id = isset( $_POST['employee_id'] ) ? absint( wp_unslash( $_POST['employee_id'] ) ) : 0;
    $tipo_input  = isset( $_POST['tipo_documento'] ) ? sanitize_text_field( wp_unslash( $_POST['tipo_documento'] ) ) : '';
    $anio        = isset( $_POST['anio_documento'] ) ? sanitize_text_field( wp_unslash( $_POST['anio_documento'] ) ) : date( 'Y' );

    if ( empty( $employee_id ) ) {
        wp_send_json_error( array( 'message' => 'Empleado no seleccionado' ), 400 );
    }
    if ( empty( $tipo_input ) ) {
        wp_send_json_error( array( 'message' => 'Tipo de documento vacío' ), 400 );
    }

    // Asegurar clases DB
    hrm_ensure_db_classes();
    $db_emp  = new HRM_DB_Empleados();
    $db_docs = new HRM_DB_Documentos();

    $employee = $db_emp->get( $employee_id );
    if ( ! $employee ) {
        wp_send_json_error( array( 'message' => 'Empleado no encontrado' ), 404 );
    }

    $rut = ! empty( $employee->rut ) ? $employee->rut : $employee_id;

    // Normalizar tipo: si es ID, obtener nombre
    $tipo_name = $tipo_input;
    if ( is_numeric( $tipo_input ) ) {
        $all_types = $db_docs->get_all_types();
        $tid = (int) $tipo_input;
        if ( isset( $all_types[ $tid ] ) ) $tipo_name = $all_types[ $tid ];
    }
    $tipo_slug = sanitize_title( $tipo_name );

    // Preparar directorio destino (orden unificado: anio / rut / tipo)
    $upload = wp_upload_dir();
    $base_dir = trailingslashit( $upload['basedir'] ) . 'hrm_docs/';

    // Normalizar año y rut similares al handler central
    $year = preg_replace( '/[^0-9]/', '', (string) $anio );
    if ( empty( $year ) ) $year = date( 'Y' );
    $rut_slug = sanitize_file_name( preg_replace( '/[^A-Za-z0-9\-]/', '_', (string) $rut ) );

    $rel_dir = trailingslashit( $year ) . trailingslashit( $rut_slug ) . trailingslashit( $tipo_slug );
    $dest_dir = wp_normalize_path( $base_dir . $rel_dir );

    if ( ! wp_mkdir_p( $dest_dir ) ) {
        // intentar crear directorio y fallar si no es posible
        wp_send_json_error( array( 'message' => 'No se pudo crear carpeta de destino en uploads' ), 500 );
    }

    if ( empty( $_FILES['archivos_subidos'] ) || empty( $_FILES['archivos_subidos']['name'] ) ) {
        wp_send_json_error( array( 'message' => 'No hay archivos para subir' ), 400 );
    }

    $files = $_FILES['archivos_subidos'];
    $saved = array();

    // Procesar múltiples archivos
    for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
        if ( empty( $files['name'][ $i ] ) ) continue;
        $tmp_name = $files['tmp_name'][ $i ];
        $orig_name_raw = isset( $files['name'][ $i ] ) ? $files['name'][ $i ] : '';
        $orig_name = sanitize_file_name( $orig_name_raw );
        // Fallback si sanitize devuelve vacío
        if ( empty( $orig_name ) ) {
            $orig_name = 'documento-' . $i . '.pdf';
        }

        // Usar el nombre original (sanitizado) y asegurar unicidad en el destino
        $final_name = wp_unique_filename( $dest_dir, $orig_name );
        $final_path = wp_normalize_path( $dest_dir . DIRECTORY_SEPARATOR . $final_name );

        // Mover archivo desde tmp a destino
        if ( ! is_uploaded_file( $tmp_name ) ) {
            // intentar continuar con el siguiente
            continue;
        }

        if ( ! @move_uploaded_file( $tmp_name, $final_path ) ) {
            error_log( 'HRM Upload - Failed to move uploaded file to: ' . $final_path );
            continue;
        }

        // Construir URL pública
        $file_url = trailingslashit( $upload['baseurl'] ) . 'hrm_docs/' . ltrim( $rel_dir, '/' ) . $final_name;

        // Registrar en tabla de documentos
        $inserted = $db_docs->create( array(
            'rut'  => $rut,
            'tipo' => $tipo_input,
            'anio' => $anio,
            'nombre' => $final_name,
            'url' => $file_url,
        ) );

        if ( $inserted ) {
            $saved[] = array( 'name' => $final_name, 'url' => $file_url );
        } else {
            // si falla la inserción en DB, intentar eliminar archivo para evitar basura
            @unlink( $final_path );
        }
    }

    if ( empty( $saved ) ) {
        wp_send_json_error( array( 'message' => 'No se pudieron procesar los archivos' ), 500 );
    }

    wp_send_json_success( array( 'message' => 'Archivos subidos', 'files' => $saved ) );
}
add_action( 'admin_init', 'hrm_handle_upload_document', 5 );
