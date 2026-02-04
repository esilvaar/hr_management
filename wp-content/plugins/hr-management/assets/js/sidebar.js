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
        // Pero no cerrar secciones que tienen un elemento activo
        var navDetails = document.querySelectorAll('.hrm-nav > details');
        var profileMidDetails = document.querySelectorAll('.hrm-profile-mid > details');
        var hrmSidebarRole = sidebar ? sidebar.dataset.hrmRole : '';
        var accordionRoles = ['administrator', 'administrador_anaconda', 'supervisor', 'editor_vacaciones'];
        var accordionTargets = Array.from(navDetails).concat(Array.from(profileMidDetails));

        if (!accordionTargets.length) {
            return;
        }

        // Función para verificar si un details tiene un enlace activo
        function hasActiveLink(details) {
            return details.querySelector('.nav-link.active, a.active') !== null;
        }

        if (hrmSidebarRole === 'empleado') {
            // Para empleados, mantener todas abiertas
            accordionTargets.forEach(function(details){
                details.open = true;
                details.addEventListener('toggle', function(){
                    if (!this.open) {
                        this.open = true;
                    }
                });
            });
        } else if (accordionRoles.includes(hrmSidebarRole)) {
            // Comportamiento acordeón: al abrir una, cerrar las demás (excepto las que tienen activo)
            accordionTargets.forEach(function(details){
                details.addEventListener('toggle', function(){
                    if(this.open){
                        accordionTargets.forEach(function(other){
                            // Solo cerrar si no es el actual Y no tiene un enlace activo
                            if(other !== details && other.open && !hasActiveLink(other)){
                                other.open = false;
                            }
                        });
                    }
                });
            });

            // Al cargar, asegurar que solo las secciones con activo estén abiertas
            // más la primera sección si ninguna tiene activo
            var anyActive = false;
            accordionTargets.forEach(function(details){
                if(hasActiveLink(details)){
                    details.open = true;
                    anyActive = true;
                }
            });

            // Si hay una sección activa, cerrar las demás que no tienen activo
            if(anyActive){
                accordionTargets.forEach(function(details){
                    if(!hasActiveLink(details) && details.open){
                        details.open = false;
                    }
                });
            }
        }
    });
})();
