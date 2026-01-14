<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Obtiene los departamentos predefinidos para cada área gerencial
 * 
 * @param string $area_gerencia El área gerencial (ej: "Comercial", "Proyectos", "Operaciones")
 * @return array Array de departamentos a cargo
 */
function hrm_get_deptos_predefinidos_por_area( $area_gerencia ) {
    $mapeo = array(
        'Comercial' => array(
            'Soporte',
            'Ventas'
        ),
        'Proyectos' => array(
            'Desarrollo'
        ),
        'Operaciones' => array(
            'Administracion',
            'Gerencia',
            'Sistemas'
        )
    );
    
    // Normalizar la búsqueda (case-insensitive)
    $area_normalizada = null;
    foreach ( $mapeo as $key => $deptos ) {
        if ( strtolower( $key ) === strtolower( $area_gerencia ) ) {
            $area_normalizada = $key;
            break;
        }
    }
    
    if ( $area_normalizada && isset( $mapeo[ $area_normalizada ] ) ) {
        return $mapeo[ $area_normalizada ];
    }
    
    return array();
}

/**
 * Renderiza la página de administración (Vista)
 */
function hrm_render_employees_admin_page() {
    // Cargar la vista correspondiente según el rol/capability del usuario
    // 1) Admin siempre ve la vista completa
    if ( current_user_can( 'manage_options' ) ) {
        require_once HRM_PLUGIN_DIR . 'views/Administrador/employees-admin.php';
        return;
    }

    // 2) Usuarios con view_hrm_admin_views (como administrador_anaconda) ven vista de admin
    if ( current_user_can( 'view_hrm_admin_views' ) ) {
        require_once HRM_PLUGIN_DIR . 'views/Administrador/employees-admin.php';
        return;
    }

    // 3) Mapear roles a vistas (configurable mediante filtro)
    $default_map = array(
        'supervisor' => HRM_PLUGIN_DIR . 'views/Administrador/employees-admin.php',
        'editor_vacaciones' => HRM_PLUGIN_DIR . 'views/employees-editor_vacaciones.php',
        'empleado' => HRM_PLUGIN_DIR . 'views/Empleado/employees-empleados.php',
    );

    $map = apply_filters( 'hrm_role_views_map', $default_map );

    $current_user = wp_get_current_user();
    if ( ! empty( $current_user->roles ) && is_array( $current_user->roles ) ) {
        foreach ( $current_user->roles as $r ) {
            if ( isset( $map[ $r ] ) && file_exists( $map[ $r ] ) ) {
                require_once $map[ $r ];
                return;
            }
        }
    }

    // 4) Fallback por capability
    if ( current_user_can( 'view_hrm_employee_admin' ) && file_exists( HRM_PLUGIN_DIR . 'views/Empleado/employees-empleados.php' ) ) {
        require_once HRM_PLUGIN_DIR . 'views/Empleado/employees-empleados.php';
        return;
    }

    wp_die( 'No tienes permisos para ver esta página.', 'Acceso denegado', array( 'response' => 403 ) );
}

/**
 * Procesa los formularios (Controlador)
 * Se ejecuta en 'admin_init' para poder redireccionar antes de enviar cabeceras HTML.
 */
