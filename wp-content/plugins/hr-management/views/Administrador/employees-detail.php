<?php

if ( ! defined( 'ABSPATH' ) ) exit;

// Pasar datos de departamentos al script JavaScript
echo '<script>
window.hrmEmployeeData = {
    departamentos: ' . json_encode( isset($hrm_departamentos) ? $hrm_departamentos : array() ) . ',
    ajaxUrl: "' . esc_js( admin_url('admin-ajax.php') ) . '"
};
</script>';

// Estilos para campos no editables (diferenciación visual)
echo "<style>
.hrm-readonly{color:#6c757d;font-style:italic;display:flex;align-items:center;gap:6px;cursor:not-allowed}
.hrm-readonly .dashicons{font-size:14px;opacity:.6}
.hrm-readonly .hrm-readonly-text{display:inline-block}
/* Hover: resaltar levemente para indicar no-editable */
.hrm-readonly:hover{background-color:rgba(0,0,0,0.03)}
</style>";

// Si no se ha seleccionado un empleado, mostrar mensaje y salir (estilo simple)
// Nota: Verificar que $employee exista y sea un objeto válido
if ( ! isset( $employee ) || ! is_object( $employee ) || empty( $employee->id ) ) {
    echo '<div class="d-flex align-items-center justify-content-center" style="min-height: 400px;">';
    echo '<h2 style="font-size: 24px; color: #856404; text-align: center; max-width: 500px;"><strong>⚠️ Atención:</strong> Por favor selecciona un usuario para ver su perfil.</h2>';
    echo '</div>';
    return;
}

$current_user_id = get_current_user_id();
$is_own_profile = intval( $employee->user_id ) === $current_user_id;
$user = wp_get_current_user();
$is_admin = in_array('administrator', (array)$user->roles) || in_array('administrador_anaconda', (array)$user->roles);
$is_supervisor = current_user_can('edit_hrm_employees');
// Detectar explícitamente rol 'supervisor' (para bloquear edición de su propio perfil)
$is_role_supervisor = in_array( 'supervisor', (array) $user->roles, true );
$can_edit_employee = hrm_can_edit_employee( $employee->id );

// Determinar campos editables según el rol del usuario
$editable_fields = array();
if ( $is_admin ) {
    // Admin puede editar todos los campos
    $editable_fields = array('nombre','apellido' ,'telefono', 'email', 'departamento', 'puesto', 'estado', 'anos_acreditados_anteriores', 'fecha_ingreso');
} elseif ( $can_edit_employee ) {
    // Gerentes y supervisores pueden editar información básica y de antigüedad del empleado
    $editable_fields = array('nombre','apellido' ,'telefono', 'email', 'departamento', 'puesto', 'anos_acreditados_anteriores', 'fecha_ingreso');
} elseif ( $is_own_profile ) {
    // El empleado puede editar su propia información básica
    $editable_fields = array('nombre','apellido' ,'telefono', 'email', 'fecha_nacimiento');
}

// Permitir edición limitada PROPIA para usuarios con rol 'supervisor' (solo campos personales)
if ( $is_role_supervisor && $is_own_profile && ! $is_admin ) {
    $editable_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
}  

// Si el usuario tiene el rol 'empleado' o 'editor_vacaciones' y no es admin/supervisor, forzar límites adicionales:
$restricted_roles = array( 'empleado', 'editor_vacaciones' );
if ( array_intersect( $restricted_roles, (array) $user->roles ) && ! $is_admin && ! $is_supervisor ) {
    if ( $is_own_profile ) {
        // Puede editar solo información personal básica
        $editable_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
    } else {
        // No puede editar perfiles de otros
        $editable_fields = array();
    }
}  

// Obtener avatar del usuario si existe (con retrocompatibilidad por opción del plugin)
$avatar_url = '';
if ( ! empty( $employee->user_id ) ) {
    $avatar_meta = get_user_meta( $employee->user_id, 'simple_local_avatar', true );
    if ( is_array( $avatar_meta ) && ! empty( $avatar_meta['full'] ) ) {
        $avatar_url = $avatar_meta['full'];
    }
    // Fallback: meta propia del plugin
    if ( empty( $avatar_url ) ) {
        $meta_url = get_user_meta( $employee->user_id, 'hrm_avatar', true );
        if ( $meta_url ) {
            $avatar_url = $meta_url;
        }
    }
    // Fallback final: Gravatar del usuario WP
    if ( empty( $avatar_url ) ) {
        $avatar_url = get_avatar_url( $employee->user_id );
    }
}
// Si no hay user_id o no hubo metas, revisar opción guardada por empleado
if ( empty( $avatar_url ) ) {
    $opt = get_option( 'hrm_avatar_emp_' . absint( $employee->id ) );
    if ( $opt ) {
        $avatar_url = $opt;
    }
}
// Helper para saber si un campo es editable por el usuario actual
function hrm_field_editable($field, $is_admin, $editable_fields) {
    return $is_admin || in_array($field, $editable_fields);
}
?>

<div class="container-fluid mt-4">
    <?php
    // Mostrar contraseña temporal si el handler la dejó en transient (usuario admin que realizó el cambio)
    if ( isset( $_GET['password_changed'] ) && intval( $_GET['password_changed'] ) === 1 ) {
        $admin_id = get_current_user_id();
        $temp_pass = get_transient( 'hrm_temp_new_pass_' . $admin_id );
        if ( $temp_pass ) {
            // Borrar inmediatamente para que solo pueda verse una vez
            delete_transient( 'hrm_temp_new_pass_' . $admin_id );
            ?>
            <div class="notice notice-success is-dismissible mb-3" id="hrm-temp-pass-notice">
                <p><strong>Contraseña actualizada.</strong> Esta es la nueva contraseña temporal (solo visible una vez):</p>
                <div class="d-flex align-items-center gap-2">
                    <input type="password" readonly id="hrm-temp-pass-input" value="<?= esc_attr( $temp_pass ) ?>" class="regular-text" style="margin-right:8px;">
                    <button type="button" class="button" id="hrm-copy-pass">Copiar</button>
                    <button type="button" class="button" id="hrm-toggle-pass-visibility">Mostrar</button>
                    <span id="hrm-copy-feedback" style="display:none;margin-left:8px;color:green;">Copiado</span>
                </div>
            </div>
            <?php
        } else {
            // Si no hay transient, mostrar mensaje genérico
            ?>
            <div class="notice notice-success is-dismissible mb-3"><p>Contraseña actualizada. (No se encontró la contraseña temporal para mostrar.)</p></div>
            <?php
        }
    }
    ?>
    <div class="row">
        <!-- SIDEBAR IZQUIERDO: Avatar e Información -->
        <div class="col-lg-4 mb-4">
            <!-- Avatar -->
            <div class="mb-3">
                <div class="hrm-panel-body text-center">
                    <div class="avatar-hover-container">
                        <?php if ( $avatar_url ) : ?>
                            <img src="<?= esc_url( $avatar_url ) ?>" alt="Avatar" class="hrm-avatar-size">
                        <?php else : ?>
                            <div class="bg-light hrm-avatar-size d-flex align-items-center justify-content-center">
                                <span class="text-muted small">Sin foto</span>
                            </div>
                        <?php endif; ?>

                        <?php if ( $is_own_profile || $is_admin || $is_supervisor ) : ?>
                        <div class="hrm-avatar-overlay">
                            <!-- Botón Cambiar -->
                            <form method="POST" enctype="multipart/form-data">
                                <?php wp_nonce_field( 'hrm_upload_avatar', 'hrm_upload_avatar_nonce' ); ?>
                                <input type="hidden" name="hrm_action" value="upload_avatar">
                                <input type="hidden" name="employee_id" value="<?= absint( $employee->id ) ?>">
                                <label class="btn btn-sm btn-light" style="cursor: pointer;">
                                    <span class="dashicons dashicons-camera"></span>
                                    <input type="file" name="avatar" accept="image/*" class="d-none" onchange="this.form.submit();">
                                </label>
                            </form>
                            <?php if ( ! empty( $avatar_url ) ) : ?>
                            <form method="POST" id="deleteAvatarForm">
                                <?php wp_nonce_field( 'hrm_delete_avatar', 'hrm_delete_avatar_nonce' ); ?>
                                <input type="hidden" name="hrm_action" value="delete_avatar">
                                <input type="hidden" name="employee_id" value="<?= absint( $employee->id ) ?>">
                                <button type="submit" class="btn btn-sm btn-danger" id="deleteAvatarBtn" data-action="delete-avatar" style="cursor: pointer;">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TARJETA: Acceso a Documentos -->
            <div class="hrm-panel">
                <div class="hrm-panel-header">
                    <h5 class="mb-0">
                        <span class="dashicons dashicons-media-document"></span>
                        Documentos
                    </h5>
                </div>
                <div class="hrm-panel-body hrm-doc-panel-body">
                    <a href="<?= esc_url( add_query_arg( array( 'page' => 'hrm-mi-documentos-contratos', 'employee_id' => absint( $employee->id ) ), admin_url( 'admin.php' ) ) ) ?>" class="hrm-doc-btn" title="Ver mis contratos" data-icon-color="#b0b5bd">
                        <div class="hrm-doc-btn-icon">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="hrm-doc-btn-content">
                            <div class="hrm-doc-btn-title">Contrato</div>
                            <div class="hrm-doc-btn-desc">Accede a tu contrato</div>
                        </div>
                        <div class="hrm-doc-btn-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                    </a>
                    <a href="<?= esc_url( add_query_arg( array( 'page' => 'hrm-mi-documentos-liquidaciones', 'employee_id' => absint( $employee->id ) ), admin_url( 'admin.php' ) ) ) ?>" class="hrm-doc-btn" title="Ver mis liquidaciones" data-icon-color="#d0d5db">
                        <div class="hrm-doc-btn-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="hrm-doc-btn-content">
                            <div class="hrm-doc-btn-title">Liquidaciones</div>
                            <div class="hrm-doc-btn-desc">Accede a tus liquidaciones</div>
                        </div>
                        <div class="hrm-doc-btn-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                    </a>
                    <a href="<?= esc_url( admin_url('admin.php?page=hrm-convivencia') ); ?>" class="hrm-doc-btn" title="Ver reglamento interno" data-icon-color="#c5cad3">
                        <div class="hrm-doc-btn-icon">
                            <span class="dashicons dashicons-id"></span>
                        </div>
                        <div class="hrm-doc-btn-content">
                            <div class="hrm-doc-btn-title">Reglamento interno</div>
                            <div class="hrm-doc-btn-desc">Accede al reglamento interno</div>
                        </div>
                        <div class="hrm-doc-btn-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Sección: Acciones de Cuenta (Cambio de Contraseña) movida al sidebar -->
            <?php 
            $user_roles = (array) $user->roles;
            $has_access = true; // Temporalmente siempre true para probar
            echo '<!-- DEBUG: is_admin=' . ($is_admin ? 'true' : 'false') . ', is_supervisor=' . ($is_supervisor ? 'true' : 'false') . ', is_role_supervisor=' . ($is_role_supervisor ? 'true' : 'false') . ', user_roles=' . implode(',', $user_roles) . ', has_access=' . ($has_access ? 'true' : 'false') . ' -->';
            if ( $has_access ) : 
            ?>
            <div class="hrm-panel mt-3">
                <div class="hrm-panel-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <span class="dashicons dashicons-admin-network"></span>
                        Acciones de Cuenta
                    </h5>
                </div>
                <div class="hrm-panel-body">
                    <div>
                        <!-- Botón con estilo similar a los bloques de Documentos -->
                        <button id="hrm-open-pass-modal" type="button" class="hrm-doc-btn" data-icon-color="#c5cad3" role="button" aria-haspopup="dialog" style="width:100%; text-align:left;">
                            <div class="hrm-doc-btn-icon">
                                <span class="dashicons dashicons-lock"></span>
                            </div>
                            <div class="hrm-doc-btn-content">
                                <div class="hrm-doc-btn-title">Cambio de contraseña</div>
                                <div class="hrm-doc-btn-desc">Abrir ventana para actualizar la contraseña del usuario</div>
                            </div>
                            <div class="hrm-doc-btn-arrow">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </div>
                        </button>
                    </div>
                    <!-- No se despliega contenido adicional en el sidebar; la notificación aparece en el área principal tras el cambio -->
                </div>
            </div>
            <?php endif; ?>

            <!-- Modal panel (estilo igual a panel de subida de documentos) -->
            <div id="hrm-pass-panel" class="border rounded shadow p-4 mb-4 bg-white" style="max-width: 520px; margin: 0 auto; display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); z-index: 10000;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><span class="dashicons dashicons-lock"></span> Cambiar Contraseña</h5>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="hrm-close-pass-panel">Cerrar</button>
                </div>
                <div id="hrm-pass-panel-body">
                    <div class="mb-2">
                        <small class="text-muted">Ingrese la nueva contraseña. Marque la casilla para notificar al usuario por correo si desea.</small>
                    </div>
                    <div class="mb-2">
                        <input type="password" id="hrm_panel_new_password" class="form-control" placeholder="Nueva contraseña">
                    </div>
                    <div class="mb-2">
                        <input type="password" id="hrm_panel_confirm_password" class="form-control" placeholder="Confirmar contraseña">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="hrm_panel_notify_user" value="1">
                        <label class="form-check-label" for="hrm_panel_notify_user">Informar al usuario (enviar el nuevo acceso por email)</label>
                    </div>

                    <div id="hrm_panel_pass_feedback" class="form-text text-danger mt-1" style="display:none;"></div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" id="hrm_panel_cancel">Cancelar</button>
                        <button type="button" class="btn btn-success" id="hrm_panel_save">Guardar contraseña</button>
                    </div>
                </div>
            </div>

            <script>
                document.querySelectorAll('.hrm-doc-btn').forEach(btn => {
                    const color = btn.getAttribute('data-icon-color');
                    const icon = btn.querySelector('.hrm-doc-btn-icon');
                    if ( color && icon ) icon.style.backgroundColor = color;
                });
            </script>
            </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="col-lg-8">
                    <!-- FORMULARIO: Datos del Empleado -->
                    <form method="POST" enctype="multipart/form-data" name="hrm_update_employee_form" action="<?= esc_url( add_query_arg( array( 'page' => 'hrm-empleados', 'tab' => 'profile', 'id' => absint( $employee->id ) ), admin_url( 'admin.php' ) ) ) ?>">
                        <?php wp_nonce_field( 'hrm_update_employee', 'hrm_update_employee_nonce' ); ?>
                        <input type="hidden" name="hrm_action" value="update_employee">
                        <input type="hidden" name="hrm_action" value="update_employee">
                        <input type="hidden" name="employee_id" value="<?= absint( $employee->id ) ?>">
                        <!-- Hidden inputs para guardar los años calculados -->
                        <input type="hidden" id="hrm_anos_en_la_empresa_hidden" name="anos_en_la_empresa" value="0">
                        <input type="hidden" id="hrm_anos_totales_trabajados_hidden" name="anos_totales_trabajados" value="0">

                        <?php if ( ! $is_admin ) : ?>
                            <div class="alert alert-info d-flex align-items-center gap-2 py-2 px-3 mb-3">
                                <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                                <small class="mb-0">Los campos con este icono no son editables para tu rol.</small>
                            </div>
                        <?php endif; ?>

                        <!-- TARJETA: Datos Personales -->
                        <div class="hrm-panel mb-3">
                            <div class="hrm-panel-header">
                                <h5 class="mb-0">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    Datos Personales
                                </h5>
                            </div>
                            <div class="hrm-panel-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="nombre" class="form-label">Nombres</label>
                                        <?php if ( hrm_field_editable('nombre', $is_admin, $editable_fields) ) : ?>
                                            <input type="text" id="nombre" name="nombre" value="<?= esc_attr( $employee->nombre ) ?>" class="form-control" required>
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->nombre ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="apellido" class="form-label">Apellidos</label>
                                        <?php if ( hrm_field_editable('apellido', $is_admin, $editable_fields) ) : ?>
                                            <input type="text" id="apellido" name="apellido" value="<?= esc_attr( $employee->apellido ) ?>" class="form-control" required>
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->apellido ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="rut" class="form-label">RUT</label>
                                        <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->rut ) ?></span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <?php if ( hrm_field_editable('email', $is_admin, $editable_fields) ) : ?>
                                            <input type="email" id="email" name="email" value="<?= esc_attr( $employee->email ) ?>" class="form-control">
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->email ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <?php if ( hrm_field_editable('telefono', $is_admin, $editable_fields) ) : ?>
                                            <input type="tel" id="telefono" name="telefono" value="<?= esc_attr( $employee->telefono ?? '' ) ?>" class="form-control">
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->telefono ?? '' ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                        <?php if ( hrm_field_editable('fecha_nacimiento', $is_admin, $editable_fields) ) : ?>
                                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= esc_attr( $employee->fecha_nacimiento && $employee->fecha_nacimiento !== '0000-00-00' ? $employee->fecha_nacimiento : '' ) ?>" class="form-control">
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= $employee->fecha_nacimiento && $employee->fecha_nacimiento !== '0000-00-00' ? esc_html( $employee->fecha_nacimiento ) : '<span class="text-muted">No asignada</span>' ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TARJETA: Datos Laborales -->
                        <div class="hrm-panel mb-3">
                            <div class="hrm-panel-header">
                                <h5 class="mb-0">
                                    <span class="dashicons dashicons-briefcase"></span>
                                    Datos Laborales
                                </h5>
                            </div>
                            <div class="hrm-panel-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="departamento" class="form-label">Departamento</label>
                                        <?php if ( hrm_field_editable('departamento', $is_admin, $editable_fields) ) : ?>
                                            <select id="departamento" name="departamento" class="form-select">
                                                <option value="">Seleccionar</option>
                                                <?php 
                                                if ( ! empty( $hrm_departamentos ) ) {
                                                    foreach ( $hrm_departamentos as $dept ) : ?>
                                                        <option value="<?= esc_attr( $dept ) ?>" <?php selected( $employee->departamento ?? '', $dept ); ?>>
                                                            <?= esc_html( $dept ) ?>
                                                        </option>
                                                    <?php endforeach;
                                                }
                                                ?>
                                            </select>
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->departamento ?? 'Sin asignar' ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="puesto" class="form-label">Puesto</label>
                                        <?php if ( hrm_field_editable('puesto', $is_admin, $editable_fields) ) : ?>
                                            <select id="puesto" name="puesto" class="form-select">
                                                <option value="">Seleccionar</option>
                                                <?php 
                                                if ( ! empty( $hrm_puestos ) ) {
                                                    foreach ( $hrm_puestos as $puesto ) : ?>
                                                        <option value="<?= esc_attr( $puesto ) ?>" <?php selected( $employee->puesto ?? '', $puesto ); ?>>
                                                            <?= esc_html( $puesto ) ?>
                                                        </option>
                                                    <?php endforeach;
                                                }
                                                ?>
                                            </select>
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->puesto ?? 'Sin asignar' ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <?php if ( ! ( in_array( 'editor_vacaciones', (array) $user->roles, true ) && ! $is_admin ) ) : ?>
                                    <div class="col-md-6">
                                        <label for="area_gerencia" class="form-label">Área de Gerencia</label>
                                        <?php if ( hrm_field_editable('area_gerencia', $is_admin, $editable_fields) ) : ?>
                                            <select id="area_gerencia" name="area_gerencia" class="form-select">
                                                <option value="">Seleccionar</option>
                                                <option value="Proyectos" <?php selected( $employee->area_gerencia ?? '', 'Proyectos' ); ?>>Proyectos</option>
                                                <option value="Comercial" <?php selected( $employee->area_gerencia ?? '', 'Comercial' ); ?>>Comercial</option>
                                                <option value="Operaciones" <?php selected( $employee->area_gerencia ?? '', 'Operaciones' ); ?>>Operaciones</option>
                                            </select>
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->area_gerencia ?? 'Sin asignar' ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <label for="fecha_ingreso" class="form-label">Fecha de Ingreso</label>
                                        <?php if ( hrm_field_editable('fecha_ingreso', $is_admin, $editable_fields) ) : ?>
                                            <input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?= esc_attr( $employee->fecha_ingreso && $employee->fecha_ingreso !== '0000-00-00' ? $employee->fecha_ingreso : '' ) ?>" class="form-control">
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= $employee->fecha_ingreso && $employee->fecha_ingreso !== '0000-00-00' ? esc_html( $employee->fecha_ingreso ) : '<span class="text-muted">No asignada</span>' ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Sección: Departamentos a cargo (solo para gerentes) -->
                                
                            </div>
                        </div>

                        <!-- TARJETA: Datos Económicos (visible para todos; editable según permisos) -->
                        <?php // Mostrar Datos Económicos a todos (solo editable según permisos) ?>
                        <div class="hrm-panel mb-3">
                            <div class="hrm-panel-header">
                                <h5 class="mb-0">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    Datos Económicos
                                </h5>
                            </div>
                            <div class="hrm-panel-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="tipo_contrato" class="form-label">Tipo de Contrato</label>
                                        <?php if ( hrm_field_editable('tipo_contrato', $is_admin, $editable_fields) ) : ?>
                                            <select id="tipo_contrato" name="tipo_contrato" class="form-select">
                                                <option value="">Seleccionar</option>
                                                <?php 
                                                if ( ! empty( $hrm_tipos_contrato ) ) {
                                                    foreach ( $hrm_tipos_contrato as $tipo ) : ?>
                                                        <option value="<?= esc_attr( $tipo ) ?>" <?php selected( $employee->tipo_contrato ?? '', $tipo ); ?>>
                                                            <?= esc_html( $tipo ) ?>
                                                        </option>
                                                    <?php endforeach;
                                                }
                                                ?>
                                            </select>
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->tipo_contrato ?? 'Sin asignar' ) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="salario" class="form-label">Salario</label>
                                        <?php if ( hrm_field_editable('salario', $is_admin, $editable_fields) ) : ?>
                                            <input type="number" id="salario" name="salario" value="<?= esc_attr( $employee->salario ?? '' ) ?>" class="form-control" step="0.01" min="0">
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?php echo $employee->salario !== null ? esc_html( number_format( (float) $employee->salario, 2, '.', ',' ) ) : '<span class="text-muted">No asignado</span>'; ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TARJETA: Antigüedad Laboral (visible para todos; editable según permisos) -->
                        <?php // Mostrar Antigüedad a todos (solo editable según permisos) ?>
                        <div class="hrm-panel mb-3">
                            <div class="hrm-panel-header">
                                <h5 class="mb-0">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    Antigüedad Laboral
                                </h5>
                            </div>
                            <div class="hrm-panel-body">
                                <div class="alert alert-info border-start border-4 border-info mb-3">
                                    <small><strong>Nota:</strong> Los años en la empresa se actualizan automáticamente cada aniversario de la fecha de ingreso. El total de años trabajados es la suma de años anteriores + años en la empresa.</small>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="anos_acreditados_anteriores" class="form-label">Años Acreditados Anteriores</label>
                                        <?php if ( hrm_field_editable('anos_acreditados_anteriores', $is_admin, $editable_fields) ) : ?>
                                            <input type="number" id="anos_acreditados_anteriores" name="anos_acreditados_anteriores" value="<?= esc_attr( $employee->anos_acreditados_anteriores ?? '0' ) ?>" class="form-control" step="0.5" min="0" title="Años de experiencia en otras empresas">
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= esc_html( $employee->anos_acreditados_anteriores ?? '0' ) ?></span></div>
                                        <?php endif; ?>
                                        <small class="form-text text-muted d-block mt-1">Experiencia laboral previa</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fecha_ingreso" class="form-label">Fecha de Ingreso <span class="text-danger">*</span></label>
                                        <?php if ( hrm_field_editable('fecha_ingreso', $is_admin, $editable_fields) ) : ?>
                                            <input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?= esc_attr( $employee->fecha_ingreso && $employee->fecha_ingreso !== '0000-00-00' ? $employee->fecha_ingreso : '' ) ?>" class="form-control" title="Fecha de ingreso a la empresa">
                                        <?php else : ?>
                                            <div class="form-control-plaintext hrm-readonly" title="Campo no editable"><span class="dashicons dashicons-lock" aria-hidden="true" style="margin-right:6px;opacity:.6"></span><span class="hrm-readonly-text"><?= $employee->fecha_ingreso && $employee->fecha_ingreso !== '0000-00-00' ? esc_html( $employee->fecha_ingreso ) : '<span class="text-muted">No asignada</span>' ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="anos_en_la_empresa" class="form-label">Años en la Empresa</label>
                                        <div class="input-group">
                                            <input type="number" id="anos_en_la_empresa" name="anos_en_la_empresa" value="<?= esc_attr( $employee->anos_en_la_empresa ?? '0' ) ?>" class="form-control" step="0.1" min="0" readonly title="Se calcula automáticamente">
                                            <span class="input-group-text"><small>años</small></span>
                                        </div>
                                        <small class="form-text text-muted d-block mt-1">Se actualiza automáticamente</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="anos_totales_trabajados" class="form-label">Total de Años Trabajados</label>
                                        <div class="input-group">
                                            <input type="number" id="anos_totales_trabajados" name="anos_totales_trabajados" value="<?= esc_attr( $employee->anos_totales_trabajados ?? '0' ) ?>" class="form-control" step="0.1" min="0" readonly title="Suma de años anteriores + años en la empresa">
                                            <span class="input-group-text"><small>años</small></span>
                                        </div>
                                        <small class="form-text text-muted d-block mt-1">Cálculo automático</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de cambio de contraseña movida al sidebar; aquí se elimina la duplicidad (se controla desde el sidebar) -->
                        <!-- (El modal permanece cargado en la página; el botón en el sidebar abre el modal) -->

                        <!-- BOTONES DE ACCIÓN -->
                        <div class="hrm-panel">
                            <div class="hrm-panel-body">
                                <div class="d-flex gap-2 justify-content-center flex-wrap">
                                    <button type="submit" class="btn btn-success">
                                        <?php if ( $is_own_profile ) : ?>
                                            <span class="dashicons dashicons-update"></span>
                                            Guardar Cambios en Mi Perfil
                                        <?php else : ?>
                                            <span class="dashicons dashicons-update"></span>
                                            Actualizar Empleado
                                        <?php endif; ?>
                                    </button>
                                    
                                    <?php if ( $is_admin && !$is_own_profile ) : ?>
                                        <?php if ( intval( $employee->estado ?? 1 ) === 1 ) : ?>
                                            <button type="button" class="btn btn-danger" id="btn-desactivar-empleado">
                                                <span class="dashicons dashicons-lock"></span>
                                                Desactivar
                                            </button>
                                        <?php else : ?>
                                            <button type="button" class="btn btn-warning" id="btn-activar-empleado">
                                                <span class="dashicons dashicons-unlock"></span>
                                                Activar
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <!-- Hidden inputs para cambio de contraseña desde modal -->
                    <input type="hidden" id="hrm_new_password" name="hrm_new_password" value="">
                    <input type="hidden" id="hrm_confirm_password" name="hrm_confirm_password" value="">
                    <input type="hidden" id="hrm_notify_user" name="hrm_notify_user" value="0">
                    </form>
                </div>
            </div>
        </div>
    </div>


