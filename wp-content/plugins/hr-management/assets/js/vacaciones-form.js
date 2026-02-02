(function(){
    'use strict';

    function safeGet(obj, key, fallback) {
        try { return obj && obj[key] !== undefined ? obj[key] : fallback; } catch(e) { return fallback; }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Helpers
        const ajaxUrl = safeGet(window.hrmVacacionesFormData, 'ajaxUrl', '/wp-admin/admin-ajax.php');

        // Alert close handlers
        const alerta = document.getElementById('alertaSolicitudCreada');
        const alertaFondo = document.getElementById('alertaFondo');
        document.querySelectorAll('.hrm-success-close').forEach(function(el){
            el.addEventListener('click', function(){
                if (alerta && alerta.remove) alerta.remove();
                if (alertaFondo && alertaFondo.remove) alertaFondo.remove();
            });
        });

        // Cancel button
        document.querySelectorAll('.hrm-cancel-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                if (history && history.back) history.back();
            });
        });

        const inicio = document.getElementById('fecha_inicio');
        const fin = document.getElementById('fecha_fin');
        const tipoSelect = document.getElementById('id_tipo');

        if (!inicio || !fin) return;

        const hoy = new Date();
        inicio.min = hoy.toISOString().split('T')[0];

        let feriadosChile = {};

        async function cargarFeriados() {
            try {
                const anoActual = new Date().getFullYear();
                const anos = [anoActual, anoActual + 1];
                for (let ano of anos) {
                    const response = await fetch( ajaxUrl + '?action=hrm_get_feriados&ano=' + ano );
                    const data = await response.json();
                    if (data.success && data.data) {
                        feriadosChile = Object.assign({}, feriadosChile, data.data);
                    }
                }
            } catch (error) {
                console.log('No se pudieron cargar los feriados:', error);
            }
        }

        cargarFeriados();

        function esFinDeSemana(fechaStr) {
            const fecha = new Date(fechaStr + 'T00:00:00');
            const dia = fecha.getDay();
            return dia === 0 || dia === 6;
        }

        function esFeriado(fechaStr) {
            return Object.prototype.hasOwnProperty.call(feriadosChile, fechaStr);
        }

        function contarDiasLaborales(fechaInicio, fechaFin) {
            let fecha = new Date(fechaInicio + 'T00:00:00');
            let fechaTermino = new Date(fechaFin + 'T00:00:00');
            let dias = 0;
            while (fecha <= fechaTermino) {
                const diaSemana = fecha.getDay();
                const fechaStr = fecha.toISOString().split('T')[0];
                if (diaSemana !== 0 && diaSemana !== 6 && !esFeriado(fechaStr)) {
                    dias++;
                }
                fecha.setDate(fecha.getDate() + 1);
            }
            return dias;
        }

        function formatearFecha(fechaStr) {
            if (!fechaStr) return '—';
            const fecha = new Date(fechaStr + 'T00:00:00');
            return fecha.toLocaleDateString('es-CL', { year: 'numeric', month: '2-digit', day: '2-digit' });
        }

        window.calcularDias = function() {
            const fechaInicio = inicio.value;
            const fechaFin = fin.value;
            if (fechaInicio && fechaFin) {
                const dias = contarDiasLaborales(fechaInicio, fechaFin);
                const elDias = document.getElementById('total_dias_display');
                const elInput = document.getElementById('total_dias_input');
                const elIni = document.getElementById('fecha_inicio_display');
                const elFin = document.getElementById('fecha_fin_display');
                if (elDias) elDias.textContent = dias;
                if (elInput) elInput.value = dias;
                if (elIni) elIni.textContent = formatearFecha(fechaInicio);
                if (elFin) elFin.textContent = formatearFecha(fechaFin);
            }
        };

        window.actualizarTipoAusencia = function() {
            if (!tipoSelect) return;
            const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
            const label = selectedOption ? selectedOption.getAttribute('data-label') : null;
            const container = document.getElementById('tipo_ausencia_display');
            if (container) container.textContent = label || 'ausencia';
        };

        // Listeners para campos
        inicio.addEventListener('change', function () {
            if (esFinDeSemana(inicio.value)) {
                alert('⚠️ La fecha de inicio no puede ser un fin de semana.');
                inicio.value = '';
                calcularDias();
                return;
            }
            if (esFeriado(inicio.value)) {
                alert('⚠️ La fecha de inicio no puede ser un día feriado.');
                inicio.value = '';
                calcularDias();
                return;
            }
            fin.min = inicio.value;
            if (fin.value && fin.value < inicio.value) fin.value = '';
            calcularDias();
        });

        fin.addEventListener('change', function () {
            if (inicio.value && fin.value < inicio.value) {
                alert('⚠️ La fecha de fin no puede ser anterior a la fecha de inicio.');
                fin.value = '';
                calcularDias();
                return;
            }
            if (esFinDeSemana(fin.value)) {
                alert('⚠️ La fecha de fin no puede ser un fin de semana.');
                fin.value = '';
                calcularDias();
                return;
            }
            if (esFeriado(fin.value)) {
                alert('⚠️ La fecha de fin no puede ser un día feriado.');
                fin.value = '';
                calcularDias();
                return;
            }
            calcularDias();
        });

        if (tipoSelect) {
            tipoSelect.addEventListener('change', function() { actualizarTipoAusencia(); });
            // default: seleccionar la primera opción (Vacaciones) si existe
            if (tipoSelect.options && tipoSelect.options.length > 1) {
                tipoSelect.value = tipoSelect.options[1].value;
                actualizarTipoAusencia();
            }
        }

        // PREVIEW modal (no inline handlers)
        const btnPreview = document.getElementById('btnPreview');
        if (btnPreview) {
            btnPreview.addEventListener('click', function() {
                const tipo = document.getElementById('id_tipo');
                const tipoLabel = tipo ? tipo.options[tipo.selectedIndex].text : '';
                const inicioVal = (document.getElementById('fecha_inicio') || {}).value;
                const finVal = (document.getElementById('fecha_fin') || {}).value;
                const totalDias = document.getElementById('total_dias_display') ? document.getElementById('total_dias_display').textContent : '0';
                const descripcion = document.getElementById('descripcion') ? document.getElementById('descripcion').value : '';

                if (!tipo || !tipo.value || !inicioVal || !finVal) {
                    alert('⚠️ Por favor completa los campos obligatorios (Tipo de ausencia, fechas) para ver la vista previa.');
                    return;
                }

                function escapeHtml(text) {
                    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                    return (text || '').replace(/[&<>\"']/g, function(m){ return map[m]; });
                }

                const previewHTML = `
                    <div id="previewModal" class="hrm-preview-modal">
                        <div class="hrm-preview-modal-content">
                            <div class="hrm-preview-modal-header">
                                <h2 class="hrm-preview-modal-title">Vista Previa de la Solicitud</h2>
                                <button type="button" class="hrm-preview-modal-close">&times;</button>
                            </div>
                            <div class="hrm-preview-modal-body">
                                <div class="hrm-preview-modal-box">
                                    <div class="hrm-preview-grid">
                                        <div>
                                            <div class="mb-3"><div class="hrm-preview-label">Nombre del Solicitante:</div><div class="hrm-preview-value">${escapeHtml( safeGet(window.hrmVacacionesFormData, 'nombreSolicitante', '') )}</div></div>
                                            <div class="mb-3"><div class="hrm-preview-label">RUT:</div><div class="hrm-preview-value">${escapeHtml( safeGet(window.hrmVacacionesFormData, 'empleadoRut', '—') )}</div></div>
                                        </div>
                                        <div>
                                            <div class="mb-3"><div class="hrm-preview-label">Cargo:</div><div class="hrm-preview-value">${escapeHtml( safeGet(window.hrmVacacionesFormData, 'empleadoPuesto', '—') )}</div></div>
                                            <div class="mb-3"><div class="hrm-preview-label">Fecha de Solicitud:</div><div class="hrm-preview-value">${escapeHtml( safeGet(window.hrmVacacionesFormData, 'fechaHoyFormat', '') )}</div></div>
                                        </div>
                                    </div>

                                    <div class="hrm-preview-section-title">Solicitud</div>
                                    <div style="text-align: justify; margin-bottom: 15px; line-height: 1.8; color: #333;">Por medio de la presente, solicito formalmente la autorización para hacer uso de mis días de <strong>${escapeHtml(tipoLabel.toLowerCase())}</strong> correspondientes al período laboral ${new Date().getFullYear()}.</div>

                                    <div class="hrm-preview-section-title">Período de Ausencia</div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 20px 0; padding: 15px; background: #98ff96; border: 2px solid #009929; border-radius: 3px;">
                                        <div style="text-align: center;"><div style="font-weight: bold; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; color: #003400;">Desde:</div><div style="font-size: 18px; font-weight: bold; color: white; padding: 12px; background: #009929; border-radius: 3px;">${formatearFecha(inicioVal)}</div></div>
                                        <div style="text-align: center;"><div style="font-weight: bold; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; color: #003400;">Hasta:</div><div style="font-size: 18px; font-weight: bold; color: white; padding: 12px; background: #009929; border-radius: 3px;">${formatearFecha(finVal)}</div></div>
                                        <div style="text-align: center;"><div style="font-weight: bold; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; color: #003400;">Total de Días:</div><div style="font-size: 18px; font-weight: bold; color: white; padding: 12px; background: #009929; border-radius: 3px;">${totalDias}</div></div>
                                    </div>

                                    ${descripcion ? `<div style="font-size: 13px; font-weight: bold; text-transform: uppercase; color: white; background: #009929; padding: 10px 15px; margin: 30px 0 15px 0; border-radius: 3px;">Comentarios</div><div class="hrm-highlight-box">${escapeHtml(descripcion)}</div>` : ''}

                                    <div style="text-align: justify; margin: 30px 0 15px 0; line-height: 1.8; color: #333;">Quedo atento(a) a la confirmación y aprobación de esta solicitud. Me comprometo a dejar mis tareas debidamente coordinadas con mi jefatura directa antes de mi ausencia.</div>
                                    <div style="text-align: justify; margin-bottom: 15px; line-height: 1.8; color: #333;">Sin otro particular, Saluda atentamente,<br><strong>${escapeHtml( safeGet(window.hrmVacacionesFormData, 'nombreSolicitante', '') )}</strong></div>
                                </div>
                            </div>
                            <div style="padding: 15px 40px; background: white; border-top: 1px solid #ddd; text-align: center;">
                                <button type="button" class="btn btn-secondary hrm-preview-close">Cerrar Vista Previa</button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', previewHTML);

                // Hook para cerrar modal
                const modalClose = document.querySelector('#previewModal .hrm-preview-close');
                if (modalClose) modalClose.addEventListener('click', function(){ document.getElementById('previewModal').remove(); });
            });
        }

    });
})();