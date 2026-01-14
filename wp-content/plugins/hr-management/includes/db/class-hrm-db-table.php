<?php
if ( ! defined( 'ABSPATH' ) ) exit;

abstract class HRM_DB_Table {

    protected $db;
    protected $table;
    protected $columns = [];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->resolve_table_name();
        $this->detect_columns();
    }

    abstract protected function base_table_name();
    abstract protected function expected_columns();

    protected function resolve_table_name() {
        $base = $this->base_table_name();

        foreach ( [
            $this->db->prefix . $base,
            $this->db->base_prefix . $base,
            $base,
        ] as $table ) {

            if ( $this->db->get_var(
                $this->db->prepare( 'SHOW TABLES LIKE %s', $table )
            ) === $table ) {
                return $table;
            }
        }

        return $this->db->prefix . $base;
    }

    protected function detect_columns() {
        $columns = $this->db->get_results( "DESCRIBE {$this->table}" );
        if ( ! $columns ) return;

        $real = array_column( $columns, 'Field' );

        foreach ( $this->expected_columns() as $alias => $variants ) {
            foreach ( $variants as $v ) {
                if ( in_array( $v, $real, true ) ) {
                    $this->columns[ $alias ] = $v;
                    break;
                }
            }
            $this->columns[ $alias ] ??= $alias;
        }
    }

    public function col( $alias ) {
        return $this->columns[ $alias ] ?? $alias;
    }

    public function table() {
        return $this->table;
    }
}
