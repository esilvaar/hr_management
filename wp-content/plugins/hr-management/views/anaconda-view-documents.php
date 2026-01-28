<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$upload_dir = wp_upload_dir();
$empresa_dir = trailingslashit( $upload_dir['basedir'] ) . 'hrm_docs/empresa';
$empresa_url = trailingslashit( $upload_dir['baseurl'] ) . 'hrm_docs/empresa';

// Determine selected document (doc_id) or fall back to latest, otherwise default static PDF
$doc_id = isset( $_GET['doc_id'] ) ? intval( $_GET['doc_id'] ) : 0;
$pdf_url = content_url( 'uploads/Documentos/Reglamento.pdf' ); // default
$page_title = 'Reglamento Interno de la Empresa'; // default title

$table = $wpdb->prefix . 'rrhh_documentos_empresa';
if ( $doc_id ) {
    $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $doc_id ) );
    if ( $doc ) {
        // prefer title from DB even if file not found
        if ( ! empty( $doc->titulo ) ) {
            $page_title = $doc->titulo;
        }
        if ( ! empty( $doc->ruta ) ) {
            $filename = basename( $doc->ruta );
            $file_path = wp_normalize_path( $empresa_dir . '/' . $filename );
            if ( file_exists( $file_path ) ) {
                $pdf_url = $empresa_url . '/' . rawurlencode( $filename );
            }
        }
    }
} else {
    $latest = $wpdb->get_row( "SELECT * FROM {$table} ORDER BY fecha_creacion DESC LIMIT 1" );
    if ( $latest ) {
        if ( ! empty( $latest->titulo ) ) {
            $page_title = $latest->titulo;
        }
        if ( ! empty( $latest->ruta ) ) {
            $filename = basename( $latest->ruta );
            $file_path = wp_normalize_path( $empresa_dir . '/' . $filename );
            if ( file_exists( $file_path ) ) {
                $pdf_url = $empresa_url . '/' . rawurlencode( $filename );
            }
        }
    }
} 
?>

<div class="hrm-page-header">
    <h1 class="hrm-page-title">
        <span class="dashicons dashicons-book-alt"></span>
        <?= esc_html( $page_title ); ?>
    </h1>
</div>

<div class="hrm-card">
    <div class="hrm-card-body">
        <div class="mb-4">
            <iframe src="https://docs.google.com/gview?url=<?= esc_url($pdf_url) ?>&embedded=true" style="width:100%; min-height:600px; border:1px solid #ccc; border-radius:8px; background:#fff;"></iframe>
        </div>
        <a href="<?= esc_url($pdf_url) ?>" class="btn btn-primary" download target="_blank">
            <span class="dashicons dashicons-download"></span> Descargar Reglamento en PDF
        </a>
    </div>
</div>
