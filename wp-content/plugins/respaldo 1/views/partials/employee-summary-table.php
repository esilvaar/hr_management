<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( empty( $employee ) ) {
    $db_emp = new HRM_DB_Empleados();
    $employee = $db_emp->get_by_user_id( get_current_user_id() );
}

if ( ! $employee ) {
    echo '<p class="notice notice-warning">No se encontró información del empleado.</p>';
    return;
}
?>

<div class="table-responsive">
    <table class="table table-hover table-bordered mb-0 align-middle">
        <thead class="bg-light">
            <tr>
                <th class="py-3">Nombre</th>
                <th class="py-3">RUT</th>
                <th class="py-3">Departamento</th>
                <th class="py-3">Puesto</th>
                <th class="py-3">Fecha de Ingreso</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="py-3 fw-bold"><?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?></td>
                <td class="py-3 font-monospace text-secondary"><?= esc_html( $employee->rut ) ?></td>
                <td class="py-3">
                    <span class="badge bg-light text-dark border">
                        <?= esc_html( $employee->departamento ) ?>
                    </span>
                </td>
                <td class="py-3">
                    <span class="badge bg-info text-white">
                        <?= esc_html( $employee->puesto ) ?>
                    </span>
                </td>
                <td class="py-3"><?= $employee->fecha_ingreso && $employee->fecha_ingreso !== '0000-00-00' ? esc_html( date( 'd/m/Y', strtotime( $employee->fecha_ingreso ) ) ) : '-' ?></td>
            </tr>
        </tbody>
    </table>
</div>



