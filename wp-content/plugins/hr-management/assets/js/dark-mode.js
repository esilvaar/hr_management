(function () {
    'use strict';

    var DarkModeManager = {
        darkModeClass: 'myplugin_dark',
        toggleId: 'mypluginDarkModeToggle',

        init: function () {
            this.setupToggleWhenReady();
        },

        setupToggleWhenReady: function () {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', this.setupToggle.bind(this));
                return;
            }

            this.setupToggle();
        },

        setupToggle: function () {
            var sidebar = document.getElementById('hrm-sidebar');
            if (!sidebar) {
                return;
            }

            if (document.getElementById(this.toggleId)) {
                this.updateToggleState(this.isDarkModeEnabled());
                return;
            }

            var toggleContainer = this.createToggleButton();
            var header = sidebar.querySelector('.hrm-sidebar-header');
            if (header) {
                header.insertAdjacentElement('afterend', toggleContainer);
            }
        },

        createToggleButton: function () {
            var container = document.createElement('div');
            container.className = 'hrm-dark-mode-toggle-container p-2 border-bottom d-flex align-items-center justify-content-between';
            container.style.cssText = 'gap: 8px; background-color: var(--hrm-sidebar-bg); border-color: var(--hrm-border-color) !important;';

            var label = document.createElement('label');
            label.className = 'form-check-label';
            label.style.cssText = 'margin: 0; cursor: pointer; user-select: none; color: var(--hrm-text-primary); font-size: 0.9rem; font-weight: 500;';
            label.textContent = 'Modo oscuro';

            var switchContainer = document.createElement('div');
            switchContainer.className = 'form-check form-switch';
            switchContainer.style.cssText = 'margin: 0;';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.id = this.toggleId;
            checkbox.style.cssText = 'cursor: pointer;';
            checkbox.checked = this.isDarkModeEnabled();

            checkbox.addEventListener('change', function (event) {
                if (event.target.checked) {
                    DarkModeManager.enableDarkMode();
                } else {
                    DarkModeManager.disableDarkMode();
                }
            });

            switchContainer.appendChild(checkbox);
            container.appendChild(label);
            container.appendChild(switchContainer);

            return container;
        },

        isDarkModeEnabled: function () {
            return document.documentElement.classList.contains(this.darkModeClass);
        },

        enableDarkMode: function () {
            document.documentElement.classList.add(this.darkModeClass);
            this.updateToggleState(true);
            this.persistPreference(true);
        },

        disableDarkMode: function () {
            document.documentElement.classList.remove(this.darkModeClass);
            this.updateToggleState(false);
            this.persistPreference(false);
        },

        updateToggleState: function (enabled) {
            var checkbox = document.getElementById(this.toggleId);
            if (checkbox) {
                checkbox.checked = enabled;
            }
        },

        persistPreference: function (enabled) {
            if (typeof mypluginDarkMode === 'undefined') {
                return;
            }

            var payload = new URLSearchParams();
            payload.append('action', 'myplugin_dark_mode_set');
            payload.append('nonce', mypluginDarkMode.nonce || '');
            payload.append('enabled', enabled ? '1' : '0');

            fetch(mypluginDarkMode.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload.toString()
            }).catch(function () {
                // Silent fail to avoid blocking UI; preference can be retried next toggle.
            });
        }
    };

    DarkModeManager.init();
    window.HRMDarkMode = DarkModeManager;
})();
