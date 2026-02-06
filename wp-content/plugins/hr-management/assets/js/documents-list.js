/**
 * Manejo de lista de documentos del empleado
 */

// Dropdown helpers (Global scope)
function hrmDetachDropdown(container, anchor) {
    if ( !container || !anchor ) return;
    if ( !container._orig ) container._orig = { parent: container.parentNode, nextSibling: container.nextSibling };
    
    container.classList.add('hrm-dropdown');
    container.style.position = 'absolute';
    container.style.zIndex = 11000;
    container.style.boxSizing = 'border-box';

    const anchorInputRect = anchor.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    
    // Use max-content to allow automatic expansion only by content
    container.style.width = 'max-content';
    container.style.minWidth = anchorInputRect.width + 'px';
    container.style.maxWidth = '95vw'; 
    container.style.boxSizing = 'border-box';

    container.style.display = 'block';
    container.style.visibility = 'hidden';
    document.body.appendChild( container );

    // Force a layout calculation to get the true width of max-content
    const menuWidth = container.offsetWidth;
    
    let left = anchorInputRect.left + window.scrollX;
    
    // If it goes off the right edge, push it left
    if ( left + menuWidth > window.scrollX + viewportWidth - 16 ) {
        left = window.scrollX + viewportWidth - menuWidth - 16;
    }
    
    left = Math.max( left, window.scrollX + 8 );
    const top = anchorInputRect.bottom + 4 + window.scrollY;

    container.style.visibility = 'visible';
    container.style.left = left + 'px';
    container.style.right = 'auto';
    container.style.top = top + 'px';
    
    window.requestAnimationFrame(() => container.classList.add('hrm-dropdown--visible'));
    container.dataset.detached = '1';
}

function hrmRestoreDropdown(container) {
    if ( !container ) return;
    container.classList.remove('hrm-dropdown--visible');
    const done = () => {
        if ( container.dataset.detached === '1' && container._orig && container._orig.parent ) {
            const parent = container._orig.parent;
            const next = container._orig.nextSibling;
            parent.insertBefore( container, next );
        }
        container.style.display = 'none';
        container.style.position = '';
        container.style.left = '';
        container.style.top = '';
        container.style.width = '';
        container.style.maxWidth = '';
        container.style.boxSizing = '';
        container.style.right = '';
        delete container.dataset.detached;
        container.removeEventListener('transitionend', done);
    };
    container.addEventListener('transitionend', done, { once: true });
    setTimeout(done, 300); 
}

// Export to window immediately
window.hrmShowDropdown = hrmDetachDropdown;
window.hrmHideDropdown = hrmRestoreDropdown;

document.addEventListener('DOMContentLoaded', function() {
    loadEmployeeDocuments();
    setupTypeFilter();
    setupYearFilter();
    setupFilterClearButtons();
    setupGlobalClickOutside();
});

function setupGlobalClickOutside() {
    document.addEventListener('click', function(e) {
        const dropdowns = [
            { input: 'hrm-doc-type-filter-search', items: 'hrm-doc-type-filter-items' },
            { input: 'hrm-doc-year-filter-search', items: 'hrm-doc-year-filter-items' },
            { input: 'hrm-tipo-search', items: 'hrm-tipo-items' },
            { input: 'hrm-anio-search', items: 'hrm-anio-items' }
        ];

        dropdowns.forEach( d => {
            const input = document.getElementById(d.input);
            const items = document.getElementById(d.items);
            
            if ( !items || items.style.display === 'none' ) return;

            // If input exists, check if click is outside both input and items
            if ( input ) {
                if ( !input.contains(e.target) && !items.contains(e.target) ) {
                    if ( typeof window.hrmHideDropdown === 'function' ) {
                        window.hrmHideDropdown(items);
                    } else {
                        items.style.display = 'none';
                        items.classList.remove('hrm-dropdown--visible');
                    }
                }
            } else {
                // If no input, just check items
                if ( !items.contains(e.target) ) {
                    if ( typeof window.hrmHideDropdown === 'function' ) {
                        window.hrmHideDropdown(items);
                    } else {
                        items.style.display = 'none';
                    }
                }
            }
        });
    });
}

function setupFilterClearButtons() {
    // per-filter clear buttons
    document.querySelectorAll('.hrm-filter-clear').forEach( btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-filter');
            if ( target === 'type' ) {
                const input = document.getElementById('hrm-doc-type-filter-search');
                const hid = document.getElementById('hrm-doc-filter-type-id');
                const items = document.getElementById('hrm-doc-type-filter-items');
                if ( input ) input.value = '';
                if ( hid ) hid.value = '';
                if ( items ) items.style.display = 'none';
                if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
            } else if ( target === 'year' ) {
                const input = document.getElementById('hrm-doc-year-filter-search');
                const hid = document.getElementById('hrm-doc-filter-year');
                const items = document.getElementById('hrm-doc-year-filter-items');
                if ( input ) input.value = '';
                if ( hid ) hid.value = '';
                if ( items ) items.style.display = 'none';
                if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
            }
        });
    });

    const clearAll = document.getElementById('hrm-doc-filters-clear-all');
    if ( clearAll ) {
        clearAll.addEventListener('click', function() {
            const typeInput = document.getElementById('hrm-doc-type-filter-search');
            const yearInput = document.getElementById('hrm-doc-year-filter-search');
            const typeHidden = document.getElementById('hrm-doc-filter-type-id');
            const yearHidden = document.getElementById('hrm-doc-filter-year');
            if ( typeInput ) typeInput.value = '';
            if ( yearInput ) yearInput.value = '';
            if ( typeHidden ) typeHidden.value = '';
            if ( yearHidden ) yearHidden.value = '';
            if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
        });
    }
}

