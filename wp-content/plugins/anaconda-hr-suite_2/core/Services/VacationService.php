<?php
/**
 * Servicio de Vacaciones/Ausencias
 * Lógica de negocio para solicitudes de ausencia
 */

namespace Anaconda\HRSuite\Core\Services;

use Anaconda\HRSuite\Core\DB\Ausencias as AusenciasDB;
use Anaconda\HRSuite\Core\DB\Empleados as EmpleadosDB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VacationService {

    protected $db;
    protected $employee_db;

    public function __construct() {
        $this->db          = new AusenciasDB();
        $this->employee_db = new EmpleadosDB();
    }

    /**
     * Crear solicitud de ausencia
     */
    public function request_vacation( $data ) {
        // Validar datos requeridos
        if ( empty( $data['id_empleado'] ) || empty( $data['fecha_inicio'] ) || empty( $data['fecha_fin'] ) ) {
            return new \WP_Error( 'invalid_data', __( 'Datos incompletos', 'anaconda-hr-suite' ) );
        }

        // Verificar que el empleado exista
        $employee = $this->employee_db->get( $data['id_empleado'] );
        if ( ! $employee ) {
            return new \WP_Error( 'employee_not_found', __( 'Empleado no encontrado', 'anaconda-hr-suite' ) );
        }

        // Validar que las fechas sean válidas
        $fecha_inicio = strtotime( $data['fecha_inicio'] );
        $fecha_fin    = strtotime( $data['fecha_fin'] );

        if ( ! $fecha_inicio || ! $fecha_fin ) {
            return new \WP_Error( 'invalid_dates', __( 'Fechas inválidas', 'anaconda-hr-suite' ) );
        }

        if ( $fecha_inicio > $fecha_fin ) {
            return new \WP_Error( 'invalid_dates', __( 'La fecha de inicio debe ser anterior a la de fin', 'anaconda-hr-suite' ) );
        }

        // Verificar que no haya solicitudes en conflicto
        $conflicting = $this->check_overlapping_requests( $data['id_empleado'], $data['fecha_inicio'], $data['fecha_fin'] );
        if ( $conflicting ) {
            return new \WP_Error( 'date_conflict', __( 'Hay una solicitud que se superpone con estas fechas', 'anaconda-hr-suite' ) );
        }

        // Crear solicitud
        $result = $this->db->create( $data );

        if ( ! $result ) {
            return new \WP_Error( 'db_error', __( 'Error al crear solicitud', 'anaconda-hr-suite' ) );
        }

        return [
            'success' => true,
            'id'      => $this->db->wpdb->insert_id,
        ];
    }

    /**
     * Obtener solicitud de ausencia
     */
    public function get_request( $id ) {
        return $this->db->get( $id );
    }

    /**
     * Obtener solicitudes de un empleado
     */
    public function get_employee_requests( $id_empleado, $args = [] ) {
        return $this->db->get_by_employee( $id_empleado, $args );
    }

    /**
     * Obtener todas las solicitudes
     */
    public function get_all_requests( $args = [] ) {
        return $this->db->get_all( $args );
    }

    /**
     * Obtener solicitudes pendientes
     */
    public function get_pending_requests() {
        return $this->db->get_pending();
    }

    /**
     * Aprobar solicitud
     */
    public function approve_request( $id, $user_id ) {
        $request = $this->db->get( $id );

        if ( ! $request ) {
            return new \WP_Error( 'not_found', __( 'Solicitud no encontrada', 'anaconda-hr-suite' ) );
        }

        if ( $request->estado !== 'pendiente' ) {
            return new \WP_Error( 'invalid_status', __( 'Solo se pueden aprobar solicitudes pendientes', 'anaconda-hr-suite' ) );
        }

        $result = $this->db->approve( $id, $user_id );

        if ( ! $result ) {
            return new \WP_Error( 'db_error', __( 'Error al aprobar solicitud', 'anaconda-hr-suite' ) );
        }

        // Enviar notificación
        $this->send_approval_notification( $request, $user_id );

        return [ 'success' => true, 'id' => $id ];
    }

    /**
     * Rechazar solicitud
     */
    public function reject_request( $id, $user_id, $reason = '' ) {
        $request = $this->db->get( $id );

        if ( ! $request ) {
            return new \WP_Error( 'not_found', __( 'Solicitud no encontrada', 'anaconda-hr-suite' ) );
        }

        if ( $request->estado !== 'pendiente' ) {
            return new \WP_Error( 'invalid_status', __( 'Solo se pueden rechazar solicitudes pendientes', 'anaconda-hr-suite' ) );
        }

        $result = $this->db->reject( $id, $user_id, $reason );

        if ( ! $result ) {
            return new \WP_Error( 'db_error', __( 'Error al rechazar solicitud', 'anaconda-hr-suite' ) );
        }

        // Enviar notificación
        $this->send_rejection_notification( $request, $user_id, $reason );

        return [ 'success' => true, 'id' => $id ];
    }

    /**
     * Verificar si hay solicitudes que se superponen
     */
    private function check_overlapping_requests( $id_empleado, $fecha_inicio, $fecha_fin ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rrhh_solicitudes_ausencia';

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id_solicitud FROM {$table} 
                WHERE id_empleado = %d 
                AND estado IN ('pendiente', 'aprobada')
                AND (
                    (fecha_inicio BETWEEN %s AND %s)
                    OR (fecha_fin BETWEEN %s AND %s)
                    OR (fecha_inicio <= %s AND fecha_fin >= %s)
                )
                LIMIT 1",
                $id_empleado,
                $fecha_inicio,
                $fecha_fin,
                $fecha_inicio,
                $fecha_fin,
                $fecha_inicio,
                $fecha_fin
            )
        );

        return ! ! $result;
    }

    /**
     * Enviar notificación de aprobación
     */
    private function send_approval_notification( $request, $approved_by_user_id ) {
        $employee = $this->employee_db->get( $request->id_empleado );

        if ( ! $employee || ! $employee->email ) {
            return;
        }

        $approver = get_user_by( 'id', $approved_by_user_id );

        $subject = __( 'Tu solicitud de vacaciones ha sido aprobada', 'anaconda-hr-suite' );
        $message = sprintf(
            __( "Hola %s,\n\nTu solicitud de vacaciones del %s al %s ha sido aprobada por %s.\n\nSaludos,\nEquipo de RRHH", 'anaconda-hr-suite' ),
            $employee->nombre,
            $request->fecha_inicio,
            $request->fecha_fin,
            $approver->display_name
        );

        wp_mail( $employee->email, $subject, $message );
    }

    /**
     * Enviar notificación de rechazo
     */
    private function send_rejection_notification( $request, $rejected_by_user_id, $reason ) {
        $employee = $this->employee_db->get( $request->id_empleado );

        if ( ! $employee || ! $employee->email ) {
            return;
        }

        $rejector = get_user_by( 'id', $rejected_by_user_id );

        $subject = __( 'Tu solicitud de vacaciones ha sido rechazada', 'anaconda-hr-suite' );
        $message = sprintf(
            __( "Hola %s,\n\nTu solicitud de vacaciones del %s al %s ha sido rechazada.\n\nMotivo: %s\n\nSaludos,\nEquipo de RRHH", 'anaconda-hr-suite' ),
            $employee->nombre,
            $request->fecha_inicio,
            $request->fecha_fin,
            $reason
        );

        wp_mail( $employee->email, $subject, $message );
    }

    /**
     * Contar solicitudes por estado
     */
    public function count_by_status( $status ) {
        return $this->db->count_by_status( $status );
    }

    /**
     * Calcular días solicitados
     */
    public function calculate_days( $fecha_inicio, $fecha_fin ) {
        $start = new \DateTime( $fecha_inicio );
        $end   = new \DateTime( $fecha_fin );

        return $start->diff( $end )->days + 1;
    }
}
