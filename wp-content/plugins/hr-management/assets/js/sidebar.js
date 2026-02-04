(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.querySelector('.hrm-mobile-toggle');
        var overlay = document.querySelector('.hrm-sidebar-overlay');
        var body = document.body;
        var sidebar = document.getElementById('hrm-sidebar');

        function openSidebar(){
            body.classList.add('hrm-sidebar-open');
            if(btn) btn.setAttribute('aria-expanded','true');
            if(overlay) overlay.setAttribute('aria-hidden','false');
            if(sidebar){
                var first = sidebar.querySelector('a,button');
                if(first) first.focus();
            }
        }
        function closeSidebar(){
            body.classList.remove('hrm-sidebar-open');
            if(btn) btn.setAttribute('aria-expanded','false');
            if(overlay) overlay.setAttribute('aria-hidden','true');
            if(btn) btn.focus();
        }

        if(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                body.classList.contains('hrm-sidebar-open') ? closeSidebar() : openSidebar();
            });
        }
        if(overlay){
            overlay.addEventListener('click', closeSidebar);
        }
        if(sidebar){
            // Cerrar al pulsar cualquier link (en móvil)
            sidebar.querySelectorAll('.nav-link').forEach(function(el){
                el.addEventListener('click', function(){
                    if(window.innerWidth < 768) closeSidebar();
                });
            });
        }

        // Cerrar con Escape
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape' && body.classList.contains('hrm-sidebar-open')) closeSidebar();
        });

        // Asegurar que la sidebar quede cerrada al redimensionar a desktop
        window.addEventListener('resize', function(){
            if(window.innerWidth >= 768) closeSidebar();
        });

        // Comportamiento ACCORDION: solo una sección abierta a la vez
        // EXCEPCIÓN: Para empleados, todas las secciones pueden estar abiertas
        var accordionMode = sidebar ? sidebar.getAttribute('data-accordion-mode') : 'single';
        var isEmployeeRole = accordionMode === 'all-open';
        
        // Obtener TODOS los details de nivel principal (directos) en la navegación
        var navDetails = document.querySelectorAll('.hrm-nav > details');
        var profileMidDetails = document.querySelectorAll('.hrm-profile-mid > details');
        var allDetails = Array.from(navDetails).concat(Array.from(profileMidDetails));
        
        // También incluir el details de la sección "Ajustes"
        var settingsDetails = document.querySelector('.myplugin-settings');
        if(settingsDetails) allDetails.push(settingsDetails);

        // Solo aplicar comportamiento de acordeón si NO es empleado
        if(!isEmployeeRole){
            allDetails.forEach(function(details){
                details.addEventListener('toggle', function(){
                    if(this.open){
                        // Cerrar todos los otros details de nivel principal
                        allDetails.forEach(function(other){
                            if(other !== details && other.open){
                                other.open = false;
                            }
                        });
                    }
                });
            });
        }

        // Si existe una sección abierta al cargar (según la página actual),
        // asegurar que permanezca abierta incluso cuando se navegue dentro de ella
        var openDetails = sidebar.querySelector('details[open]');
        if(openDetails){
            // Esto ya está manejado por el atributo 'open' en el HTML generado por PHP
            // pero lo dejamos como referencia para futuros enhancements
        }
    });
})();