/**
 * Cargar documentos del empleado via AJAX
 */
function loadEmployeeDocuments() {
    const container = document.getElementById( 'hrm-documents-container' );
    const msgDiv = document.getElementById( 'hrm-documents-message' );
    
    if ( ! container || ! hrmDocsListData ) {
        return;
    }

    // Limpiar mensajes previos
    if ( msgDiv ) {
        msgDiv.innerHTML = '';
    }

    // Si no hay employeeId válido, mostrar una alerta grande y no hacer la petición AJAX
    const employeeId = hrmDocsListData && hrmDocsListData.employeeId ? hrmDocsListData.employeeId : '';
    if ( ! employeeId ) {
        container.innerHTML = '<div class="alert alert-warning text-center myplugin-alert-big"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
        return;
    }

    const data = {
        action: 'hrm_get_employee_documents',
        employee_id: employeeId,
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
            // Renderizado del HTML en el cliente para mejor mantenibilidad
            container.innerHTML = renderDocumentsTable( data.data );
            
            // Limpiar mensaje después de renderizar
            if ( msgDiv ) {
                msgDiv.innerHTML = '';
            }
            
            setupDeleteButtons();
            // Reset del flag para permitir reinicialización de selección múltiple
            container.dataset.multipleSelectionSetup = '0';
            attachCheckboxListeners();
            setupYearFilter(); // <-- Reconfigura el filtro de año después de cargar los documentos
            setupTypeFilter(); // reconfigurar filtro de tipo después de cargar
            if ( typeof applyActiveFilters === 'function' ) applyActiveFilters(); // aplicar filtros combinados si hay alguno activo
            // Ensure a sensible default year is selected (most recent) when documents load
            if ( typeof window.hrmEnsureDefaultYearSelection === 'function' ) window.hrmEnsureDefaultYearSelection();
            // Inicializar menús de acciones personalizados
            if ( typeof setupActionMenus === 'function' ) setupActionMenus();
        } else {
            const message = (data.data && data.data.message) ? data.data.message : 'Error desconocido';

            // Si el error está relacionado con ID de empleado o ausencia de empleado, mostrar alerta grande y mantener botón deshabilitado
            if ( /ID de empleado inválido|Empleado #|Empleado no encontrado/i.test( message ) ) {
                const bigMsg = '<div class="alert alert-warning text-center myplugin-alert-big"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
                container.innerHTML = bigMsg;
                // Además, intentar deshabilitar el botón si existe
                const btnNuevo = document.getElementById('btn-nuevo-documento');
                if ( btnNuevo ) {
                    btnNuevo.setAttribute('disabled', 'disabled');
                    btnNuevo.setAttribute('aria-disabled', 'true');
                    btnNuevo.dataset.hasEmployee = '0';
                    btnNuevo.dataset.employeeId = '';
                }
                // Limpiar cualquier mensaje pequeño
                if ( msgDiv ) msgDiv.innerHTML = '';
            } else {
                // Mensaje de error genérico (mantener estilo fiel al original)
                container.innerHTML = '<p class="text-danger">Error: ' + message + '</p>';
            }
        }
    })
    .catch( error => {
        console.error( 'Error:', error );
        container.innerHTML = '<p class="text-danger">Error al cargar documentos</p>';
    });
}
window.loadEmployeeDocuments = loadEmployeeDocuments;

/**
 * Renderizado de la tabla de documentos (Frontend-side rendering)
 * Movido desde ajax.php para facilitar el mantenimiento del HTML
 */
