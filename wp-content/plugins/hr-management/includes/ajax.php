<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Registrar un shutdown handler y un log inicial para peticiones AJAX
if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
    // Log inicial para depuración rápida
    error_log( 'HRM AJAX START - action=' . ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '(none)' ) . ' REQUEST_KEYS=' . json_encode( array_keys( $_REQUEST ) ) );

    // Registrar shutdown para capturar errores fatales que no llegan al stack normal
    register_shutdown_function( function() {
        $err = error_get_last();
        if ( $err && in_array( $err['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
            error_log( 'HRM AJAX SHUTDOWN ERROR: ' . print_r( $err, true ) );
            // Mostrar algunos parámetros útiles (sin volcar todo el request para evitar datos sensibles)
            error_log( 'HRM AJAX SHUTDOWN - REQUEST_SNIPPET: ' . print_r( array_intersect_key( $_REQUEST, array( 'action' => 1, 'email' => 1, 'email_b64' => 1, 'nonce' => 1 ) ), true ) );
        }
    } );
}

// Endpoint AJAX para descargar liquidaciones en ZIP
add_action('wp_ajax_hrm_descargar_liquidaciones', 'hrm_ajax_descargar_liquidaciones');
function hrm_ajax_descargar_liquidaciones() {
    if (!is_user_logged_in()) {
        wp_die('No autorizado');
    }
    // Limpiar buffer para evitar salida antes de headers
    if (ob_get_length()) ob_end_clean();
    $user_id = get_current_user_id();
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $cantidad = isset($_GET['cantidad']) ? $_GET['cantidad'] : 'all';
    if (!$user_id || !$year) {
        wp_die('Parámetros inválidos');
    }
    descargar_liquidaciones($user_id, $year, $cantidad);
}

/**
 * Asegurar que las clases DB estén cargadas
 */
function hrm_ensure_db_classes() {
    if ( ! class_exists( 'HRM_DB_Table' ) ) {
        require_once HRM_PLUGIN_DIR . 'includes/db/class-hrm-db-table.php';
    }
    if ( ! class_exists( 'HRM_DB_Empleados' ) ) {
        require_once HRM_PLUGIN_DIR . 'includes/db/class-hrm-db-empleados.php';
    }
    if ( ! class_exists( 'HRM_DB_Documentos' ) ) {
        require_once HRM_PLUGIN_DIR . 'includes/db/class-hrm-db-documentos.php';
    }
}

/**
 * Permitir acceso a AJAX para supervisores
 * Fix para el error "Unexpected token '<', "<!DOCTYPE ""
 */
add_filter( 'user_has_cap', 'hrm_grant_ajax_access_to_supervisor', 10, 4 );
function hrm_grant_ajax_access_to_supervisor( $allcaps, $caps, $args, $user ) {
    // Solo aplicar en llamadas AJAX
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        return $allcaps;
    }
    
    // Verificar si es una acción HRM
    $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
    if ( strpos( $action, 'hrm_' ) !== 0 ) {
        return $allcaps; // No es una acción HRM
    }
    
    // Si el usuario tiene rol supervisor, asegurar que tenga acceso
    if ( ! empty( $user->roles ) && in_array( 'supervisor', $user->roles ) ) {
        // Dar permisos necesarios para AJAX
        $allcaps['read'] = true;
        $allcaps['edit_hrm_employees'] = true;
        $allcaps['view_hrm_employee_admin'] = true;
    }
    
    return $allcaps;
}

/**
 * Hook temprano para asegurar que supervisores puedan acceder a AJAX
 */


/**
 * Manejador AJAX para obtener documentos del empleado
 * Carga documentos de forma asincrónica para mejorar rendimiento
 */
