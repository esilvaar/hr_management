/**
 * Manejo de lista de documentos del empleado
 */

document.addEventListener('DOMContentLoaded', function() {
    loadEmployeeDocuments();
    setupTypeFilter();
    setupYearFilter();
    setupFilterClearButtons();
});

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
    
    if ( ! container || ! hrmDocsListData ) {
        return;
    }

    // Si no hay employeeId válido, mostrar una alerta grande y no hacer la petición AJAX
    const employeeId = hrmDocsListData && hrmDocsListData.employeeId ? hrmDocsListData.employeeId : '';
    if ( ! employeeId ) {
        container.innerHTML = '<div class="alert alert-warning text-center" style="font-size:1.25rem; padding:2rem;"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
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
            container.innerHTML = data.data;
            setupDeleteButtons();
            setupYearFilter(); // <-- Reconfigura el filtro de año después de cargar los documentos
            setupTypeFilter(); // reconfigurar filtro de tipo después de cargar
            if ( typeof applyActiveFilters === 'function' ) applyActiveFilters(); // aplicar filtros combinados si hay alguno activo
            // Inicializar menús de acciones personalizados
            if ( typeof setupActionMenus === 'function' ) setupActionMenus();
        } else {
            const message = (data.data && data.data.message) ? data.data.message : 'Error desconocido';

            // Si el error está relacionado con ID de empleado o ausencia de empleado, mostrar alerta grande y mantener botón deshabilitado
            if ( /ID de empleado inválido|Empleado #|Empleado no encontrado/i.test( message ) ) {
                const bigMsg = '<div class="alert alert-warning text-center" style="font-size:1.25rem; padding:2rem;"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
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
                const msgDiv = document.getElementById('hrm-documents-message');
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

/**
 * Configurar filtros por categoría
 */
function setupTypeFilter() {
    const searchInput = document.getElementById('hrm-doc-type-filter-search');
    const itemsContainer = document.getElementById('hrm-doc-type-filter-items');

    if ( ! searchInput || ! itemsContainer ) return;

    // Source of types: hrmDocsListData.types if available, otherwise derive from document rows
    function getTypesSource() {
        if ( window.hrmDocsListData && Array.isArray( hrmDocsListData.types ) && hrmDocsListData.types.length ) {
            return hrmDocsListData.types.map( t => ({ id: t.id, name: t.name }) );
        }
        // Derive from rows
        const container = document.getElementById('hrm-documents-container');
        const rows = container ? container.querySelectorAll('tbody tr') : [];
        const set = new Map();
        rows.forEach( r => {
            const id = r.getAttribute('data-type-id') || '';
            const name = (r.getAttribute('data-type') || '').trim();
            if ( id || name ) set.set( id || name, { id: id || '', name: name || id } );
        });
        return Array.from( set.values() );
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
        todos.addEventListener('click', function(e) {
            e.preventDefault();
            searchInput.value = '(Todos)';
            const hid = document.getElementById('hrm-doc-filter-type-id'); if ( hid ) hid.value = '';
            itemsContainer.style.display = 'none';
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
            link.addEventListener('click', function( e ) {
                e.preventDefault();
                // Set visible input and hidden id, then apply combined filters
                searchInput.value = t.name || t.id;
                const hid = document.getElementById('hrm-doc-filter-type-id');
                if ( hid ) hid.value = t.id || '';
                itemsContainer.style.display = 'none';
                if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
            });
            itemsContainer.appendChild( link );
        });
    }

    // Mostrar lista al focus
    searchInput.addEventListener('focus', function() {
        const types = getTypesSource();
        populateItems( types );
        itemsContainer.style.display = 'block';
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
    });

    // Filtrar mientras escribe
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const types = getTypesSource().filter( t => (t.name || '').toLowerCase().includes( query ) || String(t.id).includes( query ) );
        populateItems( types );
        itemsContainer.style.display = 'block';
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
    });

    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        const target = e.target;
        if ( target !== searchInput && ! itemsContainer.contains( target ) && target !== itemsContainer ) {
            itemsContainer.style.display = 'none';
        }
    });
}

function filterDocumentsByType( idOrName ) {
    // Keep for backward compatibility: set hidden and input and apply combined filters
    const hid = document.getElementById('hrm-doc-filter-type-id');
    const input = document.getElementById('hrm-doc-type-filter-search');
    if ( hid ) hid.value = idOrName || '';
    if ( input && idOrName && String(idOrName) !== '(Todos)') input.value = (typeof idOrName === 'string' && isNaN(idOrName)) ? idOrName : input.value;
    if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
}

/**
 * Configurar filtro de años
 */
function setupYearFilter() {
    const searchInput = document.getElementById('hrm-doc-year-filter-search');
    const itemsContainer = document.getElementById('hrm-doc-year-filter-items');
    
    if ( ! searchInput || ! itemsContainer ) return;
    
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

    // Mostrar lista cuando el usuario hace click
    searchInput.addEventListener('focus', function() {
        sortedYears = getYearsSource();
        populateYearItems( sortedYears, itemsContainer );
        itemsContainer.style.display = 'block';
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
        itemsContainer.style.display = 'block';
        if ( typeof updateFilterClearVisibility === 'function' ) updateFilterClearVisibility();
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

    // 'Todos' option
    const todos = document.createElement('a');
    todos.href = '#';
    todos.className = 'dropdown-item py-2 px-3';
    todos.textContent = '(Todos)';
    todos.addEventListener('click', function(e) {
        e.preventDefault();
        const input = document.getElementById('hrm-doc-year-filter-search');
        const hid = document.getElementById('hrm-doc-filter-year');
        if ( input ) input.value = '(Todos)';
        if ( hid ) hid.value = '';
        container.style.display = 'none';
        if ( typeof applyActiveFilters === 'function' ) applyActiveFilters();
    });
    container.appendChild( todos );
    
    years.forEach( year => {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item py-2 px-3';
        link.textContent = year;
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const input = document.getElementById('hrm-doc-year-filter-search');
            const hid = document.getElementById('hrm-doc-filter-year');
            if ( input ) input.value = year;
            if ( hid ) hid.value = year;
            container.style.display = 'none';
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
