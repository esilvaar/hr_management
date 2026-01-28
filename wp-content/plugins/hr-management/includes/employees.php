<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Obtiene los departamentos predefinidos para cada área gerencial
 */
function hrm_get_deptos_predefinidos_por_area( $area_gerencia ) {
    $mapeo = array(
        'Comercial' => array('Soporte', 'Ventas'),
        'Proyectos' => array('Desarrollo'),
        'Operaciones' => array('Administracion', 'Gerencia', 'Sistemas')
    );
    
    foreach ( $mapeo as $key => $deptos ) {
        if ( strtolower( $key ) === strtolower( $area_gerencia ) ) return $mapeo[ $key ];
    }
    return array();
}

/**
 * Renderiza la página de administración (Vista)
 */
function hrm_render_employees_admin_page() {
    if ( current_user_can( 'manage_options' ) ) {
        require_once HRM_PLUGIN_DIR . 'views/Administrador/employees-admin.php';
        return;
    }
    if ( current_user_can( 'view_hrm_admin_views' ) ) {
        require_once HRM_PLUGIN_DIR . 'views/Administrador/employees-admin.php';
        return;
    }

    $default_map = array(
        'supervisor' => HRM_PLUGIN_DIR . 'views/Administrador/employees-admin.php',
        'editor_vacaciones' => HRM_PLUGIN_DIR . 'views/employees-editor_vacaciones.php',
        'empleado' => HRM_PLUGIN_DIR . 'views/Empleado/employees-empleados.php',
    );
    $map = apply_filters( 'hrm_role_views_map', $default_map );
    $current_user = wp_get_current_user();
    if ( ! empty( $current_user->roles ) ) {
        foreach ( $current_user->roles as $r ) {
            if ( isset( $map[ $r ] ) && file_exists( $map[ $r ] ) ) {
                require_once $map[ $r ];
                return;
            }
        }
    }
    if ( current_user_can( 'view_hrm_employee_admin' ) && file_exists( HRM_PLUGIN_DIR . 'views/Empleado/employees-empleados.php' ) ) {
        require_once HRM_PLUGIN_DIR . 'views/Empleado/employees-empleados.php';
        return;
    }
    wp_die( 'No tienes permisos para ver esta página.', 'Acceso denegado', array( 'response' => 403 ) );
}

/**
 * Procesa los formularios (Controlador)
 */
