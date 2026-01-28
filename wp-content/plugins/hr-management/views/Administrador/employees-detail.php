<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Pasar datos de departamentos al script JavaScript
echo '<script>
window.hrmEmployeeData = {
    departamentos: ' . json_encode( isset($hrm_departamentos) ? $hrm_departamentos : array() ) . ',
    ajaxUrl: "' . esc_js( admin_url('admin-ajax.php') ) . '"
};
</script>';

// Estilos CSS (Incluye ajuste para que el botón de contraseña se vea igual a los enlaces)
echo "<style>
.hrm-readonly{color:#6c757d;font-style:italic;display:flex;align-items:center;gap:6px;cursor:not-allowed}
.hrm-readonly .dashicons{font-size:14px;opacity:.6}
.hrm-readonly .hrm-readonly-text{display:inline-block}
.hrm-readonly:hover{background-color:rgba(0,0,0,0.03)}

/* Avatar styles are provided via assets/css/employees-detail.css to ensure consistency */
.hrm-avatar-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    padding: 8px;
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
    background: rgba(255,255,255,0.92);
    border-radius: 50%;
    z-index: 5;
}

/* Clase utilitaria para resetear estilo de botón y que parezca enlace del panel */
.hrm-btn-reset {
    background: none;
    border: none;
    padding: 0;
    margin: 0;
    width: 100%;
    text-align: left;
    cursor: pointer;
    display: flex; /* Para alinear icono, texto y flecha igual que los <a> */
    align-items: center;
    justify-content: space-between;
    box-sizing: border-box;
}
.hrm-btn-reset:focus { outline: none; box-shadow: none; }

/* Expandir el enlace para cubrir todo el ancho del panel (compensa el padding del .hrm-panel-body) */
.hrm-panel .hrm-btn-reset {
    width: calc(100% + 2.5rem);
    margin-left: -1.25rem;
    margin-right: -1.25rem;
    padding-left: 1.25rem;
    padding-right: 1.25rem;
}

/* Panel específico que contiene acciones tipo botón-enlace: ocupar toda la altura */
.hrm-panel.hrm-panel-action { display: flex; flex-direction: column; }
.hrm-panel.hrm-panel-action .hrm-panel-body { flex: 1 1 auto; padding: 0; }
.hrm-panel.hrm-panel-action .hrm-btn-reset { height: 100%; align-items: center; }

/* Responsive adjustments to avoid overlap on small screens */
@media (max-width: 1200px) {
    .avatar-hover-container { margin-bottom: 28px; max-width: 180px; }
}
@media (max-width: 992px) {
    .avatar-hover-container { max-width: 160px; margin-bottom: 22px; }
    .hrm-avatar-size { width: 120px; height: 120px; }
}
@media (max-width: 576px) {
    .avatar-hover-container { max-width: 110px; margin-bottom: 14px; }
    .hrm-avatar-size { width: 88px; height: 88px; }
    /* Reset expanded button width to avoid horizontal overflow on narrow screens */
    .hrm-panel .hrm-btn-reset { width: 100%; margin-left: 0; margin-right: 0; padding-left: 0; padding-right: 0; }
    /* Ensure overlay doesn't cover important content: make it compact */
    .hrm-avatar-overlay { padding: 6px; font-size: 13px; }
}

</style>";

// Validar empleado
if ( ! isset( $employee ) || ! is_object( $employee ) || empty( $employee->id ) ) {
    echo '<div class="d-flex align-items-center justify-content-center" style="min-height: 400px;">';
    echo '<h2 style="font-size: 24px; color: #856404; text-align: center; max-width: 500px;"><strong>⚠️ Atención:</strong> Por favor selecciona un usuario para ver su perfil.</h2>';
    echo '</div>';
    return;
}

// Permisos y roles
$current_user_id = get_current_user_id();
$user = wp_get_current_user();
// Considerar propietario si el user_id coincide o si el email WP coincide con el email del empleado
$is_own_profile = ( intval( $employee->user_id ) === $current_user_id ) || ( ! empty( $user->user_email ) && ! empty( $employee->email ) && strtolower( $user->user_email ) === strtolower( $employee->email ) );
$is_admin = in_array('administrator', (array)$user->roles) || in_array('administrador_anaconda', (array)$user->roles);
$is_supervisor = current_user_can('edit_hrm_employees');
$is_role_supervisor = in_array( 'supervisor', (array) $user->roles, true );
$can_edit_employee = hrm_can_edit_employee( $employee->id );

