<?php
/**
 * Controlador de Empleados
 */

namespace Anaconda\HRSuite\Admin\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmployeesController {

    /**
     * Renderizar listado de empleados
     */
    public function render_list() {
        // Verificar permisos
        if ( ! current_user_can( 'manage_hrsuite_employees' ) && ! current_user_can( 'manage_hrsuite' ) ) {
            wp_die( __( 'No tienes permiso para acceder', 'anaconda-hr-suite' ) );
        }

        // Procesar formulario si se envió
        if ( isset( $_POST['action'] ) && 'save_employee' === $_POST['action'] ) {
            if ( ! anaconda_hrsuite_verify_nonce() ) {
                wp_die( __( 'Nonce inválido', 'anaconda-hr-suite' ) );
            }

            $this->handle_save_employee();
        }

        // Procesar eliminación
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['employee_id'] ) ) {
            if ( ! anaconda_hrsuite_verify_nonce( 'anaconda_hrsuite_nonce' ) ) {
                wp_die( __( 'Nonce inválido', 'anaconda-hr-suite' ) );
            }

            $this->handle_delete_employee( absint( $_GET['employee_id'] ) );
        }

        // Obtener datos
        $service = anaconda_hrsuite_employee_service();
        $employees = $service->get_employees();

        // Renderizar vista
        anaconda_hrsuite_render_view( 'employees-list', [
            'employees' => $employees,
        ] );
    }

    /**
     * Renderizar formulario de crear/editar empleado
     */
    public function render_form() {
        // Verificar permisos
        if ( ! current_user_can( 'manage_hrsuite_employees' ) && ! current_user_can( 'manage_hrsuite' ) ) {
            wp_die( __( 'No tienes permiso para acceder', 'anaconda-hr-suite' ) );
        }

        $employee = null;

        if ( isset( $_GET['employee_id'] ) ) {
            $service = anaconda_hrsuite_employee_service();
            $employee = $service->get_employee( absint( $_GET['employee_id'] ) );

            if ( ! $employee ) {
                wp_die( __( 'Empleado no encontrado', 'anaconda-hr-suite' ) );
            }
        }

        anaconda_hrsuite_render_view( 'employees-form', [
            'employee' => $employee,
        ] );
    }

    /**
     * Guardar empleado
     */
    private function handle_save_employee() {
        $service = anaconda_hrsuite_employee_service();

        $data = [
            'rut'              => sanitize_text_field( $_POST['rut'] ?? '' ),
            'nombre'           => sanitize_text_field( $_POST['nombre'] ?? '' ),
            'apellido'         => sanitize_text_field( $_POST['apellido'] ?? '' ),
            'email'            => sanitize_email( $_POST['email'] ?? '' ),
            'telefono'         => sanitize_text_field( $_POST['telefono'] ?? '' ),
            'fecha_nacimiento' => sanitize_text_field( $_POST['fecha_nacimiento'] ?? '' ),
            'fecha_ingreso'    => sanitize_text_field( $_POST['fecha_ingreso'] ?? '' ),
            'departamento'     => sanitize_text_field( $_POST['departamento'] ?? '' ),
            'puesto'           => sanitize_text_field( $_POST['puesto'] ?? '' ),
            'tipo_contrato'    => sanitize_text_field( $_POST['tipo_contrato'] ?? '' ),
            'salario'          => sanitize_text_field( $_POST['salario'] ?? '' ),
            'estado'           => isset( $_POST['estado'] ) ? 1 : 0,
        ];

        if ( isset( $_POST['employee_id'] ) && ! empty( $_POST['employee_id'] ) ) {
            // Actualizar
            $result = $service->update_employee( absint( $_POST['employee_id'] ), $data );
        } else {
            // Crear
            $result = $service->create_employee( $data );
        }

        if ( is_wp_error( $result ) ) {
            wp_die( $result->get_error_message() );
        }

        wp_safe_remote_post( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-employees' ), [
            'blocking' => false,
        ] );

        wp_redirect( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-employees' ) );
        exit;
    }

    /**
     * Eliminar empleado
     */
    private function handle_delete_employee( $employee_id ) {
        $service = anaconda_hrsuite_employee_service();
        $result = $service->delete_employee( $employee_id );

        if ( is_wp_error( $result ) ) {
            wp_die( $result->get_error_message() );
        }

        wp_redirect( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-employees' ) );
        exit;
    }
}
