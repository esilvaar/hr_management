<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// DB
$db_emp  = new HRM_DB_Empleados();
$db_docs = new HRM_DB_Documentos();

$current_user_id = get_current_user_id();

// Admin puede consultar otro empleado
$employee_id = isset( $_GET['employee_id'] ) ? absint( $_GET['employee_id'] ) : null;

if ( $employee_id ) {
    $employee = $db_emp->get( $employee_id );
    if ( ! $employee ) {
        echo '<div class="notice notice-error"><p>Empleado no encontrado.</p></div>';
        return;
    }
} else {
    $employee = $db_emp->get_by_user_id( $current_user_id );
    if ( ! $employee ) {
        echo '<div class="notice notice-warning"><p>No se encontró tu registro de empleado.</p></div>';
        return;
    }
}

// Obtener liquidaciones
$documents = $db_docs->get_by_rut( $employee->rut, 'liquidaciones' );

// JS data
wp_localize_script( 'hrm-mis-documentos', 'hrmMisDocsData', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
) );
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">

            <div class="hrm-panel mb-3">
                <div class="hrm-panel-header">
                    <h5 class="mb-0">
                        <span class="dashicons dashicons-media-document"></span>
                        Mis Liquidaciones
                    </h5>
                    <small class="text-muted d-block mt-2">
                        <?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?>
                        (RUT: <?= esc_html( $employee->rut ) ?>)
                    </small>
                </div>

                <div class="hrm-panel-body">

                    <div class="mb-3 d-flex align-items-center gap-2">
                        <label for="hrm-mis-year-select" class="me-2 mb-0 fw-bold">Filtrar por año:</label>
                        <select id="hrm-mis-year-select" class="form-select" style="max-width:160px;">
                            <option value="">Todos</option>
                            <?php $anio_actual = (int) date('Y'); for ($y = $anio_actual; $y >= 2000; $y--) : ?>
                                <option value="<?= esc_attr( $y ); ?>" <?= $y === $anio_actual ? 'selected' : ''; ?>><?= esc_html( $y ); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

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
                                                        $date = strtotime( $doc->fecha_carga ?? 'now' );
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
                                <p class="mt-2 mb-0">No hay liquidaciones disponibles.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PREVISUALIZACIÓN -->
                    <?php if ( empty( $GLOBALS['hrm_doc_preview_rendered'] ) ) : $GLOBALS['hrm_doc_preview_rendered'] = true; ?>
                    <div class="mt-4" id="hrm-preview-panel" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">Previsualización de documento</h6>
                            <button type="button" id="btn-cerrar-preview" class="btn btn-sm btn-outline-secondary">
                                Cerrar
                            </button>
                        </div>
                        <iframe id="hrm-preview-iframe"
                                style="width:100%;min-height:600px;border:1px solid #ccc;background:#fff;"></iframe>
                    </div>
                    <?php else: error_log('[HRM-DEBUG] Skipping duplicated liquidations preview render for employee id=' . intval( $employee->id ) ); endif; ?>

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

    // Filtrado por año (cliente) para la tabla de liquidaciones
    (function(){
        const yearSelect = document.getElementById('hrm-mis-year-select');
        const container = document.getElementById('hrm-mis-documents-container');
        if (!yearSelect || !container) return;

        function filterRowsByYear(){
            const val = yearSelect.value;
            const rows = container.querySelectorAll('table tbody tr[data-year]');
            let visible = 0;
            rows.forEach(r => {
                if (!val || r.dataset.year === val) { r.style.display = ''; visible++; } else { r.style.display = 'none'; }
            });

            // Mostrar mensaje si no hay resultados
            let noEl = container.querySelector('.hrm-no-results');
            if ( visible === 0 ) {
                if (!noEl) {
                    noEl = document.createElement('div');
                    noEl.className = 'alert alert-info hrm-no-results text-center';
                    noEl.innerHTML = '<p class="mb-0">No hay documentos para el año seleccionado.</p>';
                    container.appendChild(noEl);
                }
            } else if ( noEl ) {
                noEl.remove();
            }
        }

        yearSelect.addEventListener('change', filterRowsByYear);
        // Inicializar filtro (el select viene por defecto con el año actual seleccionado)
        filterRowsByYear();
    })();

});
</script>