function hrm_ajax_get_employee_documents() {
    // Debug: log del usuario y permisos
    $current_user = wp_get_current_user();
    $can_manage = current_user_can( 'manage_options' );
    $can_edit = current_user_can( 'edit_hrm_employees' );
    $can_view_admin = current_user_can( 'view_hrm_employee_admin' );
    
    error_log( 'HRM AJAX Documents - User ID: ' . $current_user->ID );
    error_log( 'HRM AJAX Documents - Roles: ' . implode(', ', $current_user->roles) );
    error_log( 'HRM AJAX Documents - manage_options: ' . ($can_manage ? 'YES' : 'NO') );
    error_log( 'HRM AJAX Documents - edit_hrm_employees: ' . ($can_edit ? 'YES' : 'NO') );
    error_log( 'HRM AJAX Documents - view_hrm_employee_admin: ' . ($can_view_admin ? 'YES' : 'NO') );
    
    // Listar todas las capabilities del usuario para debug
    if ( ! empty( $current_user->allcaps ) ) {
        error_log( 'HRM AJAX Documents - All caps: ' . implode(', ', array_keys( array_filter( $current_user->allcaps ) ) ) );
    }
    
    // Verificar permisos: admin, supervisor o rol con vista de empleados
    if ( ! $can_manage && ! $can_edit && ! $can_view_admin ) {
        error_log( 'HRM AJAX Documents - PERMISSION DENIED' );
        
        // Mensaje de error detallado
        $error_details = array(
            'user_id' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'roles' => $current_user->roles,
            'manage_options' => $can_manage,
            'edit_hrm_employees' => $can_edit,
            'view_hrm_employee_admin' => $can_view_admin,
        );
        
        wp_send_json_error( array(
            'message' => 'No tienes permisos para ver documentos.',
            'debug' => $error_details
        ) );
    }

    // Verificar nonce
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'hrm_get_documents' ) ) {
        wp_send_json_error( array( 'message' => 'Error de verificación de seguridad.' ) );
    }

    // Obtener ID del empleado
    $employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
    if ( ! $employee_id ) {
        wp_send_json_error( array( 'message' => 'ID de empleado inválido.' ) );
    }

    // Obtener filtro de tipo de documento (opcional)
    $doc_type = isset( $_POST['doc_type'] ) ? sanitize_text_field( $_POST['doc_type'] ) : 'all';

    // Asegurar que las clases DB estén cargadas
    hrm_ensure_db_classes();
    
    // Instancia de BD
    $db_docs = new HRM_DB_Documentos();
    $db_emp  = new HRM_DB_Empleados();

    // Obtener empleado
    $employee = $db_emp->get( $employee_id );
    if ( ! $employee ) {
        wp_send_json_error( array( 'message' => 'Empleado #' . $employee_id . ' no encontrado.' ) );
    }
    
    // Verificar que el RUT no esté vacío
    if ( empty( $employee->rut ) ) {
        wp_send_json_error( array( 'message' => 'El empleado no tiene RUT asignado.' ) );
    }

    // --------------------------------------------------
    // Endpoint AJAX para crear un nuevo tipo de documento
    // --------------------------------------------------
    // (esto permite crear el tipo desde el panel sin subir un archivo primero)
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'hrm_create_document_type' ) {
        // Se maneja en función separada abajo; devolver si fue llamada
        if ( function_exists( 'hrm_ajax_create_document_type' ) ) {
            return hrm_ajax_create_document_type();
        }
    }

    // Obtener documentos del empleado
    $documents = $db_docs->get_by_rut( $employee->rut );

    // También exponer endpoint para crear tipos desde la UI (si no existe ya)

    
    // Filtrar por tipo de documento si se especifica (acepta ID o nombre)
    if ( $doc_type !== 'all' ) {
        if ( is_numeric( $doc_type ) ) {
            $doc_type_id = (int) $doc_type;
            $documents = array_filter( $documents, function( $doc ) use ( $doc_type_id ) {
                return isset( $doc->tipo_id ) && (int) $doc->tipo_id === $doc_type_id;
            } );
        } else {
            $documents = array_filter( $documents, function( $doc ) use ( $doc_type ) {
                return isset( $doc->tipo ) && strtolower( $doc->tipo ) === strtolower( $doc_type );
            } );
        }
    }

    // Si no hay documentos, enviar respuesta vacía con HTML más amigable
    if ( empty( $documents ) ) {
        ob_start();
        ?>
        <div style="max-width:900px; margin:0 auto;">
            <div class="d-flex align-items-center justify-content-center" style="min-height: 240px;">
                <h3 style="font-size:20px; color: #856404; text-align:center; max-width: 700px;">
                    <strong>⚠️ Sin documentos:</strong> Este empleado no tiene documentos registrados.
                </h3>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success( $html );
    }

    // Ordenar documentos por mes: diciembre primero, enero último
    $month_order = array(
        'diciembre' => 1,
        'noviembre' => 2,
        'octubre'   => 3,
        'septiembre'=> 4,
        'agosto'    => 5,
        'julio'     => 6,
        'junio'     => 7,
        'mayo'      => 8,
        'abril'     => 9,
        'marzo'     => 10,
        'febrero'   => 11,
        'enero'     => 12
    );

    usort($documents, function($a, $b) use ($month_order) {
        // Extraer mes del nombre del archivo o del campo correspondiente
        $mes_a = '';
        $mes_b = '';
        // Buscar mes en el nombre del archivo (ejemplo: "liquidacion-enero-2024.pdf")
        foreach ($month_order as $mes => $orden) {
            if (stripos($a->nombre, $mes) !== false) {
                $mes_a = $mes;
                break;
            }
        }
        foreach ($month_order as $mes => $orden) {
            if (stripos($b->nombre, $mes) !== false) {
                $mes_b = $mes;
                break;
            }
        }
        // Si no se encuentra el mes, dejar al final
        $orden_a = $mes_a ? $month_order[$mes_a] : 99;
        $orden_b = $mes_b ? $month_order[$mes_b] : 99;
        return $orden_a - $orden_b;
    });

    ob_start();
    ?>
    <div style="">
        <style>
            /* Styling focused on centering table content for better UX */
            .hrm-documents-table th, .hrm-documents-table td { text-align: center !important; vertical-align: middle !important; }
            .hrm-documents-table .d-flex.align-items-center { justify-content: center; }
            .hrm-documents-table .flex-column { text-align: center; }
            /* Alineación de menú dentro de la celda (acciones) */
            .hrm-documents-table td.text-end { text-align: center !important; }
            /* Mantener enlaces del menú alineados a la izquierda dentro del dropdown */
            .hrm-documents-table .hrm-actions-menu a, .hrm-documents-table .hrm-actions-menu button { text-align: left; display:block; }
        </style>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm mb-0 hrm-documents-table">
                <thead class="table-dark small">
                    <tr>
                        <th style="width:80px;">Año</th>
                        <th style="width:160px;">Tipo</th>
                        <th>Archivo</th>
                        <th style="width:120px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody class="hrm-document-list">
                    <?php foreach ( $documents as $doc ) : ?>
                        <tr class="align-middle" data-type="<?= esc_attr( strtolower( $doc->tipo ) ) ?>" data-type-id="<?= esc_attr( $doc->tipo_id ) ?>" data-year="<?= esc_attr( $doc->anio ) ?>">
                            <td style="vertical-align: middle;"><?= esc_html( $doc->anio ) ?></td>
                            <td style="vertical-align: middle;"><small class="text-muted"><?= esc_html( ucfirst( $doc->tipo ) ?: '—' ) ?></small></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="dashicons dashicons-media-document text-secondary" aria-hidden="true"></span>
                                    <div class="d-flex flex-column text-start">
                                        <strong><?= esc_html( $doc->nombre ) ?></strong>
                                        <small class="text-muted"><?= esc_html( date( 'd M Y', strtotime( $doc->fecha ) ) ) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center" style="gap:6px;">
                                    <div class="hrm-actions-dropdown" style="position:relative; display:inline-block;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary hrm-actions-toggle" aria-expanded="false" aria-controls="hrm-actions-menu-<?= esc_attr( $doc->id ) ?>" title="Acciones">
                                            <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                                            <span class="visually-hidden">Acciones</span>
                                        </button>
                                        <div id="hrm-actions-menu-<?= esc_attr( $doc->id ) ?>" class="hrm-actions-menu" style="position:absolute; right:0; top:calc(100% + 6px); background:#fff; border:1px solid #ddd; box-shadow:0 6px 18px rgba(0,0,0,0.08); display:none; min-width:160px; z-index:1100;">
                                            <a class="d-block px-3 py-2 hrm-action-download" href="<?= esc_url( $doc->url ) ?>" target="_blank" rel="noopener noreferrer">Descargar</a>
                                            <?php if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_hrm_employees' ) ) : ?>
                                                <div style="height:1px; background:#e9ecef; margin:4px 0;"></div>
                                                <form method="post" class="hrm-delete-form m-0 p-0" style="display:block;">
                                                    <?php wp_nonce_field( 'hrm_delete_file', 'hrm_delete_nonce' ); ?>
                                                    <input type="hidden" name="hrm_action" value="delete_document">
                                                    <input type="hidden" name="doc_id" value="<?= esc_attr( $doc->id ) ?>">
                                                    <input type="hidden" name="employee_id" value="<?= esc_attr( $employee->id ) ?>">
                                                    <button type="submit" class="d-block w-100 text-start px-3 py-2 text-danger" style="background:transparent; border:none;">Eliminar</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success( $html );
}

