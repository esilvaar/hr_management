<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HRM_DB_Gerencia_Deptos extends HRM_DB_Table {

    protected function base_table_name() {
        return 'rrhh_gerencia_deptos';
    }

    /**
     * Definición de columnas y sus alias
     */
    protected function expected_columns() {
        return [
            'id'               => [ 'id_gerencia', 'id' ],
            'area_gerencial'   => [ 'area_gerencial' ],
            'depto_a_cargo'    => [ 'depto_a_cargo' ],
            'nombre_gerente'   => [ 'nombre_gerente' ],
            'correo_gerente'   => [ 'correo_gerente' ],
            'estado'           => [ 'estado' ],
        ];
    }

    /**
     * Obtener todos los departamentos a cargo de una área gerencial
     */
    public function get_deptos_by_area( $area_gerencial ) {
        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT {$this->col('depto_a_cargo')} FROM {$this->table()} 
                 WHERE {$this->col('area_gerencial')} = %s AND {$this->col('estado')} = 1",
                sanitize_text_field( $area_gerencial )
            )
        );

        if ( empty( $results ) ) {
            return [];
        }

        return array_map( function( $item ) {
            return $item->depto_a_cargo;
        }, $results );
    }

    /**
     * Guardar relaciones gerencia-departamentos
     * Elimina las anteriores y crea las nuevas
     */
    public function save_area_deptos( $area_gerencial, $departamentos = [], $nombre_gerente = '', $correo_gerente = '' ) {
        // Primero, eliminar registros anteriores de esta área
        $this->db->delete(
            $this->table(),
            [ $this->col('area_gerencial') => $area_gerencial ]
        );

        if ( empty( $departamentos ) ) {
            return true;
        }

        // Sanitizar datos del gerente
        $nombre_gerente = sanitize_text_field( $nombre_gerente );
        $correo_gerente = sanitize_email( $correo_gerente );

        // Insertar nuevos registros
        foreach ( $departamentos as $depto ) {
            $depto = sanitize_text_field( $depto );
            if ( ! empty( $depto ) ) {
                $this->db->insert(
                    $this->table(),
                    [
                        $this->col('area_gerencial') => $area_gerencial,
                        $this->col('depto_a_cargo')  => $depto,
                        $this->col('nombre_gerente') => $nombre_gerente,
                        $this->col('correo_gerente') => $correo_gerente,
                        $this->col('estado')         => 1,
                    ]
                );
            }
        }

        return true;
    }

    /**
     * Obtener todas las áreas gerenciales disponibles
     */
    public function get_all_areas() {
        $results = $this->db->get_col(
            "SELECT DISTINCT {$this->col('area_gerencial')} FROM {$this->table()} WHERE {$this->col('estado')} = 1"
        );
        return ! empty( $results ) ? $results : [];
    }
}
?>
