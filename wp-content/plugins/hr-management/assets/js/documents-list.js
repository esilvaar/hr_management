/**
 * Manejo de lista de documentos del empleado
 */

document.addEventListener('DOMContentLoaded', function() {
    loadEmployeeDocuments();
    setupCategoryFilters();
    setupYearFilter();
});

/**
 * Cargar documentos del empleado via AJAX
 */
function loadEmployeeDocuments() {
    const container = document.getElementById( 'hrm-documents-container' );
    
    if ( ! container || ! hrmDocsListData ) {
        return;
    }

    const data = {
        action: 'hrm_get_employee_documents',
        employee_id: hrmDocsListData.employeeId,
        nonce: hrmDocsListData.nonce,
        doc_type: 'all'
    };

    fetch( hrmDocsListData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams( data )
    })
    .then( response => response.json() )
    .then( data => {
        if ( data.success ) {
            container.innerHTML = data.data;
            setupDeleteButtons();
            setupYearFilter(); // <-- Reconfigura el filtro de año después de cargar los documentos
        } else {
            container.innerHTML = '<p class="text-danger">Error: ' + (data.data.message || 'Error desconocido') + '</p>';
        }
    })
    .catch( error => {
        console.error( 'Error:', error );
        container.innerHTML = '<p class="text-danger">Error al cargar documentos</p>';
    });
}

/**
 * Configurar filtros por categoría
 */
function setupCategoryFilters() {
    const buttons = document.querySelectorAll('.hrm-doc-category-btn');
    
    buttons.forEach( button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            
            // Remover clase active de todos
            buttons.forEach( b => b.classList.remove('active') );
            
            // Agregar clase active al clickeado
            if ( category !== '' ) {
                this.classList.add('active');
            }
            
            filterDocumentsByCategory( category );
        });
    });
}

/**
 * Filtrar documentos por categoría
 */