/**
 * Manejador AJAX para eliminar un documento específico
 */

// ...existing code...
function hrm_ajax_delete_employee_document() {
    // Debug: log de invocación y parámetros recibidos
    error_log( 'HRM AJAX Delete - Called. Method: ' . ( $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN' ) . ' action: ' . ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '(none)' ) );
    if ( defined( 'DOING_AJAX' ) ) {
        error_log( 'HRM AJAX Delete - DOING_AJAX = true' );
    } else {
        error_log( 'HRM AJAX Delete - DOING_AJAX not defined or false' );
    }
    error_log( 'HRM AJAX Delete - REQUEST keys: ' . implode( ', ', array_keys( $_REQUEST ) ) );

    // Validar contexto y autenticación
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        wp_send_json_error( [ 'message' => 'Petición inválida' ], 400 );
    }
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Usuario no autenticado' ], 401 );
    }

    // Verificar permisos
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_hrm_employees' ) ) {
        wp_send_json_error( [ 'message' => 'No tienes permisos para eliminar documentos.' ], 403 );
    }

    // Limpiar buffer por si hay salida anterior que rompa JSON
    if ( ob_get_length() ) {
        ob_end_clean();
    }

    // Verificar nonce - aceptar tanto 'nonce' como 'hrm_delete_nonce'
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( empty( $nonce ) && isset( $_POST['hrm_delete_nonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_POST['hrm_delete_nonce'] ) );
    }

    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hrm_delete_file' ) ) {
        error_log( 'HRM Delete Document - Nonce verification FAILED' );
        wp_send_json_error( [ 'message' => 'Token de seguridad inválido. Actualiza la página e intenta nuevamente.' ], 403 );
    }

    // Obtener ID del documento
    $doc_id = isset( $_POST['doc_id'] ) ? absint( wp_unslash( $_POST['doc_id'] ) ) : 0;
    if ( ! $doc_id ) {
        wp_send_json_error( [ 'message' => 'ID de documento inválido.' ], 400 );
    }

    // Asegurar que las clases DB estén cargadas
    hrm_ensure_db_classes();

    // Instancia de BD
    $db_docs = new HRM_DB_Documentos();

    // Obtener documento
    $doc = $db_docs->get( $doc_id );
    if ( ! $doc ) {
        wp_send_json_error( [ 'message' => 'Documento no encontrado.' ], 404 );
    }

    // Intentar eliminar archivo físico si existe URL
    $deleted_file = false;
    if ( ! empty( $doc->url ) ) {
        $upload_dir = wp_upload_dir();
        $base_url_wp = untrailingslashit( $upload_dir['baseurl'] );
        $base_dir_wp = untrailingslashit( $upload_dir['basedir'] );

        $file_path = $doc->url;

        // Si la URL comienza con baseurl, convertir a path
        if ( strpos( $file_path, $base_url_wp ) === 0 ) {
            $file_path = str_replace( $base_url_wp, $base_dir_wp, $file_path );
        }

        // Normalizar y eliminar parámetros de query si los hay
        $file_path = strtok( $file_path, '?' );
        $file_path = wp_normalize_path( $file_path );

        if ( file_exists( $file_path ) ) {
            // usar @unlink para evitar warnings sin control; log si falla
            if ( @unlink( $file_path ) ) {
                $deleted_file = true;
            } else {
                error_log( "HRM Delete Document - Failed to unlink file: {$file_path}" );
            }
        } else {
            error_log( "HRM Delete Document - File not found for deletion: {$file_path}" );
        }
    }

    // Eliminar de la base de datos y comprobar resultado
    $db_result = $db_docs->delete( $doc_id );
    if ( $db_result === false ) {
        error_log( "HRM Delete Document - DB delete failed for ID: {$doc_id}" );
        wp_send_json_error( [ 'message' => 'No se pudo eliminar el registro en la base de datos.' ], 500 );
    }

    // Respuesta de éxito (incluye info útil)
    wp_send_json_success( [
        'message' => 'El documento se eliminó correctamente.',
        'file_deleted' => $deleted_file,
        'doc_id' => $doc_id
    ] );
}
// ...existing code...s

