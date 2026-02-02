<?php
// Encolar JS de mis-documentos en admin para liquidaciones, contratos y licencias
function hrm_enqueue_mis_documentos_admin($hook)
{
    if (
        isset($_GET['page']) && in_array($_GET['page'], [
            'hrm-mi-documentos-liquidaciones',
            'hrm-mi-documentos-contratos',
            'hrm-mi-documentos-licencias',
            'hrm-mi-documentos',
        ])
    ) {
        $mis_js_path = HRM_PLUGIN_DIR . 'assets/js/mis-documentos.js';
        if ( file_exists( $mis_js_path ) ) {
            wp_enqueue_script(
                'hrm-mis-documentos',
                HRM_PLUGIN_URL . 'assets/js/mis-documentos.js',
                array('jquery'),
                HRM_PLUGIN_VERSION,
                true
            );
        } else {
            // Archivo ausente: evitar 404 en consola. Registrar para depuración si WP_DEBUG está activo.
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log('[HRM] assets/js/mis-documentos.js no existe, se omite su encolado.');
            }
        }
    }
}
add_action('admin_enqueue_scripts', 'hrm_enqueue_mis_documentos_admin');
/**
 * Plugin Name: HR Management
 * Plugin URI: https://example.com
 * Description: Plugin de gestión de Recursos Humanos. Arquitectura modular para empleados, vacaciones y más.
 * Version: 2.1.0
 * Author: Practicantes Anacondaweb
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * 1. CONSTANTES
 * ============================================================================
 */
define('HRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HRM_PLUGIN_VERSION', '2.1.0');

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
if (file_exists(HRM_PLUGIN_DIR . 'force-fix-supervisor.php')) {
    require_once HRM_PLUGIN_DIR . 'force-fix-supervisor.php';
}

// Cargar módulo de debug (solo si existe y WP_DEBUG está activo)
if (defined('WP_DEBUG') && WP_DEBUG && file_exists(HRM_PLUGIN_DIR . 'debug.php')) {
    require_once HRM_PLUGIN_DIR . 'debug.php';
}

/* FIX TEMPORAL: Forzar carga de vacaciones.php en ADMIN
 * ============================================================
 * Garantiza que las funciones de vacaciones estén disponibles
 * cuando se renderizan las páginas del panel administrativo.
 */
add_action('admin_init', function () {
    require_once HRM_PLUGIN_DIR . 'includes/vacaciones.php';

    // ACTIVACIÓN: Verificar que el admin y otros roles tengan los permisos correctos
    // Esta función se ejecuta cada vez que se carga el admin para garantizar consistencia
    if (function_exists('hrm_ensure_capabilities')) {
        hrm_ensure_capabilities();
    }
});

// TEMPORAL: Cargar herramientas de verificación de capacidades (solo para admin)
add_action('admin_menu', function () {
    if (current_user_can('manage_options')) {
        if (file_exists(HRM_PLUGIN_DIR . 'check-supervisor-caps.php')) {
            require_once HRM_PLUGIN_DIR . 'check-supervisor-caps.php';
        }
        if (file_exists(HRM_PLUGIN_DIR . 'fix-supervisor-caps.php')) {
            require_once HRM_PLUGIN_DIR . 'fix-supervisor-caps.php';
        }
    }
});


//----





/**
 * ============================================================================
 * 3. HOOKS DE ACTIVACIÓN Y DESACTIVACIÓN
 * ============================================================================
 */

/**
 * Ejecutar funciones de setup cuando se activa el plugin.
 */
function hrm_activate_plugin()
{
    hrm_create_roles();
    hrm_migrate_legacy_roles();
    hrm_ensure_capabilities();

    // Registrar evento cron para sincronización diaria de personal vigente
    hrm_schedule_daily_personal_vigente_sync();
}
register_activation_hook(__FILE__, 'hrm_activate_plugin');

/**
 * Limpiar eventos cron al desactivar el plugin.
 */
function hrm_deactivate_plugin()
{
    // Desregistrar evento cron si existe
    $timestamp = wp_next_scheduled('hrm_daily_personal_vigente_sync');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'hrm_daily_personal_vigente_sync');
    }
}
register_deactivation_hook(__FILE__, 'hrm_deactivate_plugin');

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
function hrm_redirect_non_admin_after_login($redirect_to, $request, $user)
{
    // Si hay error en el login, no redirigir
    if (isset($user->errors) && !empty($user->errors)) {
        return $redirect_to;
    }

    // Si no es un objeto de usuario válido, no redirigir
    if (!is_a($user, 'WP_User')) {
        return $redirect_to;
    }

    // Si es administrador de WordPress, dejar el comportamiento por defecto (dashboard)
    if (in_array('administrator', (array) $user->roles, true)) {
        return $redirect_to;
    }

    // Si es administrador_anaconda o supervisor (o tiene capability para editar empleados), redirigir a la lista de empleados
    if (in_array('administrador_anaconda', (array) $user->roles, true) || in_array('supervisor', (array) $user->roles, true) || user_can($user, 'edit_hrm_employees')) {
        error_log('[HRM-DEBUG] Redirecting admin_anaconda/supervisor/edit_hrm_employees to employee list for user_id=' . intval($user->ID));
        return admin_url('admin.php?page=hrm-empleados&tab=list');
    }

    // Editor de Vacaciones: forzar a panel de Vacaciones (fallback cuando otros filtros o plugins sobreescriben)
    if (in_array('editor_vacaciones', (array) $user->roles, true) || user_can($user, 'manage_hrm_vacaciones')) {
        error_log('[HRM-DEBUG] Redirecting editor_vacaciones to hrm-vacaciones for user_id=' . intval($user->ID));
        return admin_url('admin.php?page=hrm-vacaciones');
    }

    // Si es un usuario "empleado" puro, llevarlo a su perfil dentro del plugin
    if (in_array('empleado', (array) $user->roles, true)) {
        return admin_url('admin.php?page=hrm-mi-perfil-info');
    }

    // Para otros roles (supervisor, editor_vacaciones, etc.) — no forzar redirección aquí,
    // permitir que otros filtros (o la lógica por defecto) decidan.
    return $redirect_to;
}
add_filter('login_redirect', 'hrm_redirect_non_admin_after_login', 10, 3);

