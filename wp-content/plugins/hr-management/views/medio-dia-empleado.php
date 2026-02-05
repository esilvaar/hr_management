<?php
/**
 * Vista: Medio Día del Empleado
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Cargar estilos CSS
// Styles merged into plugin-common.css: assets/css/plugin-common.css (medio-dia-empleado rules moved there).

$db_emp   = new HRM_DB_Empleados();
$user_id_to_use = isset( $current_user_id ) ? $current_user_id : get_current_user_id();
$employee = $db_emp->get_by_user_id( $user_id_to_use );

if ( ! $employee ) {
    echo '<p class="notice notice-warning">No se encontró información del empleado.</p>';
    return;
}

global $wpdb;

$id_empleado = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT id_empleado FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d",
        $user_id_to_use
    )
);

if ( ! $id_empleado ) {
    echo '<p class="notice notice-error">Empleado no encontrado.</p>';
    return;
}

// Obtener solicitudes de medio día del empleado
$solicitudes_medio_dia = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rrhh_solicitudes_ausencia 
         WHERE id_empleado = %d 
         AND fecha_inicio = fecha_fin 
         AND periodo_ausencia IN ('mañana', 'tarde')
         ORDER BY fecha_creacion DESC",
        $id_empleado
    ),
    ARRAY_A
);

$show = sanitize_key( $_GET['show'] ?? '' );
$form_admin_url = remove_query_arg( 'solicitud_creada', add_query_arg( 'show', 'form' ) );
?>

<div class="card shadow-sm mx-auto mt-3 hrm-md-card">
    <div class="card-header bg-dark text-white">
        <h2 class="mb-0 hrm-md-title">
            <span class="dashicons dashicons-clock me-2"></span> Mis Medios Días
        </h2>
        <small><?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?> (RUT: <?= esc_html( $employee->rut ) ?>)</small>
    </div>
    
    <div class="card-body">
        <?php if ( $show === 'form' ) : ?>
            
            <!-- Formulario de Nueva Solicitud -->
            <div class="mb-4">
                <a href="<?= esc_url( remove_query_arg('show') ) ?>" class="btn btn-outline-secondary">
                    <span class="dashicons dashicons-arrow-left-alt me-1"></span> Volver a mis medios días
                </a>
            </div>

            <?php include HRM_PLUGIN_DIR . 'views/medio-dia-form.php'; ?>
            
        <?php else : ?>
            
            <!-- Información sobre medios días -->
            <div class="mb-4">
                <div class="alert alert-info" role="alert">
                    <strong>ℹ️ Información:</strong> Cada medio día descontará <strong>0.5 días</strong> de tus días de vacaciones disponibles.
                </div>
            </div>
            
            <!-- Mis Solicitudes de Medio Día -->
            <div class="mb-4">
                <h3 class="h5 mb-3 hrm-md-heading">
                    <span class="dashicons dashicons-list-view me-2"></span> Mis Solicitudes de Medio Día
                </h3>
                
                <?php if ( empty( $solicitudes_medio_dia ) ) : ?>
                    
                    <div class="alert hrm-md-empty" role="alert">
                        <span class="dashicons dashicons-info fs-1"></span>
                        <p class="lead mb-0 mt-2">No tienes solicitudes de medio día registradas.</p>
                    </div>
                    
                <?php else : ?>
                    
                    <div class="table-responsive hrm-md-table">
                        <table class="table table-hover mb-0 align-middle hrm-md-table-inner">
                            <thead class="bg-light">
                                <tr>
                                    <th class="py-3 ps-4">Fecha</th>
                                    <th class="py-3">Período</th>
                                    <th class="py-3">Días</th>
                                    <th class="py-3">Estado</th>
                                    <th class="py-3 pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $solicitudes_medio_dia as $s ) : ?>
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
                                    
                                    $periodo = ucfirst( $s['periodo_ausencia'] ?? 'completo' );
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <?= esc_html( $s['fecha_inicio'] ?? '' ) ?>
                                        </td>
                                        <td>
                                            <?= esc_html( $periodo ) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">0.5</span>
                                        </td>
                                        <td>
                                            <span class="badge <?= esc_attr( $badge_class ) ?> rounded-pill px-3">
                                                <?= esc_html( $s['estado'] ?? '' ) ?>
                                            </span>
                                        </td>
                                        <td class="pe-4">
                                            <?php if ( strtoupper( $s['estado'] ?? '' ) === 'PENDIENTE' ) : ?>
                                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="d-inline" onsubmit="return confirm('¿Deseas cancelar esta solicitud? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="action" value="hrm_cancelar_solicitud_vacaciones">
                                                    <input type="hidden" name="id_solicitud" value="<?= esc_attr( $s['id_solicitud'] ?? '' ) ?>">
                                                    <?php wp_nonce_field( 'hrm_cancelar_solicitud', 'hrm_nonce' ); ?>
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
            
            <!-- Botón Nueva Solicitud -->
            <div class="text-center mt-4 pt-3 border-top">
                <a href="<?= esc_url( $form_admin_url ) ?>" class="hrm-md-new btn btn-dark">
                    <span class="dashicons dashicons-plus me-2"></span> Solicitar Medio Día
                </a>
            </div>
            
        <?php endif; ?>
    </div>
</div>
