/**
 * vacaciones-form.js — Lógica del formulario "Día Completo"
 * ─────────────────────────────────────────────────────────
 * Usa jQuery(document).ready() para compatibilidad con WordPress.
 * Todos los selectores usan IDs con sufijo _vac.
 *
 * Mapa HTML → JS (fuente de verdad: views/vacaciones-form.php)
 *   #fecha_inicio_vac          date input
 *   #fecha_fin_vac             date input
 *   #id_tipo_vac               select (tipo ausencia)
 *   #tipo_ausencia_display_vac span texto formal
 *   #fecha_inicio_display_vac  div resumen
 *   #fecha_fin_display_vac     div resumen
 *   #total_dias_display_vac    div resumen
 *   #total_dias_input_vac      hidden input
 *   #descripcion_vac           textarea
 *   .hrm-cancel-btn            botón cancelar (solo en ESTE form)
 *   #alertaSolicitudCreada_vac modal éxito
 *   #alertaFondo_vac           backdrop éxito
 *   #btnPreview                vista previa
 */
jQuery(document).ready(function($) {
    'use strict';

    var TAG = '[vacaciones-form.js]';
    console.log(TAG, '▶ Inicializando...');

    // ═══════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════

    function safeGet(obj, key, fallback) {
        try { return obj && obj[key] !== undefined ? obj[key] : fallback; }
        catch(e) { return fallback; }
    }

    /**
     * parseFechaFlexible — Convierte cualquier formato de fecha a Date.
     * Acepta:  YYYY-MM-DD (nativo de input[type=date])
     *          DD-MM-YYYY  (formato latino/chileno)
     *          DD/MM/YYYY  (formato con barra)
     * Retorna: Date object o null si inválida.
     */
    function parseFechaFlexible(fechaStr) {
        if (!fechaStr || typeof fechaStr !== 'string') return null;
        fechaStr = fechaStr.trim();

        var partes, anio, mes, dia;

        // Formato YYYY-MM-DD (nativo de <input type="date">)
        if (/^\d{4}-\d{2}-\d{2}$/.test(fechaStr)) {
            partes = fechaStr.split('-');
            anio = parseInt(partes[0], 10);
            mes  = parseInt(partes[1], 10) - 1;
            dia  = parseInt(partes[2], 10);
        }
        // Formato DD-MM-YYYY o DD/MM/YYYY (latino)
        else if (/^\d{2}[-\/]\d{2}[-\/]\d{4}$/.test(fechaStr)) {
            partes = fechaStr.split(/[-\/]/);
            dia  = parseInt(partes[0], 10);
            mes  = parseInt(partes[1], 10) - 1;
            anio = parseInt(partes[2], 10);
        }
        else {
            console.warn(TAG, 'Formato de fecha no reconocido:', fechaStr);
            return null;
        }

        var fecha = new Date(anio, mes, dia);
        if (isNaN(fecha.getTime())) {
            console.warn(TAG, 'Fecha inválida (NaN):', fechaStr);
            return null;
        }
        console.log(TAG, 'parseFechaFlexible("' + fechaStr + '") →', fecha.toISOString().split('T')[0]);
        return fecha;
    }

    /**
     * Normaliza una fecha string a YYYY-MM-DD para comparaciones y cálculos.
     */
    function normalizarAISO(fechaStr) {
        var d = parseFechaFlexible(fechaStr);
        if (!d) return '';
        var y = d.getFullYear();
        var m = ('0' + (d.getMonth() + 1)).slice(-2);
        var dd = ('0' + d.getDate()).slice(-2);
        return y + '-' + m + '-' + dd;
    }

    function formatearFechaDisplay(fechaStr) {
        var d = parseFechaFlexible(fechaStr);
        if (!d) return '—';
        return d.toLocaleDateString('es-CL', { year: 'numeric', month: '2-digit', day: '2-digit' });
    }

    function esFinDeSemana(fechaStr) {
        var d = parseFechaFlexible(fechaStr);
        if (!d) return false;
        var dia = d.getDay();
        return dia === 0 || dia === 6;
    }

    // ═══════════════════════════════════════════════════
    // FERIADOS
    // ═══════════════════════════════════════════════════

    var ajaxUrl = safeGet(window.hrmVacacionesFormData, 'ajaxUrl', '/wp-admin/admin-ajax.php');
    var feriadosChile = {};

    function esFeriado(fechaISO) {
        return feriadosChile.hasOwnProperty(fechaISO);
    }

    function cargarFeriados() {
        var anoActual = new Date().getFullYear();
        var anos = [anoActual, anoActual + 1];
        console.log(TAG, 'Cargando feriados para años:', anos);

        $.each(anos, function(i, ano) {
            $.ajax({
                url: ajaxUrl,
                data: { action: 'hrm_get_feriados', ano: ano },
                dataType: 'json',
                success: function(resp) {
                    if (resp.success && resp.data) {
                        $.extend(feriadosChile, resp.data);
                        console.log(TAG, 'Feriados ' + ano + ' cargados. Total:', Object.keys(feriadosChile).length);
                    }
                },
                error: function(xhr, status, err) {
                    console.warn(TAG, 'Error al cargar feriados ' + ano + ':', err);
                }
            });
        });
    }
    cargarFeriados();

    // ═══════════════════════════════════════════════════
    // CÁLCULO DE DÍAS HÁBILES
    // ═══════════════════════════════════════════════════

    function contarDiasLaborales(fechaInicioStr, fechaFinStr) {
        var inicio = parseFechaFlexible(fechaInicioStr);
        var fin    = parseFechaFlexible(fechaFinStr);
        if (!inicio || !fin) return 0;

        var dias = 0;
        var cursor = new Date(inicio.getTime());

        while (cursor <= fin) {
            var diaSemana = cursor.getDay();
            var isoStr = cursor.getFullYear() + '-'
                + ('0' + (cursor.getMonth() + 1)).slice(-2) + '-'
                + ('0' + cursor.getDate()).slice(-2);

            if (diaSemana !== 0 && diaSemana !== 6 && !esFeriado(isoStr)) {
                dias++;
            }
            cursor.setDate(cursor.getDate() + 1);
        }
        console.log(TAG, 'contarDiasLaborales(' + fechaInicioStr + ', ' + fechaFinStr + ') =', dias);
        return dias;
    }

    // ═══════════════════════════════════════════════════
    // REFERENCIAS A ELEMENTOS (con advertencias)
    // ═══════════════════════════════════════════════════

    function $id(id) {
        var $el = $('#' + id);
        if ($el.length === 0) {
            console.warn(TAG, '⚠️ Selector no encontrado: #' + id);
        }
        return $el;
    }

    var $inicio     = $id('fecha_inicio_vac');
    var $fin        = $id('fecha_fin_vac');
    var $tipoSelect = $id('id_tipo_vac');

    var $iniDisplay  = $id('fecha_inicio_display_vac');
    var $finDisplay  = $id('fecha_fin_display_vac');
    var $diasDisplay = $id('total_dias_display_vac');
    var $diasInput   = $id('total_dias_input_vac');
    var $tipoDisplay = $id('tipo_ausencia_display_vac');
    var $descripcion = $id('descripcion_vac');

    if ($inicio.length === 0 || $fin.length === 0) {
        console.warn(TAG, '🛑 #fecha_inicio_vac o #fecha_fin_vac no encontrados. Cálculo de días desactivado.');
    }

    // Fecha mínima: hoy
    var hoyISO = new Date().toISOString().split('T')[0];
    $inicio.attr('min', hoyISO);
    console.log(TAG, 'Fecha mínima establecida:', hoyISO);

    // ═══════════════════════════════════════════════════
    // FUNCIÓN PRINCIPAL: Calcular y actualizar UI
    // ═══════════════════════════════════════════════════

    function calcularYActualizar() {
        var valInicio = $inicio.val();
        var valFin    = $fin.val();

        console.log(TAG, 'calcularYActualizar() — inicio:', valInicio, 'fin:', valFin);

        // Actualizar display de fecha inicio (incluso si fin está vacío)
        if (valInicio) {
            $iniDisplay.text(formatearFechaDisplay(valInicio));
        } else {
            $iniDisplay.text('—');
        }

        // Actualizar display de fecha fin
        if (valFin) {
            $finDisplay.text(formatearFechaDisplay(valFin));
        } else {
            $finDisplay.text('—');
        }

        // Calcular total solo si ambas fechas están presentes
        if (valInicio && valFin) {
            var dias = contarDiasLaborales(valInicio, valFin);
            $diasDisplay.text(dias);
            $diasInput.val(dias);
            console.log(TAG, '✅ UI actualizada. Total días:', dias);
        } else {
            $diasDisplay.text('0');
            $diasInput.val('0');
            console.log(TAG, 'Esperando ambas fechas para calcular.');
        }
    }

    // Exponer globalmente por si otros scripts lo necesitan
    window.calcularDias = calcularYActualizar;

    // ═══════════════════════════════════════════════════
    // EVENT LISTENERS
    // ═══════════════════════════════════════════════════

    // --- Fecha Inicio ---
    $inicio.on('change input', function() {
        var val = $(this).val();
        console.log(TAG, 'Evento change en #fecha_inicio_vac:', val);

        if (val && esFinDeSemana(val)) {
            alert('⚠️ La fecha de inicio no puede ser un fin de semana.');
            $(this).val('');
            calcularYActualizar();
            return;
        }
        var iso = normalizarAISO(val);
        if (iso && esFeriado(iso)) {
            alert('⚠️ La fecha de inicio no puede ser un día feriado.');
            $(this).val('');
            calcularYActualizar();
            return;
        }

        // Ajustar mínimo de fecha fin
        if (val) {
            $fin.attr('min', val);
            if ($fin.val() && $fin.val() < val) {
                $fin.val('');
            }
        }
        calcularYActualizar();
    });

    // --- Fecha Fin ---
    $fin.on('change input', function() {
        var val = $(this).val();
        console.log(TAG, 'Evento change en #fecha_fin_vac:', val);

        if ($inicio.val() && val && val < $inicio.val()) {
            alert('⚠️ La fecha de fin no puede ser anterior a la fecha de inicio.');
            $(this).val('');
            calcularYActualizar();
            return;
        }
        if (val && esFinDeSemana(val)) {
            alert('⚠️ La fecha de fin no puede ser un fin de semana.');
            $(this).val('');
            calcularYActualizar();
            return;
        }
        var iso = normalizarAISO(val);
        if (iso && esFeriado(iso)) {
            alert('⚠️ La fecha de fin no puede ser un día feriado.');
            $(this).val('');
            calcularYActualizar();
            return;
        }
        calcularYActualizar();
    });

    // --- Tipo de Ausencia ---
    function actualizarTipoAusencia() {
        if ($tipoSelect.length === 0) return;
        var opt = $tipoSelect.find('option:selected');
        var label = opt.data('label') || 'ausencia';
        $tipoDisplay.text(label);
        console.log(TAG, 'Tipo ausencia actualizado:', label);
    }
    window.actualizarTipoAusencia = actualizarTipoAusencia;

    $tipoSelect.on('change', actualizarTipoAusencia);
    // Default: seleccionar primera opción con valor
    if ($tipoSelect.length && $tipoSelect.find('option').length > 1) {
        $tipoSelect.val($tipoSelect.find('option').eq(1).val());
        actualizarTipoAusencia();
    }

    // ═══════════════════════════════════════════════════
    // ALERTA DE ÉXITO (elementos condicionales — no usar $id para evitar warnings)
    // ═══════════════════════════════════════════════════

    var $alerta     = $('#alertaSolicitudCreada_vac');
    var $alertaFondo = $('#alertaFondo_vac');

    $alerta.find('.hrm-success-close').on('click', function() {
        $alerta.remove();
        $alertaFondo.remove();
    });
    $alertaFondo.on('click', function() {
        $alerta.remove();
        $alertaFondo.remove();
    });

    // ═══════════════════════════════════════════════════
    // BOTÓN CANCELAR (Día Completo)
    // ═══════════════════════════════════════════════════

    $('.hrm-cancel-btn').on('click', function(e) {
        e.preventDefault();
        console.log(TAG, 'Botón Cancelar clickeado.');

        $inicio.val('');
        $fin.val('');
        $descripcion.val('');
        $iniDisplay.text('—');
        $finDisplay.text('—');
        $diasDisplay.text('0');
        $diasInput.val('0');
        $tipoDisplay.text('ausencia');

        // Campos admin
        $id('nombre_jefe_vac').val('');
        $id('observaciones_rrhh_vac').val('');

        // Reset tipo
        if ($tipoSelect.length && $tipoSelect.find('option').length > 1) {
            $tipoSelect.val($tipoSelect.find('option').eq(1).val());
            actualizarTipoAusencia();
        }

        console.log(TAG, '✅ Formulario Día Completo limpiado.');

        // Si venimos de solicitud_creada, redirigir limpio
        if (window.location.search.indexOf('solicitud_creada') !== -1) {
            var url = new URL(window.location.href);
            url.searchParams.delete('show');
            url.searchParams.delete('solicitud_creada');
            if (!url.searchParams.has('page')) url.searchParams.set('page', 'hrm-mi-perfil-vacaciones');
            window.location.href = url.toString();
        }
    });

    // ═══════════════════════════════════════════════════
    // VISTA PREVIA
    // ═══════════════════════════════════════════════════

    $(document).on('click', '#btnPreview', function() {
        var tipoLabel  = $tipoSelect.length ? $tipoSelect.find('option:selected').text() : '';
        var inicioVal  = $inicio.val();
        var finVal     = $fin.val();
        var totalDias  = $diasDisplay.text() || '0';
        var descripcion = $descripcion.val() || '';

        if (!$tipoSelect.val() || !inicioVal || !finVal) {
            alert('⚠️ Por favor completa los campos obligatorios (Tipo de ausencia, fechas) para ver la vista previa.');
            return;
        }

        function esc(text) {
            return $('<div/>').text(text || '').html();
        }

        var html = '<div id="previewModal" class="hrm-preview-modal">'
            + '<div class="hrm-preview-modal-content">'
            + '<div class="hrm-preview-modal-header">'
            + '<h2 class="hrm-preview-modal-title">Vista Previa de la Solicitud</h2>'
            + '<button type="button" class="hrm-preview-modal-close">&times;</button>'
            + '</div>'
            + '<div class="hrm-preview-modal-body"><div class="hrm-preview-modal-box">'
            + '<div class="hrm-preview-grid"><div>'
            + '<div class="mb-3"><div class="hrm-preview-label">Nombre del Solicitante:</div><div class="hrm-preview-value">' + esc(safeGet(window.hrmVacacionesFormData, 'nombreSolicitante', '')) + '</div></div>'
            + '<div class="mb-3"><div class="hrm-preview-label">RUT:</div><div class="hrm-preview-value">' + esc(safeGet(window.hrmVacacionesFormData, 'empleadoRut', '—')) + '</div></div>'
            + '</div><div>'
            + '<div class="mb-3"><div class="hrm-preview-label">Cargo:</div><div class="hrm-preview-value">' + esc(safeGet(window.hrmVacacionesFormData, 'empleadoPuesto', '—')) + '</div></div>'
            + '<div class="mb-3"><div class="hrm-preview-label">Fecha de Solicitud:</div><div class="hrm-preview-value">' + esc(safeGet(window.hrmVacacionesFormData, 'fechaHoyFormat', '')) + '</div></div>'
            + '</div></div>'
            + '<div class="hrm-preview-section-title">Solicitud</div>'
            + '<div style="text-align:justify;margin-bottom:15px;line-height:1.8;color:#333;">Por medio de la presente, solicito formalmente la autorización para hacer uso de mis días de <strong>' + esc(tipoLabel.toLowerCase()) + '</strong> correspondientes al período laboral ' + new Date().getFullYear() + '.</div>'
            + '<div class="hrm-preview-section-title">Período de Ausencia</div>'
            + '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin:20px 0;padding:15px;background:#98ff96;border:2px solid #009929;border-radius:3px;">'
            + '<div style="text-align:center;"><div style="font-weight:bold;font-size:12px;margin-bottom:8px;text-transform:uppercase;color:#003400;">Desde:</div><div style="font-size:18px;font-weight:bold;color:white;padding:12px;background:#009929;border-radius:3px;">' + formatearFechaDisplay(inicioVal) + '</div></div>'
            + '<div style="text-align:center;"><div style="font-weight:bold;font-size:12px;margin-bottom:8px;text-transform:uppercase;color:#003400;">Hasta:</div><div style="font-size:18px;font-weight:bold;color:white;padding:12px;background:#009929;border-radius:3px;">' + formatearFechaDisplay(finVal) + '</div></div>'
            + '<div style="text-align:center;"><div style="font-weight:bold;font-size:12px;margin-bottom:8px;text-transform:uppercase;color:#003400;">Total de Días:</div><div style="font-size:18px;font-weight:bold;color:white;padding:12px;background:#009929;border-radius:3px;">' + totalDias + '</div></div>'
            + '</div>'
            + (descripcion ? '<div style="font-size:13px;font-weight:bold;text-transform:uppercase;color:white;background:#009929;padding:10px 15px;margin:30px 0 15px 0;border-radius:3px;">Comentarios</div><div class="hrm-highlight-box">' + esc(descripcion) + '</div>' : '')
            + '<div style="text-align:justify;margin:30px 0 15px 0;line-height:1.8;color:#333;">Quedo atento(a) a la confirmación y aprobación de esta solicitud. Me comprometo a dejar mis tareas debidamente coordinadas con mi jefatura directa antes de mi ausencia.</div>'
            + '<div style="text-align:justify;margin-bottom:15px;line-height:1.8;color:#333;">Sin otro particular, Saluda atentamente,<br><strong>' + esc(safeGet(window.hrmVacacionesFormData, 'nombreSolicitante', '')) + '</strong></div>'
            + '</div></div>'
            + '<div style="padding:15px 40px;background:white;border-top:1px solid #ddd;text-align:center;"><button type="button" class="btn btn-secondary hrm-preview-close">Cerrar Vista Previa</button></div>'
            + '</div></div>';

        $('body').append(html);
        $(document).on('click', '.hrm-preview-close, .hrm-preview-modal-close', function() {
            $('#previewModal').remove();
        });
    });

    console.log(TAG, '✅ Inicialización completa. jQuery OK. Selectores verificados contra IDs _vac.');
});
