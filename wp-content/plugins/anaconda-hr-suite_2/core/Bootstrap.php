<?php
/**
 * Bootstrap principal del plugin
 * Gestiona la inicialización, carga de componentes y hooks
 */

namespace Anaconda\HRSuite\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bootstrap {

    /**
     * Inicializar el plugin
     */
    public function init() {
        // Cargar autoloader de clases
        $this->load_autoloader();

        // Cargar helpers y funciones globales
        require_once ANACONDA_HRSUITE_DIR . 'helpers/functions.php';

        // Ejecutar inicializaciones
        add_action( 'init', [ $this, 'setup_roles' ], 5 );
        add_action( 'init', [ $this, 'setup_plugin' ], 10 );
        // NO ejecutar migraciones - usamos tablas existentes
        // add_action( 'admin_init', [ $this, 'run_migrations' ], 10 );

        // Cargar admin
        if ( is_admin() ) {
            $this->load_admin();
        }

        // Hooks de acción
        add_action( 'admin_menu', [ $this, 'register_menu_pages' ], 10 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Activación del plugin
     */
    public function activate() {
        // NO crear tablas - usamos tablas existentes de Plugin A
        // Solo validar que existan
        Database\Migrator::validate_tables();

        // Crear roles
        Roles\RoleManager::create_roles();

        // Marcar que el plugin está activo
        update_option( 'anaconda_hrsuite_activated', 1 );
    }

    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Mantener datos intactos
        // No eliminamos tablas ni roles para compatibilidad
    }

    /**
     * Cargar autoloader de clases PSR-4
     */
    private function load_autoloader() {
        spl_autoload_register( function ( $class ) {
            // Namespace del plugin
            $prefix = 'Anaconda\\HRSuite\\';

            // Verificar si la clase pertenece al plugin
            if ( 0 !== strpos( $class, $prefix ) ) {
                return;
            }

            // Remover el prefix
            $relative_class = substr( $class, strlen( $prefix ) );

            // Convertir namespace a ruta de archivo
            $file = ANACONDA_HRSUITE_DIR . str_replace( '\\', '/', $relative_class ) . '.php';

            // Si el archivo existe, cargarlo
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        } );
    }

    /**
     * Setup inicial del plugin
     */
    public function setup_plugin() {
        // Cargar textdomain para traducciones
        load_plugin_textdomain(
            'anaconda-hr-suite',
            false,
            dirname( ANACONDA_HRSUITE_BASENAME ) . '/languages/'
        );
    }

    /**
     * Setup de roles y capacidades
     */
    public function setup_roles() {
        Roles\RoleManager::create_roles();
        Roles\RoleManager::ensure_capabilities();
    }

    /**
     * Ejecutar migraciones de datos
     */
    public function run_migrations() {
        // Solo ejecutar si no se ha migrado antes
        if ( ! get_option( 'anaconda_hrsuite_migrated' ) ) {
            Database\Migrator::migrate_legacy_data();
            update_option( 'anaconda_hrsuite_migrated', 1 );
        }
    }

    /**
     * Cargar componentes de admin
     */
    private function load_admin() {
        // Cargar controladores de admin
        require_once ANACONDA_HRSUITE_DIR . 'admin/Controllers/DashboardController.php';
        require_once ANACONDA_HRSUITE_DIR . 'admin/Controllers/EmployeesController.php';
        require_once ANACONDA_HRSUITE_DIR . 'admin/Controllers/VacationsController.php';
    }

    /**
     * Registrar páginas del menú de admin
     */
    public function register_menu_pages() {
        if ( ! current_user_can( 'manage_hrsuite' ) ) {
            return;
        }

        // Menú principal
        add_menu_page(
            __( 'HR Suite', 'anaconda-hr-suite' ),
            __( 'HR Suite', 'anaconda-hr-suite' ),
            'manage_hrsuite',
            'anaconda-hr-suite',
            [ new Admin\Controllers\DashboardController(), 'render' ],
            'dashicons-groups',
            25
        );

        // Submenu: Dashboard
        add_submenu_page(
            'anaconda-hr-suite',
            __( 'Dashboard', 'anaconda-hr-suite' ),
            __( 'Dashboard', 'anaconda-hr-suite' ),
            'manage_hrsuite',
            'anaconda-hr-suite',
            [ new Admin\Controllers\DashboardController(), 'render' ]
        );

        // Submenu: Empleados
        add_submenu_page(
            'anaconda-hr-suite',
            __( 'Empleados', 'anaconda-hr-suite' ),
            __( 'Empleados', 'anaconda-hr-suite' ),
            'manage_hrsuite_employees',
            'anaconda-hr-suite-employees',
            [ new Admin\Controllers\EmployeesController(), 'render_list' ]
        );

        // Submenu: Solicitudes de Vacaciones
        add_submenu_page(
            'anaconda-hr-suite',
            __( 'Solicitudes de Vacaciones', 'anaconda-hr-suite' ),
            __( 'Solicitudes', 'anaconda-hr-suite' ),
            'manage_hrsuite_vacations',
            'anaconda-hr-suite-vacations',
            [ new Admin\Controllers\VacationsController(), 'render_list' ]
        );
    }

    /**
     * Cargar estilos y scripts de admin
     */
    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'anaconda-hr-suite-admin',
            ANACONDA_HRSUITE_URL . 'admin/Assets/css/admin.css',
            [],
            ANACONDA_HRSUITE_VERSION
        );

        wp_enqueue_script(
            'anaconda-hr-suite-admin',
            ANACONDA_HRSUITE_URL . 'admin/Assets/js/admin.js',
            [ 'jquery' ],
            ANACONDA_HRSUITE_VERSION,
            true
        );

        // Pasar datos AJAX
        wp_localize_script(
            'anaconda-hr-suite-admin',
            'anacondaHRSuite',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'anaconda-hr-suite-nonce' ),
            ]
        );
    }
}
