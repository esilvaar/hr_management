(function(){
    'use strict';

    /**
     * HRM Dark Mode Manager
     * Gestiona el modo oscuro usando localStorage y variables CSS
     */
    
    const DarkModeManager = {
        storageKey: 'hrm_dark_mode_enabled',
        darkModeClass: 'hrm-dark-mode',
        
        /**
         * Inicializar el modo oscuro
         */
        init: function() {
            // Cargar preferencia guardada
            this.loadPreference();
            
            // Esperar a que el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupToggle());
            } else {
                this.setupToggle();
            }
        },
        
        /**
         * Cargar preferencia del usuario desde localStorage
         */
        loadPreference: function() {
            const isDarkMode = localStorage.getItem(this.storageKey) === 'true';
            
            if (isDarkMode) {
                this.enableDarkMode();
            }
        },
        
        /**
         * Configurar el botón toggle de modo oscuro
         */
        setupToggle: function() {
            // Buscar sidebar
            const sidebar = document.getElementById('hrm-sidebar');
            if (!sidebar) return;
            
            // Crear botón toggle
            const toggleBtn = this.createToggleButton();
            
            // Insertar después del header
            const header = sidebar.querySelector('.hrm-sidebar-header');
            if (header) {
                header.insertAdjacentElement('afterend', toggleBtn);
            }
            
            // Event listener
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDarkMode();
            });
        },
        
        /**
         * Crear el botón toggle
         */
        createToggleButton: function() {
            const container = document.createElement('div');
            container.className = 'hrm-dark-mode-toggle-container p-2 border-bottom d-flex align-items-center justify-content-between';
            container.style.cssText = 'gap: 8px; background-color: var(--hrm-sidebar-bg); border-color: var(--hrm-border-color) !important;';
            
            const label = document.createElement('label');
            label.className = 'form-check-label';
            label.style.cssText = 'margin: 0; cursor: pointer; user-select: none; color: var(--hrm-text-primary); font-size: 0.9rem; font-weight: 500;';
            label.textContent = '🌙 Modo oscuro';
            
            const switchContainer = document.createElement('div');
            switchContainer.className = 'form-check form-switch';
            switchContainer.style.cssText = 'margin: 0;';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.id = 'hrmDarkModeToggle';
            checkbox.style.cssText = 'cursor: pointer;';
            
            // Verificar si está activo
            if (localStorage.getItem(this.storageKey) === 'true') {
                checkbox.checked = true;
            }
            
            checkbox.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.enableDarkMode();
                } else {
                    this.disableDarkMode();
                }
            });
            
            switchContainer.appendChild(checkbox);
            switchContainer.appendChild(label);
            container.appendChild(label);
            container.appendChild(switchContainer);
            
            return container;
        },
        
        /**
         * Activar modo oscuro
         */
        enableDarkMode: function() {
            document.body.classList.add(this.darkModeClass);
            localStorage.setItem(this.storageKey, 'true');
            this.updateToggleState(true);
        },
        
        /**
         * Desactivar modo oscuro
         */
        disableDarkMode: function() {
            document.body.classList.remove(this.darkModeClass);
            localStorage.setItem(this.storageKey, 'false');
            this.updateToggleState(false);
        },
        
        /**
         * Alternar modo oscuro
         */
        toggleDarkMode: function() {
            const isDarkMode = document.body.classList.contains(this.darkModeClass);
            
            if (isDarkMode) {
                this.disableDarkMode();
            } else {
                this.enableDarkMode();
            }
        },
        
        /**
         * Actualizar estado del toggle
         */
        updateToggleState: function(enabled) {
            const checkbox = document.getElementById('hrmDarkModeToggle');
            if (checkbox) {
                checkbox.checked = enabled;
            }
        }
    };
    
    // Inicializar cuando esté listo
    DarkModeManager.init();
    
    // Exportar para uso externo si es necesario
    window.HRMDarkMode = DarkModeManager;
})();