function filterDocumentsByCategory( category ) {
    const container = document.getElementById('hrm-documents-container');
    const rows = container.querySelectorAll('tbody tr');
    
    rows.forEach( row => {
        const rowType = row.getAttribute('data-type');
        
        if ( category === '' || rowType === category.toLowerCase() ) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/**
 * Configurar filtro de años
 */
function setupYearFilter() {
    const searchInput = document.getElementById('hrm-doc-year-filter-search');
    const itemsContainer = document.getElementById('hrm-doc-year-filter-items');
    
    if ( ! searchInput || ! itemsContainer ) return;
    
    // Obtener años únicos de los documentos
    const container = document.getElementById('hrm-documents-container');
    const rows = container.querySelectorAll('tbody tr');
    const years = new Set();
    
    rows.forEach( row => {
        const year = row.getAttribute('data-year');
        if ( year ) years.add( year );
    });
    
    const sortedYears = Array.from(years).sort().reverse();
    
    // Mostrar lista cuando el usuario hace click
    searchInput.addEventListener('focus', function() {
        populateYearItems( sortedYears, itemsContainer );
        itemsContainer.style.display = 'block';
    });
    
    // Filtrar mientras escribe
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        populateYearItems( 
            sortedYears.filter( year => year.includes( query ) ), 
            itemsContainer 
        );
        itemsContainer.style.display = 'block';
    });
    
    // Cerrar al hacer click fuera (no cerrar si se clica dentro del listado de items)
    document.addEventListener('click', function(e) {
        const target = e.target;
        if ( target !== searchInput && !itemsContainer.contains( target ) && target !== itemsContainer ) {
            itemsContainer.style.display = 'none';
        }
    });
}

/**
 * Poblar items de año
 */
function populateYearItems( years, container ) {
    container.innerHTML = '';
    
    if ( years.length === 0 ) {
        container.innerHTML = '<div class="p-2">No hay años disponibles</div>';
        return;
    }
    
    years.forEach( year => {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item py-2 px-3';
        link.textContent = year;
        link.addEventListener('click', function(e) {
            e.preventDefault();
            filterDocumentsByYear( year );
            document.getElementById('hrm-doc-year-filter-search').value = year;
            container.style.display = 'none';
        });
        container.appendChild( link );
    });
}

/**
 * Filtrar documentos por año
 */
function filterDocumentsByYear( year ) {
    const container = document.getElementById('hrm-documents-container');
    const rows = container.querySelectorAll('tbody tr');
    
    rows.forEach( row => {
        const rowYear = row.getAttribute('data-year');
        
        if ( rowYear === year ) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/**
 * Configurar botones de eliminar
 */
// ...existing code...
function setupDeleteButtons() {
    const container = document.getElementById('hrm-documents-container');
    const msgDiv = document.getElementById('hrm-documents-message');

    if ( ! container ) return;

    // Evitar adjuntar múltiples veces el mismo handler (delegación)
    if ( container.dataset.deleteHandlerAttached === '1' ) return;
    container.dataset.deleteHandlerAttached = '1';

    let pendingForm = null; // referencia al formulario que se va a eliminar

    function clearMsg(delay = 4000) {
        setTimeout(() => { if (msgDiv) msgDiv.innerHTML = ''; }, delay);
    }

    // parseo seguro de la respuesta (intenta JSON, si no devuelve texto)
    function parseAjaxResponseText(text) {
        // Intentar JSON directo
        try {
            return JSON.parse(text);
        } catch (e) {
            // Si viene HTML o warnings, devolver como texto
            return { success: false, data: (text ? text : 'Respuesta inválida del servidor') };
        }
    }

    // Manejar submit en delegación
    container.addEventListener('submit', function(e) {
        const form = e.target;
        if ( ! form || ! form.classList.contains('hrm-delete-form') ) return;
        e.preventDefault();

        if (!msgDiv) return; // si no existe el contenedor, abortar

        // Evitar duplicar el confirm si ya existe
        if (document.getElementById('hrm-delete-confirm')) return;

        // Guardar el formulario pendiente
        pendingForm = form;

        msgDiv.innerHTML = `
            <div id="hrm-delete-confirm" class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>¿Estás seguro que deseas eliminar este documento?</div>
                <div class="btn-group">
                    <button id="hrm-delete-confirm-yes" class="btn btn-sm btn-danger">Eliminar</button>
                    <button id="hrm-delete-confirm-no" class="btn btn-sm btn-secondary">Cancelar</button>
                </div>
            </div>
        `;

        const btnYes = document.getElementById('hrm-delete-confirm-yes');
        const btnNo  = document.getElementById('hrm-delete-confirm-no');

        const cleanup = () => {
            pendingForm = null;
            if (msgDiv) msgDiv.innerHTML = '';
        };

        btnNo.addEventListener('click', function() {
            cleanup();
        }, { once: true });

        btnYes.addEventListener('click', function() {
            if ( ! pendingForm ) return;

            // deshabilitar botones para evitar dobles envíos
            btnYes.disabled = true;
            btnNo.disabled = true;

            const data = new FormData(pendingForm);
            data.append('action', 'hrm_delete_employee_document');

            // Asegura que el nonce esté presente con el nombre correcto
            if (!data.has('nonce') && data.has('hrm_delete_nonce')) {
                data.append('nonce', data.get('hrm_delete_nonce'));
            }

            // Mostrar indicador
            msgDiv.innerHTML = '<div class="alert alert-info">Eliminando documento...</div>';

            fetch(hrmDocsListData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'manual',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: data
            })
            .then(response => {
                // Log status and type for debugging
                console.debug('Delete HTTP status:', response.status);
                console.debug('Delete content-type:', response.headers.get('content-type'));

                // Si hubo redirección (3xx) el servidor intenta redirigir; no seguirla para poder detectarla
                if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
                    console.warn('Delete response was a redirect (status ' + response.status + ')');
                    // Asumimos posible éxito y refrescamos la lista
                    msgDiv.innerHTML = '<div class="alert alert-success">Documento eliminado (respuesta: redirección). Actualizando lista...</div>';
                    if (typeof loadEmployeeDocuments === 'function') setTimeout(() => loadEmployeeDocuments(), 300);
                    clearMsg(3000);
                    pendingForm = null;
                    // Devolver una promesa resuelta para cortar el chain
                    return Promise.reject(new Error('redirect-detected'));
                }

                return response.text().then(text => ({ status: response.status, text }));
            })
            .then(({ status, text }) => {
                console.debug('Delete response:', text);
                const trimmed = (text || '').trim();

                // Si el servidor devolvió HTML (página completa), pero con 2xx, asumimos que la acción se ejecutó y refrescamos
                if ( trimmed && trimmed.charAt(0) === '<' ) {
                    console.warn('Server returned full HTML during delete, status:', status);
                    msgDiv.innerHTML = '<div class="alert alert-success">Documento eliminado. Actualizando lista...</div>';
                    if (typeof loadEmployeeDocuments === 'function') setTimeout(() => loadEmployeeDocuments(), 300);
                    clearMsg(3000);
                    pendingForm = null;
                    return;
                }

                const parsed = parseAjaxResponseText(text);
                if (parsed.success) {
                    msgDiv.innerHTML = '<div class="alert alert-success">Documento eliminado correctamente.</div>';

                    // Eliminar fila en el DOM inmediatamente para feedback instantáneo
                    const row = pendingForm.closest('tr');
                    if (row) {
                        row.parentNode.removeChild(row);
                    }

                    // Re-ejecutar filtros/contadores si es necesario
                    if (typeof setupYearFilter === 'function') setupYearFilter();

                    // También recargar la lista completa si lo deseas (opcional)
                    if (typeof loadEmployeeDocuments === 'function') {
                        // Esperar un pequeño delay para evitar condición de carrera
                        setTimeout(() => { loadEmployeeDocuments(); }, 300);
                    }
                } else {
                    // mostrar mensaje de error (manejar string u objeto)
                    let msg = 'Error desconocido';
                    if (parsed.data) {
                        if (typeof parsed.data === 'string') msg = parsed.data;
                        else if (parsed.data.message) msg = parsed.data.message;
                        else msg = JSON.stringify(parsed.data);
                    }
                    msgDiv.innerHTML = '<div class="alert alert-danger">Error: ' + msg + '</div>';
                }
                clearMsg();
                pendingForm = null;
            })
            .catch(error => {
                // Ignorar redirect-detected interno (ya manejado)
                if ( error && error.message === 'redirect-detected' ) return;
                console.error('Delete error:', error);
                msgDiv.innerHTML = '<div class="alert alert-danger">Error al eliminar documento.</div>';
                clearMsg();
                pendingForm = null;
            });
        }, { once: true });
    });
}
// ...existing code...
