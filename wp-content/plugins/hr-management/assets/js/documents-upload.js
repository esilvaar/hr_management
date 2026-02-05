/**
 * Manejo del formulario de carga de documentos
 */

document.addEventListener('DOMContentLoaded', function() {
    setupDocumentTypeSearch();
    setupYearSearch();
    setupFormSubmit();
});

/**
 * Configurar búsqueda de tipo de documento
 */
function setupDocumentTypeSearch() {
    const searchInput = document.getElementById('hrm-tipo-search');
    const itemsContainer = document.getElementById('hrm-tipo-items');
    const hiddenInput = document.getElementById('hrm_tipo_documento');
    
    if ( ! searchInput || ! itemsContainer ) return;
    
    searchInput.addEventListener('focus', function() {
        // Mark current selection as active
        const currentVal = hiddenInput.value;
        const currentText = searchInput.value.trim().toLowerCase();
        itemsContainer.querySelectorAll('.hrm-tipo-item').forEach(item => {
            const itemId = item.getAttribute('data-tipo-id') || item.getAttribute('data-tipo');
            const itemText = item.textContent.trim().toLowerCase();
            if ( (currentVal && itemId === currentVal) || (currentText && itemText === currentText) ) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
        else { itemsContainer.style.display = 'block'; itemsContainer.classList.add('hrm-dropdown--visible'); }
    });
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const items = itemsContainer.querySelectorAll('.hrm-tipo-item');
        
        items.forEach( item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes( query ) ? 'block' : 'none';
        });
        
        if ( query.length === 0 ) {
            if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
            else { itemsContainer.style.display = 'block'; itemsContainer.classList.add('hrm-dropdown--visible'); }
        }
    });
    
    // Helper: hide/show year depending on type
    function toggleYearVisibilityForTypeName(name) {
        const nm = (name || '').toString().trim().toLowerCase();
        const yearContainer = document.getElementById('hrm-anio-items') ? document.getElementById('hrm-anio-items').parentElement : null;
        const yearInput = document.getElementById('hrm-anio-search');
        const yearHidden = document.getElementById('hrm_anio_documento');
        const yearLabel = document.querySelector('label[for="hrm-anio-search"]');
        if ( ! yearContainer || !yearInput || !yearHidden ) return;

        if ( nm === 'contrato' ) {
            // hide year label + field and disable validation
            if ( yearLabel ) yearLabel.style.display = 'none';
            yearContainer.style.display = 'none';
            yearInput.disabled = true;
            yearInput.classList.add('visually-hidden');
            yearHidden.value = '';
            yearHidden.removeAttribute('required');
        } else {
            if ( yearLabel ) yearLabel.style.display = '';
            yearContainer.style.display = '';
            yearInput.disabled = false;
            yearInput.classList.remove('visually-hidden');
            yearHidden.setAttribute('required','required');
        }
    }

    // Expose helper globally so other modules can trigger the same behaviour
    window.hrmToggleYearForTypeName = toggleYearVisibilityForTypeName;

    // Seleccionar tipo (soporta data-tipo-id + data-tipo-name ó legacy data-tipo)
    const items = itemsContainer.querySelectorAll('.hrm-tipo-item');
    items.forEach( item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const tipoId = this.getAttribute('data-tipo-id') || this.getAttribute('data-tipo') || '';
            const tipoName = this.getAttribute('data-tipo-name') || this.getAttribute('data-tipo') || this.textContent.trim();
            searchInput.value = tipoName;
            hiddenInput.value = tipoId;
            if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
            else { itemsContainer.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ itemsContainer.style.display = 'none'; }, 220); }
            // toggle year visibility
            toggleYearVisibilityForTypeName(tipoName);
            // Si existe la función global para actualizar visibilidad de clears, ejecutarla
            if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
        });
    });

    // Also react to typing in the type input (for custom typed values)
    searchInput.addEventListener('input', function() {
        toggleYearVisibilityForTypeName(this.value);
    });

    // Botón crear tipo
    const btnCrearTipo = document.getElementById('btn-crear-tipo');
    if ( btnCrearTipo ) {
        btnCrearTipo.addEventListener('click', function() {
            const nombre = searchInput.value.trim();
            if ( ! nombre ) {
                showUploadMessage('Por favor escribe el nombre del nuevo tipo', 'error');
                return;
            }

            btnCrearTipo.disabled = true;
            btnCrearTipo.textContent = 'Creando...';

            const data = new URLSearchParams();
            data.append('action', 'hrm_create_document_type');
            data.append('name', nombre);
            data.append('nonce', hrmDocsListData.createTypeNonce || '');

            fetch( hrmDocsListData.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            })
                    .then( r => r.json() )
            .then( json => {
                if ( json && json.success ) {
                    const id = json.data.id;
                    const name = json.data.name;

                    // Añadir a la lista de tipos (al principio)
                    const a = document.createElement('a');
                    a.href = '#';
                    a.className = 'dropdown-item py-2 px-3 hrm-tipo-item';
                    a.setAttribute('data-tipo-id', id );
                    a.setAttribute('data-tipo-name', name );
                    a.innerHTML = '<strong>' + name + '</strong>';
                    itemsContainer.insertBefore( a, itemsContainer.firstChild );
                    // Attach the same click handler so new items follow same behavior
                    a.addEventListener('click', function(e){ e.preventDefault(); const tipoId = this.getAttribute('data-tipo-id') || ''; const tipoName = this.getAttribute('data-tipo-name') || this.textContent.trim(); searchInput.value = tipoName; hiddenInput.value = tipoId; if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer); else { itemsContainer.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ itemsContainer.style.display = 'none'; }, 220); } toggleYearVisibilityForTypeName(tipoName); if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility(); });

                    // Añadir también al filtro de tipos si existe
                    const filterItems = document.getElementById('hrm-doc-type-filter-items');
                    if ( filterItems ) {
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

                    // Actualizar cache local de tipos si existe
                    if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) ) {
                        hrmDocsListData.types.unshift( { id: id, name: name } );
                    }

                    // Seleccionarlo automáticamente
                    searchInput.value = name;
                    hiddenInput.value = id;
                    if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
                    else { itemsContainer.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ itemsContainer.style.display = 'none'; }, 220); }
                    // Actualizar visibilidad de clears en el listado (si existe)
                    if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();

                    showUploadMessage('Tipo creado: ' + name, 'success');
                } else {
                    let msg = 'No se pudo crear el tipo';
                    if ( json && json.data && json.data.message ) msg = json.data.message;
                    showUploadMessage(msg, 'error');
                }
            })
            .catch( e => {
                showUploadMessage('Error al crear tipo', 'error');
            })
            .finally( () => {
                btnCrearTipo.disabled = false;
                btnCrearTipo.textContent = 'Crear tipo';
            });
        });
    }

    // Fallback: si no seleccionó un tipo existente y el hidden está vacío, en el submit usar el texto como tipo (se creará en el servidor)
    const form = document.getElementById('hrm-upload-form');
    if ( form ) {
        form.addEventListener('submit', function() {
            if ( ! hiddenInput.value && searchInput.value.trim() ) {
                hiddenInput.value = searchInput.value.trim();
            }
        });
    }
}

