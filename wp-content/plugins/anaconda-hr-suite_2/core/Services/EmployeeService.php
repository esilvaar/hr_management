<?php
/**
 * Servicio de Empleados
 * Lógica de negocio para empleados
 */

namespace Anaconda\HRSuite\Core\Services;

use Anaconda\HRSuite\Core\DB\Empleados as EmpleadosDB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmployeeService {

    protected $db;

    public function __construct() {
        $this->db = new EmpleadosDB();
    }

    /**
     * Crear un nuevo empleado
     */
    public function create_employee( $data ) {
        // Validar datos requeridos
        if ( empty( $data['rut'] ) || empty( $data['nombre'] ) || empty( $data['apellido'] ) ) {
            return new \WP_Error( 'invalid_data', __( 'Datos incompletos', 'anaconda-hr-suite' ) );
        }

        // Verificar que el RUT sea único
        $existing = $this->db->get_by_rut( $data['rut'] );
        if ( $existing ) {
            return new \WP_Error( 'rut_exists', __( 'El RUT ya existe', 'anaconda-hr-suite' ) );
        }

        // Crear empleado
        $result = $this->db->create( $data );

        if ( ! $result ) {
            return new \WP_Error( 'db_error', __( 'Error al crear empleado', 'anaconda-hr-suite' ) );
        }

        return [
            'success' => true,
            'id'      => $this->db->wpdb->insert_id,
        ];
    }

    /**
     * Obtener empleado por ID
     */
    public function get_employee( $id ) {
        return $this->db->get( $id );
    }

    /**
     * Obtener empleado por user_id
     */
    public function get_employee_by_user( $user_id ) {
        return $this->db->get_by_user_id( $user_id );
    }

    /**
     * Obtener todos los empleados
     */
    public function get_employees( $args = [] ) {
        return $this->db->get_all( $args );
    }

    /**
     * Actualizar empleado
     */
    public function update_employee( $id, $data ) {
        $employee = $this->db->get( $id );

        if ( ! $employee ) {
            return new \WP_Error( 'not_found', __( 'Empleado no encontrado', 'anaconda-hr-suite' ) );
        }

        // Si se cambia el RUT, verificar que sea único
        if ( ! empty( $data['rut'] ) && $data['rut'] !== $employee->rut ) {
            $existing = $this->db->get_by_rut( $data['rut'] );
            if ( $existing ) {
                return new \WP_Error( 'rut_exists', __( 'El RUT ya existe', 'anaconda-hr-suite' ) );
            }
        }

        $result = $this->db->update( $id, $data );

        if ( ! $result ) {
            return new \WP_Error( 'db_error', __( 'Error al actualizar empleado', 'anaconda-hr-suite' ) );
        }

        return [ 'success' => true, 'id' => $id ];
    }

    /**
     * Eliminar empleado (soft delete)
     */
    public function delete_employee( $id ) {
        $employee = $this->db->get( $id );

        if ( ! $employee ) {
            return new \WP_Error( 'not_found', __( 'Empleado no encontrado', 'anaconda-hr-suite' ) );
        }

        $result = $this->db->delete( $id );

        if ( ! $result ) {
            return new \WP_Error( 'db_error', __( 'Error al eliminar empleado', 'anaconda-hr-suite' ) );
        }

        return [ 'success' => true, 'id' => $id ];
    }

    /**
     * Buscar empleados
     */
    public function search_employees( $search_term, $limit = 10 ) {
        return $this->db->search( $search_term, $limit );
    }

    /**
     * Contar empleados activos
     */
    public function count_active_employees() {
        return $this->db->count_active();
    }

    /**
     * Obtener empleados por departamento
     */
    public function get_employees_by_department( $department ) {
        return $this->db->get_all( [
            'departamento' => $department,
            'estado'       => 1,
        ] );
    }
}
