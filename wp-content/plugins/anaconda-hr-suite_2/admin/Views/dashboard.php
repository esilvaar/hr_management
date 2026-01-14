<?php
/**
 * Vista: Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap anaconda-hrsuite">
    <h1><?php echo esc_html( __( 'Dashboard - HR Suite', 'anaconda-hr-suite' ) ); ?></h1>

    <div class="hrsuite-dashboard">
        <!-- Estadísticas principales -->
        <div class="hrsuite-stats">
            <div class="stat-card">
                <h3><?php echo esc_html( $total_employees ); ?></h3>
                <p><?php echo esc_html( __( 'Empleados Activos', 'anaconda-hr-suite' ) ); ?></p>
            </div>

            <div class="stat-card">
                <h3><?php echo esc_html( $pending_requests ); ?></h3>
                <p><?php echo esc_html( __( 'Solicitudes Pendientes', 'anaconda-hr-suite' ) ); ?></p>
            </div>

            <div class="stat-card">
                <h3><?php echo esc_html( $approved_requests ); ?></h3>
                <p><?php echo esc_html( __( 'Solicitudes Aprobadas', 'anaconda-hr-suite' ) ); ?></p>
            </div>

            <div class="stat-card">
                <h3><?php echo esc_html( $rejected_requests ); ?></h3>
                <p><?php echo esc_html( __( 'Solicitudes Rechazadas', 'anaconda-hr-suite' ) ); ?></p>
            </div>
        </div>

        <!-- Solicitudes recientes -->
        <div class="hrsuite-recent-requests">
            <h2><?php echo esc_html( __( 'Solicitudes Recientes', 'anaconda-hr-suite' ) ); ?></h2>

            <?php if ( ! empty( $recent_requests ) ) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html( __( 'Fecha Inicio', 'anaconda-hr-suite' ) ); ?></th>
                            <th><?php echo esc_html( __( 'Fecha Fin', 'anaconda-hr-suite' ) ); ?></th>
                            <th><?php echo esc_html( __( 'Estado', 'anaconda-hr-suite' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent_requests as $request ) : ?>
                            <tr>
                                <td><?php echo esc_html( $request->fecha_inicio ); ?></td>
                                <td><?php echo esc_html( $request->fecha_fin ); ?></td>
                                <td>
                                    <span class="status status-<?php echo esc_attr( $request->estado ); ?>">
                                        <?php echo esc_html( ucfirst( $request->estado ) ); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html( __( 'No hay solicitudes recientes', 'anaconda-hr-suite' ) ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .hrsuite-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 36px;
            margin: 0 0 10px 0;
            color: #0073aa;
        }

        .stat-card p {
            margin: 0;
            color: #666;
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
</div>
