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

        // Cargar documentos via AJAX (delegado para evitar race conditions si el core no se ha cargado)
        initLoadEmployeeDocumentsDelegate();
    }
})();

// Delegado: evitar duplicar la implementación real en este archivo. Las funciones principales
// `loadEmployeeDocuments` y `setupDeleteButtons` deben estar en `assets/js/documents-list.js`.
// Aquí solo intentamos invocar la implementación principal si está disponible.

/**
 * Delegated loader: if the primary implementation is available, call it; otherwise wait.
 */
function initLoadEmployeeDocumentsDelegate() {
    if ( typeof window.loadEmployeeDocuments === 'function' ) {
        window.loadEmployeeDocuments();
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            if ( typeof window.loadEmployeeDocuments === 'function' ) {
                window.loadEmployeeDocuments();
            }
        });
    }
}

// Ejecutar el delegado para iniciar la carga de documentos
initLoadEmployeeDocumentsDelegate();

/**
 * Nota: el manejo de los botones de eliminar se implementa en `documents-list.js` para evitar
 * comportamientos duplicados y uso de alert/confirm en distintos scripts.
 */
