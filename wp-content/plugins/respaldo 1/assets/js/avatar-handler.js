/**
 * Avatar Handler
 * Maneja la eliminación de avatares con confirmación
 */

(function () {
    'use strict';

    /**
     * Maneja el evento de eliminación de avatar
     * Solicita confirmación antes de enviar el formulario
     */
    function handleAvatarDelete(e) {
        const confirmed = confirm('¿Eliminar foto de perfil?');
        if (!confirmed) {
            e.preventDefault();
        }
    }

    /**
     * Inicializa los event listeners para avatares
     */
    function initAvatarHandlers() {
        const deleteBtn = document.getElementById('deleteAvatarBtn');
        
        if (deleteBtn) {
            deleteBtn.addEventListener('click', handleAvatarDelete);
        }
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAvatarHandlers);
    } else {
        initAvatarHandlers();
    }
})();
