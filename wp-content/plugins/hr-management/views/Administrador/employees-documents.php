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

// Remove 'Empresa' type from JS and selectors so it is not used client-side
if ( ! empty( $doc_types_js ) ) {
    $doc_types_js = array_filter( $doc_types_js, function( $t ) {
        return strtolower( trim( (string) ($t['name'] ?? '') ) ) !== 'empresa';
    } );
    // Reindex
    $doc_types_js = array_values( $doc_types_js );
}

wp_localize_script( 'hrm-documents-list-init', 'hrmDocsListData', array(
    'employeeId' => $employee_id,
    'hasEmployee' => $has_employee,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'hrm_get_documents' ),
    'createTypeNonce' => wp_create_nonce( 'hrm_create_type' ),
    'deleteTypeNonce' => wp_create_nonce( 'hrm_delete_type' ),
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
                        <select id="hrm-doc-year-filter-select" class="form-control">
                            <option value="">— Año —</option>
                            <?php $anio_actual = (int) date('Y'); for ($y = $anio_actual; $y >= 2000; $y--) : ?>
                                <option value="<?= esc_attr( $y ); ?>" <?= $y === $anio_actual ? 'selected' : ''; ?>><?= esc_html( $y ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="button" class="btn hrm-filter-clear hrm-filter-clear-inline" data-filter="year" title="Limpiar año" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:transparent; color:#d9534f; font-size:18px; padding:0; line-height:1; cursor:pointer;">&times;</button>
                    </div>
                    
                    <input type="hidden" id="hrm-doc-filter-type-id" value="">
                    <input type="hidden" id="hrm-doc-filter-year" value="<?= esc_attr( date('Y') ); ?>">
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
                Nuevo Documento
                </button>
            </div>
            <div class="col-md-auto mt-3 mt-md-0">
                <button
                    class="btn btn-secondary btn-sm"
                    id="btn-nuevo-directorio"
                    data-has-employee="1"
                    data-employee-id=""
                    title="Nuevo Directorio"
                >
                    Nuevo Directorio
                </button>
            </div>
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
                                // Omitir tipo 'Empresa' completamente en el selector de subida
                                if ( strtolower( trim( $tipo_name ) ) === 'empresa' ) continue;
                            ?>
                                <a class="dropdown-item py-2 px-3 hrm-tipo-item" href="#" data-tipo-id="<?= esc_attr( $tipo_id ) ?>" data-tipo-name="<?= esc_attr( $tipo_name ) ?>">
                                    <strong><?= esc_html( $tipo_name ) ?></strong>
                                </a>
                            <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <input type="hidden" id="hrm_tipo_documento" name="tipo_documento" required>
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
            </div>
            <div class="mb-3">
                <label for="hrm_archivos_subidos" class="form-label fw-bold">Archivos (PDF) *</label>
                <input id="hrm_archivos_subidos"
                       type="file"
                       name="archivos_subidos[]"
                       multiple
                       required
                       accept=".pdf"
                       class="form-control">
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
    <!-- Panel de Creacion de Directorios (modal similar a Subir) -->
    <div id="hrm-create-type-panel" class="border rounded shadow p-4 mb-4 bg-white" style="max-width: 520px; margin: 0 auto; display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); z-index: 9999;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><span ></span> Gestión de Directorios</h5>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Tipos existentes</label>
            <div id="hrm-create-type-list" style="max-height:260px; overflow-y:auto; border:1px solid #e9ecef; padding:8px;">
                <?php foreach ( $hrm_tipos_documento as $k => $v ) :
                    if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
                        $tipo_id = (int) $k;
                        $tipo_name = (string) $v;
                    } elseif ( is_array( $v ) && isset( $v['id'] ) ) {
                        $tipo_id = (int) $v['id'];
                        $tipo_name = (string) ( $v['nombre'] ?? $v['name'] ?? '' );
                    } else {
                        $tipo_id = '';
                        $tipo_name = (string) $v;
                    }
                    // Omitir 'Empresa' de la lista de gestión para que no aparezca en la UI
                    if ( strtolower( trim( $tipo_name ) ) === 'empresa' ) continue;
                ?>
                    <div class="d-flex align-items-center justify-content-between py-1 hrm-type-row" data-type-id="<?= esc_attr( $tipo_id ) ?>">
                        <div class="text-start"><?= esc_html( $tipo_name ) ?></div>
                        <div>
                            <?php if ( $tipo_id ) : ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-type" data-type-id="<?= esc_attr( $tipo_id ) ?>" title="Eliminar tipo">Eliminar</button>
                            <?php else : ?>
                                <button type="button" class="btn btn-sm btn-secondary" disabled>Legacy</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <hr>

        <form id="hrm-create-type-form">
            <?php wp_nonce_field( 'hrm_create_type', 'hrm_create_type_nonce' ); ?>
            <div class="mb-3">
                <label class="form-label fw-bold" for="hrm-create-tipo-name">Crear nuevo tipo</label>
                <div class="input-group">
                    <input type="text" id="hrm-create-tipo-name" class="form-control" required placeholder="Escribe el nombre...">
                    <button type="button" class="btn btn-success" id="btn-crear-tipo-dir">Crear tipo</button>
                </div>
            </div>
            <div id="hrm-create-type-message" class="mt-1"></div>
            <div class="d-flex justify-content-end gap-2 mt-2">
                <button type="button" class="btn btn-secondary" id="btn-cancelar-create-type">Cerrar</button>
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

<div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Exponer info de usuario actual y permisos al JS
    const HRM_CURRENT_USER_ID = <?php echo intval( get_current_user_id() ); ?>;
    const HRM_CAN_VIEW_OTHERS = <?php echo ( current_user_can('manage_options') || current_user_can('edit_hrm_employees') ) ? 'true' : 'false'; ?>;
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

    // Año: sincronizar select con filtro oculto y manejar clear
    (function(){
        const yearSelect = document.getElementById('hrm-doc-year-filter-select');
        const yearHidden = document.getElementById('hrm-doc-filter-year');
        const yearClearBtn = document.querySelector('.hrm-filter-clear[data-filter="year"]');
        if (!yearSelect || !yearHidden) return;

        // Inicializar hidden con el valor seleccionado (por defecto año actual)
        yearHidden.value = yearSelect.value || '';

        function triggerReload() {
            if ( typeof filterDocumentsByYear === 'function' ) {
                try { filterDocumentsByYear( yearHidden.value ); } catch(e){ if ( typeof window.loadEmployeeDocuments === 'function' ) window.loadEmployeeDocuments(); }
            } else if ( typeof window.loadEmployeeDocuments === 'function' ) {
                window.loadEmployeeDocuments();
            }
        }

        yearSelect.addEventListener('change', function(){
            yearHidden.value = this.value || '';
            if ( yearClearBtn ) yearClearBtn.style.display = this.value ? '' : 'none';
            triggerReload();
        });

        if ( yearClearBtn ) {
            yearClearBtn.style.display = yearSelect.value ? '' : 'none';
            yearClearBtn.addEventListener('click', function(){
                yearSelect.value = '';
                yearHidden.value = '';
                this.style.display = 'none';
                triggerReload();
            });
        }
    })();

    // Helper: comprobar si el tipo es 'Empresa' (case-insensitive)
    function isEmpresaType(name) {
        return (typeof name === 'string' && name.trim().toLowerCase() === 'empresa');
    }

    // Mostrar mensaje breve en el panel de upload cuando se intenta seleccionar Empresa
    function showUploadTypeBlockedMessage() {
        const upMsg = document.getElementById('hrm-upload-message');
        if (!upMsg) return;
        upMsg.innerHTML = '<div class="alert alert-warning">El tipo "Empresa" no está disponible para subir documentos. Usa otro tipo.</div>';
        setTimeout(() => { upMsg.innerHTML = ''; }, 4000);
    }

    // Deshabilitar entradas existentes con nombre 'Empresa' en los selectores y filtros
    try {
        const tipoItems = document.querySelectorAll('#hrm-tipo-items .hrm-tipo-item');
        tipoItems.forEach(it => {
            const nm = it.getAttribute('data-tipo-name') || it.textContent || '';
            if ( isEmpresaType(nm) ) {
                it.classList.add('hrm-type-disabled');
                it.setAttribute('data-disabled', '1');
                it.title = 'Este tipo no está disponible para selección';
            }
        });

        const filterItems = document.querySelectorAll('#hrm-doc-type-filter-items .hrm-doc-type-item');
        filterItems.forEach(it => {
            const nm = it.getAttribute('data-type-name') || it.textContent || '';
            if ( isEmpresaType(nm) ) {
                it.classList.add('hrm-type-disabled');
                it.setAttribute('data-disabled', '1');
                it.title = 'Tipo no utilizable';
            }
        });
    } catch (e) {
        // no-op
    }

    // Delegación: interceptar clicks en items de tipo y bloquear Empresa
    document.addEventListener('click', function(e) {
        const a = e.target.closest && e.target.closest('.hrm-tipo-item');
        if ( a ) {
            const nm = a.getAttribute('data-tipo-name') || a.textContent || '';
            if ( isEmpresaType(nm) ) {
                e.preventDefault();
                showUploadTypeBlockedMessage();
            }
        }
    });

    // Nuevo Directorio (crear tipo) - panel y lógica
    const btnNuevoDir = document.getElementById('btn-nuevo-directorio');
    const createTypePanel = document.getElementById('hrm-create-type-panel');
    const btnCerrarCreate = document.getElementById('btn-cerrar-create-type');
    const btnCancelarCreate = document.getElementById('btn-cancelar-create-type');
    const createTypeMsg = document.getElementById('hrm-create-type-message');
    const createTypeInput = document.getElementById('hrm-create-tipo-name');
    const btnCrearTipoDir = document.getElementById('btn-crear-tipo-dir');

    if (btnNuevoDir) {
        const hasEmployeeDir = btnNuevoDir.dataset.hasEmployee === '1';
        if (!hasEmployeeDir) {
            btnNuevoDir.setAttribute('disabled', 'disabled');
            btnNuevoDir.setAttribute('aria-disabled', 'true');
            btnNuevoDir.title = 'Selecciona un usuario para habilitar';
        } else {
            btnNuevoDir.removeAttribute('disabled');
            btnNuevoDir.removeAttribute('aria-disabled');
            btnNuevoDir.title = 'Nuevo Directorio';
        }

        btnNuevoDir.addEventListener('click', function(e) {
            // Permitir abrir el panel de creación de directorio siempre, sin exigir
            // que se haya seleccionado un empleado.
            e.preventDefault();
            if (createTypePanel) createTypePanel.style.display = 'block';
            if (createTypeInput) createTypeInput.value = '';
            if (createTypeMsg) createTypeMsg.innerHTML = '';
        });
    }

    if (btnCerrarCreate) btnCerrarCreate.onclick = function() { if (createTypePanel) createTypePanel.style.display = 'none'; };
    if (btnCancelarCreate) btnCancelarCreate.onclick = function() { if (createTypePanel) createTypePanel.style.display = 'none'; };

    function showCreateTypeMessage(message, type) {
        if (!createTypeMsg) return;
        const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
        createTypeMsg.innerHTML = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        setTimeout(() => { createTypeMsg.innerHTML = ''; }, 5000);
    }

    function attachDeleteHandlers() {
        const delButtons = createTypePanel ? createTypePanel.querySelectorAll('.btn-delete-type') : [];
        delButtons.forEach( btn => {
            // Avoid adding double handlers
            if (btn.dataset.attached === '1') return;
            btn.dataset.attached = '1';
            btn.addEventListener('click', function() {
                const deleteBtn = this;
                const id = deleteBtn.getAttribute('data-type-id');
                const row = deleteBtn.closest('.hrm-type-row');
                const name = row ? row.querySelector(':first-child').textContent.trim() : '';

                // Evitar múltiples confirmaciones en la misma fila
                if ( row.querySelector('.hrm-inline-confirm') ) return;

                // Ocultar el botón eliminar y mostrar confirmación inline HTML
                deleteBtn.style.display = 'none';

                const container = row.querySelector('div:last-child');
                const confirmDiv = document.createElement('div');
                confirmDiv.className = 'hrm-inline-confirm d-flex gap-2 align-items-center';
                confirmDiv.style.marginLeft = '8px';
                confirmDiv.innerHTML = '<small class="text-muted">¿Eliminar "' + name + '"? </small>' +
                    '<button type="button" class="btn btn-sm btn-danger hrm-inline-confirm-yes">Confirmar</button>' +
                    '<button type="button" class="btn btn-sm btn-secondary hrm-inline-confirm-no">Cancelar</button>';

                container.appendChild(confirmDiv);

                const btnYes = confirmDiv.querySelector('.hrm-inline-confirm-yes');
                const btnNo = confirmDiv.querySelector('.hrm-inline-confirm-no');

                btnNo.addEventListener('click', function() {
                    // cancelar: quitar confirmación y mostrar botón eliminar
                    confirmDiv.remove();
                    deleteBtn.style.display = '';
                });

                btnYes.addEventListener('click', function() {
                    btnYes.disabled = true;
                    btnYes.textContent = 'Eliminando...';

                    const data = new URLSearchParams();
                    data.append('action', 'hrm_delete_document_type');
                    data.append('id', id);
                    data.append('nonce', hrmDocsListData.deleteTypeNonce || '');

                    fetch(hrmDocsListData.ajaxUrl, { method: 'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' }, body: data })
                    .then( r => r.json() )
                    .then( json => {
                        if ( json && json.success ) {
                            // remover de la lista visual
                            const rowToRemove = createTypePanel.querySelector('.hrm-type-row[data-type-id="' + id + '"]');
                            if ( rowToRemove ) rowToRemove.remove();

                            // remover del selector de upload
                            const items = document.querySelectorAll('#hrm-tipo-items .hrm-tipo-item');
                            items.forEach( it => {
                                if ( it.getAttribute('data-tipo-id') == id ) it.remove();
                            });

                            // remover del filtro
                            const fitems = document.querySelectorAll('#hrm-doc-type-filter-items .hrm-doc-type-item');
                            fitems.forEach( it => {
                                if ( it.getAttribute('data-type-id') == id ) it.remove();
                            });

                            // actualizar cache local
                            if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) ) {
                                hrmDocsListData.types = hrmDocsListData.types.filter( t => String(t.id) !== String(id) );
                            }

                            showCreateTypeMessage('Tipo eliminado', 'success');
                            // limpiar confirm
                            confirmDiv.remove();
                        } else {
                            let msg = 'No se pudo eliminar el tipo';
                            if ( json && json.data && json.data.message ) msg = json.data.message;
                            showCreateTypeMessage(msg, 'error');
                            // restaurar UI
                            confirmDiv.remove();
                            deleteBtn.style.display = '';
                        }
                    })
                    .catch( e => {
                        console.error('Error eliminar tipo:', e);
                        showCreateTypeMessage('Error al eliminar tipo', 'error');
                        confirmDiv.remove();
                        deleteBtn.style.display = '';
                    });
                });
            });
        });
    }

    if (btnCrearTipoDir) {
        btnCrearTipoDir.addEventListener('click', function() {
            const nombre = (createTypeInput && createTypeInput.value.trim()) || '';
            if (!nombre) {
                showCreateTypeMessage('Por favor escribe el nombre del nuevo tipo', 'error');
                return;
            }
            btnCrearTipoDir.disabled = true;
            btnCrearTipoDir.textContent = 'Creando...';

            const data = new URLSearchParams();
            data.append('action', 'hrm_create_document_type');
            data.append('name', nombre);
            data.append('nonce', hrmDocsListData.createTypeNonce || '');

            fetch(hrmDocsListData.ajaxUrl, { method: 'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' }, body: data })
            .then(r => r.json())
            .then(json => {
                if (json && json.success) {
                    const id = json.data.id;
                    const name = json.data.name;

                    // Añadir a la lista de gestión
                    const list = document.getElementById('hrm-create-type-list');
                    if ( list ) {
                        const div = document.createElement('div');
                        div.className = 'd-flex align-items-center justify-content-between py-1 hrm-type-row';
                        div.setAttribute('data-type-id', id);
                        div.innerHTML = '<div class="text-start">' + name + '</div><div><button type="button" class="btn btn-sm btn-outline-danger btn-delete-type" data-type-id="' + id + '">Eliminar</button></div>';
                        list.insertBefore(div, list.firstChild);
                        // Attach handlers to the newly inserted delete button so it works immediately
                        try { attachDeleteHandlers(); } catch (e) { console.error('attachDeleteHandlers error', e); }
                    }

                    // Añadir al selector de upload si existe
                    const itemsContainer = document.getElementById('hrm-tipo-items');
                    if (itemsContainer) {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'dropdown-item py-2 px-3 hrm-tipo-item';
                        a.setAttribute('data-tipo-id', id);
                        a.setAttribute('data-tipo-name', name);
                        a.innerHTML = '<strong>' + name + '</strong>';
                        // Do not add 'Empresa' to upload selector
                        if ( ! isEmpresaType(name) ) {
                            itemsContainer.insertBefore(a, itemsContainer.firstChild);
                        }
                        a.addEventListener('click', function(e) {
                            e.preventDefault();
                            const searchInput = document.getElementById('hrm-tipo-search');
                            const hiddenInput = document.getElementById('hrm_tipo_documento');
                            if (searchInput) searchInput.value = name;
                            if (hiddenInput) hiddenInput.value = id;
                            itemsContainer.style.display = 'none';
                            if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
                        });
                    }

                    // Añadir al filtro de tipos si existe
                    const filterItems = document.getElementById('hrm-doc-type-filter-items');
                    if (filterItems) {
                        const f = document.createElement('a');
                        f.href = '#';
                        f.className = 'dropdown-item py-2 px-3 hrm-doc-type-item';
                        f.setAttribute('data-type-id', id );
                        f.setAttribute('data-type-name', name );
                        f.textContent = name;
                        // Do not add Empresa to filter list
                        if ( ! isEmpresaType(name) ) {
                            f.addEventListener('click', function(e) {
                                e.preventDefault();
                                const searchInputFilter = document.getElementById('hrm-doc-type-filter-search');
                                if ( searchInputFilter ) searchInputFilter.value = name;
                                filterItems.style.display = 'none';
                                if ( typeof filterDocumentsByType === 'function' ) filterDocumentsByType( id );
                            });
                            filterItems.insertBefore( f, filterItems.firstChild );
                        }
                    }

                    // Añadir también un botón en panel 'Documentos' (empleado detalle) si existe
                        try {
                        const docPanels = document.querySelectorAll('.hrm-panel-body.hrm-doc-panel-body');
                        function findEmployeeIdForPanel(panel){
                            // 1) data-employee-id on panel or closest ancestor
                            let el = panel.closest('[data-employee-id]') || panel.querySelector('[data-employee-id]');
                            if (el && el.dataset && el.dataset.employeeId) return el.dataset.employeeId;

                            // 2) hidden inputs inside panel (common patterns)
                            const inputSelectors = ['input[name="employee_id"]','input#employee_id','input[name="hrm_employee_id"]','input[name="employee-id"]'];
                            for (const sel of inputSelectors){
                                const inp = panel.querySelector(sel);
                                if (inp && inp.value) return inp.value;
                            }

                            // 3) data attributes directly on panel
                            if (panel.dataset && panel.dataset.employeeId) return panel.dataset.employeeId;

                            // 4) fallback to global hrmDocsListData if available
                            if (typeof hrmDocsListData !== 'undefined' && hrmDocsListData.employeeId) return hrmDocsListData.employeeId;

                            return '';
                        }

                        docPanels.forEach(panel => {
                            // No insertar si ya existe
                            if ( panel.querySelector("a[href*='hrm-mi-documentos-type-" + id + "']") ) return;

                            // No insertar botones de tipo 'Empresa' dentro del panel de empleado
                            if ( isEmpresaType(name) ) return;

                            const a = document.createElement('a');
                            a.className = 'hrm-doc-btn';

                            // Detectar employeeId específico para este panel antes de usar el global
                            const detectedEmpId = findEmployeeIdForPanel(panel) || '';
                            const includeEmp = (typeof HRM_CAN_VIEW_OTHERS !== 'undefined' && HRM_CAN_VIEW_OTHERS) || (detectedEmpId && Number(detectedEmpId) === Number(HRM_CURRENT_USER_ID));
                            const href = (includeEmp && detectedEmpId) ? ('admin.php?page=hrm-mi-documentos-type-' + id + '&employee_id=' + encodeURIComponent(detectedEmpId)) : ('admin.php?page=hrm-mi-documentos-type-' + id);
                            a.href = href;
                            a.title = name;
                            a.setAttribute('data-icon-color', '#b0b5bd');

                            a.innerHTML = '<div class="hrm-doc-btn-icon"><span class="dashicons dashicons-media-document"></span></div>' +
                                          '<div class="hrm-doc-btn-content"><div class="hrm-doc-btn-title">' + name + '</div><div class="hrm-doc-btn-desc">Accede a ' + name + '</div></div>' +
                                          '<div class="hrm-doc-btn-arrow"><span class="dashicons dashicons-arrow-right-alt2"></span></div>';

                            // Apply inline background color and CSS variable to the icon immediately
                            try {
                                const tmp = document.createElement('div');
                                tmp.innerHTML = a.innerHTML;
                                const iconNode = tmp.querySelector('.hrm-doc-btn-icon');
                                if (iconNode) {
                                    iconNode.style.backgroundColor = '#b0b5bd';
                                }
                                a.innerHTML = tmp.innerHTML;
                            } catch (e) {
                                // fallback: set after insertion
                            }
                            try { a.style.setProperty('--hrm-doc-icon', '#b0b5bd'); } catch (e) {}

                            panel.appendChild(a);
                        });

                        // Adicional: si no se encontraron panels, intentar insertar como links en sidebars, evitando duplicados
                        // NO insertar dinámicamente en la sidebar si el tipo es 'Empresa'
                        try {
                            if ( typeof name === 'string' && name.trim().toLowerCase() === 'empresa' ) {
                                // skip sidebar insertion for Empresa
                            } else {
                                const refAnchors = document.querySelectorAll("a[href*='hrm-mi-documentos-contratos'], a[href*='hrm-mi-documentos-liquidaciones']");
                                if ( refAnchors && refAnchors.length ) {
                                    refAnchors.forEach(ref => {
                                        const parentLi = ref.closest('li');
                                        if (parentLi && parentLi.parentNode && !parentLi.parentNode.querySelector("a[href*='hrm-mi-documentos-type-" + id + "']")) {
                                            const li = document.createElement('li');
                                            const a = document.createElement('a');
                                            a.className = 'nav-link px-3 py-2';
                                            a.href = 'admin.php?page=hrm-mi-documentos-type-' + id;
                                            a.textContent = name;
                                            li.appendChild(a);
                                            parentLi.parentNode.insertBefore(li, parentLi.nextSibling);
                                        }
                                    });
                                }
                            }
                        } catch (e) {
                            console.error('Error inserting into sidebar:', e);
                        }

                        // Si existe data global con tipos, actualizarla
                        if ( window.hrmDocumentTypesData && Array.isArray( window.hrmDocumentTypesData.types ) ) {
                            // no-op: server provides object id=>name; keep consistency on reload
                        }

                    } catch (e) {
                        console.error('Error agregando link a sidebar/panels:', e);
                    }

                    // Mantener modal abierto para gestión
                    if (createTypeInput) createTypeInput.value = '';

                } else {
                    let msg = 'No se pudo crear el tipo';
                    if ( json && json.data && json.data.message ) msg = json.data.message;
                    showCreateTypeMessage(msg, 'error');
                }
            })
            .catch(e => {
                console.error('Error crear tipo:', e);
                showCreateTypeMessage('Error al crear tipo', 'error');
            })
            .finally(() => {
                btnCrearTipoDir.disabled = false;
                btnCrearTipoDir.textContent = 'Crear tipo';
            });
        });

        // inicializar handlers de eliminar existentes
        attachDeleteHandlers();
    }
});
</script>

