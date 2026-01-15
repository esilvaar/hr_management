<?php
/**
 * Script para crear la página de confirmación manualmente
 * Ejecutar una sola vez
 */

// Insertar página de confirmación
$page_slug = 'confirmacion-solicitud-vacaciones';
$existing_page = get_page_by_path( $page_slug );

if ( ! $existing_page ) {
    $page_id = wp_insert_post( array(
        'post_title'    => 'Confirmación de Solicitud de Vacaciones',
        'post_name'     => $page_slug,
        'post_content'  => '[hrm_confirmation_solicitud_vacaciones]',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_author'   => get_current_user_id(),
    ) );
    
    echo 'Página creada con ID: ' . $page_id;
} else {
    echo 'La página ya existe con ID: ' . $existing_page->ID;
}
