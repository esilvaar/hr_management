/**
 * ============================================================
 * SIDEBAR - NOTIFICACIONES (DOT)
 * ============================================================
 * Marca solicitudes como vistas cuando el usuario despliega
 * el menú "Gestión de Vacaciones"
 */

(function() {
    'use strict';

    const detailsElement = document.getElementById('hrmVacacionesDetails');
    const dotElement = document.getElementById('hrmNotificationDot');
    
    if (!detailsElement) {
        return; // No hay menú de vacaciones
    }

    /**
     * Marca como visto y oculta el dot
     */
    function marcarComoVisto() {
        if (typeof ajaxurl === 'undefined') {
            console.error('HRM: ajaxurl no está definido');
            return;
        }

        // Ocultar el dot inmediatamente (UX optimista)
        if (dotElement) {
            dotElement.style.opacity = '0';
            dotElement.style.transform = 'translateY(-50%) scale(0)';
            setTimeout(() => {
                if (dotElement.parentNode) {
                    dotElement.remove();
                }
            }, 300);
        }

        // Llamar al backend para persistir
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'hrm_marcar_solicitudes_vistas'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('HRM: Error al marcar como visto', data);
            }
        })
        .catch(error => {
            console.error('HRM: Error en la petición AJAX', error);
        });
    }

    /**
     * Detectar cuando se abre el details
     */
    detailsElement.addEventListener('toggle', function() {
        if (detailsElement.open && dotElement) {
            marcarComoVisto();
        }
    });

})();

