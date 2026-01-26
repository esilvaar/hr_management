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

// Obtener contratos (tipo 'contrato')
$contracts = $db_docs->get_by_rut( $employee->rut, 'contrato' );

// Asegurar que la lista esté ordenada por fecha de carga (más reciente primero)
if ( ! empty( $contracts ) ) {
    usort( $contracts, function( $a, $b ) {
        $ta = isset( $a->fecha_carga ) ? strtotime( $a->fecha_carga ) : 0;
        $tb = isset( $b->fecha_carga ) ? strtotime( $b->fecha_carga ) : 0;
        return $tb <=> $ta;
    } );
    $latest_contract = $contracts[0];
} else {
    $latest_contract = null;
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">

            <div class="hrm-panel mb-3">
                <div class="hrm-panel-header d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <h5 class="mb-0">
                            <span class="dashicons dashicons-media-document"></span>
                            Mi Contrato
                        </h5>
                        <small class="text-muted d-block mt-2">
                            <?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?>
                            (RUT: <?= esc_html( $employee->rut ) ?>)
                        </small>
                    </div>

                    <div class="d-flex align-items-center">
                        <?php if ( $latest_contract ) : ?>
                            <a href="<?= esc_url( $latest_contract->url ) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener noreferrer" download>
                                <span class="dashicons dashicons-download"></span> Descargar contrato
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hrm-panel-body">

                    <!-- PREVISUALIZACIÓN (mostrada inmediatamente si existe contrato) -->
                    <?php if ( $latest_contract ) : ?>
                        <?php if ( empty( $GLOBALS['hrm_doc_preview_rendered'] ) ) : $GLOBALS['hrm_doc_preview_rendered'] = true; ?>
                        <div class="mb-4" id="hrm-preview-panel" style="display:block;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Previsualización del contrato <?= '(' . esc_html( $latest_contract->anio ) . ')' ?></h6>
                            </div>

                            <iframe id="hrm-preview-iframe" src="<?= esc_url( $latest_contract->url ) ?>" style="width:100%;min-height:600px;border:1px solid #ccc;background:#fff;"></iframe>
                        </div>
                        <?php else: error_log('[HRM-DEBUG] Skipping duplicated contract preview render for employee id=' . intval( $employee->id ) ); endif; ?>
                    <?php else : ?>
                        <div class="mb-4">
                            <div class="alert alert-info mb-0">Aún no tienes un contrato.</div>
                        </div>
                    <?php endif; ?>

                    

                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function(){
    document.addEventListener('DOMContentLoaded', function () {
        const previewPanel  = document.getElementById('hrm-preview-panel');
        const previewIframe = document.getElementById('hrm-preview-iframe');
        const closeBtn      = document.getElementById('btn-cerrar-preview');

        // Delegación: manejar clicks en cualquier botón de previsualizar (si existe el contenedor)
        const misContainer = document.getElementById('hrm-mis-documents-container');
        if ( misContainer ) {
            misContainer.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-preview-doc');
                if (!btn) return;
                const url = btn.dataset.url;
                if (!url) return;

                // Asegurarse que el documento sea de tipo 'contrato' (si se provee la info)
                let type = '';
                const row = btn.closest('tr');
                if ( row ) type = row.getAttribute('data-type') || '';
                if ( ! type ) type = btn.dataset.type || '';
                if ( type && type.toLowerCase() !== 'contrato' ) return; // ignorar si no es contrato

                previewIframe.src = url;
                previewPanel.style.display = 'block';
                setTimeout(() => previewPanel.scrollIntoView({ behavior: 'smooth' }), 50);
            });
        }

        if ( closeBtn ) {
            closeBtn.addEventListener('click', function () {
                previewPanel.style.display = 'none';
                previewIframe.src = '';
            });
        }
    });
})();
</script>
