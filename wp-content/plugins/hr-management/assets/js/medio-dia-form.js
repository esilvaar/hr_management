(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Cerrar alerta (botón y backdrop)
        const alerta = document.getElementById('alertaSolicitudCreada');
        const alertaFondo = document.getElementById('alertaFondo');
        document.querySelectorAll('.hrm-success-close').forEach(function(el){
            el.addEventListener('click', function(){
                // Solo cerrar el modal, la URL ya fue limpiada por el script inline
                if (alerta && alerta.remove) alerta.remove();
                if (alertaFondo && alertaFondo.remove) alertaFondo.remove();
            });
        });

        // Botón cancelar (volver atrás)
        const btnCancelar = document.querySelector('.hrm-cancel-btn');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', function(){
                if (history && history.back) history.back();
            });
        }

        const fechaInput = document.getElementById('fecha_medio_dia');
        if (!fechaInput) return;

        // La fecha debe ser hoy o posterior
        const hoy = new Date();
        const fechaMinimaStr = hoy.toISOString().split('T')[0];
        fechaInput.min = fechaMinimaStr;

        function esFinDeSemana(fechaStr) {
            try {
                const fecha = new Date(fechaStr + 'T00:00:00');
                const dia = fecha.getDay(); // 0 = domingo, 6 = sábado
                return dia === 0 || dia === 6;
            } catch(e) {
                return false;
            }
        }

        function formatearFecha(fechaStr) {
            if (!fechaStr) return '—';
            const fecha = new Date(fechaStr + 'T00:00:00');
            return fecha.toLocaleDateString('es-CL', { year: 'numeric', month: '2-digit', day: '2-digit' });
        }

        // Función para actualizar fechas (inicio y fin son iguales)
        window.actualizarFechas = function() {
            const fecha = (document.getElementById('fecha_medio_dia') || {}).value;

            if (!fecha) {
                if (document.getElementById('fecha_display')) document.getElementById('fecha_display').textContent = '—';
                if (document.getElementById('fecha_inicio')) document.getElementById('fecha_inicio').value = '';
                if (document.getElementById('fecha_fin')) document.getElementById('fecha_fin').value = '';
                return;
            }

            if (esFinDeSemana(fecha)) {
                alert('⚠️ La fecha no puede ser un fin de semana.');
                fechaInput.value = '';
                if (typeof window.actualizarFechas === 'function') window.actualizarFechas();
                return;
            }

            if (document.getElementById('fecha_display')) document.getElementById('fecha_display').textContent = formatearFecha(fecha);
            if (document.getElementById('fecha_inicio')) document.getElementById('fecha_inicio').value = fecha;
            if (document.getElementById('fecha_fin')) document.getElementById('fecha_fin').value = fecha;
        };

        // Función para actualizar el texto del período
        window.actualizarTexto = function() {
            const selected = document.querySelector('input[name="periodo_ausencia"]:checked');
            const periodo = selected ? selected.value : 'mañana';
            if (document.getElementById('periodo_text')) document.getElementById('periodo_text').textContent = periodo;
            if (document.getElementById('periodo_display')) document.getElementById('periodo_display').textContent = periodo.charAt(0).toUpperCase() + periodo.slice(1);
        };

        // Validar fecha cuando cambia
        fechaInput.addEventListener('change', function () {
            if (esFinDeSemana(fechaInput.value)) {
                alert('⚠️ La fecha no puede ser un fin de semana.');
                fechaInput.value = '';
                if (typeof window.actualizarFechas === 'function') window.actualizarFechas();
                return;
            }

            if (typeof window.actualizarFechas === 'function') window.actualizarFechas();
        });

        // Escuchar cambios en periodo (mañana/tarde)
        document.querySelectorAll('input[name="periodo_ausencia"]').forEach(function(radio){
            radio.addEventListener('change', function(){
                if (typeof window.actualizarTexto === 'function') window.actualizarTexto();
            });
        });

    });
})();