/**
 * =====================================================
 * APROBAR SOLICITUD DE VACACIONES (SUPERVISOR)
 * =====================================================
 * Aprueba una solicitud de vacaciones y descuenta los días
 */
function hrm_ajax_aprobar_solicitud_supervisor() {
    // Verificar nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'hrm_aprobar_solicitud' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ], 403 );
    }
    
    // Verificar que sea supervisor
    if ( ! current_user_can( 'manage_hrm_vacaciones' ) ) {
        wp_send_json_error( [ 'message' => 'No tienes permisos para aprobar solicitudes' ], 403 );
    }
    
    global $wpdb;
    
    $solicitud_id = absint( $_POST['solicitud_id'] ?? 0 );
    $empleado_id = absint( $_POST['empleado_id'] ?? 0 );
    $dias = absint( $_POST['dias'] ?? 0 );
    
    if ( ! $solicitud_id || ! $empleado_id || ! $dias ) {
        wp_send_json_error( [ 'message' => 'Parámetros inválidos' ] );
    }
    
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    // Obtener solicitud
    $solicitud = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table_solicitudes WHERE id_solicitud = %d",
        $solicitud_id
    ) );
    
    if ( ! $solicitud || $solicitud->id_empleado != $empleado_id ) {
        wp_send_json_error( [ 'message' => 'Solicitud no encontrada' ] );
    }
    
    // Verificar que es del departamento del supervisor
    $supervisor_user = get_userdata( get_current_user_id() );
    $supervisor_depto = $wpdb->get_var( $wpdb->prepare(
        "SELECT departamento FROM $table_empleados WHERE user_id = %d",
        get_current_user_id()
    ) );
    
    $empleado_depto = $wpdb->get_var( $wpdb->prepare(
        "SELECT departamento FROM $table_empleados WHERE id_empleado = %d",
        $empleado_id
    ) );
    
    if ( $supervisor_depto !== $empleado_depto ) {
        wp_send_json_error( [ 'message' => 'Solo puedes aprobar solicitudes de tu departamento' ] );
    }
    
    // Actualizar estado de solicitud
    $updated = $wpdb->update(
        $table_solicitudes,
        [ 'estado' => 'APROBADA' ],
        [ 'id_solicitud' => $solicitud_id ],
        [ '%s' ],
        [ '%d' ]
    );
    
    if ( $updated === false ) {
        wp_send_json_error( [ 'message' => 'Error al actualizar solicitud' ] );
    }
    
    // Descontar días de vacaciones del empleado
    $dias_actuales = $wpdb->get_var( $wpdb->prepare(
        "SELECT dias_vacaciones_disponibles FROM $table_empleados WHERE id_empleado = %d",
        $empleado_id
    ) );
    
    $nuevos_dias = max( 0, (int) $dias_actuales - $dias );
    
    $wpdb->update(
        $table_empleados,
        [ 'dias_vacaciones_disponibles' => $nuevos_dias ],
        [ 'id_empleado' => $empleado_id ],
        [ '%d' ],
        [ '%d' ]
    );
    
    error_log( "HRM: Solicitud $solicitud_id aprobada por supervisor. Días descontados: $dias. Días restantes: $nuevos_dias" );
    
    wp_send_json_success( [
        'message' => 'Solicitud aprobada correctamente',
        'dias_restantes' => $nuevos_dias
    ] );
}