/**
 * Configurar búsqueda de año
 */
function setupYearSearch() {
    const searchInput = document.getElementById('hrm-anio-search');
    const itemsContainer = document.getElementById('hrm-anio-items');
    const hiddenInput = document.getElementById('hrm_anio_documento');
    
    if ( ! searchInput || ! itemsContainer ) return;
    
    searchInput.addEventListener('focus', function() {
        // Mark current selection as active
        const currentVal = hiddenInput.value;
        itemsContainer.querySelectorAll('.hrm-anio-item').forEach(item => {
            const itemAnio = item.getAttribute('data-anio');
            if ( currentVal && itemAnio === currentVal ) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
        else itemsContainer.style.display = 'block';
    });
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const items = itemsContainer.querySelectorAll('.hrm-anio-item');
        
        items.forEach( item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes( query ) ? 'block' : 'none';
        });
        
        if ( query.length === 0 ) {
            if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
            else itemsContainer.style.display = 'block';
        }
    });
    
    // Seleccionar año
    const items = itemsContainer.querySelectorAll('.hrm-anio-item');
    items.forEach( item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const anio = this.getAttribute('data-anio');
            searchInput.value = anio;
            hiddenInput.value = anio;
            if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
            else { itemsContainer.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ itemsContainer.style.display = 'none'; }, 220); }
        });
    });
    // Año por defecto 2026
    searchInput.value = '2026';
    hiddenInput.value = '2026';
    // Actualizar visibilidad de clears en el listado (si existe)
    if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
}

