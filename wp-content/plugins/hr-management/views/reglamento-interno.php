<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Ruta absoluta del PDF en uploads
$pdf_url = content_url( 'uploads/Documentos/Reglamento.pdf' );
?>

<div class="hrm-page-header">
    <h1 class="hrm-page-title">
        <span class="dashicons dashicons-book-alt"></span>
        Reglamento Interno de la Empresa
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
