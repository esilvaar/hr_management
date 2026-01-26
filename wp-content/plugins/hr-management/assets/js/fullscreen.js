/**
 * Manejador de Pantalla Completa para HR Management
 * Permite alternar entre modo normal y pantalla completa
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Debug: mostrar los datos pasados desde PHP
        try {
            console.log('[HRM-FS] hrmFullscreenData =', window.hrmFullscreenData);
        } catch (e) {
            // Ignorar si console no está disponible
        }

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
                const layouts = document.querySelectorAll('.hrm-admin-layout');
                const wrapCount = document.querySelectorAll('.wrap.hrm-admin-wrap').length;
                if (layouts.length > 1 || wrapCount > 1) {
                    console.warn('[HRM-DEBUG] Multiple HRM layouts detected', { layouts: layouts.length, hrmWraps: wrapCount });
                    if (userId === 1) {
                        const banner = document.createElement('div');
                        banner.style.position = 'fixed';
                        banner.style.top = '8px';
                        banner.style.left = '50%';
                        banner.style.transform = 'translateX(-50%)';
                        banner.style.zIndex = '99999';
                        banner.style.background = 'rgba(255, 75, 75, 0.95)';
                        banner.style.color = '#fff';
                        banner.style.padding = '10px 18px';
                        banner.style.borderRadius = '6px';
                        banner.style.fontWeight = '600';
                        banner.style.boxShadow = '0 6px 18px rgba(0,0,0,0.12)';
                        banner.textContent = 'HRM DEBUG: Se detectaron ' + layouts.length + ' layouts y ' + wrapCount + ' wraps. Revisa la consola para más detalles.';
                        document.body.appendChild(banner);
                        setTimeout(() => banner.remove(), 12000);
                    }
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
