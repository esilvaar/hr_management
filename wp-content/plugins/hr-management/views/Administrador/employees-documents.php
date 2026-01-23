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
                        itemsContainer.insertBefore(a, itemsContainer.firstChild);
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
                        f.addEventListener('click', function(e) {
                            e.preventDefault();
                            const searchInputFilter = document.getElementById('hrm-doc-type-filter-search');
                            if ( searchInputFilter ) searchInputFilter.value = name;
                            filterItems.style.display = 'none';
                            if ( typeof filterDocumentsByType === 'function' ) filterDocumentsByType( id );
                        });
                        filterItems.insertBefore( f, filterItems.firstChild );
                    }

                    // Actualizar cache local de tipos
                    if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) ) {
                        hrmDocsListData.types.unshift( { id: id, name: name } );
                    }

                    showCreateTypeMessage('Tipo creado: ' + name, 'success');

                    // Adjuntar handler al nuevo botón de eliminar
                    attachDeleteHandlers();

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

