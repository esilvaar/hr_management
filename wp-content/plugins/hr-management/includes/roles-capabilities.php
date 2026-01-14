<?php
/**
 * Roles y Capacidades del plugin HR Management
 * Gestión centralizada de roles personalizados y capabilities
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crear roles personalizados y asignar capabilities en la activación del plugin.
 */
function hrm_create_roles() {
    // Definir todas las capacidades del plugin
    $all_caps = array(
        'view_hrm_employee_admin',
        'edit_hrm_employees',
        'view_hrm_own_profile',
        'manage_hrm_vacaciones',
        'manage_hrm',
        'view_hrm_admin_views',
        'edit_hrm_vacaciones',
        'delete_hrm_vacaciones',
        'approve_hrm_vacaciones',
        'view_hrm_reports',
        'manage_hrm_documentos',
    );
    
    // ================================================================
    // Asegurar que el Administrador de WordPress tenga TODO
    // ================================================================
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        foreach ( $all_caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }
    
    // Crear rol 'administrador_anaconda' (específico del plugin, no WordPress)
    // Nota: Este rol tiene permisos de empleado normal pero con capacidad especial
    // 'view_hrm_admin_views' para ver vistas de administrador
    if ( ! get_role( 'administrador_anaconda' ) ) {
        add_role( 'administrador_anaconda', 'Administrador Anaconda', array(
            'read' => true,
            'upload_files' => true,
            'view_hrm_employee_admin' => true,
            'view_hrm_own_profile' => true,
            'view_hrm_admin_views' => true,  // Nueva capacidad para ver vistas de admin
        ) );
    }

    // Crear rol 'empleado'
    if ( ! get_role( 'empleado' ) ) {
        add_role( 'empleado', 'Empleado', array(
            'read' => true,
            'upload_files' => true,
            'view_hrm_employee_admin' => true,
            'view_hrm_own_profile' => true,
        ) );
    }

    // Crear rol 'supervisor'
    if ( ! get_role( 'supervisor' ) ) {
        add_role( 'supervisor', 'Supervisor', array(
            'read' => true,
            'upload_files' => true,
            'view_hrm_employee_admin' => true,
            'edit_hrm_employees' => true,
            'view_hrm_own_profile' => true,
        ) );
    }

    // Crear rol 'editor_vacaciones'
    if ( ! get_role( 'editor_vacaciones' ) ) {
        add_role( 'editor_vacaciones', 'Editor Vacaciones', array(
            'read' => true,
            'upload_files' => true,
            'manage_hrm_vacaciones' => true,
            'view_hrm_employee_admin' => true,
            'view_hrm_own_profile' => true,
        ) );
    }
}

/**
 * Migrar usuarios con roles antiguos al nuevo sistema.
 */
function hrm_migrate_legacy_roles() {
    if ( get_role( 'hr_employee' ) ) {
        $users = get_users( array( 'role' => 'hr_employee' ) );
        foreach ( $users as $u ) {
            $u->add_role( 'empleado' );
            $u->remove_role( 'hr_employee' );
        }
        remove_role( 'hr_employee' );
    }
}

/**
 * Asegurar que todos los roles tengan las capacidades requeridas.
 * Se ejecuta en el hook 'init' para verificación continua.
 */
function hrm_ensure_capabilities() {
    // TODAS las capacidades personalizadas del plugin
    $all_caps = array(
        'view_hrm_employee_admin',
        'edit_hrm_employees',
        'view_hrm_own_profile',
        'manage_hrm_vacaciones',
        'manage_hrm',
        'view_hrm_admin_views',
        'edit_hrm_vacaciones',
        'delete_hrm_vacaciones',
        'approve_hrm_vacaciones',
        'view_hrm_reports',
        'manage_hrm_documentos',
    );

    // ================================================================
    // ADMINISTRADOR (WordPress): Acceso total a TODO
    // ================================================================
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        // Agregar TODAS las capacidades al admin de WordPress
        foreach ( $all_caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
        error_log( 'HRM: Administrator role has ALL capabilities' );
    }

    // Administrador Anaconda: asegurar capacidades de empleado + capacidad especial para ver vistas admin
    $admin_anaconda = get_role( 'administrador_anaconda' );
    if ( $admin_anaconda ) {
        // Capacidades básicas de empleado
        if ( ! $admin_anaconda->has_cap( 'view_hrm_employee_admin' ) ) {
            $admin_anaconda->add_cap( 'view_hrm_employee_admin' );
        }
        if ( ! $admin_anaconda->has_cap( 'view_hrm_own_profile' ) ) {
            $admin_anaconda->add_cap( 'view_hrm_own_profile' );
        }
        
        // Capacidad especial para ver vistas de administrador
        if ( ! $admin_anaconda->has_cap( 'view_hrm_admin_views' ) ) {
            $admin_anaconda->add_cap( 'view_hrm_admin_views' );
        }
        
        // NO agregar manage_hrm_employees - este usuario es un empleado normal con acceso especial a vistas
    }

    // Empleado
    $empleado = get_role( 'empleado' );
    if ( $empleado ) {
        $empleado->add_cap( 'view_hrm_employee_admin' );
        $empleado->add_cap( 'view_hrm_own_profile' );
        // Asegurar capacidad mínima de WordPress para acceder al admin/profile
        if ( ! $empleado->has_cap( 'read' ) ) {
            $empleado->add_cap( 'read' );
        }
        if ( ! $empleado->has_cap( 'upload_files' ) ) {
            $empleado->add_cap( 'upload_files' );
        }
    }

    // Supervisor
    $supervisor = get_role( 'supervisor' );
    if ( $supervisor ) {
        // Agregar capabilities explícitamente
        if ( ! $supervisor->has_cap( 'view_hrm_employee_admin' ) ) {
            $supervisor->add_cap( 'view_hrm_employee_admin' );
        }
        if ( ! $supervisor->has_cap( 'edit_hrm_employees' ) ) {
            $supervisor->add_cap( 'edit_hrm_employees' );
        }
        if ( ! $supervisor->has_cap( 'view_hrm_own_profile' ) ) {
            $supervisor->add_cap( 'view_hrm_own_profile' );
        }
        if ( ! $supervisor->has_cap( 'manage_hrm_vacaciones' ) ) {
            $supervisor->add_cap( 'manage_hrm_vacaciones' );
        }
        if ( ! $supervisor->has_cap( 'read' ) ) {
            $supervisor->add_cap( 'read' );
        }
        if ( ! $supervisor->has_cap( 'upload_files' ) ) {
            $supervisor->add_cap( 'upload_files' );
        }
        
        // Log para debug
        error_log( 'HRM: Supervisor capabilities updated. Has edit_hrm_employees: ' . ($supervisor->has_cap( 'edit_hrm_employees' ) ? 'YES' : 'NO') );
    } else {
        error_log( 'HRM WARNING: Supervisor role does not exist! Run hrm_create_roles()' );
    }

    // Editor de Vacaciones
    $editor_vac = get_role( 'editor_vacaciones' );
    if ( $editor_vac ) {
        $editor_vac->add_cap( 'manage_hrm_vacaciones' );
        $editor_vac->add_cap( 'view_hrm_employee_admin' );
        $editor_vac->add_cap( 'view_hrm_own_profile' );
        if ( ! $editor_vac->has_cap( 'read' ) ) {
            $editor_vac->add_cap( 'read' );
        }
        if ( ! $editor_vac->has_cap( 'upload_files' ) ) {
            $editor_vac->add_cap( 'upload_files' );
        }
    }
}
