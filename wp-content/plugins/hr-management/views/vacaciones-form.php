<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Cargar estilos CSS
wp_enqueue_style( 'hrm-vacaciones-formulario', plugins_url( 'hr-management/assets/css/vacaciones-formulario.css' ), array(), '1.0.0' );

// Obtener datos del empleado logueado usando la función centralizada
$empleado_data = hrm_obtener_datos_empleado();

// Obtener nombre del usuario si no hay datos de empleado
$current_user = wp_get_current_user();
$nombre_solicitante = $empleado_data 
    ? $empleado_data->nombre . ' ' . $empleado_data->apellido 
    : $current_user->display_name;

$is_admin = current_user_can( 'manage_options' );
$fecha_hoy = current_time( 'Y-m-d' );
$fecha_hoy_format = current_time( 'd/m/Y' );

// Verificar si la solicitud fue creada exitosamente
$solicitud_creada = isset( $_GET['solicitud_creada'] ) && $_GET['solicitud_creada'] === '1';
?>

<?php if ( $solicitud_creada ) : ?>
<div id="alertaSolicitudCreada" class="hrm-success-modal">
    <div class="hrm-success-icon">✓</div>
    <h2 class="hrm-success-title">¡Solicitud Creada Exitosamente!</h2>
    <p class="hrm-success-text">
        Tu solicitud de vacaciones ha sido creada y enviada a tu gerente directo y al editor de vacaciones para revisión.
    </p>
    <p class="hrm-success-small">
        Recibirás un correo de confirmación en tu bandeja de entrada.
    </p>
    <button onclick="document.getElementById('alertaSolicitudCreada').remove(); document.getElementById('alertaFondo').remove();" class="hrm-success-button">
        Continuar
    </button>
</div>

<div id="alertaFondo" class="hrm-success-backdrop" onclick="document.getElementById('alertaSolicitudCreada').remove(); document.getElementById('alertaFondo').remove();"></div>
<?php endif; ?>

