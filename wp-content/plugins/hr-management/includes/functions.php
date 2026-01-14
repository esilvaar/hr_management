<?php
/**
 * Funciones personalizadas del plugin HR Management
 * 
 * Este archivo centraliza todo el enqueueing de scripts y estilos,
 * así como funciones helper globales del plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================================
 * CONSTANTES DE CONFIGURACIÓN
 * ============================================================================
 */

// Definir solo si no existen
if ( ! defined( 'HRM_AVATAR_MAX_SIZE' ) ) {
    define( 'HRM_AVATAR_MAX_SIZE', 5 * MB_IN_BYTES );  // 5MB
}

if ( ! defined( 'HRM_AVATAR_ALLOWED_TYPES' ) ) {
    define( 'HRM_AVATAR_ALLOWED_TYPES', array( 'image/jpeg', 'image/png', 'image/gif' ) );
}

if ( ! defined( 'HRM_AVATAR_UPLOAD_DIR' ) ) {
    define( 'HRM_AVATAR_UPLOAD_DIR', 'hrm-avatars' );
}

if ( ! defined( 'HRM_CACHE_TIMEOUT' ) ) {
    define( 'HRM_CACHE_TIMEOUT', HOUR_IN_SECONDS );  // 1 hora
}

/**
 * ============================================================================
 * ENQUEUE DE SCRIPTS Y ESTILOS
 * ============================================================================
 */

/**
 * Encolar Bootstrap (CDN) solo en las páginas del admin del plugin.
 * Evita cargar Bootstrap en todo el admin para reducir conflictos.
 */