function renderDocumentsTable( data ) {
    if ( ! data.documents || data.documents.length === 0 ) {
        return `
            <div class="hrm-no-docs-container">
                <div class="hrm-no-docs-inner">
                    <h3 class="hrm-no-docs-title">
                        <strong>⚠️ Sin documentos:</strong> Este empleado no tiene documentos registrados.
                    </h3>
                </div>
            </div>`;
    }

    let rows = '';
    data.documents.forEach( doc => {
        let actionsHtml = `<a class="d-block px-3 py-2 hrm-action-download" href="${doc.url}" target="_blank" rel="noopener noreferrer">Descargar</a>`;
        
        if ( data.can_delete ) {
            actionsHtml += `
                <div class="hrm-actions-sep"></div>
                <form method="post" class="hrm-delete-form m-0 p-0">
                    <input type="hidden" name="hrm_delete_nonce" value="${data.delete_nonce}">
                    <input type="hidden" name="hrm_action" value="delete_document">
                    <input type="hidden" name="doc_id" value="${doc.id}">
                    <input type="hidden" name="employee_id" value="${data.employee_id}">
                    <button type="submit" class="d-block w-100 text-start px-3 py-2 text-danger hrm-delete-btn">Eliminar</button>
                </form>`;
        }

        const tipoDisplay = doc.tipo ? (doc.tipo.charAt(0).toUpperCase() + doc.tipo.slice(1)) : '—';

        rows += `
            <tr class="align-middle" data-type="${doc.tipo.toLowerCase()}" data-type-id="${doc.tipo_id}" data-year="${doc.anio}" data-doc-id="${doc.id}">
                <td class="align-middle text-center">
                    <input type="checkbox" class="hrm-doc-checkbox" value="${doc.id}">
                </td>
                <td class="align-middle">${doc.anio}</td>
                <td class="align-middle"><small class="text-muted">${tipoDisplay}</small></td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <span class="dashicons dashicons-media-document text-secondary" aria-hidden="true"></span>
                        <div class="d-flex flex-column text-start">
                            <strong>${doc.nombre}</strong>
                            <small class="text-muted">${doc.fecha}</small>
                        </div>
                    </div>
                </td>
                <td class="text-end">
                    <div class="d-inline-flex align-items-center hrm-gap-6">
                        <div class="hrm-actions-dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary hrm-actions-toggle" aria-expanded="false" aria-controls="hrm-actions-menu-${doc.id}" title="Acciones">
                                <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                                <span class="visually-hidden">Acciones</span>
                            </button>
                            <div id="hrm-actions-menu-${doc.id}" class="hrm-actions-menu">
                                ${actionsHtml}
                            </div>
                        </div>
                    </div>
                </td>
            </tr>`;
    });

    return `
        <div class="hrm-documents-wrapper">
            <div class="d-flex gap-2 mb-3 align-items-center justify-content-between">
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" id="hrm-delete-selected-btn" class="btn btn-danger btn-sm" style="display: none !important;">
                        <span class="dashicons dashicons-trash" style="margin-right: 4px;"></span>
                        Eliminar Seleccionados (<span id="hrm-selected-count">0</span>)
                    </button>
                    <span id="hrm-selection-info" class="text-muted small" style="display: none;"></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm mb-0 hrm-documents-table">
                    <thead class="table-dark small">
                        <tr>
                            <th class="hrm-th-checkbox text-center" style="width: 50px;">
                                <input type="checkbox" id="hrm-doc-select-all" title="Seleccionar todos">
                            </th>
                            <th class="hrm-th-year">Año</th>
                            <th class="hrm-th-type">Tipo</th>
                            <th>Archivo</th>
                            <th class="hrm-th-actions text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="hrm-document-list">
                        ${rows}
                    </tbody>
                </table>
            </div>
        </div>`;
}

/**
 * Configurar filtros por categoría
 */
