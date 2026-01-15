<?php
/**
 * Vista del formulario de vacaciones para administradores y supervisores
 * Muestra el documento formal completo permitiendo editar la sección de RRHH
 * Los supervisores solo pueden ver solicitudes de empleados de sus departamentos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// IMPORTANTE: Forzar actualización de capacidades del usuario actual
// Esto es necesario en caso de que se hayan agregado capacidades al rol
// después de que el usuario fue asignado al rol
$current_user = wp_get_current_user();
if ( $current_user && $current_user->ID ) {
    // Recargar las capacidades del usuario desde la base de datos
    $current_user->get_role_caps();
    
    // Log para debug
    error_log( 'HRM: Vacaciones Vista - Capacidades del usuario ' . $current_user->ID . ' recargadas' );
    error_log( 'HRM: Usuario tiene manage_hrm_vacaciones: ' . ($current_user->has_cap('manage_hrm_vacaciones') ? 'YES' : 'NO') );
}

// Verificar permisos básicos
$es_admin = current_user_can( 'manage_options' );
$es_editor_vacaciones = current_user_can( 'manage_hrm_vacaciones' ) && ! current_user_can( 'edit_hrm_employees' );
$es_supervisor = current_user_can( 'edit_hrm_employees' ) && ! current_user_can( 'manage_options' );

if ( ! $es_admin && ! $es_editor_vacaciones && ! $es_supervisor ) {
    wp_die( 'No tienes permisos para acceder a esta página.' );
}

// Obtener ID de solicitud
$id_solicitud = absint( $_GET['solicitud_id'] ?? 0 );

if ( ! $id_solicitud ) {
    wp_die( 'ID de solicitud inválido' );
}

// Obtener datos de la solicitud
$table_solicitudes = $wpdb->prefix . 'rrhh_solicitudes_ausencia';
$table_empleados = $wpdb->prefix . 'rrhh_empleados';
$table_tipo_ausencia = $wpdb->prefix . 'rrhh_tipo_ausencia';

$solicitud = $wpdb->get_row( $wpdb->prepare(
    "SELECT s.*, e.rut, e.nombre, e.apellido, e.puesto, e.departamento, e.correo, ta.nombre as tipo_ausencia_nombre
     FROM {$table_solicitudes} s
     JOIN {$table_empleados} e ON s.id_empleado = e.id_empleado
     LEFT JOIN {$table_tipo_ausencia} ta ON s.id_tipo = ta.id_tipo
     WHERE s.id_solicitud = %d",
    $id_solicitud
) );

if ( ! $solicitud ) {
    wp_die( 'Solicitud no encontrada' );
}

// Si es supervisor (no admin ni editor), verificar que el empleado pertenezca a sus departamentos
if ( $es_supervisor ) {
    $user_id = get_current_user_id();
    $departamentos_supervisor = array();
    
    // Verificar si es el Gerente de Operaciones (area_gerencia = 'Operaciones')
    $area_gerencia_usuario = $wpdb->get_var( $wpdb->prepare(
        "SELECT area_gerencia FROM {$wpdb->prefix}rrhh_empleados 
         WHERE user_id = %d AND departamento = 'Gerencia' AND estado = 1 LIMIT 1",
        $user_id
    ) );
    
    if ( $area_gerencia_usuario && strtolower( $area_gerencia_usuario ) === 'operaciones' ) {
        // Es el Gerente de Operaciones - puede ver Gerencia, Sistemas y Administración
        $departamentos_supervisor = array( 'Gerencia', 'Sistemas', 'Administración', 'Administracion' );
        
        // También agregar los departamentos que tenga asignados en gerencia_deptos
        $deptos_adicionales = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT depto_a_cargo FROM {$wpdb->prefix}rrhh_gerencia_deptos 
             WHERE nombre_gerente = (SELECT CONCAT(nombre, ' ', apellido) FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d)
             AND estado = 1",
            $user_id
        ) );
        
        if ( ! empty( $deptos_adicionales ) ) {
            $departamentos_supervisor = array_unique( array_merge( $departamentos_supervisor, $deptos_adicionales ) );
        }
    } else {
        // Supervisor normal - obtener departamentos a cargo desde gerencia_deptos
        $departamentos_supervisor = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT depto_a_cargo FROM {$wpdb->prefix}rrhh_gerencia_deptos 
             WHERE nombre_gerente = (SELECT CONCAT(nombre, ' ', apellido) FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d)
             AND estado = 1",
            $user_id
        ) );
        
        // Si no encuentra por nombre completo, intentar por nombre solamente
        if ( empty( $departamentos_supervisor ) ) {
            $nombre_gerente = $wpdb->get_var( $wpdb->prepare(
                "SELECT nombre FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d LIMIT 1",
                $user_id
            ) );
            
            if ( $nombre_gerente ) {
                $departamentos_supervisor = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT depto_a_cargo FROM {$wpdb->prefix}rrhh_gerencia_deptos 
                     WHERE nombre_gerente LIKE %s AND estado = 1",
                    '%' . $nombre_gerente . '%'
                ) );
            }
        }
    }
    
    // Verificar que el departamento del empleado de la solicitud esté en la lista del supervisor
    $departamento_empleado = $solicitud->departamento ?? '';
    
    if ( empty( $departamentos_supervisor ) || ! in_array( $departamento_empleado, $departamentos_supervisor, true ) ) {
        wp_die( 'No tienes permisos para ver esta solicitud. El empleado no pertenece a tus departamentos a cargo.' );
    }
}

// Calcular total de días laborales
function contar_dias_laborales( $fecha_inicio, $fecha_fin ) {
    $fecha_inicio = new DateTime( $fecha_inicio );
    $fecha_fin = new DateTime( $fecha_fin );
    $dias = 0;
    
    while ( $fecha_inicio <= $fecha_fin ) {
        $dia_semana = (int) $fecha_inicio->format( 'w' );
        if ( $dia_semana !== 0 && $dia_semana !== 6 ) {
            $dias++;
        }
        $fecha_inicio->modify( '+1 day' );
    }
    
    return $dias;
}

$total_dias = contar_dias_laborales( $solicitud->fecha_inicio, $solicitud->fecha_fin );

// Formatear fecha
function fmt_fecha( $fecha ) {
    if ( ! $fecha ) return '—';
    $d = new DateTime( $fecha );
    return $d->format( 'd/m/Y' );
}

// Obtener fecha de solicitud (usar fecha_creacion si existe, sino usar hoy)
$fecha_solicitud = ! empty( $solicitud->fecha_creacion ) ? $solicitud->fecha_creacion : current_time( 'Y-m-d' );

// Cargar estilos CSS
wp_enqueue_style( 'hrm-vacaciones-formulario', plugins_url( 'hr-management/assets/css/vacaciones-formulario.css' ), array(), '1.0.0' );
?>

<div class="wrap">
    <div class="container-fluid px-4 my-4">
        <div class="d-flex justify-content-between align-items-center mb-4 admin-controls">
            <h2 class="mb-0">
                <span class="dashicons dashicons-media-document"></span>
                Solicitud de Ausencia #<?php echo esc_html( $id_solicitud ); ?>
            </h2>
            <div class="gap-2 d-flex">
                <button onclick="window.print()" class="btn btn-outline-primary">
                    <span class="dashicons dashicons-media-document"></span> Imprimir
                </button>
                <button onclick="history.back()" class="btn btn-outline-secondary">
                    <span class="dashicons dashicons-arrow-left"></span> Volver
                </button>
            </div>
        </div>
        
        <!-- CONTENEDOR DEL DOCUMENTO FORMAL -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="hrm_guardar_respuesta_rrhh">
            <input type="hidden" name="solicitud_id" value="<?php echo esc_attr( $id_solicitud ); ?>">
            <?php wp_nonce_field( 'hrm_respuesta_rrhh', 'hrm_nonce' ); ?>
            
            <div class="documento-formal p-5 mx-auto" style="max-width: 900px; background: white;">
                
                <!-- ENCABEZADO CON DATOS DEL SOLICITANTE -->
                <div class="seccion-encabezado">
                    <!-- Columna izquierda -->
                    <div>
                        <div class="campo-info">
                            <div class="label-info">Nombre del Solicitante:</div>
                            <div class="valor-info">
                                <?php echo esc_html( $solicitud->nombre . ' ' . $solicitud->apellido ); ?>
                            </div>
                        </div>
                        
                        <div class="campo-info">
                            <div class="label-info">RUT:</div>
                            <div class="valor-info">
                                <?php echo esc_html( $solicitud->rut ); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna derecha -->
                    <div>
                        <div class="campo-info">
                            <div class="label-info">Cargo:</div>
                            <div class="valor-info">
                                <?php echo esc_html( $solicitud->puesto ); ?>
                            </div>
                        </div>
                        
                        <div class="campo-info">
                            <div class="label-info">Fecha de Solicitud:</div>
                            <div class="valor-info">
                                <?php echo esc_html( fmt_fecha( $fecha_solicitud ) ); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SECCIÓN: SOLICITUD FORMAL -->
                <div class="titulo-seccion">Solicitud</div>
                
                <div class="parrafo-formal">
                    Por medio de la presente, solicito formalmente la autorización para hacer uso de mis días de 
                    <strong><?php echo esc_html( strtolower( $solicitud->tipo_ausencia_nombre ) ); ?></strong>
                    correspondientes al período laboral <?php echo esc_html( date( 'Y' ) ); ?>.
                </div>
                
                <!-- SECCIÓN: PERIODO DE VACACIONES -->
                <div class="titulo-seccion">Período de Ausencia</div>
                
                <!-- RESUMEN DEL PERÍODO -->
                <div class="datos-periodo">
                    <div class="campo-periodo">
                        <div class="campo-periodo-label">Desde:</div>
                        <div class="campo-periodo-valor"><?php echo esc_html( fmt_fecha( $solicitud->fecha_inicio ) ); ?></div>
                    </div>
                    <div class="campo-periodo">
                        <div class="campo-periodo-label">Hasta:</div>
                        <div class="campo-periodo-valor"><?php echo esc_html( fmt_fecha( $solicitud->fecha_fin ) ); ?></div>
                    </div>
                    <div class="campo-periodo">
                        <div class="campo-periodo-label">Total de Días:</div>
                        <div class="campo-periodo-valor"><?php echo esc_html( $total_dias ); ?></div>
                    </div>
                </div>
                
                <!-- MOSTRAR COMENTARIOS SI EXISTEN -->
                <?php if ( ! empty( $solicitud->motivo ) || ! empty( $solicitud->descripcion ) ) : ?>
                    <div class="titulo-seccion">Comentarios del Solicitante</div>
                    <div class="parrafo-formal" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0d6efd; margin-bottom: 20px;">
                        <?php echo nl2br( esc_html( $solicitud->motivo ?: $solicitud->descripcion ) ); ?>
                    </div>
                <?php endif; ?>
                
                <!-- CIERRE FORMAL -->
                <div class="parrafo-formal" style="margin-top: 30px;">
                    Quedo atento(a) a la confirmación y aprobación de esta solicitud. Me comprometo a dejar mis tareas 
                    debidamente coordinadas con mi jefatura directa antes de mi ausencia.
                </div>
                
                <div class="parrafo-formal">
                    Sin otro particular, Saluda atentamente,
                </div>
                
                <!-- SECCIÓN RECURSOS HUMANOS (EDITABLE PARA ADMIN) -->
                <div class="seccion-rrhh">
                    <div class="titulo-seccion" style="margin-top: 0; border-bottom: 1px solid #ffc107;">
                        ⭐ Recursos Humanos / Jefatura Directa
                    </div>
                    
                    <?php 
                    // Verificar si la solicitud está bloqueada (no es PENDIENTE)
                    $solicitud_bloqueada = ( $solicitud->estado !== 'PENDIENTE' );
                    ?>
                    
                    <?php if ( $solicitud_bloqueada ) : ?>
                        <div style="padding: 15px; margin-bottom: 20px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
                            <strong>⚠️ Solicitud Bloqueada</strong><br>
                            Esta solicitud ya ha sido <strong><?php echo $solicitud->estado === 'APROBADA' ? 'APROBADA' : 'RECHAZADA'; ?></strong> 
                            y no puede ser modificada. Solo está permitida la visualización del contenido.
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-bottom: 20px;">
                        <div class="form-label fw-bold" style="margin-bottom: 10px;">Respuesta:</div>
                        <div style="display: flex; gap: 30px;">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="radio" id="respuesta_aceptado" name="respuesta_rrhh" value="aceptado" 
                                       <?php checked( $solicitud->estado, 'APROBADA' ); ?>
                                       <?php echo $solicitud_bloqueada ? 'disabled' : ''; ?>>
                                <label for="respuesta_aceptado" style="cursor: pointer; margin: 0; opacity: <?php echo $solicitud_bloqueada ? '0.6' : '1'; ?>;">Aceptado</label>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="radio" id="respuesta_rechazado" name="respuesta_rrhh" value="rechazado"
                                       <?php checked( $solicitud->estado, 'RECHAZADA' ); ?>
                                       <?php echo $solicitud_bloqueada ? 'disabled' : ''; ?>>
                                <label for="respuesta_rechazado" style="cursor: pointer; margin: 0; opacity: <?php echo $solicitud_bloqueada ? '0.6' : '1'; ?>;">Rechazado</label>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="radio" id="respuesta_pendiente" name="respuesta_rrhh" value="pendiente"
                                       <?php checked( $solicitud->estado, 'PENDIENTE' ); ?>
                                       <?php echo $solicitud_bloqueada ? 'disabled' : ''; ?>>
                                <label for="respuesta_pendiente" style="cursor: pointer; margin: 0; opacity: <?php echo $solicitud_bloqueada ? '0.6' : '1'; ?>;">Pendiente de Revisar</label>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Obtener nombre del usuario logueado para autocompletar
                    $current_user = wp_get_current_user();
                    $nombre_usuario_logueado = $current_user->display_name ?: $current_user->user_login;
                    
                    // Usar el nombre guardado si existe, sino usar el del usuario logueado
                    $nombre_jefe_value = ! empty( $solicitud->nombre_jefe ) ? $solicitud->nombre_jefe : $nombre_usuario_logueado;
                    ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label for="nombre_jefe" class="form-label fw-bold">Nombre de Jefe/RRHH:</label>
                            <input type="text" 
                                   name="nombre_jefe" 
                                   id="nombre_jefe" 
                                   class="form-control"
                                   placeholder="Nombre completo"
                                   value="<?php echo esc_attr( $nombre_jefe_value ); ?>"
                                   <?php echo $solicitud_bloqueada ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div>
                            <label for="fecha_respuesta" class="form-label fw-bold">Fecha de Respuesta:</label>
                            <input type="date" 
                                   name="fecha_respuesta" 
                                   id="fecha_respuesta" 
                                   class="form-control"
                                   value="<?php echo esc_attr( ! empty( $solicitud->fecha_respuesta ) ? $solicitud->fecha_respuesta : current_time( 'Y-m-d' ) ); ?>"
                                   <?php echo $solicitud_bloqueada ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="text-align: center; font-weight: bold; font-size: 12px; text-transform: uppercase; margin-bottom: 10px;">
                            Firma Gerente / Editor de Vacaciones
                        </div>
                        <div class="linea-firma"></div>
                    </div>
                    
                    <div>
                        <label for="observaciones_rrhh" class="form-label fw-bold">Observaciones (Opcional):</label>
                        <textarea name="observaciones_rrhh" 
                                  id="observaciones_rrhh" 
                                  rows="4"
                                  placeholder="Observaciones, comentarios o razones de rechazo..."
                                  <?php echo $solicitud_bloqueada ? 'readonly' : ''; ?>><?php echo esc_textarea( $solicitud->motivo_rechazo ?? '' ); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- BOTONES DE ACCIÓN (fuera del documento para no imprimir) -->
            <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4 admin-controls">
                <?php if ( ! $solicitud_bloqueada ) : ?>
                    <button type="submit" class="btn btn-success btn-lg px-5">
                        <span class="dashicons dashicons-yes"></span> Guardar Respuesta de RRHH
                    </button>
                <?php else : ?>
                    <button type="button" class="btn btn-success btn-lg px-5" disabled title="No se puede editar una solicitud aprobada o rechazada">
                        <span class="dashicons dashicons-yes"></span> Guardar Respuesta de RRHH
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-lg px-5" onclick="history.back();">
                    <span class="dashicons dashicons-arrow-left"></span> Volver
                </button>
            </div>
        </form>
    </div>
</div>
