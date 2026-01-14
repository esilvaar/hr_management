<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. INICIALIZACIÓN
$db_emp  = new HRM_DB_Empleados();   
$db_docs = new HRM_DB_Documentos(); 

$tab = sanitize_key( $_GET['tab'] ?? 'list' );
$id  = absint( $_GET['id'] ?? 0 );

$message_success = '';
$message_error   = '';

// 2. CONTROLADOR (Procesamiento de Formularios)
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $action = $_POST['hrm_action'] ?? '';

    // --- ACCIÓN A: Actualizar Empleado ---
    if ( $action === 'update_employee' && check_admin_referer( 'hrm_update_employee', 'hrm_update_employee_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );

        // Verificar permisos (igual que en el handler central): admin/editor o el propio usuario
        $employee_obj = $db_emp->get( $emp_id );
        $current_user_id = get_current_user_id();

        $allowed = false;
        if ( current_user_can( 'edit_hrm_employees' ) || current_user_can( 'manage_options' ) || current_user_can( 'manage_hrm_employees' ) ) {
            $allowed = true;
        } elseif ( current_user_can( 'view_hrm_own_profile' ) && $employee_obj && intval( $employee_obj->user_id ) === $current_user_id ) {
            $allowed = true;
        }

        if ( ! $allowed ) {
            $message_error = 'No tienes permisos para editar este perfil.';
        } else {
            if ( $db_emp->update( $emp_id, $_POST ) ) {
                $message_success = 'Datos actualizados correctamente.';
                $employee = $db_emp->get( $emp_id ); // Recargar datos
            } else {
                $message_error = 'No se realizaron cambios.';
            }
        }
    }
    
    // --- ACCIÓN B: Crear Empleado + Usuario WP (VERSIÓN DEFINITIVA) ---
    elseif ( $action === 'create_employee' && check_admin_referer( 'hrm_create_employee', 'hrm_create_employee_nonce' ) ) {
        
        // 1. Recoger datos
        $rut          = sanitize_text_field( $_POST['rut'] ?? '' );
        $email        = sanitize_email( $_POST['email'] ?? '' );
        $nombre       = sanitize_text_field( $_POST['nombre'] ?? '' );
        $apellido     = sanitize_text_field( $_POST['apellido'] ?? '' );
        $fecha_ingreso= sanitize_text_field( $_POST['fecha_ingreso'] ?? '' );
        $rol_wp       = sanitize_text_field( $_POST['rol_usuario_wp'] ?? 'subscriber' );

        // 2. Detectar Checkbox
        $crear_wp = isset($_POST['crear_usuario_wp']); 

        // 3. Validación de campos obligatorios
        $missing = array();
        if ( $rut === '' ) $missing[] = 'RUT';
        if ( $nombre === '' ) $missing[] = 'Nombres';
        if ( $apellido === '' ) $missing[] = 'Apellidos';
        if ( $email === '' || ! is_email( $email ) ) $missing[] = 'Email válido';
        if ( $fecha_ingreso === '' ) $missing[] = 'Fecha de ingreso';

        // Validar formato de fecha (esperamos YYYY-MM-DD)
        if ( $fecha_ingreso !== '' ) {
            $dt = DateTime::createFromFormat( 'Y-m-d', $fecha_ingreso );
            $valid_date = $dt && $dt->format('Y-m-d') === $fecha_ingreso;
            if ( ! $valid_date ) {
                $missing[] = 'Fecha de ingreso (formato inválido)';
            }
        }

        if ( ! empty( $missing ) ) {
            $message_error = 'Faltan campos obligatorios: ' . implode( ', ', $missing );
        }
        
        $wp_user_id = null;
        $error_wp   = '';

        // 3. Lógica de Creación de Usuario WP
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

        // 4. Guardar en Base de Datos de Empleados
        if ( $error_wp ) {
            $message_error = $error_wp; // Si falló WP, mostramos error y no guardamos nada
        } elseif ( empty( $message_error ) ) {
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
                $msg_extra = $wp_user_id ? " (Usuario web creado: $username_clean)" : "";
                
                // Redirigir para limpiar formulario (Patrón PRG)
                wp_safe_redirect( add_query_arg( ['page' => 'hrm-empleados', 'tab' => 'list', 'msg' => 'created'], admin_url( 'admin.php' ) ) );
                exit;
            } else {
                // Rollback: Si falla la BD local, borramos el usuario WP para no dejar basura
                if ( $wp_user_id ) wp_delete_user( $wp_user_id );
                $message_error = 'Error SQL al guardar empleado. Verifica que el RUT no esté duplicado en la lista.';
            }
        }
    }

    // --- ACCIÓN C: Subir Documentos ---
    elseif ( $action === 'upload_document' && check_admin_referer( 'hrm_upload_file', 'hrm_upload_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );
        $tipo   = wp_kses_post( trim( $_POST['tipo_documento'] ?? 'Generico' ) );
        $anio_raw = isset($_POST['anio_documento']) ? trim($_POST['anio_documento']) : '';
        $anio   = ! empty($anio_raw) ? (int)$anio_raw : 0;
        $empleado_obj = $db_emp->get( $emp_id );
        $files = $_FILES['archivos_subidos'] ?? [];
        
        // Debug: Log del valor recibido
        error_log('DEBUG anio_documento: ' . var_export($anio_raw, true) . ' | anio: ' . $anio);

        if ( ! $empleado_obj ) {
            $message_error = 'Error: Empleado no encontrado.';
        } elseif ( empty( $files ) || empty( $files['name'][0] ) ) {
            $message_error = 'No seleccionaste archivos.';
        } elseif ( empty($anio_raw) || $anio === 0 ) {
            $message_error = 'Debes seleccionar el año del documento.';
        } elseif ( $anio < 1900 || $anio > (int)date('Y') + 1 ) {
            $message_error = 'El año seleccionado no es válido.';
        } else {
            $upload_dir_info = wp_upload_dir();
            $base_dir        = $upload_dir_info['basedir'] . '/hrm_docs';
            $base_url        = $upload_dir_info['baseurl'] . '/hrm_docs';

            $folder_year = $anio;
            $folder_user = sanitize_file_name( $empleado_obj->rut );
            $folder_type = sanitize_file_name( $tipo );

            $relative_path    = '/' . $folder_year . '/' . $folder_user . '/' . $folder_type;
            $final_target_dir = $base_dir . $relative_path;
            $final_target_url = $base_url . $relative_path;

            // Crear la carpeta si no existe
            if ( ! file_exists( $final_target_dir ) ) {
                $mkdir_result = wp_mkdir_p( $final_target_dir );
                if ( ! $mkdir_result ) {
                    $message_error = 'No se pudo crear la carpeta para guardar los documentos.';
                } else {
                    $count_ok = 0; 
                    $count_err = 0;
                    $total_files = count( $files['name'] );

                    for ( $i = 0; $i < $total_files; $i++ ) {
                        if ( $files['error'][$i] !== UPLOAD_ERR_OK ) { $count_err++; continue; }
                        
                        $filename = sanitize_file_name( $files['name'][$i] );
                        $final_filename = file_exists( $final_target_dir . '/' . $filename ) ? time() . '_' . $filename : $filename;
                        
                        $file_path = $final_target_dir . '/' . $final_filename;
                        $file_url  = $final_target_url . '/' . $final_filename;

                        if ( move_uploaded_file( $files['tmp_name'][$i], $file_path ) ) {
                            $saved = $db_docs->create([
                                'rut'    => $empleado_obj->rut,
                                'tipo'   => $tipo,
                                'anio'   => $anio,
                                'nombre' => $final_filename,
                                'url'    => $file_url 
                            ]);
                            $saved ? $count_ok++ : $count_err++;
                        } else {
                            $count_err++;
                        }
                    }
                    if ( $count_ok > 0 ) $message_success = "Se subieron $count_ok archivo(s) en la carpeta del año $folder_year.";
                    if ( $count_err > 0 ) $message_error = "Fallaron $count_err archivo(s).";
                }
            } else {
                // La carpeta ya existe, guardar archivos directamente
                $count_ok = 0; 
                $count_err = 0;
                $total_files = count( $files['name'] );

                for ( $i = 0; $i < $total_files; $i++ ) {
                    if ( $files['error'][$i] !== UPLOAD_ERR_OK ) { $count_err++; continue; }
                    
                    $filename = sanitize_file_name( $files['name'][$i] );
                    $final_filename = file_exists( $final_target_dir . '/' . $filename ) ? time() . '_' . $filename : $filename;
                    
                    $file_path = $final_target_dir . '/' . $final_filename;
                    $file_url  = $final_target_url . '/' . $final_filename;

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
                if ( $count_ok > 0 ) $message_success = "Se subieron $count_ok archivo(s) en la carpeta del año $folder_year.";
                if ( $count_err > 0 ) $message_error = "Fallaron $count_err archivo(s).";
            }
        }
    }

    // --- ACCIÓN D: Eliminar Documento ---
    elseif ( $action === 'delete_document' && check_admin_referer( 'hrm_delete_file', 'hrm_delete_nonce' ) ) {
        $doc_id = absint( $_POST['doc_id'] );
        $doc    = $db_docs->get( $doc_id );

        if ( $doc ) {
            $upload_dir = wp_upload_dir();
            $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $doc->url );

            if ( file_exists( $file_path ) ) unlink( $file_path ); 
            $db_docs->delete( $doc_id ); 
            
            $message_success = 'Archivo eliminado correctamente.';
        } else {
            $message_error = 'El archivo no existe.';
        }
    }
}

