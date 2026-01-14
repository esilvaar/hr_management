<?php
/**
 * Vista: Listado de Solicitudes de Vacaciones
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap anaconda-hrsuite">
    <h1><?php echo esc_html( __( 'Solicitudes de Vacaciones', 'anaconda-hr-suite' ) ); ?></h1>

    <!-- Filtros -->
    <div class="hrsuite-filters">
        <a href="<?php echo esc_url( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-vacations' ) ); ?>" class="button <?php echo empty( $status_filter ) ? 'active' : ''; ?>">
            <?php echo esc_html( __( 'Todas', 'anaconda-hr-suite' ) ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( [ 'status' => 'pendiente' ], anaconda_hrsuite_admin_url( 'anaconda-hr-suite-vacations' ) ) ); ?>" class="button <?php echo 'pendiente' === $status_filter ? 'active' : ''; ?>">
            <?php echo esc_html( __( 'Pendientes', 'anaconda-hr-suite' ) ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( [ 'status' => 'aprobada' ], anaconda_hrsuite_admin_url( 'anaconda-hr-suite-vacations' ) ) ); ?>" class="button <?php echo 'aprobada' === $status_filter ? 'active' : ''; ?>">
            <?php echo esc_html( __( 'Aprobadas', 'anaconda-hr-suite' ) ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( [ 'status' => 'rechazada' ], anaconda_hrsuite_admin_url( 'anaconda-hr-suite-vacations' ) ) ); ?>" class="button <?php echo 'rechazada' === $status_filter ? 'active' : ''; ?>">
            <?php echo esc_html( __( 'Rechazadas', 'anaconda-hr-suite' ) ); ?>
        </a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html( __( 'Empleado', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Fecha Inicio', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Fecha Fin', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Días', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Estado', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Acciones', 'anaconda-hr-suite' ) ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $requests ) ) : ?>
                <?php foreach ( $requests as $request ) : ?>
                    <tr>
                        <td>
                            <?php
                            if ( isset( $employees_map[ $request->id_empleado ] ) ) {
                                $emp = $employees_map[ $request->id_empleado ];
                                echo esc_html( $emp->nombre . ' ' . $emp->apellido );
                            } else {
                                echo esc_html( __( 'Desconocido', 'anaconda-hr-suite' ) );
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $request->fecha_inicio ); ?></td>
                        <td><?php echo esc_html( $request->fecha_fin ); ?></td>
                        <td>
                            <?php
                            $service = anaconda_hrsuite_vacation_service();
                            echo esc_html( $service->calculate_days( $request->fecha_inicio, $request->fecha_fin ) );
                            ?>
                        </td>
                        <td>
                            <span class="status status-<?php echo esc_attr( $request->estado ); ?>">
                                <?php echo esc_html( ucfirst( $request->estado ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ( 'pendiente' === $request->estado && current_user_can( 'approve_hrsuite_vacations' ) ) : ?>
                                <form method="POST" style="display: inline;">
                                    <?php wp_nonce_field( 'anaconda_hrsuite_nonce' ); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr( $request->id_solicitud ); ?>">
                                    <button type="submit" class="button button-primary button-small">
                                        <?php echo esc_html( __( 'Aprobar', 'anaconda-hr-suite' ) ); ?>
                                    </button>
                                </form>

                                <button type="button" class="button button-small" onclick="showRejectForm(<?php echo esc_attr( $request->id_solicitud ); ?>)">
                                    <?php echo esc_html( __( 'Rechazar', 'anaconda-hr-suite' ) ); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Modal de rechazo -->
                    <tr id="reject-form-<?php echo esc_attr( $request->id_solicitud ); ?>" style="display: none;">
                        <td colspan="6">
                            <form method="POST">
                                <?php wp_nonce_field( 'anaconda_hrsuite_nonce' ); ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="request_id" value="<?php echo esc_attr( $request->id_solicitud ); ?>">

                                <label><?php echo esc_html( __( 'Motivo del rechazo:', 'anaconda-hr-suite' ) ); ?></label>
                                <textarea name="reason" rows="3" style="width: 100%;"></textarea>

                                <button type="submit" class="button button-primary">
                                    <?php echo esc_html( __( 'Rechazar', 'anaconda-hr-suite' ) ); ?>
                                </button>

                                <button type="button" class="button" onclick="hideRejectForm(<?php echo esc_attr( $request->id_solicitud ); ?>)">
                                    <?php echo esc_html( __( 'Cancelar', 'anaconda-hr-suite' ) ); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6"><?php echo esc_html( __( 'No hay solicitudes', 'anaconda-hr-suite' ) ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <style>
        .hrsuite-filters {
            margin-bottom: 20px;
        }

        .hrsuite-filters .button {
            margin-right: 5px;
        }

        .hrsuite-filters .button.active {
            background: #0073aa;
            color: #fff;
        }

        .status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pendiente {
            background: #fff8e5;
            color: #7e6b00;
        }

        .status-aprobada {
            background: #d5e8d4;
            color: #003300;
        }

        .status-rechazada {
            background: #f8cecc;
            color: #660000;
        }
    </style>

    <script>
        function showRejectForm(id) {
            document.getElementById('reject-form-' + id).style.display = 'table-row';
        }

        function hideRejectForm(id) {
            document.getElementById('reject-form-' + id).style.display = 'none';
        }
    </script>
</div>
