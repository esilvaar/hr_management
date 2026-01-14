<?php
// Obtener datos
$db = new AHR_DB();
$empleados = $db->get_availability_today();
$stats = $db->get_availability_stats();
$pendientes = $db->get_pending_count();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">📊 Dashboard RRHH - Disponibilidad Hoy</h1>

    <!-- ESTADÍSTICAS RÁPIDAS -->
    <div class="ahr-stats" style="display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap;">
        <div class="card"
            style="flex: 1; min-width: 200px; background: #f0f9ff; padding: 15px; border-left: 4px solid #007cba;">
            <h3 style="margin-top: 0;">🟢 Disponibles</h3>
            <p style="font-size: 32px; font-weight: bold; margin: 5px 0;"><?php echo $stats['disponibles']; ?></p>
            <p style="color: #666;">de <?php echo $stats['total']; ?> empleados</p>
        </div>

        <div class="card"
            style="flex: 1; min-width: 200px; background: #fef2f2; padding: 15px; border-left: 4px solid #dc2626;">
            <h3 style="margin-top: 0;">🔴 Ausentes</h3>
            <p style="font-size: 32px; font-weight: bold; margin: 5px 0;"><?php echo $stats['ausentes']; ?></p>
            <p style="color: #666;"><?php echo $stats['porcentaje']; ?>% de disponibilidad</p>
        </div>

        <div class="card"
            style="flex: 1; min-width: 200px; background: #fffbeb; padding: 15px; border-left: 4px solid #f59e0b;">
            <h3 style="margin-top: 0;">⏳ Pendientes</h3>
            <p style="font-size: 32px; font-weight: bold; margin: 5px 0;"><?php echo $pendientes; ?></p>
            <p style="color: #666;"><a href="?page=ahr-dashboard&view=requests">Ver solicitudes</a></p>
        </div>
    </div>

    <!-- DOS COLUMNAS -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">

        <!-- DISPONIBLES -->
        <div class="card" style="background: white; border: 1px solid #ddd; padding: 20px;">
            <h2 style="color: #16a34a; margin-top: 0;">✅ Empleados Disponibles</h2>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="widefat fixed" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 10px; text-align: left;">Nombre</th>
                            <th style="padding: 10px; text-align: left;">Cargo</th>
                            <th style="padding: 10px; text-align: left;">Departamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                            <?php if ($emp->estado_hoy == 'DISPONIBLE'): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px;">
                                        <strong><?php echo esc_html($emp->nombres . ' ' . $emp->apellidos); ?></strong>
                                    </td>
                                    <td style="padding: 10px;"><?php echo esc_html($emp->cargo); ?></td>
                                    <td style="padding: 10px;">
                                        <span
                                            style="background: #e0f2fe; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                            <?php echo esc_html($emp->departamento); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AUSENTES -->
        <div class="card" style="background: white; border: 1px solid #ddd; padding: 20px;">
            <h2 style="color: #dc2626; margin-top: 0;">⛔ Empleados Ausentes</h2>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="widefat fixed" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 10px; text-align: left;">Nombre</th>
                            <th style="padding: 10px; text-align: left;">Motivo</th>
                            <th style="padding: 10px; text-align: left;">Departamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                            <?php if ($emp->estado_hoy == 'AUSENTE'): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px;">
                                        <strong><?php echo esc_html($emp->nombres . ' ' . $emp->apellidos); ?></strong>
                                    </td>
                                    <td style="padding: 10px;">
                                        <span
                                            style="background: #fee2e2; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                            <?php echo esc_html($emp->motivo); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px;"><?php echo esc_html($emp->departamento); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ACCIONES RÁPIDAS -->
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
        <h3 style="margin-top: 0;">Acciones Rápidas</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=ahr-vacaciones'); ?>" class="button button-primary">
                ➕ Nueva Solicitud
            </a>
            <a href="<?php echo admin_url('admin.php?page=ahr-turnos'); ?>" class="button">
                📊 Ver Matriz de Turnos
            </a>
            <a href="<?php echo admin_url('admin.php?page=ahr-empleados'); ?>" class="button">
                👥 Ver Empleados
            </a>
            <a href="<?php echo admin_url('admin.php?page=ahr-dashboard&view=requests'); ?>"
                class="button button-secondary">
                📋 Solicitudes Pendientes
            </a>
        </div>
    </div>
</div>