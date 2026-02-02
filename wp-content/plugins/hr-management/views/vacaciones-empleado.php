<?php
/**
 * Vista: Vacaciones del Empleado
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Cargar estilos CSS
wp_enqueue_style( 'hrm-vacaciones-empleado', plugins_url( 'hr-management/assets/css/vacaciones-empleado.css' ), array(), '2.2.0' );

/* =====================================================
 * OBTENER EMPLEADO
 * ===================================================== */

$db_emp   = new HRM_DB_Empleados();
// Usar el user_id pasado explícitamente, o caer al usuario actual
$user_id_to_use = isset( $current_user_id ) ? $current_user_id : get_current_user_id();

// DEBUG DETALLADO
$current_wp_user = wp_get_current_user();
error_log( '=== HRM VACACIONES DEBUG ===' );
error_log( 'Current WP User ID: ' . $user_id_to_use );
error_log( 'Current WP User Login: ' . $current_wp_user->user_login );
error_log( 'Current WP User Email: ' . $current_wp_user->user_email );

$employee = $db_emp->get_by_user_id( $user_id_to_use );
error_log( 'Employee data from DB: ' . print_r( $employee, true ) );

if ( ! $employee ) {
    echo '<p class="notice notice-warning">
        No se encontró información del empleado.
    </p>';
    return;
}

global $wpdb;

/* =====================================================
 * OBTENER ID DEL EMPLEADO
 * ===================================================== */

$id_empleado = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT id_empleado
         FROM {$wpdb->prefix}rrhh_empleados
         WHERE user_id = %d",
        $user_id_to_use
    )
);

if ( ! $id_empleado ) {
    echo '<p class="notice notice-error">Empleado no encontrado.</p>';
    return;
}

/* =====================================================
 * OBTENER SALDO DE VACACIONES (ORDEN CORRECTO)
 * ===================================================== */

$saldo = hrm_get_saldo_vacaciones( $id_empleado );

if ( ! $saldo ) {
    echo '<p class="notice notice-warning">
        No se encontró saldo de vacaciones para este empleado.
        Contacte a RRHH.
    </p>';
    return;
}

/* =====================================================
 * VERIFICAR Y ACTUALIZAR DÍAS DE VACACIONES POR ANIVERSARIO
 * ===================================================== */

if ( function_exists( 'hrm_actualizar_dias_vacaciones_por_aniversario' ) ) {
    // Actualizar si se cumplió un año desde ingreso
    $fue_actualizado = hrm_actualizar_dias_vacaciones_por_aniversario( $id_empleado );
    
    // Si se actualizó, recargar el saldo de vacaciones para mostrar los nuevos datos
    if ( $fue_actualizado ) {
        $saldo = hrm_get_saldo_vacaciones( $id_empleado );
    }
}

/* =====================================================
 * OBTENER SOLICITUDES DEL EMPLEADO
 * ===================================================== */

if ( ! isset( $solicitudes ) ) {
    if ( function_exists( 'hrm_get_vacaciones_empleado' ) ) {
        $solicitudes = hrm_get_vacaciones_empleado( $user_id_to_use );
    } else {
        $solicitudes = hrm_get_vacaciones_empleado( $user_id_to_use );
    }
}

/* =====================================================
 * CONTROL DE VISTA
 * ===================================================== */

$show = sanitize_key( $_GET['show'] ?? '' );

// Generar URL para el formulario manteniendo la página actual (sea perfil o debug)
$form_admin_url = add_query_arg( 'show', 'form' );
?>