function setupTypeFilter() {
    const searchInput = document.getElementById('hrm-doc-type-filter-search');
    const itemsContainer = document.getElementById('hrm-doc-type-filter-items');

    if ( ! searchInput || ! itemsContainer ) return;

    // Prevent multiple handler attachments when called repeatedly
    if ( itemsContainer.dataset.hrmTypeFilterAttached === '1' ) return;
    itemsContainer.dataset.hrmTypeFilterAttached = '1';

    // Source of types: hrmDocsListData.types if available, otherwise derive from document rows
    function getTypesSource() {
        // Prefer types derived from rendered document rows (ensures only types with documents are shown)
        const containerRows = document.getElementById('hrm-documents-container');
        const rows = containerRows ? containerRows.querySelectorAll('tbody tr') : [];
        const set = new Map();
        rows.forEach( r => {
            const id = (r.getAttribute('data-type-id') || '').trim();
            const name = (r.getAttribute('data-type') || '').trim();
            if ( id || name ) set.set( id || name, { id: id || '', name: name || id } );
        });
        if ( set.size ) {
            return Array.from( set.values() );
        }
        // Fallback to global types if present (may include types without documents)
        if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) && hrmDocsListData.types.length ) {
            return hrmDocsListData.types.map( t => ({ id: t.id, name: t.name }) );
        }
        return [];
    }

    function populateItems( types ) {
        itemsContainer.innerHTML = '';
        if ( ! types.length ) {
            itemsContainer.innerHTML = '<div class="p-2">No hay tipos disponibles</div>';
            return;
        }

        // 'Todos' option
        const todos = document.createElement('a');
        todos.href = '#';
        todos.className = 'dropdown-item py-2 px-3 hrm-doc-type-item';
        todos.setAttribute('data-type-id', '');
        todos.setAttribute('data-type-name', '(Todos)');
        todos.textContent = '(Todos)';

        // Mark active if no specific type selected
        try {
            const curH = document.getElementById('hrm-doc-filter-type-id');
            const curV = document.getElementById('hrm-doc-type-filter-search');
            const curVal = (curH && curH.value) ? curH.value : (curV ? curV.value : '');
            if ( ! curVal || curVal === '(Todos)' ) todos.classList.add('active');
        } catch(e){}

        todos.addEventListener('click', function(e) {
            e.preventDefault();
            searchInput.value = '(Todos)';
            const hid = document.getElementById('hrm-doc-filter-type-id'); if ( hid ) hid.value = '';
            // restore if detached
            if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
            else itemsContainer.style.display = 'none';
            // update default year selection scoped to selected type
            const currentTypeHidden = document.getElementById('hrm-doc-filter-type-id');
            const currentTypeInput = document.getElementById('hrm-doc-type-filter-search');
            const typeVal = (currentTypeHidden && currentTypeHidden.value) ? currentTypeHidden.value : (currentTypeInput ? currentTypeInput.value : '');
            if ( typeof window.hrmEnsureDefaultYearSelection === 'function' ) window.hrmEnsureDefaultYearSelection(typeVal);
            if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
        });
        itemsContainer.appendChild( todos );

        types.forEach( t => {
            const link = document.createElement('a');
            link.href = '#';
            link.className = 'dropdown-item py-2 px-3 hrm-doc-type-item';
            link.setAttribute('data-type-id', t.id );
            link.setAttribute('data-type-name', t.name );
            link.textContent = t.name || t.id;

            // Mark active if matches current filter
            try {
                const curH = document.getElementById('hrm-doc-filter-type-id');
                const curV = document.getElementById('hrm-doc-type-filter-search');
                if ( (curH && String(curH.value) === String(t.id)) || (curV && curV.value === t.name) ) {
                    link.classList.add('active');
                }
            } catch(e){}

            link.addEventListener('click', function( e ) {
                e.preventDefault();
                // Set visible input and hidden id, then apply combined filters
                searchInput.value = t.name || t.id;
                const hid = document.getElementById('hrm-doc-filter-type-id');
                if ( hid ) hid.value = t.id || '';
                // restore dropdown to avoid leaving it detached
                if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
                else itemsContainer.style.display = 'none';
                // Seleccionar automáticamente el año más reciente para este tipo
                if ( typeof window.hrmEnsureDefaultYearSelection === 'function' ) {
                    window.hrmEnsureDefaultYearSelection(t.id || t.name);
                }
                if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
            });
            itemsContainer.appendChild( link );
        });

    }

    // Mostrar lista al focus
    searchInput.addEventListener('focus', function() {
        const types = getTypesSource();
        populateItems( types );
        // detach to body to avoid layout shift
        if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
    });

    // Filtrar mientras escribe
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const types = getTypesSource().filter( t => (t.name || '').toLowerCase().includes( query ) || String(t.id).includes( query ) );
        populateItems( types );
        if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
    });

    // Cerrar con tecla Escape
    searchInput.addEventListener('keydown', function(e) {
        if ( e.key === 'Escape' || e.keyCode === 27 ) {
            e.preventDefault();
            if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
            else itemsContainer.style.display = 'none';
            searchInput.blur();
        }
    });
}

function filterDocumentsByType( idOrName ) {
    // Keep for backward compatibility: set hidden and input and apply combined filters
    const hid = document.getElementById('hrm-doc-filter-type-id');
    const input = document.getElementById('hrm-doc-type-filter-search');
    if ( hid ) hid.value = idOrName || '';
    if ( input && idOrName && String(idOrName) !== '(Todos)') input.value = (typeof idOrName === 'string' && isNaN(idOrName)) ? idOrName : input.value;
    // If no year selected, default to most recent available
    const yearHidden = document.getElementById('hrm-doc-filter-year');
    if ( yearHidden && ! yearHidden.value ) {
        if ( typeof window.hrmEnsureDefaultYearSelection === 'function' ) window.hrmEnsureDefaultYearSelection();
    }
    if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
}

/**
 * Configurar filtro de años
 */
