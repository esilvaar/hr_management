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
    if ( $action === 'update_employee' && check_admin_referer( 'hrm_update_employee', 'hrm_update_employee_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );
        
        if ( ! hrm_can_edit_employee( $emp_id ) ) {
            hrm_redirect_with_message( $redirect_base, __( 'No tienes permisos para editar este perfil.', 'hr-management' ), 'error' );
        }

        $employee_obj = $db_emp->get( $emp_id );
        if ( ! $employee_obj ) wp_die('Empleado no encontrado');

        // 1. PROCESAR DATOS NORMALES (Campos editables)
        // ---------------------------------------------
        $current_user = wp_get_current_user();
        $is_admin = current_user_can( 'manage_options' );
        $is_supervisor = current_user_can( 'edit_hrm_employees' );
        $is_own = ( intval( $employee_obj->user_id ) === get_current_user_id() );

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

