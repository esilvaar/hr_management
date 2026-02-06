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
<?php // Styles merged into plugin-common.css: assets/css/plugin-common.css - specific view file removed. ?>

<div class="mb-3 ps-4 pt-3">
    <?php
    if ( isset( $_GET['employee_id'] ) ) {
        $ref_id = absint( $_GET['employee_id'] );
        $back_page = isset( $_GET['source_page'] ) ? sanitize_text_field( $_GET['source_page'] ) : 'hrm-empleados';
        // Mostrar botón Volver si venimos desde un perfil específico (indicado por employee_id en URL)
        // Ya sea propio o ajeno, si se especificó el ID es porque se navegó en contexto de ese empleado
        ?>
        <a href="<?= esc_url( admin_url( 'admin.php?page=' . $back_page . '&tab=profile&id=' . $ref_id ) ) ?>" class="btn btn-secondary btn-sm">
            <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: text-bottom;"></span> Volver al Perfil
        </a>
        <?php
    }
    ?>
</div>

<div class="hrm-page-header">
    <h1 class="hrm-page-title">
        <span class="dashicons dashicons-book-alt"></span>
        <?= esc_html( $page_title ); ?>
    </h1>
</div>

<div class="hrm-card">
    <div class="hrm-card-body">
        <div class="mb-4">
            <iframe src="https://docs.google.com/gview?url=<?= esc_url($pdf_url) ?>&embedded=true" class="hrm-doc-iframe"></iframe>
        </div>
        <a href="<?= esc_url($pdf_url) ?>" class="btn btn-primary" download target="_blank">
            <span class="dashicons dashicons-download"></span> Descargar Reglamento en PDF
        </a>
    </div>
</div>
