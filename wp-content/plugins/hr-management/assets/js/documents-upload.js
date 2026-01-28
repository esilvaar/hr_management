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
        itemsContainer.style.display = 'block';
    });
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const items = itemsContainer.querySelectorAll('.hrm-tipo-item');
        
        items.forEach( item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes( query ) ? 'block' : 'none';
        });
        
        if ( query.length === 0 ) {
            itemsContainer.style.display = 'block';
        }
    });
    
    // Seleccionar tipo (soporta data-tipo-id + data-tipo-name ó legacy data-tipo)
    const items = itemsContainer.querySelectorAll('.hrm-tipo-item');
    items.forEach( item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const tipoId = this.getAttribute('data-tipo-id') || this.getAttribute('data-tipo') || '';
            const tipoName = this.getAttribute('data-tipo-name') || this.getAttribute('data-tipo') || this.textContent.trim();
            searchInput.value = tipoName;
            hiddenInput.value = tipoId;
            itemsContainer.style.display = 'none';
            // Si existe la función global para actualizar visibilidad de clears, ejecutarla
            if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
        });
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
                    itemsContainer.style.display = 'none';
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
                console.error('Error crear tipo:', e);
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
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if ( e.target !== searchInput && e.target !== itemsContainer ) {
            itemsContainer.style.display = 'none';
        }
    });
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
        itemsContainer.style.display = 'block';
    });
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const items = itemsContainer.querySelectorAll('.hrm-anio-item');
        
        items.forEach( item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes( query ) ? 'block' : 'none';
        });
        
        if ( query.length === 0 ) {
            itemsContainer.style.display = 'block';
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
            itemsContainer.style.display = 'none';
        });
    });
    // Año por defecto 2026
    searchInput.value = '2026';
    hiddenInput.value = '2026';
    // Actualizar visibilidad de clears en el listado (si existe)
    if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if ( e.target !== searchInput && e.target !== itemsContainer ) {
            itemsContainer.style.display = 'none';
        }
    });
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
        
        if ( ! anioInput.value ) {
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
            console.log('[HRM-UPLOAD] submitting', { action: form.action, tipo: tipoInput && tipoInput.value, anio: anioInput && anioInput.value, files: fileNames });
        } catch (err) {
            console.log('[HRM-UPLOAD] submit debug failed', err);
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
                        console.log('[HRM-UPLOAD] raw response (file ' + (i+1) + '):', txt);
                        try {
                            const json = JSON.parse(txt);
                            console.log('[HRM-UPLOAD] parsed json (file ' + (i+1) + '):', json);
                            if ( json && json.success ) {
                                anySuccess = true;
                            } else {
                                console.warn('[HRM-UPLOAD] upload returned error for file ' + (i+1), json);
                            }
                        } catch (e) {
                            // non-json
                            console.warn('[HRM-UPLOAD] non-JSON response for file ' + (i+1));
                            anySuccess = true;
                        }
                    } catch (err) {
                        console.error('[HRM-UPLOAD] fetch error for file ' + (i+1) + ':', err);
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
            console.log('[HRM-UPLOAD] raw response:', data);

            // Si es JSON, procesarlo
            try {
                const json = JSON.parse(data);
                console.log('[HRM-UPLOAD] parsed json:', json);
                if ( json.success ) {
                    showUploadMessage('Documentos subidos correctamente', 'success');
                    console.log('[HRM-UPLOAD] upload success, results:', json.data || json);
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
                    console.warn('[HRM-UPLOAD] upload returned error', json);
                    showUploadMessage('Error: ' + (json.data && json.data.message ? json.data.message : (json.message || 'Error desconocido')), 'error');
                }
            } catch(e) {
                // Si no es JSON, asumir que es una redirección exitosa (o HTML devuelto)
                console.warn('[HRM-UPLOAD] non-JSON response, assuming success or redirect. Preview:', data && data.slice ? data.slice(0,500) : data);
                showUploadMessage('Documentos subidos correctamente', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
            
                submitButton.disabled = false;
            submitButton.innerHTML = '<span class="dashicons dashicons-upload"></span> Subir Documentos';
        })
        .catch(error => {
            console.error('[HRM-UPLOAD] fetch error:', error);
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
