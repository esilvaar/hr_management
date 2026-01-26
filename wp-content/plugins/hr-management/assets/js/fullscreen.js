/**
 * Manejador de Pantalla Completa para HR Management
 * Permite alternar entre modo normal y pantalla completa
 */

(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        
        // Crear botón de toggle solo en páginas del plugin
        const urlParams = new URLSearchParams(window.location.search);
        const isHrmPage = urlParams.get('page') && urlParams.get('page').startsWith('hrm');
        
        if (!isHrmPage) {
            return; // No hacer nada si no estamos en una página del plugin
        }
        
        // Verificar si el usuario es ID 1
        const userId = window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId);
        const isAdmin = userId === 1;
        const isInFullscreen = isFullscreenMode();
        
        // Para usuarios que no sean ID 1, activar automáticamente pantalla completa
        // Nota: Esto puede forzar redirecciones que causen problemas visuales. Controlado por hrmFullscreenData.autoRedirect
        const autoRedirect = ( typeof hrmFullscreenData !== 'undefined' && hrmFullscreenData.autoRedirect ) ? true : false;
        if ( autoRedirect && !isAdmin && !isInFullscreen) {
            // Redirigir a versión fullscreen
            urlParams.set('fullscreen', '1');
            /**
             * Manejador de Pantalla Completa para HR Management
             * Permite alternar entre modo normal y pantalla completa
             */

            (function() {
                'use strict';
    
                // Esperar a que el DOM esté listo
                document.addEventListener('DOMContentLoaded', function() {
        
                    // Crear botón de toggle solo en páginas del plugin
                    const urlParams = new URLSearchParams(window.location.search);
                    const isHrmPage = urlParams.get('page') && urlParams.get('page').startsWith('hrm');
        
                    if (!isHrmPage) {
                        return; // No hacer nada si no estamos en una página del plugin
                    }
        
                    // Verificar si el usuario es ID 1
                    const userId = window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId);
                    const isAdmin = userId === 1;
                    const isInFullscreen = isFullscreenMode();
        
                    // Para usuarios que no sean ID 1, activar automáticamente pantalla completa
                    // Nota: Esto puede forzar redirecciones que causen problemas visuales. Controlado por hrmFullscreenData.autoRedirect
                    const autoRedirect = ( typeof hrmFullscreenData !== 'undefined' && hrmFullscreenData.autoRedirect ) ? true : false;
                    if ( autoRedirect && !isAdmin && !isInFullscreen) {
                        // Redirigir a versión fullscreen
                        urlParams.set('fullscreen', '1');
                        const newUrl = window.location.pathname + '?' + urlParams.toString();
                        window.location.href = newUrl;
                        return; // No continuar con el resto del código
                    }
        
                    createFullscreenToggle();
        
                    // Preservar modo fullscreen al navegar
                    if (isFullscreenMode()) {
                        preserveFullscreenOnLinks();
                        // Comprobar layout: detectar duplicados y mostrar aviso si existe un problema
                        checkLayoutDuplication();
                    }

                    /**
                     * Detecta duplicación de elementos de layout en pantalla completa
                     * y muestra un aviso temporal para debugging
                     */
                    function checkLayoutDuplication() {
                        try {
                            const layouts = document.querySelectorAll('.hrm-admin-layout');
                            const wrapCount = document.querySelectorAll('.wrap.hrm-admin-wrap').length;
                            if ( layouts.length > 1 || wrapCount > 1 ) {
                                console.warn('[HRM-DEBUG] Multiple HRM layouts detected', { layouts: layouts.length, hrmWraps: wrapCount });
                                // Añadir aviso visual solo para administradores
                                const userId = window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId);
                                const isAdmin = userId === 1;
                                if ( isAdmin ) {
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
                        } catch(e) {
                            console.error('[HRM-DEBUG] checkLayoutDuplication error', e);
                        }
                    }
        
                    // Manejar tecla F11 para toggle (opcional)
                    document.addEventListener('keydown', function(e) {
                        const userId = window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId);
                        const isAdmin = userId === 1;
                        const isInFullscreen = isFullscreenMode();
            
                        // F11 o Ctrl+Shift+F (solo para admin)
                        if ((e.key === 'F11' || (e.ctrlKey && e.shiftKey && e.key === 'F')) && isAdmin) {
                            e.preventDefault();
                            toggleFullscreen();
                        }
            
                        // ESC para salir del fullscreen (solo para admin)
                        if (e.key === 'Escape' && isInFullscreen && isAdmin) {
                            e.preventDefault();
                            toggleFullscreen();
                        }
                    });
                });
    
                /**
                 * Crear el botón de toggle de pantalla completa
                 */
                function createFullscreenToggle() {
                    // Verificar si el usuario es ID 1
                    const userId = window.hrmFullscreenData && parseInt(window.hrmFullscreenData.userId);
                    const isAdmin = userId === 1;
                    const isInFullscreen = isFullscreenMode();
        
                    // No mostrar botón para usuarios que no sean ID 1 o 20 cuando están en pantalla completa
                    if (!isAdmin && isInFullscreen) {
                        return; // No crear el botón
                    }
        
                    const button = document.createElement('button');
                    button.className = 'hrm-fullscreen-toggle';
                    button.setAttribute('data-tooltip', 'Pantalla Completa (Ctrl+Shift+F)');
                    button.innerHTML = '<span>Pantalla Completa</span>';
        
                    // Cambiar texto si ya estamos en fullscreen (solo para admin)
                    if (isInFullscreen && isAdmin) {
                        button.innerHTML = '<span>Salir</span>';
                        button.setAttribute('data-tooltip', 'Salir de Pantalla Completa (ESC)');
                    }
        
                    button.addEventListener('click', toggleFullscreen);
        
                    // Agregar al body
                    document.body.appendChild(button);
                }
    
                /**
                 * Verificar si estamos en modo pantalla completa
                 */
                function isFullscreenMode() {
                    const urlParams = new URLSearchParams(window.location.search);
                    return urlParams.get('fullscreen') === '1';
                }
    
                /**
                 * Alternar entre modo normal y pantalla completa
                 */
                function toggleFullscreen() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const currentUrl = window.location.href;
                    let newUrl;
        
                    if (isFullscreenMode()) {
                        // Salir de pantalla completa
                        urlParams.delete('fullscreen');
                        newUrl = window.location.pathname + '?' + urlParams.toString();
                    } else {
                        // Entrar en pantalla completa
                        urlParams.set('fullscreen', '1');
                        newUrl = window.location.pathname + '?' + urlParams.toString();
                    }
        
                    // Redirigir con animación suave
                    document.body.style.opacity = '0.8';
                    setTimeout(function() {
                        window.location.href = newUrl;
                    }, 150);
                }
    
                /**
                 * Preservar el parámetro fullscreen en todos los enlaces del plugin
                 */
                function preserveFullscreenOnLinks() {
                    // Obtener todos los enlaces en la página
                    const links = document.querySelectorAll('a[href*="admin.php?page=hrm"], a[href*="page=hrm"]');
        
                    links.forEach(function(link) {
                        const href = link.getAttribute('href');
            
                        // Solo modificar si no tiene ya el parámetro fullscreen
                        if (href && !href.includes('fullscreen=')) {
                            const separator = href.includes('?') ? '&' : '?';
                            link.setAttribute('href', href + separator + 'fullscreen=1');
                        }
                    });
        
                    // Observar cambios en el DOM para nuevos enlaces (para contenido dinámico)
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
        
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
    
            })();
