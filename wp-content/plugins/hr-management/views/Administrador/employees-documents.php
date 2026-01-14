<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! $employee ) {
    echo '<p class="notice notice-warning">Selecciona un empleado para gestionar sus documentos.</p>';
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
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#hrm-upload-modal">
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

    <!-- Listado de Documentos -->
    <div id="hrm-documents-container" class="p-3 text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>
</div>

<!-- Modal para Subir Documentos -->
<div class="modal fade" id="hrm-upload-modal" tabindex="-1" aria-labelledby="hrm-upload-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hrm-upload-modal-label">
                    <span class="dashicons dashicons-upload"></span> Subir Nuevo Documento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body">
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
                               accept=".pdf,.doc,.docx"
                               class="form-control">
                        <small class="text-muted d-block mt-1">Puedes seleccionar varios archivos a la vez</small>
                    </div>

                    <div id="hrm-upload-message" class="mt-3"></div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="hrm-upload-form" class="btn btn-success">
                    <span class="dashicons dashicons-upload"></span> Subir Documentos
                </button>
            </div>
        </div>
    </div>
</div>

