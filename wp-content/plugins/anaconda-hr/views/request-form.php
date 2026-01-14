<div class="wrap">
    <h1 class="wp-heading-inline">Solicitar Vacaciones / Ausencia</h1>
    <hr class="wp-header-end">

    <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
        <h2 class="title">Nueva Solicitud</h2>
        
        <form method="post" id="form_solicitud">
            <?php wp_nonce_field('ahr_nonce_create', 'ahr_security'); ?>
            <input type="hidden" name="ahr_action" value="nueva_solicitud">
            
            <table class="form-table">
                <tr>
                    <th><label for="tipo_ausencia">Tipo de Ausencia</label></th>
                    <td>
                        <select name="tipo" id="tipo_ausencia" class="regular-text" required>
                            <option value="Vacaciones">Vacaciones Legales</option>
                            <option value="Licencia">Licencia Médica</option>
                            <option value="Permiso">Permiso Administrativo (Sin goce)</option>
                            <option value="DiaAdministrativo">Día Administrativo</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="fecha_inicio">Desde</label></th>
                    <td><input type="date" name="fecha_inicio" id="fecha_inicio" required></td>
                </tr>
                <tr>
                    <th><label for="fecha_fin">Hasta (Inclusive)</label></th>
                    <td><input type="date" name="fecha_fin" id="fecha_fin" required></td>
                </tr>
            </table>

            <div id="info_dias" class="notice notice-info inline" style="display:none; margin: 15px 0; padding: 10px;">
                <p><strong>Días a solicitar:</strong> <span id="contador_dias" style="font-size: 1.2em; font-weight: bold;">0</span> <span id="texto_tipo_dias">días hábiles</span>.</p>
            </div>

            <p>
                <label for="motivo"><strong>Motivo / Comentarios (Opcional):</strong></label><br>
                <textarea name="motivo" id="motivo" rows="3" class="large-text code" placeholder="Ej: Vacaciones familiares al sur..."></textarea>
            </p>

            <p class="submit">
                <button type="submit" class="button button-primary button-large">Enviar Solicitud</button>
            </p>
        </form>
    </div>

    <br>
    <div class="card" style="padding: 0;">
        <h3 style="padding: 15px; margin: 0; background: #f0f0f1; border-bottom: 1px solid #ccc;">Mi Historial de Solicitudes</h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="20%">Desde</th>
                    <th width="20%">Hasta</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($mis_solicitudes)) : ?>
                    <?php foreach($mis_solicitudes as $m): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($m->fecha_inicio)); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($m->fecha_fin)); ?></td> <td><?php echo esc_html($m->tipo); ?></td>
                        <td>
                            <?php 
                            // Etiquetas de colores según estado
                            $color = '#999';
                            if($m->estado == 'APROBADO') $color = '#46b450'; // Verde
                            if($m->estado == 'RECHAZADO') $color = '#dc3232'; // Rojo
                            if($m->estado == 'PENDIENTE') $color = '#ffb900'; // Naranja
                            ?>
                            <span style="color:white; background:<?php echo $color; ?>; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                <?php echo strtoupper($m->estado); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">No tienes solicitudes registradas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fInicio = document.getElementById('fecha_inicio');
    const fFin = document.getElementById('fecha_fin');
    const infoBox = document.getElementById('info_dias');
    const contador = document.getElementById('contador_dias');
    const tipoAusencia = document.getElementById('tipo_ausencia');
    const textoTipo = document.getElementById('texto_tipo_dias');

    function calcularDias() {
        if(fInicio.value && fFin.value) {
            let d1 = new Date(fInicio.value);
            let d2 = new Date(fFin.value);

            // Validación básica
            if (d2 < d1) {
                alert("La fecha de término no puede ser anterior a la de inicio.");
                fFin.value = "";
                infoBox.style.display = 'none';
                return;
            }

            // Lógica de días hábiles vs corridos
            let diasTotales = 0;
            let esLicencia = (tipoAusencia.value === 'Licencia');

            // Clonar fecha para iterar
            let current = new Date(d1);
            while (current <= d2) {
                let dayOfWeek = current.getUTCDay(); // 0 = Domingo, 6 = Sábado
                
                // Si es Licencia, cuentan todos (días corridos). 
                // Si es Vacaciones, solo cuentan Lunes a Viernes (días hábiles).
                if (esLicencia) {
                    diasTotales++;
                } else {
                    if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                        diasTotales++;
                    }
                }
                current.setDate(current.getDate() + 1);
            }

            // Mostrar resultado
            contador.innerText = diasTotales;
            textoTipo.innerText = esLicencia ? "días corridos" : "días hábiles";
            infoBox.style.display = 'block';
        }
    }

    // Escuchar cambios
    fInicio.addEventListener('change', calcularDias);
    fFin.addEventListener('change', calcularDias);
    tipoAusencia.addEventListener('change', calcularDias);
});
</script>