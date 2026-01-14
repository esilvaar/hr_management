<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
add_action( 'admin_init', 'hrm_ensure_supervisor_ajax_access', 1 );
function hrm_ensure_supervisor_ajax_access() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        return;
    }
    
    $current_user = wp_get_current_user();
    if ( ! empty( $current_user->roles ) && in_array( 'supervisor', $current_user->roles ) ) {
        // Forzar que el usuario tenga capacidades necesarias
        $current_user->add_cap( 'read' );
        $current_user->add_cap( 'edit_hrm_employees' );
        $current_user->add_cap( 'view_hrm_employee_admin' );
    }
}

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

    // Obtener documentos del empleado
    $documents = $db_docs->get_by_rut( $employee->rut );
    
    // Filtrar por tipo de documento si se especifica
    if ( $doc_type !== 'all' ) {
        $documents = array_filter( $documents, function( $doc ) use ( $doc_type ) {
            return strtolower( $doc->tipo ) === strtolower( $doc_type );
        } );
    }

    // Si no hay documentos, enviar respuesta vacía con HTML apropiado
    if ( empty( $documents ) ) {
        ob_start();
        ?>
        <p>No hay documentos registrados para este empleado.</p>
        <?php
        $html = ob_get_clean();
        wp_send_json_success( $html );
    }

    // Generar tabla HTML con documentos
    ob_start();
    ?>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Año</th>
                <th>Tipo</th>
                <th>Archivo</th>
                <th style="width: 200px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $documents as $doc ) : ?>
                <tr data-year="<?= esc_attr( $doc->anio ) ?>" data-type="<?= esc_attr( strtolower( $doc->tipo ) ) ?>">
                    <td><?= esc_attr( $doc->anio ) ?></td>
                    <td><?= esc_html( $doc->tipo ) ?></td>
                    <td><?= esc_html( $doc->nombre ) ?></td>
                    <td>
                        <a href="<?= esc_url( $doc->url ) ?>" target="_blank" class="btn btn-sm btn-primary">
                            Descargar
                        </a>
                        <?php if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_hrm_employees' ) ) : ?>
                            <form method="post" class="d-inline hrm-delete-form">
                                <?php wp_nonce_field( 'hrm_delete_file', 'hrm_delete_nonce' ); ?>
                                <input type="hidden" name="hrm_action" value="delete_document">
                                <input type="hidden" name="doc_id" value="<?= esc_attr( $doc->id ) ?>">
                                <input type="hidden" name="employee_id" value="<?= esc_attr( $employee->id ) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    Eliminar
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $html = ob_get_clean();
    wp_send_json_success( $html );
}

/**
 * Manejador AJAX para eliminar un documento específico
 */
function hrm_ajax_delete_employee_document() {
    // Verificar permisos
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_hrm_employees' ) ) {
        wp_send_json_error( 'No tienes permisos para eliminar documentos.' );
    }

    // Verificar nonce - aceptar tanto 'nonce' como 'hrm_delete_nonce'
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
    if ( empty( $nonce ) && isset( $_POST['hrm_delete_nonce'] ) ) {
        $nonce = sanitize_text_field( $_POST['hrm_delete_nonce'] );
    }
    
    error_log( 'HRM Delete Document - Nonce received: ' . substr( $nonce, 0, 10 ) . '...' );
    
    if ( ! wp_verify_nonce( $nonce, 'hrm_delete_file' ) ) {
        error_log( 'HRM Delete Document - Nonce verification FAILED for: ' . substr( $nonce, 0, 10 ) );
        wp_send_json_error( 'Error de verificación de seguridad.' );
    }
    
    error_log( 'HRM Delete Document - Nonce verification OK' );

    // Obtener ID del documento
    $doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
    if ( ! $doc_id ) {
        wp_send_json_error( 'ID de documento inválido.' );
    }

    // Asegurar que las clases DB estén cargadas
    hrm_ensure_db_classes();
    
    // Instancia de BD
    $db_docs = new HRM_DB_Documentos();
    
    // Obtener documento
    $doc = $db_docs->get( $doc_id );
    if ( ! $doc ) {
        wp_send_json_error( 'Documento no encontrado.' );
    }

    // Eliminar archivo físico
    $upload_dir = wp_upload_dir();
    $base_url_wp = $upload_dir['baseurl'];
    $base_dir_wp = $upload_dir['basedir'];
    
    // Convertir URL a ruta física para borrar
    $file_path = str_replace( $base_url_wp, $base_dir_wp, $doc->url );
    
    if ( file_exists( $file_path ) ) {
        unlink( $file_path );
    }
    
    // Eliminar de la base de datos
    $db_docs->delete( $doc_id );
    
    wp_send_json_success( 'Documento eliminado correctamente.' );
}

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
add_action( 'wp_ajax_hrm_delete_employee_document', 'hrm_ajax_delete_employee_document' );
add_action( 'wp_ajax_hrm_aprobar_solicitud_supervisor', 'hrm_ajax_aprobar_solicitud_supervisor' );
add_action( 'wp_ajax_hrm_rechazar_solicitud_supervisor', 'hrm_ajax_rechazar_solicitud_supervisor' );