/**
 * Configurar envío del formulario
 */
function setupFormSubmit() {
    const form = document.getElementById('hrm-upload-form');
    if ( ! form ) {
        return;
    }
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) {
        // submit button missing -- proceed without logging
    }
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validar campos requeridos
        const tipoInput = document.getElementById('hrm_tipo_documento');
        const anioInput = document.getElementById('hrm_anio_documento');
        const filesInput = document.getElementById('hrm_archivos_subidos');
        // Valores actuales (no debug log in production)
        
        if ( ! tipoInput.value ) {
            showUploadMessage('Por favor selecciona un tipo de documento', 'error');
            return;
        }
        
        // Only require year if the visible year input is enabled
        const visibleYearInput = document.getElementById('hrm-anio-search');
        if ( visibleYearInput && ! visibleYearInput.disabled && ! anioInput.value ) {
            showUploadMessage('Por favor selecciona un año', 'error');
            return;
        }
        
        if ( ! filesInput.files.length ) {
            showUploadMessage('Por favor selecciona al menos un archivo', 'error');
            return;
        }
        // Validar solo PDF
        for (let i = 0; i < filesInput.files.length; i++) {
            if (filesInput.files[i].type !== 'application/pdf') {
                showUploadMessage('Solo se permiten archivos PDF', 'error');
                return;
            }
        }
        
        // Enviar formulario
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Subiendo...';
        
        const formData = new FormData(form);

        // Debug: log de envío (para inspección en consola)
        try {
            const fileNames = filesInput && filesInput.files ? Array.from(filesInput.files).map(f => f.name) : [];
            // [HRM-UPLOAD] debug log removed
        } catch (err) {
            // [HRM-UPLOAD] debug log removed
        }
        
        // If multiple files, upload them sequentially to reduce server load/timeouts
        const filesCount = filesInput.files ? filesInput.files.length : 0;


        if ( filesCount > 1 ) {
            (async function(){
                const filesArr = Array.from(filesInput.files);
                let anySuccess = false;
                for (let i = 0; i < filesArr.length; i++) {
                    showUploadMessage('Subiendo archivo ' + (i+1) + ' de ' + filesArr.length + '...', 'success');
                    const singleFD = new FormData(form);
                    // Replace file inputs with a single file to send
                    try { singleFD.delete('archivos_subidos[]'); } catch(e) {}
                    singleFD.append('archivos_subidos[]', filesArr[i]);

                    try {
                        const resp = await fetch(form.action || '', { method: 'POST', body: singleFD });
                        const txt = await resp.text();
                        // [HRM-UPLOAD] debug log removed
                        try {
                            const json = JSON.parse(txt);
                            // [HRM-UPLOAD] debug log removed
                            if ( json && json.success ) {
                                anySuccess = true;
                            } else {
                                // [HRM-UPLOAD] debug warning removed
                            }
                        } catch (e) {
                            // non-json
                            // [HRM-UPLOAD] debug warning removed
                            anySuccess = true;
                        }
                    } catch (err) {
                        // [HRM-UPLOAD] debug error removed
                        // stop on network error
                        showUploadMessage('Error al subir archivos (falló la conexión)', 'error');
                        break;
                    }
                }

                if ( anySuccess ) {
                    showUploadMessage('Documentos subidos correctamente', 'success');
                    setTimeout(() => { location.reload(); }, 1200);
                } else {
                    showUploadMessage('No se pudieron subir los archivos', 'error');
                }

                submitButton.disabled = false;
                submitButton.innerHTML = '<span class="dashicons dashicons-upload"></span> Subir Documentos';
            })();

            return;
        }



        fetch(form.action || '', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Log crudo de respuesta
            // [HRM-UPLOAD] debug log removed

            // Si es JSON, procesarlo
            try {
                const json = JSON.parse(data);
                // [HRM-UPLOAD] debug log removed
                if ( json.success ) {
                    showUploadMessage('Documentos subidos correctamente', 'success');
                    // [HRM-UPLOAD] debug log removed
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('hrm-upload-modal'));
                        if ( modal ) modal.hide();
                        form.reset();
                        document.getElementById('hrm_tipo_documento').value = '';
                        document.getElementById('hrm_anio_documento').value = '';
                        document.getElementById('hrm-tipo-search').value = '';
                        document.getElementById('hrm-anio-search').value = '';
                        loadEmployeeDocuments();
                    }, 1500);
                } else {
                    // [HRM-UPLOAD] debug warning removed
                    showUploadMessage('Error: ' + (json.data && json.data.message ? json.data.message : (json.message || 'Error desconocido')), 'error');
                }
            } catch(e) {
                // Si no es JSON, asumir que es una redirección exitosa (o HTML devuelto)
                // [HRM-UPLOAD] debug warning removed
                showUploadMessage('Documentos subidos correctamente', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
            
                submitButton.disabled = false;
            submitButton.innerHTML = '<span class="dashicons dashicons-upload"></span> Subir Documentos';
        })
        .catch(error => {
            // [HRM-UPLOAD] debug error removed
            showUploadMessage('Error al subir documentos', 'error');
            submitButton.disabled = false;
            submitButton.innerHTML = '<span class="dashicons dashicons-upload"></span> Subir Documentos';
        });
    });
}