// Logging temporal para depurar permisos (remover en producción)
if ( defined('WP_DEBUG') && WP_DEBUG ) {
    $roles = is_object( $user ) ? (array) $user->roles : array();
    error_log('[HRM-PERM] current_user_id=' . intval($current_user_id) . ' roles=' . implode(',', $roles) . ' is_own_profile=' . ( $is_own_profile ? '1' : '0' ) . ' is_admin=' . ( $is_admin ? '1' : '0' ) . ' is_supervisor=' . ( $is_supervisor ? '1' : '0' ) . ' can_edit_employee=' . ( $can_edit_employee ? '1' : '0' ) . ' employee_user_id=' . intval($employee->user_id) . ' employee_email=' . (string)$employee->email );
}

// También mostrar en la consola del navegador para depuración rápida
if ( defined('WP_DEBUG') && WP_DEBUG ) {
    $perm_debug = array(
        'current_user_id'   => intval( $current_user_id ),
        'roles'             => is_object( $user ) ? array_values( (array) $user->roles ) : array(),
        'is_own_profile'    => (bool) $is_own_profile,
        'is_admin'          => (bool) $is_admin,
        'is_supervisor'     => (bool) $is_supervisor,
        'can_edit_employee' => (bool) $can_edit_employee,
        'employee_user_id'  => intval( $employee->user_id ),
        'employee_email'    => (string) $employee->email,
    );

    echo '<script>console.log("[HRM-PERM]", ' . wp_json_encode( $perm_debug ) . ');</script>';
}

// Determinar campos editables
$editable_fields = array();
if ( $is_admin ) {
    $editable_fields = array('nombre','apellido' ,'telefono', 'email', 'departamento', 'puesto', 'estado', 'anos_acreditados_anteriores', 'fecha_ingreso', 'tipo_contrato', 'salario', 'area_gerencia');
} elseif ( $can_edit_employee ) {
    $editable_fields = array('nombre','apellido' ,'telefono', 'email', 'departamento', 'puesto', 'anos_acreditados_anteriores', 'fecha_ingreso');
} elseif ( $is_own_profile ) {
    $editable_fields = array('nombre','apellido' ,'telefono', 'email', 'fecha_nacimiento');
}

// Supervisor editando su propio perfil
if ( $is_role_supervisor && $is_own_profile && ! $is_admin ) {
    $editable_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
}  

// Roles restringidos
$restricted_roles = array( 'empleado', 'editor_vacaciones' );
if ( array_intersect( $restricted_roles, (array) $user->roles ) && ! $is_admin && ! $is_supervisor ) {
    if ( $is_own_profile ) {
        $editable_fields = array('nombre','apellido','telefono','email','fecha_nacimiento');
    } else {
        $editable_fields = array();
    }
}  

// Obtener Avatar
$avatar_url = '';
if ( ! empty( $employee->user_id ) ) {
    $avatar_meta = get_user_meta( $employee->user_id, 'simple_local_avatar', true );
    if ( is_array( $avatar_meta ) && ! empty( $avatar_meta['full'] ) ) {
        $avatar_url = $avatar_meta['full'];
    }
    if ( empty( $avatar_url ) ) {
        $meta_url = get_user_meta( $employee->user_id, 'hrm_avatar', true );
        if ( $meta_url ) $avatar_url = $meta_url;
    }
    if ( empty( $avatar_url ) ) {
        $avatar_url = get_avatar_url( $employee->user_id );
    }
}
if ( empty( $avatar_url ) ) {
    $opt = get_option( 'hrm_avatar_emp_' . absint( $employee->id ) );
    if ( $opt ) $avatar_url = $opt;
}

function hrm_field_editable($field, $is_admin, $editable_fields) {
    return $is_admin || in_array($field, $editable_fields);
}
?>

