<?php
/**
 * Plugin Name: Anaconda HR Suite
 * Plugin URI: https://anacondaweb.com
 * Description: Sistema unificado de gestión de RRHH - Empleados, Vacaciones, Documentos
 * Version: 1.0.0
 * Author: Anaconda
 * Author URI: https://anacondaweb.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: anaconda-hr-suite
 * Domain Path: /languages
 * Requires: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ============================================================================
 * CONSTANTES DEL PLUGIN
 * ============================================================================
 */
define( 'ANACONDA_HRSUITE_VERSION', '1.0.0' );
define( 'ANACONDA_HRSUITE_FILE', __FILE__ );
define( 'ANACONDA_HRSUITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ANACONDA_HRSUITE_URL', plugin_dir_url( __FILE__ ) );
define( 'ANACONDA_HRSUITE_BASENAME', plugin_basename( __FILE__ ) );

// Namespace raíz
define( 'ANACONDA_HRSUITE_NAMESPACE', 'Anaconda\\HRSuite' );

/**
 * ============================================================================
 * CARGA DEL BOOTSTRAP
 * ============================================================================
 */
require_once ANACONDA_HRSUITE_DIR . 'core/Bootstrap.php';

// Inicializar plugin
$plugin = new Anaconda\HRSuite\Core\Bootstrap();
$plugin->init();

/**
 * ============================================================================
 * HOOKS DE ACTIVACIÓN Y DESACTIVACIÓN
 * ============================================================================
 */
register_activation_hook( ANACONDA_HRSUITE_FILE, [ $plugin, 'activate' ] );
register_deactivation_hook( ANACONDA_HRSUITE_FILE, [ $plugin, 'deactivate' ] );
