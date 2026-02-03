<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Cargar estilos CSS
wp_enqueue_style( 'hrm-vacaciones-formulario', plugins_url( 'hr-management/assets/css/vacaciones-formulario.css' ), array(), '1.0.0' );

// Encolar el comportamiento de la vista (JS extraído de inline)
wp_enqueue_script(
    'hrm-vacaciones-form',
    HRM_PLUGIN_URL . 'assets/js/vacaciones-form.js',
    array(),
    HRM_PLUGIN_VERSION,
    true
);
wp_localize_script( 'hrm-vacaciones-form', 'hrmVacacionesFormData', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nombreSolicitante' => isset( $nombre_solicitante ) ? esc_js( $nombre_solicitante ) : '',
    'empleadoRut' => isset( $empleado_data ) && ! empty( $empleado_data->rut ) ? esc_js( $empleado_data->rut ) : '—',
    'empleadoPuesto' => isset( $empleado_data ) && ! empty( $empleado_data->puesto ) ? esc_js( $empleado_data->puesto ) : '—',
    'fechaHoyFormat' => isset( $fecha_hoy_format ) ? esc_js( $fecha_hoy_format ) : '' ,
) );

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
    <button type="button" class="hrm-success-button hrm-success-close">
        Continuar
    </button>
</div>

<div id="alertaFondo" class="hrm-success-backdrop hrm-success-close"></div>
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
            <select name="id_tipo" id="id_tipo" class="form-select" required>
                <option value="">— Seleccione el tipo —</option>
                <?php foreach ( $lista_tipos as $id => $label ) : ?>
                    <option value="<?= esc_attr( $id ) ?>" data-label="<?= esc_attr( strtolower($label) ) ?>" selected>
                        <?= esc_html( $label ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- SECCIÓN: PERIODO DE VACACIONES -->
        <div class="titulo-seccion">Período de Ausencia</div>
        
        <div class="mb-3">
            <label for="fecha_inicio" class="form-label fw-bold">Fecha de inicio <span class="text-danger">*</span></label>
            <input type="date" 
                   name="fecha_inicio" 
                   id="fecha_inicio" 
                   class="form-control" 
                   required>
        </div>
        
        <div class="mb-3">
            <label for="fecha_fin" class="form-label fw-bold">Fecha de fin <span class="text-danger">*</span></label>
            <input type="date" 
                   name="fecha_fin" 
                   id="fecha_fin" 
                   class="form-control" 
                   required> 
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
            <div class="titulo-seccion myplugin-mt-0">Recursos Humanos / Jefatura Directa</div>
            
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
            <button type="button" class="btn btn-secondary px-5 hrm-cancel-btn">
                <span class="dashicons dashicons-no"></span> Cancelar
            </button>
        </div>
    </form>
</div>

<?php // JS moved to assets/js/vacaciones-form.js - behavior and preview handled by the enqueued script ?>

<?php // The inline JavaScript was removed and moved to `assets/js/vacaciones-form.js`. This ensures behavior is loaded via enqueued assets and avoids duplicate listeners or exposed JS in the page output. ?>

