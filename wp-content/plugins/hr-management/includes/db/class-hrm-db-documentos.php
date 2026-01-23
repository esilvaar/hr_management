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
            'id_solicitud' => [ 'id_solicitud' ],       
            'tipo'   => [ 'tipo_documento' ],
            'anio'   => [ 'anio' ],
            'nombre' => [ 'nombre_archivo' ],
            'url'    => [ 'ruta_archivo' ],
            'fecha'  => [ 'fecha_carga' ],
            'año'=> [ 'anio'  ],
        ];
    }

    /**
     * Resolver nombre real de la tabla de tipos de documentos.
     * Intenta varias variantes y patrones (incluyendo Bu6K9_ prefix).
     * @return string Nombre de tabla resuelto
     */
    private function resolve_tipo_table() {
        if ( isset( $this->tipo_table ) && $this->tipo_table ) {
            return $this->tipo_table;
        }

        $candidates = array(
            $this->db->prefix . 'rrhh_tipos_documento',
            $this->db->prefix . 'rrhh_tipo_documentos',
            $this->db->base_prefix . 'rrhh_tipos_documento',
            $this->db->base_prefix . 'rrhh_tipo_documentos',
            'Bu6K9_rrhh_tipos_documento',
        );

        foreach ( $candidates as $cand ) {
            try {
                $found = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $cand ) );
                if ( $found === $cand ) {
                    $this->tipo_table = $cand;
                    return $this->tipo_table;
                }
            } catch ( Exception $e ) {
                // ignorar
            }
        }

        // Intentar búsqueda por patrón (cualquier tabla que contenga el slug)
        $patterns = array( 'rrhh_tipos_documento', 'rrhh_tipo_documentos' );
        foreach ( $patterns as $p ) {
            $like = '%' . $this->db->esc_like( $p ) . '%';
            $found = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $like ) );
            if ( $found ) {
                $this->tipo_table = $found;
                return $this->tipo_table;
            }
        }

        // Fallback: prefijo + nombre esperado
        $this->tipo_table = $this->db->prefix . 'rrhh_tipos_documento';
        return $this->tipo_table;
    }

    /**
     * Buscar documentos por RUT
     * @param string $rut_empleado
     * @param string|null $tipo_documento Filtrar por tipo específico (ej: 'contrato', 'liquidaciones', 'licencia')
     */
    public function get_by_rut( $rut_empleado, $tipo_documento = null ) {
        global $wpdb;
        $rut = sanitize_text_field( $rut_empleado );

        // Nombre de la tabla de tipos (resuelto automáticamente)
        $tipo_table = $this->resolve_tipo_table();

        // Seleccionar el ID del tipo y el nombre (si existe)
        $sql = "SELECT 
                    d.{$this->col('id')} AS id,
                    d.{$this->col('rut')} AS rut,
                    d.{$this->col('tipo')} AS tipo_id,
                    COALESCE(t.nombre, '') AS tipo,
                    d.{$this->col('anio')} AS anio,
                    d.{$this->col('nombre')} AS nombre,
                    d.{$this->col('url')} AS url,
                    d.{$this->col('fecha')} AS fecha
                FROM {$this->table()} d
                LEFT JOIN {$tipo_table} t ON t.id = d.{$this->col('tipo')}
                WHERE d.{$this->col('rut')} = %s";

        $params = [ $rut ];

        // Soportar filtrado por id o por nombre si se especifica
        if ( ! empty( $tipo_documento ) ) {
            if ( is_numeric( $tipo_documento ) ) {
                $sql .= " AND {$this->col('tipo')} = %d";
                $params[] = (int) $tipo_documento;
            } else {
                $sql .= " AND LOWER(t.nombre) = %s";
                $params[] = strtolower( sanitize_text_field( $tipo_documento ) );
            }
        }

        $sql .= " ORDER BY d.{$this->col('fecha')} DESC";

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
        global $wpdb;

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

        // Determinar tipo_id: puede venir como ID numérico o como nombre de tipo
        $tipo_input = $data['tipo'] ?? '';
        $tipo_id = 0;
        $tipo_table = $this->resolve_tipo_table();

        if ( is_numeric( $tipo_input ) ) {
            $tipo_id = (int) $tipo_input;
            $exists = $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$tipo_table} WHERE id = %d", $tipo_id ) );
            if ( ! $exists ) {
                error_log( "HRM_DB_Documentos::create() - tipo_id no encontrado: {$tipo_id}" );
                return false;
            }
        } else {
            $tipo_name = sanitize_text_field( $tipo_input );
            if ( empty( $tipo_name ) ) {
                error_log( 'HRM_DB_Documentos::create() - tipo vacío' );
                return false;
            }

            // Buscar por nombre (case insensitive)
            $found = $this->db->get_var( $this->db->prepare( "SELECT id FROM {$tipo_table} WHERE LOWER(nombre) = LOWER(%s) LIMIT 1", $tipo_name ) );
            if ( $found ) {
                $tipo_id = (int) $found;
            } else {
                // Insertar nuevo tipo si no existe
                $ins = $this->db->insert( $tipo_table, [ 'nombre' => $tipo_name ], [ '%s' ] );
                if ( $ins ) {
                    $tipo_id = (int) $this->db->insert_id;
                } else {
                    error_log( "HRM_DB_Documentos::create() - No se pudo crear tipo: {$tipo_name} - " . $this->db->last_error );
                    return false;
                }
            }
        }

        $insert_data = [
            $this->col('rut')    => $rut,
            $this->col('tipo')   => $tipo_id,
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

    /**
     * Obtener todos los tipos de documento registrados
     * @return array Associative array id => nombre
     */
    public function get_all_types() {
        $table = $this->resolve_tipo_table();
        $rows = $this->db->get_results( "SELECT id, nombre FROM {$table} WHERE 1 ORDER BY nombre ASC", ARRAY_A );
        if ( empty( $rows ) ) return [];
        $out = [];
        foreach ( $rows as $r ) {
            $out[ (int) $r['id'] ] = $r['nombre'];
        }
        return $out;
    }

    /**
     * Crear un nuevo tipo de documento si no existe y devolver su ID
     * @param string $nombre
     * @return int|false ID del tipo creado o existente, false en error
     */
    public function create_type( $nombre ) {
        $nombre = sanitize_text_field( trim( $nombre ) );
        if ( empty( $nombre ) ) return false;

        $table = $this->resolve_tipo_table();

        // Buscar existente (case-insensitive)
        $found = $this->db->get_var( $this->db->prepare( "SELECT id FROM {$table} WHERE LOWER(nombre) = LOWER(%s) LIMIT 1", $nombre ) );
        if ( $found ) {
            return (int) $found;
        }

        $ins = $this->db->insert( $table, [ 'nombre' => $nombre ], [ '%s' ] );
        if ( ! $ins ) {
            error_log( 'HRM_DB_Documentos::create_type failed - ' . $this->db->last_error . ' - Nombre: ' . $nombre );
            return false;
        }

        return (int) $this->db->insert_id;
    }

    /**
     * Eliminar un tipo de documento por ID
     * Retorna true en caso de éxito, WP_Error si existen documentos asociados, false en error.
     * @param int $id
     * @return bool|WP_Error
     */
    public function delete_type( $id ) {
        $id = intval( $id );
        if ( ! $id ) return false;

        $table = $this->resolve_tipo_table();

        // Verificar que no existan documentos asociados a este tipo
        $count = $this->db->get_var( $this->db->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE {$this->col('tipo')} = %d", $id ) );
        if ( $count && intval( $count ) > 0 ) {
            return new WP_Error( 'has_documents', 'Existen documentos asociados a este tipo' );
        }

        $deleted = $this->db->delete( $table, [ 'id' => $id ], [ '%d' ] );
        if ( $deleted === false ) {
            error_log( 'HRM_DB_Documentos::delete_type failed - ' . $this->db->last_error . ' - ID: ' . $id );
            return false;
        }

        return (bool) $deleted;
    }
}