/**
 * =====================================================
 * RECHAZAR SOLICITUD DE VACACIONES (SUPERVISOR)
 * =====================================================
 * Rechaza una solicitud de vacaciones
 */
function hrm_ajax_rechazar_solicitud_supervisor() {
    // Verificar nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'hrm_rechazar_solicitud' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ], 403 );
    }
    
    // Verificar que sea supervisor
    if ( ! current_user_can( 'manage_hrm_vacaciones' ) ) {
        wp_send_json_error( [ 'message' => 'No tienes permisos para rechazar solicitudes' ], 403 );
    }
    
    global $wpdb;
    
    $solicitud_id = absint( $_POST['solicitud_id'] ?? 0 );
    
    if ( ! $solicitud_id ) {
        wp_send_json_error( [ 'message' => 'Parámetros inválidos' ] );
    }
    
    $table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    
    // Obtener solicitud
    $solicitud = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table_solicitudes WHERE id_solicitud = %d",
        $solicitud_id
    ) );
    
    if ( ! $solicitud ) {
        wp_send_json_error( [ 'message' => 'Solicitud no encontrada' ] );
    }
    
    // Verificar que es del departamento del supervisor
    $supervisor_depto = $wpdb->get_var( $wpdb->prepare(
        "SELECT departamento FROM $table_empleados WHERE user_id = %d",
        get_current_user_id()
    ) );
    
    $empleado_depto = $wpdb->get_var( $wpdb->prepare(
        "SELECT departamento FROM $table_empleados WHERE id_empleado = %d",
        $solicitud->id_empleado
    ) );
    
    if ( $supervisor_depto !== $empleado_depto ) {
        wp_send_json_error( [ 'message' => 'Solo puedes rechazar solicitudes de tu departamento' ] );
    }
    
    // Actualizar estado de solicitud
    $updated = $wpdb->update(
        $table_solicitudes,
        [ 'estado' => 'RECHAZADA' ],
        [ 'id_solicitud' => $solicitud_id ],
        [ '%s' ],
        [ '%d' ]
    );
    
    if ( $updated === false ) {
        wp_send_json_error( [ 'message' => 'Error al actualizar solicitud' ] );
    }
    
    error_log( "HRM: Solicitud $solicitud_id rechazada por supervisor" );
    
    wp_send_json_success( [ 'message' => 'Solicitud rechazada correctamente' ] );
}