// 3. OBTENCIÓN DE DATOS
$employees = [];
$employee  = null;
$documents = [];

// Listas configurables para poblar selects (pueden extenderse mediante filtros)
$hrm_departamentos = apply_filters( 'hrm_departamentos', array( 'Soporte', 'Desarrollo', 'Administracion', 'Ventas', 'Gerencia', 'Sistemas' ) );
$hrm_puestos = apply_filters( 'hrm_puestos', array(
    'Gerente',
    'Ingeniero en Sistemas',
    'Ingeniero de Soporte',
    'Administrativo(a) Contable',
    'Asistente Comercial',
    'Desarrollador de Software',
    'Diseñador Gráfico'
) );
$hrm_tipos_documento = apply_filters( 'hrm_tipos_documento', array( 'Contrato', 'Liquidaciones', 'Licencia', 'Induccion' ) );
$hrm_tipos_contrato = apply_filters( 'hrm_tipos_contrato', array( 'Indefinido', 'Plazo Fijo', 'Por Proyecto' ) );

// Detectar filtro de estado (activos/inactivos)
$show_inactive = isset( $_GET['show_inactive'] ) && $_GET['show_inactive'] === '1';

if ( $tab === 'list' ) {
    // Por defecto mostrar solo activos (estado=1), a menos que se solicite inactivos
    $employees = $show_inactive ? $db_emp->get_by_status( 0 ) : $db_emp->get_by_status( 1 );
} elseif ( $id ) {
    $employee = $db_emp->get( $id );
    if ( $employee && $tab === 'upload' ) {
        $documents = $db_docs->get_by_rut( $employee->rut );
    }
}

