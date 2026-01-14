<?php
/**
 * Controlador de Vacaciones
 */

namespace Anaconda\HRSuite\Admin\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VacationsController {

    /**
     * Renderizar listado de solicitudes
     */
    public function render_list() {
        // Verificar permisos
        if ( ! current_user_can( 'manage_hrsuite_vacations' ) && ! current_user_can( 'manage_hrsuite' ) ) {
            wp_die( __( 'No tienes permiso para acceder', 'anaconda-hr-suite' ) );
        }

        // Procesar aprobación/rechazo
        if ( isset( $_POST['action'] ) && in_array( $_POST['action'], [ 'approve', 'reject' ] ) ) {
            if ( ! anaconda_hrsuite_verify_nonce() ) {
                wp_die( __( 'Nonce inválido', 'anaconda-hr-suite' ) );
            }

            if ( 'approve' === $_POST['action'] ) {
                $this->handle_approve_request();
            } else {
                $this->handle_reject_request();
            }
        }

        // Obtener datos
        $service = anaconda_hrsuite_vacation_service();

        // Filtrar por estado
        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : null;

        $requests = $service->get_all_requests( [
            'estado'  => $status,
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ] );

        // Obtener datos de empleados para mostrar nombres
        $employee_service = anaconda_hrsuite_employee_service();
        $employees_map    = [];

        foreach ( $employee_service->get_employees() as $emp ) {
            $employees_map[ $emp->id ] = $emp;
        }

        // Renderizar vista
        anaconda_hrsuite_render_view( 'vacations-list', [
            'requests'       => $requests,
            'employees_map'  => $employees_map,
            'status_filter'  => $status,
        ] );
    }

    /**
     * Aprobar solicitud
     */
    private function handle_approve_request() {
        if ( ! isset( $_POST['request_id'] ) ) {
            wp_die( __( 'ID de solicitud faltante', 'anaconda-hr-suite' ) );
        }

        $service = anaconda_hrsuite_vacation_service();
        $result = $service->approve_request( absint( $_POST['request_id'] ), get_current_user_id() );

        if ( is_wp_error( $result ) ) {
            wp_die( $result->get_error_message() );
        }

        wp_redirect( add_query_arg( [ 'status' => 'aprobada' ], anaconda_hrsuite_admin_url( 'anaconda-hr-suite-vacations' ) ) );
        exit;
    }

    /**
     * Rechazar solicitud
     */
    private function handle_reject_request() {
        if ( ! isset( $_POST['request_id'] ) ) {
            wp_die( __( 'ID de solicitud faltante', 'anaconda-hr-suite' ) );
        }

        $reason = sanitize_textarea_field( $_POST['reason'] ?? '' );

        $service = anaconda_hrsuite_vacation_service();
        $result = $service->reject_request( absint( $_POST['request_id'] ), get_current_user_id(), $reason );

        if ( is_wp_error( $result ) ) {
            wp_die( $result->get_error_message() );
        }

        wp_redirect( add_query_arg( [ 'status' => 'rechazada' ], anaconda_hrsuite_admin_url( 'anaconda-hr-suite-vacations' ) ) );
        exit;
    }
}
