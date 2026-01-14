<?php
/**
 * Controlador de Dashboard
 */

namespace Anaconda\HRSuite\Admin\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardController {

    /**
     * Renderizar dashboard
     */
    public function render() {
        // Verificar permisos
        if ( ! current_user_can( 'view_hrsuite_dashboard' ) && ! current_user_can( 'manage_hrsuite' ) ) {
            wp_die( __( 'No tienes permiso para acceder', 'anaconda-hr-suite' ) );
        }

        // Obtener datos para el dashboard
        $data = $this->get_dashboard_data();

        // Renderizar vista
        anaconda_hrsuite_render_view( 'dashboard', $data );
    }

    /**
     * Obtener datos para el dashboard
     */
    private function get_dashboard_data() {
        $employee_service  = anaconda_hrsuite_employee_service();
        $vacation_service  = anaconda_hrsuite_vacation_service();

        return [
            'total_employees'       => $employee_service->count_active_employees(),
            'pending_requests'      => $vacation_service->count_by_status( 'pendiente' ),
            'approved_requests'     => $vacation_service->count_by_status( 'aprobada' ),
            'rejected_requests'     => $vacation_service->count_by_status( 'rechazada' ),
            'recent_requests'       => array_slice( $vacation_service->get_all_requests(), 0, 5 ),
            'current_user'          => wp_get_current_user(),
            'is_admin'              => current_user_can( 'manage_hrsuite' ),
        ];
    }
}