<!-- Panel fijo para desactivar/activar empleado -->
<?php if ( ($is_admin || $is_supervisor) && !$is_own_profile ) : ?>
    <div id="hrm-toggle-panel" class="border rounded shadow p-4 mb-4 bg-white" style="max-width: 400px; margin: 0 auto; display: none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); z-index: 9999;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0" id="hrm-toggle-title"><span class="dashicons"></span> </h5>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-cerrar-toggle">Cerrar</button>
        </div>
        <div id="hrm-toggle-msg" class="mb-3"></div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" id="btn-cancelar-toggle">Cancelar</button>
            <form method="POST" class="d-inline" id="form-toggle-estado">
                <?php wp_nonce_field( 'hrm_toggle_employee_status', 'hrm_toggle_status_nonce' ); ?>
                <input type="hidden" name="hrm_action" value="toggle_employee_status">
                <input type="hidden" name="employee_id" value="<?= absint( $employee->id ) ?>">
                <input type="hidden" name="current_estado" id="input-current-estado" value="<?= intval( $employee->estado ?? 1 ) ?>">
                <button type="submit" class="btn" id="btn-confirmar-toggle">
                    <span class="dashicons"></span>
                    <span id="btn-confirmar-text"></span>
                </button>
            </form>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const panel = document.getElementById('hrm-toggle-panel');
        const btnDesactivar = document.getElementById('btn-desactivar-empleado');
        const btnActivar = document.getElementById('btn-activar-empleado');
        const btnCerrar = document.getElementById('btn-cerrar-toggle');
        const btnCancelar = document.getElementById('btn-cancelar-toggle');
        const formToggle = document.getElementById('form-toggle-estado');
        const inputEstado = document.getElementById('input-current-estado');
        const title = document.getElementById('hrm-toggle-title');
        const msg = document.getElementById('hrm-toggle-msg');
        const btnConfirmar = document.getElementById('btn-confirmar-toggle');
        const btnConfirmarText = document.getElementById('btn-confirmar-text');
        if (btnDesactivar) {
            btnDesactivar.onclick = function() {
                inputEstado.value = '1';
                title.innerHTML = '<span class="dashicons dashicons-warning text-danger"></span> Desactivar Empleado';
                btnConfirmar.className = 'btn btn-danger';
                btnConfirmar.querySelector('.dashicons').className = 'dashicons dashicons-lock';
                btnConfirmarText.textContent = 'Confirmar Desactivación';
                msg.innerHTML = `<strong>¿Estás seguro de desactivar a <span class='text-danger'><?= esc_html( $employee->nombre ) ?> <?= esc_html( $employee->apellido ) ?></span>?<br>Esta acción bloqueará su acceso.</strong>`;
                panel.style.display = 'block';
            };
        }
        if (btnActivar) {
            btnActivar.onclick = function() {
                inputEstado.value = '0';
                title.innerHTML = '<span class="dashicons dashicons-yes text-success"></span> Activar Empleado';
                btnConfirmar.className = 'btn btn-success';
                btnConfirmar.querySelector('.dashicons').className = 'dashicons dashicons-unlock';
                btnConfirmarText.textContent = 'Confirmar Activación';
                msg.innerHTML = `<strong>¿Deseas activar a <span class='text-success'><?= esc_html( $employee->nombre ) ?> <?= esc_html( $employee->apellido ) ?></span>?<br>El acceso será restaurado inmediatamente.</strong>`;
                panel.style.display = 'block';
            };
        }
        btnCerrar.onclick = btnCancelar.onclick = function() {
            panel.style.display = 'none';
        };
    });
    </script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos del formulario
    const fechaIngresoInput = document.getElementById('fecha_ingreso');
    const anosAnterioresInput = document.getElementById('anos_acreditados_anteriores');
    const anosEmpresaInput = document.getElementById('anos_en_la_empresa');
    const anosTotalesInput = document.getElementById('anos_totales_trabajados');

    if (!fechaIngresoInput || !anosAnterioresInput || !anosEmpresaInput || !anosTotalesInput) {
        return; // Salir si no encontramos los elementos (posiblemente no es admin/supervisor)
    }

    /**
     * Calcula los años en la empresa basado en la fecha de ingreso
     * Solo muestra AÑOS COMPLETOS (sin decimales)
     * Se actualiza solo cuando se cumple un aniversario
     */
    function calcularAnosEnEmpresa() {
        const fechaIngreso = fechaIngresoInput.value;
        
        if (!fechaIngreso) {
            anosEmpresaInput.value = '0';
            document.getElementById('hrm_anos_en_la_empresa_hidden').value = '0';
            anosEmpresaInput.title = 'Selecciona una fecha de ingreso';
            return 0;
        }

        // Validar que la fecha no sea futura
        const fechaIngresoObj = new Date(fechaIngreso + 'T00:00:00');
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        if (fechaIngresoObj > hoy) {
            anosEmpresaInput.value = '0';
            document.getElementById('hrm_anos_en_la_empresa_hidden').value = '0';
            anosEmpresaInput.title = 'Error: La fecha de ingreso no puede ser futura';
            anosEmpresaInput.classList.add('is-invalid');
            fechaIngresoInput.classList.add('is-invalid');
            return 0;
        }

        // Remover clase de error si la fecha es válida
        anosEmpresaInput.classList.remove('is-invalid');
        fechaIngresoInput.classList.remove('is-invalid');

        // Cálculo de años completos SOLO
        const diffMs = hoy - fechaIngresoObj;
        const diffDias = diffMs / (1000 * 60 * 60 * 24);
        const anos = (diffDias / 365.25);
        
        // Solo usar la parte ENTERA (años completos)
        const anosCompletos = Math.floor(anos);
        
        anosEmpresaInput.value = anosCompletos;
        // Sincronizar con el hidden input
        document.getElementById('hrm_anos_en_la_empresa_hidden').value = anosCompletos;
        
        // Actualizar el título con información sobre el próximo aniversario
        const proximoAniversario = new Date(fechaIngresoObj);
        proximoAniversario.setFullYear(proximoAniversario.getFullYear() + anosCompletos + 1);
        const diasFaltantes = Math.ceil((proximoAniversario - hoy) / (1000 * 60 * 60 * 24));
        
        anosEmpresaInput.title = `${anosCompletos} año(s) en la empresa. Próximo aniversario en ${diasFaltantes} días`;
        
        return anosCompletos;
    }

    /**
     * Calcula el total de años trabajados
     * Solo suma AÑOS COMPLETOS (sin decimales)
     */
    function calcularTotalAnos() {
        const anosAnteriores = parseInt(anosAnterioresInput.value) || 0;
        const anosEmpresa = parseInt(anosEmpresaInput.value) || 0;
        
        const totalAnos = anosAnteriores + anosEmpresa;
        
        anosTotalesInput.value = totalAnos;
        // Sincronizar con el hidden input
        document.getElementById('hrm_anos_totales_trabajados_hidden').value = totalAnos;
        
        // Actualizar el título con información detallada
        anosTotalesInput.title = `${totalAnos} año(s) de experiencia laboral total`;
        
        return totalAnos;
    }

    /**
     * Actualiza todos los cálculos
     */
    function actualizarCalculos() {
        calcularAnosEnEmpresa();
        calcularTotalAnos();
    }

    // Event listeners
    if (fechaIngresoInput) {
        fechaIngresoInput.addEventListener('change', actualizarCalculos);
        fechaIngresoInput.addEventListener('blur', actualizarCalculos);
    }

    if (anosAnterioresInput) {
        anosAnterioresInput.addEventListener('change', actualizarCalculos);
        anosAnterioresInput.addEventListener('input', actualizarCalculos);
        anosAnterioresInput.addEventListener('blur', actualizarCalculos);
    }

    // Calcular al cargar la página
    actualizarCalculos();
    
    // IMPORTANTE: Sincronizar valores justo antes de enviar el formulario
    const form = document.querySelector('form[name="hrm_update_employee_form"], form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Asegurar que los valores estén actualizados antes de enviar
            if (anosEmpresaInput) {
                document.getElementById('hrm_anos_en_la_empresa_hidden').value = anosEmpresaInput.value;
            }
            if (anosTotalesInput) {
                document.getElementById('hrm_anos_totales_trabajados_hidden').value = anosTotalesInput.value;
            }

            // Validación de cambio de contraseña (si se intenta cambiar)
            const newPass = document.getElementById('hrm_new_password');
            const confirmPass = document.getElementById('hrm_confirm_password');
            const feedback = document.getElementById('hrm_pass_feedback');
            if ( newPass && newPass.value.trim() !== '' ) {
                // Regla mínima: 8 caracteres
                if ( newPass.value.length < 8 ) {
                    e.preventDefault();
                    if (feedback) { feedback.style.display = 'block'; feedback.textContent = 'La contraseña debe tener al menos 8 caracteres.'; }
                    newPass.focus();
                    return false;
                }
                if ( !confirmPass || newPass.value !== confirmPass.value ) {
                    e.preventDefault();
                    if (feedback) { feedback.style.display = 'block'; feedback.textContent = 'Las contraseñas no coinciden.'; }
                    confirmPass ? confirmPass.focus() : newPass.focus();
                    return false;
                }
            }
        });
    }

    // Copiar contraseña al portapapeles cuando esté disponible en la alerta (solo para el usuario que hizo el cambio) y manejar modal
    console.log('DOM loaded, checking panel elements');
    // Panel controls (uses panel fixed style similar to upload panel)
    const openPanelBtn = document.getElementById('hrm-open-pass-modal');
    const panel = document.getElementById('hrm-pass-panel');
    const closeBtn = document.getElementById('hrm-close-pass-panel');
    const cancelBtn = document.getElementById('hrm_panel_cancel');
    const saveBtn = document.getElementById('hrm_panel_save');
    const panelNew = document.getElementById('hrm_panel_new_password');
    const panelConfirm = document.getElementById('hrm_panel_confirm_password');
    const panelNotify = document.getElementById('hrm_panel_notify_user');
    const panelFeedback = document.getElementById('hrm_panel_pass_feedback');

    console.log('openPanelBtn:', openPanelBtn);
    console.log('panel:', panel);

    function showPanel() {
        console.log('showPanel called');
        if ( panel ) {
            panel.style.display = 'block';
            if ( panelNew ) { panelNew.focus(); }
        }
    }
    function hidePanel() {
        if ( panel ) {
            panel.style.display = 'none';
            if ( panelNew ) panelNew.value = '';
            if ( panelConfirm ) panelConfirm.value = '';
            if ( panelFeedback ) { panelFeedback.style.display = 'none'; panelFeedback.textContent = ''; }
        }
    }

    if ( openPanelBtn ) {
        console.log('Adding click listener to openPanelBtn');
        openPanelBtn.addEventListener('click', function() {
            console.log('Button clicked, showing panel');
            showPanel();
        });
    } else {
        console.log('openPanelBtn not found');
    }
    if ( closeBtn ) closeBtn.addEventListener('click', hidePanel);
    if ( cancelBtn ) cancelBtn.addEventListener('click', hidePanel);

    // Guardar: sincronizar con inputs del formulario principal y enviar
    if ( saveBtn ) {
        saveBtn.addEventListener('click', function() {
            if ( !panelNew ) return;
            const newVal = panelNew.value.trim();
            const confirmVal = panelConfirm ? panelConfirm.value.trim() : '';
            if ( newVal === '' ) {
                panelFeedback.style.display = 'block';
                panelFeedback.textContent = 'La contraseña no puede estar vacía si quieres cambiarla.';
                return;
            }
            if ( newVal.length < 8 ) {
                panelFeedback.style.display = 'block';
                panelFeedback.textContent = 'La contraseña debe tener al menos 8 caracteres.';
                return;
            }
            if ( newVal !== confirmVal ) {
                panelFeedback.style.display = 'block';
                panelFeedback.textContent = 'Las contraseñas no coinciden.';
                return;
            }

            // Poner valores en el formulario principal y enviar
            const mainNew = document.getElementById('hrm_new_password');
            const mainConfirm = document.getElementById('hrm_confirm_password');
            const mainNotify = document.getElementById('hrm_notify_user');
            if ( mainNew ) mainNew.value = newVal;
            if ( mainConfirm ) mainConfirm.value = confirmVal;
            if ( mainNotify ) {
                if ( panelNotify && panelNotify.checked ) mainNotify.value = '1';
                else mainNotify.value = '';
            }

            // Enviar el formulario principal
            const mainForm = document.querySelector('form[name="hrm_update_employee_form"], form');
            if ( mainForm ) {
                mainForm.submit();
            }
        });
    }

    // Copy/visibility not needed here (handled earlier when transient exists), but keep handlers if present
    const copyBtn = document.getElementById('hrm-copy-pass');
    const toggleBtn = document.getElementById('hrm-toggle-pass-visibility');
    const passInput = document.getElementById('hrm-temp-pass-input');
    const feedback = document.getElementById('hrm-copy-feedback');

    if ( toggleBtn && passInput ) {
        toggleBtn.addEventListener('click', function() {
            if ( passInput.type === 'password' ) {
                passInput.type = 'text';
                toggleBtn.textContent = 'Ocultar';
            } else {
                passInput.type = 'password';
                toggleBtn.textContent = 'Mostrar';
            }
        });
    }

    if ( copyBtn && passInput ) {
        copyBtn.addEventListener('click', function() {
            const val = passInput.value || '';
            if ( !val ) return;
            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText( val ).then(function() {
                    if ( feedback ) { feedback.style.display = 'inline'; feedback.textContent = 'Copiado'; setTimeout(() => { feedback.style.display = 'none'; }, 2500); }
                }).catch(function() {
                    fallbackCopyTextToClipboard( val, feedback );
                });
            } else {
                fallbackCopyTextToClipboard( val, feedback );
            }
        });
    }

    function fallbackCopyTextToClipboard(text, feedbackEl) {
        const el = document.createElement('textarea');
        el.value = text;
        el.style.position = 'fixed';
        el.style.left = '-9999px';
        document.body.appendChild(el);
        el.select();
        try {
            const ok = document.execCommand('copy');
            if ( ok && feedbackEl ) { feedbackEl.style.display = 'inline'; feedbackEl.textContent = 'Copiado'; setTimeout(() => { feedbackEl.style.display = 'none'; }, 2500); }
        } catch (err) {
            console.error('Fallback copy failed', err);
        }
        document.body.removeChild(el);
    }
});
</script>
