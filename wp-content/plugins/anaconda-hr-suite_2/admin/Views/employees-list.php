<?php
/**
 * Vista: Listado de Empleados
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap anaconda-hrsuite">
    <h1><?php echo esc_html( __( 'Gestión de Empleados', 'anaconda-hr-suite' ) ); ?></h1>

    <a href="<?php echo esc_url( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-employees', [ 'action' => 'new' ] ) ); ?>" class="page-title-action">
        <?php echo esc_html( __( 'Agregar Nuevo Empleado', 'anaconda-hr-suite' ) ); ?>
    </a>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html( __( 'RUT', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Nombre', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Email', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Departamento', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Puesto', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Estado', 'anaconda-hr-suite' ) ); ?></th>
                <th><?php echo esc_html( __( 'Acciones', 'anaconda-hr-suite' ) ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $employees ) ) : ?>
                <?php foreach ( $employees as $employee ) : ?>
                    <tr>
                        <td><?php echo esc_html( $employee->rut ); ?></td>
                        <td><?php echo esc_html( $employee->nombre . ' ' . $employee->apellido ); ?></td>
                        <td><?php echo esc_html( $employee->email ); ?></td>
                        <td><?php echo esc_html( $employee->departamento ); ?></td>
                        <td><?php echo esc_html( $employee->puesto ); ?></td>
                        <td>
                            <span class="status status-<?php echo $employee->estado ? 'activo' : 'inactivo'; ?>">
                                <?php echo $employee->estado ? esc_html( __( 'Activo', 'anaconda-hr-suite' ) ) : esc_html( __( 'Inactivo', 'anaconda-hr-suite' ) ); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-employees', [ 'action' => 'edit', 'employee_id' => $employee->id ] ) ); ?>" class="button">
                                <?php echo esc_html( __( 'Editar', 'anaconda-hr-suite' ) ); ?>
                            </a>

                            <a href="<?php echo esc_url( wp_nonce_url( anaconda_hrsuite_admin_url( 'anaconda-hr-suite-employees', [ 'action' => 'delete', 'employee_id' => $employee->id ] ), 'anaconda_hrsuite_nonce' ) ); ?>" class="button button-link-delete" onclick="return confirm('<?php echo esc_attr( __( '¿Estás seguro?', 'anaconda-hr-suite' ) ); ?>')">
                                <?php echo esc_html( __( 'Eliminar', 'anaconda-hr-suite' ) ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7"><?php echo esc_html( __( 'No hay empleados', 'anaconda-hr-suite' ) ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <style>
        .status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-activo {
            background: #d5e8d4;
            color: #003300;
        }

        .status-inactivo {
            background: #f8cecc;
            color: #660000;
        }

        .page-title-action {
            margin-left: 10px;
        }
    </style>
</div>