// Preparar lista de empleados para el selector
$all_emps = array();
if ( $tab !== 'list' ) {
    $all_emps = $db_emp->get_all();
}
?>

<div class="wrap hrm-admin-wrap">
    
    <div class="hrm-admin-layout">
        <?php hrm_get_template_part( 'partials/sidebar-loader' ); ?>
        
        <main class="hrm-content">
            
            <?php if ( ! empty( $message_success ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?= esc_html( $message_success ) ?></p></div>
            <?php endif; ?>

            <?php if ( ! empty( $message_error ) ) : ?>
                <div class="notice notice-error is-dismissible"><p><?= esc_html( $message_error ) ?></p></div>
            <?php endif; ?>

            <?php if ( isset($_GET['msg']) && $_GET['msg'] == 'created' ) : ?>
                <div class="notice notice-success is-dismissible"><p>Empleado creado correctamente.</p></div>
            <?php endif; ?>

            <!-- Selector de Empleado (usando partial para mantener consistencia) -->
            <?php if ( $tab !== 'list' && $tab !== 'new' ) : ?>
                <?php hrm_get_template_part( 'employee-selector', '', compact( 'all_emps', 'tab', 'id', 'db_emp' ) ); ?>
            <?php endif; ?>

            <div class="hrm-admin-panel">

            <!-- Renderizar vista según tab seleccionado -->
            <?php if ( $tab === 'list' ) : ?>

                <?php hrm_get_template_part( 'employees-list', '', compact( 'employees', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( $tab === 'profile' && $id ) : ?>

                <?php hrm_get_template_part( 'employees-detail', '', compact( 'employee', 'hrm_departamentos', 'hrm_puestos', 'hrm_tipos_contrato', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( $tab === 'upload' && $id ) : ?>

                <?php hrm_get_template_part( 'employees-documents', '', compact( 'employee', 'documents', 'hrm_tipos_documento', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( $tab === 'new' ) : ?>

                <?php hrm_get_template_part( 'employees-create', '', compact( 'hrm_departamentos', 'hrm_puestos', 'hrm_tipos_contrato', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( ( $tab === 'profile' || $tab === 'upload' ) && ! $id ) : ?>
                <div class="d-flex align-items-center justify-content-center" style="min-height: 400px;">
                    <h2 style="font-size: 24px; color: #856404; text-align: center; max-width: 500px;"><strong>⚠️ Atención:</strong> Por favor selecciona un usuario para continuar.</h2>
                </div>
            <?php endif; ?>

            </div>
        </main>
    </div>
</div>
