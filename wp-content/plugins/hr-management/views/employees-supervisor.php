<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$db_emp = new HRM_DB_Empleados();
$employees = $db_emp->get_all();
?>

<div class="wrap hrm-admin-wrap">
    <div class="hrm-admin-layout">
        <?php hrm_get_template_part( 'partials/sidebar-loader' ); ?>
        <main class="hrm-content">
            <h1 class="wp-heading-inline">Gestión - Supervisor</h1>
            <p>Vista reducida para supervisores: acceso a lista y perfiles de su equipo.</p>

            <table class="table table-striped">
        <thead>
            <tr><th>RUT</th><th>Nombre</th><th>Puesto</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php if ( empty( $employees ) ) : ?>
                <tr><td colspan="4">No hay empleados registrados.</td></tr>
            <?php else : ?>
                <?php foreach ( $employees as $emp ) : ?>
                    <tr>
                        <td><?= esc_html( $emp->rut ) ?></td>
                        <td><?= esc_html( $emp->nombre . ' ' . $emp->apellido ) ?></td>
                        <td><?= esc_html( $emp->puesto ) ?></td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="?page=hrm-empleados&tab=profile&id=<?= esc_attr( $emp->id ) ?>">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
        <?php if ( ! empty( $_GET['id'] ) ) : ?>
            <?php
            // Cargar y mostrar el perfil del empleado seleccionado
            $employee = $db_emp->get( intval( $_GET['id'] ) );
            $hrm_departamentos = apply_filters( 'hrm_departamentos', array( 'Soporte', 'Desarrollo', 'Administracion', 'Ventas', 'Gerencia', 'Sistemas' ) );
            $hrm_puestos = apply_filters( 'hrm_puestos', array( 'Técnico', 'Analista', 'Gerente', 'Administrativo', 'Practicante' ) );
            $hrm_tipos_contrato = apply_filters( 'hrm_tipos_contrato', array( 'Indefinido', 'Plazo Fijo', 'Por Proyecto' ) );
            hrm_get_template_part( 'Administrador/employees-detail' );
            ?>
        <?php endif; ?>
        </main>
    </div>
</div>
