<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Forzar carga del JS de subida de documentos siempre
wp_enqueue_script(
    'hrm-documents-upload',
    HRM_PLUGIN_URL . 'assets/js/documents-upload.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// Forzar carga del JS de lista de documentos
wp_enqueue_script(
    'hrm-documents-list-init',
    HRM_PLUGIN_URL . 'assets/js/documents-list-init.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// Forzar carga del JS de filtros de documentos
wp_enqueue_script(
    'hrm-documents-list',
    HRM_PLUGIN_URL . 'assets/js/documents-list.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

if ( ! $employee ) {
    echo '<div class="d-flex align-items-center justify-content-center" style="min-height: 400px;">';
    echo '<h2 style="font-size: 24px; color: #856404; text-align: center; max-width: 500px;"><strong>⚠️ Atención:</strong> Por favor selecciona un usuario para gestionar sus documentos.</h2>';
    echo '</div>';
    return;
}

// Pasar variables al JavaScript mediante wp_localize_script
wp_localize_script( 'hrm-documents-list-init', 'hrmDocsListData', array(
    'employeeId' => intval( $employee->id ),
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'hrm_get_documents' )
) );
?>

<!-- Sección de Documentos -->
<div class="hrm-documents-section">
    <div class="d-flex align-items-center justify-content-between mb-3 p-3">
        <h5 class="mb-0 text-black">Documentos del Empleado</h5>
        <button class="btn btn-success btn-sm" id="btn-nuevo-documento">
            <span class="dashicons dashicons-plus-alt"></span> Nuevo Documento
        </button>
    </div>

    <!-- Filtros por Categoría -->
    <div class="mb-3 p-3 border-bottom">
        <h6 class="fw-bold mb-2">Filtrar por Categoría</h6>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm hrm-doc-category-btn" data-category="contrato">
                <span class="dashicons dashicons-media-document"></span> Contrato
            </button>
            <button class="btn btn-outline-secondary btn-sm hrm-doc-category-btn" data-category="liquidaciones">
                <span class="dashicons dashicons-media-document"></span> Liquidaciones
            </button>
            <button class="btn btn-outline-secondary btn-sm hrm-doc-category-btn" data-category="licencia">
                <span class="dashicons dashicons-media-document"></span> Licencias
            </button>
            <button class="btn btn-outline-secondary btn-sm hrm-doc-category-btn" data-category="">
                <span class="dashicons dashicons-trash"></span> Limpiar
            </button>
        </div>
    </div>


    <!-- Filtro de Año -->
    <div class="mb-3 p-3 border-bottom">
        <h6 class="fw-bold mb-2">Filtrar por Año</h6>
        <div style="position: relative; display: inline-block; width: 100%; max-width: 250px;">
            <input 
                type="text" 
                class="form-control" 
                id="hrm-doc-year-filter-search" 
                placeholder="Buscar año..."
                autocomplete="off">
            <div id="hrm-doc-year-filter-items" style="position: absolute; top: 100%; left: 0; min-width: 250px; max-width: 400px; background: white; border: 1px solid #dee2e6; border-top: none; max-height: 300px; overflow-y: auto; z-index: 1000; display: none;"></div>
        </div>
    </div> 

    <!-- Panel de subida de documentos -->
    <div id="hrm-upload-panel" class="border rounded shadow p-4 mb-4 bg-white" style="max-width: 600px; margin: 0 auto; display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); z-index: 9999;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><span class="dashicons dashicons-upload"></span> Subir Nuevo Documento</h5>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-cerrar-upload">Cerrar</button>
        </div>
        <form method="post" enctype="multipart/form-data" id="hrm-upload-form">
            <?php wp_nonce_field( 'hrm_upload_file', 'hrm_upload_nonce' ); ?>
            <input type="hidden" name="employee_id" value="<?= esc_attr( $employee->id ) ?>">
            <input type="hidden" name="hrm_action" value="upload_document">
            <div class="mb-3">
                <label class="form-label fw-bold" for="hrm-tipo-search">Tipo de Documento *</label>
                <div style="position: relative;">
                    <input 
                        type="text" 
                        class="form-control" 
                        id="hrm-tipo-search" 
                        placeholder="Selecciona o escribe tipo..."
                        autocomplete="off">
                    <div id="hrm-tipo-items" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #dee2e6; border-top: none; max-height: 300px; overflow-y: auto; z-index: 1000; display: none;">
                        <?php foreach ( $hrm_tipos_documento as $tipo ) : ?>
                            <a class="dropdown-item py-2 px-3 hrm-tipo-item" href="#" data-tipo="<?= esc_attr( $tipo ) ?>">
                                <strong><?= esc_html( $tipo ) ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" id="hrm_tipo_documento" name="tipo_documento" required>
                <small class="text-muted d-block mt-1">Selecciona el tipo de documento a subir</small>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" for="hrm-anio-search">Año del Documento *</label>
                <div style="position: relative;">
                    <input 
                        type="text" 
                        class="form-control" 
                        id="hrm-anio-search" 
                        placeholder="Selecciona o escribe año..."
                        autocomplete="off">
                    <div id="hrm-anio-items" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #dee2e6; border-top: none; max-height: 300px; overflow-y: auto; z-index: 1000; display: none;">
                        <?php
                        $anio_actual = (int)date('Y');
                        for ($y = $anio_actual; $y >= 2000; $y--) {
                            echo '<a class="dropdown-item py-2 px-3 hrm-anio-item" href="#" data-anio="' . $y . '"><strong>' . $y . '</strong></a>';
                        }
                        ?>
                    </div>
                </div>
                <input type="hidden" id="hrm_anio_documento" name="anio_documento" required>
                <small class="text-muted d-block mt-1">Selecciona el año del documento</small>
            </div>
            <div class="mb-3">
                <label for="hrm_archivos_subidos" class="form-label fw-bold">Archivos (PDF, DOC, DOCX) *</label>
                <input id="hrm_archivos_subidos"
                       type="file"
                       name="archivos_subidos[]"
                       multiple
                       required
                       accept=".pdf"
                       class="form-control">
                <small class="text-muted d-block mt-1">Puedes seleccionar varios archivos a la vez</small>
            </div>
            <div id="hrm-upload-message" class="mt-3"></div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-secondary" id="btn-cancelar-upload">Cancelar</button>
                <button type="submit" class="btn btn-success">
                    <span class="dashicons dashicons-upload"></span> Subir Documentos
                </button>
            </div>
        </form>
    </div>

    <!-- Listado de Documentos -->
    <div id="hrm-documents-message"></div>
    <div id="hrm-documents-container" class="p-3 text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadPanel = document.getElementById('hrm-upload-panel');
    const btnNuevo = document.getElementById('btn-nuevo-documento');
    const btnCerrar = document.getElementById('btn-cerrar-upload');
    const btnCancelar = document.getElementById('btn-cancelar-upload');
    if (btnNuevo) {
        btnNuevo.onclick = function() {
            uploadPanel.style.display = 'block';
        };
    }
    if (btnCerrar) {
        btnCerrar.onclick = function() {
            uploadPanel.style.display = 'none';
        };
    }
    if (btnCancelar) {
        btnCancelar.onclick = function() {
            uploadPanel.style.display = 'none';
        };
    }
});
</script>

