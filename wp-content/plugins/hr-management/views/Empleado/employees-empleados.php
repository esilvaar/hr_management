<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. INICIALIZACIÓN
$db_emp  = new HRM_DB_Empleados();   
$db_docs = new HRM_DB_Documentos(); 

$current_user_id = get_current_user_id();
$employee = null;

// Obtener el empleado vinculado al usuario actual
$employee = $db_emp->get_by_wp_user( $current_user_id );

// Si no encuentra vinculación, mostrar error
if ( ! $employee ) {
    wp_die( 'No hay registro de empleado vinculado a tu usuario.', 'Error', array( 'response' => 403 ) );
}

$emp_id = $employee->id;
$tab = sanitize_key( $_GET['tab'] ?? 'profile' );

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

    // --- ACCIÓN A: Actualizar Perfil Propio ---
    if ( $action === 'update_employee' && check_admin_referer( 'hrm_update_employee', 'hrm_update_employee_nonce' ) ) {
        
        // Verificar que intenta actualizar solo su propio perfil
        $post_emp_id = absint( $_POST['employee_id'] ?? 0 );
        if ( intval( $post_emp_id ) !== $emp_id ) {
            $message_error = 'No puedes editar el perfil de otro empleado.';
        } else {
            // Solo permitir actualizar ciertos campos (no RUT, no email de sistema, etc)
            $allowed_fields = array(
                'nombre', 'apellido', 'telefono', 'fecha_nacimiento', 
                'puesto', 'departamento'
            );
            
            $data = array();
            foreach ( $allowed_fields as $field ) {
                if ( isset( $_POST[ $field ] ) ) {
                    $data[ $field ] = sanitize_text_field( $_POST[ $field ] );
                }
            }

            if ( $db_emp->update( $emp_id, $data ) ) {
                $message_success = 'Tu perfil ha sido actualizado correctamente.';
                $employee = $db_emp->get( $emp_id ); // Recargar datos
            } else {
                $message_error = 'No se realizaron cambios en tu perfil.';
            }
        }
    }

    // --- ACCIÓN B: Subir Avatar ---
    elseif ( $action === 'upload_avatar' && check_admin_referer( 'hrm_upload_avatar', 'hrm_upload_avatar_nonce' ) ) {
        $post_emp_id = absint( $_POST['employee_id'] ?? 0 );
        if ( intval( $post_emp_id ) !== $emp_id ) {
            $message_error = 'No puedes subir avatar de otro empleado.';
        } elseif ( empty( $_FILES['avatar'] ) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK ) {
            $message_error = 'No seleccionaste un archivo válido.';
        } else {
            // Procesar upload de avatar (reutilizar lógica)
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
                    'post_status'    => 'inherit'
                );

                $attach_id = wp_insert_attachment( $attachment, $file_path );
                if ( ! is_wp_error( $attach_id ) ) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    
                    $avatar_url = wp_get_attachment_url( $attach_id );
                    update_user_meta( $current_user_id, 'simple_local_avatar', array( 'full' => $avatar_url ) );
                    
                    $message_success = 'Avatar actualizado correctamente.';
                    $employee = $db_emp->get( $emp_id );
                } else {
                    $message_error = 'Error al procesar la imagen.';
                }
            } else {
                $error_msg = isset( $upload_result['error'] ) ? $upload_result['error'] : 'Error desconocido.';
                $message_error = $error_msg;
            }
        }
    }

    // --- ACCIÓN C: Subir Documentos (solo propios) ---
    elseif ( $action === 'upload_document' && check_admin_referer( 'hrm_upload_file', 'hrm_upload_nonce' ) ) {
        $post_emp_id = absint( $_POST['employee_id'] ?? 0 );
        if ( intval( $post_emp_id ) !== $emp_id ) {
            $message_error = 'No puedes subir documentos de otro empleado.';
        } else {
            $tipo   = wp_kses_post( trim( $_POST['tipo_documento'] ?? 'Generico' ) );
            $anio_raw = isset($_POST['anio_documento']) ? trim($_POST['anio_documento']) : '';
            $anio   = ! empty($anio_raw) ? (int)$anio_raw : 0;
            $files = $_FILES['archivos_subidos'] ?? [];

            if ( empty( $files ) || empty( $files['name'][0] ) ) {
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
                $folder_user = sanitize_file_name( $employee->rut );
                $folder_type = sanitize_file_name( $tipo );

                $relative_path    = '/' . $folder_year . '/' . $folder_user . '/' . $folder_type;
                $final_target_dir = $base_dir . $relative_path;
                $final_target_url = $base_url . $relative_path;

                if ( ! file_exists( $final_target_dir ) ) {
                    wp_mkdir_p( $final_target_dir );
                    // Crear index.html para evitar ejecución de ficheros y listado
                    if ( function_exists( 'hrm_ensure_placeholder_index' ) ) {
                        hrm_ensure_placeholder_index( $final_target_dir );
                    }
                }

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
                            'rut'    => $employee->rut,
                            'tipo'   => $tipo,
                            'anio'   => $anio,
                            'nombre' => $final_filename,
                            'url'    => $file_url 
                        ]);
                        if ( $saved ) {
                            $count_ok++;
                        } else {
                            $count_err++;
                            error_log( "HRM: Error guardando documento en BD - RUT: {$employee->rut}, Archivo: $final_filename" );
                        }
                    } else {
                        $count_err++;
                        error_log( "HRM: Error moviendo archivo - {$files['name'][$i]} a $file_path" );
                    }
                    }
                }
                if ( $count_ok > 0 ) $message_success = "Se subieron $count_ok archivo(s) en la carpeta del año $folder_year.";
                if ( $count_err > 0 ) $message_error = "Fallaron $count_err archivo(s).";
            }
        }
    }

    // --- ACCIÓN D: Eliminar Documento Propio ---
    elseif ( $action === 'delete_document' && check_admin_referer( 'hrm_delete_file', 'hrm_delete_nonce' ) ) {
        $doc_id = absint( $_POST['doc_id'] );
        $doc    = $db_docs->get( $doc_id );

        if ( $doc && $doc->rut === $employee->rut ) { // Verificar que es su documento
            $upload_dir = wp_upload_dir();
            $file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $doc->url );

            if ( file_exists( $file_path ) ) unlink( $file_path ); 
            $db_docs->delete( $doc_id ); 
            
            $message_success = 'Documento eliminado correctamente.';
        } else {
            $message_error = 'No tienes permiso para eliminar este documento.';
        }
    }


