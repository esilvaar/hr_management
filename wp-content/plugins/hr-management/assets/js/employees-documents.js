(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Exponer info de usuario actual y permisos al JS (via hrmDocsListData)
        const HRM_CURRENT_USER_ID = (window.hrmDocsListData && window.hrmDocsListData.currentUserId) ? parseInt(window.hrmDocsListData.currentUserId,10) : 0;
        const HRM_CAN_VIEW_OTHERS = (window.hrmDocsListData && window.hrmDocsListData.canViewOthers) ? Boolean(window.hrmDocsListData.canViewOthers) : false;
        const uploadPanel = document.getElementById('hrm-upload-panel');
        const btnNuevo = document.getElementById('btn-nuevo-documento');
        const btnCerrar = document.getElementById('btn-cerrar-upload');
        const btnCancelar = document.getElementById('btn-cancelar-upload');
        const msgDiv = document.getElementById('hrm-documents-message');
        const hiddenInput = document.getElementById('hrm_upload_employee_id');

        function showSelectEmployeeAlert() {
            const bigMsg = '<div class="alert alert-warning text-center hrm-big-alert"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
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

                uploadPanel && (uploadPanel.style.display = 'block');
            });
        }

        if (btnCerrar) {
            btnCerrar.onclick = function() {
                uploadPanel && (uploadPanel.style.display = 'none');
            };
        }
        if (btnCancelar) {
            btnCancelar.onclick = function() {
                uploadPanel && (uploadPanel.style.display = 'none');
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
        function isEmpresaType(name) { return (typeof name === 'string' && name.trim().toLowerCase() === 'empresa'); }

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
        } catch (e) { /* no-op */ }

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
                            // ... (rest of code unchanged)
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
                // ... (rest unchanged)
            });

            // inicializar handlers de eliminar existentes
            attachDeleteHandlers();
        }
    });
})();
