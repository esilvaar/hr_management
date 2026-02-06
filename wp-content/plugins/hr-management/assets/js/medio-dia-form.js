/**
 * medio-dia-form.js — Lógica del formulario "Medio Día"
 * ─────────────────────────────────────────────────────
 * Usa jQuery(document).ready() para compatibilidad con WordPress.
 * Todos los selectores usan IDs con sufijo _medio.
 *
 * Mapa HTML → JS (fuente de verdad: views/medio-dia-form.php)
 *   #fecha_medio_dia_medio     date input principal (VISIBLE)
 *   #fecha_inicio_medio        hidden input
 *   #fecha_fin_medio           hidden input
 *   #fecha_display_medio       div resumen fecha
 *   #periodo_manana_medio      radio "Mañana"
 *   #periodo_tarde_medio       radio "Tarde"
 *   #periodo_text_medio        strong en texto formal
 *   #periodo_display_medio     div resumen período
 *   #total_dias_display_medio  div resumen total (siempre 0.5)
 *   #total_dias_input_medio    hidden input (siempre 0.5)
 *   #descripcion_medio         textarea motivo
 *   #id_tipo_medio             hidden input tipo
 *   #btnCancelarMedio          botón cancelar (onclick inline en HTML)
 *   #alertaSolicitudCreada_medio  modal éxito
 *   #alertaFondo_medio            backdrop éxito
 *
 * NOTA: El botón Cancelar se maneja TAMBIÉN con onclick="fuerzaBrutaLimpiarMedioDia()"
 * directamente en el HTML. Este JS añade lógica adicional por ID como respaldo.
 */
