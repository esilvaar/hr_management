<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. INICIALIZACIÓN
$db_emp  = new HRM_DB_Empleados();   
$db_docs = new HRM_DB_Documentos(); 

$tab = sanitize_key( $_GET['tab'] ?? 'list' );
$id  = absint( $_GET['id'] ?? 0 );

$message_success = '';
$message_error   = '';

// Mostrar mensajes enviados por redirects centrales (hrm_redirect_with_message)
if ( isset( $_GET['message_success'] ) && ! empty( $_GET['message_success'] ) ) {
    $message_success = rawurldecode( sanitize_text_field( wp_unslash( $_GET['message_success'] ) ) );
}
if ( isset( $_GET['message_error'] ) && ! empty( $_GET['message_error'] ) ) {
    $message_error = rawurldecode( sanitize_text_field( wp_unslash( $_GET['message_error'] ) ) );
}

// 2. CONTROLADOR (Procesamiento de Formularios)
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $action = $_POST['hrm_action'] ?? '';
    error_log( 'HRM Controller - Action: ' . $action );
    error_log( 'HRM Controller - POST data: ' . print_r( $_POST, true ) );

    // --- ACCIÓN A: Actualizar Empleado ---
    if ( $action === 'update_employee' && check_admin_referer( 'hrm_update_employee', 'hrm_update_employee_nonce' ) ) {
        error_log( 'HRM Controller - Nonce OK for update_employee' );
        $emp_id = absint( $_POST['employee_id'] );
        error_log( 'HRM Controller - Employee ID: ' . $emp_id );

        // Verificar permisos (igual que en el handler central): admin/editor o el propio usuario
        $employee_obj = $db_emp->get( $emp_id );
        error_log( 'HRM Controller - Employee obj: ' . print_r( $employee_obj, true ) );
        $current_user_id = get_current_user_id();
        error_log( 'HRM Controller - Current user ID: ' . $current_user_id );

        $allowed = false;
        if ( current_user_can( 'edit_hrm_employees' ) || current_user_can( 'manage_options' ) || current_user_can( 'manage_hrm_employees' ) ) {
            $allowed = true;
            error_log( 'HRM Controller - Allowed by capability' );
        } elseif ( current_user_can( 'view_hrm_own_profile' ) && $employee_obj && intval( $employee_obj->user_id ) === $current_user_id ) {
            $allowed = true;
            error_log( 'HRM Controller - Allowed by own profile' );
        }

        if ( ! $allowed ) {
            $message_error = 'No tienes permisos para editar este perfil.';
            error_log( 'HRM Controller - Permission denied' );
        } else {
            error_log( 'HRM Controller - Calling controlled update' );

            // SERVER-SIDE: limitar campos que pueden actualizarse según permisos
            $current_user_id = get_current_user_id();
            $is_admin = current_user_can( 'manage_options' ) || current_user_can( 'edit_hrm_employees' );
            $is_supervisor = current_user_can( 'edit_hrm_employees' );
            $is_own_profile = ( $employee_obj && intval( $employee_obj->user_id ) === $current_user_id );

            // Override para roles restringidos ('empleado' y 'editor_vacaciones') y control de 'supervisor'
            $current_user_obj = wp_get_current_user();
            $restricted_roles = array( 'empleado', 'editor_vacaciones' );
            $is_role_supervisor = in_array( 'supervisor', (array) $current_user_obj->roles, true );
            if ( $is_role_supervisor && $is_own_profile && ! ( current_user_can( 'manage_options' ) ) ) {
                // Supervisores SOLO pueden editar campos personales en su propio perfil
                $allowed_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
            } elseif ( array_intersect( $restricted_roles, (array) $current_user_obj->roles ) && ! $is_admin && ! $is_supervisor ) {
                if ( $is_own_profile ) {
                    $allowed_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
                } else {
                    $allowed_fields = array();
                }
            } elseif ( $is_admin ) {
                $allowed_fields = array('nombre','apellido','telefono','email','departamento','puesto','estado','anos_acreditados_anteriores','fecha_ingreso','salario');
            } elseif ( hrm_can_edit_employee( $emp_id ) && ! $is_own_profile ) {
                $allowed_fields = array('nombre','apellido','telefono','email','departamento','puesto','anos_acreditados_anteriores','fecha_ingreso');
            } elseif ( $is_own_profile ) {
                $allowed_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
            } else {
                $allowed_fields = array();
            }

            $update_data = array();
            foreach ( $allowed_fields as $field ) {
                if ( isset( $_POST[ $field ] ) ) {
                    switch ( $field ) {
                        case 'email':
                            $update_data['email'] = sanitize_email( $_POST['email'] );
                            break;
                        case 'fecha_nacimiento':
                            $fecha_nac = sanitize_text_field( $_POST['fecha_nacimiento'] );
                            if ( preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $fecha_nac ) ) {
                                $update_data['fecha_nacimiento'] = $fecha_nac;
                            }
                            break;
                        case 'salario':
                            $update_data['salario'] = floatval( $_POST['salario'] );
                            break;
                        case 'anos_acreditados_anteriores':
                            $update_data['anos_acreditados_anteriores'] = floatval( $_POST['anos_acreditados_anteriores'] );
                            break;
                        case 'estado':
                            $update_data['estado'] = intval( $_POST['estado'] );
                            break;
                        case 'fecha_ingreso':
                            $fecha = sanitize_text_field( $_POST['fecha_ingreso'] );
                            if ( preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $fecha ) ) {
                                $update_data['fecha_ingreso'] = $fecha;
                            }
                            break;
                        default:
                            $update_data[ $field ] = sanitize_text_field( $_POST[ $field ] );
                    }
                }
            }

            // Recalcular años si corresponde
            if ( isset( $update_data['fecha_ingreso'] ) || isset( $update_data['anos_acreditados_anteriores'] ) ) {
                $fecha_ingreso_final = isset( $update_data['fecha_ingreso'] ) ? $update_data['fecha_ingreso'] : ( $employee_obj->fecha_ingreso ?? '' );
                $anos_empresa = 0;
                if ( $fecha_ingreso_final && $fecha_ingreso_final !== '0000-00-00' ) {
                    $fecha_obj = DateTime::createFromFormat( 'Y-m-d', $fecha_ingreso_final );
                    if ( $fecha_obj ) {
                        $today = new DateTime( 'today' );
                        $diff = $today->diff( $fecha_obj );
                        $anos_empresa = intval( $diff->y );
                    }
                }
                $update_data['anos_en_la_empresa'] = $anos_empresa;
                $anos_anteriores = isset( $update_data['anos_acreditados_anteriores'] ) ? floatval( $update_data['anos_acreditados_anteriores'] ) : floatval( $employee_obj->anos_acreditados_anteriores ?? 0 );
                $update_data['anos_totales_trabajados'] = $anos_anteriores + $anos_empresa;
            }

            if ( empty( $update_data ) ) {
                $message_error = 'No tienes permiso para modificar los campos enviados.';
                error_log( 'HRM Controller - No allowed fields in POST' );
            } else {
                $update_result = $db_emp->update( $emp_id, $update_data );
                error_log( 'HRM Controller - Update result: ' . print_r( $update_result, true ) );
                if ( $update_result ) {
                    $message_success = 'Datos actualizados correctamente.';
                    $employee = $db_emp->get( $emp_id ); // Recargar datos
                    error_log( 'HRM Controller - Update success' );
                } else {
                    $message_error = 'No se realizaron cambios.';
                    error_log( 'HRM Controller - Update failed' );
                }
            }
        }
    }
    
    // El bloque de creación de empleado se procesa ahora en el handler central `hrm_handle_employees_post()` (includes/employees.php) para garantizar que las redirecciones ocurran antes de enviar salida y evitar warnings de headers. Si necesitas ajustar su comportamiento, edítalo allí.

    // --- ACCIÓN C: Subir Documentos ---
    elseif ( $action === 'upload_document' && check_admin_referer( 'hrm_upload_file', 'hrm_upload_nonce' ) ) {
        $emp_id = absint( $_POST['employee_id'] );
        
        // Validar permisos: admin, edit_hrm_employees o supervisor global
        $can_upload = current_user_can( 'manage_options' ) || 
                      current_user_can( 'edit_hrm_employees' ) ||
                      hrm_es_supervisor_global();
        
        if ( ! $can_upload ) {
            $message_error = 'No tienes permisos para subir documentos.';
            error_log( 'HRM: Intento de upload sin permisos - User ID: ' . get_current_user_id() . ', Empleado ID: ' . $emp_id );
        } else {
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
    }

    // --- ACCIÓN D: Eliminar Documento ---
    elseif ( $action === 'delete_document' && check_admin_referer( 'hrm_delete_file', 'hrm_delete_nonce' ) ) {
        
        // Validar permisos: admin, edit_hrm_employees o supervisor global
        $can_delete = current_user_can( 'manage_options' ) || 
                      current_user_can( 'edit_hrm_employees' ) ||
                      hrm_es_supervisor_global();
        
        if ( ! $can_delete ) {
            $message_error = 'No tienes permisos para eliminar documentos.';
            error_log( 'HRM: Intento de delete sin permisos - User ID: ' . get_current_user_id() );
        } else {
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
    'Diseñador Gráfico',
    'Practicante',
) );
// Obtener tipos de documento desde BD (si existen), fallback a lista estática
hrm_ensure_db_classes();
$db_docs = new HRM_DB_Documentos();
$hrm_tipos_documento = $db_docs->get_all_types();
if ( empty( $hrm_tipos_documento ) ) {
    $hrm_tipos_documento = apply_filters( 'hrm_tipos_documento', array( 'Contrato', 'Liquidaciones', 'Licencia' ) );
}
$hrm_tipos_contrato = apply_filters( 'hrm_tipos_contrato', array( 'Indefinido', 'Plazo Fijo', 'Por Proyecto' ) );

// Detectar filtro de estado (activos/inactivos)
$show_inactive = isset( $_GET['show_inactive'] ) && $_GET['show_inactive'] === '1';
// Detectar toggle 'view_all' (solo aplicable para administrador_anaconda)
$view_all = isset( $_GET['view_all'] ) && $_GET['view_all'] === '1';

if ( $tab === 'list' ) {
    // Si se solicita ver todo y el usuario es administrador_anaconda, devolver todos (filtrados por estado si aplica)
    $current_user = wp_get_current_user();
    $is_anaconda = in_array( 'administrador_anaconda', (array) $current_user->roles, true );

    if ( $view_all && $is_anaconda ) {
        $employees = $show_inactive ? $db_emp->get_by_status( 0 ) : $db_emp->get_by_status( 1 );
    } else {
        // Por defecto mostrar solo activos (estado=1), a menos que se solicite inactivos
        $employees = $show_inactive ? $db_emp->get_visible_for_user( get_current_user_id(), 0 ) : $db_emp->get_visible_for_user( get_current_user_id(), 1 );
    }
} elseif ( $id ) {
    $employee = $db_emp->get( $id );
    if ( $employee && $tab === 'upload' ) {
        $documents = $db_docs->get_by_rut( $employee->rut );
    }
}

// Preparar lista de empleados para el selector
$all_emps = array();
if ( $tab !== 'list' ) {
    $all_emps = $db_emp->get_visible_for_user( get_current_user_id(), null );
}
?>

<div class="wrap hrm-admin-wrap">
    
    <div class="hrm-admin-layout">
        <?php hrm_get_template_part( 'partials/sidebar-loader' ); ?>
        
        <main class="hrm-content">
            
            <?php if ( ! empty( $message_success ) ) : ?>
                <?php if ( $tab === 'new' ) : // Mostrar el notice en blanco sobre la vista de creación ?>
                    <div class="notice notice-success is-dismissible hrm-notice-success">
                        <p class="hrm-notice-text mb-0 p-2"><?= esc_html( $message_success ) ?></p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-success is-dismissible"><p><?= esc_html( $message_success ) ?></p></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( ! empty( $message_error ) ) : ?>
                <div class="notice notice-error is-dismissible"><p><?= esc_html( $message_error ) ?></p></div>
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

            <?php elseif ( $tab === 'upload' ) : ?>

                <?php hrm_get_template_part( 'employees-documents', '', compact( 'employee', 'documents', 'hrm_tipos_documento', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( $tab === 'new' ) : ?>

                <?php hrm_get_template_part( 'employees-create', '', compact( 'hrm_departamentos', 'hrm_puestos', 'hrm_tipos_contrato', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( $tab === 'profile' && ! $id ) : ?>
                <div class="hrm-empty-placeholder">
                    <h2><strong>⚠️ Atención:</strong> Por favor selecciona un usuario para continuar.</h2>
                </div>
            <?php endif; ?>

            </div>
        </main>
    </div>
</div>
