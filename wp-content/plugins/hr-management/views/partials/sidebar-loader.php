<?php
/**
 * Sidebar Loader - Carga la sidebar correcta según el rol del usuario
 * 
 * Roles soportados:
 * - administrator (manage_options)
 * - administrador_anaconda (manage_options)
 * - supervisor (edit_hrm_employees)
 * - editor_vacaciones (manage_hrm_vacaciones)
 * - empleado (view_hrm_own_profile)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Carga la sidebar unificada según capabilities del usuario
$current_user = wp_get_current_user();
error_log( '[HRM-DEBUG] Sidebar loader (unified): user_id=' . $current_user->ID . ', roles=' . json_encode( $current_user->roles ) );

require_once __DIR__ . '/sidebar.php';