function hrm_handle_employees_post() {
    if ( ! is_admin() || $_SERVER['REQUEST_METHOD'] !== 'POST' ) return;
    if ( ! isset( $_POST['hrm_action'] ) ) return;

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $cu = wp_get_current_user();
        error_log('[HRM-POST] hrm_handle_employees_post invoked. user_id=' . intval($cu->ID) . ' roles=' . json_encode($cu->roles) . ' REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '') . ' POST_hrm_action=' . (isset($_POST['hrm_action']) ? sanitize_text_field($_POST['hrm_action']) : '') );
    }

    $action = $_POST['hrm_action'];
    $db_emp  = new HRM_DB_Empleados();
    $db_docs = new HRM_DB_Documentos();
    $base_url = add_query_arg( ['page' => 'hrm-empleados'], admin_url( 'admin.php' ) );
    
    // Redirección inteligente
    $referrer_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : 'hrm-empleados';
    $is_own_profile = in_array( $referrer_page, array( 'hrm-mi-perfil', 'hrm-mi-perfil-info' ), true );
    $redirect_base = ( $is_own_profile ) ? add_query_arg( ['page' => $referrer_page], admin_url( 'admin.php' ) ) : $base_url;

    // =========================================================================
    // ACCIÓN A: ACTUALIZAR EMPLEADO
    // =========================================================================
    if ( $action === 'update_employee' ) {
        // Verificar nonce manualmente para evitar que WP haga wp_die() y devuelva 403 sin contexto
        $nonce = isset( $_POST['hrm_update_employee_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hrm_update_employee_nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hrm_update_employee' ) ) {
            // Registrar para depuración y redirigir con mensaje amigable
            error_log( 'HRM: update_employee - nonce verification failed for user_id=' . get_current_user_id() . ' ip=' . ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
            hrm_redirect_with_message( $redirect_base, __( 'Token de seguridad inválido. Intenta recargar la página e inténtalo de nuevo.', 'hr-management' ), 'error' );
        }

        $emp_id = absint( $_POST['employee_id'] );
        
        if ( ! hrm_can_edit_employee( $emp_id ) ) {
            hrm_redirect_with_message( $redirect_base, __( 'No tienes permisos para editar este perfil.', 'hr-management' ), 'error' );
        }

        $employee_obj = $db_emp->get( $emp_id );
        if ( ! $employee_obj ) wp_die('Empleado no encontrado');

        // 1. PROCESAR DATOS NORMALES (Campos editables)
        // ---------------------------------------------
        $current_user = wp_get_current_user();
        // Consider administrator, administrador_anaconda and users with view_hrm_admin_views as admin for update purposes
        $is_admin = current_user_can( 'manage_options' ) || current_user_can( 'view_hrm_admin_views' ) || in_array( 'administrador_anaconda', (array) $current_user->roles, true );
        $is_supervisor = current_user_can( 'edit_hrm_employees' );
        // Owner detection: linked by user_id OR matching email
        $is_own = ( intval( $employee_obj->user_id ) === get_current_user_id() ) || ( ! empty( $current_user->user_email ) && ! empty( $employee_obj->email ) && strtolower( $current_user->user_email ) === strtolower( $employee_obj->email ) );

        // Determinar campos permitidos (Misma lógica que la vista para seguridad)
        $allowed_fields = array();
        if( $is_admin ) {
            $allowed_fields = array('nombre','apellido','telefono','email','departamento','puesto','estado','anos_acreditados_anteriores','fecha_ingreso','tipo_contrato','salario','area_gerencia');
        } elseif( $is_supervisor && !$is_own ) {
             $allowed_fields = array('nombre','apellido','telefono','email','departamento','puesto','anos_acreditados_anteriores','fecha_ingreso');
        } elseif( $is_own ) {
             $allowed_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
        }

        $update_data = array();
        foreach ( $allowed_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                if($field === 'email') $update_data['email'] = sanitize_email( $_POST['email'] );
                elseif($field === 'salario') $update_data['salario'] = floatval( $_POST['salario'] );
                elseif($field === 'anos_acreditados_anteriores') $update_data['anos_acreditados_anteriores'] = floatval( $_POST['anos_acreditados_anteriores'] );
                else $update_data[ $field ] = sanitize_text_field( $_POST[ $field ] );
            }
        }

        // Recálculo automático de años
        if ( isset( $update_data['fecha_ingreso'] ) || isset( $update_data['anos_acreditados_anteriores'] ) ) {
            $fi = isset( $update_data['fecha_ingreso'] ) ? $update_data['fecha_ingreso'] : $employee_obj->fecha_ingreso;
            $anos_empresa = 0;
            if ( $fi && $fi !== '0000-00-00' ) {
                $d1 = new DateTime( $fi ); $d2 = new DateTime();
                $diff = $d2->diff( $d1 );
                $anos_empresa = $diff->y;
            }
            $update_data['anos_en_la_empresa'] = $anos_empresa;
            $previos = isset( $update_data['anos_acreditados_anteriores'] ) ? $update_data['anos_acreditados_anteriores'] : ($employee_obj->anos_acreditados_anteriores ?? 0);
            $update_data['anos_totales_trabajados'] = $previos + $anos_empresa;
        }

        // Ejecutar actualización de datos
        $db_result = false;
        if(!empty($update_data)) {
            $db_result = $db_emp->update( $emp_id, $update_data );
        }

        // 2. PROCESAR CAMBIO DE CONTRASEÑA (Integrado)
        // --------------------------------------------
        $password_changed = false;
        $email_sent = false;

        if ( isset( $_POST['hrm_new_password'] ) && ! empty( $_POST['hrm_new_password'] ) ) {
            $new_pass = sanitize_text_field( $_POST['hrm_new_password'] );
            
            // Validar longitud
            if ( strlen( $new_pass ) >= 8 ) {
                $wp_user_id = intval( $employee_obj->user_id );
                
                // Cambiar pass en WordPress
                wp_set_password( $new_pass, $wp_user_id );
                $password_changed = true;

                // Guardar pass temporal para mostrar (solo al admin que lo cambió)
                set_transient( 'hrm_temp_new_pass_' . get_current_user_id(), $new_pass, 60 );

                // Enviar Correo si se solicitó
                if ( isset( $_POST['hrm_notify_user'] ) && $_POST['hrm_notify_user'] == '1' ) {
                    $user_info = get_userdata( $wp_user_id );
                    $to = $user_info->user_email;
                    $subject = 'Credenciales actualizadas - Intranet';
                    $message = "Hola " . $employee_obj->nombre . ",\r\n\r\n";
                    $message .= "Se ha actualizado tu contraseña de acceso.\r\n";
                    $message .= "Usuario: " . $user_info->user_login . "\r\n";
                    $message .= "Nueva Contraseña: " . $new_pass . "\r\n\r\n";
                    $message .= "Accede aquí: " . wp_login_url() . "\r\n";
                    
                    $headers = array('Content-Type: text/plain; charset=UTF-8');
                    $sent = wp_mail( $to, $subject, $message, $headers );
                    if($sent) $email_sent = true;
                }
            }
        }

        // 3. FINALIZAR Y REDIRECCIONAR
        // ----------------------------
        do_action( 'hrm_after_employee_update', $emp_id ); // Hooks externos

        $msgs = array();
        if($db_result) $msgs[] = 'Datos actualizados.';
        if($password_changed) $msgs[] = 'Contraseña cambiada.';

        // Construir URL de redirección
        $args = array(
            'id' => $emp_id, 
            'tab' => 'profile'
        );
        
        if ( $password_changed ) $args['password_changed'] = '1';
        if ( $email_sent ) $args['email_sent'] = '1';

        if ( $db_result || $password_changed ) {
            $args['message_success'] = rawurlencode( implode(' ', $msgs) );
            wp_redirect( add_query_arg( $args, $redirect_base ) );
            exit;
        } else {
            // Si no hubo cambios
            wp_redirect( add_query_arg( $args, $redirect_base ) );
            exit;
        }
    }

    // =========================================================================
    // ACCIÓN: TOGGLE ESTADO
    // =========================================================================
    if ( $action === 'toggle_employee_status' && check_admin_referer( 'hrm_toggle_employee_status', 'hrm_toggle_status_nonce' ) ) {
        if ( ! current_user_can( 'edit_hrm_employees' ) ) wp_die('Sin permisos');
        
        $emp_id = absint( $_POST['employee_id'] );
        $nuevo_estado = ( intval($_POST['current_estado']) === 1 ) ? 0 : 1;
        
        global $wpdb;
        $wpdb->update( 
            $wpdb->prefix . 'rrhh_empleados', 
            array('estado' => $nuevo_estado), 
            array('id_empleado' => $emp_id) 
        );
        
        wp_redirect( add_query_arg( ['id'=>$emp_id, 'tab'=>'profile', 'message_success'=>'Estado actualizado'], $redirect_base ) );
        exit;
    }

    // =========================================================================
    // ACCIÓN: UPLOAD AVATAR
    // =========================================================================
    if ( $action === 'upload_avatar' && check_admin_referer( 'hrm_upload_avatar', 'hrm_upload_avatar_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );
        if ( ! empty( $_FILES['avatar'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $upload = wp_handle_upload( $_FILES['avatar'], ['test_form' => false] );
            if ( isset( $upload['file'] ) ) {
                $attach_id = wp_insert_attachment( array(
                    'guid' => $upload['url'], 
                    'post_mime_type' => $upload['type'],
                    'post_title' => basename($upload['file']),
                    'post_content' => '',
                    'post_status' => 'inherit'
                ), $upload['file'] );
                
                $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
                wp_update_attachment_metadata( $attach_id, $attach_data );
                
                $emp = $db_emp->get($emp_id);
                update_user_meta( $emp->user_id, 'hrm_avatar', $upload['url'] );
                update_user_meta( $emp->user_id, 'simple_local_avatar', array('full'=>$upload['url']) );
            }
        }
        wp_redirect( add_query_arg( ['id'=>$emp_id, 'tab'=>'profile'], $redirect_base ) );
        exit;
    }

    // =========================================================================
    // ACCIÓN: UPLOAD DOCUMENTS (desde formulario de documentos)
    // =========================================================================
    if ( $action === 'upload_document' ) {
        // Esperamos un nonce llamado hrm_upload_nonce
        $nonce = isset( $_POST['hrm_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hrm_upload_nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hrm_upload_file' ) ) {
            wp_send_json_error( array( 'message' => 'Token de seguridad inválido.' ) );
        }

        // Validar employee_id
        $emp_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
        if ( ! $emp_id ) {
            wp_send_json_error( array( 'message' => 'Empleado no especificado.' ) );
        }

        // Cargar clases DB
        hrm_ensure_db_classes();
        $db_emp  = new HRM_DB_Empleados();
        $db_docs = new HRM_DB_Documentos();

        $employee = $db_emp->get( $emp_id );
        if ( ! $employee ) {
            wp_send_json_error( array( 'message' => 'Empleado no encontrado.' ) );
        }

        // Permisos: admin/supervisor o el propio usuario vinculado
        $can_manage = current_user_can( 'manage_options' ) || current_user_can( 'edit_hrm_employees' ) || current_user_can( 'view_hrm_admin_views' );
        $is_owner = ( intval( $employee->user_id ) === get_current_user_id() );
        if ( ! ( $can_manage || $is_owner ) ) {
            wp_send_json_error( array( 'message' => 'No tienes permisos para subir documentos para este empleado.' ), 403 );
        }

        // Validar inputs
        $tipo_input = isset( $_POST['tipo_documento'] ) ? sanitize_text_field( wp_unslash( $_POST['tipo_documento'] ) ) : '';
        $anio_input = isset( $_POST['anio_documento'] ) ? sanitize_text_field( wp_unslash( $_POST['anio_documento'] ) ) : date( 'Y' );

        if ( empty( $tipo_input ) ) {
            wp_send_json_error( array( 'message' => 'Tipo de documento no especificado.' ) );
        }

        if ( empty( $_FILES['archivos_subidos'] ) ) {
            wp_send_json_error( array( 'message' => 'No se detectaron archivos para subir.' ) );
        }

        // Debug: listar nombres y tmp de archivos recibidos
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $incoming_names = is_array( $_FILES['archivos_subidos']['name'] ) ? array_values( $_FILES['archivos_subidos']['name'] ) : array( $_FILES['archivos_subidos']['name'] );
            $incoming_tmps  = is_array( $_FILES['archivos_subidos']['tmp_name'] ) ? array_values( $_FILES['archivos_subidos']['tmp_name'] ) : array( $_FILES['archivos_subidos']['tmp_name'] );
            error_log( '[HRM-UPLOAD] incoming FILES names=' . json_encode( $incoming_names ) . ' tmp=' . json_encode( $incoming_tmps ) );
        }

        // Preparar ruta base de uploads personalizada
        $upload = wp_upload_dir();
        $base_dir = untrailingslashit( $upload['basedir'] );
        $base_url = untrailingslashit( $upload['baseurl'] );

        // Normalizar partes de la ruta
        $year = preg_replace('/[^0-9]/', '', $anio_input);
        if ( empty( $year ) ) $year = date('Y');
        $rut_raw = isset( $employee->rut ) ? (string) $employee->rut : '';
        $rut_slug = sanitize_file_name( preg_replace('/[^A-Za-z0-9\-]/', '_', $rut_raw) );
        $tipo_name = '';
        // Si tipo es numérico, intentar resolver nombre
        if ( is_numeric( $tipo_input ) ) {
            $all_types = $db_docs->get_all_types();
            $tipo_id = intval( $tipo_input );
            $tipo_name = isset( $all_types[ $tipo_id ] ) ? $all_types[ $tipo_id ] : (string) $tipo_id;
        } else {
            $tipo_name = (string) $tipo_input;
        }
        $tipo_slug = sanitize_file_name( sanitize_title( $tipo_name ) );
        if ( empty( $tipo_slug ) ) $tipo_slug = 'otros';

        $target_dir = wp_normalize_path( $base_dir . '/hrm_docs/' . $year . '/' . $rut_slug . '/' . $tipo_slug );

        // Registrar para depuración (mostrar valores que construyen la ruta)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[HRM-UPLOAD] base_dir=' . $base_dir . ' base_url=' . $base_url . ' year=' . $year . ' rut_slug=' . $rut_slug . ' tipo_slug=' . $tipo_slug . ' target_dir=' . $target_dir );
        }

        // Crear directorios si no existen
        if ( ! file_exists( $target_dir ) ) {
            if ( ! wp_mkdir_p( $target_dir ) ) {
                wp_send_json_error( array( 'message' => 'No se pudo crear el directorio de destino.' ) );
            }
            // crear index.html placeholder
            $index = $target_dir . '/index.html';
            if ( ! file_exists( $index ) ) @file_put_contents( $index, '<!doctype html><title></title>' );
        }

        $results = array();

        // Procesar cada archivo subido
        $files = $_FILES['archivos_subidos'];
        $count = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
        for ( $i = 0; $i < $count; $i++ ) {
            $orig_name = isset( $files['name'][ $i ] ) ? $files['name'][ $i ] : '';
            $tmp_name  = isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '';
            $error     = isset( $files['error'][ $i ] ) ? $files['error'][ $i ] : 1;
            $type_mime = isset( $files['type'][ $i ] ) ? $files['type'][ $i ] : '';

            if ( $error !== UPLOAD_ERR_OK ) {
                $results[] = array( 'file' => $orig_name, 'success' => false, 'message' => 'Error al subir archivo.' );
                continue;
            }

            // Solo permitir PDF por seguridad (coincide con validación cliente)
            if ( strtolower( $type_mime ) !== 'application/pdf' && pathinfo( $orig_name, PATHINFO_EXTENSION ) !== 'pdf' ) {
                $results[] = array( 'file' => $orig_name, 'success' => false, 'message' => 'Formato no permitido. Solo PDF.' );
                continue;
            }

            // Normalizar y sanitizar nombre, removiendo sufijos tipo timestamp si aplica
            $normalized_name = function_exists( 'hrm_normalize_uploaded_filename' ) ? hrm_normalize_uploaded_filename( $orig_name ) : $orig_name;
            $safe_name = sanitize_file_name( $normalized_name );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[HRM-UPLOAD] filename normalization applied: orig=' . $orig_name . ' normalized=' . $normalized_name . ' safe=' . $safe_name );
            }

            $dest_path = $target_dir . '/' . $safe_name;
            $counter = 0;
            while ( file_exists( $dest_path ) ) {
                $counter++;
                $dest_path = $target_dir . '/' . pathinfo( $safe_name, PATHINFO_FILENAME ) . '-' . $counter . '.' . pathinfo( $safe_name, PATHINFO_EXTENSION );
            }

            // Log antes de mover
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[HRM-UPLOAD] preparing move - orig_name=' . $orig_name . ' tmp=' . $tmp_name . ' mime=' . $type_mime . ' dest=' . $dest_path );
            }

            // Mover archivo
            $moved = @move_uploaded_file( $tmp_name, $dest_path );
            if ( ! $moved ) {
                // intentar copy como fallback
                $copied = @copy( $tmp_name, $dest_path );
                if ( ! $copied ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( '[HRM-UPLOAD] move AND copy FAILED for ' . $orig_name . ' tmp=' . $tmp_name . ' dest=' . $dest_path );
                    }
                    $results[] = array( 'file' => $orig_name, 'success' => false, 'message' => 'No se pudo mover el archivo.' );
                    continue;
                } else {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( '[HRM-UPLOAD] copy fallback SUCCESS for ' . $orig_name . ' dest=' . $dest_path );
                    }
                }
            } else {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[HRM-UPLOAD] move_uploaded_file SUCCESS for ' . $orig_name . ' dest=' . $dest_path );
                }
            }

            // Construir URL pública (determinístico)
            $file_name = basename( $dest_path );
            $subpath = 'hrm_docs/' . $year . '/' . $rut_slug . '/' . $tipo_slug . '/' . $file_name;
            $file_url = untrailingslashit( $base_url ) . '/' . str_replace( '\\', '/', $subpath );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[HRM-UPLOAD] file_url=' . $file_url );
            }

            // Registrar en la base de datos
            $create_ok = $db_docs->create( array(
                'rut'  => $employee->rut,
                'tipo' => $tipo_input,
                'anio' => intval( $year ),
                'nombre' => $safe_name,
                'url'  => $file_url,
            ) );

            if ( $create_ok ) {
                $results[] = array( 'file' => $orig_name, 'success' => true, 'url' => $file_url );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    $final_exists = file_exists( $dest_path );
                    error_log( '[HRM-UPLOAD] final_file_exists dest=' . $dest_path . ' exists=' . ( $final_exists ? 'YES' : 'NO' ) . ' real=' . ( $final_exists ? realpath( $dest_path ) : '' ) . ' size=' . ( $final_exists ? filesize( $dest_path ) : 0 ) . ' counter=' . intval( $counter ) );
                    error_log( '[HRM-UPLOAD] DB create ok for rut=' . $employee->rut . ' tipo=' . $tipo_input . ' name=' . $safe_name . ' url=' . $file_url );
                }
            } else {
                // Si falló la DB, eliminar el archivo físicamente
                @unlink( $dest_path );
                $results[] = array( 'file' => $orig_name, 'success' => false, 'message' => 'Error al guardar en base de datos.' );
            }
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    // =========================================================================
    // ACCIÓN: DELETE AVATAR
    // =========================================================================
    if ( $action === 'delete_avatar' && check_admin_referer( 'hrm_delete_avatar', 'hrm_delete_avatar_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );
        $emp = $db_emp->get($emp_id);
        delete_user_meta( $emp->user_id, 'hrm_avatar' );
        delete_user_meta( $emp->user_id, 'simple_local_avatar' );
        wp_redirect( add_query_arg( ['id'=>$emp_id, 'tab'=>'profile'], $redirect_base ) );
        exit;
    }
    
    // --- ACCIÓN B: Crear Empleado + Usuario WP (migrado del template a handler central) ---
    elseif ( $action === 'create_employee' && check_admin_referer( 'hrm_create_employee', 'hrm_create_employee_nonce' ) ) {
        // Solo usuarios con capacidad de administrar empleados pueden crear (admin/supervisor)
        if ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'edit_hrm_employees' ) ) ) {
            hrm_redirect_with_message( $redirect_base, 'No tienes permisos para crear empleados.', 'error' );
        }

        // 1. Recoger datos
        $rut          = sanitize_text_field( $_POST['rut'] ?? '' );
        $email        = sanitize_email( $_POST['email'] ?? '' );
        $nombre       = sanitize_text_field( $_POST['nombre'] ?? '' );
        $apellido     = sanitize_text_field( $_POST['apellido'] ?? '' );
        $fecha_ingreso= sanitize_text_field( $_POST['fecha_ingreso'] ?? '' );
        $rol_wp       = sanitize_text_field( $_POST['rol_usuario_wp'] ?? 'subscriber' );

        // 2. Detectar Checkbox
        $crear_wp = isset($_POST['crear_usuario_wp']); 
        // Si se va a crear usuario y no se seleccionó rol, forzar 'subscriber'
        if ($crear_wp && empty($rol_wp)) {
            $rol_wp = 'subscriber';
        }

        // 3. Validación de campos obligatorios
        $missing = array();
        if ( $rut === '' ) $missing[] = 'RUT';
        if ( $nombre === '' ) $missing[] = 'Nombres';
        if ( $apellido === '' ) $missing[] = 'Apellidos';
        if ( $email === '' || ! is_email( $email ) ) $missing[] = 'Email válido';

        if ( ! empty( $missing ) ) {
            hrm_redirect_with_message( $redirect_base, 'Faltan campos obligatorios: ' . implode( ', ', $missing ), 'error' );
        }

        $wp_user_id = null;
        $error_wp   = '';

        // 4. Lógica de Creación de Usuario WP (si se solicitó)
        if ( $crear_wp ) {
            // Solo usuarios con capacidad de crear usuarios pueden crear cuentas WP
            if ( ! current_user_can( 'create_users' ) ) {
                $error_wp = 'No tienes permisos para crear usuarios en WordPress.';
            }

            // Si no hay error, procesar creación de usuario
            if ( empty( $error_wp ) ) {
                // Limpieza vital: "12.345.678-9" -> "12345678-9"
                $username_clean = str_replace([ '.', ' ', ',' ], '', trim( $rut ) );

                if ( empty( $email ) || ! is_email( $email ) ) {
                    $error_wp = 'El correo es inválido o está vacío.';
                } elseif ( email_exists( $email ) ) {
                    $error_wp = 'El correo ya existe en WordPress.';
                } elseif ( username_exists( $username_clean ) ) {
                    $error_wp = "El usuario (RUT: $username_clean) ya existe en WordPress.";
                } else {
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
                        $error_wp = 'Error WP: ' . $new_id->get_error_message();
                    } else {
                        $wp_user_id = $new_id;
                        $sent = false;
                        if ( function_exists( 'hrm_send_user_credentials_email' ) ) {
                            $sent = hrm_send_user_credentials_email( $new_id, $username_clean, $password, $email );
                        }
                        if ( ! $sent && apply_filters( 'hrm_send_new_user_notification', false, $new_id ) ) {
                            wp_new_user_notification( $new_id, null, 'both' );
                        }
                    }
                }
            }
        }

        // 5. Guardar en Base de Datos de Empleados
        if ( $error_wp ) {
            // Si hubo error en la creación WP, retrocedemos
            if ( $wp_user_id ) wp_delete_user( $wp_user_id );
            hrm_redirect_with_message( $redirect_base, $error_wp, 'error' );
        } else {
            // Construir data con todos los campos (campos opcionales pueden quedar vacíos)
            $data = array(
                'rut' => $rut,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'fecha_ingreso' => $fecha_ingreso,
                'telefono' => sanitize_text_field( $_POST['telefono'] ?? '' ),
                'fecha_nacimiento' => sanitize_text_field( $_POST['fecha_nacimiento'] ?? '' ),
                'departamento' => sanitize_text_field( $_POST['departamento'] ?? '' ),
                'puesto' => sanitize_text_field( $_POST['puesto'] ?? '' ),
                'tipo_contrato' => sanitize_text_field( $_POST['tipo_contrato'] ?? '' ),
                'salario' => isset($_POST['salario']) && $_POST['salario'] !== '' ? floatval( $_POST['salario'] ) : null,
                'estado' => 1,
            );

            // Si creamos usuario, vinculamos el ID
            if ( $wp_user_id ) {
                $data['user_id'] = $wp_user_id;
            }

            if ( $db_emp->create( $data ) ) {
                $msg_text = $wp_user_id ? sprintf( __( 'Empleado creado (usuario WP: %s)', 'hr-management' ), $username_clean ) : __( 'Empleado creado correctamente.', 'hr-management' );
                // Redirigir de vuelta al formulario de creación para dejar el formulario limpio y mostrar el mensaje ahí
                hrm_redirect_with_message( add_query_arg( ['page' => 'hrm-empleados', 'tab' => 'new'], admin_url( 'admin.php' ) ), $msg_text, 'success' );
            } else {
                // Rollback: Si falla la BD local, borramos el usuario WP para no dejar basura
                if ( $wp_user_id ) wp_delete_user( $wp_user_id );
                hrm_redirect_with_message( $redirect_base, 'Error SQL al guardar empleado. Verifica que el RUT no esté duplicado en la lista.', 'error' );
            }
        }
    }

    // =========================================================================
    // ACCIÓN: TOGGLE ESTADO
    // =========================================================================
    if ( $action === 'toggle_employee_status' && check_admin_referer( 'hrm_toggle_employee_status', 'hrm_toggle_status_nonce' ) ) {
        if ( ! current_user_can( 'edit_hrm_employees' ) ) wp_die('Sin permisos');
        
        $emp_id = absint( $_POST['employee_id'] );
        $nuevo_estado = ( intval($_POST['current_estado']) === 1 ) ? 0 : 1;
        
        global $wpdb;
        $wpdb->update( 
            $wpdb->prefix . 'rrhh_empleados', 
            array('estado' => $nuevo_estado), 
            array('id_empleado' => $emp_id) 
        );
        
        wp_redirect( add_query_arg( ['id'=>$emp_id, 'tab'=>'profile', 'message_success'=>'Estado actualizado'], $redirect_base ) );
        exit;
    }
}
add_action( 'admin_init', 'hrm_handle_employees_post' );

