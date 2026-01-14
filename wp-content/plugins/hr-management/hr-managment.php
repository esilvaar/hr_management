<?php
/**
 * Plugin Name: HR Management
 * Plugin URI: https://example.com
 * Description: Plugin de gestión de Recursos Humanos. Arquitectura modular para empleados, vacaciones y más.
 * Version: 2.1.0
 * Author: Practicantes Anacondaweb
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ============================================================================
 * 1. CONSTANTES
 * ============================================================================
 */
define( 'HRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HRM_PLUGIN_VERSION', '2.1.0' );

/**
 * ============================================================================
 * 2. CARGA DE DEPENDENCIAS
 * ============================================================================
 * Se usa un archivo centralizado (includes/load.php) para simplificar
 * el mantenimiento y control de dependencias.
 */

require_once HRM_PLUGIN_DIR . 'includes/load.php';

// TEMPORAL: Forzar reparación de capacidades del supervisor (se ejecuta una sola vez)
// Cargar fix temporal si existe (evitar fatal si el archivo fue eliminado)
if ( file_exists( HRM_PLUGIN_DIR . 'force-fix-supervisor.php' ) ) {
    require_once HRM_PLUGIN_DIR . 'force-fix-supervisor.php';
}

// Cargar módulo de debug
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    require_once HRM_PLUGIN_DIR . 'debug.php';
}

 /* FIX TEMPORAL: Forzar carga de vacaciones.php en ADMIN
 * ============================================================
 * Garantiza que las funciones de vacaciones estén disponibles
 * cuando se renderizan las páginas del panel administrativo.
 */
add_action( 'admin_init', function () {
    require_once HRM_PLUGIN_DIR . 'includes/vacaciones.php';
});

// TEMPORAL: Cargar herramientas de verificación de capacidades (solo para admin)
add_action( 'admin_menu', function() {
    if ( current_user_can( 'manage_options' ) ) {
        if ( file_exists( HRM_PLUGIN_DIR . 'check-supervisor-caps.php' ) ) {
            require_once HRM_PLUGIN_DIR . 'check-supervisor-caps.php';
        }
        if ( file_exists( HRM_PLUGIN_DIR . 'fix-supervisor-caps.php' ) ) {
            require_once HRM_PLUGIN_DIR . 'fix-supervisor-caps.php';
        }
    }
} );


//----





/**
 * ============================================================================
 * 3. HOOKS DE ACTIVACIÓN Y DESACTIVACIÓN
 * ============================================================================
 */

/**
 * Ejecutar funciones de setup cuando se activa el plugin.
 */
function hrm_activate_plugin() {
    hrm_create_roles();
    hrm_migrate_legacy_roles();
    hrm_ensure_capabilities();
    
    // Registrar evento cron para sincronización diaria de personal vigente
    hrm_schedule_daily_personal_vigente_sync();
}
register_activation_hook( __FILE__, 'hrm_activate_plugin' );

/**
 * Limpiar eventos cron al desactivar el plugin.
 */
function hrm_deactivate_plugin() {
    // Desregistrar evento cron si existe
    $timestamp = wp_next_scheduled( 'hrm_daily_personal_vigente_sync' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'hrm_daily_personal_vigente_sync' );
    }
}
register_deactivation_hook( __FILE__, 'hrm_deactivate_plugin' );

/**
 * ============================================================================
 * REDIRECCIÓN AL LOGIN PARA USUARIOS NO-ADMIN
 * ============================================================================
 */

/**
 * Redirigir a los usuarios después del login según su rol.
 * - Administrators: dashboard por defecto
 * - administrador_anaconda: vista de empleados del plugin (admin views)
 * - Otros usuarios: su perfil del plugin
 */
function hrm_redirect_non_admin_after_login( $redirect_to, $request, $user ) {
    // Si hay error en el login, no redirigir
    if ( isset( $user->errors ) && ! empty( $user->errors ) ) {
        return $redirect_to;
    }
    
    // Si no es un objeto de usuario válido, no redirigir
    if ( ! is_a( $user, 'WP_User' ) ) {
        return $redirect_to;
    }
    
    // Si es administrador de WordPress, dejar el comportamiento por defecto (dashboard)
    if ( in_array( 'administrator', (array) $user->roles ) ) {
        return $redirect_to;
    }
    
    // Si es administrador_anaconda, redirigir a la vista de empleados del plugin (admin views)
    if ( in_array( 'administrador_anaconda', (array) $user->roles ) ) {
        return admin_url( 'admin.php?page=hrm-empleados' );
    }
    
    // Para cualquier otro usuario, redirigir a su perfil
    return admin_url( 'admin.php?page=hrm-mi-perfil-info' );
}
add_filter( 'login_redirect', 'hrm_redirect_non_admin_after_login', 10, 3 );

