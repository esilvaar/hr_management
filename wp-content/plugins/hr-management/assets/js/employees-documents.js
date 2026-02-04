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

        function showSelectEmployeeAlert(shouldScroll) {
            const bigMsg = '<div class="alert alert-warning text-center hrm-big-alert"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
            if (msgDiv) {
                msgDiv.innerHTML = bigMsg;
                // Solo hacer scroll si se solicita explícitamente (ej: al hacer click en botón)
                if (shouldScroll) {
                    msgDiv.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
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
                    showSelectEmployeeAlert(true);
                    return;
                }

                // Prefill del employee id en el formulario de subida
                if (hiddenInput) {
                    hiddenInput.value = curEmployeeId;
                }

                uploadPanel && (uploadPanel.style.display = 'block');

                // Ensure year visibility matches current selected type when opening the panel
                try {
                    const tipoSearch = document.getElementById('hrm-tipo-search');
                    const tipoValue = tipoSearch ? tipoSearch.value : '';
                    if ( typeof window.hrmToggleYearForTypeName === 'function' ) window.hrmToggleYearForTypeName(tipoValue);
                } catch(e) { /* no-op */ }

                // Also default the upload year to the currently active year filter (if set)
                try {
                    const globalYear = document.getElementById('hrm-doc-filter-year');
                    const uploadHidden = document.getElementById('hrm_anio_documento');
                    const uploadVisible = document.getElementById('hrm-anio-search');
                    if ( globalYear && globalYear.value ) {
                        if ( uploadHidden ) uploadHidden.value = globalYear.value;
                        if ( uploadVisible ) uploadVisible.value = globalYear.value;
                    }
                } catch(e) { /* no-op */ }
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

        // Mostrar alerta inicial si no hay empleado (sin scroll automático)
        if (btnNuevo && btnNuevo.dataset.hasEmployee !== '1') {
            showSelectEmployeeAlert(false);
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

        // Año: manejar input de año (se llena dinámicamente desde la lista de documentos)
        (function(){
            const yearInput = document.getElementById('hrm-doc-year-filter-search');
            const yearHidden = document.getElementById('hrm-doc-filter-year');
            const yearClearBtn = document.querySelector('.hrm-filter-clear[data-filter="year"]');
            if (!yearInput || !yearHidden) return;

            function triggerReload() {
                if ( typeof applyActiveFilters === 'function' ) {
                    try { applyActiveFilters(); } catch(e) { if ( typeof window.loadEmployeeDocuments === 'function' ) window.loadEmployeeDocuments(); }
                } else if ( typeof window.loadEmployeeDocuments === 'function' ) {
                    window.loadEmployeeDocuments();
                }
            }

            // Clear button visibility
            if ( yearClearBtn ) {
                yearClearBtn.style.display = yearHidden.value ? '' : 'none';
                yearClearBtn.addEventListener('click', function(){
                    yearInput.value = '';
                    yearHidden.value = '';
                    this.style.display = 'none';
                    triggerReload();
                });
            }

            // When user types, we just show the list (handled by documents-list.js) and update hidden when a year is chosen there.
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

                        // Use AbortController to prevent hanging requests
                        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                        const signal = controller ? controller.signal : null;
                        const timeoutMs = 15000; // 15s
                        const timeoutId = controller ? setTimeout(() => { controller.abort(); }, timeoutMs) : null;

                        fetch(hrmDocsListData.ajaxUrl, { method: 'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' }, body: data, signal: signal })
                        .then( r => r.json() )
                        .then( json => {
                            if ( controller && timeoutId ) clearTimeout(timeoutId);

                            if ( json && json.success ) {
                                // remover de la lista visual
                                if ( row ) row.remove();

                                // remover del selector de upload
                                document.querySelectorAll('#hrm-tipo-items .hrm-tipo-item').forEach( it => {
                                    if ( it.getAttribute('data-tipo-id') == id ) it.remove();
                                });

                                // remover del filtro
                                document.querySelectorAll('#hrm-doc-type-filter-items .hrm-doc-type-item').forEach( it => {
                                    if ( it.getAttribute('data-type-id') == id ) it.remove();
                                });

                                // eliminar enlaces relacionados en sidebar (si existen)
                                try {
                                    document.querySelectorAll("a[href*='hrm-mi-documentos-type-" + id + "']").forEach(el => el.remove());
                                    document.querySelectorAll('li').forEach(li => {
                                        if ( li.querySelector("a[href*='hrm-mi-documentos-type-" + id + "']") ) {
                                            li.remove();
                                        }
                                    });
                                } catch (e) { /* no-op */ }

                                // actualizar cache local
                                if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) ) {
                                    hrmDocsListData.types = hrmDocsListData.types.filter( t => String(t.id) !== String(id) );
                                }

                                showCreateTypeMessage('Tipo eliminado', 'success');
                                confirmDiv.remove();

                            } else {
                                let msg = 'No se pudo eliminar el tipo';
                                if ( json && json.data && json.data.message ) msg = json.data.message;
                                showCreateTypeMessage(msg, 'error');
                                confirmDiv.remove();
                                deleteBtn.style.display = '';
                            }
                        })
                        .catch( e => {
                            if ( controller && timeoutId ) clearTimeout(timeoutId);
                            if ( e && e.name === 'AbortError' ) {
                                showCreateTypeMessage('Tiempo de espera agotado al eliminar tipo', 'error');
                            } else {
                                showCreateTypeMessage('Error al eliminar tipo', 'error');
                            }
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

                // Abortable fetch with timeout to avoid hanging
                const controllerCreate = typeof AbortController !== 'undefined' ? new AbortController() : null;
                const signalCreate = controllerCreate ? controllerCreate.signal : null;
                const timeoutCreate = controllerCreate ? setTimeout(() => { controllerCreate.abort(); }, 15000) : null;

                fetch(hrmDocsListData.ajaxUrl, { method: 'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' }, body: data, signal: signalCreate })
                .then( r => r.json() )
                .then( json => {
                    if ( controllerCreate && timeoutCreate ) clearTimeout(timeoutCreate);
                    if ( json && json.success ) {
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
                            try { attachDeleteHandlers(); } catch (e) { /* no-op */ }
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
                            if ( ! isEmpresaType(name) ) {
                                itemsContainer.insertBefore(a, itemsContainer.firstChild);
                            }
                            a.addEventListener('click', function(e) {
                                e.preventDefault();
                                const searchInput = document.getElementById('hrm-tipo-search');
                                const hiddenInput = document.getElementById('hrm_tipo_documento');
                                if (searchInput) searchInput.value = name;
                                if (hiddenInput) hiddenInput.value = id;
                                if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
                                else { itemsContainer.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ itemsContainer.style.display = 'none'; }, 220); }
                                if ( typeof window.hrmToggleYearForTypeName === 'function' ) window.hrmToggleYearForTypeName(name);
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
                            if ( ! isEmpresaType(name) ) {
                                f.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    const searchInputFilter = document.getElementById('hrm-doc-type-filter-search');
                                    if ( searchInputFilter ) searchInputFilter.value = name;
                                    if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(filterItems);
                                    else { filterItems.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ filterItems.style.display = 'none'; }, 220); }
                                    if ( typeof filterDocumentsByType === 'function' ) filterDocumentsByType( id );
                                });
                                filterItems.insertBefore( f, filterItems.firstChild );
                            }
                        }

                        // Actualizar cache local de tipos si existe
                        if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) ) {
                            hrmDocsListData.types.unshift( { id: id, name: name } );
                        }

                        showCreateTypeMessage('Tipo creado: ' + name, 'success');
                        if (createTypeInput) createTypeInput.value = '';
                    } else {
                        let msg = 'No se pudo crear el tipo';
                        if ( json && json.data && json.data.message ) msg = json.data.message;
                        showCreateTypeMessage(msg, 'error');
                    }
                })
                .catch( e => {
                    if ( controllerCreate && timeoutCreate ) clearTimeout(timeoutCreate);
                    if ( e && e.name === 'AbortError' ) {
                        showCreateTypeMessage('Tiempo de espera agotado al crear tipo', 'error');
                    } else {
                        showCreateTypeMessage('Error al crear tipo', 'error');
                    }
                })
                .finally( () => {
                    btnCrearTipoDir.disabled = false;
                    btnCrearTipoDir.textContent = 'Crear tipo';
                });
            });

            // Prevent Enter key from submitting the form (we handle create via AJAX click)
            const createForm = document.getElementById('hrm-create-type-form');
            if ( createForm ) {
                createForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if ( btnCrearTipoDir ) btnCrearTipoDir.click();
                });
            }

            // inicializar handlers de eliminar existentes
            attachDeleteHandlers();
        }
    });
})();