<div class="documento-formal p-5 mx-auto my-3">
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="hrm_enviar_vacaciones">
        <input type="hidden" name="fecha_solicitud" value="<?php echo esc_attr( $fecha_hoy ); ?>">
        <?php wp_nonce_field( 'hrm_solicitud_vacaciones', 'hrm_nonce' ); ?>
        
        <!-- ENCABEZADO CON DATOS DEL SOLICITANTE -->
        <div class="seccion-encabezado">
            <!-- Columna izquierda -->
            <div>
                <div class="campo-info">
                    <div class="label-info">Nombre del Solicitante:</div>
                    <div class="valor-info">
                        <?php echo esc_html( $nombre_solicitante ); ?>
                    </div>
                </div>
                
                <div class="campo-info">
                    <div class="label-info">RUT:</div>
                    <div class="valor-info">
                        <?php echo esc_html( $empleado_data ? $empleado_data->rut : '—' ); ?>
                    </div>
                </div>
            </div>
            
            <!-- Columna derecha -->
            <div>
                <div class="campo-info">
                    <div class="label-info">Cargo:</div>
                    <div class="valor-info">
                        <?php echo esc_html( $empleado_data ? $empleado_data->puesto : '—' ); ?>
                    </div>
                </div>
                
                <div class="campo-info">
                    <div class="label-info">Fecha de Solicitud:</div>
                    <div class="valor-info">
                        <?php echo esc_html( $fecha_hoy_format ); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN: SOLICITUD FORMAL -->
        <div class="titulo-seccion">Solicitud</div>
        
        <div class="parrafo-formal">
            Por medio de la presente, solicito formalmente la autorización para hacer uso de mis días de 
            <span id="tipo_ausencia_display">ausencia</span> 
            correspondientes al período laboral <?php echo esc_html( date( 'Y' ) ); ?>.
        </div>
        
        <!-- TIPO DE AUSENCIA (campo oculto visualmente, pero funcional) -->
        <div class="mb-3 d-none">
            <label for="id_tipo" class="form-label fw-bold d-block mb-2">Tipo de ausencia <span class="text-danger">*</span></label>
            <?php 
            $lista_tipos = function_exists('hrm_get_tipos_ausencia_definidos') ? hrm_get_tipos_ausencia_definidos() : [];
            ?>
            <select name="id_tipo" id="id_tipo" class="form-select" required onchange="actualizarTipoAusencia()">
                <option value="">— Seleccione el tipo —</option>
                <?php foreach ( $lista_tipos as $id => $label ) : ?>
                    <option value="<?= esc_attr( $id ) ?>" data-label="<?= esc_attr( strtolower($label) ) ?>" selected>
                        <?= esc_html( $label ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- SCRIPT para establecer automáticamente el tipo de ausencia a Vacaciones -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var selectTipo = document.getElementById('id_tipo');
                if (selectTipo && selectTipo.options.length > 1) {
                    selectTipo.value = selectTipo.options[1].value; // Seleccionar la primera opción (Vacaciones)
                    actualizarTipoAusencia();
                }
            });
        </script>
        
        <!-- SECCIÓN: PERIODO DE VACACIONES -->
        <div class="titulo-seccion">Período de Ausencia</div>
        
        <div class="mb-3">
            <label for="fecha_inicio" class="form-label fw-bold">Fecha de inicio <span class="text-danger">*</span></label>
            <input type="date" 
                   name="fecha_inicio" 
                   id="fecha_inicio" 
                   class="form-control" 
                   required
                   onchange="calcularDias()">
        </div>
        
        <div class="mb-3">
            <label for="fecha_fin" class="form-label fw-bold">Fecha de fin <span class="text-danger">*</span></label>
            <input type="date" 
                   name="fecha_fin" 
                   id="fecha_fin" 
                   class="form-control" 
                   required
                   onchange="calcularDias()">
        </div>
        
        <!-- RESUMEN DEL PERÍODO -->
        <div class="datos-periodo">
            <div class="campo-periodo">
                <div class="campo-periodo-label">Desde:</div>
                <div class="campo-periodo-valor" id="fecha_inicio_display">—</div>
            </div>
            <div class="campo-periodo">
                <div class="campo-periodo-label">Hasta:</div>
                <div class="campo-periodo-valor" id="fecha_fin_display">—</div>
            </div>
            <div class="campo-periodo">
                <div class="campo-periodo-label">Total de Días:</div>
                <div class="campo-periodo-valor" id="total_dias_display">0</div>
            </div>
        </div>
        
        <input type="hidden" name="total_dias" id="total_dias_input" value="0">
        
        <!-- CAMPO DE COMENTARIOS (opcional) -->
        <div class="mb-3">
            <label for="descripcion" class="form-label fw-bold">Comentarios adicionales (opcional)</label>
            <textarea name="descripcion" 
                      id="descripcion" 
                      rows="3" 
                      class="form-control"
                      placeholder="Información adicional relevante..."></textarea>
        </div>
        
        <!-- CIERRE FORMAL -->
        <div class="parrafo-formal mt-4">
            Quedo atento(a) a la confirmación y aprobación de esta solicitud. Me comprometo a dejar mis tareas 
            debidamente coordinadas con mi jefatura directa antes de mi ausencia.
        </div>
        
        <div class="parrafo-formal">
            Sin otro particular, Saluda atentamente,<br>
            <strong><?php echo esc_html( $nombre_solicitante ); ?></strong>
        </div>
        
        <!-- SECCIÓN RECURSOS HUMANOS (solo visible para admins) -->
        <?php if ( $is_admin ) : ?>
        <div class="seccion-rrhh">
            <div class="titulo-seccion" style="margin-top: 0;">Recursos Humanos / Jefatura Directa</div>
            
            <div class="mb-3">
                <div class="form-label fw-bold mb-2">Respuesta:</div>
                <div class="opciones-respuesta d-flex gap-4 mt-2">
                    <div class="opcion-respuesta d-flex align-items-center gap-2">
                        <input type="radio" id="respuesta_aceptado" name="respuesta_rrhh" value="aceptado">
                        <label for="respuesta_aceptado">Aceptado</label>
                    </div>
                    <div class="opcion-respuesta d-flex align-items-center gap-2">
                        <input type="radio" id="respuesta_rechazado" name="respuesta_rrhh" value="rechazado">
                        <label for="respuesta_rechazado">Rechazado</label>
                    </div>
                </div>
            </div>
            
            <div class="hrm-grid-two mb-3">
                <div>
                    <label for="nombre_jefe" class="form-label fw-bold">Nombre de Jefe/RRHH:</label>
                    <input type="text" 
                           name="nombre_jefe" 
                           id="nombre_jefe" 
                           class="form-control"
                           placeholder="Nombre completo">
                </div>
                
                <div>
                    <label for="fecha_respuesta" class="form-label fw-bold">Fecha de Respuesta:</label>
                    <input type="date" 
                           name="fecha_respuesta" 
                           id="fecha_respuesta" 
                           class="form-control"
                           value="<?php echo esc_attr( $fecha_hoy ); ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <div class="hrm-sign-title">
                    Firma Gerente / Editor de Vacaciones
                </div>
                <div class="linea-firma"></div>
            </div>
            
            <div class="mb-3">
                <label for="observaciones_rrhh" class="form-label fw-bold">Observaciones (Opcional):</label>
                <textarea name="observaciones_rrhh" 
                          id="observaciones_rrhh" 
                          rows="4" 
                          class="form-control"
                          placeholder="Observaciones, comentarios o razones de rechazo..."></textarea>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- BOTONES DE ACCIÓN -->
        <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-5">
            <button type="submit" name="hrm_enviar_solicitud" class="btn btn-primary px-5">
                <span class="dashicons dashicons-yes"></span> Enviar Solicitud
            </button>
            <button type="button" class="btn btn-secondary px-5" onclick="history.back();">
                <span class="dashicons dashicons-no"></span> Cancelar
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inicio = document.getElementById('fecha_inicio');
    const fin = document.getElementById('fecha_fin');
    const tipoSelect = document.getElementById('id_tipo');
    
    if (!inicio || !fin) return;
    
    // La fecha de inicio debe ser hoy en adelante
    const hoy = new Date();
    const fechaMinimaStr = hoy.toISOString().split('T')[0];
    inicio.min = fechaMinimaStr;
    
    // Objeto para almacenar feriados (clave: YYYY-MM-DD)
    let feriadosChile = {};
    
    // Cargar feriados desde el servidor
    async function cargarFeriados() {
        try {
            const anoActual = new Date().getFullYear();
            const anos = [anoActual, anoActual + 1]; // Cargar año actual y siguiente
            
            for (let ano of anos) {
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=hrm_get_feriados&ano=' + ano);
                const data = await response.json();
                if (data.success && data.data) {
                    feriadosChile = { ...feriadosChile, ...data.data };
                }
            }
        } catch (error) {
            console.log('No se pudieron cargar los feriados:', error);
        }
    }
    
    // Cargar feriados al inicio
    cargarFeriados();
    
    // Función para validar si una fecha es fin de semana
    function esFinDeSemana(fechaStr) {
        const fecha = new Date(fechaStr + 'T00:00:00');
        const dia = fecha.getDay(); // 0 = domingo, 6 = sábado
        return dia === 0 || dia === 6;
    }
    
    // Función para validar si una fecha es feriado
    function esFeriado(fechaStr) {
        return feriadosChile.hasOwnProperty(fechaStr);
    }
    
    // Función para calcular días laborales (sin feriados)
    function contarDiasLaborales(fechaInicio, fechaFin) {
        let fecha = new Date(fechaInicio + 'T00:00:00');
        let fechaTermino = new Date(fechaFin + 'T00:00:00');
        let dias = 0;
        
        while (fecha <= fechaTermino) {
            const diaSemana = fecha.getDay();
            const fechaStr = fecha.toISOString().split('T')[0];
            
            // No contar si es fin de semana o feriado
            if (diaSemana !== 0 && diaSemana !== 6 && !esFeriado(fechaStr)) {
                dias++;
            }
            fecha.setDate(fecha.getDate() + 1);
        }
        
        return dias;
    }
    
    // Función para formatear fecha
    function formatearFecha(fechaStr) {
        if (!fechaStr) return '—';
        const fecha = new Date(fechaStr + 'T00:00:00');
        return fecha.toLocaleDateString('es-CL', { year: 'numeric', month: '2-digit', day: '2-digit' });
    }
    
    // Calcular días automáticamente
    window.calcularDias = function() {
        const fechaInicio = inicio.value;
        const fechaFin = fin.value;
        
        if (fechaInicio && fechaFin) {
            const dias = contarDiasLaborales(fechaInicio, fechaFin);
            document.getElementById('total_dias_display').textContent = dias;
            document.getElementById('total_dias_input').value = dias;
            document.getElementById('fecha_inicio_display').textContent = formatearFecha(fechaInicio);
            document.getElementById('fecha_fin_display').textContent = formatearFecha(fechaFin);
        }
    };
    
    // Actualizar nombre de tipo de ausencia en el texto
    window.actualizarTipoAusencia = function() {
        const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
        const label = selectedOption.getAttribute('data-label');
        document.getElementById('tipo_ausencia_display').textContent = label || 'ausencia';
    };
    
    // Validar fecha de inicio
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
        
        if (fin.value && fin.value < inicio.value) {
            fin.value = '';
        }
        
        calcularDias();
    });
    
    // Validar fecha de fin
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
    
    // FUNCIONALIDAD DE VISTA PREVIA
    document.getElementById('btnPreview').addEventListener('click', function() {
        // Obtener valores del formulario
        const tipoSelect = document.getElementById('id_tipo');
        const tipoLabel = tipoSelect.options[tipoSelect.selectedIndex].text;
        const inicio = document.getElementById('fecha_inicio').value;
        const fin = document.getElementById('fecha_fin').value;
        const totalDias = document.getElementById('total_dias_display').textContent;
        const descripcion = document.getElementById('descripcion').value;
        
        // Validar que tenga los datos básicos
        if (!tipoSelect.value || !inicio || !fin) {
            alert('⚠️ Por favor completa los campos obligatorios (Tipo de ausencia, fechas) para ver la vista previa.');
            return;
        }
        
        // Función para escapar HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // Función para formatear fecha
        function formatearFecha(fechaStr) {
            if (!fechaStr) return '—';
            const fecha = new Date(fechaStr + 'T00:00:00');
            return fecha.toLocaleDateString('es-CL', { year: 'numeric', month: '2-digit', day: '2-digit' });
        }
        
        // Crear el HTML de la vista previa
        const previewHTML = `
            <div id="previewModal" class="hrm-preview-modal">
                <div class="hrm-preview-modal-content">
                    <div class="hrm-preview-modal-header">
                        <h2 class="hrm-preview-modal-title">Vista Previa de la Solicitud</h2>
                        <button onclick="document.getElementById('previewModal').remove()" class="hrm-preview-modal-close">&times;</button> 
                    </div>
                    
                    <div class="hrm-preview-modal-body">
                        <div class="hrm-preview-modal-box">
                            <!-- Encabezado -->
                            <div class="hrm-preview-grid">
                                <div>
                                    <div class="mb-3">
                                        <div class="hrm-preview-label">Nombre del Solicitante:</div>
                                        <div class="hrm-preview-value">
                                            <?php echo esc_html( $nombre_solicitante ); ?>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="hrm-preview-label">RUT:</div>
                                        <div class="hrm-preview-value">
                                            <?php echo esc_html( $empleado_data ? $empleado_data->rut : '—' ); ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="mb-3">
                                        <div class="hrm-preview-label">Cargo:</div>
                                        <div class="hrm-preview-value">
                                            <?php echo esc_html( $empleado_data ? $empleado_data->puesto : '—' ); ?>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="hrm-preview-label">Fecha de Solicitud:</div>
                                        <div class="hrm-preview-value">
                                            <?php echo esc_html( $fecha_hoy_format ); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Solicitud -->
                            <div class="hrm-preview-section-title">Solicitud</div>
                            
                            <div style="text-align: justify; margin-bottom: 15px; line-height: 1.8; color: #333;">
                                Por medio de la presente, solicito formalmente la autorización para hacer uso de mis días de <strong>${escapeHtml(tipoLabel.toLowerCase())}</strong> correspondientes al período laboral ${new Date().getFullYear()}.
                            </div>
                            
                            <!-- Período -->
                            <div class="hrm-preview-section-title">Período de Ausencia</div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 20px 0; padding: 15px; background: #98ff96; border: 2px solid #009929; border-radius: 3px;">
                                <div style="text-align: center;">
                                    <div style="font-weight: bold; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; color: #003400;">Desde:</div>
                                    <div style="font-size: 18px; font-weight: bold; color: white; padding: 12px; background: #009929; border-radius: 3px;">${formatearFecha(inicio)}</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-weight: bold; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; color: #003400;">Hasta:</div>
                                    <div style="font-size: 18px; font-weight: bold; color: white; padding: 12px; background: #009929; border-radius: 3px;">${formatearFecha(fin)}</div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-weight: bold; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; color: #003400;">Total de Días:</div>
                                    <div style="font-size: 18px; font-weight: bold; color: white; padding: 12px; background: #009929; border-radius: 3px;">${totalDias}</div>
                                </div>
                            </div>
                            
                            ${descripcion ? `
                            <div style="font-size: 13px; font-weight: bold; text-transform: uppercase; color: white; background: #009929; padding: 10px 15px; margin: 30px 0 15px 0; border-radius: 3px;">Comentarios</div>
                            <div class="hrm-highlight-box">
                                ${escapeHtml(descripcion)}
                            </div>
                            ` : ''}
                            
                            <!-- Cierre -->
                            <div style="text-align: justify; margin: 30px 0 15px 0; line-height: 1.8; color: #333;">
                                Quedo atento(a) a la confirmación y aprobación de esta solicitud. Me comprometo a dejar mis tareas debidamente coordinadas con mi jefatura directa antes de mi ausencia.
                            </div>
                            
                            <div style="text-align: justify; margin-bottom: 15px; line-height: 1.8; color: #333;">
                                Sin otro particular, Saluda atentamente,<br>
                                <strong><?php echo esc_html( $nombre_solicitante ); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding: 15px 40px; background: white; border-top: 1px solid #ddd; text-align: center;">
                        <button onclick="document.getElementById('previewModal').remove()" class="btn btn-secondary">Cerrar Vista Previa</button>
                    </div>
                </div>
            </div>
        `;
        
        // Insertar el modal
        document.body.insertAdjacentHTML('beforeend', previewHTML);
    });
});
</script>