/**
 * Mostrar mensaje en el formulario
 */
function showUploadMessage( message, type ) {
    const messageContainer = document.getElementById('hrm-upload-message');
    if ( ! messageContainer ) return;
    
    const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
    messageContainer.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    
    // Remover mensaje después de 5 segundos
    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 5000);
}

/* UPLOAD PANEL & employee binding (migrated from view inline script) */
function setupUploadPanel() {
    const uploadPanel = document.getElementById('hrm-upload-panel');
    const btnNuevo = document.getElementById('btn-nuevo-documento');
    const btnCerrar = document.getElementById('btn-cerrar-upload');
    const btnCancelar = document.getElementById('btn-cancelar-upload');
    const msgDiv = document.getElementById('hrm-documents-message');
    const hiddenInput = document.getElementById('hrm_upload_employee_id');

    function showSelectEmployeeAlert() {
        const bigMsg = '<div class="alert alert-warning text-center myplugin-alert-big"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
        if (msgDiv) { msgDiv.innerHTML = bigMsg; msgDiv.scrollIntoView({behavior: 'smooth', block: 'center'}); }
        const container = document.getElementById('hrm-documents-container');
        if (container) container.innerHTML = bigMsg;
    }

    function clearAlert() {
        if (msgDiv) msgDiv.innerHTML = '';
        const container = document.getElementById('hrm-documents-container');
        if (container) container.innerHTML = '';
    }

    if (btnNuevo) {
        btnNuevo.addEventListener('click', function(e) {
            const curHasEmployee = btnNuevo.dataset.hasEmployee === '1';
            const curEmployeeId = btnNuevo.dataset.employeeId || '';
            if (!curHasEmployee || !curEmployeeId) {
                e.preventDefault();
                showSelectEmployeeAlert();
                return;
            }
            if (hiddenInput) hiddenInput.value = curEmployeeId;
            if (uploadPanel) uploadPanel.style.display = 'block';
        });
    }

    if (btnCerrar) btnCerrar.addEventListener('click', function(){ if (uploadPanel) uploadPanel.style.display = 'none'; });
    if (btnCancelar) btnCancelar.addEventListener('click', function(){ if (uploadPanel) uploadPanel.style.display = 'none'; });

    if (btnNuevo && btnNuevo.dataset.hasEmployee !== '1') showSelectEmployeeAlert();

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
            if ( typeof window.loadEmployeeDocuments === 'function' ) window.loadEmployeeDocuments();
        } else {
            btnNuevo.dataset.employeeId = '';
            btnNuevo.dataset.hasEmployee = '0';
            btnNuevo.setAttribute('disabled', 'disabled');
            btnNuevo.setAttribute('aria-disabled', 'true');
            if (hiddenInput) hiddenInput.value = '';
            showSelectEmployeeAlert();
        }
    };

    if (typeof hrmDocsListData !== 'undefined' && hrmDocsListData.employeeId) {
        window.hrmDocumentsSetEmployee(hrmDocsListData.employeeId);
    }
}

document.addEventListener('DOMContentLoaded', function(){
    setupUploadPanel();
});
