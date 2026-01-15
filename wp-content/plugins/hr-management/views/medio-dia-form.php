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
<div id="alertaSolicitudCreada" class="hrm-success-modal" style="
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    padding: 40px;
    max-width: 500px;
    width: 90%;
    z-index: 10000;
    text-align: center;
">
    <div style="font-size: 60px; margin-bottom: 20px;">✓</div>
    <h2 style="color: #4caf50; margin: 0 0 15px 0; font-size: 28px;">¡Solicitud Creada Exitosamente!</h2>
    <p style="color: #666; margin: 0 0 20px 0; line-height: 1.6;">
        Tu solicitud de medio día ha sido creada y enviada a tu gerente directo y al editor de vacaciones para revisión.
    </p>
    <p style="color: #999; margin: 0 0 20px 0; font-size: 14px;">
        Recibirás un correo de confirmación en tu bandeja de entrada.
    </p>
    <button onclick="document.getElementById('alertaSolicitudCreada').remove(); document.getElementById('alertaFondo').remove();" style="
        background: #4caf50;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 6px;
        font-weight: bold;
        cursor: pointer;
        font-size: 16px;
    ">
        Continuar
    </button>
</div>

<div id="alertaFondo" onclick="document.getElementById('alertaSolicitudCreada').remove(); document.getElementById('alertaFondo').remove();" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    cursor: pointer;
"></div>
<?php endif; ?>

