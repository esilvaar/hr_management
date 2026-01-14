<?php
/**
 * Gestor de Roles y Capacidades
 */

namespace Anaconda\HRSuite\Core\Roles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RoleManager {

    /**
     * Crear roles personalizados
     */
    public static function create_roles() {
        // Rol: HR Administrator
        if ( ! get_role( 'hr_admin' ) ) {
            add_role(
                'hr_admin',
                __( 'HR Administrator', 'anaconda-hr-suite' ),
                self::get_admin_capabilities()
            );
        }

        // Rol: HR Supervisor
        if ( ! get_role( 'hr_supervisor' ) ) {
            add_role(
                'hr_supervisor',
                __( 'HR Supervisor', 'anaconda-hr-suite' ),
                self::get_supervisor_capabilities()
            );
        }

        // Rol: HR Employee
        if ( ! get_role( 'hr_employee' ) ) {
            add_role(
                'hr_employee',
                __( 'HR Employee', 'anaconda-hr-suite' ),
                self::get_employee_capabilities()
            );
        }
    }

    /**
     * Asegurar que todos los roles tengan capacidades correctas
     */
    public static function ensure_capabilities() {
        // Admin obtiene todas las capacidades
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            foreach ( self::get_admin_capabilities() as $cap => $grant ) {
                $admin_role->add_cap( $cap );
            }
        }

        // HR Admin
        $hr_admin = get_role( 'hr_admin' );
        if ( $hr_admin ) {
            foreach ( self::get_admin_capabilities() as $cap => $grant ) {
                if ( $grant ) {
                    $hr_admin->add_cap( $cap );
                }
            }
        }

        // HR Supervisor
        $hr_supervisor = get_role( 'hr_supervisor' );
        if ( $hr_supervisor ) {
            foreach ( self::get_supervisor_capabilities() as $cap => $grant ) {
                if ( $grant ) {
                    $hr_supervisor->add_cap( $cap );
                }
            }
        }

        // HR Employee
        $hr_employee = get_role( 'hr_employee' );
        if ( $hr_employee ) {
            foreach ( self::get_employee_capabilities() as $cap => $grant ) {
                if ( $grant ) {
                    $hr_employee->add_cap( $cap );
                }
            }
        }
    }

    /**
     * Capacidades de HR Admin
     */
    private static function get_admin_capabilities() {
        return [
            'read'                          => true,
            'manage_hrsuite'                => true,
            'manage_hrsuite_employees'      => true,
            'manage_hrsuite_vacations'      => true,
            'manage_hrsuite_documents'      => true,
            'approve_hrsuite_vacations'     => true,
            'edit_hrsuite_employees'        => true,
            'delete_hrsuite_employees'      => true,
            'view_hrsuite_reports'          => true,
            'manage_hrsuite_settings'       => true,
        ];
    }

    /**
     * Capacidades de HR Supervisor
     */
    private static function get_supervisor_capabilities() {
        return [
            'read'                          => true,
            'view_hrsuite_dashboard'        => true,
            'manage_hrsuite_employees'      => true,
            'view_hrsuite_employees'        => true,
            'manage_hrsuite_vacations'      => true,
            'approve_hrsuite_vacations'     => true,
            'view_hrsuite_reports'          => true,
            'request_vacation'              => true,
        ];
    }

    /**
     * Capacidades de HR Employee
     */
    private static function get_employee_capabilities() {
        return [
            'read'                          => true,
            'view_own_profile'              => true,
            'edit_own_profile'              => true,
            'request_vacation'              => true,
            'view_own_vacations'            => true,
            'view_own_documents'            => true,
            'upload_documents'              => true,
        ];
    }
}