function setupYearFilter() {
    const searchInput = document.getElementById('hrm-doc-year-filter-search');
    const itemsContainer = document.getElementById('hrm-doc-year-filter-items');
    
    if ( ! searchInput || ! itemsContainer ) return;
    
    // Prevent attaching multiple times
    if ( itemsContainer.dataset.hrmYearFilterAttached === '1' ) return;
    itemsContainer.dataset.hrmYearFilterAttached = '1';

    // Obtener años únicos de los documentos (dinámico)
    function getYearsSource() {
        const container = document.getElementById('hrm-documents-container');
        const rows = container ? container.querySelectorAll('tbody tr') : [];
        const years = new Set();
        rows.forEach( row => {
            const year = row.getAttribute('data-year');
            if ( year ) years.add( year );
        });
        return Array.from(years).sort().reverse();
    }

    let sortedYears = getYearsSource();

    // Ensure default year selection (exposed globally)
    // Accepts an optional typeIdOrName to restrict available years to that type
    function ensureDefaultYearSelection( typeIdOrName ) {
        const hid = document.getElementById('hrm-doc-filter-year');
        const input = document.getElementById('hrm-doc-year-filter-search');
        if ( ! hid ) return;

        // Compute years from rows, optionally filtered by type
        const container = document.getElementById('hrm-documents-container');
        const rows = container ? Array.from(container.querySelectorAll('tbody tr')) : [];
        const years = [];
        rows.forEach( r => {
            const rowTypeId = (r.getAttribute('data-type-id') || '').trim();
            const rowTypeName = (r.getAttribute('data-type') || '').trim().toLowerCase();
            const rowYear = (r.getAttribute('data-year') || '').trim();
            if ( !rowYear ) return;
            if ( typeIdOrName ) {
                const t = typeIdOrName.toString().toLowerCase();
                if ( String(rowTypeId) !== t && rowTypeName !== t ) return;
            }
            if ( ! years.includes(rowYear) ) years.push(rowYear);
        });
        // If none found and a type was specified, fallback to all years
        if ( typeIdOrName && years.length === 0 ) {
            const allYears = getYearsSource();
            if ( allYears && allYears.length ) {
                hid.value = allYears[0];
                if ( input ) input.value = allYears[0];
                if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
                return;
            }
        }

        years.sort().reverse();
        if ( ! years.length ) return;

        if ( ! hid.value || hid.value === '' ) {
            hid.value = years[0];
            if ( input ) input.value = years[0];
        } else {
            if ( years.indexOf(hid.value) === -1 ) {
                hid.value = years[0];
                if ( input ) input.value = years[0];
            } else {
                if ( input ) input.value = hid.value;
            }
        }
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
        // Aplicar filtros después de asegurar la selección por defecto
        if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
    }
    window.hrmEnsureDefaultYearSelection = ensureDefaultYearSelection;

    // Mostrar lista cuando el usuario hace click
    searchInput.addEventListener('focus', function() {
        sortedYears = getYearsSource();
        populateYearItems( sortedYears, itemsContainer );
        // show with fade/detach via global helper (if available)
        if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
    });
    
    // Filtrar mientras escribe
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        sortedYears = getYearsSource();
        populateYearItems( 
            sortedYears.filter( year => year.includes( query ) ), 
            itemsContainer 
        );
        if ( typeof window.hrmShowDropdown === 'function' ) window.hrmShowDropdown(itemsContainer, searchInput);
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
    });
    
    // Cerrar con tecla Escape
    searchInput.addEventListener('keydown', function(e) {
        if ( e.key === 'Escape' || e.keyCode === 27 ) {
            e.preventDefault();
            if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(itemsContainer);
            else itemsContainer.style.display = 'none';
            searchInput.blur();
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

    // 'Todos' option
    const todos = document.createElement('a');
    todos.href = '#';
    todos.className = 'dropdown-item py-2 px-3';
    todos.textContent = '(Todos)';
    // mark active if no specific year selected
    try {
        const curH = document.getElementById('hrm-doc-filter-year');
        const curV = document.getElementById('hrm-doc-year-filter-search');
        const curVal = (curH && curH.value) ? curH.value : (curV ? curV.value : '');
        if ( ! curVal || curVal === '(Todos)' ) todos.classList.add('active');
    } catch(e){}
    todos.addEventListener('click', function(e) {
        e.preventDefault();
        const input = document.getElementById('hrm-doc-year-filter-search');
        const hid = document.getElementById('hrm-doc-filter-year');
        if ( input ) input.value = '(Todos)';
        if ( hid ) hid.value = '';
        if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(container);
        else { container.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ container.style.display = 'none'; }, 220); }
        if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
    });
    container.appendChild( todos );
    
    years.forEach( year => {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item py-2 px-3';
        link.textContent = year;
        // mark active if equals current hidden or visible
        try {
            const curH = document.getElementById('hrm-doc-filter-year');
            const curV = document.getElementById('hrm-doc-year-filter-search');
            const curVal = (curH && curH.value) ? curH.value : (curV ? curV.value : '');
            if ( String(curVal) === String(year) ) link.classList.add('active');
        } catch(e){}
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const input = document.getElementById('hrm-doc-year-filter-search');
            const hid = document.getElementById('hrm-doc-filter-year');
            if ( input ) input.value = year;
            if ( hid ) hid.value = year;
            if ( typeof window.hrmHideDropdown === 'function' ) window.hrmHideDropdown(container);
            else { container.classList.remove('hrm-dropdown--visible'); setTimeout(()=>{ container.style.display = 'none'; }, 220); }
            if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
        });
        container.appendChild( link );
    });
}

/**
 * Filtrar documentos por año
 */
function filterDocumentsByYear( year ) {
    // compatibility shim: set hidden and input
    const hid = document.getElementById('hrm-doc-filter-year');
    const input = document.getElementById('hrm-doc-year-filter-search');
    if ( hid ) hid.value = year || '';
    if ( input && year && year !== '(Todos)' ) input.value = year;
    if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
}

/**
 * Aplicar filtros activos (tipo + año) de forma combinada
 */