jQuery(document).ready(function($) {
    'use strict';

    var TAG = '[medio-dia-form.js]';
    console.log(TAG, '▶ Inicializando...');

    // ═══════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════

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
    // REFERENCIAS A ELEMENTOS (con advertencias)
    // ═══════════════════════════════════════════════════

    function $id(id) {
        var $el = $('#' + id);
        if ($el.length === 0) {
            console.warn(TAG, '⚠️ Selector no encontrado: #' + id);
        }
        return $el;
    }

    var $fechaInput      = $id('fecha_medio_dia_medio');
    var $fechaDisplay    = $id('fecha_display_medio');
    var $periodoText     = $id('periodo_text_medio');
    var $periodoDisplay  = $id('periodo_display_medio');
    var $hiddenInicio    = $id('fecha_inicio_medio');
    var $hiddenFin       = $id('fecha_fin_medio');
    var $totalDiasDisplay = $id('total_dias_display_medio');
    var $totalDiasInput  = $id('total_dias_input_medio');
    var $descripcion     = $id('descripcion_medio');

    if ($fechaInput.length === 0) {
        console.warn(TAG, '🛑 #fecha_medio_dia_medio no encontrado. Abortando inicialización.');
        return;
    }

    // Fecha mínima: hoy
    var hoyISO = new Date().toISOString().split('T')[0];
    $fechaInput.attr('min', hoyISO);
    console.log(TAG, 'Fecha mínima establecida:', hoyISO);

    // ═══════════════════════════════════════════════════
    // FUNCIÓN: Actualizar fecha en resumen
    // ═══════════════════════════════════════════════════

    function actualizarFechas() {
        var val = $fechaInput.val();
        console.log(TAG, 'actualizarFechas() — valor:', val);

        if (!val) {
            $fechaDisplay.text('—');
            $hiddenInicio.val('');
            $hiddenFin.val('');
            console.log(TAG, 'Fecha limpiada (sin valor).');
            return;
        }

        // Validar fin de semana
        if (esFinDeSemana(val)) {
            alert('⚠️ La fecha no puede ser un fin de semana.');
            $fechaInput.val('');
            $fechaDisplay.text('—');
            $hiddenInicio.val('');
            $hiddenFin.val('');
            return;
        }

        // Actualizar display
        var fechaFormateada = formatearFechaDisplay(val);
        $fechaDisplay.text(fechaFormateada);
        console.log(TAG, '✅ #fecha_display_medio actualizado a:', fechaFormateada);

        // Actualizar hidden inputs (inicio y fin son iguales en medio día)
        var isoVal = val; // input[type=date] ya entrega YYYY-MM-DD
        $hiddenInicio.val(isoVal);
        $hiddenFin.val(isoVal);
        console.log(TAG, '✅ Hidden inputs actualizados:', isoVal);
    }

    // Exponer globalmente
    window.actualizarFechas = actualizarFechas;

    // ═══════════════════════════════════════════════════
    // FUNCIÓN: Actualizar período (Mañana/Tarde)
    // ═══════════════════════════════════════════════════

    function actualizarPeriodo(valorExplicito) {
        // Si se pasa un valor explícito (del evento click), usarlo directamente.
        // Si no, leer del DOM pero SOLO dentro del contenedor de medio día.
        var valor;
        if (typeof valorExplicito === 'string' && valorExplicito.length > 0) {
            valor = valorExplicito;
        } else {
            // Buscar solo radios dentro del form de medio día para evitar conflicto
            var $selected = $fechaInput.closest('form').find('input[name="periodo_ausencia"]:checked');
            valor = $selected.length ? $selected.val() : 'mañana';
        }

        var capitalizado = valor.charAt(0).toUpperCase() + valor.slice(1);
        $periodoText.text(valor);
        $periodoDisplay.text(capitalizado);
        console.log(TAG, '✅ Período actualizado a:', capitalizado, '(valor raw:', valor, ')');
    }

    // Exponer globalmente
    window.actualizarTexto = actualizarPeriodo;

    // ═══════════════════════════════════════════════════
    // EVENT LISTENERS
    // ═══════════════════════════════════════════════════

    // --- Cambio de fecha ---
    $fechaInput.on('change input', function() {
        console.log(TAG, 'Evento change/input en #fecha_medio_dia_medio:', $(this).val());
        actualizarFechas();
    });

    // --- Cambio de período (radio buttons) ---
    // Usamos el valor del radio clickeado directamente (this.value) para
    // evitar que :checked apunte a un radio del otro formulario.
    $fechaInput.closest('form').find('input[name="periodo_ausencia"]').on('change click', function() {
        var valorClickeado = this.value;
        console.log(TAG, 'Evento change/click en radio periodo_ausencia — this.value:', valorClickeado);
        actualizarPeriodo(valorClickeado);
    });

    // ═══════════════════════════════════════════════════
    // ALERTA DE ÉXITO
    // ═══════════════════════════════════════════════════

    // Las alertas solo existen en el DOM cuando ?solicitud_creada=1,
    // por lo que es normal que no se encuentren. Usamos $ directo sin $id para no generar warnings.
    var $alerta     = $('#alertaSolicitudCreada_medio');
    var $alertaFondo = $('#alertaFondo_medio');

    $alerta.find('.hrm-success-close').on('click', function() {
        $alerta.remove();
        $alertaFondo.remove();
    });
    $alertaFondo.on('click', function() {
        $alerta.remove();
        $alertaFondo.remove();
    });

    // ═══════════════════════════════════════════════════
    // BOTÓN CANCELAR (respaldo jQuery además del onclick inline)
    // ═══════════════════════════════════════════════════

    $('#btnCancelarMedio').on('click', function(e) {
        e.preventDefault();
        console.log(TAG, 'Botón Cancelar clickeado (jQuery handler).');

        $fechaInput.val('');
        $descripcion.val('');
        $fechaDisplay.text('—');
        $periodoDisplay.text('Mañana');
        $periodoText.text('mañana');
        $hiddenInicio.val('');
        $hiddenFin.val('');
        $totalDiasDisplay.text('0.5');
        $totalDiasInput.val('0.5');

        // Reset radio a "Mañana"
        $id('periodo_manana_medio').prop('checked', true);

        // Campos admin
        $id('nombre_jefe_medio').val('');
        $id('observaciones_rrhh_medio').val('');

        console.log(TAG, '✅ Formulario Medio Día limpiado.');
    });

    console.log(TAG, '✅ Inicialización completa. jQuery OK. Selectores verificados contra IDs _medio.');
});
