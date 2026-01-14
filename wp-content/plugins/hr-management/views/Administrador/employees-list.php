<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Esta vista muestra el listado de todos los empleados
// Acepta `$lista_empleados` (preferido) o `$employees`.

$lista = array();
if ( ! empty( $lista_empleados ) ) {
    $lista = $lista_empleados;
} elseif ( ! empty( $employees ) ) {
    $lista = $employees;
}
?>

<div class="rounded shadow-sm mx-auto mt-3">
    
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center p-3 bg-dark text-white rounded-top">
        <h2 class="mb-0">Directorio de Empleados</h2>
        <div class="d-flex gap-2">
            <?php 
            $show_inactive = isset( $_GET['show_inactive'] ) && $_GET['show_inactive'] === '1';
            $toggle_url = $show_inactive 
                ? '?page=hrm-empleados&tab=list' 
                : '?page=hrm-empleados&tab=list&show_inactive=1';
            $fullscreen = isset( $_GET['fullscreen'] ) ? '&fullscreen=1' : '';
            $toggle_url .= $fullscreen;
            ?>
            <a href="<?= esc_url( $toggle_url ) ?>" class="btn text-black btn-light btn-sm <?= $show_inactive ? 'active' : '' ?>">
                <span class="dashicons dashicons-<?= $show_inactive ? 'visibility' : 'hidden' ?>"></span>
                <?= $show_inactive ? 'Ver Activos' : 'Ver Inactivos' ?>
            </a>
            <a href="?page=hrm-empleados&tab=new<?= $fullscreen ?>" class="btn text-black btn-light btn-sm">
                <span class="dashicons dashicons-plus-alt2"></span> Nuevo Empleado
            </a>
        </div>
    </div>

    <div class="p-0">
        <div class="table-responsive">
            <table class="rounded table table-hover table-striped mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th scope="col" class="text-center" style="width: 80px;">Avatar</th>
                        <th scope="col">Empleado</th>
                        <th scope="col">RUT</th>
                        <th scope="col">Departamento</th>
                        <th scope="col">Cargo</th>
                        <th scope="col">Contacto</th>
                        <th scope="col" class="text-center" style="width: 200px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $lista ) ) : ?>
                        <?php foreach ( $lista as $empleado ) : ?>
                            <?php
                            // Preferir avatar guardado en usermeta u opción
                            $avatar_url = '';
                            if ( ! empty( $empleado->user_id ) ) {
                                $meta = get_user_meta( intval( $empleado->user_id ), 'hrm_avatar', true );
                                if ( ! empty( $meta ) ) $avatar_url = $meta;
                            }
                            if ( empty( $avatar_url ) ) {
                                $opt = get_option( 'hrm_avatar_emp_' . intval( $empleado->id ) );
                                if ( ! empty( $opt ) ) $avatar_url = $opt;
                            }
                            
                            // Determinar ID del empleado
                            $emp_id = '';
                            if ( ! empty( $empleado->id ) ) {
                                $emp_id = $empleado->id;
                            } elseif ( ! empty( $empleado->ID ) ) {
                                $emp_id = $empleado->ID;
                            } elseif ( ! empty( $empleado->user_id ) ) {
                                $emp_id = $empleado->user_id;
                            }
                            ?>
                            <tr>
                                <td class="text-center px-3 py-3">
                                    <?php if ( ! empty( $avatar_url ) ) : ?>
                                        <img src="<?= esc_url( $avatar_url ) ?>" 
                                             alt="<?= esc_attr( $empleado->nombre ) ?>" 
                                             class="rounded-circle" 
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else : ?>
                                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <span class="text-muted fw-bold">
                                                <?= esc_html( strtoupper( substr( $empleado->nombre, 0, 1 ) . substr( $empleado->apellido, 0, 1 ) ) ) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-3 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-dark">
                                            <?= esc_html( $empleado->nombre . ' ' . $empleado->apellido ) ?>
                                        </span>
                                        <?php if ( isset( $empleado->estado ) && intval( $empleado->estado ) === 0 ) : ?>
                                            <span class="badge bg-danger" title="Empleado inactivo - Acceso bloqueado">
                                                <span class="dashicons dashicons-lock" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                                Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-3 py-3">
                                    <span class="font-monospace text-secondary"><?= esc_html( $empleado->rut ) ?></span>
                                </td>

                                <td class="px-3 py-3">
                                    <span class="badge bg-success text-white border">
                                        <?= esc_html( $empleado->departamento ?? 'N/A' ) ?>
                                    </span>
                                </td>

                                <td class="px-3 py-3">
                                    <span class="badge bg-secondary text-white">
                                        <?= esc_html( $empleado->puesto ?? 'N/A' ) ?>
                                    </span>
                                </td>

                                <td class="px-3 py-3">
                                        <?= esc_html( $empleado->email ) ?>
                                </td>

                                <td class="text-center px-3 py-3">
                                    <?php if ( ! empty( $emp_id ) ) : ?>
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="<?= esc_url( admin_url( 'admin.php?page=hrm-empleados&tab=profile&id=' . rawurlencode( $emp_id ) ) ) ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Ver perfil">
                                                <span class="dashicons dashicons-admin-users"></span>
                                            </a>
                                            <a href="<?= esc_url( admin_url( 'admin.php?page=hrm-mi-documentos-contratos&employee_id=' . absint( $emp_id ) ) ) ?>" 
                                               class="btn btn-sm btn-secondary" 
                                               title="Ver documentos">
                                                <span class="dashicons dashicons-media-document"></span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <?php if ( $show_inactive ) : ?>
                                        <div class="mb-3">
                                            <span class="dashicons dashicons-lock" style="font-size: 64px; opacity: 0.5;"></span>
                                        </div>
                                        <p class="fs-5 fw-semibold mb-2">No hay empleados inactivos.</p>
                                        <a href="?page=hrm-empleados&tab=list" class="btn btn-secondary mt-2">
                                            <span class="dashicons dashicons-visibility"></span> Ver Empleados Activos
                                        </a>
                                    <?php else : ?>
                                        <div class="mb-3">
                                            <span class="dashicons dashicons-admin-users" style="font-size: 64px; opacity: 0.5;"></span>
                                        </div>
                                        <p class="fs-5 fw-semibold mb-2">No hay empleados activos registrados.</p>
                                        <a href="?page=hrm-empleados&tab=new" class="btn btn-primary mt-2">
                                            <span class="dashicons dashicons-plus-alt2"></span> Crear Primer Empleado
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>    
</div>
