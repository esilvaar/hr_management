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

// Verificar permisos
$current_user_id = get_current_user_id();
$can_edit = current_user_can( 'edit_hrm_employees' ) || 
            ( $current_user_id && intval( $employee->user_id ) === $current_user_id && current_user_can( 'view_hrm_own_profile' ) );

if ( ! $can_edit ) {
    echo '<div class="notice notice-error"><p>No tienes permisos para editar tu perfil.</p></div>';
    return;
}
?>

<form method="post">
    <?php wp_nonce_field( 'hrm_update_employee', 'hrm_update_employee_nonce' ); ?>
    <input type="hidden" name="hrm_action" value="update_employee">
    <input type="hidden" name="employee_id" value="<?= esc_attr( $employee->id ) ?>">

    <table class="form-table">
        <tr>
            <th><label for="hrm_nombre">Nombres</label></th>
            <td>
                <input id="hrm_nombre" name="nombre" type="text" value="<?= esc_attr( $employee->nombre ) ?>" class="regular-text form-control">
            </td>
        </tr>
        <tr>
            <th><label for="hrm_apellido">Apellidos</label></th>
            <td>
                <input id="hrm_apellido" name="apellido" type="text" value="<?= esc_attr( $employee->apellido ) ?>" class="regular-text form-control">
            </td>
        </tr>
        <tr>
            <th><label for="hrm_email">Correo</label></th>
            <td>
                <input id="hrm_email" name="email" type="email" value="<?= esc_attr( $employee->email ) ?>" class="regular-text form-control">
            </td>
        </tr>
        <tr>
            <th><label for="hrm_telefono">Teléfono</label></th>
            <td>
                <input id="hrm_telefono" name="telefono" type="text" value="<?= esc_attr( $employee->telefono ) ?>" class="regular-text form-control">
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </p>
</form>

<p>
    <a href="<?= esc_url( admin_url( 'admin.php?page=hrm-mi-perfil-info' ) ) ?>" class="btn btn-secondary">
        Volver a mi información
    </a>
</p>
