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
        var navDetails = document.querySelectorAll('.hrm-nav > details');
        var profileMidDetails = document.querySelector('.hrm-profile-mid > details');
        var allDetails = Array.from(navDetails);
        
        // Incluir el details de "Documentos-Reglamentos" si existe
        if(profileMidDetails) allDetails.push(profileMidDetails);

        allDetails.forEach(function(details){
            details.addEventListener('toggle', function(){
                if(this.open){
                    // Cerrar todos los otros details
                    allDetails.forEach(function(other){
                        if(other !== details && other.open){
                            other.open = false;
                        }
                    });
                }
            });
        });
    });
})();