/**
 * ============================================================================
 * 4. INICIALIZACIÓN DEL PLUGIN (Init Hook)
 * ============================================================================
 */

/**
 * Enqueue de estilos y scripts del plugin en el área administrativa
 */
function hrm_enqueue_admin_assets()
{
    global $pagenow;

    // Cargar assets solo en páginas del plugin
    if ($pagenow === 'admin.php' && isset($_GET['page'])) {
        $page = sanitize_text_field($_GET['page']);

        // Estilos de sidebar/layout y JS específicos (todas las páginas HRM)
        if (strpos($page, 'hrm') === 0 || $page === 'hr-management') {
            // CSS general del plugin
            wp_enqueue_style(
                'hrm-style',
                HRM_PLUGIN_URL . 'assets/css/plugin-base.css',
                array(),
                HRM_PLUGIN_VERSION
            );

            // Consolidated small view styles (merged into plugin-common.css)
            wp_enqueue_style(
                'hrm-plugin-common',
                HRM_PLUGIN_URL . 'assets/css/plugin-common.css',
                array('hrm-style','hrm-bootstrap'),
                HRM_PLUGIN_VERSION
            );

            // CSS del sidebar - Responsive (kept separate because it's larger / specialized)
            // NOTE: Enqueue below (unchanged)

            // CSS del sidebar - Responsive
            wp_enqueue_style(
                'hrm-sidebar-responsive',
                HRM_PLUGIN_URL . 'assets/css/sidebar-responsive.css',
                array('hrm-admin-sidebar'),
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

            // Pasar lista de tipos de documento al script del sidebar para permitir
            // inserción dinámica cuando se cree un nuevo tipo via AJAX
            hrm_ensure_db_classes();
            $db_docs_for_sidebar = new HRM_DB_Documentos();
            $sidebar_doc_types = $db_docs_for_sidebar->get_all_types();
            wp_localize_script(
                'hrm-sidebar-responsive',
                'hrmDocumentTypesData',
                array(
                    'types' => $sidebar_doc_types
                )
            );

            // JS de notificaciones del sidebar
            wp_enqueue_script(
                'hrm-sidebar-notifications',
                HRM_PLUGIN_URL . 'assets/js/sidebar-notifications.js',
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

            // Pasar ajaxUrl, nonce y datos al script de crear empleados
            wp_localize_script(
                'hrm-employees-create',
                'hrmCreateData',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('hrm_check_email_nonce'),
                    'todosDeptos' => isset($GLOBALS['hrm_departamentos']) ? $GLOBALS['hrm_departamentos'] : array(),
                    'deptosPredefinidos' => array(
                        'comercial' => array('Soporte', 'Ventas'),
                        'proyectos' => array('Desarrollo'),
                        'operaciones' => array('Administracion', 'Gerencia', 'Sistemas'),
                    ),
                    // Mapa departamento -> puestos (se usará para filtrar el select de puestos)
                    'mapaPuestos' => array(
                        'soporte' => array('Ingeniero de Soporte', 'Practicante'),
                        'desarrollo' => array('Desarrollador de Software', 'Diseñador Gráfico'),
                        'ventas' => array('Asistente Comercial'),
                        'administracion' => array('Administrativo(a) Contable'),
                        'gerencia' => array('Gerente'),
                        'sistemas' => array('Ingeniero en Sistemas'),
                    ),
                )
            );
        }
    }
}
add_action('admin_enqueue_scripts', 'hrm_enqueue_admin_assets');

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
function hrm_hide_admin_bar_fullscreen()
{
    if (isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1') {
        show_admin_bar(false);
    }
}
add_action('init', 'hrm_hide_admin_bar_fullscreen');

/**
 * Agregar clase CSS al body para ocultar elementos en pantalla completa.
 */
function hrm_add_fullscreen_body_class($classes)
{
    if (isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1') {
        $classes .= ' hrm-fullscreen-mode';
    }
    return $classes;
}
add_filter('admin_body_class', 'hrm_add_fullscreen_body_class');

/**
 * Enqueue CSS para modo pantalla completa.
 */
function hrm_enqueue_fullscreen_styles()
{
    // Cargar assets solo en páginas del plugin
    if (isset($_GET['page']) && strpos($_GET['page'], 'hrm') === 0) {
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

        // Pasar datos al JS: userId y control de redirección automática.
        // Debug log temporal: mostrar userId y valor de autoRedirect
        error_log('[HRM-FS] enqueue fullscreen user_id=' . get_current_user_id() . ' autoRedirect=' . (get_current_user_id() !== 1 ? '1' : '0'));
        // Por defecto forzamos redirección automática a fullscreen para usuarios cuyo ID != 1,
        // pero permitimos sobreescribirlo mediante el filtro `hrm_fullscreen_auto_redirect`.
        wp_localize_script('hrm-fullscreen', 'hrmFullscreenData', array(
            'userId' => get_current_user_id(),
            'autoRedirect' => apply_filters('hrm_fullscreen_auto_redirect', get_current_user_id() !== 1),
        ));

        // Nota: `wp_localize_script` ya fue llamado arriba con `userId` y `autoRedirect`.
    }
}
add_action('admin_enqueue_scripts', 'hrm_enqueue_fullscreen_styles');

/**
 * Verificación continua de capacidades en cada carga.
 * Se ejecuta en 'init' con prioridad 20 para asegurar que los roles
 * ya existen (add_role() se ejecuta en 'init' con prioridad 0).
 */
add_action('init', 'hrm_ensure_capabilities', 20);

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
function hrm_block_inactive_employee_login($user, $password)
{
    // Si ya hay un error, retornar
    if (is_wp_error($user)) {
        return $user;
    }

    // Si no es un objeto de usuario válido, retornar
    if (!is_a($user, 'WP_User')) {
        return $user;
    }

    // Buscar el empleado asociado a este usuario
    global $wpdb;
    $table = $wpdb->prefix . 'rrhh_empleados';

    $employee = $wpdb->get_row($wpdb->prepare(
        "SELECT id_empleado as id, estado, nombre, apellido FROM $table WHERE user_id = %d LIMIT 1",
        $user->ID
    ));

    // Si no hay empleado asociado, permitir acceso
    if (!$employee) {
        return $user;
    }

    // Si el empleado está inactivo (estado = 0), bloquear acceso
    if (isset($employee->estado) && intval($employee->estado) === 0) {
        return new WP_Error(
            'employee_inactive',
            sprintf(
                '<strong>Acceso Denegado:</strong> Tu cuenta de empleado ha sido desactivada. Por favor contacta al administrador de Recursos Humanos para más información.',
                esc_html($employee->nombre . ' ' . $employee->apellido)
            )
        );
    }

    return $user;
}
add_filter('wp_authenticate_user', 'hrm_block_inactive_employee_login', 10, 2);

/**
 * Registrar shortcodes del plugin.
 */
add_action('init', function () {
    add_shortcode('hrm_solicitud_vacaciones', 'hrm_render_formulario_vacaciones_shortcode');
    add_shortcode('hrm_mis_vacaciones', 'hrm_render_vacaciones_empleado_page');
}, 10);

/**
 * ============================================================================
 * 5. RENDERIZADORES (PAGE CALLBACKS)
 * ============================================================================
 */

/**
 * Renderizar página de vacaciones para admin.
 */
function hrm_render_vacaciones_admin_page()
{
    // IMPORTANTE: Forzar actualización de capacidades del usuario actual
    // Esto es necesario en caso de que se hayan agregado capacidades al rol
    // después de que el usuario fue asignado al rol
    $current_user = wp_get_current_user();
    if ($current_user && $current_user->ID) {
        // Recargar las capacidades del usuario desde la base de datos
        $current_user->get_role_caps();

        // Log para debug
        error_log('HRM: Capacidades del usuario ' . $current_user->ID . ' recargadas. Tiene manage_hrm_vacaciones: ' . ($current_user->has_cap('manage_hrm_vacaciones') ? 'YES' : 'NO'));
    }

    if (!current_user_can('manage_options') && !current_user_can('view_hrm_admin_views') && !current_user_can('manage_hrm_vacaciones')) {
        wp_die(__('No tienes permisos para ver esta página.', 'hr-management'), __('Acceso denegado', 'hr-management'), array('response' => 403));
    }

    $search = sanitize_text_field($_GET['empleado'] ?? '');
    $solicitudes = hrm_get_all_vacaciones($search);

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    hrm_get_template_part(
        'vacaciones-admin',
        '',
        ['solicitudes' => $solicitudes]
    );
    echo '</main>';
    echo '</div>';
    echo '</div>';
}


/**
 * Renderizar página de vacaciones para empleado.
 */
function hrm_render_vacaciones_empleado_page()
{
    if (!is_user_logged_in()) {
        echo '<p>Debes iniciar sesión para ver tus vacaciones.</p>';
        return;
    }

    // Obtener el user_id actual y limpiar caché para evitar datos viejos
    $current_user_id = get_current_user_id();

    wp_cache_delete('hrm_vacaciones_empleado_' . $current_user_id);

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    hrm_get_template_part('vacaciones-empleado', '', ['current_user_id' => $current_user_id]);
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar shortcode de formulario de vacaciones.
 */
function hrm_render_formulario_vacaciones_shortcode()
{
    ob_start();
    hrm_get_template_part('vacaciones-form');
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
function hrm_render_profile_overview()
{
    if (!is_user_logged_in()) {
        echo '<p>Debes iniciar sesión para ver tu perfil.</p>';
        return;
    }

    if (!class_exists('HRM_DB_Empleados')) {
        require_once plugin_dir_path(__FILE__) . 'includes/db/class-hrm-db-empleados.php';
    }

    $db_emp = new HRM_DB_Empleados();
    $employee = $db_emp->get_by_user_id(get_current_user_id());

    if (!$employee) {
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
    $hrm_departamentos = apply_filters('hrm_departamentos', array(
        'Administración',
        'Ventas',
        'Desarrollo',
        'Marketing',
        'Soporte',
        'Recursos Humanos',
        'Gerencia',
        'Sistemas'
    ));

    $hrm_puestos = apply_filters('hrm_puestos', array(
        'Gerente',
        'Ingeniero en Sistemas',
        'Ingeniero de Soporte',
        'Administrativo(a) Contable',
        'Asistente Comercial',
        'Desarrollador de Software',
        'Diseñador Gráfico'
    ));

    $hrm_tipos_contrato = apply_filters('hrm_tipos_contrato', array(
        'Indefinido',
        'Plazo Fijo',
        'Por Proyecto',
        'Práctica',
        'Part-time',
        'Teletrabajo'
    ));

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    // Usar el template employees-detail.php en lugar de profile-overview
    hrm_get_template_part('Administrador/employees-detail', '', compact('employee', 'hrm_departamentos', 'hrm_puestos', 'hrm_tipos_contrato'));
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar formulario de edición del perfil del usuario actual.
 */
function hrm_render_profile_edit()
{
    if (!current_user_can('view_hrm_own_profile')) {
        $cu = wp_get_current_user();
        $roles = !empty($cu->roles) ? implode(', ', $cu->roles) : '(sin roles)';
        $caps = array_keys(array_filter((array) $cu->allcaps));
        $caps_list = !empty($caps) ? implode(', ', $caps) : '(sin capabilities)';

        echo '<div class="wrap"><h1>Acceso denegado</h1>';
        echo '<div class="notice notice-error"><p>No tienes permiso para editar tu perfil.</p></div>';
        echo '<h2>Diagnóstico (temporal)</h2>';
        echo '<p><strong>Usuario:</strong> ' . esc_html($cu->user_login) . ' (ID: ' . intval($cu->ID) . ')</p>';
        echo '<p><strong>Roles:</strong> ' . esc_html($roles) . '</p>';
        echo '<p><strong>Capabilities:</strong> ' . esc_html($caps_list) . '</p>';
        echo '<p>Comprueba que tu rol tenga <code>view_hrm_own_profile</code> para poder editar.</p>';
        echo '</div>';
        return;
    }

    $db_emp = new HRM_DB_Empleados();
    $employee = $db_emp->get_by_user_id(get_current_user_id());

    if (!$employee) {
        echo '<div class="wrap"><p class="notice notice-warning">No se encontró un registro de empleado vinculado a tu usuario.</p></div>';
        return;
    }

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    hrm_get_template_part('profile-edit-form');
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
function hrm_register_admin_menus()
{
    // Determinar si el usuario tiene acceso a vistas de administrador
    // Puede ser por manage_hrm_employees O por el rol administrador_anaconda con view_hrm_admin_views
    $has_admin_access = current_user_can('manage_hrm_employees') || current_user_can('view_hrm_admin_views');
    // Debug: registrar roles y capacidades al ejecutar el registro de menús
    $_hrm_dbg_user = wp_get_current_user();
    $hrm_dbg_is_anaconda = in_array('administrador_anaconda', (array) $_hrm_dbg_user->roles);
    error_log('[HRM-DEBUG] hrm_register_admin_menus user_id=' . intval($_hrm_dbg_user->ID) . ' roles=' . json_encode($_hrm_dbg_user->roles) . ' manage_options=' . (current_user_can('manage_options') ? 'YES' : 'NO') . ' view_hrm_admin_views=' . (current_user_can('view_hrm_admin_views') ? 'YES' : 'NO') . ' is_anaconda=' . ($hrm_dbg_is_anaconda ? 'YES' : 'NO'));

    // MENÚ PRINCIPAL: HR Management (solo para admins con manage_hrm_employees o view_hrm_admin_views)
    if (current_user_can('manage_options') || $has_admin_access) {
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

        // MENÚ DEBUG
        add_submenu_page(
            'hrm-empleados',
            'Vacaciones Empleado',
            'Vacaciones Empleado',
            'view_hrm_admin_views',
            'hrm-debug-vacaciones-empleado',
            function () {
                $current_user_id = get_current_user_id();
                wp_cache_delete('hrm_vacaciones_empleado_' . $current_user_id);
                $solicitudes = hrm_get_vacaciones_empleado($current_user_id);
                echo '<div class="wrap">';
                echo '<div class="hrm-admin-layout">';
                hrm_get_template_part('partials/sidebar-loader');
                echo '<main class="hrm-content">';
                hrm_get_template_part('vacaciones-empleado', '', ['current_user_id' => $current_user_id]);
                echo '</main>';
                echo '</div>';
                echo '</div>';
            }
        );

            // Submenú: Documentos empresa (para administradores)
            add_submenu_page(
                'hrm-empleados',
                'Documentos empresa',
                'Documentos empresa',
                'view_hrm_admin_views',
                'hrm-anaconda-documents',
                'hrm_render_anaconda_documents_page'
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
                hrm_get_template_part('partials/sidebar-loader');
                echo '<main class="hrm-content">';
                hrm_get_template_part('vacaciones-form');
                echo '</main>';
                echo '</div>';
                echo '</div>';
            }
        );
    }

    // PORTAL EMPLEADO CON SUBMENÚS (Para todos los usuarios logueados EXCEPTO administrador_anaconda)
    if (is_user_logged_in() && !current_user_can('manage_options') && !current_user_can('manage_hrm_employees')) {

        // Verificar si es administrador_anaconda
        $current_user = wp_get_current_user();
        $is_anaconda = in_array('administrador_anaconda', (array) $current_user->roles);

        // Si es administrador_anaconda, no mostrar menú de "Mi Perfil" (solo accede a vistas admin)
        // No hacemos return; en su lugar marcamos una bandera para omitir sólo el
        // bloque de "Mi Perfil" pero permitir registrar otras páginas como
        // Convivencia (registrada más abajo).
        if ($is_anaconda) {
            $skip_my_profile_for_anaconda = true;
        } else {
            $skip_my_profile_for_anaconda = false;
        }
        // MENÚ PARA SUPERVISORES
        if (current_user_can('edit_hrm_employees')) {
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

        if (current_user_can('manage_hrm_vacaciones')) {
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
        if (empty($skip_my_profile_for_anaconda)) {
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
                function () {
                    hrm_render_profile_overview(); }
            );

            // Submenú: Mis Vacaciones (bajo Mi Perfil)
            add_submenu_page(
                'hrm-mi-perfil',
                'Mis Vacaciones',
                'Mis Vacaciones',
                'read',
                'hrm-mi-perfil-vacaciones',
                function () {
                    hrm_render_vacaciones_empleado_page(); }
            );

            // Submenú: Mis Documentos (bajo Mi Perfil)
            add_submenu_page(
                'hrm-mi-perfil',
                'Mis Documentos',
                'Mis Documentos',
                'read',
                'hrm-mi-documentos',
                function () {
                    hrm_render_mis_documentos_page(); }
            );

            // Submenú: Mis Contratos (bajo Mis Documentos)
            add_submenu_page(
                'hrm-mi-documentos',
                'Mis Contratos',
                'Contratos',
                'read',
                'hrm-mi-documentos-contratos',
                function () {
                    hrm_render_mis_documentos_contratos_page(); }
            );

            // Submenú: Mis Liquidaciones (bajo Mis Documentos)
            add_submenu_page(
                'hrm-mi-documentos',
                'Mis Liquidaciones',
                'Liquidaciones',
                'read',
                'hrm-mi-documentos-liquidaciones',
                function () {
                    hrm_render_mis_documentos_liquidaciones_page(); }
            );

            // Submenú: Mis Licencias (bajo Mis Documentos)
            add_submenu_page(
                'hrm-mi-documentos',
                'Mis Licencias',
                'Licencias',
                'read',
                'hrm-mi-documentos-licencias',
                function () {
                    hrm_render_mis_documentos_licencias_page(); }
            );

            // Submenús dinámicos: añadir un submenú para cada tipo de documento registrado
            hrm_ensure_db_classes();
            $db_docs = new HRM_DB_Documentos();
            $doc_types = $db_docs->get_all_types();
            if (!empty($doc_types)) {
                foreach ($doc_types as $t_id => $t_name) {
                    $slug = 'hrm-mi-documentos-type-' . intval($t_id);
                    // Registrar el submenú (capacidad 'read' permite ver en "Mi Perfil")
                    add_submenu_page(
                        'hrm-mi-documentos',
                        $t_name,
                        $t_name,
                        'read',
                        $slug,
                        'hrm_render_mis_documentos_tipo_page'
                    );
                }
            }
        }

        // MENÚ INDEPENDIENTE: Convivencia (posición normal 60)
        // Nota: originalmente este menú se añadía solo dentro del portal empleado
        // (excluyendo administradores y administrador_anaconda). Lo registramos
        // fuera del bloque para que también esté disponible a administradores
        // y al rol administrador_anaconda cuando corresponda.
    }

    // Registrar la página de Convivencia para cualquier usuario logueado.
    // Esto garantiza que `administrator` y `administrador_anaconda` puedan
    // acceder a la vista mediante admin.php?page=hrm-convivencia.
    // Registrar la página de Convivencia / Mis Vacaciones para administradores
    // o para el rol `administrador_anaconda`. Para administradores los
    // registramos como top-level; para administrador_anaconda los añadimos
    // como submenús bajo 'hrm-empleados' para evitar duplicados.
    $current_user = wp_get_current_user();
    $is_anaconda = in_array('administrador_anaconda', (array) $current_user->roles);
    if (is_user_logged_in()) {
        if (current_user_can('manage_options')) {
            add_menu_page(
                'Convivencia',
                'Convivencia',
                'read',
                'hrm-convivencia',
                'hrm_render_reglamento_interno_page',
                'dashicons-groups',
                60
            );

            // Registrar "Mis Documentos" y subpáginas para administradores (para permitir acceso por URL)
            add_submenu_page(
                'hrm-empleados',
                'Mis Documentos',
                'Mis Documentos',
                'manage_options',
                'hrm-mi-documentos',
                'hrm_render_mis_documentos_page'
            );

            add_submenu_page(
                'hrm-empleados',
                'Mis Contratos',
                'Contratos',
                'manage_options',
                'hrm-mi-documentos-contratos',
                'hrm_render_mis_documentos_contratos_page'
            );

            add_submenu_page(
                'hrm-empleados',
                'Mis Liquidaciones',
                'Liquidaciones',
                'manage_options',
                'hrm-mi-documentos-liquidaciones',
                'hrm_render_mis_documentos_liquidaciones_page'
            );

            add_submenu_page(
                'hrm-empleados',
                'Mis Licencias',
                'Licencias',
                'manage_options',
                'hrm-mi-documentos-licencias',
                'hrm_render_mis_documentos_licencias_page'
            );

            // Registrar dinámicamente tipos por id para admins
            hrm_ensure_db_classes();
            $db_docs_temp = new HRM_DB_Documentos();
            $doc_types_temp = $db_docs_temp->get_all_types();
            if (!empty($doc_types_temp)) {
                foreach ($doc_types_temp as $t_id => $t_name) {
                    $slug = 'hrm-mi-documentos-type-' . intval($t_id);
                    add_submenu_page(
                        'hrm-empleados',
                        $t_name,
                        $t_name,
                        'manage_options',
                        $slug,
                        'hrm_render_mis_documentos_tipo_page'
                    );
                }
            }

            // NOTA: No registrar "Mis Vacaciones" como top-level para administradores
            // para evitar duplicados; se registra más abajo como submenú bajo
            // 'hrm-empleados' (mismo slug 'hrm-mi-perfil-vacaciones').
            error_log('[HRM-DEBUG] Registered top-level Convivencia for admin user_id=' . get_current_user_id());
        } elseif ($is_anaconda) {
            // Intentar añadir como submenús bajo HR Management para mantener UI
            // Ensure parent menu exists for administrador_anaconda: if the parent
            // 'hrm-empleados' wasn't registered earlier (due to capability checks),
            // register a minimal top-level menu so submenus can be attached and
            // the page slug becomes routable (avoids 403 "not allowed to access").
            if (empty($GLOBALS['submenu']['hrm-empleados'])) {
                add_menu_page(
                    'HR Management',
                    'HR Management',
                    'view_hrm_employee_admin',
                    'hrm-empleados',
                    'hrm_render_employees_admin_page',
                    'dashicons-businessman',
                    6
                );
            }

            add_submenu_page(
                'hrm-empleados',
                'Convivencia',
                'Convivencia',
                'read',
                'hrm-convivencia',
                'hrm_render_reglamento_interno_page'
            );

            add_submenu_page(
                'hrm-empleados',
                'Mis Vacaciones',
                'Mis Vacaciones',
                'read',
                'hrm-mi-perfil-vacaciones',
                'hrm_render_vacaciones_empleado_page'
            );

            // Añadir Mis Documentos y tipos para administrador_anaconda (como subpáginas bajo hrm-empleados)
            add_submenu_page(
                'hrm-empleados',
                'Mis Documentos',
                'Mis Documentos',
                'view_hrm_admin_views',
                'hrm-mi-documentos',
                'hrm_render_mis_documentos_page'
            );

            // Submenú: Documentos empresa (administrador_anaconda)
            add_submenu_page(
                'hrm-empleados',
                'Documentos empresa',
                'Documentos empresa',
                'view_hrm_admin_views',
                'hrm-anaconda-documents',
                'hrm_render_anaconda_documents_page'
            );

            // Registrar tipos dinámicos también para administrador_anaconda
            hrm_ensure_db_classes();
            $db_docs_temp2 = new HRM_DB_Documentos();
            $doc_types_temp2 = $db_docs_temp2->get_all_types();
            if (!empty($doc_types_temp2)) {
                foreach ($doc_types_temp2 as $t_id => $t_name) {
                    $slug = 'hrm-mi-documentos-type-' . intval($t_id);
                    add_submenu_page(
                        'hrm-empleados',
                        $t_name,
                        $t_name,
                        'view_hrm_admin_views',
                        $slug,
                        'hrm_render_mis_documentos_tipo_page'
                    );
                }
            }

            error_log('[HRM-DEBUG] Registered Convivencia and Mis Vacaciones as submenus for administrador_anaconda user_id=' . get_current_user_id());
        } else {
            // Registrar Convivencia para cualquier usuario autenticado (capacidad 'read')
            add_menu_page(
                'Convivencia',
                'Convivencia',
                'read',
                'hrm-convivencia',
                'hrm_render_reglamento_interno_page',
                'dashicons-groups',
                60
            );
            error_log('[HRM-DEBUG] Registered Convivencia top-level for logged-in user_id=' . get_current_user_id());
        }
    }

    // ACCESO A MI PERFIL PARA ADMINISTRADORES (como submenú de HR Management)
    if (current_user_can('manage_options')) {
        // Submenú: Mi Perfil
        add_submenu_page(
            'hrm-empleados',
            'Mi Perfil',
            'Mi Perfil',
            'manage_options',
            'hrm-mi-perfil',
            function () {
                hrm_render_profile_overview(); }
        );

        // Submenú: Ver Información (para admin, mantiene la misma URL que usan los sidebars)
        add_submenu_page(
            'hrm-empleados',
            'Ver Información',
            'Ver Información',
            'manage_options',
            'hrm-mi-perfil-info',
            function () {
                hrm_render_profile_overview(); }
        );

        // Submenú: Mis Vacaciones
        add_submenu_page(
            'hrm-empleados',
            'Mis Vacaciones',
            'Mis Vacaciones',
            'manage_options',
            'hrm-mi-perfil-vacaciones',
            function () {
                hrm_render_vacaciones_empleado_page(); }
        );

        // Submenú: Mis Documentos
        add_submenu_page(
            'hrm-empleados',
            'Mis Documentos',
            'Mis Documentos',
            'manage_options',
            'hrm-mi-documentos',
            function () {
                hrm_render_mis_documentos_page(); }
        );

        // Submenú: Mis Contratos (para admin)
        add_submenu_page(
            'hrm-empleados',
            'Mis Contratos',
            'Contratos',
            'manage_options',
            'hrm-mi-documentos-contratos',
            function () {
                hrm_render_mis_documentos_contratos_page(); }
        );

        // Submenú: Mis Liquidaciones (para admin)
        add_submenu_page(
            'hrm-empleados',
            'Mis Liquidaciones',
            'Liquidaciones',
            'manage_options',
            'hrm-mi-documentos-liquidaciones',
            function () {
                hrm_render_mis_documentos_liquidaciones_page(); }
        );

        // Submenú: Mis Licencias (para admin)
        add_submenu_page(
            'hrm-empleados',
            'Mis Licencias',
            'Licencias',
            'manage_options',
            'hrm-mi-documentos-licencias',
            function () {
                hrm_render_mis_documentos_licencias_page(); }
        );
    }
}
add_action('admin_menu', 'hrm_register_admin_menus');

/**
 * Renderizar página de Reglamento Interno.
 */
function hrm_render_reglamento_interno_page()
{
    if (!is_user_logged_in()) {
        wp_die('Debes iniciar sesión para ver esta página.', 'Acceso denegado', array('response' => 403));
    }

    echo '<div class="wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    hrm_get_template_part('anaconda-view-documents');
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página "Mis Documentos" (usuario actual, solo lectura)
 */
function hrm_render_mis_documentos_page()
{
    // Debe estar logueado
    if (!is_user_logged_in()) {
        wp_die('Debes iniciar sesión para ver esta página.');
    }

    echo '<div class="wrap hrm-admin-wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    hrm_get_template_part('mis-documentos');
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página de Documentos Empresa (Anaconda) - Acceso por admin / administrador_anaconda
 */
function hrm_render_anaconda_documents_page()
{
    if (!is_user_logged_in()) {
        wp_die('Debes iniciar sesión para ver esta página.', 'Acceso denegado', array('response' => 403));
    }

    // Comprobar permisos: admins, rol administrador_anaconda o capability view_hrm_admin_views
    $current_user = wp_get_current_user();
    $has_manage = current_user_can('manage_options');
    $can_view_admin = current_user_can('view_hrm_admin_views');
    $is_anaconda = in_array('administrador_anaconda', (array) $current_user->roles, true);

    error_log('[HRM-DEBUG] hrm_render_anaconda_documents_page permission check - user_id=' . intval($current_user->ID) . ' roles=' . json_encode($current_user->roles) . ' has_manage=' . ($has_manage ? 'YES' : 'NO') . ' can_view_admin=' . ($can_view_admin ? 'YES' : 'NO') . ' is_anaconda=' . ($is_anaconda ? 'YES' : 'NO'));
    if (function_exists('hrm_local_debug_log')) {
        hrm_local_debug_log('[HRM-DEBUG] hrm_render_anaconda_documents_page permission check - user_id=' . intval($current_user->ID) . ' roles=' . json_encode($current_user->roles) . ' has_manage=' . ($has_manage ? 'YES' : 'NO') . ' can_view_admin=' . ($can_view_admin ? 'YES' : 'NO') . ' is_anaconda=' . ($is_anaconda ? 'YES' : 'NO'));
    }

    if (!($has_manage || $can_view_admin || $is_anaconda)) {
        // Log detail and show useful hint when WP_DEBUG is enabled
        $debug = '[HRM-DEBUG] Access denied to anaconda documents page: user_id=' . intval($current_user->ID) . ' roles=' . json_encode($current_user->roles) . ' has_manage=' . ($has_manage ? 'YES' : 'NO') . ' can_view_admin=' . ($can_view_admin ? 'YES' : 'NO') . ' is_anaconda=' . ($is_anaconda ? 'YES' : 'NO');
        error_log($debug);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_die('No tienes permisos para ver esta página. Detalles: ' . esc_html($debug), 'Acceso denegado', array('response' => 403));
        }
        wp_die('No tienes permisos para ver esta página.', 'Acceso denegado', array('response' => 403));
    }

    // Render counter + guard para evitar duplicados
    $GLOBALS['hrm_render_count'] = isset($GLOBALS['hrm_render_count']) ? $GLOBALS['hrm_render_count'] + 1 : 1;
    error_log('[HRM-DEBUG] hrm_render_anaconda_documents_page called. render_count=' . intval($GLOBALS['hrm_render_count']));

    if (isset($GLOBALS['hrm_render_count']) && intval($GLOBALS['hrm_render_count']) > 1) {
        if (empty($GLOBALS['hrm_view_rendered']['anaconda-documents'])) {
            $GLOBALS['hrm_view_rendered']['anaconda-documents'] = true;
            hrm_get_template_part('anaconda-documents-create');
        } else {
            error_log('[HRM-DEBUG] Already rendered anaconda-documents, skipping duplicate call.');
        }
        return;
    }

    echo '<div class="wrap hrm-admin-wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    $GLOBALS['hrm_view_rendered']['anaconda-documents'] = true;
    hrm_get_template_part('anaconda-documents-create');
    echo '</main>';
    echo '</div>';
    echo '</div>';
}



/**
 * Renderizar página de Mis Documentos - Contratos
 */
function hrm_render_mis_documentos_contratos_page()
{
    // Debe estar logueado
    if (!is_user_logged_in()) {
        wp_die('Debes iniciar sesión para ver esta página.');
    }

    // Render counter for debugging duplicates
    $GLOBALS['hrm_render_count'] = isset($GLOBALS['hrm_render_count']) ? $GLOBALS['hrm_render_count'] + 1 : 1;
    error_log('[HRM-DEBUG] hrm_render_mis_documentos_contratos_page called. render_count=' . intval($GLOBALS['hrm_render_count']));

    // If a previous HRM layout was already rendered in this request, avoid rendering another wrapper
    if (isset($GLOBALS['hrm_render_count']) && intval($GLOBALS['hrm_render_count']) > 1) {
        error_log('[HRM-DEBUG] Skipping outer wrapper to avoid duplicate layout (contracts).');
        if (empty($GLOBALS['hrm_view_rendered']['mis-documentos-contratos'])) {
            $GLOBALS['hrm_view_rendered']['mis-documentos-contratos'] = true;
            hrm_get_template_part('mis-documentos-contratos');
        } else {
            error_log('[HRM-DEBUG] Already rendered mis-documentos-contratos, skipping duplicate call.');
        }
        return;
    }

    echo '<div class="wrap hrm-admin-wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    $GLOBALS['hrm_view_rendered']['mis-documentos-contratos'] = true;
    hrm_get_template_part('mis-documentos-contratos');
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página de Mis Documentos - Liquidaciones
 */
function hrm_render_mis_documentos_liquidaciones_page()
{
    // Debe estar logueado
    if (!is_user_logged_in()) {
        wp_die('Debes iniciar sesión para ver esta página.');
    }

    // Render counter for debugging duplicates
    $GLOBALS['hrm_render_count'] = isset($GLOBALS['hrm_render_count']) ? $GLOBALS['hrm_render_count'] + 1 : 1;
    error_log('[HRM-DEBUG] hrm_render_mis_documentos_liquidaciones_page called. render_count=' . intval($GLOBALS['hrm_render_count']));

    // If a previous HRM layout was already rendered in this request, avoid rendering another wrapper
    if (isset($GLOBALS['hrm_render_count']) && intval($GLOBALS['hrm_render_count']) > 1) {
        error_log('[HRM-DEBUG] Skipping outer wrapper to avoid duplicate layout (liquidations).');
        if (empty($GLOBALS['hrm_view_rendered']['mis-documentos-liquidaciones'])) {
            $GLOBALS['hrm_view_rendered']['mis-documentos-liquidaciones'] = true;
            hrm_get_template_part('mis-documentos-liquidaciones');
        } else {
            error_log('[HRM-DEBUG] Already rendered mis-documentos-liquidaciones, skipping duplicate call.');
        }
        return;
    }

    echo '<div class="wrap hrm-admin-wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    $GLOBALS['hrm_view_rendered']['mis-documentos-liquidaciones'] = true;
    hrm_get_template_part('mis-documentos-liquidaciones');
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página de Mis Documentos - Licencias
 */
function hrm_render_mis_documentos_licencias_page()
{
    // Debe estar logueado
    if (!is_user_logged_in()) {
        wp_die('Debes iniciar sesión para ver esta página.');
    }

    echo '<div class="wrap hrm-admin-wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    hrm_get_template_part('mis-documentos-licencias');
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página de Mis Documentos - Tipo dinámico
 */
function hrm_render_mis_documentos_tipo_page()
{
    // Debe estar logueado
    if (!is_user_logged_in()) {
        wp_die('Debes iniciar sesión para ver esta página.');
    }

    // Resolver type_id desde el slug (hrm-mi-documentos-type-<ID>) o GET
    $page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    $type_id = 0;
    if (preg_match('/hrm-mi-documentos-type-(\d+)/', $page_slug, $m)) {
        $type_id = intval($m[1]);
    }
    if (isset($_GET['type_id'])) {
        $type_id = intval($_GET['type_id']);
    }

    // Soportar visualización de documentos de otro empleado (solo para admins/editores)
    $employee_id = 0;
    if (isset($_GET['employee_id'])) {
        $employee_id = absint($_GET['employee_id']);
        if ($employee_id) {
            $can_view_others = current_user_can('manage_options') || current_user_can('edit_hrm_employees');
            if ( ! $can_view_others ) {
                // Intentar resolver si el employee_id corresponde al empleado vinculado al usuario actual
                if (!class_exists('HRM_DB_Empleados')) {
                    require_once plugin_dir_path(__FILE__) . 'includes/db/class-hrm-db-empleados.php';
                }
                $db_emp_check = new HRM_DB_Empleados();
                $my_employee = $db_emp_check->get_by_user_id( get_current_user_id() );
                $my_emp_id = $my_employee ? intval( $my_employee->id ) : 0;

                if ( $employee_id !== $my_emp_id ) {
                    wp_die('No tienes permisos para ver documentos de otros empleados.');
                }
                // Si coincide, permitimos la visualización (es el propio usuario)
            }
        }
    }

    // Render counter for debugging duplicates
    $GLOBALS['hrm_render_count'] = isset($GLOBALS['hrm_render_count']) ? $GLOBALS['hrm_render_count'] + 1 : 1;
    error_log('[HRM-DEBUG] hrm_render_mis_documentos_tipo_page called. render_count=' . intval($GLOBALS['hrm_render_count']));

    // If a previous HRM layout was already rendered in this request, avoid rendering another wrapper
    if (isset($GLOBALS['hrm_render_count']) && intval($GLOBALS['hrm_render_count']) > 1) {
        error_log('[HRM-DEBUG] Skipping outer wrapper to avoid duplicate layout (type).');
        $view_key = 'mis-documentos-tipo-' . intval($type_id);
        if ($type_id) {
            hrm_ensure_db_classes();
            $db_docs_local = new HRM_DB_Documentos();
            $all_types_local = $db_docs_local->get_all_types();
            $type_slug_local = isset($all_types_local[$type_id]) ? sanitize_title($all_types_local[$type_id]) : '';

            $slug_path = $type_slug_local ? HRM_PLUGIN_DIR . "views/mis-documentos-tipo-" . $type_slug_local . ".php" : '';
            $id_path = HRM_PLUGIN_DIR . "views/mis-documentos-tipo-" . intval($type_id) . ".php";

            if (empty($GLOBALS['hrm_view_rendered'][$view_key])) {
                $GLOBALS['hrm_view_rendered'][$view_key] = true;
                if ($slug_path && file_exists($slug_path)) {
                    hrm_get_template_part('mis-documentos-tipo', $type_slug_local, compact('type_id', 'employee_id'));
                } elseif (file_exists($id_path)) {
                    hrm_get_template_part('mis-documentos-tipo', $type_id, compact('type_id', 'employee_id'));
                } else {
                    hrm_get_template_part('mis-documentos-tipo', '', compact('type_id', 'employee_id'));
                }
            } else {
                error_log('[HRM-DEBUG] Already rendered ' . $view_key . ', skipping duplicate call.');
            }
        } else {
            $view_key = 'mis-documentos-tipo-0';
            if (empty($GLOBALS['hrm_view_rendered'][$view_key])) {
                $GLOBALS['hrm_view_rendered'][$view_key] = true;
                hrm_get_template_part('mis-documentos-tipo', '', compact('type_id', 'employee_id'));
            } else {
                error_log('[HRM-DEBUG] Already rendered ' . $view_key . ', skipping duplicate call.');
            }
        }
        return;
    }

    // Debug: registrar acceso a la página con contexto
    error_log('[HRM-DEBUG] hrm_render_mis_documentos_tipo_page called - type_id=' . intval($type_id) . ' employee_id=' . intval($employee_id) . ' current_user_id=' . get_current_user_id() . ' roles=' . json_encode(wp_get_current_user()->roles));

    echo '<div class="wrap hrm-admin-wrap">';
    echo '<div class="hrm-admin-layout">';
    hrm_get_template_part('partials/sidebar-loader');
    echo '<main class="hrm-content">';
    // Intentar cargar plantilla específica por tipo (prefiere slug-nombre, fallback id)
    if ($type_id) {
        hrm_ensure_db_classes();
        $db_docs_local = new HRM_DB_Documentos();
        $all_types_local = $db_docs_local->get_all_types();
        $type_slug_local = isset($all_types_local[$type_id]) ? sanitize_title($all_types_local[$type_id]) : '';

        $slug_path = $type_slug_local ? HRM_PLUGIN_DIR . "views/mis-documentos-tipo-" . $type_slug_local . ".php" : '';
        $id_path = HRM_PLUGIN_DIR . "views/mis-documentos-tipo-" . intval($type_id) . ".php";

        if ($slug_path && file_exists($slug_path)) {
            $GLOBALS['hrm_view_rendered']['mis-documentos-tipo-' . intval($type_id)] = true;
            hrm_get_template_part('mis-documentos-tipo', $type_slug_local, compact('type_id', 'employee_id'));
        } elseif (file_exists($id_path)) {
            $GLOBALS['hrm_view_rendered']['mis-documentos-tipo-' . intval($type_id)] = true;
            hrm_get_template_part('mis-documentos-tipo', $type_id, compact('type_id', 'employee_id'));
        } else {
            $GLOBALS['hrm_view_rendered']['mis-documentos-tipo-' . intval($type_id)] = true;
            hrm_get_template_part('mis-documentos-tipo', '', compact('type_id', 'employee_id'));
        }
    } else {
        hrm_get_template_part('mis-documentos-tipo', '', compact('type_id', 'employee_id'));
    }
    echo '</main>';
    echo '</div>';
    echo '</div>';
}

/**
 * Renderizar página para ver/editar solicitud de vacaciones (ADMIN, EDITORES DE VACACIONES y SUPERVISORES)
 */
function hrm_render_formulario_solicitud_page()
{
    // IMPORTANTE: Forzar actualización de capacidades del usuario actual
    $current_user = wp_get_current_user();
    if ($current_user && $current_user->ID) {
        $current_user->get_role_caps();
    }

    // Verificar permisos - Admins, editores de vacaciones, o usuarios con view_hrm_admin_views pueden ver
    $puede_ver = current_user_can('manage_options')
        || current_user_can('manage_hrm_vacaciones')
        || current_user_can('view_hrm_admin_views');

    if (!$puede_ver) {
        wp_die('No tienes permisos para acceder a esta página.');
    }

    hrm_get_template_part('vacaciones-formulario-vista');
}