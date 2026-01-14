/**
 * Employee Selector - Bootstrap Dropdown con búsqueda
 * Permite filtrar y seleccionar empleados con búsqueda en tiempo real
 */

(function () {
    'use strict';

    function initEmployeeSelector() {
        const searchInput = document.getElementById( 'hrm-employee-search' );
        const employeeItems = document.querySelectorAll( '.hrm-employee-item' );
        const btn = document.getElementById( 'hrm-employee-selector-btn' );

        if ( ! searchInput || ! employeeItems.length ) return;

        // Filtrar empleados mientras se escribe
        searchInput.addEventListener( 'input', function () {
            const query = this.value.toLowerCase().trim();

            employeeItems.forEach( function ( item ) {
                const searchText = item.dataset.employeeSearch;

                if ( query === '' || searchText.indexOf( query ) !== -1 ) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Enfocar el input al abrir el dropdown
        const dropdownMenu = document.querySelector( '.hrm-employee-selector .dropdown' );
        if ( dropdownMenu ) {
            dropdownMenu.addEventListener( 'shown.bs.dropdown', function () {
                searchInput.focus();
                searchInput.select();
            });
        }

        // Actualizar botón cuando se selecciona un empleado
        employeeItems.forEach( function ( item ) {
            item.addEventListener( 'click', function ( e ) {
                const employeeName = this.dataset.employeeName;
                btn.textContent = employeeName;
            });
        });

        // Limpiar búsqueda al cerrar dropdown
        dropdownMenu.addEventListener( 'hidden.bs.dropdown', function () {
            searchInput.value = '';
            employeeItems.forEach( function ( item ) {
                item.style.display = '';
            });
        });
    }

    // Ejecutar cuando DOM esté listo
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initEmployeeSelector );
    } else {
        initEmployeeSelector();
    }
})();

