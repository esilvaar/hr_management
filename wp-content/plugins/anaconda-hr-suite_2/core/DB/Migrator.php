<?php
/**
 * Validador de Base de Datos
 * Verifica que existan las tablas de Plugin A
 * NO crea nuevas tablas - solo usa las existentes
 */

namespace Anaconda\HRSuite\Core\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Migrator {

    /**
     * Validar que existan las tablas requeridas de Plugin A
     * NO crea tablas - solo verifica
     */
    public static function validate_tables() {
        global $wpdb;

        $table_empleados = $wpdb->prefix . 'ahr_empleados';
        $table_vacaciones = $wpdb->prefix . 'ahr_vacaciones';

        // Verificar que existan las tablas de Plugin A
        $empleados_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_empleados'" ) === $table_empleados;
        $vacaciones_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_vacaciones'" ) === $table_vacaciones;

        if ( ! $empleados_exists || ! $vacaciones_exists ) {
            // Log de error si faltan tablas
            error_log( 'Anaconda HR Suite: Tablas de Plugin A no encontradas' );
            return false;
        }

        // Marcar como validado
        update_option( 'anaconda_hrsuite_tables_validated', 1 );
        return true;
    }

    /**
     * Información sobre las tablas usadas
     */
    public static function get_table_info() {
        global $wpdb;

        return [
            'empleados'   => $wpdb->prefix . 'ahr_empleados',
            'vacaciones'  => $wpdb->prefix . 'ahr_vacaciones',
            'users'       => $wpdb->prefix . 'users',
            'usermeta'    => $wpdb->prefix . 'usermeta',
        ];
    }
}

                descripcion TEXT,
                requiere_aprobacion TINYINT(1) DEFAULT 1,
                activo TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE KEY uk_nombre (nombre),
                KEY idx_activo (activo)
            ) {$charset_collate};";

            dbDelta( $sql );

            // Insertar tipos predeterminados
            self::insert_default_absence_types();
        }

        // ========================================
        // TABLA: Solicitudes de Ausencia
        // ========================================
        $table_ausencias = $wpdb->prefix . 'rrhh_solicitudes_ausencia';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_ausencias'" ) !== $table_ausencias ) {
            $sql = "CREATE TABLE {$table_ausencias} (
                id_solicitud BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_empleado BIGINT UNSIGNED NOT NULL,
                id_tipo_ausencia BIGINT UNSIGNED,
                fecha_inicio DATE NOT NULL,
                fecha_fin DATE NOT NULL,
                motivo TEXT,
                estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
                aprobado_por BIGINT UNSIGNED,
                fecha_resolucion DATETIME,
                observaciones_rechazo TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                KEY idx_id_empleado (id_empleado),
                KEY idx_estado (estado),
                KEY idx_fecha_inicio (fecha_inicio),
                KEY idx_aprobado_por (aprobado_por),
                FOREIGN KEY (id_empleado) REFERENCES {$wpdb->prefix}rrhh_empleados(id) ON DELETE CASCADE,
                FOREIGN KEY (id_tipo_ausencia) REFERENCES {$table_tipos_ausencia}(id) ON DELETE SET NULL,
                FOREIGN KEY (aprobado_por) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
            ) {$charset_collate};";

            dbDelta( $sql );
        }

        // ========================================
        // TABLA: Documentos
        // ========================================
        $table_documentos = $wpdb->prefix . 'rrhh_documentos';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_documentos'" ) !== $table_documentos ) {
            $sql = "CREATE TABLE {$table_documentos} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_empleado BIGINT UNSIGNED NOT NULL,
                tipo_documento VARCHAR(100),
                nombre_archivo VARCHAR(255) NOT NULL,
                ruta_archivo VARCHAR(255) NOT NULL,
                fecha_carga DATETIME DEFAULT CURRENT_TIMESTAMP,
                vigencia_hasta DATE,
                
                KEY idx_id_empleado (id_empleado),
                KEY idx_tipo_documento (tipo_documento),
                FOREIGN KEY (id_empleado) REFERENCES {$wpdb->prefix}rrhh_empleados(id) ON DELETE CASCADE
            ) {$charset_collate};";

            dbDelta( $sql );
        }

        // ========================================
        // TABLA: Historial de Ausencias
        // ========================================
        $table_historial = $wpdb->prefix . 'rrhh_historial_ausencias';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_historial'" ) !== $table_historial ) {
            $sql = "CREATE TABLE {$table_historial} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_solicitud BIGINT UNSIGNED NOT NULL,
                accion VARCHAR(50) NOT NULL,
                usuario_id BIGINT UNSIGNED,
                notas TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                KEY idx_id_solicitud (id_solicitud),
                KEY idx_usuario_id (usuario_id),
                FOREIGN KEY (id_solicitud) REFERENCES {$table_ausencias}(id_solicitud) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
            ) {$charset_collate};";

            dbDelta( $sql );
        }
    }

    /**
     * Insertar tipos de ausencia predeterminados
     */
    private static function insert_default_absence_types() {
        global $wpdb;

        $types = [
            [ 'nombre' => 'Vacaciones', 'descripcion' => 'Período de vacaciones', 'requiere_aprobacion' => 1 ],
            [ 'nombre' => 'Enfermedad', 'descripcion' => 'Ausencia por enfermedad', 'requiere_aprobacion' => 1 ],
            [ 'nombre' => 'Permiso Médico', 'descripcion' => 'Permiso con certificado médico', 'requiere_aprobacion' => 1 ],
            [ 'nombre' => 'Licencia', 'descripcion' => 'Licencia no remunerada', 'requiere_aprobacion' => 1 ],
            [ 'nombre' => 'Capacitación', 'descripcion' => 'Ausencia por capacitación', 'requiere_aprobacion' => 1 ],
        ];

        $table = $wpdb->prefix . 'rrhh_tipo_ausencia';

        foreach ( $types as $type ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE nombre = %s",
                    $type['nombre']
                )
            );

            if ( ! $exists ) {
                $wpdb->insert(
                    $table,
                    [
                        'nombre'                => $type['nombre'],
                        'descripcion'           => $type['descripcion'],
                        'requiere_aprobacion'   => $type['requiere_aprobacion'],
                        'activo'                => 1,
                    ],
                    [ '%s', '%s', '%d', '%d' ]
                );
            }
        }
    }

    /**
     * Migrar datos de plugins legados
     * Solo ejecuta una vez
     */
    public static function migrate_legacy_data() {
        // Verificar si ya se ha migrado
        if ( get_option( 'anaconda_hrsuite_migrated' ) ) {
            return;
        }

        // Migrar datos de PluginA (anaconda-hr)
        self::migrate_from_plugin_a();

        // Migrar datos de PluginB (hr-managment) - si es necesario
        self::migrate_from_plugin_b();

        // Marcar como migrado
        update_option( 'anaconda_hrsuite_migrated', 1 );
    }

    /**
     * Migrar empleados de PluginA (ahr_empleados)
     */
    private static function migrate_from_plugin_a() {
        global $wpdb;

        $old_table    = $wpdb->prefix . 'ahr_empleados';
        $new_table    = $wpdb->prefix . 'rrhh_empleados';
        $old_vacations = $wpdb->prefix . 'ahr_vacaciones';

        // Verificar si la tabla antigua existe
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_table'" ) !== $old_table ) {
            return;
        }

        // Obtener empleados de Plugin A
        $old_employees = $wpdb->get_results( "SELECT * FROM {$old_table}" );

        foreach ( $old_employees as $emp ) {
            // Verificar si ya existe
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$new_table} WHERE rut = %s",
                    $emp->rut
                )
            );

            if ( ! $exists ) {
                $wpdb->insert(
                    $new_table,
                    [
                        'rut'           => $emp->rut,
                        'nombre'        => $emp->nombres,
                        'apellido'      => $emp->apellidos,
                        'email'         => $emp->email,
                        'fecha_ingreso' => $emp->fecha_ingreso,
                        'departamento'  => $emp->departamento,
                        'puesto'        => $emp->cargo,
                        'user_id'       => $emp->wp_user_id,
                        'estado'        => ( $emp->estado === 'Activo' ) ? 1 : 0,
                    ],
                    [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ]
                );

                $new_emp_id = $wpdb->insert_id;

                // Migrar vacaciones de este empleado
                if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_vacations'" ) === $old_vacations ) {
                    self::migrate_vacations_from_plugin_a( $emp->id, $new_emp_id, $emp->user_id );
                }
            }
        }
    }

    /**
     * Migrar vacaciones de PluginA a nuevas ausencias
     */
    private static function migrate_vacations_from_plugin_a( $old_emp_id, $new_emp_id, $user_id ) {
        global $wpdb;

        $old_vacations = $wpdb->prefix . 'ahr_vacaciones';
        $new_ausencias = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
        $tipo_ausencia = $wpdb->prefix . 'rrhh_tipo_ausencia';

        // Obtener el ID del tipo de ausencia "Vacaciones"
        $tipo_id = $wpdb->get_var(
            "SELECT id FROM {$tipo_ausencia} WHERE nombre = 'Vacaciones'"
        );

        // Obtener vacaciones del empleado antiguo
        $old_vacs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$old_vacations} WHERE user_id = %d",
                $user_id
            )
        );

        foreach ( $old_vacs as $vac ) {
            // Verificar si ya existe
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id_solicitud FROM {$new_ausencias} 
                    WHERE id_empleado = %d AND fecha_inicio = %s AND fecha_fin = %s",
                    $new_emp_id,
                    $vac->fecha_inicio,
                    $vac->fecha_fin
                )
            );

            if ( ! $exists ) {
                // Mapear estados
                $estado = 'pendiente';
                if ( $vac->estado === 'APROBADA' ) {
                    $estado = 'aprobada';
                } elseif ( $vac->estado === 'RECHAZADA' ) {
                    $estado = 'rechazada';
                }

                $wpdb->insert(
                    $new_ausencias,
                    [
                        'id_empleado'      => $new_emp_id,
                        'id_tipo_ausencia' => $tipo_id,
                        'fecha_inicio'     => $vac->fecha_inicio,
                        'fecha_fin'        => $vac->fecha_fin,
                        'motivo'           => $vac->motivo,
                        'estado'           => $estado,
                        'aprobado_por'     => $vac->aprobado_por_id,
                        'fecha_resolucion' => $vac->fecha_resolucion,
                    ],
                    [ '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
                );
            }
        }
    }

    /**
     * Migrar datos de PluginB si es necesario
     */
    private static function migrate_from_plugin_b() {
        global $wpdb;

        $old_table = $wpdb->prefix . 'rrhh_empleados';
        $new_table = $wpdb->prefix . 'rrhh_empleados';

        // Si son la misma tabla, no hay nada que hacer
        if ( $old_table === $new_table ) {
            return;
        }

        // Plugin B ya usa la misma tabla que nuestro plugin
        // Por lo tanto no necesitamos migración
    }
}