/**
 * ============================================================================
 * 4. INICIALIZACIÓN DEL PLUGIN (Init Hook)
 * ============================================================================
 */

/**
 * Enqueue de estilos y scripts del plugin en el área administrativa
 */
function hrm_enqueue_admin_assets() {
    global $pagenow;
    
    // Cargar assets solo en páginas del plugin
    if ( $pagenow === 'admin.php' && isset( $_GET['page'] ) ) {
        $page = sanitize_text_field( $_GET['page'] );
        
        // Estilos de sidebar/layout y JS específicos (todas las páginas HRM)
        if ( strpos( $page, 'hrm' ) === 0 || $page === 'hr-management' ) {
            // CSS general del plugin
            wp_enqueue_style(
                'hrm-style',
                HRM_PLUGIN_URL . 'assets/css/plugin-base.css',
                array(),
                HRM_PLUGIN_VERSION
            );
            
            // CSS del sidebar - Layout
            wp_enqueue_style(
                'hrm-admin-sidebar',
                HRM_PLUGIN_URL . 'assets/css/layout-sidebar-admin.css',
                array(),
                HRM_PLUGIN_VERSION
            );

            // CSS del sidebar - Navegación
            wp_enqueue_style(
                'hrm-sidebar-navigation',
                HRM_PLUGIN_URL . 'assets/css/sidebar-nav.css',
                array( 'hrm-admin-sidebar' ),
                HRM_PLUGIN_VERSION
            );

            // CSS del sidebar - Dark Theme
            wp_enqueue_style(
                'hrm-sidebar-dark',
                HRM_PLUGIN_URL . 'assets/css/sidebar-theme-dark.css',
                array( 'hrm-admin-sidebar' ),
                HRM_PLUGIN_VERSION
            );

            // CSS del sidebar - Responsive
            wp_enqueue_style(
                'hrm-sidebar-responsive',
                HRM_PLUGIN_URL . 'assets/css/sidebar-responsive.css',
                array( 'hrm-admin-sidebar' ),
                HRM_PLUGIN_VERSION
            );

            // JS del sidebar - Responsive
            wp_enqueue_script(
                'hrm-sidebar-responsive',
                HRM_PLUGIN_URL . 'assets/js/sidebar-responsive.js',
                array(),
                HRM_PLUGIN_VERSION,
                true
            );

            // CSS para vista de detalle de empleados
            wp_enqueue_style(
                'hrm-employees-detail',
                HRM_PLUGIN_URL . 'assets/css/employees-detail.css',
                array(),
                HRM_PLUGIN_VERSION
            );

            // JS para vista de detalle de empleados
            wp_enqueue_script(
                'hrm-employees-detail',
                HRM_PLUGIN_URL . 'assets/js/employees-detail.js',
                array(),
                HRM_PLUGIN_VERSION,
                true
            );

            // Pasar datos al script de employees-detail
            wp_localize_script(
                'hrm-employees-detail',
                'hrmEmployeeData',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'departamentos' => isset($GLOBALS['hrm_departamentos']) ? $GLOBALS['hrm_departamentos'] : array()
                )
            );

            // JS usado en crear empleados (se carga solo si existe la vista)
            wp_enqueue_script(
                'hrm-employees-create',
                HRM_PLUGIN_URL . 'assets/js/employees-create.js',
                array(),
                HRM_PLUGIN_VERSION,
                true
            );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_admin_assets' );

/**
 * ============================================================================
 * MODO PANTALLA COMPLETA
 * ============================================================================
 * Permite ocultar el menú de administración de WordPress para
 * mostrar el contenido del plugin en pantalla completa.
 * Uso: Agregar &fullscreen=1 a la URL
 */

/**
 * Ocultar la barra de administración en modo pantalla completa.
 */
function hrm_hide_admin_bar_fullscreen() {
    if ( isset( $_GET['fullscreen'] ) && $_GET['fullscreen'] == '1' ) {
        show_admin_bar( false );
    }
}
add_action( 'init', 'hrm_hide_admin_bar_fullscreen' );

/**
 * Agregar clase CSS al body para ocultar elementos en pantalla completa.
 */
function hrm_add_fullscreen_body_class( $classes ) {
    if ( isset( $_GET['fullscreen'] ) && $_GET['fullscreen'] == '1' ) {
        $classes .= ' hrm-fullscreen-mode';
    }
    return $classes;
}
add_filter( 'admin_body_class', 'hrm_add_fullscreen_body_class' );

/**
 * Enqueue CSS para modo pantalla completa.
 */
function hrm_enqueue_fullscreen_styles() {
    // Cargar assets solo en páginas del plugin
    if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'hrm' ) === 0 ) {
        // Siempre cargar el CSS (tiene estilos para el botón)
        wp_enqueue_style(
            'hrm-fullscreen',
            HRM_PLUGIN_URL . 'assets/css/fullscreen.css',
            array(),
            HRM_PLUGIN_VERSION
        );
        
        // Siempre cargar el JS (para el botón toggle)
        wp_enqueue_script(
            'hrm-fullscreen',
            HRM_PLUGIN_URL . 'assets/js/fullscreen.js',
            array(),
            HRM_PLUGIN_VERSION,
            true
        );
        
        // Pasar datos del usuario actual al JS
        wp_localize_script(
            'hrm-fullscreen',
            'hrmFullscreenData',
            array(
                'userId' => get_current_user_id(),
            )
        );
    }
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_fullscreen_styles' );

/**
 * Verificación continua de capacidades en cada carga.
 * Se ejecuta en 'init' con prioridad 20 para asegurar que los roles
 * ya existen (add_role() se ejecuta en 'init' con prioridad 0).
 */
add_action( 'init', 'hrm_ensure_capabilities', 20 );

/**
 * ============================================================================
 * BLOQUEO DE USUARIOS INACTIVOS
 * ============================================================================
 * Impide el inicio de sesión de empleados con estado=0 (Inactivo)
 * Solo los administradores pueden activar/desactivar usuarios
 */

/**
 * Bloquear inicio de sesión de empleados inactivos.
 */
function hrm_block_inactive_employee_login( $user, $password ) {
    // Si ya hay un error, retornar
    if ( is_wp_error( $user ) ) {
        return $user;
    }
    
    // Si no es un objeto de usuario válido, retornar
    if ( ! is_a( $user, 'WP_User' ) ) {
        return $user;
    }
    
    // Buscar el empleado asociado a este usuario
    global $wpdb;
    $table = $wpdb->prefix . 'rrhh_empleados';
    
    $employee = $wpdb->get_row( $wpdb->prepare(
        "SELECT id_empleado as id, estado, nombre, apellido FROM $table WHERE user_id = %d LIMIT 1",
        $user->ID
    ) );
    
    // Si no hay empleado asociado, permitir acceso
    if ( ! $employee ) {
        return $user;
    }
    
    // Si el empleado está inactivo (estado = 0), bloquear acceso
    if ( isset( $employee->estado ) && intval( $employee->estado ) === 0 ) {
        return new WP_Error(
            'employee_inactive',
            sprintf(
                '<strong>Acceso Denegado:</strong> Tu cuenta de empleado ha sido desactivada. Por favor contacta al administrador de Recursos Humanos para más información.',
                esc_html( $employee->nombre . ' ' . $employee->apellido )
            )
        );
    }
    
    return $user;
}
add_filter( 'wp_authenticate_user', 'hrm_block_inactive_employee_login', 10, 2 );

/**
 * Registrar shortcodes del plugin.
 */
add_action( 'init', function() {
    add_shortcode( 'hrm_solicitud_vacaciones', 'hrm_render_formulario_vacaciones_shortcode' );
    add_shortcode( 'hrm_mis_vacaciones', 'hrm_render_vacaciones_empleado_page' );
}, 10 );

/**
 * ============================================================================
 * 5. RENDERIZADORES (PAGE CALLBACKS)
 * ============================================================================
 */

/**
 * Renderizar página de vacaciones para admin.
 */
function hrm_render_vacaciones_admin_page() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'view_hrm_admin_views' ) && ! current_user_can( 'manage_hrm_vacaciones' ) ) {
        wp_die( __( 'No tienes permisos para ver esta página.', 'hr-management' ), __( 'Acceso denegado', 'hr-management' ), array( 'response' => 403 ) );
    }

    $search = sanitize_text_field( $_GET['empleado'] ?? '' );
