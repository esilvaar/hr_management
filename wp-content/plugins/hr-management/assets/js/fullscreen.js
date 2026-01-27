/**
 * Manejador de Pantalla Completa para HR Management
 * Permite alternar entre modo normal y pantalla completa
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Debug: mostrar los datos pasados desde PHP
        // hrmFullscreenData is available on window when provided by PHP; do not log in production

        // Crear botón de toggle solo en páginas del plugin
        const urlParams = new URLSearchParams(window.location.search);
        const isHrmPage = urlParams.get('page') && urlParams.get('page').startsWith('hrm');

        if (!isHrmPage) {
            return; // No hacer nada si no estamos en una página del plugin
        }

        // Verificar si el usuario es ID 1
        const userId = (window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId)) || 0;
        const isAdmin = userId === 1;
        const isInFullscreen = isFullscreenMode();

        // Para usuarios que no sean ID 1, activar automáticamente pantalla completa
        const autoRedirect = (window.hrmFullscreenData && window.hrmFullscreenData.autoRedirect) ? true : false;
        if (autoRedirect && !isAdmin && !isInFullscreen) {
            urlParams.set('fullscreen', '1');
            window.location.href = window.location.pathname + '?' + urlParams.toString();
            return;
        }

        createFullscreenToggle();

        if (isFullscreenMode()) {
            preserveFullscreenOnLinks();
            checkLayoutDuplication();
        }

        function checkLayoutDuplication() {
            try {
                const allLayouts = Array.from(document.querySelectorAll('.hrm-admin-layout'));
                const allWraps = Array.from(document.querySelectorAll('.wrap.hrm-admin-wrap'));

                // Consider only visible top-level elements to avoid false positives from hidden or nested nodes
                const visibleLayouts = allLayouts.filter(el => el.offsetParent !== null && el.closest('.hrm-admin-layout') === el);
                const visibleWraps = allWraps.filter(el => el.offsetParent !== null && el.closest('.wrap.hrm-admin-wrap') === el);

                if (visibleLayouts.length > 1 || visibleWraps.length > 1) {
                    // Multiple visible layouts detected; do not display debug banner in production.
                }
            } catch (e) {
                console.error('[HRM-DEBUG] checkLayoutDuplication error', e);
            }
        }

        document.addEventListener('keydown', function(e) {
            const uid = (window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId)) || 0;
            const admin = uid === 1;
            const inFs = isFullscreenMode();
            if ((e.key === 'F11' || (e.ctrlKey && e.shiftKey && e.key === 'F')) && admin) {
                e.preventDefault();
                toggleFullscreen();
            }
            if (e.key === 'Escape' && inFs && admin) {
                e.preventDefault();
                toggleFullscreen();
            }
        });

        function createFullscreenToggle() {
            const uid = (window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId)) || 0;
            const admin = uid === 1;
            const inFs = isFullscreenMode();
            if (!admin && inFs) return;
            const button = document.createElement('button');
            button.className = 'hrm-fullscreen-toggle';
            button.setAttribute('data-tooltip', 'Pantalla Completa (Ctrl+Shift+F)');
            button.innerHTML = '<span>Pantalla Completa</span>';
            if (inFs && admin) {
                button.innerHTML = '<span>Salir</span>';
                button.setAttribute('data-tooltip', 'Salir de Pantalla Completa (ESC)');
            }
            button.addEventListener('click', toggleFullscreen);
            document.body.appendChild(button);
        }

        function isFullscreenMode() {
            const params = new URLSearchParams(window.location.search);
            return params.get('fullscreen') === '1';
        }

        function toggleFullscreen() {
            const params = new URLSearchParams(window.location.search);
            if (isFullscreenMode()) {
                params.delete('fullscreen');
            } else {
                params.set('fullscreen', '1');
            }
            const newUrl = window.location.pathname + '?' + params.toString();
            document.body.style.opacity = '0.8';
            setTimeout(function() { window.location.href = newUrl; }, 150);
        }

        function preserveFullscreenOnLinks() {
            const links = document.querySelectorAll('a[href*="admin.php?page=hrm"], a[href*="page=hrm"]');
            links.forEach(function(link) {
                const href = link.getAttribute('href');
                if (href && !href.includes('fullscreen=')) {
                    const separator = href.includes('?') ? '&' : '?';
                    link.setAttribute('href', href + separator + 'fullscreen=1');
                }
            });
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        const newLinks = document.querySelectorAll('a[href*="admin.php?page=hrm"], a[href*="page=hrm"]');
                        newLinks.forEach(function(link) {
                            const href = link.getAttribute('href');
                            if (href && !href.includes('fullscreen=')) {
                                const separator = href.includes('?') ? '&' : '?';
                                link.setAttribute('href', href + separator + 'fullscreen=1');
                            }
                        });
                    }
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

    });

})();
