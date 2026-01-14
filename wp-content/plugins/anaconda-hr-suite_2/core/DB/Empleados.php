<?php
/**
 * Clase de BD para Empleados
 * Gestiona la tabla existente: wp_ahr_empleados de Plugin A
 * Mapea campos automáticamente entre formatos
 */

namespace Anaconda\HRSuite\Core\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Empleados {

    protected $wpdb;
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'ahr_empleados'; // Tabla de Plugin A
    }

    /**
     * Crear un empleado
     * Mapea nuestro formato al formato de Plugin A
     */
    public function create( $data ) {
        // Validar datos requeridos
        if ( empty( $data['rut'] ) || empty( $data['nombre'] ) || empty( $data['apellido'] ) ) {
            return false;
        }

        // Verificar RUT único
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare( "SELECT id FROM {$this->table} WHERE rut = %s", $data['rut'] )
        );

        if ( $exists ) {
            return false;
        }

        // Mapear a formato Plugin A
        $insert_data = [
            'rut'           => sanitize_text_field( $data['rut'] ),
            'nombres'       => sanitize_text_field( $data['nombre'] ), // Mapeo: nombre → nombres
            'apellidos'     => sanitize_text_field( $data['apellido'] ), // Mapeo: apellido → apellidos
            'email'         => sanitize_email( $data['email'] ?? '' ),
            'departamento'  => sanitize_text_field( $data['departamento'] ?? '' ),
            'cargo'         => sanitize_text_field( $data['puesto'] ?? '' ), // Mapeo: puesto → cargo
            'fecha_ingreso' => ! empty( $data['fecha_ingreso'] ) ? sanitize_text_field( $data['fecha_ingreso'] ) : current_time( 'Y-m-d' ),
            'wp_user_id'    => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
            'estado'        => sanitize_text_field( $data['estado'] ?? 'Activo' ),
        ];

        $result = $this->wpdb->insert( $this->table, $insert_data );
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Obtener un empleado por ID
     */
    public function get( $id ) {
        $id = absint( $id );
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id )
        );
        return $row ? $this->map_employee( $row ) : null;
    }

    /**
     * Obtener empleado por user_id de WordPress
     */
    public function get_by_user_id( $user_id ) {
        $user_id = absint( $user_id );
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE wp_user_id = %d", $user_id )
        );
        return $row ? $this->map_employee( $row ) : null;
    }

    /**
     * Obtener empleado por RUT
     */
    public function get_by_rut( $rut ) {
        $rut = sanitize_text_field( $rut );
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE rut = %s", $rut )
        );
        return $row ? $this->map_employee( $row ) : null;
    }

    /**
     * Obtener todos los empleados
     */
    public function get_all( $args = [] ) {
        $defaults = [
            'estado'       => 'Activo',
            'departamento' => null,
            'limit'        => -1,
            'offset'       => 0,
            'orderby'      => 'apellidos',
            'order'        => 'ASC',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = [];

        if ( null !== $args['estado'] ) {
            $estado = sanitize_text_field( $args['estado'] );
            $where[] = $this->wpdb->prepare( "estado = %s", $estado );
        }

        if ( null !== $args['departamento'] ) {
            $departamento = sanitize_text_field( $args['departamento'] );
            $where[] = $this->wpdb->prepare( "departamento = %s", $departamento );
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $orderby = in_array( $args['orderby'], [ 'nombres', 'apellidos', 'rut', 'id', 'fecha_ingreso' ] ) ? $args['orderby'] : 'apellidos';
        $order   = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $args['order'] ) : 'ASC';

        $limit_clause = '';
        if ( $args['limit'] > 0 ) {
            $limit = absint( $args['limit'] );
            $offset = absint( $args['offset'] );
            $limit_clause = " LIMIT {$limit} OFFSET {$offset}";
        }

        $query = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order}{$limit_clause}";

        $results = $this->wpdb->get_results( $query );
        return array_map( [ $this, 'map_employee' ], $results );
    }

    /**
     * Actualizar un empleado
     */
    public function update( $id, $data ) {
        $id = absint( $id );

        $update_data = [];

        // Mapear campos
        if ( isset( $data['nombre'] ) ) {
            $update_data['nombres'] = sanitize_text_field( $data['nombre'] );
        }
        if ( isset( $data['apellido'] ) ) {
            $update_data['apellidos'] = sanitize_text_field( $data['apellido'] );
        }
        if ( isset( $data['email'] ) ) {
            $update_data['email'] = sanitize_email( $data['email'] );
        }
        if ( isset( $data['departamento'] ) ) {
            $update_data['departamento'] = sanitize_text_field( $data['departamento'] );
        }
        if ( isset( $data['puesto'] ) ) {
            $update_data['cargo'] = sanitize_text_field( $data['puesto'] );
        }
        if ( isset( $data['fecha_ingreso'] ) ) {
            $update_data['fecha_ingreso'] = sanitize_text_field( $data['fecha_ingreso'] );
        }
        if ( isset( $data['estado'] ) ) {
            $update_data['estado'] = sanitize_text_field( $data['estado'] );
        }
        if ( isset( $data['user_id'] ) ) {
            $update_data['wp_user_id'] = absint( $data['user_id'] );
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        return $this->wpdb->update( $this->table, $update_data, [ 'id' => $id ] ) !== false;
    }

    /**
     * Eliminar un empleado (soft delete)
     */
    public function delete( $id ) {
        $id = absint( $id );
        return $this->update( $id, [ 'estado' => 'Inactivo' ] );
    }

    /**
     * Contar empleados activos
     */
    public function count_active() {
        $result = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE estado = 'Activo'"
        );
        return absint( $result );
    }

    /**
     * Buscar empleados por nombre o RUT
     */
    public function search( $search_term, $limit = 10 ) {
        $search_term = '%' . $this->wpdb->esc_like( $search_term ) . '%';
        $limit = absint( $limit );

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                WHERE (nombres LIKE %s OR apellidos LIKE %s OR rut LIKE %s)
                AND estado = 'Activo'
                LIMIT %d",
                $search_term, $search_term, $search_term, $limit
            )
        );

        return array_map( [ $this, 'map_employee' ], $results );
    }

    /**
     * Obtener empleados por departamento
     */
    public function get_by_department( $department ) {
        $department = sanitize_text_field( $department );
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                WHERE departamento = %s AND estado = 'Activo'
                ORDER BY apellidos ASC",
                $department
            )
        );

        return array_map( [ $this, 'map_employee' ], $results );
    }

    /**
     * Mapear datos de Plugin A al formato de nuestra API
     */
    private function map_employee( $employee ) {
        return [
            'id'              => $employee->id,
            'rut'             => $employee->rut,
            'nombre'          => $employee->nombres,
            'apellido'        => $employee->apellidos,
            'email'           => $employee->email,
            'departamento'    => $employee->departamento,
            'puesto'          => $employee->cargo,
            'fecha_ingreso'   => $employee->fecha_ingreso,
            'user_id'         => $employee->wp_user_id,
            'estado'          => $employee->estado,
            'created_at'      => $employee->created_at,
            // Mantener originales también por compatibilidad
            'nombres'         => $employee->nombres,
            'apellidos'       => $employee->apellidos,
            'cargo'           => $employee->cargo,
            'wp_user_id'      => $employee->wp_user_id,
        ];
    }
}


        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                WHERE (nombre LIKE %s OR apellido LIKE %s OR rut LIKE %s) 
                AND estado = 1
                LIMIT %d",
                '%' . $this->wpdb->esc_like( $search_term ) . '%',
                '%' . $this->wpdb->esc_like( $search_term ) . '%',
                '%' . $this->wpdb->esc_like( $search_term ) . '%',
                $limit
            )
        );
    }
}