<div class="documento-formal p-5 mx-auto my-3" style="max-width: 900px; background: white;">
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="hrm_enviar_medio_dia">
        <input type="hidden" name="fecha_solicitud" value="<?php echo esc_attr( $fecha_hoy ); ?>">
        <?php wp_nonce_field( 'hrm_solicitud_medio_dia', 'hrm_nonce' ); ?>
        
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
        <div class="titulo-seccion">Solicitud de Medio Día</div>
        
        <div class="parrafo-formal">
            Por medio de la presente, solicito formalmente la autorización para faltar medio día 
            (<strong id="periodo_text">mañana</strong>) 
            correspondiente al período laboral <?php echo esc_html( date( 'Y' ) ); ?>.
        </div>
        
        <!-- TIPO DE AUSENCIA (campo oculto visualmente, pero funcional) -->
        <input type="hidden" name="id_tipo" id="id_tipo" value="1">
        
        <!-- SECCIÓN: FECHA DEL MEDIO DÍA -->
        <div class="titulo-seccion">Fecha del Medio Día</div>
        
        <div style="margin-bottom: 20px;">
            <label for="fecha_medio_dia" class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
            <input type="date" 
                   name="fecha_medio_dia" 
                   id="fecha_medio_dia" 
                   class="form-control" 
                   required
                   onchange="actualizarFechas()">
        </div>
        
        <!-- PERÍODO DEL DÍA -->
        <div style="margin-bottom: 20px;">
            <label class="form-label fw-bold">Período del día <span class="text-danger">*</span></label>
            <div class="opciones-respuesta" style="display: flex; gap: 30px; margin-top: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="radio" id="periodo_manana" name="periodo_ausencia" value="mañana" checked onchange="actualizarTexto()">
                    <label for="periodo_manana" style="margin-bottom: 0; cursor: pointer;">Mañana</label>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="radio" id="periodo_tarde" name="periodo_ausencia" value="tarde" onchange="actualizarTexto()">
                    <label for="periodo_tarde" style="margin-bottom: 0; cursor: pointer;">Tarde</label>
                </div>
            </div>
        </div>
        
        <!-- RESUMEN DEL PERÍODO -->
        <div class="datos-periodo">
            <div class="campo-periodo">
                <div class="campo-periodo-label">Fecha:</div>
                <div class="campo-periodo-valor" id="fecha_display">—</div>
            </div>
            <div class="campo-periodo">
                <div class="campo-periodo-label">Período:</div>
                <div class="campo-periodo-valor" id="periodo_display">Mañana</div>
            </div>
            <div class="campo-periodo">
                <div class="campo-periodo-label">Total de Días:</div>
                <div class="campo-periodo-valor" id="total_dias_display">0.5</div>
            </div>
        </div>
        
        <input type="hidden" name="fecha_inicio" id="fecha_inicio" value="">
        <input type="hidden" name="fecha_fin" id="fecha_fin" value="">
        <input type="hidden" name="total_dias" id="total_dias_input" value="0.5">
        
        <!-- CAMPO DE COMENTARIOS (opcional) -->
        <div style="margin-bottom: 20px;">
            <label for="descripcion" class="form-label fw-bold">Motivo (opcional)</label>
            <textarea name="descripcion" 
                      id="descripcion" 
                      rows="3" 
                      class="form-control"
                      placeholder="Información adicional relevante..."></textarea>
        </div>
        
        <!-- CIERRE FORMAL -->
        <div class="parrafo-formal" style="margin-top: 30px;">
            Quedo atento(a) a la confirmación y aprobación de esta solicitud. Me comprometo a dejar mis tareas 
            debidamente coordinadas con mi jefatura directa.
        </div>
        
        <div class="parrafo-formal">
            Sin otro particular, Saluda atentamente,<br>
            <strong><?php echo esc_html( $nombre_solicitante ); ?></strong>
        </div>
        
        <!-- SECCIÓN RECURSOS HUMANOS (solo visible para admins) -->
        <?php if ( $is_admin ) : ?>
        <div class="seccion-rrhh">
            <div class="titulo-seccion" style="margin-top: 0;">Recursos Humanos / Jefatura Directa</div>
            
            <div style="margin-bottom: 20px;">
                <div class="form-label fw-bold" style="margin-bottom: 10px;">Respuesta:</div>
                <div class="opciones-respuesta">
                    <div class="opcion-respuesta">
                        <input type="radio" id="respuesta_aceptado" name="respuesta_rrhh" value="aceptado">
                        <label for="respuesta_aceptado">Aceptado</label>
                    </div>
                    <div class="opcion-respuesta">
                        <input type="radio" id="respuesta_rechazado" name="respuesta_rrhh" value="rechazado">
                        <label for="respuesta_rechazado">Rechazado</label>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
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
            
            <div style="margin-bottom: 20px;">
                <div style="text-align: center; font-weight: bold; font-size: 12px; text-transform: uppercase; margin-bottom: 10px;">
                    Firma Gerente / Editor de Vacaciones
                </div>
                <div class="linea-firma"></div>
            </div>
            
            <div style="margin-bottom: 20px;">
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
    const fechaInput = document.getElementById('fecha_medio_dia');
    
    // La fecha debe ser hoy o posterior (para permitir faltar en la tarde de hoy)
    const hoy = new Date();
    const fechaMinimaStr = hoy.toISOString().split('T')[0];
    fechaInput.min = fechaMinimaStr;
    
    // Función para validar si una fecha es fin de semana
    function esFinDeSemana(fechaStr) {
        const fecha = new Date(fechaStr + 'T00:00:00');
        const dia = fecha.getDay(); // 0 = domingo, 6 = sábado
        return dia === 0 || dia === 6;
    }
    
    // Función para formatear fecha
    function formatearFecha(fechaStr) {
        if (!fechaStr) return '—';
        const fecha = new Date(fechaStr + 'T00:00:00');
        return fecha.toLocaleDateString('es-CL', { year: 'numeric', month: '2-digit', day: '2-digit' });
    }
    
    // Función para actualizar fechas (inicio y fin son iguales)
    window.actualizarFechas = function() {
        const fecha = document.getElementById('fecha_medio_dia').value;
        
        if (!fecha) {
            document.getElementById('fecha_display').textContent = '—';
            document.getElementById('fecha_inicio').value = '';
            document.getElementById('fecha_fin').value = '';
            return;
        }
        
        if (esFinDeSemana(fecha)) {
            alert('⚠️ La fecha no puede ser un fin de semana.');
            document.getElementById('fecha_medio_dia').value = '';
            document.getElementById('fecha_display').textContent = '—';
            return;
        }
        
        document.getElementById('fecha_display').textContent = formatearFecha(fecha);
        document.getElementById('fecha_inicio').value = fecha;
        document.getElementById('fecha_fin').value = fecha;
    };
    
    // Función para actualizar el texto del período
    window.actualizarTexto = function() {
        const periodo = document.querySelector('input[name="periodo_ausencia"]:checked').value;
        document.getElementById('periodo_text').textContent = periodo;
        document.getElementById('periodo_display').textContent = periodo.charAt(0).toUpperCase() + periodo.slice(1);
    };
    
    // Validar fecha cuando cambia
    fechaInput.addEventListener('change', function () {
        if (esFinDeSemana(fechaInput.value)) {
            alert('⚠️ La fecha no puede ser un fin de semana.');
            fechaInput.value = '';
            actualizarFechas();
            return;
        }
        
        actualizarFechas();
    });
});
</script>
