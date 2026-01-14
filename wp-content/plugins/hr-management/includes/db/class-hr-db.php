<?php
/**
 * Clase para gestión de la base de datos del plugin HR Management.
 *
 * Proporciona métodos para interactuar con la tabla de empleados,
 * incluyendo detección automática de nombres de tabla y columnas.
 *
 * @package HR_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Evitar acceso directo


class HRM_DB {
    private static $instance = null; //patron singleton para tener una sola instancia
    private $db;
    private $table_employees;
    private $col_id       = null;
    private $col_wp_user  = null;

    private function __construct() {
        global $wpdb; // Objeto global de base de datos de WordPress
        $this->db              = $wpdb;
        $this->table_employees = $this->resolve_table_name();
        $this->detect_columns();
    }

    /**
     * Localiza la tabla de empleados según el prefijo real.
     * Si no encuentra la tabla con el prefijo actual, prueba con base_prefix y sin prefijo.
     */
    private function resolve_table_name() {
        // Permite definir un nombre fijo por constante.
        $const_table = defined( 'HRM_TABLE_EMPLOYEES' ) ? HRM_TABLE_EMPLOYEES : '';

        $candidates = array_filter( array(
            $const_table,
            $this->db->prefix . 'hrm_employees',
            $this->db->base_prefix . 'hrm_employees',
            $this->db->prefix . 'rrhh_empleados',
            $this->db->base_prefix . 'rrhh_empleados',
            'hrm_employees',
            'rrhh_empleados',
        ) );

        foreach ( $candidates as $table ) {
            $exists = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists === $table ) {
                return $table;
            }
        }

        // Búsqueda por patrón para sufijo común (ej. Bu6K9_rrhh_empleados).
        $pattern = '%rrhh_empleados';
        $match   = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
        if ( $match ) {
            return $match;
        }

        // Devuelve el prefijo normal como predeterminado aunque no exista todavía.
        return $this->db->prefix . 'hrm_empleados';
    }

    /**
     * Detecta automáticamente los nombres reales de las columnas ID y usuario WP.
     */
    private function detect_columns() {
        // Si la tabla no existe aún, usa nombres por defecto.
        $table_exists = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $this->table_employees ) );
        if ( ! $table_exists ) {
            $this->col_id      = 'id';
            $this->col_wp_user = 'wp_user_id';
            return;
        }

        // Obtiene columnas de la tabla.
        $columns = $this->db->get_results( "DESCRIBE {$this->table_employees}" );
        if ( empty( $columns ) ) {
            $this->col_id      = 'id';
            $this->col_wp_user = 'wp_user_id';
            return;
        }

        $col_names = array_column( $columns, 'Field' );

        // Detecta nombre de columna ID.
        $id_candidates = array( 'id_empleado', 'id', 'ID', 'empleado_id' );
        foreach ( $id_candidates as $candidate ) {
            if ( in_array( $candidate, $col_names, true ) ) {
                $this->col_id = $candidate;
                break;
            }
        }
        if ( ! $this->col_id ) {
            $this->col_id = 'id';
        }

        // Detecta nombre de columna usuario WP.
        $user_candidates = array( 'wp_user_id', 'user_id', 'usuario_wp', 'usuario_id' );
        foreach ( $user_candidates as $candidate ) {
            if ( in_array( $candidate, $col_names, true ) ) {
                $this->col_wp_user = $candidate;
                break;
            }
        }
        if ( ! $this->col_wp_user ) {
            $this->col_wp_user = 'wp_user_id';
        }
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Obtener empleados (join con usuarios de WP opcional).
     */
    public function get_employees() {
        return $this->db->get_results( "SELECT * FROM {$this->table_employees}" );
    }

    /**
     * Obtener un empleado por id.
     */
    public function get_employee( $id ) {
        $id = (int) $id;
        if ( $id <= 0 ) {
            return null;
        }

        $col = $this->col_id ? $this->col_id : 'id';
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_employees} WHERE {$col} = %d",
                $id
            )
        );
    }

    /**
     * Insertar empleado.
     */
    public function insert_employee( $data ) {
        $col_user = $this->col_wp_user ? $this->col_wp_user : 'wp_user_id';
        return $this->db->insert(
            $this->table_employees,
            array(
                $col_user    => isset( $data['wp_user_id'] ) ? (int) $data['wp_user_id'] : 0,
                'position'   => isset( $data['position'] ) ? sanitize_text_field( $data['position'] ) : '',
                'department' => isset( $data['department'] ) ? sanitize_text_field( $data['department'] ) : '',
                'phone'      => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
                'join_date'  => isset( $data['join_date'] ) ? sanitize_text_field( $data['join_date'] ) : null,
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Actualizar empleado por id.
     */
    public function update_employee( $id, $data ) {
        $col = $this->col_id ? $this->col_id : 'id';
        return $this->db->update(
            $this->table_employees,
            array(
                'position'   => isset( $data['position'] ) ? sanitize_text_field( $data['position'] ) : '',
                'department' => isset( $data['department'] ) ? sanitize_text_field( $data['department'] ) : '',
                'phone'      => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
                'join_date'  => isset( $data['join_date'] ) ? sanitize_text_field( $data['join_date'] ) : null,
            ),
            array( $col => (int) $id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Borrar empleado.
     */
    public function delete_employee( $id ) {
        $col = $this->col_id ? $this->col_id : 'id';
        return $this->db->delete( $this->table_employees, array( $col => (int) $id ), array( '%d' ) );
    }

    /**
     * Obtener nombre real de la columna ID.
     */
    public function get_id_column() {
        return $this->col_id ?: 'id';
    }

    /**
     * Obtener nombre real de la columna usuario WP.
     */
    public function get_user_column() {
        return $this->col_wp_user ?: 'wp_user_id';
    }

    /**
     * Obtiene el mapeo de columnas reales en la tabla.
     * Prueba variantes en español e inglés para cada campo esperado.
     */
    public function get_column_mapping() {
        // Caché en propiedad estática para evitar múltiples DESCRIBE
        static $mapping = null;
        if ( $mapping !== null ) {
            return $mapping;
        }

        $mapping = array();

        // Mapeos de columnas esperadas → variantes a buscar
        $expected_columns = array(
            'position'   => array( 'puesto', 'position', 'posicion', 'cargo', 'job_title', 'titulo' ),
            'department' => array( 'departamento', 'department', 'dept', 'area', 'seccion' ),
            'phone'      => array( 'telefono', 'phone', 'tel', 'movil', 'celular', 'telephone' ),
            'join_date'  => array( 'fecha_ingreso', 'join_date', 'fecha_vinculacion', 'start_date', 'fecha_inicio' ),
            'name'       => array( 'nombre', 'name', 'first_name', 'nombre_completo' ),
            'last_name'  => array( 'apellido', 'last_name', 'apellidos', 'surname' ),
            'email'      => array( 'correo', 'email', 'mail', 'e_mail' ),
        );

        // Obtiene todas las columnas reales de la tabla
        $columns = $this->db->get_results( "DESCRIBE {$this->table_employees}" );
        if ( empty( $columns ) ) {
            return $mapping;
        }

        $col_names = array_column( $columns, 'Field' );

        // Para cada columna esperada, busca el nombre real
        foreach ( $expected_columns as $alias => $variants ) {
            foreach ( $variants as $variant ) {
                if ( in_array( $variant, $col_names, true ) ) {
                    $mapping[ $alias ] = $variant;
                    break;
                }
            }
            // Si no encuentra, usa el alias por defecto
            if ( ! isset( $mapping[ $alias ] ) ) {
                $mapping[ $alias ] = $alias;
            }
        }

        return $mapping;
    }

    /**
     * Obtiene el nombre real de una columna dado un alias esperado.
     */
    public function get_column_name_for( $alias ) {
        $mapping = $this->get_column_mapping();
        return isset( $mapping[ $alias ] ) ? $mapping[ $alias ] : $alias;
    }
}

// Helper global para acceso rápido si se prefiere.
function hrm_db() {
    return HRM_DB::instance();
}
