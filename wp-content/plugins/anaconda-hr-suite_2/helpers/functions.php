<?php
/**
 * Funciones de ayuda globales
 * Facilitan el acceso a servicios desde cualquier lugar del plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Anaconda\HRSuite\Core\Services\EmployeeService;
use Anaconda\HRSuite\Core\Services\VacationService;

/**
 * Obtener instancia del servicio de empleados
 */
function anaconda_hrsuite_employee_service() {
    static $service = null;

    if ( null === $service ) {
        $service = new EmployeeService();
    }

    return $service;
}

/**
 * Obtener instancia del servicio de vacaciones
 */
function anaconda_hrsuite_vacation_service() {
    static $service = null;

    if ( null === $service ) {
        $service = new VacationService();
    }

    return $service;
}

/**
 * Obtener empleado actual del usuario logeado
 */
function anaconda_hrsuite_get_current_employee() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $service = anaconda_hrsuite_employee_service();
    return $service->get_employee_by_user( get_current_user_id() );
}

/**
 * Verificar si el usuario actual es HR Admin
 */
function anaconda_hrsuite_is_hr_admin() {
    return current_user_can( 'manage_hrsuite' );
}

/**
 * Verificar si el usuario actual es HR Supervisor
 */
function anaconda_hrsuite_is_hr_supervisor() {
    return current_user_can( 'manage_hrsuite_employees' ) || anaconda_hrsuite_is_hr_admin();
}

/**
 * Verificar si el usuario actual es HR Employee
 */
function anaconda_hrsuite_is_hr_employee() {
    return current_user_can( 'request_vacation' );
}

/**
 * Obtener opción del plugin
 */
function anaconda_hrsuite_get_option( $option, $default = false ) {
    return get_option( 'anaconda_hrsuite_' . $option, $default );
}

/**
 * Actualizar opción del plugin
 */
function anaconda_hrsuite_update_option( $option, $value ) {
    return update_option( 'anaconda_hrsuite_' . $option, $value );
}

/**
 * Renderizar una vista de admin
 */
function anaconda_hrsuite_render_view( $view, $data = [] ) {
    $file = ANACONDA_HRSUITE_DIR . 'admin/Views/' . $view . '.php';

    if ( file_exists( $file ) ) {
        extract( $data, EXTR_SKIP );
        include $file;
    }
}

/**
 * Obtener URL de admin del plugin
 */
function anaconda_hrsuite_admin_url( $page = '', $args = [] ) {
    $url = admin_url( 'admin.php?page=anaconda-hr-suite' );

    if ( ! empty( $page ) ) {
        $url = admin_url( 'admin.php?page=' . $page );
    }

    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    return $url;
}

/**
 * Sanitizar nonce del plugin
 */
function anaconda_hrsuite_verify_nonce( $nonce_name = 'anaconda_hrsuite_nonce' ) {
    if ( empty( $_REQUEST[ $nonce_name ] ) ) {
        return false;
    }

    return wp_verify_nonce( $_REQUEST[ $nonce_name ], 'anaconda_hrsuite_nonce' );
}

/**
 * Crear nonce del plugin
 */
function anaconda_hrsuite_create_nonce() {
    return wp_create_nonce( 'anaconda_hrsuite_nonce' );
}