function applyActiveFilters() {
    const typeId = (document.getElementById('hrm-doc-filter-type-id') ? document.getElementById('hrm-doc-filter-type-id').value : '').trim();
    const typeName = (document.getElementById('hrm-doc-type-filter-search') ? document.getElementById('hrm-doc-type-filter-search').value : '').trim();
    const year = (document.getElementById('hrm-doc-filter-year') ? document.getElementById('hrm-doc-filter-year').value : '').trim();

    const container = document.getElementById('hrm-documents-container');
    if ( ! container ) return;
    const rows = container.querySelectorAll('tbody tr');

    rows.forEach( row => {
        const rowTypeId = (row.getAttribute('data-type-id') || '').trim();
        const rowTypeName = (row.getAttribute('data-type') || '').trim().toLowerCase();
        const rowYear = (row.getAttribute('data-year') || '').trim();

        let typeMatch = true;
        if ( typeId ) {
            typeMatch = String(rowTypeId) === String(typeId);
        } else if ( typeName && typeName !== '(Todos)' ) {
            typeMatch = rowTypeName === typeName.toLowerCase();
        }

        let yearMatch = true;
        if ( year && year !== '(Todos)' ) {
            yearMatch = rowYear === year;
        }

        row.style.display = ( typeMatch && yearMatch ) ? '' : 'none';
    });

    // Mostrar/ocultar las X según estado de filtros
    updateFilterClearVisibility();
    
    // Actualizar UI de selección después de aplicar filtros
    if ( typeof updateSelectionUI === 'function' ) {
        updateSelectionUI();
    }
}

/**
 * Mostrar u ocultar botones de limpieza dentro de los inputs
 */
