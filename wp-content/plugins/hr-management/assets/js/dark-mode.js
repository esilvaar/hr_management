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

            var slot = document.getElementById('myplugin-dark-toggle-slot');
            var toggleContainer = this.createToggleButton();

            if (slot) {
                slot.appendChild(toggleContainer);
                return;
            }

            var header = sidebar.querySelector('.hrm-sidebar-header');
            if (header) {
                header.insertAdjacentElement('afterend', toggleContainer);
            }
        },

        createToggleButton: function () {
            var container = document.createElement('div');
            container.className = 'myplugin-settings-panel';

            var button = document.createElement('button');
            button.type = 'button';
            button.id = this.toggleId;
            button.className = 'myplugin-toggle-btn';
            button.setAttribute('aria-pressed', this.isDarkModeEnabled() ? 'true' : 'false');

            var label = document.createElement('span');
            label.className = 'myplugin-toggle-label';
            label.textContent = 'Modo oscuro';

            var switchWrap = document.createElement('span');
            switchWrap.className = 'myplugin-toggle-switch';
            switchWrap.setAttribute('aria-hidden', 'true');

            var switchText = document.createElement('span');
            switchText.className = 'myplugin-toggle-text';
            switchText.textContent = this.isDarkModeEnabled() ? 'ON' : 'OFF';

            var thumb = document.createElement('span');
            thumb.className = 'myplugin-toggle-thumb';

            switchWrap.appendChild(switchText);
            switchWrap.appendChild(thumb);

            button.appendChild(label);
            button.appendChild(switchWrap);

            button.addEventListener('click', function () {
                var enabled = DarkModeManager.toggleDarkMode();
                button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                switchText.textContent = enabled ? 'ON' : 'OFF';
            });

            container.appendChild(button);

            return container;
        },

        isDarkModeEnabled: function () {
            return document.documentElement.classList.contains(this.darkModeClass);
        },

        enableDarkMode: function () {
            document.documentElement.classList.add(this.darkModeClass);
            this.persistPreference(true);
        },

        disableDarkMode: function () {
            document.documentElement.classList.remove(this.darkModeClass);
            this.persistPreference(false);
        },

        toggleDarkMode: function () {
            var isDarkMode = this.isDarkModeEnabled();
            if (isDarkMode) {
                this.disableDarkMode();
                return false;
            }

            this.enableDarkMode();
            return true;
        },

        updateToggleState: function (enabled) {
            var button = document.getElementById(this.toggleId);
            if (!button) {
                return;
            }

            button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            var text = button.querySelector('.myplugin-toggle-text');
            if (text) {
                text.textContent = enabled ? 'ON' : 'OFF';
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
