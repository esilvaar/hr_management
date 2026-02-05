<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HRM_DB_Numeros_Emergencia extends HRM_DB_Table {

    protected function base_table_name() {
        return 'rrhh_numeros_emergencia';
    }

    /**
     * Definición de columnas y sus alias
     */
    protected function expected_columns() {
        return [
            'id'                 => [ 'id' ],
            'rut_empleado'       => [ 'rut_empleado' ],
            'nombre_contacto'    => [ 'nombre_contacto' ],
            'numero_telefono'    => [ 'numero_telefono' ],
            'relacion'           => [ 'relacion' ],
        ];
    }

    /**
     * Obtener todos los contactos de emergencia de un empleado por RUT
     */
    public function get_by_rut( $rut ) {
        $rut = sanitize_text_field( $rut );
        $results = $this->db->get_results( $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->col('rut_empleado')} = %s ORDER BY id DESC",
            $rut
        ) );
        return $results ?: [];
    }

    /**
     * Obtener un contacto de emergencia específico por ID
     */
    public function get_by_id( $id ) {
        $id = absint( $id );
        return $this->db->get_row( $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->col('id')} = %d",
            $id
        ) );
    }

    /**
     * Crear un nuevo contacto de emergencia
     */
    public function insert( $data ) {
        $data = $this->sanitize_data( $data );

        if ( empty( $data['rut_empleado'] ) || empty( $data['nombre_contacto'] ) || empty( $data['numero_telefono'] ) ) {
            return false;
        }

        $insert = $this->db->insert(
            $this->table,
            [
                $this->col('rut_empleado')    => $data['rut_empleado'],
                $this->col('nombre_contacto') => $data['nombre_contacto'],
                $this->col('numero_telefono') => $data['numero_telefono'],
                $this->col('relacion')        => $data['relacion'] ?? '',
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        return $insert ? $this->db->insert_id : false;
    }

    /**
     * Actualizar un contacto de emergencia
     */
    public function update( $id, $data ) {
        $id   = absint( $id );
        $data = $this->sanitize_data( $data );

        $update_data = [];
        if ( isset( $data['nombre_contacto'] ) ) {
            $update_data[ $this->col('nombre_contacto') ] = $data['nombre_contacto'];
        }
        if ( isset( $data['numero_telefono'] ) ) {
            $update_data[ $this->col('numero_telefono') ] = $data['numero_telefono'];
        }
        if ( isset( $data['relacion'] ) ) {
            $update_data[ $this->col('relacion') ] = $data['relacion'];
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        return $this->db->update(
            $this->table,
            $update_data,
            [ $this->col('id') => $id ],
            array_fill( 0, count( $update_data ), '%s' ),
            [ '%d' ]
        );
    }

    /**
     * Eliminar un contacto de emergencia
     */
    public function delete( $id ) {
        $id = absint( $id );
        return $this->db->delete(
            $this->table,
            [ $this->col('id') => $id ],
            [ '%d' ]
        );
    }

    /**
     * Sanitizar datos de entrada
     */
    private function sanitize_data( $data ) {
        return [
            'rut_empleado'    => isset( $data['rut_empleado'] ) ? sanitize_text_field( $data['rut_empleado'] ) : '',
            'nombre_contacto' => isset( $data['nombre_contacto'] ) ? sanitize_text_field( $data['nombre_contacto'] ) : '',
            'numero_telefono' => isset( $data['numero_telefono'] ) ? sanitize_text_field( $data['numero_telefono'] ) : '',
            'relacion'        => isset( $data['relacion'] ) ? sanitize_text_field( $data['relacion'] ) : '',
        ];
    }

    /**
     * Obtener todas los contactos de emergencia por employee ID
     * (requiere una consulta a la tabla de empleados)
     */
    public function get_by_employee_id( $employee_id ) {
        global $wpdb;
        
        $employee_id = absint( $employee_id );
        
        // Primero obtener el RUT del empleado
        $employee = $wpdb->get_row( $wpdb->prepare(
            "SELECT rut FROM {$wpdb->prefix}rrhh_empleados WHERE id_empleado = %d",
            $employee_id
        ) );

        if ( ! $employee ) {
            return [];
        }

        return $this->get_by_rut( $employee->rut );
    }
}
