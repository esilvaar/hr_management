(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const desactivarPanel = document.getElementById('hrm-desactivar-panel');
        const desactivarMsg = document.getElementById('hrm-desactivar-msg');
        let empleadoId = null;
        let empleadoNombre = '';

        // Abrir modal de desactivación
        document.querySelectorAll('.btn-desactivar-empleado').forEach(btn => {
            btn.addEventListener('click', function() {
                empleadoId = this.getAttribute('data-id');
                empleadoNombre = this.getAttribute('data-nombre');
                desactivarMsg.innerHTML = `<strong>¿Seguro que deseas desactivar a <span class='text-danger'>${empleadoNombre}</span>?<br>Esta acción bloqueará su acceso.</strong>`;
                desactivarPanel.style.display = 'block';
            });
        });

        // Cerrar modal - botón X
        const btnCerrar = document.getElementById('btn-cerrar-desactivar');
        if (btnCerrar) {
            btnCerrar.onclick = function() {
                desactivarPanel.style.display = 'none';
            };
        }

        // Cerrar modal - botón Cancelar
        const btnCancelar = document.getElementById('btn-cancelar-desactivar');
        if (btnCancelar) {
            btnCancelar.onclick = function() {
                desactivarPanel.style.display = 'none';
            };
        }

        // Confirmar desactivación
        const btnConfirmar = document.getElementById('btn-confirmar-desactivar');
        if (btnConfirmar) {
            btnConfirmar.onclick = function() {
                if (!empleadoId) return;
                desactivarMsg.innerHTML = `<span class='text-success'>Desactivando empleado...</span>`;
                
                // Simulación de AJAX
                setTimeout(function() {
                    desactivarMsg.innerHTML = `<span class='text-success'>Empleado desactivado correctamente.</span>`;
                    setTimeout(function() { 
                        desactivarPanel.style.display = 'none'; 
                        location.reload(); 
                    }, 1200);
                }, 1000);
            };
        }
    });
})();
