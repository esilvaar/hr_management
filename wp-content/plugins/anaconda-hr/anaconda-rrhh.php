<?php
/**
 * Plugin Name: Anaconda RRHH v2
 * Description: Sistema de Gestión de Vacaciones y Empleados (Versión 2.0 con Dashboard)
 * Version: 2.0
 * Author: Departamento TI
 * Text Domain: anaconda-hr
 */

if (!defined('ABSPATH'))
    exit;

// Definir constante para rutas
define('AHR_PATH', plugin_dir_path(__FILE__));

// 1. Incluir archivos funcionales
require_once AHR_PATH . 'includes/class-ahr-db.php';
require_once AHR_PATH . 'includes/admin-menu.php';
require_once AHR_PATH . 'includes/process-form.php';

// 2. Hook de activación para crear tablas en la Base de Datos
register_activation_hook(__FILE__, 'ahr_install_tables');

function ahr_install_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Tabla de Solicitudes (Vacaciones)
    // Agregamos fecha_resolucion y aprobado_por_id directamente
    $tabla_vacaciones = $wpdb->prefix . 'ahr_vacaciones';
    $sql1 = "CREATE TABLE $tabla_vacaciones (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        tipo varchar(50) NOT NULL,
        fecha_inicio date NOT NULL,
        fecha_fin date NOT NULL,
        motivo text,
        estado varchar(20) DEFAULT 'PENDIENTE',
        fecha_resolucion datetime DEFAULT NULL,
        aprobado_por_id bigint(20) UNSIGNED DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Tabla de Empleados
    $tabla_empleados = $wpdb->prefix . 'ahr_empleados';
    $sql2 = "CREATE TABLE $tabla_empleados (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        rut varchar(20) NOT NULL,
        nombres varchar(100) NOT NULL,
        apellidos varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        departamento varchar(100),
        cargo varchar(100),
        fecha_ingreso date,
        wp_user_id bigint(20) UNSIGNED DEFAULT 0,
        estado varchar(20) DEFAULT 'Activo',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta($sql1);
    dbDelta($sql2);
}

// 3. Configuración de Roles y Capacidades
add_action('init', 'ahr_setup_roles');

function ahr_setup_roles()
{
    // 1. Rol EMPLEADO (Solo puede ver sus cosas)
    add_role('ahr_empleado', 'Empleado Anaconda', array(
        'read' => true,
        'upload_files' => true
    ));

    // 2. Rol SUPERVISOR (Puede aprobar pero no configurar plugin)
    add_role('ahr_supervisor', 'Supervisor RRHH', array(
        'read' => true,
        'manage_options' => false, // No puede tocar ajustes generales
        'ahr_approve_requests' => true // Capacidad personalizada
    ));

    // 3. Rol EDITOR (Gestión de contenido, ya existe en WP pero lo aseguramos)
    $editor = get_role('editor');
    if ($editor) {
        $editor->add_cap('ahr_view_reports');
    }

    // 4. Admin ya tiene todo por defecto ('manage_options')
}