function hrm_handle_employees_post() {
    // 1. Verificaciones de seguridad básicas
    if ( ! is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        return;
    }

    // Si no se envió ninguna acción de nuestro plugin, salimos.
    if ( ! isset( $_POST['hrm_action'] ) ) {
        return;
    }

    $action = $_POST['hrm_action'];

    // 2. Instancias de Base de Datos
    // Asegúrate de que las clases estén cargadas antes de este punto
    $db_emp  = new HRM_DB_Empleados();
    $db_docs = new HRM_DB_Documentos();

    // URL base para redirecciones (volver a la página del plugin)
    $base_url = add_query_arg( ['page' => 'hrm-empleados'], admin_url( 'admin.php' ) );

    // Detectar página de origen para redirección correcta
    $referrer_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : 'hrm-empleados';
    $is_own_profile = in_array( $referrer_page, array( 'hrm-mi-perfil', 'hrm-mi-perfil-info' ), true );
    
    // Si viene de su propia página, redireccionar ahí; sino a empleados
    $redirect_base = ( $is_own_profile ) ? 
        add_query_arg( ['page' => $referrer_page], admin_url( 'admin.php' ) ) :
        $base_url;

    // =========================================================================
    // ACCIÓN A: ACTUALIZAR EMPLEADO
    // =========================================================================
    if ( $action === 'update_employee' && check_admin_referer( 'hrm_update_employee', 'hrm_update_employee_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );

        hrm_debug_log( 'Update employee action triggered', $_POST );

        // Verificar permisos
        if ( ! hrm_can_edit_employee( $emp_id ) ) {
            hrm_redirect_with_message(
                $redirect_base,
                __( 'No tienes permisos para editar este perfil.', 'hr-management' ),
                'error'
            );
        }

        // Si el request incluye un archivo de avatar, procesarlo primero
        if ( ! empty( $_FILES['avatar'] ) && isset( $_FILES['avatar']['name'] ) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK ) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $file = $_FILES['avatar'];
            $overrides = array( 'test_form' => false );

            $upload_result = wp_handle_upload( $file, $overrides );
            if ( isset( $upload_result['file'] ) && ! empty( $upload_result['file'] ) && file_exists( $upload_result['file'] ) ) {
                // Guardado físico correcto; intentamos registrar como attachment en WP
                $file_path = $upload_result['file'];

                if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                if ( ! function_exists( 'wp_insert_attachment' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                }

                $filetype = wp_check_filetype( basename( $file_path ), null );
                $attachment = array(
                    'guid'           => $upload_result['url'],
                    'post_mime_type' => $filetype['type'] ?? '',
                    'post_title'     => sanitize_file_name( basename( $file_path ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attach_id = wp_insert_attachment( $attachment, $file_path );
                if ( ! is_wp_error( $attach_id ) ) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                    wp_update_attachment_metadata( $attach_id, $attach_data );

                    $avatar_url = wp_get_attachment_url( $attach_id );

                    if ( ! empty( $employee_obj ) && ! empty( $employee_obj->user_id ) ) {
                        $user_id = intval( $employee_obj->user_id );
                        // Guardamos URL específica de plugin
                        update_user_meta( $user_id, 'hrm_avatar', esc_url_raw( $avatar_url ) );
                        // Sincronizar con metadatos comunes (plugins que usan attachment ID)
                        update_user_meta( $user_id, 'user_avatar', $attach_id );
                        update_user_meta( $user_id, 'wp_user_avatar', $attach_id );
                        // simple_local_avatar suele esperar array con 'full' y 'thumb'
                        update_user_meta( $user_id, 'simple_local_avatar', array( 'full' => $avatar_url, 'thumb' => $avatar_url ) );
                    } else {
                        update_option( 'hrm_avatar_emp_' . intval( $emp_id ), esc_url_raw( $avatar_url ) );
                    }
                } else {
                    // Fallback: si no pudimos crear attachment, guardamos URL directa
                    if ( isset( $upload_result['url'] ) && ! empty( $upload_result['url'] ) ) {
                        $avatar_url = $upload_result['url'];
                        if ( ! empty( $employee_obj ) && ! empty( $employee_obj->user_id ) ) {
                            update_user_meta( intval( $employee_obj->user_id ), 'hrm_avatar', esc_url_raw( $avatar_url ) );
                        } else {
                            update_option( 'hrm_avatar_emp_' . intval( $emp_id ), esc_url_raw( $avatar_url ) );
                        }
                    }
                }
            }
        }

        $update_result = $db_emp->update( $emp_id, $_POST );
        hrm_debug_log( 'Update result', $update_result ? 'success' : 'failed' );

        // ★ Actualizar años en la empresa y total de años trabajados
        do_action( 'hrm_after_employee_update', $emp_id );

        // Si es Gerente, guardar departamentos a cargo
        $puesto = isset( $_POST['puesto'] ) ? sanitize_text_field( $_POST['puesto'] ) : '';
        $area_gerencia = isset( $_POST['area_gerencia'] ) ? sanitize_text_field( $_POST['area_gerencia'] ) : '';
        $deptos_a_cargo = isset( $_POST['deptos_a_cargo'] ) ? (array) $_POST['deptos_a_cargo'] : [];
        
        if ( strtolower( $puesto ) === 'gerente' && ! empty( $area_gerencia ) ) {
            // Obtener nombre y correo del gerente
            $nombre_gerente = '';
            $correo_gerente = '';
            if ( isset( $_POST['nombre'] ) && isset( $_POST['apellido'] ) ) {
                $nombre = sanitize_text_field( $_POST['nombre'] );
                $apellido = sanitize_text_field( $_POST['apellido'] );
                $nombre_gerente = trim( "$nombre $apellido" );
            }
            if ( isset( $_POST['email'] ) ) {
                $correo_gerente = sanitize_email( $_POST['email'] );
            }
            
            require_once plugin_dir_path( __FILE__ ) . 'db/class-hrm-db-gerencia-deptos.php';
            $db_gerencia = new HRM_DB_Gerencia_Deptos();
            $db_gerencia->save_area_deptos( $area_gerencia, $deptos_a_cargo, $nombre_gerente, $correo_gerente );
            error_log( "HRM: Departamentos a cargo actualizados para área: $area_gerencia (Gerente: $nombre_gerente - $correo_gerente)" );
            
            // LIMPIAR CACHÉS DE VACACIONES RELACIONADOS
            // Esto es importante para que los gerentes vean las solicitudes correctas
            wp_cache_delete( 'hrm_all_vacaciones_' . md5( '' . '' . '' ), '' );
            // Limpiar todos los cachés de vacaciones de todos los usuarios
            global $wpdb;
            $users = $wpdb->get_results( "SELECT ID FROM {$wpdb->users}" );
            foreach ( $users as $user ) {
                $cache_patterns = array(
                    'hrm_all_vacaciones_' . md5( '' . '' . $user->ID ),
                    'hrm_all_vacaciones_' . md5( '' . 'PENDIENTE' . $user->ID ),
                    'hrm_all_vacaciones_' . md5( '' . 'APROBADA' . $user->ID ),
                    'hrm_all_vacaciones_' . md5( '' . 'RECHAZADA' . $user->ID ),
                );
                foreach ( $cache_patterns as $pattern ) {
                    wp_cache_delete( $pattern );
                }
            }
        }

        if ( $update_result ) {
            hrm_redirect_with_message(
                $redirect_base,
                __( 'Datos actualizados.', 'hr-management' ),
                'success'
            );
        } else {
            hrm_redirect_with_message(
                $redirect_base,
                __( 'No se realizaron cambios o error en actualización.', 'hr-management' ),
                'error'
            );
        }
    }

    // =========================================================================
    // ACCIÓN: TOGGLE ESTADO EMPLEADO (Activar/Desactivar)
    // =========================================================================
    if ( $action === 'toggle_employee_status' && check_admin_referer( 'hrm_toggle_employee_status', 'hrm_toggle_status_nonce' ) ) {
        
        // Solo administradores y supervisores pueden cambiar el estado
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_hrm_employees' ) ) {
            hrm_redirect_with_message(
                $redirect_base,
                __( 'No tienes permisos para cambiar el estado de empleados.', 'hr-management' ),
                'error'
            );
        }
        
        $emp_id = absint( $_POST['employee_id'] );
        $current_estado = intval( $_POST['current_estado'] ?? 1 );
        
        // Toggle: si está activo (1), cambiar a inactivo (0) y viceversa
        $nuevo_estado = ( $current_estado === 1 ) ? 0 : 1;
        
        global $wpdb;
        $table = $wpdb->prefix . 'rrhh_empleados';
        
        // Obtener el departamento del empleado ANTES de cambiar el estado
        $empleado = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT departamento FROM {$table} WHERE id_empleado = %d",
                $emp_id
            )
        );
        
        $result = $wpdb->update(
            $table,
            array( 'estado' => $nuevo_estado ),
            array( 'id_empleado' => $emp_id ),
            array( '%d' ),
            array( '%d' )
        );
        
        if ( $result !== false ) {
            // Si el empleado tiene departamento, actualizar el recuento
            if ( $empleado && ! empty( $empleado->departamento ) ) {
                $departamento = $empleado->departamento;
                
                // Si se desactiva (de 1 a 0), decrementar personal_vigente
                if ( $current_estado === 1 && $nuevo_estado === 0 ) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}rrhh_departamentos 
                             SET personal_vigente = GREATEST(0, personal_vigente - 1)
                             WHERE nombre_departamento = %s",
                            $departamento
                        )
                    );
                    error_log( "HRM: Decrementado personal_vigente para departamento '$departamento' (empleado desactivado)" );
                    // Limpiar caché de departamentos para obtener datos frescos
                    hrm_clear_departamentos_cache();
                }
                // Si se activa (de 0 a 1), incrementar personal_vigente
                elseif ( $current_estado === 0 && $nuevo_estado === 1 ) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}rrhh_departamentos 
                             SET personal_vigente = personal_vigente + 1
                             WHERE nombre_departamento = %s",
                            $departamento
                        )
                    );
                    error_log( "HRM: Incrementado personal_vigente para departamento '$departamento' (empleado activado)" );
                    // Limpiar caché de departamentos para obtener datos frescos
                    hrm_clear_departamentos_cache();
                }
            }
            
            $mensaje = ( $nuevo_estado === 1 ) 
                ? __( 'Empleado activado correctamente. Ahora puede iniciar sesión.', 'hr-management' )
                : __( 'Empleado desactivado correctamente. Su acceso ha sido bloqueado.', 'hr-management' );
            
            $redirect_url = add_query_arg( 
                array( 'page' => 'hrm-empleados', 'tab' => 'profile', 'id' => $emp_id ), 
                admin_url( 'admin.php' ) 
            );
            
            hrm_redirect_with_message( $redirect_url, $mensaje, 'success' );
        } else {
            hrm_redirect_with_message(
                $redirect_base,
                __( 'Error al cambiar el estado del empleado.', 'hr-management' ),
                'error'
            );
        }
    }

    // =========================================================================
    // ACCIÓN E: ELIMINAR AVATAR SUBIDO
    // =========================================================================
    if ( $action === 'delete_avatar' && check_admin_referer( 'hrm_delete_avatar', 'hrm_delete_avatar_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] ?? 0 );
        if ( ! $emp_id ) {
            wp_redirect( add_query_arg( ['tab' => 'profile', 'id' => $emp_id, 'message_error' => rawurlencode('Empleado no válido.')], $redirect_base ) );
            exit;
        }

        $employee_obj = $db_emp->get( $emp_id );
        $current_user_id = get_current_user_id();

        $allowed = false;
        if ( current_user_can( 'edit_hrm_employees' ) ) {
            $allowed = true;
        } elseif ( current_user_can( 'view_hrm_own_profile' ) && $employee_obj && intval( $employee_obj->user_id ) === $current_user_id ) {
            $allowed = true;
        }

        if ( ! $allowed ) {
            wp_redirect( add_query_arg( ['tab' => 'profile', 'id' => $emp_id, 'message_error' => rawurlencode('No tienes permisos para esta acción.')], $redirect_base ) );
            exit;
        }

        // Si está vinculado a user WP, intentamos borrar attachment y metadatos
        if ( $employee_obj && ! empty( $employee_obj->user_id ) ) {
            $user_id = intval( $employee_obj->user_id );

            // Borrar attachment si existe en usermeta (user_avatar / wp_user_avatar)
            $attach_id = get_user_meta( $user_id, 'user_avatar', true );
            if ( empty( $attach_id ) ) {
                $attach_id = get_user_meta( $user_id, 'wp_user_avatar', true );
            }

            // Si no hay ID, intentar resolver desde URL almacenada en metas
            if ( empty( $attach_id ) ) {
                $avatar_url = '';
                $simple_local = get_user_meta( $user_id, 'simple_local_avatar', true );
                if ( is_array( $simple_local ) && ! empty( $simple_local['full'] ) ) {
                    $avatar_url = esc_url_raw( $simple_local['full'] );
                }
                if ( empty( $avatar_url ) ) {
                    $meta_url = get_user_meta( $user_id, 'hrm_avatar', true );
                    if ( $meta_url ) {
                        $avatar_url = esc_url_raw( $meta_url );
                    }
                }
                if ( $avatar_url ) {
                    $maybe_attach = attachment_url_to_postid( $avatar_url );
                    if ( $maybe_attach ) {
                        $attach_id = $maybe_attach;
                    } else {
                        // Último recurso: eliminar archivo físico si existe
                        $upload_dir = wp_upload_dir();
                        $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $avatar_url );
                        if ( $file_path && file_exists( $file_path ) ) {
                            @unlink( $file_path );
                        }
                    }
                }
            }

            if ( ! empty( $attach_id ) && is_numeric( $attach_id ) ) {
                // Intentar borrar attachment (force delete)
                wp_delete_attachment( intval( $attach_id ), true );
            }

            // Eliminar metadatos que sincronizamos
            delete_user_meta( $user_id, 'hrm_avatar' );
            delete_user_meta( $user_id, 'user_avatar' );
            delete_user_meta( $user_id, 'wp_user_avatar' );
            delete_user_meta( $user_id, 'simple_local_avatar' );
            // También eliminar opción legacy por si existiera
            delete_option( 'hrm_avatar_emp_' . intval( $emp_id ) );
        } else {
            // Si no hay user_id, eliminar archivo físico y la opción específica
            $opt_key = 'hrm_avatar_emp_' . intval( $emp_id );
            $opt_url = get_option( $opt_key );
            if ( $opt_url ) {
                $upload_dir = wp_upload_dir();
                $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $opt_url );
                if ( $file_path && file_exists( $file_path ) ) {
                    @unlink( $file_path );
                }
            }
            delete_option( $opt_key );
        }

        wp_redirect( add_query_arg( ['tab' => 'profile', 'id' => $emp_id, 'message_success' => rawurlencode('Avatar eliminado.')], $redirect_base ) );
        exit;
    }

    // =========================================================================
    // ACCIÓN: SUBIR AVATAR (Sin tocar otros datos del empleado)
    // =========================================================================
    if ( $action === 'upload_avatar' ) {
        hrm_debug_log( 'Upload avatar action triggered' );
        
        // Verificar nonce
        if ( ! isset( $_POST['hrm_upload_avatar_nonce'] ) || 
             ! wp_verify_nonce( $_POST['hrm_upload_avatar_nonce'], 'hrm_upload_avatar' ) ) {
            hrm_debug_log( 'Nonce verification failed' );
            hrm_redirect_with_message(
                $redirect_base,
                __( 'Error de seguridad: nonce inválido.', 'hr-management' ),
                'error'
            );
        }
        
        $emp_id = absint( $_POST['employee_id'] ?? 0 );
        if ( ! $emp_id ) {
            hrm_debug_log( 'No employee_id provided' );
            wp_redirect( $redirect_base );
            exit;
        }

        // Cargar contexto empleado y usuario actual
        $employee_obj   = $db_emp->get( $emp_id );
        $current_user_id = get_current_user_id();
        $allowed = false;

        // Verificar permisos
        if ( ! hrm_can_edit_employee( $emp_id ) ) {
            hrm_redirect_with_message(
                $redirect_base,
                __( 'No tienes permisos para subir avatar.', 'hr-management' ),
                'error'
            );
        }
        if ( current_user_can( 'edit_hrm_employees' ) ) {
            $allowed = true;
        } elseif ( current_user_can( 'view_hrm_own_profile' ) && $employee_obj && intval( $employee_obj->user_id ) === $current_user_id ) {
            $allowed = true;
        }

        if ( ! $allowed ) {
            wp_redirect( add_query_arg( ['message_error' => rawurlencode('No tienes permisos para subir avatar.')], $redirect_base ) );
            exit;
        }

        // Procesar archivo de avatar
        if ( ! empty( $_FILES['avatar'] ) && isset( $_FILES['avatar']['name'] ) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK ) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $file = $_FILES['avatar'];
            $overrides = array( 'test_form' => false );

            $upload_result = wp_handle_upload( $file, $overrides );
            if ( isset( $upload_result['file'] ) && ! empty( $upload_result['file'] ) && file_exists( $upload_result['file'] ) ) {
                $file_path = $upload_result['file'];

                if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                if ( ! function_exists( 'wp_insert_attachment' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                }

                $filetype = wp_check_filetype( basename( $file_path ), null );
                $attachment = array(
                    'guid'           => $upload_result['url'],
                    'post_mime_type' => $filetype['type'] ?? '',
                    'post_title'     => sanitize_file_name( basename( $file_path ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );

                $attach_id = wp_insert_attachment( $attachment, $file_path );
                if ( ! is_wp_error( $attach_id ) ) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                    wp_update_attachment_metadata( $attach_id, $attach_data );

                    $avatar_url = wp_get_attachment_url( $attach_id );

                    if ( ! empty( $employee_obj ) && ! empty( $employee_obj->user_id ) ) {
                        $user_id = intval( $employee_obj->user_id );
                        update_user_meta( $user_id, 'hrm_avatar', esc_url_raw( $avatar_url ) );
                        update_user_meta( $user_id, 'user_avatar', $attach_id );
                        update_user_meta( $user_id, 'wp_user_avatar', $attach_id );
                        update_user_meta( $user_id, 'simple_local_avatar', array( 'full' => $avatar_url, 'thumb' => $avatar_url ) );
                    } else {
                        update_option( 'hrm_avatar_emp_' . intval( $emp_id ), esc_url_raw( $avatar_url ) );
                    }

                    wp_redirect( add_query_arg( ['message_success' => rawurlencode('Avatar actualizado.')], $redirect_base ) );
                    exit;
                } else {
                    if ( isset( $upload_result['url'] ) && ! empty( $upload_result['url'] ) ) {
                        $avatar_url = $upload_result['url'];
                        if ( ! empty( $employee_obj ) && ! empty( $employee_obj->user_id ) ) {
                            update_user_meta( intval( $employee_obj->user_id ), 'hrm_avatar', esc_url_raw( $avatar_url ) );
                        } else {
                            update_option( 'hrm_avatar_emp_' . intval( $emp_id ), esc_url_raw( $avatar_url ) );
                        }
                    }
                    wp_redirect( add_query_arg( ['message_success' => rawurlencode('Avatar actualizado.')], $redirect_base ) );
                    exit;
                }
            } else {
                $error_msg = isset( $upload_result['error'] ) ? $upload_result['error'] : 'Error desconocido al subir archivo.';
                wp_redirect( add_query_arg( ['message_error' => rawurlencode( $error_msg )], $redirect_base ) );
                exit;
            }
        } else {
            wp_redirect( add_query_arg( ['message_error' => rawurlencode('No seleccionaste un archivo.')], $redirect_base ) );
            exit;
        }
    }

    // =========================================================================
    // ACCIÓN B: CREAR EMPLEADO (CON LÓGICA DE USUARIO WP)
    // =========================================================================
    if ( $action === 'create_employee' && check_admin_referer( 'hrm_create_employee', 'hrm_create_employee_nonce' ) ) {
        
        // 1. Recoger datos del formulario
        $rut      = sanitize_text_field( $_POST['rut'] );
        $email    = sanitize_email( $_POST['email'] );
        $nombre   = sanitize_text_field( $_POST['nombre'] );
        $apellido = sanitize_text_field( $_POST['apellido'] );
        $rol_wp   = sanitize_text_field( $_POST['rol_usuario_wp'] ?? 'subscriber' );
        
        // Checkbox: ¿El usuario quiere crear cuenta web?
        $crear_wp = isset($_POST['crear_usuario_wp']); 

        $wp_user_id = null; // Aquí guardaremos el ID si se crea el usuario
        $error_wp   = '';   // Aquí guardaremos errores de WP si ocurren

        // 2. Intentar crear el usuario en WordPress
        if ( $crear_wp ) {
            // Limpieza del RUT para usarlo como Username (quitar puntos, espacios)
            $username_clean = str_replace(['.', ' ', ','], '', trim($rut));

            // Validaciones previas
            if ( empty( $email ) || ! is_email( $email ) ) {
                $error_wp = 'El correo electrónico es inválido o está vacío.';
            } elseif ( email_exists( $email ) ) {
                $error_wp = 'El correo electrónico ya está registrado en WordPress.';
            } elseif ( username_exists( $username_clean ) ) {
                $error_wp = "El usuario '$username_clean' (RUT) ya existe en WordPress.";
            } else {
                // Todo OK, procedemos a insertar en wp_users
                $password = wp_generate_password( 12, false );
                
                $userdata = [
                    'user_login' => $username_clean,
                    'user_email' => $email,
                    'user_pass'  => $password,
                    'first_name' => $nombre,
                    'last_name'  => $apellido,
                    'role'       => $rol_wp,
                ];

                $new_id = wp_insert_user( $userdata );

                if ( is_wp_error( $new_id ) ) {
                    // Falló WP
                    $error_wp = $new_id->get_error_message();
                } else {
                    // Éxito WP
                    $wp_user_id = $new_id;
                    // Enviar credenciales (username + password) al usuario recién creado
                    $sent = false;
                    if ( function_exists( 'hrm_send_user_credentials_email' ) ) {
                        $sent = hrm_send_user_credentials_email( $new_id, $username_clean, $password, $email );
                    }
                    // Como fallback opcional, permitir llamar al notifier WP si el filtro lo permite
                    if ( ! $sent && apply_filters( 'hrm_send_new_user_notification', false, $new_id ) ) {
                        wp_new_user_notification( $new_id, null, 'both' );
                    }
                }
            }
        }

        // 3. Manejo de Errores antes de guardar en BD Local
        if ( $error_wp ) {
            // Si hubo error creando el usuario WP, NO creamos el empleado y mostramos el error
            wp_redirect( add_query_arg( ['tab' => 'new', 'message_error' => rawurlencode("Error Usuario WP: $error_wp")], $base_url ) );
            exit;
        }

        // 4. Preparar datos para la tabla personalizada
        $data = $_POST;
        
        // Si se creó usuario WP, añadimos el ID al array para guardarlo
        if ( $wp_user_id ) {
            $data['user_id'] = $wp_user_id;
        }
        
        // Log para debugging de años
        error_log( "HRM DEBUG create_employee - anos_en_la_empresa: " . ($data['anos_en_la_empresa'] ?? 'NO ENVIADO') );
        error_log( "HRM DEBUG create_employee - anos_totales_trabajados: " . ($data['anos_totales_trabajados'] ?? 'NO ENVIADO') );

        // 5. Guardar en tabla rrhh_empleados
        if ( $db_emp->create( $data ) ) {
            // 5.5. Si es Gerente, guardar departamentos a cargo
            $puesto = isset( $data['puesto'] ) ? sanitize_text_field( $data['puesto'] ) : '';
            $area_gerencia = isset( $data['area_gerencia'] ) ? sanitize_text_field( $data['area_gerencia'] ) : '';
            $deptos_a_cargo = isset( $_POST['deptos_a_cargo'] ) ? (array) $_POST['deptos_a_cargo'] : [];
            
            // Si no se proporcionan departamentos, usar los predefinidos según el área gerencial
            if ( empty( $deptos_a_cargo ) && ! empty( $area_gerencia ) ) {
                $deptos_a_cargo = hrm_get_deptos_predefinidos_por_area( $area_gerencia );
                error_log( "HRM: Departamentos predefinidos para {$area_gerencia}: " . implode( ', ', $deptos_a_cargo ) );
            }
            
            if ( strtolower( $puesto ) === 'gerente' && ! empty( $area_gerencia ) && ! empty( $deptos_a_cargo ) ) {
                // Obtener nombre y correo del gerente
                $nombre_gerente = '';
                $correo_gerente = '';
                if ( isset( $data['nombre'] ) && isset( $data['apellido'] ) ) {
                    $nombre = sanitize_text_field( $data['nombre'] );
                    $apellido = sanitize_text_field( $data['apellido'] );
                    $nombre_gerente = trim( "$nombre $apellido" );
                }
                if ( isset( $data['email'] ) ) {
                    $correo_gerente = sanitize_email( $data['email'] );
                }
                
                require_once plugin_dir_path( __FILE__ ) . 'db/class-hrm-db-gerencia-deptos.php';
                $db_gerencia = new HRM_DB_Gerencia_Deptos();
                $db_gerencia->save_area_deptos( $area_gerencia, $deptos_a_cargo, $nombre_gerente, $correo_gerente );
                error_log( "HRM: Departamentos a cargo guardados para área: $area_gerencia (Gerente: $nombre_gerente - $correo_gerente)" );
                
                // LIMPIAR CACHÉS DE VACACIONES
                global $wpdb;
                $users = $wpdb->get_results( "SELECT ID FROM {$wpdb->users}" );
                foreach ( $users as $user ) {
                    $cache_patterns = array(
                        'hrm_all_vacaciones_' . md5( '' . '' . $user->ID ),
                        'hrm_all_vacaciones_' . md5( '' . 'PENDIENTE' . $user->ID ),
                        'hrm_all_vacaciones_' . md5( '' . 'APROBADA' . $user->ID ),
                        'hrm_all_vacaciones_' . md5( '' . 'RECHAZADA' . $user->ID ),
                    );
                    foreach ( $cache_patterns as $pattern ) {
                        wp_cache_delete( $pattern );
                    }
                }
            }
            
            // 6. Incrementar total_empleados en el departamento
            global $wpdb;
            
            $departamento = isset( $data['departamento'] ) ? sanitize_text_field( $data['departamento'] ) : '';
            
            if ( ! empty( $departamento ) ) {
                // Incrementar total_empleados en Bu6K9_rrhh_departamentos
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}rrhh_departamentos 
                         SET total_empleados = total_empleados + 1 
                         WHERE nombre_departamento = %s",
                        $departamento
                    )
                );
                
                error_log( "HRM: Incrementado total_empleados para departamento '$departamento'" );
                // Limpiar caché de departamentos para obtener datos frescos
                hrm_clear_departamentos_cache();
            }
            
            $msg = 'Empleado creado correctamente.';
            if ( $wp_user_id ) $msg .= ' (Usuario web generado).';

            wp_redirect( add_query_arg( ['tab' => 'list', 'message_success' => rawurlencode($msg)], $base_url ) );
            exit;
        } else {
            // ROLLBACK: Si falló la base de datos local, borramos el usuario WP para no dejar "huérfanos"
            if ( $wp_user_id ) wp_delete_user( $wp_user_id );

            wp_redirect( add_query_arg( ['tab' => 'new', 'message_error' => rawurlencode('Error SQL al guardar empleado. Revise si el RUT está duplicado.')], $base_url ) );
            exit;
        }
    }

    // =========================================================================
    // ACCIÓN C: SUBIR DOCUMENTOS
    // =========================================================================
    if ( $action === 'upload_document' && check_admin_referer( 'hrm_upload_file', 'hrm_upload_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );
        $tipo   = wp_kses_post( trim( $_POST['tipo_documento'] ?? 'Generico' ) );
        $anio_raw = isset($_POST['anio_documento']) ? trim($_POST['anio_documento']) : '';
        $anio   = ! empty($anio_raw) ? (int)$anio_raw : 0;
        
        $empleado_obj = $db_emp->get( $emp_id );
        $files        = $_FILES['archivos_subidos'] ?? [];

        // Validaciones
        if ( ! $empleado_obj ) {
            wp_redirect( add_query_arg( ['tab' => 'upload', 'id' => $emp_id, 'message_error' => rawurlencode('Empleado no encontrado.')], $base_url ) );
            exit;
        }

        if ( empty( $files ) || empty( $files['name'][0] ) ) {
            wp_redirect( add_query_arg( ['tab' => 'upload', 'id' => $emp_id, 'message_error' => rawurlencode('No seleccionaste archivos.')], $base_url ) );
            exit;
        }

        if ( empty($anio_raw) || $anio === 0 ) {
            wp_redirect( add_query_arg( ['tab' => 'upload', 'id' => $emp_id, 'message_error' => rawurlencode('Debes seleccionar el año del documento.')], $base_url ) );
            exit;
        }

        if ( $anio < 1900 || $anio > (int)date('Y') + 1 ) {
            wp_redirect( add_query_arg( ['tab' => 'upload', 'id' => $emp_id, 'message_error' => rawurlencode('El año seleccionado no es válido.')], $base_url ) );
            exit;
        }

        // Configuración de carpetas: /uploads/hrm_docs/{año}/RUT/Tipo
        $upload_dir_info = wp_upload_dir();
        $base_dir        = $upload_dir_info['basedir'] . '/hrm_docs';
        $base_url_img    = $upload_dir_info['baseurl'] . '/hrm_docs';

        $folder_year = $anio;
        $folder_user = sanitize_file_name( $empleado_obj->rut );
        $folder_type = sanitize_file_name( $tipo );

        $relative_path    = '/' . $folder_year . '/' . $folder_user . '/' . $folder_type;
        $final_target_dir = $base_dir . $relative_path;
        $final_target_url = $base_url_img . $relative_path;

        // Crear carpeta si no existe
        if ( ! file_exists( $final_target_dir ) ) {
            wp_mkdir_p( $final_target_dir );
        }

        $count_ok = 0;
        $count_err = 0;
        $total_files = count( $files['name'] );

        // Bucle de subida
        for ( $i = 0; $i < $total_files; $i++ ) {
            if ( $files['error'][$i] !== UPLOAD_ERR_OK ) { $count_err++; continue; }

            $filename = sanitize_file_name( $files['name'][$i] );
            // Evitar sobrescritura usando timestamp
            $final_filename = file_exists( $final_target_dir . '/' . $filename ) ? time() . '_' . $filename : $filename;

            $file_path = $final_target_dir . '/' . $final_filename;
            $file_url  = $final_target_url . '/' . $final_filename;

            // Mover archivo y guardar en BD
            if ( move_uploaded_file( $files['tmp_name'][$i], $file_path ) ) {
                $saved = $db_docs->create([
                    'rut'    => $empleado_obj->rut,
                    'tipo'   => $tipo,
                    'anio'   => $anio,
                    'nombre' => $final_filename,
                    'url'    => $file_url
                ]);
                if ( $saved ) {
                    $count_ok++;
                } else {
                    $count_err++;
                    error_log( "HRM: Error guardando documento en BD - RUT: {$empleado_obj->rut}, Archivo: $final_filename" );
                }
            } else {
                $count_err++;
                error_log( "HRM: Error moviendo archivo - {$files['name'][$i]} a $file_path" );
            }
        }

        // Mensajes de resultado
        $msgs = [];
        if ( $count_ok > 0 ) $msgs[] = "$count_ok documento(s) subido(s) en la carpeta del año $folder_year.";
        if ( $count_err > 0 ) $msgs[] = "$count_err fallo(s).";

        $redirect_args = ['tab' => 'upload', 'id' => $emp_id];
        if ( $count_ok > 0 ) $redirect_args['message_success'] = rawurlencode( implode(' ', $msgs) );
        if ( $count_err > 0 ) $redirect_args['message_error']   = rawurlencode( implode(' ', $msgs) );

        wp_redirect( add_query_arg( $redirect_args, $base_url ) );
        exit;
    }

    // =========================================================================
    // ACCIÓN D: ELIMINAR DOCUMENTO
    // =========================================================================
    if ( $action === 'delete_document' && check_admin_referer( 'hrm_delete_file', 'hrm_delete_nonce' ) ) {
        $doc_id = absint( $_POST['doc_id'] );
        $doc    = $db_docs->get( $doc_id );

        if ( $doc ) {
            $upload_dir = wp_upload_dir();
            $base_url_wp = $upload_dir['baseurl'];
            $base_dir_wp = $upload_dir['basedir'];
            
            // Convertir URL a ruta física para borrar
            $file_path = str_replace( $base_url_wp, $base_dir_wp, $doc->url );
            
            if ( file_exists( $file_path ) ) {
                unlink( $file_path );
            }
            
            $db_docs->delete( $doc_id );
            
            wp_redirect( add_query_arg( ['tab'=>'upload', 'id'=>absint($_POST['employee_id']), 'message_success'=>rawurlencode('Archivo eliminado.')], $base_url ) );
            exit;
        } else {
            wp_redirect( add_query_arg( ['tab'=>'upload', 'id'=>absint($_POST['employee_id']), 'message_error'=>rawurlencode('Archivo no encontrado.')], $base_url ) );
            exit;
        }
    }
}

// Enganchar la función al inicio de admin para procesar los POST
add_action( 'admin_init', 'hrm_handle_employees_post' );