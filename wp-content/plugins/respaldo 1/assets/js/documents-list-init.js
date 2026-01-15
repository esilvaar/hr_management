/**
 * Inicialización de la lista de documentos
 * Este archivo se carga solo cuando sea necesario
 */

(function() {
    // Esperar a que el DOM esté listo
    if ( document.readyState === 'loading' ) {
        document.addEventListener('DOMContentLoaded', initDocumentsList);
    } else {
        initDocumentsList();
    }

    function initDocumentsList() {
        // Verificar que exista el contenedor
        const container = document.getElementById('hrm-documents-container');
        if ( ! container ) return;

        // Cargar documentos via AJAX
        loadEmployeeDocuments();
    }
})();

/**
 * Cargar documentos del empleado
 */
function loadEmployeeDocuments() {
    const container = document.getElementById('hrm-documents-container');
    
    if ( ! container || ! window.hrmDocsListData ) {
        return;
    }

    const data = {
        action: 'hrm_get_employee_documents',
        employee_id: window.hrmDocsListData.employeeId,
        nonce: window.hrmDocsListData.nonce,
        doc_type: 'all'
    };

    const params = new URLSearchParams(data);

    fetch(window.hrmDocsListData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            container.innerHTML = data.data;
            setupDeleteButtons();
        } else {
            const message = data.data && data.data.message ? data.data.message : 'Error desconocido';
            container.innerHTML = '<p class="text-danger">Error: ' + message + '</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<p class="text-danger">Error al cargar documentos</p>';
    });
}

/**
 * Configurar botones de eliminar
 */
function setupDeleteButtons() {
    const forms = document.querySelectorAll('.hrm-delete-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('¿Estás seguro que deseas eliminar este documento?')) {
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'hrm_delete_employee_document');
            formData.append('nonce', window.hrmDocsListData.nonce);
            
            fetch(window.hrmDocsListData.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadEmployeeDocuments();
                } else {
                    const message = data.data && data.data.message ? data.data.message : 'Error desconocido';
                    alert('Error: ' + message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar documento');
            });
        });
    });
}