<div class="card shadow-sm mx-auto mt-3 hrm-vacaciones-card">
    <div class="card-header bg-dark text-white">
        <h2 class="mb-0">
            <span class="dashicons dashicons-calendar-alt me-2"></span> Mis Vacaciones
        </h2>
        <small><?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?> (RUT: <?= esc_html( $employee->rut ) ?>)</small>
    </div>
    
    <div class="card-body">
        <?php if ( $show === 'form' ) : ?>
            
            <!-- Formulario de Nueva Solicitud de Vacaciones -->
            <div class="mb-4">
                <a href="<?= esc_url( remove_query_arg('show') ) ?>" class="btn btn-outline-secondary">
                    <span class="dashicons dashicons-arrow-left-alt me-1"></span> Volver a mis vacaciones
                </a>
            </div>

            <?php include HRM_PLUGIN_DIR . 'views/vacaciones-form.php'; ?>
            
        <?php elseif ( $show === 'medio-dia' ) : ?>
            
            <!-- Formulario de Solicitud de Medio Día -->
            <div class="mb-4">
                <a href="<?= esc_url( remove_query_arg('show') ) ?>" class="btn btn-outline-secondary">
                    <span class="dashicons dashicons-arrow-left-alt me-1"></span> Volver a mis vacaciones
                </a>
            </div>

            <?php include HRM_PLUGIN_DIR . 'views/medio-dia-form.php'; ?>
            
        <?php else : ?>
            
            <!-- Saldo de Vacaciones - Cálculo según Ley Chilena -->
            <div class="mb-4">
                <h3 class="h5 mb-3 hrm-emp-heading">
                    
                </h3>
                
                <?php
                // Obtener el cálculo completo según ley chilena
                if ( function_exists( 'hrm_get_saldo_vacaciones_chile' ) ) {
                    $saldo_chile = hrm_get_saldo_vacaciones_chile( $id_empleado );
                    
                    if ( ! isset( $saldo_chile['error'] ) || $saldo_chile['error'] === false ) {
                        // Mostrar el panel completo con detalle
                        echo hrm_render_saldo_vacaciones_chile( $saldo_chile, true );
                    } else {
                        // Si hay error, mostrar tabla simplificada con datos del sistema antiguo
                        ?>
                        <div class="alert alert-warning mb-3">
                            <small><?= esc_html( $saldo_chile['mensaje'] ?? 'No se pudo calcular el saldo detallado' ) ?></small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0 text-center">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="py-3">Días anuales</th>
                                        <th class="py-3">Usados</th>
                                        <th class="py-3">Disponibles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-3">
                                            <span class="fw-bold fs-5" style="color: #1a1a1a;">
                                                <?= esc_html( $saldo->dias_vacaciones_anuales ?? 0 ) ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="fw-bold text-secondary fs-5">
                                                <?= esc_html( $saldo->dias_vacaciones_usados ?? 0 ) ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-success fs-5 px-3 py-2">
                                                <?= esc_html( $saldo->dias_vacaciones_disponibles ?? 0 ) ?>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                } else {
                    // Fallback: sistema antiguo
                    ?>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 text-center">
                            <thead class="bg-light">
                                <tr>
                                    <th class="py-3">Días anuales</th>
                                    <th class="py-3">Usados</th>
                                    <th class="py-3">Disponibles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="py-3">
                                        <span class="fw-bold fs-5" style="color: #1a1a1a;">
                                            <?= esc_html( $saldo->dias_vacaciones_anuales ?? 0 ) ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span class="fw-bold text-secondary fs-5">
                                            <?= esc_html( $saldo->dias_vacaciones_usados ?? 0 ) ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-success fs-5 px-3 py-2">
                                            <?= esc_html( $saldo->dias_vacaciones_disponibles ?? 0 ) ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <hr class="my-4">
            
            <!-- Mis Solicitudes -->
            <div class="mb-4">
                <h3 class="h5 mb-3" style="color: #1a1a1a; font-weight: 600;">
                    <span class="dashicons dashicons-list-view me-2"></span> Mis Solicitudes
                </h3>

                <?php
                // Obtener estado del filtro (por defecto PENDIENTE)
                $estado_filtro = sanitize_text_field( $_GET['estado'] ?? 'PENDIENTE' );
                
                // Filtrar solicitudes por estado
                $solicitudes_filtradas = [];
                if ( ! empty( $solicitudes ) ) {
                    foreach ( $solicitudes as $solicitud ) {
                        $estado_solicitud = strtoupper( $solicitud['estado'] ?? '' );
                        if ( $estado_filtro === 'TODAS' || $estado_filtro === $estado_solicitud ) {
                            $solicitudes_filtradas[] = $solicitud;
                        }
                    }
                }
                ?>

                <!-- Selector de Estado -->
                <div class="mb-3 d-flex gap-2 align-items-center">
                    <label for="estado_filtro" class="form-label mb-0 fw-semibold">Filtrar por:</label>
                    <select id="estado_filtro" class="form-select hrm-filter-select" onchange="window.location.href = '<?php echo esc_url( remove_query_arg( 'estado' ) ); ?>' + (this.value ? '&estado=' + this.value : '');">
                        <option value="PENDIENTE" <?php selected( $estado_filtro, 'PENDIENTE' ); ?>>
                            ⏳ Pendiente (Por Revisar)
                        </option>
                        <option value="APROBADA" <?php selected( $estado_filtro, 'APROBADA' ); ?>>
                            ✅ Aprobada
                        </option>
                        <option value="RECHAZADA" <?php selected( $estado_filtro, 'RECHAZADA' ); ?>>
                            ❌ Rechazada
                        </option>
                        <option value="TODAS" <?php selected( $estado_filtro, 'TODAS' ); ?>>
                            🔄 Ver todas
                        </option>
                    </select>
                </div>

                <!-- Panel Header con Indicador de Estado -->
                <div class="hrm-panel-header bg-light border-bottom px-4 py-3 mb-3" style="border-radius: 4px 4px 0 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php
                            $color_estado = '#999';
                            $icono_estado = '🔵';
                            
                            if ( $estado_filtro === 'PENDIENTE' ) {
                                $color_estado = '#0a0a0a';
                                $icono_estado = '';
                                $texto_estado = 'Solicitudes Pendientes';
                            } elseif ( $estado_filtro === 'APROBADA' ) {
                                $color_estado = '#0a0a0a';
                                $icono_estado = '';
                                $texto_estado = 'Solicitudes Aprobadas';
                            } elseif ( $estado_filtro === 'RECHAZADA' ) {
                                $color_estado = '#0b0b0b';
                                $icono_estado = '';
                                $texto_estado = 'Solicitudes Rechazadas';
                            } else {
                                $texto_estado = 'Todas las Solicitudes';
                            }
                            ?>
                            <span style="color: <?php echo $color_estado; ?>;"><?php echo $icono_estado; ?> <?php echo esc_html( $texto_estado ); ?></span>
                        </h5>
                        <span >  :    <strong><?php echo count( $solicitudes_filtradas ); ?></strong></span>
                    </div>
                </div>
                
                <?php if ( empty( $solicitudes_filtradas ) ) : ?>
                    
                    <div class="alert hrm-empty-alert" role="alert">
                        <span class="dashicons dashicons-info fs-1"></span>
                        <p class="lead mb-0 mt-2">No tienes solicitudes de vacaciones registradas.</p>
                    </div>
                    
                <?php else : ?>
                    
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="py-3 ps-4">Desde</th>
                                    <th class="py-3">Hasta</th>
                                    <th class="py-3">Estado</th>
                                    <th class="py-3 pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $solicitudes_filtradas as $s ) : ?>
                                    <?php 
                                    $estado = strtoupper( $s['estado'] ?? '' );
                                    $badge_class = 'bg-secondary';
                                    
                                    if ( $estado === 'APROBADA' ) {
                                        $badge_class = 'bg-success';
                                    } elseif ( $estado === 'RECHAZADA' ) {
                                        $badge_class = 'bg-danger';
                                    } elseif ( $estado === 'PENDIENTE' ) {
                                        $badge_class = 'bg-warning text-dark';
                                    }
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <?= esc_html( $s['fecha_inicio'] ?? '' ) ?>
                                            <?php if ( isset($s['fecha_inicio']) && isset($s['fecha_fin']) && $s['fecha_inicio'] === $s['fecha_fin'] && isset($s['total_dias']) && $s['total_dias'] == 0.5 ) : ?>
                                                <br><small color="black" title="Solicitud de Medio Día">⏰ Medio Día</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold">
                                            <?= esc_html( $s['fecha_fin'] ?? '' ) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= esc_attr( $badge_class ) ?> rounded-pill px-3">
                                                <?= esc_html( $s['estado'] ?? '' ) ?>
                                            </span>
                                        </td>
                                        <td class="pe-4">
                                            <?php if ( strtoupper( $s['estado'] ?? '' ) === 'PENDIENTE' ) : ?>
                                                <?php 
                                                // Determinar si es medio día o solicitud normal
                                                // La función hrm_get_vacaciones_empleado devuelve tipo_solicitud = 'medio_dia' para medio día
                                                $es_medio_dia = ( isset($s['tipo_solicitud']) && $s['tipo_solicitud'] === 'medio_dia' ) || 
                                                                ( isset($s['fecha_inicio']) && isset($s['fecha_fin']) && $s['fecha_inicio'] === $s['fecha_fin'] && isset($s['total_dias']) && floatval($s['total_dias']) == 0.5 );
                                                $action = $es_medio_dia ? 'hrm_cancelar_solicitud_medio_dia' : 'hrm_cancelar_solicitud_vacaciones';
                                                $nonce_action = $es_medio_dia ? 'hrm_cancelar_solicitud_medio_dia' : 'hrm_cancelar_solicitud';
                                                ?>
                                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="d-inline" onsubmit="return confirm('¿Deseas cancelar esta solicitud? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="action" value="<?= esc_attr( $action ) ?>">
                                                    <input type="hidden" name="id_solicitud" value="<?= esc_attr( $s['id_solicitud'] ?? '' ) ?>">
                                                    <?php wp_nonce_field( $nonce_action, 'hrm_nonce' ); ?>
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar solicitud">
                                                        <span class="dashicons dashicons-trash"></span> Cancelar
                                                    </button>
                                                </form>
                                            <?php else : ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php endif; ?>
            </div>
            
            <!-- Botones Nueva Solicitud -->
            <div class="text-center mt-4 pt-3 border-top d-flex gap-3 justify-content-center">
                <a href="<?= esc_url( $form_admin_url ) ?>" class="hrm-new-request btn btn-dark">
                    <span class="dashicons dashicons-plus me-2"></span> Nueva solicitud de vacaciones
                </a>
                <a href="<?= esc_url( add_query_arg( 'show', 'medio-dia' ) ) ?>" class="hrm-md-request btn btn-warning">
                    <span class="dashicons dashicons-clock me-2"></span> Solicitar medio día
                </a>
            </div>
            
        <?php endif; ?>
    </div>
</div>