function hrm_enqueue_bootstrap( $hook ) {
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    // CSS de Bootstrap
    wp_enqueue_style(
        'hrm-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
        array(),
        '5.3.2'
    );

    // JS de Bootstrap (bundle incluye Popper)
    wp_enqueue_script(
        'hrm-bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.3.2',
        true
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_bootstrap' );

/**
 * Encolar estilos principales del plugin
 */
function hrm_enqueue_main_styles( $hook ) {
    // Cargar solo en páginas del plugin
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    wp_enqueue_style(
        'hrm-style',
        HRM_PLUGIN_URL . 'assets/css/plugin-base.css',
        array(),
        HRM_PLUGIN_VERSION
    );

    // Cargar estilos personalizados de checkboxes DESPUÉS de Bootstrap
    // Usar filemtime para forzar recarga del caché
    $checkboxes_css_file = plugin_dir_path( __DIR__ ) . 'assets/css/checkboxes-custom.css';
    $checkboxes_css_version = file_exists( $checkboxes_css_file ) ? filemtime( $checkboxes_css_file ) : HRM_PLUGIN_VERSION;
    
    wp_enqueue_style(
        'hrm-checkboxes-custom',
        HRM_PLUGIN_URL . 'assets/css/checkboxes-custom.css',
        array( 'hrm-bootstrap' ), // Depende de Bootstrap
        $checkboxes_css_version
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_main_styles' );

/**
 * Encolar estilos del módulo de vacaciones en admin
 */
function hrm_enqueue_vacaciones_admin_styles( $hook ) {
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    wp_enqueue_style(
        'hrm-vacaciones-admin-css',
        HRM_PLUGIN_URL . 'assets/css/vacaciones-admin-panel.css',
        array(),
        HRM_PLUGIN_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_vacaciones_admin_styles' );

/**
 * Encolar estilos del módulo de vacaciones en frontend
 */
function hrm_enqueue_vacaciones_frontend_styles() {
    // Solo usuarios logueados (empleados)
    if ( ! is_user_logged_in() ) {
        return;
    }

    wp_enqueue_style(
        'hrm-vacaciones-frontend-css',
        HRM_PLUGIN_URL . 'assets/css/hrm-vacaciones.css',
        array(),
        HRM_PLUGIN_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'hrm_enqueue_vacaciones_frontend_styles' );

/**
 * Encolar scripts del módulo de gestión de documentos en admin
 */
function hrm_enqueue_documents_list_scripts( $hook ) {
    // Cargar solo en páginas del plugin
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    wp_enqueue_script(
        'hrm-documents-list',
        HRM_PLUGIN_URL . 'assets/js/documents-list.js',
        array(),
        HRM_PLUGIN_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_documents_list_scripts' );

/**
 * Encolar scripts del formulario de carga de documentos en admin
 */
function hrm_enqueue_documents_upload_scripts( $hook ) {
    // Cargar solo en páginas del plugin
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    wp_enqueue_script(
        'hrm-documents-upload',
        HRM_PLUGIN_URL . 'assets/js/documents-upload.js',
        array(),
        HRM_PLUGIN_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_documents_upload_scripts' );

/**
 * Encolar scripts de inicialización de lista de documentos en admin
 */
function hrm_enqueue_documents_list_init_scripts( $hook ) {
    // Cargar solo en páginas del plugin
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    wp_enqueue_script(
        'hrm-documents-list-init',
        HRM_PLUGIN_URL . 'assets/js/documents-list-init.js',
        array( 'hrm-documents-list' ),
        HRM_PLUGIN_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_documents_list_init_scripts' );

/**
 * Encolar scripts principales del plugin
 */
function hrm_enqueue_main_scripts( $hook ) {
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    wp_enqueue_script(
        'hrm-script',
        HRM_PLUGIN_URL . 'assets/js/script.js',
        array(),
        HRM_PLUGIN_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_main_scripts' );

/**
 * Encolar assets exclusivos de la vista Employees Detail.
 */
function hrm_enqueue_employee_detail_assets( $hook ) {
    if ( strpos( $hook, 'hrm' ) === false ) {
        return;
    }

    $page  = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    $tab   = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
    $has_id = isset( $_GET['id'] ) && $_GET['id'] !== '';

    $pages_using_detail = array( 'hrm-mi-perfil', 'hrm-mi-perfil-info' );

    $should_enqueue = false;

    if ( in_array( $page, $pages_using_detail, true ) ) {
        $should_enqueue = true;
    }

    if ( $page === 'hrm-mi-perfil' && ( $tab === '' || $tab === 'profile' ) ) {
        $should_enqueue = true;
    }

    if ( $page === 'hrm-empleados' && ( $tab === 'profile' || $has_id ) ) {
        $should_enqueue = true;
    }

    if ( ! $should_enqueue ) {
        return;
    }

    wp_enqueue_style(
        'hrm-employees-detail',
        HRM_PLUGIN_URL . 'assets/css/employees-detail.css',
        array( 'hrm-style' ),
        HRM_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'hrm-employees-detail',
        HRM_PLUGIN_URL . 'assets/js/employees-detail.js',
        array(),
        HRM_PLUGIN_VERSION,
        true
    );
}
add_action( 'admin_enqueue_scripts', 'hrm_enqueue_employee_detail_assets' );

/**
 * Encolar script de toggle (collapse y cambio de texto)
 */
function mi_script_toggle() {
    $plugin_dir = plugin_dir_url(__DIR__);
    $script_url = $plugin_dir . 'assets/js/toggle.js';
    
    wp_enqueue_script(
        'toggle-datos',
        $script_url,
        array(),
        filemtime(plugin_dir_path(__DIR__) . 'assets/js/toggle.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'mi_script_toggle');
add_action('admin_enqueue_scripts', 'mi_script_toggle');

/**
 * Encolar script de manejo de avatares
 */
function mi_enqueue_avatar_handler() {
    $plugin_dir = plugin_dir_url(__DIR__);
    $script_url = $plugin_dir . 'assets/js/avatar-handler.js';
    
    wp_enqueue_script(
        'hrm-avatar-handler',
        $script_url,
        array(),
        filemtime(plugin_dir_path(__DIR__) . 'assets/js/avatar-handler.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'mi_enqueue_avatar_handler');
add_action('admin_enqueue_scripts', 'mi_enqueue_avatar_handler');

/**
 * Encolar script del selector de empleados
 */
function mi_enqueue_employee_selector() {
    $plugin_dir = plugin_dir_url(__DIR__);
    $script_url = $plugin_dir . 'assets/js/employee-selector.js';
    
    wp_enqueue_script(
        'hrm-employee-selector',
        $script_url,
        array(),
        filemtime(plugin_dir_path(__DIR__) . 'assets/js/employee-selector.js'),
        true
    );
    
    // Localizar datos para el script
    wp_localize_script(
        'hrm-employee-selector',
        'hrmData',
        array(
            'tab' => isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'list',
        )
    );
}
add_action('admin_enqueue_scripts', 'mi_enqueue_employee_selector');

/**
 * ============================================================================
 * FUNCIONES HELPER GLOBALES
 * ============================================================================
 */

/**
 * Verificar si el usuario actual puede editar un empleado específico
 * 
 * @param int $employee_id ID del empleado
 * @return bool True si tiene permisos, false otherwise
 */
function hrm_can_edit_employee( $employee_id ) {
    global $wpdb;
    
    $employee_id = intval( $employee_id );
    $current_user_id = get_current_user_id();
    $db = new HRM_DB_Empleados();
    $employee = $db->get( $employee_id );
    
    if ( ! $employee ) {
        return false;
    }
    
    // Admins siempre pueden editar
    if ( current_user_can( 'edit_hrm_employees' ) ) {
        return true;
    }
    
    // Gerentes pueden editar empleados de sus departamentos
    if ( current_user_can( 'edit_hrm_employees' ) || hrm_user_is_gerente( $current_user_id ) ) {
        // Obtener los departamentos que el gerente tiene a cargo
        $departamentos_gerente = hrm_get_departamentos_gerente( $current_user_id );
        
        // Si el empleado pertenece a uno de estos departamentos, el gerente puede editarlo
        if ( ! empty( $departamentos_gerente ) && in_array( $employee->departamento, $departamentos_gerente, true ) ) {
            return true;
        }
    }
    
    // Empleados solo pueden editar su propio perfil
    if ( current_user_can( 'view_hrm_own_profile' ) ) {
        return $employee && intval( $employee->user_id ) === $current_user_id;
    }
    
    return false;
}

/**
 * Verifica si un usuario es gerente
 * 
 * @param int $user_id ID del usuario
 * @return bool True si es gerente
 */
function hrm_user_is_gerente( $user_id ) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'rrhh_empleados';
    
    $puesto = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT puesto FROM {$table} WHERE user_id = %d AND estado = 1",
            intval( $user_id )
        )
    );
    
    return ! empty( $puesto ) && strtolower( $puesto ) === 'gerente';
}

/**
 * Obtiene los departamentos que un gerente tiene a cargo
 * 
 * @param int $user_id ID del usuario (gerente)
 * @return array Array de nombres de departamentos
 */
function hrm_get_departamentos_gerente( $user_id ) {
    global $wpdb;
    
    $table_empleados = $wpdb->prefix . 'rrhh_empleados';
    $table_gerencia = $wpdb->prefix . 'rrhh_gerencia_deptos';
    
    // Obtener el nombre del gerente
    $nombre_gerente = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT CONCAT(nombre, ' ', apellido) FROM {$table_empleados} WHERE user_id = %d",
            intval( $user_id )
        )
    );
    
    if ( empty( $nombre_gerente ) ) {
        return array();
    }
    
    // Obtener todos los departamentos a cargo del gerente
    $departamentos = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT depto_a_cargo FROM {$table_gerencia} 
             WHERE nombre_gerente = %s AND estado = 1",
            $nombre_gerente
        )
    );
    
    return ! empty( $departamentos ) ? $departamentos : array();
}

/**
 * Redirigir con mensaje de notificación
 * 
 * @param string $base_url URL base para redirección
 * @param string $message Mensaje a mostrar
 * @param string $type Tipo de mensaje: 'success', 'error', 'warning'
 * @return void
 */
function hrm_redirect_with_message( $base_url, $message, $type = 'error' ) {
    $query_var = 'message_' . $type;
    wp_redirect( add_query_arg( 
        [ $query_var => rawurlencode( $message ) ], 
        $base_url 
    ) );
    exit;
}

/**
 * Log de debug solo en WP_DEBUG mode
 * 
 * @param string $message Mensaje a registrar
 * @param mixed $data Datos adicionales para debug
 * @return void
 */
function hrm_debug_log( $message, $data = null ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $log_message = 'HR Management: ' . $message;
        if ( ! is_null( $data ) ) {
            $log_message .= ' | ' . ( is_string( $data ) ? $data : print_r( $data, true ) );
        }
        error_log( $log_message );
    }
}

/**
 * Obtener constante del plugin con fallback
 * 
 * @param string $const_name Nombre de la constante
 * @param mixed $default Valor por defecto si no existe
 * @return mixed Valor de la constante o default
 */
function hrm_get_const( $const_name, $default = null ) {
    return defined( $const_name ) ? constant( $const_name ) : $default;
}