<div class="container-fluid mt-4">
    <?php
    // MENSAJES DE ÉXITO DE CONTRASEÑA O CORREO
    if ( isset( $_GET['password_changed'] ) && $_GET['password_changed'] == '1' ) {
        $admin_id = get_current_user_id();
        $temp_pass = get_transient( 'hrm_temp_new_pass_' . $admin_id );
        
        echo '<div class="notice notice-success is-dismissible mb-3"><p><strong>Contraseña actualizada correctamente.</strong></p>';
        
        if ( $temp_pass ) {
            delete_transient( 'hrm_temp_new_pass_' . $admin_id );
            ?>
            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="dashicons dashicons-lock"></span> Nueva clave temporal: 
                <input type="text" readonly value="<?= esc_attr( $temp_pass ) ?>" class="regular-text code" onclick="this.select();">
                <small class="text-muted">(Cópiala, solo se muestra una vez)</small>
            </div>
            <?php
        }
        if ( isset( $_GET['email_sent'] ) && $_GET['email_sent'] == '1' ) {
            echo '<p class="mt-1"><span class="dashicons dashicons-email-alt"></span> Se ha enviado un correo al usuario.</p>';
        }
        echo '</div>';
    }
    ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
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
                                <button type="submit" class="btn btn-sm btn-danger"><span class="dashicons dashicons-trash"></span></button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

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

                    <a href="<?= esc_url( add_query_arg( array( 'page' => 'hrm-mi-documentos-liquidaciones', 'employee_id' => absint( $employee->id ) ), admin_url( 'admin.php' ) ) ) ?>" class="hrm-doc-btn" title="Ver mis liquidaciones" data-icon-color="#b0b5bd">
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
                    <?php
                    // Añadir enlaces para tipos de documento dinámicos (excluyendo nombres reservados)
                    hrm_ensure_db_classes();
                    $db_docs = new HRM_DB_Documentos();
                    $doc_types = $db_docs->get_all_types();
                    if ( ! empty( $doc_types ) ) {
                        $reserved = array_map( 'strtolower', array( 'contrato', 'contratos', 'liquidacion', 'liquidaciones', 'licencia', 'licencias' ) );
                        foreach ( $doc_types as $t_id => $t_name ) {
                            $t_name_l = strtolower( trim( $t_name ) );
                            if ( in_array( $t_name_l, $reserved, true ) ) continue;
                            if ( $t_name_l === 'empresa' ) continue; // don't render Empresa here
                            $url = add_query_arg( array( 'page' => 'hrm-mi-documentos-type-' . intval( $t_id ), 'employee_id' => absint( $employee->id ) ), admin_url( 'admin.php' ) );
                            ?>
                            <a href="<?= esc_url( $url ) ?>" class="hrm-doc-btn" title="<?= esc_attr( $t_name ) ?>" data-icon-color="#b0b5bd">
                                <div class="hrm-doc-btn-icon">
                                    <span class="dashicons dashicons-media-document"></span>
                                </div>
                                <div class="hrm-doc-btn-content">
                                    <div class="hrm-doc-btn-title"><?= esc_html( $t_name ) ?></div>
                                    <div class="hrm-doc-btn-desc">Accede a <?= esc_html( $t_name ) ?></div>
                                </div>
                                <div class="hrm-doc-btn-arrow">
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </div>
                            </a>
                            <?php
                        }
                    }
                    ?>

                    

                    <?php
                    // Documentos Empresa (listado dinámico desde la tabla personalizada)
                    global $wpdb;
                    $table = $wpdb->prefix . 'rrhh_documentos_empresa';
                    $company_docs = $wpdb->get_results( "SELECT id, titulo FROM {$table} ORDER BY fecha_creacion DESC" );
                    if ( ! empty( $company_docs ) ) :
                        foreach ( $company_docs as $cd ) :
                            $cd_id = intval( $cd->id );
                            $cd_title = esc_html( $cd->titulo ? $cd->titulo : 'Documento Empresa ' . $cd_id );
                            $cd_url = esc_url( add_query_arg( array( 'page' => 'hrm-convivencia', 'doc_id' => $cd_id ), admin_url( 'admin.php' ) ) );
                            ?>
                            <a href="<?= $cd_url ?>" class="hrm-doc-btn" title="<?= esc_attr( $cd_title ) ?>" data-icon-color="#b0b5bd">
                                <div class="hrm-doc-btn-icon">
                                    <span class="dashicons dashicons-media-document"></span>
                                </div>
                                <div class="hrm-doc-btn-content">
                                    <div class="hrm-doc-btn-title"><?= $cd_title ?></div>
                                    <div class="hrm-doc-btn-desc">Documento Empresa</div>
                                </div>
                                <div class="hrm-doc-btn-arrow">
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </div>
                            </a>
                            <?php
                        endforeach;
                    endif;
                    ?>

                </div>
            </div>

            <?php 
            // Mostrar solo si es Admin, Supervisor o el propio dueño
            $can_change_pass = $is_admin || $is_supervisor;
            if ( $can_change_pass ) : 
            ?>
            <div class="hrm-panel mt-3 hrm-panel-action">
                <div class="hrm-panel-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><span class="dashicons dashicons-admin-network"></span> Acciones de Cuenta</h5>
                </div>
                <div class="hrm-panel-body">
                    <a href="#" id="hrm-open-pass-modal" class="hrm-doc-btn" data-icon-color="#b0b5bd" role="button" aria-haspopup="dialog">
                        <div class="hrm-doc-btn-icon">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <div class="hrm-doc-btn-content">
                            <div class="hrm-doc-btn-title">Cambio de contraseña</div>
                            <div class="hrm-doc-btn-desc">Actualizar clave de acceso WP</div>
                        </div>
                        <div class="hrm-doc-btn-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div> <div class="col-lg-8">
            <?php $current_page_slug = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'hrm-empleados'; ?>
            <form method="POST" enctype="multipart/form-data" name="hrm_update_employee_form" action="<?= esc_url( admin_url( 'admin.php?page=' . $current_page_slug . '&tab=profile&id=' . absint( $employee->id ) ) ) ?>">
                <?php wp_nonce_field( 'hrm_update_employee', 'hrm_update_employee_nonce' ); ?>
                <input type="hidden" name="hrm_action" value="update_employee">
                <input type="hidden" name="employee_id" value="<?= absint( $employee->id ) ?>">
                
                <input type="hidden" id="hrm_new_password" name="hrm_new_password" value="">
                <input type="hidden" id="hrm_confirm_password" name="hrm_confirm_password" value="">
                <input type="hidden" id="hrm_notify_user" name="hrm_notify_user" value="0">

                <input type="hidden" id="hrm_anos_en_la_empresa_hidden" name="anos_en_la_empresa" value="0">
                <input type="hidden" id="hrm_anos_totales_trabajados_hidden" name="anos_totales_trabajados" value="0">

                <?php if ( ! $is_admin ) : ?>
                    <div class="alert alert-info d-flex align-items-center gap-2 py-2 px-3 mb-3">
                        <span class="dashicons dashicons-lock"></span>
                        <small class="mb-0">Los campos con candado no son editables para tu rol.</small>
                    </div>
                <?php endif; ?>

                <div class="hrm-panel mb-3">
                    <div class="hrm-panel-header"><h5 class="mb-0">Datos Personales</h5></div>
                    <div class="hrm-panel-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombres</label>
                                <input type="text" name="nombre" value="<?= esc_attr( $employee->nombre ) ?>" class="form-control" <?= hrm_field_editable('nombre', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellidos</label>
                                <input type="text" name="apellido" value="<?= esc_attr( $employee->apellido ) ?>" class="form-control" <?= hrm_field_editable('apellido', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">RUT</label>
                                <div class="form-control-plaintext hrm-readonly"><span class="dashicons dashicons-lock"></span> <?= esc_html( $employee->rut ) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" value="<?= esc_attr( $employee->email ) ?>" class="form-control" <?= hrm_field_editable('email', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                            </div>
                        </div>
                         <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" value="<?= esc_attr( $employee->telefono ?? '' ) ?>" class="form-control" <?= hrm_field_editable('telefono', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha Nacimiento</label>
                                <input type="date" name="fecha_nacimiento" value="<?= esc_attr( $employee->fecha_nacimiento ) ?>" class="form-control" <?= hrm_field_editable('fecha_nacimiento', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hrm-panel mb-3">
                    <div class="hrm-panel-header"><h5 class="mb-0">Datos Laborales</h5></div>
                    <div class="hrm-panel-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Departamento</label>
                                <?php if ( hrm_field_editable('departamento', $is_admin, $editable_fields) ) : ?>
                                    <select name="departamento" class="form-select">
                                        <option value="">Seleccionar</option>
                                        <?php if ( ! empty( $hrm_departamentos ) ) { foreach ( $hrm_departamentos as $dept ) { ?>
                                            <option value="<?= esc_attr( $dept ) ?>" <?php selected( $employee->departamento ?? '', $dept ); ?>><?= esc_html( $dept ) ?></option>
                                        <?php } } ?>
                                    </select>
                                <?php else : ?>
                                    <div class="form-control-plaintext hrm-readonly"><span class="dashicons dashicons-lock"></span> <?= esc_html( $employee->departamento ?? 'Sin asignar' ) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Puesto</label>
                                <?php if ( hrm_field_editable('puesto', $is_admin, $editable_fields) ) : ?>
                                    <select name="puesto" class="form-select">
                                        <option value="">Seleccionar</option>
                                        <?php if ( ! empty( $hrm_puestos ) ) { foreach ( $hrm_puestos as $puesto ) { ?>
                                            <option value="<?= esc_attr( $puesto ) ?>" <?php selected( $employee->puesto ?? '', $puesto ); ?>><?= esc_html( $puesto ) ?></option>
                                        <?php } } ?>
                                    </select>
                                <?php else : ?>
                                    <div class="form-control-plaintext hrm-readonly"><span class="dashicons dashicons-lock"></span> <?= esc_html( $employee->puesto ?? 'Sin asignar' ) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                         <div class="row mb-3">
                            <?php if ( ! ( in_array( 'editor_vacaciones', (array) $user->roles, true ) && ! $is_admin ) ) : ?>
                            <div class="col-md-6">
                                <label class="form-label">Área Gerencia</label>
                                <?php if ( hrm_field_editable('area_gerencia', $is_admin, $editable_fields) ) : ?>
                                    <select name="area_gerencia" class="form-select">
                                        <option value="">Seleccionar</option>
                                        <option value="Proyectos" <?php selected( $employee->area_gerencia ?? '', 'Proyectos' ); ?>>Proyectos</option>
                                        <option value="Comercial" <?php selected( $employee->area_gerencia ?? '', 'Comercial' ); ?>>Comercial</option>
                                        <option value="Operaciones" <?php selected( $employee->area_gerencia ?? '', 'Operaciones' ); ?>>Operaciones</option>
                                    </select>
                                <?php else : ?>
                                    <div class="form-control-plaintext hrm-readonly"><span class="dashicons dashicons-lock"></span> <?= esc_html( $employee->area_gerencia ?? 'Sin asignar' ) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de Ingreso</label>
                                <input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?= esc_attr( $employee->fecha_ingreso ) ?>" class="form-control" <?= hrm_field_editable('fecha_ingreso', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="hrm-panel mb-3">
                    <div class="hrm-panel-header"><h5 class="mb-0">Datos Económicos</h5></div>
                    <div class="hrm-panel-body">
                         <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tipo de Contrato</label>
                                <?php if ( hrm_field_editable('tipo_contrato', $is_admin, $editable_fields) ) : ?>
                                    <select name="tipo_contrato" class="form-select">
                                        <option value="">Seleccionar</option>
                                        <?php if ( ! empty( $hrm_tipos_contrato ) ) { foreach ( $hrm_tipos_contrato as $tipo ) { ?>
                                            <option value="<?= esc_attr( $tipo ) ?>" <?php selected( $employee->tipo_contrato ?? '', $tipo ); ?>><?= esc_html( $tipo ) ?></option>
                                        <?php } } ?>
                                    </select>
                                <?php else : ?>
                                    <div class="form-control-plaintext hrm-readonly"><span class="dashicons dashicons-lock"></span> <?= esc_html( $employee->tipo_contrato ?? 'Sin asignar' ) ?></div>
                                <?php endif; ?>
                            </div>
                             <div class="col-md-6">
                                <label class="form-label">Salario</label>
                                <input type="number" name="salario" value="<?= esc_attr( $employee->salario ?? '' ) ?>" class="form-control" step="0.01" <?= hrm_field_editable('salario', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                             </div>
                         </div>
                    </div>
                 </div>

                <div class="hrm-panel mb-3">
                    <div class="hrm-panel-header"><h5 class="mb-0">Antigüedad</h5></div>
                    <div class="hrm-panel-body">
                        <div class="row mb-3">
                             <div class="col-md-6">
                                <label class="form-label">Años Previos</label>
                                <input type="number" id="anos_acreditados_anteriores" name="anos_acreditados_anteriores" value="<?= esc_attr( $employee->anos_acreditados_anteriores ?? '0' ) ?>" class="form-control" step="0.5" <?= hrm_field_editable('anos_acreditados_anteriores', $is_admin, $editable_fields) ? '' : 'readonly' ?>>
                             </div>
                             <div class="col-md-6">
                                <label class="form-label">Años en Empresa</label>
                                <input type="number" id="anos_en_la_empresa" class="form-control" readonly>
                             </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Total Años Trabajados</label>
                                <input type="number" id="anos_totales_trabajados" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hrm-panel">
                    <div class="hrm-panel-body d-flex gap-2 justify-content-center flex-wrap">
                        <button type="submit" class="btn btn-success"><span class="dashicons dashicons-update"></span> Guardar Cambios</button>
                        
                                <?php if ( ( $is_admin || $is_supervisor || $is_role_supervisor ) && ! $is_own_profile ) : ?>
                                    <?php if ( intval( $employee->estado ?? 1 ) === 1 ) : ?>
                                        <button type="button" class="btn btn-danger" id="btn-desactivar-empleado">Desactivar</button>
                                    <?php else : ?>
                                        <button type="button" class="btn btn-warning" id="btn-activar-empleado">Activar</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<div id="hrm-pass-panel" class="border rounded shadow p-4 mb-4 bg-white" style="max-width: 520px; margin: 0 auto; display: none; position: fixed; top: 15%; left: 50%; transform: translateX(-50%); z-index: 10000;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><span class="dashicons dashicons-lock"></span> Cambiar Contraseña</h5>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="hrm-close-pass-panel">Cerrar</button>
    </div>
    <div id="hrm-pass-panel-body">
        <div class="alert alert-warning py-2 small">
            <span class="dashicons dashicons-warning" style="font-size:16px;"></span> Esto cambiará el acceso a WordPress para el usuario.
        </div>
        <div class="mb-2">
            <input type="password" id="hrm_panel_new_password" class="form-control" placeholder="Nueva contraseña (mín 8 caracteres)">
        </div>
        <div class="mb-2">
            <input type="password" id="hrm_panel_confirm_password" class="form-control" placeholder="Confirmar contraseña">
        </div>
        
        <?php if($is_admin && !$is_own_profile): ?>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="hrm_panel_notify_user" value="1">
            <label class="form-check-label" for="hrm_panel_notify_user">Enviar credenciales por correo al usuario</label>
        </div>
        <?php endif; ?>

        <div id="hrm_panel_pass_feedback" class="text-danger mt-1 small" style="display:none;"></div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-secondary" id="hrm_panel_cancel">Cancelar</button>
            <button type="button" class="btn btn-primary" id="hrm_panel_save">Aplicar cambio y Guardar</button>
        </div>
    </div>
</div>

<div id="hrm-toggle-panel" class="border rounded shadow p-4 mb-4 bg-white" style="max-width: 400px; display: none; position: fixed; top: 20%; left: 50%; transform: translateX(-50%); z-index: 9999;">
    <h5 id="hrm-toggle-title" class="mb-3"></h5>
    <div id="hrm-toggle-msg" class="mb-3"></div>
    <div class="d-flex justify-content-end gap-2">
         <button type="button" class="btn btn-secondary" id="btn-cancelar-toggle">Cancelar</button>
         <form method="POST" id="form-toggle-estado">
            <?php wp_nonce_field( 'hrm_toggle_employee_status', 'hrm_toggle_status_nonce' ); ?>
            <input type="hidden" name="hrm_action" value="toggle_employee_status">
            <input type="hidden" name="employee_id" value="<?= absint( $employee->id ) ?>">
            <input type="hidden" name="current_estado" id="input-current-estado" value="<?= intval( $employee->estado ?? 1 ) ?>">
            <button type="submit" class="btn" id="btn-confirmar-toggle">Confirmar</button>
         </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. CÁLCULO DE AÑOS DE ANTIGÜEDAD
    const fi = document.getElementById('fecha_ingreso');
    const ap = document.getElementById('anos_acreditados_anteriores');
    const ae = document.getElementById('anos_en_la_empresa');
    const at = document.getElementById('anos_totales_trabajados');
    const h_ae = document.getElementById('hrm_anos_en_la_empresa_hidden');
    const h_at = document.getElementById('hrm_anos_totales_trabajados_hidden');

    function calcular() {
        if(!fi || !fi.value) return;
        const ingreso = new Date(fi.value + 'T00:00:00');
        const hoy = new Date();
        if(ingreso > hoy) { ae.value = 0; return; }
        
        const diff = hoy - ingreso;
        const anos = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
        
        ae.value = anos;
        if(h_ae) h_ae.value = anos;

        const previos = parseFloat(ap ? ap.value : 0) || 0;
        const total = anos + previos;
        at.value = total;
        if(h_at) h_at.value = total;
    }

    if(fi) fi.addEventListener('change', calcular);
    if(ap) ap.addEventListener('input', calcular);
    calcular(); // Init

    // 2. MODAL CONTRASEÑA
    const openBtn = document.getElementById('hrm-open-pass-modal');
    const panel = document.getElementById('hrm-pass-panel');
    const closeBtn = document.getElementById('hrm-close-pass-panel');
    const cancelBtn = document.getElementById('hrm_panel_cancel');
    const saveModalBtn = document.getElementById('hrm_panel_save');
    
    // Inputs del modal
    const inputNew = document.getElementById('hrm_panel_new_password');
    const inputConfirm = document.getElementById('hrm_panel_confirm_password');
    const inputNotify = document.getElementById('hrm_panel_notify_user');
    const feedback = document.getElementById('hrm_panel_pass_feedback');

    // Inputs ocultos del form principal
    const hiddenPass = document.getElementById('hrm_new_password');
    const hiddenConf = document.getElementById('hrm_confirm_password');
    const hiddenNotify = document.getElementById('hrm_notify_user');
    const mainForm = document.querySelector('form[name="hrm_update_employee_form"]');

    if(openBtn) {
        openBtn.addEventListener('click', function(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            panel.style.display = 'block';
            if(inputNew) inputNew.value = '';
            if(inputConfirm) inputConfirm.value = '';
            if(feedback) feedback.style.display = 'none';
        });
    }

    function closePanel() { panel.style.display = 'none'; }
    if(closeBtn) closeBtn.addEventListener('click', closePanel);
    if(cancelBtn) cancelBtn.addEventListener('click', closePanel);

    if(saveModalBtn) {
        saveModalBtn.addEventListener('click', function() {
            const pass = inputNew.value.trim();
            const conf = inputConfirm.value.trim();

            if(pass.length < 8) {
                feedback.textContent = 'La contraseña debe tener al menos 8 caracteres.';
                feedback.style.display = 'block';
                return;
            }
            if(pass !== conf) {
                feedback.textContent = 'Las contraseñas no coinciden.';
                feedback.style.display = 'block';
                return;
            }

            // Mover datos a form principal
            if(hiddenPass) hiddenPass.value = pass;
            if(hiddenConf) hiddenConf.value = conf; // por seguridad doble check
            if(inputNotify && hiddenNotify) {
                hiddenNotify.value = inputNotify.checked ? '1' : '0';
            }

            // Enviar formulario principal
            mainForm.submit();
        });
    }

    // 3. TOGGLE ESTADO
    const btnDes = document.getElementById('btn-desactivar-empleado');
    const btnAct = document.getElementById('btn-activar-empleado');
    const togglePanel = document.getElementById('hrm-toggle-panel');
    const btnCancelToggle = document.getElementById('btn-cancelar-toggle');
    const toggleTitle = document.getElementById('hrm-toggle-title');
    const toggleMsg = document.getElementById('hrm-toggle-msg');
    const toggleConfirmBtn = document.getElementById('btn-confirmar-toggle');
    const inputEstado = document.getElementById('input-current-estado');

    if(btnDes) {
        btnDes.onclick = function() {
            inputEstado.value = '1';
            toggleTitle.innerHTML = 'Desactivar Empleado';
            toggleTitle.className = 'mb-3 text-danger';
            toggleMsg.innerHTML = '¿Seguro que deseas bloquear el acceso a este empleado?';
            toggleConfirmBtn.className = 'btn btn-danger';
            togglePanel.style.display = 'block';
        }
    }
    if(btnAct) {
        btnAct.onclick = function() {
            inputEstado.value = '0';
            toggleTitle.innerHTML = 'Activar Empleado';
            toggleTitle.className = 'mb-3 text-success';
            toggleMsg.innerHTML = '¿Reactivar acceso al sistema?';
            toggleConfirmBtn.className = 'btn btn-success';
            togglePanel.style.display = 'block';
        }
    }
    if(btnCancelToggle) btnCancelToggle.onclick = function() { togglePanel.style.display = 'none'; }

    // 4. COLOREAR ICONOS DOCUMENTOS
    // Enforce a single icon color for all document buttons to avoid later JS/inline changes
    const ENFORCED_DOC_ICON = '#b0b5bd';
    document.querySelectorAll('.hrm-doc-btn').forEach(btn => {
        const icon = btn.querySelector('.hrm-doc-btn-icon');
        if ( icon ) {
            try { btn.style.setProperty('--hrm-doc-icon', ENFORCED_DOC_ICON); } catch (e) {}
            try { icon.style.backgroundColor = ENFORCED_DOC_ICON; } catch (e) {}
            // normalize data-attribute so other scripts that read it won't change appearance
            try { btn.setAttribute('data-icon-color', ENFORCED_DOC_ICON); } catch (e) {}
        }
    });

    // Observe dynamic additions and apply the same coloring to newly inserted .hrm-doc-btn elements
    const observer = new MutationObserver(mutations => {
        for (const m of mutations) {
            if (!m.addedNodes || !m.addedNodes.length) continue;
            m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.classList && node.classList.contains('hrm-doc-btn')) {
                    const icon = node.querySelector('.hrm-doc-btn-icon');
                    if (icon) {
                        try { node.style.setProperty('--hrm-doc-icon', ENFORCED_DOC_ICON); } catch (e) {}
                        try { icon.style.backgroundColor = ENFORCED_DOC_ICON; } catch (e) {}
                        try { node.setAttribute('data-icon-color', ENFORCED_DOC_ICON); } catch (e) {}
                    }
                }
                // Also check descendants
                const children = node.querySelectorAll ? node.querySelectorAll('.hrm-doc-btn') : [];
                children.forEach(ch => {
                    const icon = ch.querySelector('.hrm-doc-btn-icon');
                    if (icon) {
                        try { ch.style.setProperty('--hrm-doc-icon', ENFORCED_DOC_ICON); } catch (e) {}
                        try { icon.style.backgroundColor = ENFORCED_DOC_ICON; } catch (e) {}
                        try { ch.setAttribute('data-icon-color', ENFORCED_DOC_ICON); } catch (e) {}
                    }
                });
            });
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
});
</script>

<?php
// Carga de scripts de Upload y Listado de Documentos
wp_enqueue_script(
    'hrm-documents-upload',
    HRM_PLUGIN_URL . 'assets/js/documents-upload.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

wp_enqueue_script(
    'hrm-documents-list',
    HRM_PLUGIN_URL . 'assets/js/documents-list.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

wp_enqueue_script(
    'hrm-documents-list-init',
    HRM_PLUGIN_URL . 'assets/js/documents-list-init.js',
    array('jquery', 'hrm-documents-list'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// Variables para JS de documentos
$has_employee = ! empty( $employee );
$employee_id  = $has_employee ? intval( $employee->id ) : 0;
$doc_types_js = array();
if ( ! empty( $hrm_tipos_documento ) ) {
    foreach ( $hrm_tipos_documento as $k => $v ) {
        if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
            $doc_types_js[] = array( 'id' => (int) $k, 'name' => (string) $v );
        } elseif ( is_array( $v ) && isset( $v['id'] ) ) {
            $doc_types_js[] = array( 'id' => (int) $v['id'], 'name' => (string) ( $v['nombre'] ?? $v['name'] ?? '' ) );
        } else {
            $doc_types_js[] = array( 'id' => '', 'name' => (string) $v );
        }
    }
}

wp_localize_script( 'hrm-documents-list-init', 'hrmDocsListData', array(
    'employeeId' => $employee_id,
    'hasEmployee' => $has_employee,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'hrm_get_documents' ),
    'createTypeNonce' => wp_create_nonce( 'hrm_create_type' ),
    'types' => $doc_types_js,
) );
?>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadPanel = document.getElementById('hrm-upload-panel');
    const btnNuevo = document.getElementById('btn-nuevo-documento');
    const btnCerrar = document.getElementById('btn-cerrar-upload');
    const btnCancelar = document.getElementById('btn-cancelar-upload');
    const msgDiv = document.getElementById('hrm-documents-message');
    const hiddenInput = document.getElementById('hrm_upload_employee_id');

    function showSelectEmployeeAlert() {
        const bigMsg = '<div class="alert alert-warning text-center" style="font-size:1.25rem; padding:2rem;"><span class="me-2">⚠️</span><strong>Atención:</strong> Por favor selecciona un usuario para continuar.</div>';
        if (msgDiv) { msgDiv.innerHTML = bigMsg; msgDiv.scrollIntoView({behavior: 'smooth', block: 'center'}); }
        const container = document.getElementById('hrm-documents-container');
        if (container) container.innerHTML = bigMsg;
    }

    function clearAlert() {
        if (msgDiv) msgDiv.innerHTML = '';
        const container = document.getElementById('hrm-documents-container');
        if (container) container.innerHTML = '';
    }

    if (btnNuevo) {
        btnNuevo.addEventListener('click', function(e) {
            const curHasEmployee = btnNuevo.dataset.hasEmployee === '1';
            const curEmployeeId = btnNuevo.dataset.employeeId || '';
            if (!curHasEmployee || !curEmployeeId) {
                e.preventDefault();
                showSelectEmployeeAlert();
                return;
            }
            if (hiddenInput) hiddenInput.value = curEmployeeId;
            uploadPanel.style.display = 'block';
        });
    }

    if (btnCerrar) btnCerrar.onclick = function() { uploadPanel.style.display = 'none'; };
    if (btnCancelar) btnCancelar.onclick = function() { uploadPanel.style.display = 'none'; };

    if (btnNuevo && btnNuevo.dataset.hasEmployee !== '1') showSelectEmployeeAlert();

    window.hrmDocumentsSetEmployee = function(employeeId) {
        if (!btnNuevo) return;
        if (employeeId) {
            btnNuevo.dataset.employeeId = employeeId;
            btnNuevo.dataset.hasEmployee = '1';
            btnNuevo.removeAttribute('disabled');
            btnNuevo.removeAttribute('aria-disabled');
            btnNuevo.title = 'Nuevo Documento';
            if (hiddenInput) hiddenInput.value = employeeId;
            clearAlert();
            if ( typeof window.loadEmployeeDocuments === 'function' ) window.loadEmployeeDocuments();
        } else {
            btnNuevo.dataset.employeeId = '';
            btnNuevo.dataset.hasEmployee = '0';
            btnNuevo.setAttribute('disabled', 'disabled');
            btnNuevo.setAttribute('aria-disabled', 'true');
            if (hiddenInput) hiddenInput.value = '';
            showSelectEmployeeAlert();
        }
    };

    if (typeof hrmDocsListData !== 'undefined' && hrmDocsListData.employeeId) {
        window.hrmDocumentsSetEmployee(hrmDocsListData.employeeId);
    }
});
</script>