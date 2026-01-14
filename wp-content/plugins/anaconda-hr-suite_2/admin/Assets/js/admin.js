/**
 * Anaconda HR Suite - Admin JavaScript
 */

(function ($) {
    'use strict';

    // Mostrar/ocultar formularios de rechazo
    window.showRejectForm = function (id) {
        document.getElementById('reject-form-' + id).style.display = 'table-row';
    };

    window.hideRejectForm = function (id) {
        document.getElementById('reject-form-' + id).style.display = 'none';
    };

    // Validar formulario de empleado
    $(document).ready(function () {
        $('#employee-form').on('submit', function () {
            var rut = $.trim($('#rut').val());
            var nombre = $.trim($('#nombre').val());
            var apellido = $.trim($('#apellido').val());
            var email = $.trim($('#email').val());

            if (!rut) {
                alert('El RUT es requerido');
                return false;
            }

            if (!nombre) {
                alert('El nombre es requerido');
                return false;
            }

            if (!apellido) {
                alert('El apellido es requerido');
                return false;
            }

            if (email && !isValidEmail(email)) {
                alert('El email no es válido');
                return false;
            }

            return true;
        });
    });

    // Función para validar email
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

})(jQuery);