function updateFilterClearVisibility() {
    const typeInput = document.getElementById('hrm-doc-type-filter-search');
    const yearInput = document.getElementById('hrm-doc-year-filter-search');
    const typeHidden = document.getElementById('hrm-doc-filter-type-id');
    const yearHidden = document.getElementById('hrm-doc-filter-year');

    const typeClear = document.querySelector('.hrm-filter-clear[data-filter="type"]');
    const yearClear = document.querySelector('.hrm-filter-clear[data-filter="year"]');

    // Resolver lista de tipos actual (cache o derivados de filas)
    let types = [];
    if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) && hrmDocsListData.types.length ) {
        types = hrmDocsListData.types.map(t => ({ id: String(t.id), name: (t.name || '').toLowerCase() }));
    } else {
        const container = document.getElementById('hrm-documents-container');
        const rows = container ? container.querySelectorAll('tbody tr') : [];
        const set = new Map();
        rows.forEach( r => {
            const id = (r.getAttribute('data-type-id') || '').trim();
            const name = (r.getAttribute('data-type') || '').trim();
            if ( id || name ) set.set( id || name, { id: String(id || ''), name: (name || id).toLowerCase() } );
        });
        types = Array.from( set.values() );
    }

    // Resolver lista de años
    const yearsSet = new Set();
    const container = document.getElementById('hrm-documents-container');
    const rows = container ? container.querySelectorAll('tbody tr') : [];
    rows.forEach( row => { const y = (row.getAttribute('data-year') || '').trim(); if ( y ) yearsSet.add( y ); });

    // Determinar si hay filtro activo en tipo
    let typeHas = false;
    if ( typeHidden && typeHidden.value ) typeHas = true;
    else if ( typeInput && typeInput.value ) {
        const v = typeInput.value.trim();
        if ( v && v !== '(Todos)' ) {
            // Si coincide con algún nombre o id existente, consideramos seleccionado
            const lv = v.toLowerCase();
            typeHas = types.some( t => (t.id && String(t.id) === v) || (t.name && t.name === lv) );
        }
    }

    // Determinar si hay filtro activo en año
    let yearHas = false;
    if ( yearHidden && yearHidden.value ) yearHas = true;
    else if ( yearInput && yearInput.value ) {
        const v = yearInput.value.trim();
        if ( v && v !== '(Todos)' ) yearHas = yearsSet.has( v );
    }

    if ( typeClear ) typeClear.style.display = typeHas ? 'inline' : 'none';
    if ( yearClear ) yearClear.style.display = yearHas ? 'inline' : 'none';

    // Add visual feedback to active filters
    const tc = typeInput ? typeInput.closest('.hrm-filter-container') : null;
    const yc = yearInput ? yearInput.closest('.hrm-filter-container') : null;
    if ( tc ) {
        if ( typeHas ) tc.classList.add('hrm-filter-active');
        else tc.classList.remove('hrm-filter-active');
    }
    if ( yc ) {
        // Year is considered "active" if it's NOT the (Todos) option
        if ( yearHas && yearHidden.value !== '' ) yc.classList.add('hrm-filter-active');
        else yc.classList.remove('hrm-filter-active');
    }
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

    // Inicializar también los menús de acciones (3 puntos)
    if ( typeof setupActionMenus === 'function' ) setupActionMenus();

    // --- Función para gestionar menús de acciones (toggle, cerrar al click fuera, Escape)
    function setupActionMenus() {
        // Sólo añadir una vez
        if ( container.dataset.hrmActionMenus === '1' ) return;
        container.dataset.hrmActionMenus = '1';

        // Helper: abrir menú y posicionarlo en body (para evitar que sea recortado por scroll)
        function openMenuDetached(menu, toggle) {
            if ( !menu || !toggle ) return;
            // Guardar estado original para restaurar
            if ( !menu._orig ) {
                menu._orig = { parent: menu.parentNode, nextSibling: menu.nextSibling };
            }

            // Mostrar el menú temporalmente para calcular dimensiones
            menu.style.display = 'block';
            menu.style.position = 'absolute';
            menu.style.left = '0px';
            menu.style.top = '0px';
            menu.style.zIndex = 1100;

            const rect = toggle.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();

            // Calcular posición: alinear a la derecha del botón y debajo
            const viewportWidth = document.documentElement.clientWidth;
            let left = rect.right - menuRect.width + window.scrollX;
            // Ajustar para que el menú no salga de pantalla
            if ( left + menuRect.width > window.scrollX + viewportWidth - 8 ) {
                left = window.scrollX + viewportWidth - menuRect.width - 8;
            }
            left = Math.max( left, window.scrollX + 8 );
            const top = rect.bottom + 6 + window.scrollY;

            menu.style.left = left + 'px';
            menu.style.top = top + 'px';

            // Mover al body para evitar clipping por overflow
            document.body.appendChild( menu );
            menu.dataset.detached = '1';
            toggle.setAttribute('aria-expanded', 'true');
        }

        function closeMenuDetached(menu, toggle) {
            if ( !menu ) return;

            // Ocultar
            menu.style.display = 'none';

            // Si fue movido, restaurar a su lugar original
            if ( menu.dataset.detached === '1' && menu._orig && menu._orig.parent ) {
                const parent = menu._orig.parent;
                const next = menu._orig.nextSibling;
                parent.insertBefore( menu, next );
                menu.style.position = '';
                menu.style.left = '';
                menu.style.top = '';
                delete menu.dataset.detached;
            }

            if ( toggle ) toggle.setAttribute('aria-expanded', 'false');
        }

        // Toggle de menú al pulsar el botón
        container.addEventListener('click', function(e) {
            const toggle = e.target.closest('.hrm-actions-toggle');
            if ( toggle ) {
                const menuId = toggle.getAttribute('aria-controls');
                const menu = menuId ? document.getElementById(menuId) : toggle.nextElementSibling;
                if ( !menu ) return;

                // Cerrar otros menús abiertos (restaurarlos)
                document.querySelectorAll('.hrm-actions-menu').forEach( m => {
                    if ( m !== menu ) {
                        const t = document.querySelector('[aria-controls="' + m.id + '"]');
                        closeMenuDetached( m, t );
                    }
                });

                const isOpen = menu.style.display === 'block' && menu.dataset.detached === '1';
                if ( isOpen ) {
                    closeMenuDetached( menu, toggle );
                } else {
                    openMenuDetached( menu, toggle );
                }

                e.stopPropagation();
                return;
            }

            // Si clic dentro de un item del menú, dejar que el handler natural (link o form submit) actúe
        });

        // Cerrar al hacer click fuera
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.hrm-actions-menu').forEach( m => {
                const t = document.querySelector('[aria-controls="' + m.id + '"]');
                closeMenuDetached( m, t );
            } );
        });

        // Cerrar con Escape
        document.addEventListener('keydown', function(e) {
            if ( e.key === 'Escape' ) {
                document.querySelectorAll('.hrm-actions-menu').forEach( m => {
                    const t = document.querySelector('[aria-controls="' + m.id + '"]');
                    closeMenuDetached( m, t );
                } );
            }
        });

        // Cerrar menús al hacer scroll o resize para que no se queden desalineados
        window.addEventListener('scroll', function() {
            document.querySelectorAll('.hrm-actions-menu').forEach( m => {
                const t = document.querySelector('[aria-controls="' + m.id + '"]');
                closeMenuDetached( m, t );
            } );
        }, { passive: true });

        window.addEventListener('resize', function() {
            document.querySelectorAll('.hrm-actions-menu').forEach( m => {
                const t = document.querySelector('[aria-controls="' + m.id + '"]');
                closeMenuDetached( m, t );
            } );
        });
    }

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

        // Si el formulario está dentro de un menu desplegable, cerrarlo al confirmar
        function closeParentMenuIfAny(node) {
            const menu = node.closest('.hrm-actions-menu');
            if ( !menu ) return;
            // Intentar localizar el toggle asociado (por aria-controls) si el form fue movido
            const toggle = document.querySelector('[aria-controls="' + menu.id + '"]') || (node.closest('tr') ? node.closest('tr').querySelector('.hrm-actions-toggle') : null);

            // Si el menú fue movido (detached), restaurarlo
            if ( menu.dataset.detached === '1' && menu._orig && menu._orig.parent ) {
                const parent = menu._orig.parent;
                const next = menu._orig.nextSibling;
                parent.insertBefore( menu, next );
                menu.style.position = '';
                menu.style.left = '';
                menu.style.top = '';
                delete menu.dataset.detached;
            }

            // Ocultar y actualizar estado del toggle
            menu.style.display = 'none';
            if ( toggle ) toggle.setAttribute('aria-expanded', 'false');
        }

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

/**
 * Configurar funcionalidad de selección múltiple de documentos
 */
function setupMultipleSelection() {
    // Esta función ya no se usa, attachCheckboxListeners se llama directamente después de renderizar
}

