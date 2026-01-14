<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// $employee ya está disponible del contexto del renderizador
if ( empty( $employee ) ) {
    $db_emp = new HRM_DB_Empleados();
    $employee = $db_emp->get_by_user_id( get_current_user_id() );
}

if ( ! $employee ) {
    echo '<p class="notice notice-warning">No se encontró la información.</p>';
    return;
}
?>

<table class="table table-borderless">
    <tr>
        <th>Nombres</th>
        <td><?= esc_html( $employee->nombre ) ?></td>
    </tr>
    <tr>
        <th>Apellidos</th>
        <td><?= esc_html( $employee->apellido ) ?></td>
    </tr>
    <tr>
        <th>RUT</th>
        <td><?= esc_html( $employee->rut ) ?></td>
    </tr>
    <tr>
        
        <th>Correo</th>
        <td><?= esc_html( $employee->email ) ?></td>
    </tr>
    <tr>
        <th>Teléfono</th>
        <td><?= esc_html( $employee->telefono ) ?></td>
    </tr>
    <tr>
        <th>Departamento</th>
        <td><?= esc_html( $employee->departamento ) ?></td>
    </tr>
    <tr>
        <th>Puesto</th>
        <td><?= esc_html( $employee->puesto ) ?></td>
    </tr>
    <tr>
        <th>Fecha de Ingreso</th>
        <td><?= $employee->fecha_ingreso && $employee->fecha_ingreso !== '0000-00-00' ? esc_html( date( 'd/m/Y', strtotime( $employee->fecha_ingreso ) ) ) : 'No especificada' ?></td>
    </tr>
</table>

<p>
    <a href="<?= esc_url( admin_url( 'admin.php?page=hrm-mi-perfil-editar' ) ) ?>" class="btn btn-primary">
        Editar mi perfil
    </a>
</p>
