<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HRM_DB_Documentos extends HRM_DB_Table {

    protected function base_table_name() {
        return 'rrhh_documentos';
    }

    protected function expected_columns() {
        return [
            'id'     => [ 'id' ],
            'rut'    => [ 'rut' ],             
            'tipo'   => [ 'tipo_documento' ],
            'anio'   => [ 'anio' ],
            'nombre' => [ 'nombre_archivo' ],
            'url'    => [ 'ruta_archivo' ],
            'fecha'  => [ 'fecha_carga' ],
        ];
    }

    /**
     * Buscar documentos por RUT
     * @param string $rut_empleado
     * @param string|null $tipo_documento Filtrar por tipo específico (ej: 'contrato', 'liquidaciones', 'licencia')
     */
    public function get_by_rut( $rut_empleado, $tipo_documento = null ) {
        $rut = sanitize_text_field( $rut_empleado );

        $sql = "SELECT 
                    {$this->col('id')} AS id,
                    {$this->col('rut')} AS rut,
                    {$this->col('tipo')} AS tipo,
                    {$this->col('anio')} AS anio,
                    {$this->col('nombre')} AS nombre,
                    {$this->col('url')} AS url,
                    {$this->col('fecha')} AS fecha
                FROM {$this->table()} 
                WHERE {$this->col('rut')} = %s";
        
        $params = [ $rut ];
        
        if ( ! empty( $tipo_documento ) ) {
            $sql .= " AND LOWER({$this->col('tipo')}) = %s";
            $params[] = strtolower( sanitize_text_field( $tipo_documento ) );
        }
        
        $sql .= " ORDER BY {$this->col('fecha')} DESC";

        return $this->db->get_results( $this->db->prepare( $sql, ...$params ) );
    }

    /**
     * Obtener un solo documento por ID (para eliminarlo)
     */
    public function get( $id ) {
        $sql = "SELECT 
                    {$this->col('id')} AS id,
                    {$this->col('url')} AS url,
                    {$this->col('nombre')} AS nombre
                FROM {$this->table()} 
                WHERE {$this->col('id')} = %d";
        
        return $this->db->get_row( $this->db->prepare( $sql, (int)$id ) );
    }

    public function create( $data ) {
        // Validar que el RUT exista en la tabla de empleados
        $rut = sanitize_text_field( $data['rut'] );
        $employee_exists = $this->db->get_var( 
            $this->db->prepare( 
                "SELECT COUNT(*) FROM {$this->db->prefix}rrhh_empleados WHERE rut = %s", 
                $rut 
            ) 
        );
        
        if ( ! $employee_exists ) {
            error_log( "HRM_DB_Documentos::create() - RUT no existe: $rut" );
            return false;
        }
        
        $insert_data = [
            $this->col('rut')    => $rut,
            $this->col('tipo')   => wp_kses_post( $data['tipo'] ),
            $this->col('anio')   => intval( $data['anio'] ?? date('Y') ),
            $this->col('nombre') => sanitize_file_name( $data['nombre'] ),
            $this->col('url')    => esc_url_raw( $data['url'] ),
            $this->col('fecha')  => current_time( 'mysql' ),
        ];
        
        $result = $this->db->insert( $this->table(), $insert_data );
        
        if ( ! $result ) {
            error_log( 'HRM_DB_Documentos::create() failed - ' . $this->db->last_error . ' - Data: ' . json_encode( $insert_data ) );
        }
        
        return $result;
    }

    public function delete( $id ) {
        return $this->db->delete( 
            $this->table(), 
            [ $this->col('id') => $id ], 
            [ '%d' ] 
        );
    }
}