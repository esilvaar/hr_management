<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function hrm_db_empleados() {
    static $instance = null;

    if ( $instance === null ) {
        $instance = new HRM_DB_Empleados();
    }

    return $instance;
}