// Enganchar las funciones AJAX
add_action( 'wp_ajax_hrm_get_employee_documents', 'hrm_ajax_get_employee_documents' );

/**
 * Endpoint AJAX: Crear nuevo tipo de documento desde el panel
 */
function hrm_ajax_create_document_type() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'No autorizado' ], 401 );
    }

    // Verificar permisos: admin o editar empleados
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_hrm_employees' ) ) {
        wp_send_json_error( [ 'message' => 'No tienes permisos para crear tipos' ], 403 );
    }

    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hrm_create_type' ) ) {
        wp_send_json_error( [ 'message' => 'Token de seguridad inválido' ], 403 );
    }

    $nombre = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    if ( empty( $nombre ) ) {
        wp_send_json_error( [ 'message' => 'Nombre inválido' ], 400 );
    }

    hrm_ensure_db_classes();
    $db_docs = new HRM_DB_Documentos();

    $id = $db_docs->create_type( $nombre );
    if ( ! $id ) {
        wp_send_json_error( [ 'message' => 'No se pudo crear el tipo' ], 500 );
    }

    wp_send_json_success( [ 'id' => $id, 'name' => $nombre ] );
}

add_action( 'wp_ajax_hrm_create_document_type', 'hrm_ajax_create_document_type' );

/**
 * Endpoint AJAX: Comprobar si un email ya existe en WP
 * Retorna JSON { success: true, data: { exists: bool, user_id: int|null } }
 */
