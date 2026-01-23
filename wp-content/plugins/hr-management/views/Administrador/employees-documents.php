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

// Forzar carga del JS de lista de documentos (core)
wp_enqueue_script(
    'hrm-documents-list',
    HRM_PLUGIN_URL . 'assets/js/documents-list.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// Init script depende del core para garantizar que las funciones estén disponibles
wp_enqueue_script(
    'hrm-documents-list-init',
    HRM_PLUGIN_URL . 'assets/js/documents-list-init.js',
    array('jquery', 'hrm-documents-list'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// No terminamos la ejecución aquí: permitimos mostrar filtros y botón incluso sin un empleado seleccionado.
$has_employee = ! empty( $employee );
$employee_id  = $has_employee ? intval( $employee->id ) : 0;

// Pasar variables al JavaScript mediante wp_localize_script
// Preparar lista de tipos para JS: normalizar a array de objetos { id, name }
$doc_types_js = array();
if ( ! empty( $hrm_tipos_documento ) ) {
    foreach ( $hrm_tipos_documento as $k => $v ) {
        if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
            $doc_types_js[] = array( 'id' => (int) $k, 'name' => (string) $v );
        } elseif ( is_array( $v ) && isset( $v['id'] ) ) {
            $doc_types_js[] = array( 'id' => (int) $v['id'], 'name' => (string) ( $v['nombre'] ?? $v['name'] ?? '' ) );
        } else {
            $doc_types_js[] = array( 'id' => '', 'name' => (string) $v );
        }
    }
}

wp_localize_script( 'hrm-documents-list-init', 'hrmDocsListData', array(
    'employeeId' => $employee_id,
    'hasEmployee' => $has_employee,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'hrm_get_documents' ),
    'createTypeNonce' => wp_create_nonce( 'hrm_create_type' ),
    'types' => $doc_types_js,
) );
?>

<!-- Sección de Documentos -->
<div class="hrm-documents-section">
    <div class="d-flex align-items-center justify-content-between mb-3 p-3">
        <h5 class="mb-0 text-black">Documentos del Empleado</h5>
    </div>

    <!-- Filtros (Tipo y Año) en la misma fila -->
    <div class="mb-3 p-3">
        <div class="row align-items-center">
            <div class="col-md">
                <h6 class="fw-bold mb-2">Filtros</h6>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <div style="position: relative; min-width: 220px; max-width: 320px;">
                        <input 
                            type="text" 
                            class="form-control" 
                            id="hrm-doc-type-filter-search" 
                            placeholder="Buscar tipo..."
                            autocomplete="off"
                            style="padding-right:36px;">
                        <div id="hrm-doc-type-filter-items" style="position: absolute; top: 100%; left: 0; min-width: 250px; max-width: 400px; background: white; border: 1px solid #dee2e6; border-top: none; max-height: 300px; overflow-y: auto; z-index: 1000; display: none;"></div>
                        <button type="button" class="btn hrm-filter-clear hrm-filter-clear-inline" data-filter="type" title="Limpiar tipo" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:transparent; color:#d9534f; font-size:18px; padding:0; line-height:1; cursor:pointer; display: none;">&times;</button>
                    </div>

                    <div style="position: relative; min-width: 150px; max-width: 220px;">
                        <input 
                            type="text" 
                            class="form-control" 
                            id="hrm-doc-year-filter-search" 
                            placeholder="Buscar año..."
                            autocomplete="off"
                            style="padding-right:36px;">
                        <div id="hrm-doc-year-filter-items" style="position: absolute; top: 100%; left: 0; min-width: 150px; max-width: 250px; background: white; border: 1px solid #dee2e6; border-top: none; max-height: 300px; overflow-y: auto; z-index: 1000; display: none;"></div>
                        <button type="button" class="btn hrm-filter-clear hrm-filter-clear-inline" data-filter="year" title="Limpiar año" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:transparent; color:#d9534f; font-size:18px; padding:0; line-height:1; cursor:pointer; display: none;">&times;</button>
                    </div>
                    
                    <input type="hidden" id="hrm-doc-filter-type-id" value="">
                    <input type="hidden" id="hrm-doc-filter-year" value="">
                    <button id="hrm-doc-filters-clear-all" type="button" class="btn btn-sm btn-outline-secondary ms-1" title="Limpiar todos">&times;</button>
                </div>
            </div>

            <div class="col-md-auto text-md-end mt-3 mt-md-0">
                <button
                    class="btn btn-success btn-sm"
                    id="btn-nuevo-documento"
                    data-employee-id="<?= esc_attr( $employee_id ) ?>"
                    data-has-employee="<?= $has_employee ? '1' : '0' ?>"
                    <?= ! $has_employee ? 'disabled aria-disabled="true"' : '' ?>
                    title="<?= ! $has_employee ? 'Selecciona un usuario para habilitar' : 'Nuevo Documento' ?>"
                >
                    <span class="dashicons dashicons-plus-alt"></span> Nuevo Documento
                </button>
            </div>
        </div>
    </div>

    <!-- Alert pequeña eliminada: usamos la alerta grande central en #hrm-documents-container -->

    <!-- Panel de subida de documentos -->
    <div id="hrm-upload-panel" class="border rounded shadow p-4 mb-4 bg-white" style="max-width: 600px; margin: 0 auto; display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); z-index: 9999;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><span class="dashicons dashicons-upload"></span> Subir Nuevo Documento</h5>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-cerrar-upload">Cerrar</button>
        </div>
        <form method="post" enctype="multipart/form-data" id="hrm-upload-form">
            <?php wp_nonce_field( 'hrm_upload_file', 'hrm_upload_nonce' ); ?>
            <input type="hidden" name="employee_id" id="hrm_upload_employee_id" value="<?= esc_attr( $employee_id ) ?>">
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
                        <?php
                        // $hrm_tipos_documento puede ser array asociativo id=>nombre o lista de strings (legacy)
                        foreach ( $hrm_tipos_documento as $k => $v ) :
                            if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
                                $tipo_id = (int) $k;
                                $tipo_name = (string) $v;
                            } elseif ( is_array( $v ) && isset( $v['id'] ) ) {
                                $tipo_id = (int) $v['id'];
                                $tipo_name = (string) ( $v['nombre'] ?? $v['name'] ?? '' );
                            } else {
                                // Legacy: flat array of names
                                $tipo_id = '';
                                $tipo_name = (string) $v;
                            }
                        ?>
                            <a class="dropdown-item py-2 px-3 hrm-tipo-item" href="#" data-tipo-id="<?= esc_attr( $tipo_id ) ?>" data-tipo-name="<?= esc_attr( $tipo_name ) ?>">
                                <strong><?= esc_html( $tipo_name ) ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <input type="hidden" id="hrm_tipo_documento" name="tipo_documento" required>
                    <button type="button" id="btn-crear-tipo" class="btn btn-outline-primary btn-sm">Crear tipo</button>
                    <small class="text-muted d-block mt-1">Selecciona el tipo de documento a subir (o crea uno nuevo)</small>
                </div>
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
    const msgDiv = document.getElementById('hrm-documents-message');
    const hiddenInput = document.getElementById('hrm_upload_employee_id');

    function showSelectEmployeeAlert() {
        const bigMsg = '<div class="alert alert-warning text-center" style="font-size:1.25rem; padding:2rem;"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
        if (msgDiv) {
            msgDiv.innerHTML = bigMsg;
            msgDiv.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
        const container = document.getElementById('hrm-documents-container');
        if (container) container.innerHTML = bigMsg;
    }

    function clearAlert() {
        if (msgDiv) msgDiv.innerHTML = '';
        const container = document.getElementById('hrm-documents-container');
        if (container) container.innerHTML = '';
    }

    // Inicializar estado del botón según data-attributes
    if (btnNuevo) {
        const hasEmployee = btnNuevo.dataset.hasEmployee === '1';
        if (!hasEmployee) {
            btnNuevo.setAttribute('disabled', 'disabled');
            btnNuevo.setAttribute('aria-disabled', 'true');
            btnNuevo.title = 'Selecciona un usuario para habilitar';
        } else {
            btnNuevo.removeAttribute('disabled');
            btnNuevo.removeAttribute('aria-disabled');
            btnNuevo.title = 'Nuevo Documento';
        }

        btnNuevo.addEventListener('click', function(e) {
            const curHasEmployee = btnNuevo.dataset.hasEmployee === '1';
            const curEmployeeId = btnNuevo.dataset.employeeId || '';

            if (!curHasEmployee || !curEmployeeId) {
                e.preventDefault();
                showSelectEmployeeAlert();
                return;
            }

            // Prefill del employee id en el formulario de subida
            if (hiddenInput) {
                hiddenInput.value = curEmployeeId;
            }

            uploadPanel.style.display = 'block';
        });
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

    // Mostrar alerta inicial si no hay empleado
    if (btnNuevo && btnNuevo.dataset.hasEmployee !== '1') {
        showSelectEmployeeAlert();
    }

    // Función pública para actualizar employee desde otras partes del código
    window.hrmDocumentsSetEmployee = function(employeeId) {
        if (!btnNuevo) return;
        if (employeeId) {
            btnNuevo.dataset.employeeId = employeeId;
            btnNuevo.dataset.hasEmployee = '1';
            btnNuevo.removeAttribute('disabled');
            btnNuevo.removeAttribute('aria-disabled');
            btnNuevo.title = 'Nuevo Documento';
            if (hiddenInput) hiddenInput.value = employeeId;
            clearAlert();

            // Intentar cargar documentos para este empleado
            if ( typeof window.loadEmployeeDocuments === 'function' ) {
                window.loadEmployeeDocuments();
            }
        } else {
            btnNuevo.dataset.employeeId = '';
            btnNuevo.dataset.hasEmployee = '0';
            btnNuevo.setAttribute('disabled', 'disabled');
            btnNuevo.setAttribute('aria-disabled', 'true');
            btnNuevo.title = 'Selecciona un usuario para habilitar';
            if (hiddenInput) hiddenInput.value = '';
            showSelectEmployeeAlert();
        }
    };

    // Si hrmDocsListData está disponible (script cargado), usarlo para inicializar el estado
    if (typeof hrmDocsListData !== 'undefined') {
        if (hrmDocsListData.employeeId) {
            window.hrmDocumentsSetEmployee(hrmDocsListData.employeeId);
        }
    }
});
</script>

