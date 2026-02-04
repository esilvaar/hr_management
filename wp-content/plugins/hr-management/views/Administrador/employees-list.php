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
            $fullscreen = isset( $_GET['fullscreen'] ) && $_GET['fullscreen'] === '1';

            // URL de toggle para ver inactivos
            $toggle_url = $show_inactive 
                ? add_query_arg( array( 'page' => 'hrm-empleados', 'tab' => 'list' ), admin_url('admin.php') )
                : add_query_arg( array( 'page' => 'hrm-empleados', 'tab' => 'list', 'show_inactive' => '1' ), admin_url('admin.php') );
            if ( $fullscreen ) $toggle_url = add_query_arg( 'fullscreen', '1', $toggle_url );

            // Botón para administradores Anaconda: ver todos los empleados (view_all)
            $current_user = wp_get_current_user();
            $is_anaconda = in_array( 'administrador_anaconda', (array) $current_user->roles, true );
            $view_all = isset( $_GET['view_all'] ) && $_GET['view_all'] === '1';

            if ( $is_anaconda ) {
                $view_all_url = $view_all 
                    ? add_query_arg( array( 'page' => 'hrm-empleados', 'tab' => 'list' ), admin_url('admin.php') )
                    : add_query_arg( array( 'page' => 'hrm-empleados', 'tab' => 'list', 'view_all' => '1' ), admin_url('admin.php') );
                if ( $show_inactive ) $view_all_url = add_query_arg( 'show_inactive', '1', $view_all_url );
                if ( $fullscreen ) $view_all_url = add_query_arg( 'fullscreen', '1', $view_all_url );
            }
            ?>
            <a href="<?= esc_url( $toggle_url ) ?>" class="btn text-black btn-light btn-sm <?= $show_inactive ? 'active' : '' ?>">
                <span class="dashicons dashicons-<?= $show_inactive ? 'visibility' : 'hidden' ?>"></span>
                <?= $show_inactive ? 'Ver Activos' : 'Ver Inactivos' ?>
            </a>

            <?php if ( $is_anaconda ) : ?>
                <a href="<?= esc_url( $view_all_url ) ?>" class="btn text-black btn-light btn-sm <?= $view_all ? 'active' : '' ?>">
                    <span class="dashicons dashicons-<?= $view_all ? 'visibility' : 'hidden' ?>"></span>
                    <?= $view_all ? 'Ver por Área' : 'Ver Todos' ?>
                </a>
            <?php endif; ?>
            <?php
            $create_url = add_query_arg( array( 'page' => 'hrm-empleados', 'tab' => 'new' ), admin_url('admin.php') );
            if ( $fullscreen ) $create_url = add_query_arg( 'fullscreen', '1', $create_url );
            ?>
            <a href="<?= esc_url( $create_url ) ?>" class="btn text-black btn-light btn-sm">
                <span class="dashicons dashicons-plus-alt2"></span> Nuevo Empleado
            </a>
        </div>
    </div>

    <div class="p-0">
        <div class="table-responsive">
            <table class="rounded table table-hover table-striped mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th scope="col" class="text-center myplugin-col-80">Avatar</th>
                        <th scope="col">Empleado</th>
                        <th scope="col">RUT</th>
                        <th scope="col">Departamento</th>
                        <th scope="col">Cargo</th>
                        <th scope="col">Contacto</th>
                        <th scope="col" class="text-center myplugin-col-200">Acciones</th>
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
                                             class="rounded-circle myplugin-avatar-50"
                                             style="width:50px;height:50px;object-fit:cover;" />
                                    <?php else : ?>
                                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center myplugin-avatar-50"
                                             style="width:50px;height:50px;">
                                            <span class="text-muted fw-bold">
                                                <?= esc_html( strtoupper( substr( $empleado->nombre, 0, 1 ) . substr( $empleado->apellido, 0, 1 ) ) ) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-3 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold hrm-table-text-main">
                                            <?= esc_html( $empleado->nombre . ' ' . $empleado->apellido ) ?>
                                        </span>
                                        <?php if ( isset( $empleado->estado ) && intval( $empleado->estado ) === 0 ) : ?>
                                            <span class="badge bg-danger" title="Empleado inactivo - Acceso bloqueado">
                                                <span class="dashicons dashicons-lock" class="myplugin-icon-12"></span>
                                                Inactivo
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="px-3 py-3">
                                    <span class="font-monospace hrm-table-text-secondary"><?= esc_html( $empleado->rut ) ?></span>
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
                                            <?php
                                                $roles = (array) ( wp_get_current_user()->roles ?? array() );
                                                $can_manage = current_user_can( 'manage_options' ) || $is_anaconda || in_array( 'supervisor', $roles, true ) || current_user_can( 'edit_hrm_employees' );
                                                
                                                // Para administrador_anaconda, verificar si el empleado está fuera de su área de gerencia
                                                $puede_ver_botones = $can_manage;
                                                if ( $is_anaconda && ! current_user_can( 'manage_options' ) ) {
                                                    // Obtener área de gerencia del usuario actual
                                                    global $wpdb;
                                                    $area_usuario = $wpdb->get_var(
                                                        $wpdb->prepare(
                                                            "SELECT area_gerencia FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d LIMIT 1",
                                                            get_current_user_id()
                                                        )
                                                    );
                                                    // Mostrar botones solo si el empleado NO está en su área de gerencia
                                                    $puede_ver_botones = $area_usuario && $empleado->area_gerencia !== $area_usuario;
                                                }
                                            ?>
                                            <?php if ( $show_inactive ) : ?>
                                                <?php if ( $puede_ver_botones ) : ?>
                                                    <form method="post" action="<?= esc_url( admin_url( 'admin.php?page=hrm-empleados&tab=list' ) ) ?>" class="myplugin-inline-block myplugin-m-0">
                                                        <?php wp_nonce_field( 'hrm_toggle_employee_status', 'hrm_toggle_status_nonce' ); ?>
                                                        <input type="hidden" name="hrm_action" value="toggle_employee_status" />
                                                        <input type="hidden" name="employee_id" value="<?= esc_attr( $emp_id ) ?>" />
                                                        <input type="hidden" name="current_estado" value="<?= esc_attr( isset($empleado->estado) ? $empleado->estado : 0 ) ?>" />
                                                        <button type="submit" class="btn btn-sm btn-success" title="Activar" onclick="return confirm('¿Confirmar activación del empleado?');">
                                                            <span class="dashicons dashicons-yes"></span>
                                                        </button>
                                                    </form>

                                                    <form method="post" action="<?= esc_url( admin_url( 'admin.php?page=hrm-empleados&tab=list' ) ) ?>" class="myplugin-inline-block myplugin-m-0">
                                                        <?php wp_nonce_field( 'hrm_delete_employee', 'hrm_delete_employee_nonce' ); ?>
                                                        <input type="hidden" name="hrm_action" value="delete_employee" />
                                                        <input type="hidden" name="employee_id" value="<?= esc_attr( $emp_id ) ?>" />
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('Eliminar este empleado eliminará también su usuario de WordPress y documentos asociados. ¿Continuar?');">
                                                            <span class="dashicons dashicons-trash"></span>
                                                        </button>
                                                    </form>
                                                <?php else : ?>
                                                    <span class="text-muted text-sm">Sin acciones</span>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <a href="<?= esc_url( admin_url( 'admin.php?page=hrm-empleados&tab=profile&id=' . rawurlencode( $emp_id ) ) ) ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Editar">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </a>
                                                <a href="<?= esc_url( admin_url( 'admin.php?page=hrm-empleados&tab=upload&id=' . absint( $emp_id ) ) ) ?>" 
                                                   class="btn btn-sm btn-secondary" 
                                                   title="Subir documentos">
                                                    <span class="dashicons dashicons-upload"></span>
                                                </a>
                                            <?php endif; ?>
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
                                            <span class="dashicons dashicons-lock" class="myplugin-icon-64 myplugin-opacity-50"></span>
                                        </div>
                                        <p class="fs-5 fw-semibold m-2">No hay empleados inactivos.</p>
                                        <a href="?page=hrm-empleados&tab=list" class="btn btn-secondary mt-2">
                                            <span class="dashicons dashicons-visibility"></span> Ver Empleados Activos
                                        </a>
                                    <?php else : ?>
                                        <div class="mb-3">
                                            <span class="dashicons dashicons-admin-users mb-3" class="myplugin-icon-64 myplugin-opacity-50"></span>
                                        </div>
                                        <p class="fs-5 fw-semibold m-2">No hay empleados activos registrados.</p>
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

        <!-- Panel fijo para desactivar empleado -->
        <div id="hrm-desactivar-panel" class="border rounded shadow p-4 mb-4 bg-white myplugin-fixed-panel myplugin-panel-400 myplugin-panel-top-10 myplugin-hidden">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 text-danger"><span class="dashicons dashicons-no"></span> Desactivar Empleado</h5>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-cerrar-desactivar">Cerrar</button>
            </div>
            <div id="hrm-desactivar-msg" class="mb-3"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" id="btn-cancelar-desactivar">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-desactivar">Desactivar</button>
            </div>
        </div>

        <?php
        // Encolar el script de manejo de empleados
        wp_enqueue_script(
            'hrm-employees-list',
            HRM_PLUGIN_URL . 'assets/js/employees-list.js',
            array(),
            HRM_PLUGIN_VERSION,
            true
        );
        ?>
    </div>    
</div>
