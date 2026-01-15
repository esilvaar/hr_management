/**
 * Sidebar Responsive - Toggle Handler
 * Maneja la apertura/cierre de la sidebar en móviles
 */

document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('hrm-sidebar-toggle');
    const sidebarClose = document.getElementById('hrm-sidebar-close');
    const sidebarOverlay = document.getElementById('hrm-sidebar-overlay');
    const sidebar = document.querySelector('.hrm-sidebar');
    
    if (!sidebarToggle || !sidebar) {
        return;
    }
    
    /**
     * Abre la sidebar
     */
    function openSidebar() {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
    }
    
    /**
     * Cierra la sidebar
     */
    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    }
    
    /**
     * Toggle de la sidebar
     */
    function toggleSidebar() {
        if (sidebar.classList.contains('active')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    
    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // Cerrar sidebar cuando se hace clic en un link
    const navLinks = document.querySelectorAll('.hrm-nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Solo cerrar en pantallas móviles
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });
    
    // Cerrar sidebar al cambiar el tamaño de la ventana (cuando pasa de móvil a desktop)
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });
});
