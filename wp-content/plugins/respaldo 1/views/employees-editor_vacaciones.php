<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'manage_hrm_vacaciones' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( 'No tienes permisos para ver esta página.', 'Acceso denegado', array( 'response' => 403 ) );
}

$solicitudes = function_exists('hrm_get_all_vacaciones') ? hrm_get_all_vacaciones() : array();
?>

<div class="wrap hrm-admin-wrap">
    <div class="hrm-admin-layout">
        <?php hrm_get_template_part( 'partials/sidebar-loader' ); ?>
        <main class="hrm-content">
            <h1 class="wp-heading-inline">Gestión de Vacaciones</h1>
            <p>Vista para editor de vacaciones: revisa y aprueba/rechaza solicitudes.</p>

            <?php if ( empty( $solicitudes ) ) : ?>
        <p>No hay solicitudes registradas.</p>
    <?php else : ?>
        <table class="table table-striped">
            <thead>
                <tr><th>Empleado</th><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ( $solicitudes as $s ) : ?>
                    <tr>
                        <td><?= esc_html( ($s['nombre'] ?? '') . ' ' . ($s['apellido'] ?? '') ) ?></td>
                        <td><?= esc_html( $s['tipo'] ?? '' ) ?></td>
                        <td><?= esc_html( $s['fecha_inicio'] ?? '' ) ?></td>
                        <td><?= esc_html( $s['fecha_fin'] ?? '' ) ?></td>
                        <td><?= esc_html( $s['total_dias'] ?? '' ) ?></td>
                        <td><span class="badge <?php $st = strtoupper( $s['estado'] ?? '' ); echo $st === 'APROBADO' ? 'bg-success' : ( $st === 'RECHAZADO' ? 'bg-danger' : 'bg-warning text-dark' ); ?>"><?php echo esc_html( $s['estado'] ?? '' ); ?></span></td>
                        <td>
                            <?php if ( ($s['estado'] ?? '') !== 'APROBADO' ) : ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field( 'hrm_aprobar_solicitud', 'hrm_nonce' ); ?>
                                    <input type="hidden" name="accion" value="aprobar">
                                    <input type="hidden" name="solicitud_id" value="<?= esc_attr( $s['id_solicitud'] ?? '' ) ?>">
                                    <button class="btn btn-primary btn-sm">Aprobar</button>
                                </form>
                            <?php endif; ?>

                            <?php if ( ($s['estado'] ?? '') !== 'RECHAZAR' && ($s['estado'] ?? '') !== 'RECHAZADO' ) : ?>
                                <form method="post" style="display:inline; margin-left:6px;">
                                    <?php wp_nonce_field( 'hrm_rechazar_solicitud', 'hrm_nonce' ); ?>
                                    <input type="hidden" name="accion" value="rechazar">
                                    <input type="hidden" name="solicitud_id" value="<?= esc_attr( $s['id_solicitud'] ?? '' ) ?>">
                                    <button class="btn btn-secondary btn-sm">Rechazar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php
    // Permitir acceso rápido al perfil de un empleado seleccionado
    if ( ! empty( $_GET['id'] ) ) {
        $employee = ( new HRM_DB_Empleados() )->get( intval( $_GET['id'] ) );
        $hrm_departamentos = apply_filters( 'hrm_departamentos', array( 'Soporte', 'Desarrollo', 'Administracion', 'Ventas', 'Gerencia', 'Sistemas' ) );
        $hrm_puestos = apply_filters( 'hrm_puestos', array( 'Técnico', 'Analista', 'Gerente', 'Administrativo', 'Practicante' ) );
        $hrm_tipos_contrato = apply_filters( 'hrm_tipos_contrato', array( 'Indefinido', 'Plazo Fijo', 'Por Proyecto' ) );
        hrm_get_template_part( 'Administrador/employees-detail' );
    }
    ?>
        </main>
    </div>
</div>