function attachCheckboxListeners() {
    const container = document.getElementById('hrm-documents-container');
    const selectAllCheckbox = document.getElementById('hrm-doc-select-all');
    const deleteSelectedBtn = document.getElementById('hrm-delete-selected-btn');
    const selectedCountSpan = document.getElementById('hrm-selected-count');
    const tbody = container ? container.querySelector('tbody.hrm-document-list') : null;

    if (!selectAllCheckbox || !deleteSelectedBtn || !tbody) {
        return;
    }

    console.log('HRM: Inicializando selección múltiple');
    selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = tbody.querySelectorAll('.hrm-doc-checkbox');
        let visibleCount = 0;
        
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            const isVisible = row.style.display !== 'none';
            
            if (isVisible) {
                cb.checked = this.checked;
                visibleCount++;
            }
        });
        updateSelectionUI();
    });

    // Listener para cada checkbox individual
    tbody.addEventListener('change', function(e) {
        if (e.target.classList.contains('hrm-doc-checkbox')) {
            const checkboxes = tbody.querySelectorAll('.hrm-doc-checkbox');
            let visibleCheckboxes = [];
            let checkedCount = 0;
            let visibleCount = 0;
            
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                const isVisible = row.style.display !== 'none';
                
                if (isVisible) {
                    visibleCheckboxes.push(cb);
                    visibleCount++;
                    if (cb.checked) checkedCount++;
                }
            });

            // Actualizar estado del checkbox "seleccionar todos"
            // Solo basarse en los checkboxes visibles
            if (visibleCount > 0) {
                if (checkedCount === visibleCount) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (checkedCount > 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
            }

            updateSelectionUI();
        }
    });

    // Listener para botón de eliminar seleccionados
    deleteSelectedBtn.addEventListener('click', function() {
        const checkboxes = tbody.querySelectorAll('.hrm-doc-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Por favor selecciona al menos un documento');
            return;
        }

        const docIds = Array.from(checkboxes).map(cb => cb.value);
        const confirmMsg = `¿Estás seguro de que deseas eliminar ${docIds.length} documento(s)? Esta acción no se puede deshacer.`;

        if (confirm(confirmMsg)) {
            deleteMultipleDocuments(docIds);
        }
    });
}

function updateSelectionUI() {
    const container = document.getElementById('hrm-documents-container');
    const deleteSelectedBtn = document.getElementById('hrm-delete-selected-btn');
    const selectedCountSpan = document.getElementById('hrm-selected-count');
    const tbody = container ? container.querySelector('tbody.hrm-document-list') : null;

    if (!deleteSelectedBtn || !selectedCountSpan || !tbody) {
        return;
    }

    const checkboxes = tbody.querySelectorAll('.hrm-doc-checkbox');
    let checkedCount = 0;
    let visibleCount = 0;

    checkboxes.forEach(cb => {
        const row = cb.closest('tr');
        const isVisible = row.style.display !== 'none';
        
        if (isVisible) {
            visibleCount++;
            if (cb.checked) {
                checkedCount++;
            }
        }
    });

    selectedCountSpan.textContent = checkedCount;

    if (checkedCount > 0) {
        deleteSelectedBtn.style.display = 'inline-block';
    } else {
        deleteSelectedBtn.style.display = 'none';
    }
}

function deleteMultipleDocuments(docIds) {
    const container = document.getElementById('hrm-documents-container');
    const msgDiv = document.getElementById('hrm-documents-message');

    if (!container || !msgDiv) return;

    // Mostrar mensaje de carga
    msgDiv.innerHTML = '<div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span>Eliminando documentos...</div>';

    // Obtener nonce de los datos globales
    const nonce = hrmDocsListData ? hrmDocsListData.deleteNonce : '';

    // Crear promesas para cada eliminación
    const deletePromises = docIds.map(docId => {
        const data = {
            action: 'hrm_delete_employee_document',
            doc_id: docId,
            nonce: nonce
        };

        return fetch(hrmDocsListData.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.data ? result.data.message || 'Error desconocido' : 'Error al eliminar');
            }
            return { docId, success: true };
        })
        .catch(error => {
            console.error('Error al eliminar doc_id ' + docId, error);
            return { docId, success: false, error: error.message };
        });
    });

    // Esperar a que se completen todas las eliminaciones
    Promise.all(deletePromises)
        .then(results => {
            const successCount = results.filter(r => r.success).length;
            const failureCount = results.filter(r => !r.success).length;

            if (failureCount === 0) {
                msgDiv.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">✓ ' + successCount + ' documento(s) eliminado(s) exitosamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                let errorMsg = '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
                errorMsg += successCount + ' documento(s) eliminado(s) exitosamente. ';
                errorMsg += failureCount + ' documento(s) fallaron al eliminar.';
                errorMsg += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                msgDiv.innerHTML = errorMsg;
            }

            // Recargar inmediatamente la lista (sin delay)
            loadEmployeeDocuments();
        })
        .catch(error => {
            console.error('Error crítico al eliminar documentos:', error);
            msgDiv.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error crítico al eliminar documentos.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        });
}
