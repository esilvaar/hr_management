<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function hrm_render_formulario_vacaciones() {
    ob_start();
    include HRM_PLUGIN_DIR . 'views/vacaciones-form.php';
    return ob_get_clean();
}

add_shortcode( 'hrm_solicitud_vacaciones', 'hrm_render_formulario_vacaciones' );
