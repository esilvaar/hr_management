<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Datos JS localizados para `employees-detail.js`
wp_localize_script( 'hrm-employees-detail', 'hrmEmployeeData', array(
    'departamentos' => isset( $hrm_departamentos ) ? $hrm_departamentos : array(),
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
) );

// Estilos están en `assets/css/employees-detail.css`; se encolan desde `hrm_enqueue_employee_detail_assets` in `includes/functions.php`.
?>
<!-- Styles moved to assets/css/employees-detail.css -->
<?php

// Validar empleado
if ( ! isset( $employee ) || ! is_object( $employee ) || empty( $employee->id ) ) {
    ?>
    <div class="d-flex flex-column align-items-center justify-content-center text-center myplugin-min-h-500 py-5">
        <div class="mb-5">
            <span class="dashicons dashicons-admin-users myplugin-icon-64 myplugin-opacity-50" style="font-size: 80px; width: 80px; height: 80px;"></span>
        </div>
        <h2 class="myplugin-warning-title mb-5 px-3"><strong>⚠️ Atención:</strong> Por favor selecciona un usuario para ver su perfil.</h2>
        <div class="mt-2" style="max-width: 350px; width: 100%; margin: 0 auto;">
             <?php hrm_get_template_part( 'employee-selector', '', compact( 'tab' ) ); ?>
        </div>
    </div>
    <?php
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

    wp_localize_script( 'hrm-employees-detail', 'hrmPermDebug', $perm_debug );
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
                <input type="text" readonly value="<?= esc_attr( $temp_pass ) ?>" class="regular-text code hrm-select-on-click">
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
                                <label class="btn btn-sm btn-light myplugin-cursor-pointer">
                                    <span class="dashicons dashicons-camera"></span>
                                    <input type="file" name="avatar" accept="image/*" class="d-none hrm-avatar-input">
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
                            <?php 
                            // Mostrar el dropdown de Área Gerencia solo si el departamento es "Gerencia" o "Administracion"
                            $es_gerencia = isset( $employee->departamento ) && ( strtolower( $employee->departamento ) === 'gerencia' || strtolower( $employee->departamento ) === 'administracion' );
                            if ( ( ! ( in_array( 'editor_vacaciones', (array) $user->roles, true ) && ! $is_admin ) ) && $es_gerencia ) : 
                            ?>
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

<div id="hrm-pass-panel" class="border rounded shadow p-4 mb-4 bg-white myplugin-fixed-panel myplugin-panel-520 myplugin-hidden">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><span class="dashicons dashicons-lock"></span> Cambiar Contraseña</h5>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="hrm-close-pass-panel">Cerrar</button>
    </div>
    <div id="hrm-pass-panel-body">
        <div class="alert alert-warning py-2 small">
            <span class="dashicons dashicons-warning myplugin-icon-16"></span> Esto cambiará el acceso a WordPress para el usuario.
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

        <div id="hrm_panel_pass_feedback" class="text-danger mt-1 small myplugin-hidden"></div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-secondary" id="hrm_panel_cancel">Cancelar</button>
            <button type="button" class="btn btn-primary" id="hrm_panel_save">Aplicar cambio y Guardar</button>
        </div>
    </div>
</div>

<div id="hrm-toggle-panel" class="border rounded shadow p-4 mb-4 bg-white myplugin-fixed-panel myplugin-panel-400 myplugin-hidden">
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