$solicitudes = hrm_get_all_vacaciones( $search );

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part(
                'vacaciones-admin',
                '',
                [ 'solicitudes' => $solicitudes ]
            );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}


/**
 * Renderizar página de vacaciones para empleado.
 */
function hrm_render_vacaciones_empleado_page() {
    if ( ! is_user_logged_in() ) {
        echo '<p>Debes iniciar sesión para ver tus vacaciones.</p>';
        return;
    }
    
    // Obtener el user_id actual y limpiar caché para evitar datos viejos
    $current_user_id = get_current_user_id();

    wp_cache_delete( 'hrm_vacaciones_empleado_' . $current_user_id );
    
    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part( 'vacaciones-empleado', '', [ 'current_user_id' => $current_user_id ] );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar shortcode de formulario de vacaciones.
 */
function hrm_render_formulario_vacaciones_shortcode() {
    ob_start();
    hrm_get_template_part( 'vacaciones-form' );
    return ob_get_clean();
}

/**
 * ============================================================================
 * RENDERIZADORES DE PERFIL PARA SUBMENÚS
 * ============================================================================
 */

/**
 * Renderizar vista general del perfil del usuario actual.
 */
function hrm_render_profile_overview() {
    if ( ! is_user_logged_in() ) {
        echo '<p>Debes iniciar sesión para ver tu perfil.</p>';
        return;
    }

    if ( ! class_exists( 'HRM_DB_Empleados' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/db/class-hrm-db-empleados.php';
    }

    $db_emp = new HRM_DB_Empleados();
    $employee = $db_emp->get_by_user_id( get_current_user_id() );

    if ( ! $employee ) {
        echo '<div class="wrap">';
        echo '<p class="notice notice-warning">No se encontró un registro de empleado vinculado a tu usuario.</p>';
        echo '<p><strong>Debug Info:</strong></p>';
        echo '<ul>';
        echo '<li>User ID actual: ' . get_current_user_id() . '</li>';
        echo '<li>User login: ' . wp_get_current_user()->user_login . '</li>';
        echo '<li>Es admin: ' . (current_user_can('manage_options') ? 'Sí' : 'No') . '</li>';
        echo '</ul>';
        echo '</div>';
        return;
    }

    // Cargar configuración de departamentos, puestos y tipos de contrato
    $hrm_departamentos = apply_filters( 'hrm_departamentos', array(
        'Administración',
        'Ventas',
        'Desarrollo',
        'Marketing',
        'Soporte',
        'Recursos Humanos',
        'Gerencia',
        'Sistemas'
    ));

    $hrm_puestos = apply_filters( 'hrm_puestos', array(
        'Gerente',
        'Analista',
        'Desarrollador',
        'Diseñador',
        'Asistente',
        'Coordinador'
    ));

    $hrm_tipos_contrato = apply_filters( 'hrm_tipos_contrato', array(
        'Indefinido',
        'Plazo Fijo',
        'Por Proyecto',
        'Prácticas'
    ));

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            // Usar el template employees-detail.php en lugar de profile-overview
            hrm_get_template_part( 'Administrador/employees-detail', '', compact( 'employee', 'hrm_departamentos', 'hrm_puestos', 'hrm_tipos_contrato' ) );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar formulario de edición del perfil del usuario actual.
 */
function hrm_render_profile_edit() {
    if ( ! current_user_can( 'view_hrm_own_profile' ) ) {
        $cu = wp_get_current_user();
        $roles = ! empty( $cu->roles ) ? implode( ', ', $cu->roles ) : '(sin roles)';
        $caps = array_keys( array_filter( (array) $cu->allcaps ) );
        $caps_list = ! empty( $caps ) ? implode( ', ', $caps ) : '(sin capabilities)';

        echo '<div class="wrap"><h1>Acceso denegado</h1>';
        echo '<div class="notice notice-error"><p>No tienes permiso para editar tu perfil.</p></div>';
        echo '<h2>Diagnóstico (temporal)</h2>';
        echo '<p><strong>Usuario:</strong> ' . esc_html( $cu->user_login ) . ' (ID: ' . intval( $cu->ID ) . ')</p>';
        echo '<p><strong>Roles:</strong> ' . esc_html( $roles ) . '</p>';
        echo '<p><strong>Capabilities:</strong> ' . esc_html( $caps_list ) . '</p>';
        echo '<p>Comprueba que tu rol tenga <code>view_hrm_own_profile</code> para poder editar.</p>';
        echo '</div>';
        return;
    }

    $db_emp = new HRM_DB_Empleados();
    $employee = $db_emp->get_by_user_id( get_current_user_id() );

    if ( ! $employee ) {
        echo '<div class="wrap"><p class="notice notice-warning">No se encontró un registro de empleado vinculado a tu usuario.</p></div>';
        return;
    }

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part( 'profile-edit-form' );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * ============================================================================
 * 6. REGISTRO DE MENÚS ADMIN
 * ============================================================================
 */

/**
 * Registrar menú principal y submenús del plugin.
 */
function hrm_register_admin_menus() {
    // Determinar si el usuario tiene acceso a vistas de administrador
    // Puede ser por manage_hrm_employees O por el rol administrador_anaconda con view_hrm_admin_views
    $has_admin_access = current_user_can( 'manage_hrm_employees' ) || current_user_can( 'view_hrm_admin_views' );
    
    // MENÚ PRINCIPAL: HR Management (solo para admins con manage_hrm_employees o view_hrm_admin_views)
    if ( current_user_can( 'manage_options' ) || $has_admin_access ) {
        add_menu_page(
            'HR Management',
            'HR Management',
            'view_hrm_employee_admin',  // Capacidad mínima requerida
            'hrm-empleados',
            'hrm_render_employees_admin_page',
            'dashicons-businessman',
            6
        );

        // Submenú: Empleados (primer submenú, para que no se duplique el título)
        add_submenu_page(
            'hrm-empleados',
            'Empleados',
            'Empleados',
            'view_hrm_employee_admin',
            'hrm-empleados',
            'hrm_render_employees_admin_page'
        );

        // Submenú: Vacaciones
        add_submenu_page(
            'hrm-empleados',
            'Vacaciones',
            'Vacaciones',
            'view_hrm_admin_views',  // Cambiar a capacidad especial
            'hrm-vacaciones',
            'hrm_render_vacaciones_admin_page'
        );

        // Submenú: Ver/Editar Solicitud (oculto pero accesible por URL)
        add_submenu_page(
            'hrm-empleados',
            'Solicitud de Vacaciones',
            '',  // No mostrar en el menú
            'view_hrm_admin_views',
            'hrm-vacaciones-formulario',
            'hrm_render_formulario_solicitud_page'
        );

        // Submenú: Reglamento Interno
        add_submenu_page(
            'hrm-empleados',
            'Reglamento Interno',
            'Reglamento Interno',
            'view_hrm_admin_views',
            'hrm-reglamento-interno',
            'hrm_render_reglamento_interno_page'
        );

        // // Submenú: Roles y Usuarios (Opcional)
        // if ( function_exists( 'rrhh_add_user_page' ) ) {
        //     add_submenu_page(
        //         'hrm-empleados',
        //         'Roles y Usuarios',
        //         'Roles y Usuarios',
        //         'manage_options',
        //         'hrm-reportes',
        //         'rrhh_add_user_page'
        //     );
        // }

        // MENÚ DEBUG
        add_submenu_page(
            'hrm-empleados',
            '[DEBUG] Vacaciones Empleado',
            '[DEBUG] Vacaciones Empleado',
            'view_hrm_admin_views',
            'hrm-debug-vacaciones-empleado',
            function () {
                $current_user_id = get_current_user_id();
                wp_cache_delete( 'hrm_vacaciones_empleado_' . $current_user_id );
                $solicitudes = hrm_get_vacaciones_empleado( $current_user_id );
                echo '<div class="wrap">';
                echo '<div class="hrm-admin-layout">';
                    hrm_get_template_part( 'partials/sidebar-loader' );
                    echo '<main class="hrm-content">';
                        hrm_get_template_part( 'vacaciones-empleado', '', [ 'current_user_id' => $current_user_id ] );
                    echo '</main>';
                echo '</div>';
                echo '</div>';
            }
        );

        add_submenu_page(
            'hrm-empleados',
            '[DEBUG] Formulario Vacaciones',
            '[DEBUG] Formulario Vacaciones',
            'view_hrm_admin_views',
            'hrm-debug-vacaciones-form',
            function () {
                echo '<div class="wrap">';
                echo '<div class="hrm-admin-layout">';
                    hrm_get_template_part( 'partials/sidebar-loader' );
                    echo '<main class="hrm-content">';
                        hrm_get_template_part( 'vacaciones-form' );
                    echo '</main>';
                echo '</div>';
                echo '</div>';
            }
        );
    }

    // PORTAL EMPLEADO CON SUBMENÚS (Para todos los usuarios logueados EXCEPTO administrador_anaconda)
    if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_hrm_employees' ) ) {
        
        // Verificar si es administrador_anaconda
        $current_user = wp_get_current_user();
        $is_anaconda = in_array( 'administrador_anaconda', (array) $current_user->roles );
        
        // Si es administrador_anaconda, no mostrar menú de "Mi Perfil" (solo accede a vistas admin)
        if ( $is_anaconda ) {
            return;
        }
        // MENÚ PARA SUPERVISORES
        if ( current_user_can( 'edit_hrm_employees' ) ) {
            add_menu_page(
                'Empleados',
                'Empleados',
                'edit_hrm_employees',
                'hrm-empleados',
                'hrm_render_employees_admin_page',
                'dashicons-businessman',
                6
            );

            add_submenu_page(
                'hrm-empleados',
                'Empleados',
                'Empleados',
                'edit_hrm_employees',
                'hrm-empleados',
                'hrm_render_employees_admin_page'
            );
            
            // Submenú oculto: Ver Solicitud de Vacaciones (para supervisores)
            add_submenu_page(
                'hrm-empleados',
                'Solicitud de Vacaciones',
                '',  // No mostrar en el menú
                'edit_hrm_employees',
                'hrm-vacaciones-formulario',
                'hrm_render_formulario_solicitud_page'
            );
        }

        if ( current_user_can( 'manage_hrm_vacaciones' ) ) {
            add_menu_page(
                'Vacaciones',
                'Vacaciones',
                'manage_hrm_vacaciones',
                'hrm-vacaciones',
                'hrm_render_vacaciones_admin_page',
                'dashicons-calendar-alt',
                6
            );

            add_submenu_page(
                'hrm-vacaciones',
                'Vacaciones',
                'Vacaciones',
                'manage_hrm_vacaciones',
                'hrm-vacaciones',
                'hrm_render_vacaciones_admin_page'
            );

            add_submenu_page(
                'hrm-vacaciones',
                'Solicitud de Vacaciones',
                '',
                'manage_hrm_vacaciones',
                'hrm-vacaciones-formulario',
                'hrm_render_formulario_solicitud_page'
            );
        }

        // MENÚ PRINCIPAL: Mi Perfil (posición 6 para que esté al inicio)
        add_menu_page(
            'Mi Perfil',
            'Mi Perfil',
            'read',
            'hrm-mi-perfil',
            'hrm_render_profile_overview',
            'dashicons-admin-users',
            6
        );

        // Submenú: Ver Información (bajo Mi Perfil)
        add_submenu_page(
            'hrm-mi-perfil',
            'Ver Información',
            'Ver Información',
            'read',
            'hrm-mi-perfil-info',
            function() { hrm_render_profile_overview(); }
        );

        // Submenú: Mis Vacaciones (bajo Mi Perfil)
        add_submenu_page(
            'hrm-mi-perfil',
            'Mis Vacaciones',
            'Mis Vacaciones',
            'read',
            'hrm-mi-perfil-vacaciones',
            function() { hrm_render_vacaciones_empleado_page(); }
        );

        // Submenú: Mis Documentos (bajo Mi Perfil)
        add_submenu_page(
            'hrm-mi-perfil',
            'Mis Documentos',
            'Mis Documentos',
            'read',
            'hrm-mi-documentos',
            function() { hrm_render_mis_documentos_page(); }
        );

        // Submenú: Mis Contratos (bajo Mis Documentos)
        add_submenu_page(
            'hrm-mi-documentos',
            'Mis Contratos',
            'Contratos',
            'read',
            'hrm-mi-documentos-contratos',
            function() { hrm_render_mis_documentos_contratos_page(); }
        );

        // Submenú: Mis Liquidaciones (bajo Mis Documentos)
        add_submenu_page(
            'hrm-mi-documentos',
            'Mis Liquidaciones',
            'Liquidaciones',
            'read',
            'hrm-mi-documentos-liquidaciones',
            function() { hrm_render_mis_documentos_liquidaciones_page(); }
        );

        // Submenú: Mis Licencias (bajo Mis Documentos)
        add_submenu_page(
            'hrm-mi-documentos',
            'Mis Licencias',
            'Licencias',
            'read',
            'hrm-mi-documentos-licencias',
            function() { hrm_render_mis_documentos_licencias_page(); }
        );

        // MENÚ INDEPENDIENTE: Convivencia (posición normal 60)
        add_menu_page(
            'Convivencia',
            'Convivencia',
            'read',
            'hrm-convivencia',
            'hrm_render_reglamento_interno_page',
            'dashicons-groups',
            60
        );
    }

    // ACCESO A MI PERFIL PARA ADMINISTRADORES (como submenú de HR Management)
    if ( current_user_can( 'manage_options' ) ) {
        // Submenú: Mi Perfil
        add_submenu_page(
            'hrm-empleados',
            'Mi Perfil',
            'Mi Perfil',
            'manage_options',
            'hrm-mi-perfil',
            function() { hrm_render_profile_overview(); }
        );

        // Submenú: Ver Información (para admin, mantiene la misma URL que usan los sidebars)
        add_submenu_page(
            'hrm-empleados',
            'Ver Información',
            'Ver Información',
            'manage_options',
            'hrm-mi-perfil-info',
            function() { hrm_render_profile_overview(); }
        );

        // Submenú: Mis Vacaciones
        add_submenu_page(
            'hrm-empleados',
            'Mis Vacaciones',
            'Mis Vacaciones',
            'manage_options',
            'hrm-mi-perfil-vacaciones',
            function() { hrm_render_vacaciones_empleado_page(); }
        );

        // Submenú: Mis Documentos
        add_submenu_page(
            'hrm-empleados',
            'Mis Documentos',
            'Mis Documentos',
            'manage_options',
            'hrm-mi-documentos',
            function() { hrm_render_mis_documentos_page(); }
        );

        // Submenú: Mis Contratos (para admin)
        add_submenu_page(
            'hrm-empleados',
            'Mis Contratos',
            'Contratos',
            'manage_options',
            'hrm-mi-documentos-contratos',
            function() { hrm_render_mis_documentos_contratos_page(); }
        );

        // Submenú: Mis Liquidaciones (para admin)
        add_submenu_page(
            'hrm-empleados',
            'Mis Liquidaciones',
            'Liquidaciones',
            'manage_options',
            'hrm-mi-documentos-liquidaciones',
            function() { hrm_render_mis_documentos_liquidaciones_page(); }
        );

        // Submenú: Mis Licencias (para admin)
        add_submenu_page(
            'hrm-empleados',
            'Mis Licencias',
            'Licencias',
            'manage_options',
            'hrm-mi-documentos-licencias',
            function() { hrm_render_mis_documentos_licencias_page(); }
        );
    }
}
add_action( 'admin_menu', 'hrm_register_admin_menus' );

/**
 * Renderizar página de Reglamento Interno.
 */
function hrm_render_reglamento_interno_page() {
    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part( 'reglamento-interno' );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página "Mis Documentos" (usuario actual, solo lectura)
 */
function hrm_render_mis_documentos_page() {
    // Debe estar logueado
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para ver esta página.' );
    }

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part( 'mis-documentos' );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página de Mis Documentos - Contratos
 */
function hrm_render_mis_documentos_contratos_page() {
    // Debe estar logueado
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para ver esta página.' );
    }

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part( 'mis-documentos-contratos' );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página de Mis Documentos - Liquidaciones
 */
function hrm_render_mis_documentos_liquidaciones_page() {
    // Debe estar logueado
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para ver esta página.' );
    }

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part( 'mis-documentos-liquidaciones' );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página de Mis Documentos - Licencias
 */
function hrm_render_mis_documentos_licencias_page() {
    // Debe estar logueado
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para ver esta página.' );
    }

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
        hrm_get_template_part( 'partials/sidebar-loader' );
        echo '<main class="hrm-content">';
            hrm_get_template_part( 'mis-documentos-licencias' );
        echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página para ver/editar solicitud de vacaciones (ADMIN y SUPERVISORES)
 */
function hrm_render_formulario_solicitud_page() {
    // Verificar permisos - Admins, o usuarios con view_hrm_admin_views pueden ver
    $puede_ver = current_user_can( 'manage_options' ) 
                 || current_user_can( 'view_hrm_admin_views' );
    
    if ( ! $puede_ver ) {
        wp_die( 'No tienes permisos para acceder a esta página.' );
    }
    
    hrm_get_template_part( 'vacaciones-formulario-vista' );
}