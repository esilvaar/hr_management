<?php
/**
     * Roles y Capacidades del plugin HR Management
     * Gestión centralizada de roles personalizados y capabilities
     */

    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Mapeo de vistas a capabilities para granularidad por vista.
     * Usar hrm_current_user_can_view() desde templates, AJAX y REST para verificar acceso.
     */
    function hrm_get_view_capabilities()
    {
        return array(
            'dashboard' => 'view_hrm_dashboard',
            'employees_list' => 'view_hrm_employees_list',
            'employee_profile' => 'view_hrm_employee_profile',
            'employee_upload' => 'view_hrm_employee_upload',
            'employees_create' => 'create_hrm_employees',
            'vacaciones_calendar' => 'view_hrm_vacaciones_calendar',
            'vacaciones_create' => 'create_hrm_vacaciones',
            'vacaciones_approve' => 'approve_hrm_vacaciones',
            'reports' => 'view_hrm_reports',
            'documents' => 'manage_hrm_documentos',
        );
    }

    /**
     * Helper: verifica que el usuario (o el usuario actual) pueda ver una vista.
     * - $view_key: clave del mapeo devuelto por hrm_get_view_capabilities
     * - $user_id: (opcional) ID de usuario. Si no se pasa, se usa el usuario actual.
     */
    function hrm_current_user_can_view($view_key, $user_id = null)
    {
        $caps = hrm_get_view_capabilities();
        if (!isset($caps[$view_key])) {
            return false;
        }
        $cap = $caps[$view_key];

        // Si se pasa $user_id: comprobar si el usuario actual puede ver los datos del usuario objetivo
        if ($user_id) {
            $target_id = intval($user_id);
            $current_user = wp_get_current_user();

            // Caso especial: perfil de empleado
            if ($view_key === 'employee_profile') {
                // Si es su propio perfil, permitir si tiene capability propia
                if ($current_user->ID === $target_id) {
                    return current_user_can('view_hrm_own_profile') || current_user_can('view_hrm_employee_profile') || current_user_can('manage_hrm');
                }
                // Si es el perfil de otro usuario, sólo si puede ver perfiles ajenos
                return current_user_can('view_hrm_employee_profile') || current_user_can('manage_hrm');
            }

            // Para otras vistas que puedan recibir $user_id (p.e. uploads), comprobamos la capability correspondiente
            return current_user_can($cap) || current_user_can('manage_hrm');
        }

        return current_user_can($cap) || current_user_can('manage_hrm');
    }

    /**
     * Crear roles personalizados y asignar capabilities en la activación del plugin.
     */
    function hrm_create_roles()
    {
        // Definir todas las capacidades del plugin (incluye capacidades por vista para granularidad)
        $all_caps = array(
            // capacidades legacy / funcionales
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

            // capacidades por vista (granularidad)
            'view_hrm_dashboard',
            'view_hrm_employees_list',
            'view_hrm_employee_profile',
            'view_hrm_employee_upload',
            'create_hrm_employees',
            'view_hrm_vacaciones_calendar',
            'create_hrm_vacaciones',
        );

        // ================================================================
        // Asegurar que el Administrador de WordPress tenga TODO
        // ================================================================
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($all_caps as $cap) {
                if (!$admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
        }

        // Crear rol 'administrador_anaconda' (específico del plugin, no WordPress)
        // Nota: Este rol tiene permisos de empleado normal pero con capacidad especial
        // 'view_hrm_admin_views' para ver vistas de administrador
        if (!get_role('administrador_anaconda')) {
            add_role('administrador_anaconda', 'Administrador Anaconda', array(
                'read' => true,
                'upload_files' => true,
                'view_hrm_employee_admin' => true,
                'view_hrm_own_profile' => true,
                'view_hrm_admin_views' => true,  // Nueva capacidad para ver vistas de admin
                // vistas por defecto
                'view_hrm_dashboard' => true,
                'view_hrm_employees_list' => true,
                'view_hrm_employee_profile' => true,
                'view_hrm_employee_upload' => true,
                'create_hrm_employees' => true,
                'view_hrm_vacaciones_calendar' => true,
                'view_hrm_reports' => true,
                'manage_hrm_documentos' => true,
            ));
        }

        // Crear rol 'empleado' (solo acceso a su propio perfil y cosas básicas)
        if (!get_role('empleado')) {
            add_role('empleado', 'Empleado', array(
                'read' => true,
                'upload_files' => true,
                'view_hrm_employee_admin' => true,
                'view_hrm_own_profile' => true,
                // granular (solo acceso propio donde aplique)
                'view_hrm_dashboard' => true,
                'view_hrm_vacaciones_calendar' => true,
                'create_hrm_vacaciones' => true,
            ));
        }

        // Crear rol 'supervisor'
        if (!get_role('supervisor')) {
            add_role('supervisor', 'Supervisor', array(
                'read' => true,
                'upload_files' => true,
                'view_hrm_employee_admin' => true,
                'edit_hrm_employees' => true,
                'view_hrm_own_profile' => true,
                'view_hrm_admin_views' => true,
                'manage_hrm_employees' => true,
                // vistas / acciones de supervisor
                'view_hrm_dashboard' => true,
                'view_hrm_employees_list' => true,
                'view_hrm_employee_upload' => true,
                'create_hrm_employees' => true,
                'view_hrm_vacaciones_calendar' => true,
                'approve_hrm_vacaciones' => true,
            ));
        } else {
            $supervisor = get_role('supervisor');
            $supervisor->add_cap('view_hrm_admin_views');
            $supervisor->add_cap('manage_hrm_employees');
            // asegurar vistas
            $supervisor->add_cap('view_hrm_dashboard');
            $supervisor->add_cap('view_hrm_employees_list');
            $supervisor->add_cap('view_hrm_employee_upload');
            $supervisor->add_cap('create_hrm_employees');
            $supervisor->add_cap('approve_hrm_vacaciones');
        }

        // Crear rol 'editor_vacaciones'
        if (!get_role('editor_vacaciones')) {
            add_role('editor_vacaciones', 'Editor Vacaciones', array(
                'read' => true,
                'upload_files' => true,
                'manage_hrm_vacaciones' => true,
                'view_hrm_employee_admin' => true,
                'view_hrm_own_profile' => true,
                'view_hrm_admin_views' => true,
                'view_hrm_employee_upload' => true, // puede ver/gestionar documentos de empleados
            ));
        } else {
            $editor_vac = get_role('editor_vacaciones');
            $editor_vac->add_cap('view_hrm_admin_views');
            $editor_vac->add_cap('view_hrm_employee_upload');
        }

        // Asegurar que el rol 'editor' estándar de WP tenga sólo acceso personal (no vistas admin)
        $wp_editor = get_role('editor');
        if ($wp_editor) {
            // Dar acceso sólo a ver su propio perfil
            $wp_editor->add_cap('view_hrm_own_profile');
            // Retirar capacidades administrativas previas por seguridad
            if ($wp_editor->has_cap('view_hrm_admin_views')) {
                $wp_editor->remove_cap('view_hrm_admin_views');
            }
            if ($wp_editor->has_cap('view_hrm_employee_admin')) {
                $wp_editor->remove_cap('view_hrm_employee_admin');
            }
        }
    }

    /**
     * Migrar usuarios con roles antiguos al nuevo sistema.
     */
    function hrm_migrate_legacy_roles()
    {
        if (get_role('hr_employee')) {
            $users = get_users(array('role' => 'hr_employee'));
            foreach ($users as $u) {
                $u->add_role('empleado');
                $u->remove_role('hr_employee');
            }
            remove_role('hr_employee');
        }
    }

    /**
     * Asegurar que todos los roles tengan las capacidades requeridas.
     * Se ejecuta en el hook 'init' para verificación continua.
     */
    function hrm_ensure_capabilities()
    {
        // TODAS las capacidades personalizadas del plugin (incluye capacidades por vista)
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
            'manage_hrm_employees',

            // vistas
            'view_hrm_dashboard',
            'view_hrm_employees_list',
            'view_hrm_employee_profile',
            'view_hrm_employee_upload',
            'create_hrm_employees',
            'view_hrm_vacaciones_calendar',
            'create_hrm_vacaciones',
        );

        // ================================================================
        // ADMINISTRADOR (WordPress): Acceso total a TODO
        // ================================================================
        $admin = get_role('administrator');
        if ($admin) {
            // Agregar TODAS las capacidades al admin de WordPress
            foreach ($all_caps as $cap) {
                if (!$admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
            error_log('HRM: Administrator role has ALL capabilities');
        }

        // Administrador Anaconda: asegurar capacidades de empleado + capacidad especial para ver vistas admin
        $admin_anaconda = get_role('administrador_anaconda');
        if ($admin_anaconda) {
            // Capacidades básicas de empleado
            if (!$admin_anaconda->has_cap('view_hrm_employee_admin')) {
                $admin_anaconda->add_cap('view_hrm_employee_admin');
            }
            if (!$admin_anaconda->has_cap('view_hrm_own_profile')) {
                $admin_anaconda->add_cap('view_hrm_own_profile');
            }

            // Capacidad especial para ver vistas de administrador
            if (!$admin_anaconda->has_cap('view_hrm_admin_views')) {
                $admin_anaconda->add_cap('view_hrm_admin_views');
            }

            // asegurar vistas mínimas para este rol
            $admin_anaconda->add_cap('view_hrm_dashboard');
            $admin_anaconda->add_cap('view_hrm_employees_list');
            $admin_anaconda->add_cap('view_hrm_employee_profile');
            $admin_anaconda->add_cap('view_hrm_vacaciones_calendar');
            $admin_anaconda->add_cap('view_hrm_reports');
            $admin_anaconda->add_cap('manage_hrm_documentos');

            // NO agregar manage_hrm_employees - este usuario es un empleado normal con acceso especial a vistas
        }

        // Empleado
        $empleado = get_role('empleado');
        if ($empleado) {
            $empleado->add_cap('view_hrm_employee_admin');
            $empleado->add_cap('view_hrm_own_profile');
            // Asegurar capacidad mínima de WordPress para acceder al admin/profile
            if (!$empleado->has_cap('read')) {
                $empleado->add_cap('read');
            }
            if (!$empleado->has_cap('upload_files')) {
                $empleado->add_cap('upload_files');
            }
        }

        // Supervisor
        $supervisor = get_role('supervisor');
        if ($supervisor) {
            error_log('HRM: Ensuring capabilities for role: supervisor');
            // Agregar capabilities explícitamente
            if (!$supervisor->has_cap('view_hrm_employee_admin')) {
                $supervisor->add_cap('view_hrm_employee_admin');
                error_log('HRM: Added view_hrm_employee_admin to supervisor');
            }
            if (!$supervisor->has_cap('edit_hrm_employees')) {
                $supervisor->add_cap('edit_hrm_employees');
                error_log('HRM: Added edit_hrm_employees to supervisor');
            }
            if (!$supervisor->has_cap('view_hrm_admin_views')) {
                $supervisor->add_cap('view_hrm_admin_views');
                error_log('HRM: Added view_hrm_admin_views to supervisor');
            }
            if (!$supervisor->has_cap('manage_hrm_employees')) {
                $supervisor->add_cap('manage_hrm_employees');
                error_log('HRM: Added manage_hrm_employees to supervisor');
            }
            if (!$supervisor->has_cap('view_hrm_own_profile')) {
                $supervisor->add_cap('view_hrm_own_profile');
            }
            // Supervisores pueden ver lista y perfiles de empleados
            if (!$supervisor->has_cap('view_hrm_employees_list')) {
                $supervisor->add_cap('view_hrm_employees_list');
            }
            if (!$supervisor->has_cap('view_hrm_employee_profile')) {
                $supervisor->add_cap('view_hrm_employee_profile');
            }
            if (!$supervisor->has_cap('view_hrm_employee_upload')) {
                $supervisor->add_cap('view_hrm_employee_upload');
            }
            if (!$supervisor->has_cap('create_hrm_employees')) {
                $supervisor->add_cap('create_hrm_employees');
            }
            if (!$supervisor->has_cap('manage_hrm_vacaciones')) {
                $supervisor->add_cap('manage_hrm_vacaciones');
            }
            if (!$supervisor->has_cap('read')) {
                $supervisor->add_cap('read');
            }
            if (!$supervisor->has_cap('upload_files')) {
                $supervisor->add_cap('upload_files');
            }

            // Log para debug
            error_log('HRM: Supervisor capabilities updated. Has view_hrm_admin_views: ' . ($supervisor->has_cap('view_hrm_admin_views') ? 'YES' : 'NO'));
        } else {
            error_log('HRM WARNING: Supervisor role does not exist! Run hrm_create_roles()');
        }

        // Editor de Vacaciones
        $editor_vac = get_role('editor_vacaciones');
        if ($editor_vac) {
            $editor_vac->add_cap('manage_hrm_vacaciones');
            $editor_vac->add_cap('view_hrm_employee_admin');
            $editor_vac->add_cap('view_hrm_own_profile');
            // Editor de vacaciones puede ver/subir documentos de empleados, pero no ver listados completos ni perfiles ajenos
            if (!$editor_vac->has_cap('view_hrm_employee_upload')) {
                $editor_vac->add_cap('view_hrm_employee_upload');
            }
            if (!$editor_vac->has_cap('read')) {
                $editor_vac->add_cap('read');
            }
            if (!$editor_vac->has_cap('upload_files')) {
                $editor_vac->add_cap('upload_files');
            }
        }
    }

    /**
     * Forzar actualización de capabilities para usuarios con rol administrador_anaconda.
     * Se ejecuta en admin_init para asegurar que los usuarios tengan las capabilities
     * correctas incluso si fueron asignados al rol antes de que las capabilities
     * fueran definidas o actualizadas.
     * 
     * Esta función es necesaria porque WordPress no siempre aplica las capabilities
     * del rol a los usuarios existentes cuando el rol es modificado.
     */
    function hrm_force_update_user_capabilities()
    {
        // Solo ejecutar en el área administrativa
        if (!is_admin()) {
            return;
        }

        // Solo ejecutar para usuarios autenticados
        if (!is_user_logged_in()) {
            return;
        }

        $current_user = wp_get_current_user();

        // Solo procesar usuarios con rol administrador_anaconda
        if (!in_array('administrador_anaconda', (array) $current_user->roles, true)) {
            return;
        }

        // Verificar si ya tiene todas las capabilities necesarias
        $required_caps = array(
            'view_hrm_admin_views',
            'view_hrm_employee_admin',
            'view_hrm_own_profile',
            // vistas mínimas
            'view_hrm_dashboard',
            'view_hrm_employees_list',
            'view_hrm_employee_profile',
            'view_hrm_employee_upload',
            'create_hrm_employees',
        );

        $needs_update = false;
        foreach ($required_caps as $cap) {
            if (!$current_user->has_cap($cap)) {
                $needs_update = true;
                break;
            }
        }

        // Si ya tiene todas las capabilities, no hacer nada
        if (!$needs_update) {
            return;
        }

        // Agregar las capabilities faltantes
        foreach ($required_caps as $cap) {
            if (!$current_user->has_cap($cap)) {
                $current_user->add_cap($cap);
            }
        }

        error_log('[HRM] Capabilities updated for user_id=' . intval($current_user->ID) . ' (administrador_anaconda)');
    }
    add_action('admin_init', 'hrm_force_update_user_capabilities', 1);
