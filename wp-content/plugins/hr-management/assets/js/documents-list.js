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
        populateYearItems( Array.from(years), itemsContainer );
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
    
    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
        if ( e.target !== searchInput ) {
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
function setupDeleteButtons() {
    const forms = document.querySelectorAll('.hrm-delete-form');
    
    forms.forEach( form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if ( ! confirm('¿Estás seguro que deseas eliminar este documento?') ) {
                return;
            }
            
            const data = new FormData( this );
            data.append('action', 'hrm_delete_employee_document');
            
            fetch( hrmDocsListData.ajaxUrl, {
                method: 'POST',
                body: data
            })
            .then( response => response.json() )
            .then( data => {
                if ( data.success ) {
                    loadEmployeeDocuments();
                } else {
                    alert( 'Error: ' + data.data.message );
                }
            })
            .catch( error => {
                console.error( 'Error:', error );
                alert( 'Error al eliminar documento' );
            });
        });
    });
}
