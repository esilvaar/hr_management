<?php
/**
 * Clase de BD para Ausencias/Vacaciones
 * Gestiona la tabla existente: wp_ahr_vacaciones de Plugin A
 * Mapea campos automáticamente entre formatos
 */

namespace Anaconda\HRSuite\Core\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ausencias {

    protected $wpdb;
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'ahr_vacaciones'; // Tabla de Plugin A
    }

    /**
     * Crear solicitud de ausencia/vacación
     * Mapea al formato de Plugin A
     */
    public function create( $data ) {
        if ( empty( $data['user_id'] ) || empty( $data['fecha_inicio'] ) || empty( $data['fecha_fin'] ) ) {
            return false;
        }

        // Validar que el empleado exista
        $empleados = new Empleados();
        $employee = $empleados->get_by_user_id( $data['user_id'] );
        if ( ! $employee ) {
            return false;
        }

        // Validar fechas
        $fecha_inicio = strtotime( $data['fecha_inicio'] );
        $fecha_fin = strtotime( $data['fecha_fin'] );

        if ( ! $fecha_inicio || ! $fecha_fin || $fecha_inicio > $fecha_fin ) {
            return false;
        }

        // Verificar solapamientos
        if ( $this->check_overlapping_requests( $data['user_id'], $data['fecha_inicio'], $data['fecha_fin'] ) ) {
            return false;
        }

        // Mapear a formato Plugin A
        $insert_data = [
            'user_id'       => absint( $data['user_id'] ),
            'tipo'          => sanitize_text_field( $data['tipo'] ?? 'Vacaciones' ),
            'fecha_inicio'  => sanitize_text_field( $data['fecha_inicio'] ),
            'fecha_fin'     => sanitize_text_field( $data['fecha_fin'] ),
            'motivo'        => sanitize_textarea_field( $data['motivo'] ?? '' ),
            'estado'        => sanitize_text_field( $data['estado'] ?? 'PENDIENTE' ),
        ];

        $result = $this->wpdb->insert( $this->table, $insert_data );
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Obtener solicitud por ID
     */
    public function get( $id ) {
        $id = absint( $id );
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id )
        );
        return $row ? $this->map_vacation( $row ) : null;
    }

    /**
     * Obtener solicitudes de un empleado
     */
    public function get_by_employee( $user_id ) {
        $user_id = absint( $user_id );
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY fecha_inicio DESC",
                $user_id
            )
        );

        return array_map( [ $this, 'map_vacation' ], $results );
    }

    /**
     * Obtener todas las solicitudes
     */
    public function get_all( $args = [] ) {
        $defaults = [
            'estado'  => null,
            'limit'   => -1,
            'offset'  => 0,
            'orderby' => 'fecha_inicio',
            'order'   => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = [];

        if ( null !== $args['estado'] ) {
            $estado = sanitize_text_field( $args['estado'] );
            $where[] = $this->wpdb->prepare( "estado = %s", $estado );
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $orderby = in_array( $args['orderby'], [ 'fecha_inicio', 'fecha_fin', 'id', 'user_id' ] ) ? $args['orderby'] : 'fecha_inicio';
        $order   = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $args['order'] ) : 'DESC';

        $limit_clause = '';
        if ( $args['limit'] > 0 ) {
            $limit = absint( $args['limit'] );
            $offset = absint( $args['offset'] );
            $limit_clause = " LIMIT {$limit} OFFSET {$offset}";
        }

        $query = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order}{$limit_clause}";

        $results = $this->wpdb->get_results( $query );
        return array_map( [ $this, 'map_vacation' ], $results );
    }

    /**
     * Obtener solicitudes pendientes
     */
    public function get_pending() {
        $results = $this->wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE estado = 'PENDIENTE' ORDER BY fecha_inicio ASC"
        );

        return array_map( [ $this, 'map_vacation' ], $results );
    }

    /**
     * Aprobar solicitud
     */
    public function approve( $id, $user_id ) {
        $id = absint( $id );
        $user_id = absint( $user_id );

        $update_data = [
            'estado'              => 'APROBADA',
            'aprobado_por_id'     => $user_id,
            'fecha_resolucion'    => current_time( 'mysql' ),
        ];

        $result = $this->wpdb->update( $this->table, $update_data, [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Rechazar solicitud
     */
    public function reject( $id, $user_id, $reason = '' ) {
        $id = absint( $id );
        $user_id = absint( $user_id );

        $update_data = [
            'estado'              => 'RECHAZADA',
            'aprobado_por_id'     => $user_id,
            'fecha_resolucion'    => current_time( 'mysql' ),
        ];

        // Nota: Plugin A puede no tener campo para la razón del rechazo
        // Nos adaptamos a sus campos existentes

        $result = $this->wpdb->update( $this->table, $update_data, [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Actualizar solicitud
     */
    public function update( $id, $data ) {
        $id = absint( $id );

        $update_data = [];

        if ( isset( $data['tipo'] ) ) {
            $update_data['tipo'] = sanitize_text_field( $data['tipo'] );
        }
        if ( isset( $data['fecha_inicio'] ) ) {
            $update_data['fecha_inicio'] = sanitize_text_field( $data['fecha_inicio'] );
        }
        if ( isset( $data['fecha_fin'] ) ) {
            $update_data['fecha_fin'] = sanitize_text_field( $data['fecha_fin'] );
        }
        if ( isset( $data['motivo'] ) ) {
            $update_data['motivo'] = sanitize_textarea_field( $data['motivo'] );
        }
        if ( isset( $data['estado'] ) ) {
            $update_data['estado'] = sanitize_text_field( $data['estado'] );
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        return $this->wpdb->update( $this->table, $update_data, [ 'id' => $id ] ) !== false;
    }

    /**
     * Eliminar solicitud
     */
    public function delete( $id ) {
        $id = absint( $id );
        return $this->wpdb->delete( $this->table, [ 'id' => $id ] ) !== false;
    }

    /**
     * Contar por estado
     */
    public function count_by_status( $status ) {
        $status = sanitize_text_field( $status );

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE estado = %s",
                $status
            )
        );

        return absint( $result );
    }

    /**
     * Verificar solapamientos de fechas
     */
    private function check_overlapping_requests( $user_id, $fecha_inicio, $fecha_fin ) {
        $user_id = absint( $user_id );
        $fecha_inicio = sanitize_text_field( $fecha_inicio );
        $fecha_fin = sanitize_text_field( $fecha_fin );

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                WHERE user_id = %d
                AND estado IN ('PENDIENTE', 'APROBADA')
                AND (
                    (fecha_inicio <= %s AND fecha_fin >= %s)
                    OR (fecha_inicio >= %s AND fecha_inicio <= %s)
                    OR (fecha_fin >= %s AND fecha_fin <= %s)
                )",
                $user_id, $fecha_fin, $fecha_inicio,
                $fecha_inicio, $fecha_fin,
                $fecha_inicio, $fecha_fin
            )
        );

        return absint( $result ) > 0;
    }

    /**
     * Mapear datos de Plugin A al formato de nuestra API
     */
    private function map_vacation( $vacation ) {
        // Calcular días
        $fecha_inicio = strtotime( $vacation->fecha_inicio );
        $fecha_fin = strtotime( $vacation->fecha_fin );
        $days = 0;

        if ( $fecha_inicio && $fecha_fin ) {
            $days = floor( ( $fecha_fin - $fecha_inicio ) / 86400 ) + 1;
        }

        return [
            'id_solicitud'       => $vacation->id,
            'user_id'            => $vacation->user_id,
            'tipo'               => $vacation->tipo,
            'fecha_inicio'       => $vacation->fecha_inicio,
            'fecha_fin'          => $vacation->fecha_fin,
            'motivo'             => $vacation->motivo,
            'estado'             => $vacation->estado,
            'aprobado_por_id'    => $vacation->aprobado_por_id,
            'fecha_resolucion'   => $vacation->fecha_resolucion,
            'created_at'         => $vacation->created_at,
            'days'               => $days,
            // Originales
            'id'                 => $vacation->id,
        ];
    }
}

class Ausencias {

    protected $wpdb;
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
    }

    /**
     * Obtener nombre de la tabla
     */
    public function get_table_name() {
        return $this->table;
    }

    /**
     * Crear una solicitud de ausencia
     */
    public function create( $data ) {
        $insert_data = [
            'id_empleado'      => absint( $data['id_empleado'] ?? 0 ),
            'id_tipo_ausencia' => ! empty( $data['id_tipo_ausencia'] ) ? absint( $data['id_tipo_ausencia'] ) : null,
            'fecha_inicio'     => sanitize_text_field( $data['fecha_inicio'] ?? '' ),
            'fecha_fin'        => sanitize_text_field( $data['fecha_fin'] ?? '' ),
            'motivo'           => sanitize_textarea_field( $data['motivo'] ?? '' ),
            'estado'           => sanitize_text_field( $data['estado'] ?? 'pendiente' ),
            'aprobado_por'     => ! empty( $data['aprobado_por'] ) ? absint( $data['aprobado_por'] ) : null,
            'fecha_resolucion' => ! empty( $data['fecha_resolucion'] ) ? sanitize_text_field( $data['fecha_resolucion'] ) : null,
            'created_at'       => current_time( 'mysql' ),
        ];

        $formats = [
            '%d', '%d', '%s', '%s', '%s',
            '%s', '%d', '%s', '%s'
        ];

        return $this->wpdb->insert( $this->table, $insert_data, $formats );
    }

    /**
     * Obtener una solicitud por ID
     */
    public function get( $id ) {
        $id = absint( $id );
        return $this->wpdb->get_row(
            $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id_solicitud = %d", $id )
        );
    }

    /**
     * Obtener solicitudes de un empleado
     */
    public function get_by_employee( $id_empleado, $args = [] ) {
        $id_empleado = absint( $id_empleado );
        $defaults    = [
            'estado'  => null,
            'limit'   => -1,
            'offset'  => 0,
            'orderby' => 'fecha_inicio',
            'order'   => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = "WHERE id_empleado = {$id_empleado}";

        if ( null !== $args['estado'] ) {
            $estado = sanitize_text_field( $args['estado'] );
            $where .= $this->wpdb->prepare( " AND estado = %s", $estado );
        }

        $orderby = sanitize_sql_orderby( $args['orderby'] ) ?: 'fecha_inicio';
        $order   = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ? $args['order'] : 'DESC';

        $limit_clause = '';
        if ( $args['limit'] > 0 ) {
            $limit_clause = $this->wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        }

        $query = "SELECT * FROM {$this->table} {$where} ORDER BY {$orderby} {$order}{$limit_clause}";

        return $this->wpdb->get_results( $query );
    }

    /**
     * Obtener todas las solicitudes (con filtros)
     */
    public function get_all( $args = [] ) {
        $defaults = [
            'estado'     => null,
            'id_empleado' => null,
            'limit'      => -1,
            'offset'     => 0,
            'orderby'    => 'fecha_inicio',
            'order'      => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = "WHERE 1=1";

        if ( null !== $args['estado'] ) {
            $estado = sanitize_text_field( $args['estado'] );
            $where .= $this->wpdb->prepare( " AND estado = %s", $estado );
        }

        if ( null !== $args['id_empleado'] ) {
            $id_empleado = absint( $args['id_empleado'] );
            $where      .= " AND id_empleado = {$id_empleado}";
        }

        $orderby = sanitize_sql_orderby( $args['orderby'] ) ?: 'fecha_inicio';
        $order   = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ? $args['order'] : 'DESC';

        $limit_clause = '';
        if ( $args['limit'] > 0 ) {
            $limit_clause = $this->wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
        }

        $query = "SELECT * FROM {$this->table} {$where} ORDER BY {$orderby} {$order}{$limit_clause}";

        return $this->wpdb->get_results( $query );
    }

    /**
     * Actualizar una solicitud
     */
    public function update( $id, $data ) {
        $id = absint( $id );

        $update_data = [];
        $formats     = [];

        $allowed_fields = [
            'id_tipo_ausencia',
            'fecha_inicio',
            'fecha_fin',
            'motivo',
            'estado',
            'aprobado_por',
            'fecha_resolucion',
            'observaciones_rechazo',
        ];

        foreach ( $allowed_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                switch ( $field ) {
                    case 'id_tipo_ausencia':
                    case 'aprobado_por':
                        $update_data[ $field ] = ! empty( $data[ $field ] ) ? absint( $data[ $field ] ) : null;
                        $formats[]             = $update_data[ $field ] !== null ? '%d' : '%s';
                        break;
                    case 'motivo':
                    case 'observaciones_rechazo':
                        $update_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
                        $formats[]             = '%s';
                        break;
                    default:
                        $update_data[ $field ] = sanitize_text_field( $data[ $field ] );
                        $formats[]             = '%s';
                }
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        $update_data['updated_at'] = current_time( 'mysql' );
        $formats[]                  = '%s';

        return $this->wpdb->update(
            $this->table,
            $update_data,
            [ 'id_solicitud' => $id ],
            $formats,
            [ '%d' ]
        );
    }

    /**
     * Aprobar una solicitud
     */
    public function approve( $id, $approved_by_user_id ) {
        return $this->update( $id, [
            'estado'           => 'aprobada',
            'aprobado_por'     => $approved_by_user_id,
            'fecha_resolucion' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Rechazar una solicitud
     */
    public function reject( $id, $approved_by_user_id, $reason = '' ) {
        return $this->update( $id, [
            'estado'                  => 'rechazada',
            'aprobado_por'            => $approved_by_user_id,
            'fecha_resolucion'        => current_time( 'mysql' ),
            'observaciones_rechazo'   => $reason,
        ] );
    }

    /**
     * Contar solicitudes por estado
     */
    public function count_by_status( $status = 'pendiente' ) {
        $status = sanitize_text_field( $status );
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE estado = %s",
                $status
            )
        );
        return absint( $result );
    }

    /**
     * Obtener solicitudes pendientes de aprobación
     */
    public function get_pending() {
        return $this->get_all( [
            'estado'  => 'pendiente',
            'orderby' => 'created_at',
            'order'   => 'ASC',
        ] );
    }
}
