/**
 * Emergency Contacts Management
 * Gestión de contactos de emergencia en el formulario de detalles del empleado
 */

(function($) {
    'use strict';

    var hrmEC = {
        data: hrmEmergencyContactsData || {},
        currentEditingId: null,

        init: function() {
            this.bindEvents();
            this.loadEmergencyContacts();
        },

        bindEvents: function() {
            var self = this;

            // Agregar nuevo contacto
            $(document).on('click', '#hrm-add-emergency-contact', function(e) {
                e.preventDefault();
                self.openEmergencyContactPanel('add');
            });

            // Cerrar panel
            $(document).on('click', '#hrm-close-emergency-contact-panel', function(e) {
                e.preventDefault();
                self.closeEmergencyContactPanel();
            });

            // Cancelar en el panel
            $(document).on('click', '#hrm_ec_cancel', function(e) {
                e.preventDefault();
                self.closeEmergencyContactPanel();
            });

            // Guardar contacto
            $(document).on('click', '#hrm_ec_save', function(e) {
                e.preventDefault();
                self.saveEmergencyContact();
            });

            // Editar contacto
            $(document).on('click', '.hrm-ec-edit-btn', function(e) {
                e.preventDefault();
                var contactId = $(this).data('id');
                self.editEmergencyContact(contactId);
            });

            // Eliminar contacto
            $(document).on('click', '.hrm-ec-delete-btn', function(e) {
                e.preventDefault();
                var contactId = $(this).data('id');
                if (confirm('¿Estás seguro de que quieres eliminar este contacto de emergencia?')) {
                    self.deleteEmergencyContact(contactId);
                }
            });
        },

        openEmergencyContactPanel: function(mode, contactId) {
            var self = this;
            var panel = $('#hrm-emergency-contact-panel');
            var title = $('#hrm-ec-title');

            // Limpiar formulario
            $('#hrm_ec_nombre_contacto').val('');
            $('#hrm_ec_numero_telefono').val('');
            $('#hrm_ec_relacion').val('');
            $('#hrm_ec_feedback').addClass('myplugin-hidden').text('');
            this.currentEditingId = null;

            if (mode === 'edit' && contactId) {
                title.text('Editar Contacto de Emergencia');
                // Cargar datos del contacto
                var row = $('[data-ec-id="' + contactId + '"]');
                if (row.length) {
                    $('#hrm_ec_nombre_contacto').val(row.find('.hrm-ec-nombre').text());
                    $('#hrm_ec_numero_telefono').val(row.find('.hrm-ec-telefono').text());
                    $('#hrm_ec_relacion').val(row.find('.hrm-ec-relacion').data('value'));
                    this.currentEditingId = contactId;
                }
            } else {
                title.text('Agregar Contacto de Emergencia');
            }

            panel.removeClass('myplugin-hidden');
            $('#hrm_ec_nombre_contacto').focus();
        },

        closeEmergencyContactPanel: function() {
            $('#hrm-emergency-contact-panel').addClass('myplugin-hidden');
            this.currentEditingId = null;
        },

        isValidPhoneNumber: function(telefono) {
            // Regex que acepta:
            // +56 9 XXXX XXXX, +56912345678 (formato internacional Chile)
            // 9 XXXX XXXX, 912345678 (formato nacional sin +56)
            // 02 XXXX XXXX, 0212345678 (formato con código de área)
            // Espacios y guiones permitidos
            var phoneRegex = /^(\+?56)?[\s-]?(9[\s-]?\d{4}[\s-]?\d{4}|\d{2}[\s-]?\d{4}[\s-]?\d{4}|9\d{8}|\d{9})$/;
            
            // Limpiar espacios y guiones para validar
            var cleanPhone = telefono.replace(/[\s-]/g, '');
            return phoneRegex.test(cleanPhone);
        },

        saveEmergencyContact: function() {
            var self = this;
            var nombre = $('#hrm_ec_nombre_contacto').val().trim();
            var telefono = $('#hrm_ec_numero_telefono').val().trim();
            var relacion = $('#hrm_ec_relacion').val().trim();

            // Validación básica
            if (!nombre || !telefono) {
                $('#hrm_ec_feedback').removeClass('myplugin-hidden').text('Por favor completa los campos obligatorios (nombre y teléfono).');
                return;
            }

            // Validar formato de teléfono
            if (!this.isValidPhoneNumber(telefono)) {
                $('#hrm_ec_feedback').removeClass('myplugin-hidden').text('Por favor ingresa un número de teléfono válido (ej: +56912345678, 912345678 o 02 1234 5678).');
                return;
            }

            var action = this.currentEditingId ? 'hrm_edit_emergency_contact' : 'hrm_add_emergency_contact';
            var data = {
                action: action,
                security: this.data.nonce,
                employee_id: this.data.employeeId,
                employee_rut: this.data.employeeRut,
                nombre_contacto: nombre,
                numero_telefono: telefono,
                relacion: relacion,
            };

            if (this.currentEditingId) {
                data.contact_id = this.currentEditingId;
            }

            $.ajax({
                url: this.data.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.closeEmergencyContactPanel();
                        self.loadEmergencyContacts();
                    } else {
                        $('#hrm_ec_feedback').removeClass('myplugin-hidden').text(response.data.message || 'Error al guardar el contacto.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    $('#hrm_ec_feedback').removeClass('myplugin-hidden').text('Error en la solicitud. Por favor intenta de nuevo.');
                }
            });
        },

        editEmergencyContact: function(contactId) {
            this.openEmergencyContactPanel('edit', contactId);
        },

        deleteEmergencyContact: function(contactId) {
            var self = this;

            $.ajax({
                url: this.data.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrm_delete_emergency_contact',
                    security: this.data.nonce,
                    contact_id: contactId,
                    employee_id: this.data.employeeId,
                },
                success: function(response) {
                    if (response.success) {
                        self.loadEmergencyContacts();
                    } else {
                        alert(response.data.message || 'Error al eliminar el contacto.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('Error en la solicitud. Por favor intenta de nuevo.');
                }
            });
        },

        loadEmergencyContacts: function() {
            var self = this;
            var container = $('#hrm-emergency-contacts-container');

            if (!this.data.employeeRut) {
                container.html('<p class="text-muted text-center py-3">No hay datos del empleado disponibles.</p>');
                return;
            }

            $.ajax({
                url: this.data.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrm_get_emergency_contacts',
                    security: this.data.nonce,
                    employee_rut: this.data.employeeRut,
                },
                success: function(response) {
                    if (response.success) {
                        self.renderEmergencyContacts(response.data.contacts);
                    } else {
                        container.html('<p class="text-muted text-center py-3">Error al cargar los contactos.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    container.html('<p class="text-muted text-center py-3">Error al cargar los contactos.</p>');
                }
            });
        },

        renderEmergencyContacts: function(contacts) {
            var container = $('#hrm-emergency-contacts-container');
            var canEdit = this.data.canEdit;

            if (!contacts || contacts.length === 0) {
                container.html('<p class="text-muted text-center py-3">No hay contactos de emergencia registrados.</p>');
                return;
            }

            var html = '<div class="table-responsive">';
            html += '<table class="table table-sm table-hover">';
            html += '<thead class="table-light">';
            html += '<tr>';
            html += '<th>Nombre Contacto</th>';
            html += '<th>Teléfono</th>';
            html += '<th>Relación</th>';
            if (canEdit) {
                html += '<th style="width: 100px;">Acciones</th>';
            }
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            $.each(contacts, function(i, contact) {
                html += '<tr data-ec-id="' + contact.id + '">';
                html += '<td><span class="hrm-ec-nombre">' + this.escapeHtml(contact.nombre_contacto) + '</span></td>';
                html += '<td><span class="hrm-ec-telefono">' + this.escapeHtml(contact.numero_telefono) + '</span></td>';
                html += '<td><span class="hrm-ec-relacion" data-value="' + this.escapeHtml(contact.relacion) + '">' + (contact.relacion ? this.escapeHtml(contact.relacion) : '<em class="text-muted">-</em>') + '</span></td>';
                if (canEdit) {
                    html += '<td>';
                    html += '<button class="btn btn-xs btn-warning hrm-ec-edit-btn" data-id="' + contact.id + '" title="Editar"><span class="dashicons dashicons-edit"></span></button> ';
                    html += '<button class="btn btn-xs btn-danger hrm-ec-delete-btn" data-id="' + contact.id + '" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>';
                    html += '</td>';
                }
                html += '</tr>';
            }.bind(this));

            html += '</tbody>';
            html += '</table>';
            html += '</div>';

            container.html(html);
        },

        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        hrmEC.init();
    });

})(jQuery);
