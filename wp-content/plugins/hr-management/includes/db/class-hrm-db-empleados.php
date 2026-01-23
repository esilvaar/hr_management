<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HRM_DB_Empleados extends HRM_DB_Table {

    protected function base_table_name() {
        return 'rrhh_empleados';
    }

    /**
     * Definición de columnas y sus alias
     */
    protected function expected_columns() {
        return [
            'id'               => [ 'id_empleado', 'id' ], // Tu PK
            'user_id'          => [ 'user_id' ],           // Enlace con WP
            'rut'              => [ 'rut' ],
            'nombre'           => [ 'nombre' ],
            'apellido'         => [ 'apellido' ],
            'email'            => [ 'correo' ],
            'telefono'         => [ 'telefono' ],
            'fecha_nacimiento' => [ 'fecha_nacimiento' ],
            'fecha_ingreso'    => [ 'fecha_ingreso' ],
            'departamento'     => [ 'departamento' ],
            'area_gerencia'    => [ 'area_gerencia' ],
            'puesto'           => [ 'puesto' ],
            'tipo_contrato'    => [ 'tipo_contrato' ],
            'salario'          => [ 'salario' ],
            'estado'           => [ 'estado' ],
            'anos_acreditados_anteriores' => [ 'anos_acreditados_anteriores' ],
            'anos_en_la_empresa'         => [ 'anos_en_la_empresa' ],
            'anos_totales_trabajados'    => [ 'anos_totales_trabajados' ],
        ];
    }

    /**
     * Prepara el SELECT con alias para usar $obj->email en vez de $obj->correo
     */
    private function get_select_columns() {
        $columns = [];
        $keys = [
            'id', 'user_id', 'rut', 'nombre', 'apellido', 'email', 'telefono', 
            'fecha_nacimiento', 'fecha_ingreso', 'departamento', 'area_gerencia',
            'puesto', 'tipo_contrato', 'salario', 'estado', 'anos_acreditados_anteriores',
            'anos_en_la_empresa', 'anos_totales_trabajados'
        ];
        
        foreach ($keys as $key) {
            $columns[] = "{$this->col($key)} AS {$key}";
        }
        return implode(', ', $columns);
    }

    public function get_all() {
        $select = $this->get_select_columns();
        return $this->db->get_results( "SELECT {$select} FROM {$this->table()}" );
    }

    /**
     * Obtener empleados filtrados por estado
     * @param int $estado 1 para activos, 0 para inactivos, null para todos
     * @return array
     */
    public function get_by_status( $estado = 1 ) {
        $select = $this->get_select_columns();
        
        if ( $estado === null ) {
            return $this->get_all();
        }
        
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT {$select} FROM {$this->table()} WHERE {$this->col('estado')} = %d",
                (int) $estado
            )
        );
    }

    public function get( $id ) {
        $select = $this->get_select_columns();
        return $this->db->get_row(
            $this->db->prepare( "SELECT {$select} FROM {$this->table()} WHERE {$this->col('id')} = %d", (int) $id )
        );
    }

    // Buscar empleado por ID de Usuario WP
    public function get_by_wp_user( $wp_user_id ) {
        $select = $this->get_select_columns();
        return $this->db->get_row( 
            $this->db->prepare( "SELECT {$select} FROM {$this->table()} WHERE {$this->col('user_id')} = %d", (int)$wp_user_id ) 
        );
    }

    // Alias más intuitivo
    public function get_by_user_id( $user_id ) {
        return $this->get_by_wp_user( $user_id );
    }

    /**
     * Obtener empleado por email (retorna objeto o null)
     */
    public function get_by_email( $email ) {
        $select = $this->get_select_columns();
        return $this->db->get_row( $this->db->prepare( "SELECT {$select} FROM {$this->table()} WHERE {$this->col('email')} = %s", $email ) );
    }



    /**
     * Obtener empleados visibles para un usuario dado.
     * Usuarios con rol 'administrador_anaconda' o 'supervisor' que además sean gerentes
     * solo verán empleados con la misma `area_gerencia`.
     *
     * @param int|null $user_id
     * @param int|null $estado 1 = activos, 0 = inactivos, null = todos
     * @return array|object
     */
    public function get_visible_for_user( $user_id = null, $estado = 1 ) {
        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        $wp_user = get_userdata( $user_id );
        if ( ! $wp_user ) {
            return $estado === null ? $this->get_all() : $this->get_by_status( $estado );
        }

        $roles = (array) ( $wp_user->roles ?? array() );
        $restricted_roles = array( 'administrador_anaconda', 'supervisor' );

        // Si el usuario no tiene rol restringido, devolver lista normal
        if ( empty( array_intersect( $roles, $restricted_roles ) ) ) {
            return $estado === null ? $this->get_all() : $this->get_by_status( $estado );
        }

        // Obtener empleado vinculado al usuario WP
        $empleado = $this->get_by_wp_user( $user_id );
        if ( ! $empleado ) {
            return $estado === null ? $this->get_all() : $this->get_by_status( $estado );
        }

        // Requerimos que el empleado sea 'Gerente' (puesto) o su departamento sea 'Gerencia'
        $es_gerente = false;
        if ( isset( $empleado->puesto ) && strcasecmp( trim( $empleado->puesto ), 'Gerente' ) === 0 ) {
            $es_gerente = true;
        }
        if ( isset( $empleado->departamento ) && strcasecmp( trim( $empleado->departamento ), 'Gerencia' ) === 0 ) {
            $es_gerente = true;
        }

        if ( ! $es_gerente ) {
            return $estado === null ? $this->get_all() : $this->get_by_status( $estado );
        }

        $area = trim( $empleado->area_gerencia ?? '' );
        if ( $area === '' ) {
            // Sin área definida: no aplicamos restricción para evitar bloquear al usuario
            return $estado === null ? $this->get_all() : $this->get_by_status( $estado );
        }

        // Intentar obtener departamentos predefinidos para esta área (mapa centralizado)
        $deptos_predefinidos = function_exists( 'hrm_get_deptos_predefinidos_por_area' ) ? hrm_get_deptos_predefinidos_por_area( $area ) : array();

        $select = $this->get_select_columns();

        // Si existen departamentos predefinidos, filtrar por departamento IN (...)
        if ( ! empty( $deptos_predefinidos ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $deptos_predefinidos ), '%s' ) );
            $sql = "SELECT {$select} FROM {$this->table()} WHERE {$this->col('departamento')} IN ($placeholders)";
            $params = $deptos_predefinidos;

            if ( $estado !== null ) {
                $sql .= " AND {$this->col('estado')} = %d";
                $params[] = (int) $estado;
            }

            // Preparar parámetros para $this->db->prepare
            return call_user_func_array( array( $this->db, 'get_results' ), array( call_user_func_array( array( $this->db, 'prepare' ), array_merge( array( $sql ), $params ) ) ) );
        }

        // Fallback: si no hay departamentos predefinidos, filtrar por área_gerencia como antes
        if ( $estado !== null ) {
            return $this->db->get_results( $this->db->prepare( "SELECT {$select} FROM {$this->table()} WHERE {$this->col('area_gerencia')} = %s AND {$this->col('estado')} = %d", $area, (int) $estado ) );
        }

        return $this->db->get_results( $this->db->prepare( "SELECT {$select} FROM {$this->table()} WHERE {$this->col('area_gerencia')} = %s", $area ) );
    }

    public function create( $data ) {
        $insert_data = [
            $this->col('rut')              => sanitize_text_field( $data['rut'] ),
            $this->col('nombre')           => sanitize_text_field( $data['nombre'] ),
            $this->col('apellido')         => sanitize_text_field( $data['apellido'] ),
            $this->col('fecha_ingreso')    => !empty($data['fecha_ingreso']) ? sanitize_text_field($data['fecha_ingreso']) : current_time('Y-m-d'),
            
            // Campos opcionales
            $this->col('user_id')          => !empty($data['user_id']) ? absint($data['user_id']) : null,
            $this->col('email')            => sanitize_email( $data['email'] ?? '' ),
            $this->col('telefono')         => sanitize_text_field( $data['telefono'] ?? '' ),
            $this->col('fecha_nacimiento') => !empty($data['fecha_nacimiento']) ? sanitize_text_field($data['fecha_nacimiento']) : null,
            $this->col('departamento')     => sanitize_text_field( $data['departamento'] ?? '' ),
            $this->col('area_gerencia')    => sanitize_text_field( $data['area_gerencia'] ?? '' ),
            $this->col('puesto')           => sanitize_text_field( $data['puesto'] ?? '' ),
            $this->col('tipo_contrato')    => sanitize_text_field( $data['tipo_contrato'] ?? '' ),
            $this->col('salario')          => !empty($data['salario']) ? floatval($data['salario']) : null,
            $this->col('anos_acreditados_anteriores') => !empty($data['anos_acreditados_anteriores']) ? intval($data['anos_acreditados_anteriores']) : 0,
            $this->col('anos_en_la_empresa')         => !empty($data['anos_en_la_empresa']) ? intval($data['anos_en_la_empresa']) : 0,
            $this->col('anos_totales_trabajados')    => !empty($data['anos_totales_trabajados']) ? intval($data['anos_totales_trabajados']) : 0,
            // Estado como booleano (1/0) por defecto 1
            $this->col('estado')           => isset($data['estado']) ? (int) boolval( $data['estado'] ) : 1,
        ];

        return $this->db->insert( $this->table(), $insert_data );
    }

    public function update( $id, $data ) {
        // Mapeo de campos permitidos que pueden ser actualizados
        $allowed_fields = [
            'rut' => 'sanitize_text_field',
            'nombre' => 'sanitize_text_field',
            'apellido' => 'sanitize_text_field',
            'email' => 'sanitize_email',
            'telefono' => 'sanitize_text_field',
            'fecha_nacimiento' => 'sanitize_text_field',
            'fecha_ingreso' => 'sanitize_text_field',
            'departamento' => 'sanitize_text_field',
            'area_gerencia' => 'sanitize_text_field',
            'puesto' => 'sanitize_text_field',
            'tipo_contrato' => 'sanitize_text_field',
            'salario' => 'floatval',
            'anos_acreditados_anteriores' => 'intval',
            'anos_en_la_empresa' => 'intval',
            'anos_totales_trabajados' => 'intval',
            'estado' => 'boolval',
        ];

        $update_data = [];

        // Solo incluir campos que se enviaron en el POST
        foreach ( $allowed_fields as $field => $sanitizer ) {
            if ( isset( $data[ $field ] ) ) {
                $value = $data[ $field ];
                
                // Aplicar sanitización basada en el tipo de campo
                if ( $sanitizer === 'floatval' ) {
                    $value = ! empty( $value ) ? floatval( $value ) : null;
                } elseif ( $sanitizer === 'intval' ) {
                    $value = ! empty( $value ) ? intval( $value ) : 0;
                } elseif ( $sanitizer === 'boolval' ) {
                    $value = (int) boolval( $value );
                } elseif ( $sanitizer === 'sanitize_email' ) {
                    $value = sanitize_email( $value );
                } else {
                    $value = call_user_func( $sanitizer, $value );
                }

                $update_data[ $this->col( $field ) ] = $value;
            }
        }

        // Si no hay datos para actualizar, retornar true (no es un error)
        if ( empty( $update_data ) ) {
            return true;
        }

        return $this->db->update( 
            $this->table(), 
            $update_data, 
            [ $this->col('id') => $id ]
        );
    }
}