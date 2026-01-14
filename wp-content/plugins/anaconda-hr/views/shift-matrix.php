<?php
// Obtener fecha de inicio (hoy o la que elijan)
$fecha_inicio = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$dias_a_mostrar = 7;

// Instanciar DB y obtener datos cruzados
$db = new AHR_DB();
$matriz = $db->get_availability_matrix($fecha_inicio, $dias_a_mostrar);

// Generar encabezados de fechas
$headers = [];
for ($i = 0; $i < $dias_a_mostrar; $i++) {
    $headers[] = date('d/m', strtotime("$fecha_inicio +$i days"));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Matriz de Disponibilidad de Turnos</h1>
    <hr class="wp-header-end">

    <div class="tablenav top">
        <form method="get">
            <input type="hidden" name="page" value="ahr-turnos">
            <label>Ver semana desde: </label>
            <input type="date" name="fecha" value="<?php echo $fecha_inicio; ?>">
            <button class="button">Actualizar</button>
            <button type="button" class="button button-primary" onclick="window.print()">🖨️ Imprimir
                Planificación</button>
        </form>
    </div>

    <div class="card" style="margin-top: 10px; padding: 0; overflow-x: auto;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 200px;">Empleado / Cargo</th>
                    <?php foreach ($headers as $h): ?>
                        <th style="text-align: center; background: #f0f0f1;"><?php echo $h; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matriz as $fila): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($fila['empleado']); ?></strong><br>
                            <span style="font-size: 11px; color: #666;"><?php echo esc_html($fila['cargo']); ?></span>
                        </td>

                        <?php foreach ($fila['dias'] as $fecha => $estado): ?>
                            <td style="text-align: center; vertical-align: middle;">
                                <?php if ($estado === 'Disponible'): ?>
                                    <span class="dashicons dashicons-yes" style="color: #46b450; font-size: 25px;"></span>
                                    <div style="font-size: 10px; color: #46b450; font-weight: bold;">DISPONIBLE</div>
                                <?php else: ?>
                                    <?php
                                    $color = ($estado == 'Licencia') ? '#dc3232' : '#ffb900'; // Rojo o Naranja
                                    ?>
                                    <span
                                        style="background: <?php echo $color; ?>; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; display: block;">
                                        <?php echo strtoupper($estado); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="description">
        * Esta tabla se actualiza automáticamente según las solicitudes aprobadas en el sistema.
    </p>
</div>