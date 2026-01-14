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
    
    // Seleccionar tipo
    const items = itemsContainer.querySelectorAll('.hrm-tipo-item');
    items.forEach( item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const tipo = this.getAttribute('data-tipo');
            searchInput.value = tipo;
            hiddenInput.value = tipo;
            itemsContainer.style.display = 'none';
        });
    });
    
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
    if ( ! form ) return;
    
    const submitButton = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validar campos requeridos
        const tipoInput = document.getElementById('hrm_tipo_documento');
        const anioInput = document.getElementById('hrm_anio_documento');
        const filesInput = document.getElementById('hrm_archivos_subidos');
        
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
        
        // Enviar formulario
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Subiendo...';
        
        const formData = new FormData(form);
        
        fetch(form.action || '', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Si es JSON, procesarlo
            try {
                const json = JSON.parse(data);
                if ( json.success ) {
                    showUploadMessage('Documentos subidos correctamente', 'success');
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
                    showUploadMessage('Error: ' + json.data.message, 'error');
                }
            } catch(e) {
                // Si no es JSON, asumir que es una redirección exitosa
                showUploadMessage('Documentos subidos correctamente', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
            
            submitButton.disabled = false;
            submitButton.innerHTML = '<span class="dashicons dashicons-upload"></span> Subir Documentos';
        })
        .catch(error => {
            console.error('Error:', error);
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