function hrm_ajax_check_email() {
    // Debug: registrar llamada para detectar problemas que causan 500
    $current_user_id = get_current_user_id();
    error_log( "HRM AJAX hrm_check_email called by user_id={$current_user_id}. REQUEST=" . json_encode( array_intersect_key( $_REQUEST, array( 'action' => 1, 'email' => 1, 'email_b64' => 1, 'nonce' => 1 ) ) ) );
    // Also append to a file in wp-content to capture logs if syslog is suppressed
    @file_put_contents( WP_CONTENT_DIR . '/hrm_ajax_debug.log', date( 'c' ) . " - hrm_check_email invoked by user_id={$current_user_id} REQUEST_KEYS=" . json_encode( array_keys( $_REQUEST ) ) . PHP_EOL, FILE_APPEND );

    // Solo para usuarios con permisos de administración de empleados o creación de usuarios
    if ( ! current_user_can( 'edit_hrm_employees' ) && ! current_user_can( 'create_users' ) && ! current_user_can( 'manage_options' ) ) {
        error_log( "HRM AJAX hrm_check_email - permission denied for user_id={$current_user_id}" );
        wp_send_json_error( array( 'message' => 'No tienes permisos para verificar este email.' ), 403 );
    }

    $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( $_REQUEST['nonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'hrm_check_email_nonce' ) ) {
        error_log( "HRM AJAX hrm_check_email - invalid nonce for user_id={$current_user_id}" );
        wp_send_json_error( array( 'message' => 'Nonce inválido' ), 403 );
    }

    // Support base64-encoded email (email_b64) to avoid WAF/ModSecurity blocking of raw email in POST bodies
    $email = '';
    if ( isset( $_REQUEST['email_b64'] ) ) {
        $email_b64 = wp_unslash( $_REQUEST['email_b64'] );
        $decoded = base64_decode( $email_b64, true );
        if ( $decoded === false ) {
            error_log( "HRM AJAX hrm_check_email - invalid base64 for email_b64" );
            wp_send_json_error( array( 'message' => 'Email inválido (codificación)' ), 400 );
        }
        $email = sanitize_email( $decoded );
        error_log( "HRM AJAX hrm_check_email - received email via base64: {$email}" );
    } elseif ( isset( $_REQUEST['email'] ) ) {
        $email = sanitize_email( wp_unslash( $_REQUEST['email'] ) );
    }

    if ( empty( $email ) || ! is_email( $email ) ) {
        error_log( "HRM AJAX hrm_check_email - invalid email provided: '{$email}'" );
        wp_send_json_error( array( 'message' => 'Email inválido' ), 400 );
    }

    // Comprobar usuario WP
    try {
        $user_id = email_exists( $email );

        // Comprobar en tabla de empleados (correo)
        hrm_ensure_db_classes();
        $db_emp = new HRM_DB_Empleados();

        // Usar prepare y get_row dentro de try/catch para capturar errores SQL
        // Usar el método seguro en la clase para buscar por email
        $empleado = $db_emp->get_by_email( $email );

        // Verificar errores SQL inesperados a través del accessor
        $last_error = $db_emp->last_error();
        if ( ! empty( $last_error ) ) {
            error_log( "HRM AJAX hrm_check_email - SQL error: " . $last_error );
            wp_send_json_error( array( 'message' => 'Error en la consulta de la base de datos.' ), 500 );
        }

    } catch ( Exception $e ) {
        error_log( "HRM AJAX hrm_check_email - Exception: " . $e->getMessage() );
        wp_send_json_error( array( 'message' => 'Error interno al verificar email.' ), 500 );
    }

    $response = array(
        'exists_wp' => (bool) $user_id,
        'wp_user_id' => $user_id ? intval( $user_id ) : null,
        'exists_emp' => ! empty( $empleado ),
        'employee_id' => ! empty( $empleado ) ? intval( $empleado->id ) : null,
        'employee_name' => ! empty( $empleado ) ? trim( ($empleado->nombre ?? '') . ' ' . ($empleado->apellido ?? '') ) : '',
    );

    error_log( "HRM AJAX hrm_check_email - response: " . json_encode( $response ) );

    wp_send_json_success( $response );
}
add_action( 'wp_ajax_hrm_check_email', 'hrm_ajax_check_email' );
add_action( 'wp_ajax_hrm_delete_employee_document', 'hrm_ajax_delete_employee_document' );
add_action( 'wp_ajax_hrm_aprobar_solicitud_supervisor', 'hrm_ajax_aprobar_solicitud_supervisor' );
add_action( 'wp_ajax_hrm_rechazar_solicitud_supervisor', 'hrm_ajax_rechazar_solicitud_supervisor' );