// 3. OBTENCIÓN DE DATOS
$documents = array();

$hrm_departamentos = apply_filters( 'hrm_departamentos', array( 'Soporte', 'Desarrollo', 'Administracion', 'Ventas', 'Gerencia', 'Sistemas' ) );
$hrm_puestos = apply_filters( 'hrm_puestos', array( 'Técnico', 'Analista', 'Gerente', 'Administrativo', 'Practicante' ) );
$hrm_tipos_documento = apply_filters( 'hrm_tipos_documento', array( 'Contrato', 'Certificado', 'Identidad', 'Medico', 'Otro' ) );

if ( $tab === 'upload' ) {
    $documents = $db_docs->get_by_rut( $employee->rut );
}
?>

<div class="wrap hrm-empleado-wrap">
    <div class="hrm-admin-layout">
        <?php hrm_get_template_part( 'partials/sidebar-loader' ); ?>
        <main class="hrm-content">
            <h1 class="wp-heading-inline">Mi Perfil</h1>
            
            <?php if ( ! empty( $message_success ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?= esc_html( $message_success ) ?></p></div>
            <?php endif; ?>

            <?php if ( ! empty( $message_error ) ) : ?>
                <div class="notice notice-error is-dismissible"><p><?= esc_html( $message_error ) ?></p></div>
            <?php endif; ?>

            <!-- Tabs de Navegación -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=hrm-mi-perfil&tab=profile" class="nav-tab <?= $tab === 'profile' ? 'nav-tab-active' : '' ?>">Perfil</a>
                <a href="?page=hrm-mi-perfil&tab=upload" class="nav-tab <?= $tab === 'upload' ? 'nav-tab-active' : '' ?>">Documentos</a>
            </h2>

            <div class="hrm-admin-panel">

            <!-- Renderizar vista según tab seleccionado -->
            <?php if ( $tab === 'profile' && $employee ) : ?>

                <?php hrm_get_template_part( 'employees-detail', '', compact( 'employee', 'hrm_departamentos', 'hrm_puestos', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( $tab === 'upload' && $employee ) : ?>

                <?php hrm_get_template_part( 'employees-documents', '', compact( 'employee', 'documents', 'hrm_tipos_documento', 'message_success', 'message_error' ) ); ?>

            <?php elseif ( ! $employee ) : ?>
                <div class="d-flex align-items-center justify-content-center" style="min-height: 400px;">
                    <h2 style="font-size: 24px; color: #856404; text-align: center; max-width: 500px;"><strong>⚠️ Atención:</strong> Por favor selecciona un usuario para continuar.</h2>
                </div>
            <?php endif; ?>

            </div>
        </main>
    </div>
</div>