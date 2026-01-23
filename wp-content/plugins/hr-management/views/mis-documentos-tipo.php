<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Vista genérica para mostrar documentos por tipo en "Mi Perfil"
// Espera `$_GET['page']` con formato: hrm-mi-documentos-type-<ID>

// Resolver tipo_id desde el slug si no viene por parámetro
$page_slug = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
// Allow predefining $type_id (useful for per-type templates that set it)
if ( ! isset( $type_id ) ) {
    $type_id = 0;
    if ( preg_match( '/hrm-mi-documentos-type-(\d+)/', $page_slug, $m ) ) {
        $type_id = intval( $m[1] );
    }
    if ( isset( $_GET['type_id'] ) ) {
        $type_id = intval( $_GET['type_id'] );
    }
}

if ( ! $type_id ) {
    echo '<div class="notice notice-warning"><p>Tipo de documento inválido.</p></div>';
    return;
}

hrm_ensure_db_classes();
$db_emp = new HRM_DB_Empleados();
$db_docs = new HRM_DB_Documentos();

$current_user_id = get_current_user_id();
$employee = $db_emp->get_by_user_id( $current_user_id );
if ( ! $employee ) {
    echo '<div class="notice notice-warning"><p>No se encontró tu registro de empleado.</p></div>';
    return;
}

$types = $db_docs->get_all_types();
$type_name = isset( $types[ $type_id ] ) ? $types[ $type_id ] : 'Tipo';

$documents = $db_docs->get_by_rut( $employee->rut, $type_id );
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">

            <div class="hrm-panel mb-3">
                <div class="hrm-panel-header">
                    <h5 class="mb-0">
                        <span class="dashicons dashicons-media-document"></span>
                        <?= esc_html( $type_name ) ?>
                    </h5>
                    <small class="text-muted d-block mt-2">
                        <?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?>
                        (RUT: <?= esc_html( $employee->rut ) ?>)
                    </small>
                </div>

                <div class="hrm-panel-body">

                    <div id="hrm-mis-documents-container">
                        <?php if ( ! empty( $documents ) ) : ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Año</th>
                                            <th>Archivo</th>
                                            <th>Fecha de Carga</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $documents as $doc ) : ?>
                                            <tr data-year="<?= esc_attr( $doc->anio ) ?>">
                                                <td><?= esc_html( $doc->anio ) ?></td>
                                                <td>
                                                    <span class="dashicons dashicons-media-document"></span>
                                                    <?= esc_html( $doc->nombre ) ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php
                                                        $date = strtotime( $doc->fecha ?? 'now' );
                                                        echo date_i18n( 'd/m/Y H:i', $date );
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="<?= esc_url( $doc->url ) ?>"
                                                       class="btn btn-sm btn-outline-primary"
                                                       target="_blank"
                                                       rel="noopener noreferrer">
                                                        <span class="dashicons dashicons-download"></span> Descargar
                                                    </a>

                                                    <button type="button"
                                                            class="btn btn-sm btn-secondary btn-preview-doc ms-2"
                                                            data-url="<?= esc_url( $doc->url ) ?>">
                                                        <span class="dashicons dashicons-visibility"></span> Previsualizar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else : ?>
                            <div class="alert alert-info text-center py-4">
                                <span class="dashicons dashicons-media-document" style="font-size:48px;opacity:.5;"></span>
                                <p class="mt-2 mb-0">No hay documentos disponibles.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const previewPanel  = document.getElementById('hrm-preview-panel');
    const previewIframe = document.getElementById('hrm-preview-iframe');
    const closeBtn      = document.getElementById('btn-cerrar-preview');

    document.querySelectorAll('.btn-preview-doc').forEach(btn => {
        btn.addEventListener('click', function () {
            const url = this.dataset.url;
            if (!url) return;

            previewIframe.src = url;
            previewPanel.style.display = 'block';

            setTimeout(() => {
                previewPanel.scrollIntoView({ behavior: 'smooth' });
            }, 50);
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            previewPanel.style.display = 'none';
            previewIframe.src = '';
        });
    }

});
</script>
