<?php
// Los estilos se cargan en functions.php mediante hooks de WordPress
// No encolar estilos aquí, ya que es demasiado tarde en el ciclo
// En este caso agregamos un enqueue por-vista para reglas menores y utilidades
wp_enqueue_style( 'hrm-vacaciones-admin', plugins_url( 'hr-management/assets/css/vacaciones-admin.css' ), array(), '1.0.0' );

// Encolar el comportamiento de la vista (JS extraído de inline)
wp_enqueue_script(
    'hrm-vacaciones-admin',
    HRM_PLUGIN_URL . 'assets/js/vacaciones-admin.js',
    array(),
    HRM_PLUGIN_VERSION,
    true
);

// Preparar datos localizados necesarios por el JS
$vacaciones_js_solicitudes = array();
foreach ( $solicitudes as $s ) {
    $vacaciones_js_solicitudes[ isset($s['id_solicitud']) ? $s['id_solicitud'] : 0 ] = array(
        'nombre' => isset($s['nombre']) ? $s['nombre'] . ' ' . ($s['apellido'] ?? '') : '',
        'comentario' => isset($s['comentario_empleado']) ? $s['comentario_empleado'] : ''
    );
}

wp_localize_script( 'hrm-vacaciones-admin', 'hrmVacacionesAdminData', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'sincronizarNonce' => wp_create_nonce( 'hrm_sincronizar_personal' ),
    'solicitudesData' => $vacaciones_js_solicitudes,
) );

if ( ! defined( 'ABSPATH' ) ) exit;

// IMPORTANTE: Forzar actualización de capacidades del usuario actual
$current_user = wp_get_current_user();
if ( $current_user && $current_user->ID ) {
    $current_user->get_role_caps();
}

$search_term = sanitize_text_field( $_GET['empleado'] ?? '' );
$estado_filtro = sanitize_text_field( $_GET['estado'] ?? 'PENDIENTE' );
$tab_activo = sanitize_text_field( $_GET['tab'] ?? 'departamentos' );

// Determinar capacidades del usuario actual
$es_usuario_administrativo = current_user_can( 'edit_hrm_employees' ) || current_user_can( 'manage_hrm_vacaciones' ) || current_user_can( 'manage_options' );

// Si es supervisor (gerente), obtener sus departamentos a cargo desde la tabla de gerencia
// EXCEPTO si es editor de vacaciones (que ve TODO sin filtros)
$es_supervisor = current_user_can( 'edit_hrm_employees' ) && ! current_user_can( 'manage_options' );
$es_editor_vacaciones = current_user_can( 'manage_hrm_vacaciones' ) && ! current_user_can( 'edit_hrm_employees' );
$departamentos_supervisor = array(); // Array de departamentos a cargo
$es_gerente_operaciones = false; // Flag para identificar al Gerente de Operaciones

// Solo filtrar por departamentos si es supervisor (pero no si es editor de vacaciones)
if ( $es_supervisor && ! $es_editor_vacaciones ) {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Primero verificar si es el Gerente de Operaciones (area_gerencia = 'Operaciones')
    $area_gerencia_usuario = $wpdb->get_var( $wpdb->prepare(
        "SELECT area_gerencia FROM {$wpdb->prefix}rrhh_empleados 
         WHERE user_id = %d AND departamento = 'Gerencia' AND estado = 1 LIMIT 1",
        $user_id
    ) );
    
    if ( $area_gerencia_usuario && strtolower( $area_gerencia_usuario ) === 'operaciones' ) {
        // Es el Gerente de Operaciones - puede ver Gerencia, Sistemas y Administración
        $es_gerente_operaciones = true;
        $departamentos_supervisor = array( 'Gerencia', 'Sistemas', 'Administración', 'Administracion' );
        
        // También agregar los departamentos que tenga asignados en gerencia_deptos
        $deptos_adicionales = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT depto_a_cargo FROM {$wpdb->prefix}rrhh_gerencia_deptos 
             WHERE nombre_gerente = (SELECT CONCAT(nombre, ' ', apellido) FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d)
             AND estado = 1",
            $user_id
        ) );
        
        if ( ! empty( $deptos_adicionales ) ) {
            $departamentos_supervisor = array_unique( array_merge( $departamentos_supervisor, $deptos_adicionales ) );
        }
    } else {
        // Supervisor normal - obtener departamentos a cargo desde gerencia_deptos
        $departamentos_supervisor = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT depto_a_cargo FROM {$wpdb->prefix}rrhh_gerencia_deptos 
             WHERE nombre_gerente = (SELECT CONCAT(nombre, ' ', apellido) FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d)
             AND estado = 1
             ORDER BY depto_a_cargo ASC",
            $user_id
        ) );
        
        // Si no encuentra por nombre completo, intentar por nombre solamente
        if ( empty( $departamentos_supervisor ) ) {
            $nombre_gerente = $wpdb->get_var( $wpdb->prepare(
                "SELECT nombre FROM {$wpdb->prefix}rrhh_empleados WHERE user_id = %d LIMIT 1",
                $user_id
            ) );
            
            if ( $nombre_gerente ) {
                $departamentos_supervisor = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT depto_a_cargo FROM {$wpdb->prefix}rrhh_gerencia_deptos 
                     WHERE nombre_gerente LIKE %s AND estado = 1
                     ORDER BY depto_a_cargo ASC",
                    '%' . $nombre_gerente . '%'
                ) );
            }
        }
    }
}

// Paginación: obtener página actual
$items_por_pagina = 20;
$pagina_actual = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

$solicitudes = hrm_get_all_vacaciones( $search_term, $estado_filtro, $pagina_actual, $items_por_pagina );

$total_solicitudes = count( $solicitudes );

// Contadores visibles (SOLO PENDIENTES - criterio operativo)
$count_dia_completo = function_exists( 'hrm_count_vacaciones_visibles' ) ? hrm_count_vacaciones_visibles( 'PENDIENTE' ) : 0;
$count_medio_dia = function_exists( 'hrm_count_medio_dia_visibles' ) ? hrm_count_medio_dia_visibles( 'PENDIENTE' ) : 0;
?>

<div class="wrap">
    <div class="container-fluid px-4">
        <div class="hrm-admin-dashboard">

            <!-- Título principal -->
            <div class="d-flex align-items-center justify-content-between gap-2 mb-4">
                <h1 class="wp-heading-inline fs-2 fw-bold text-primary mb-0">
                    Panel de Gestion de Vacaciones
                </h1>
                <?php
                // Optional action: mark all HRM notifications as read (visible to editor and gerente)
                $current_uid = get_current_user_id();
                $can_mark_all = current_user_can( 'manage_hrm_vacaciones' ) || ( function_exists( 'hrm_user_is_gerente_supervisor' ) && hrm_user_is_gerente_supervisor( $current_uid ) );
                if ( $can_mark_all ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                        <?php wp_nonce_field( 'hrm_mark_all_notifications_read', 'hrm_mark_all_notifications_read_nonce' ); ?>
                        <input type="hidden" name="action" value="hrm_mark_all_notifications_read">
                        <button type="submit" class="btn btn-outline-secondary">Marcar todas como leídas</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Navegación de Tabs -->
            <ul class="nav nav-tabs nav-fill mb-4 border-bottom-2" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $tab_activo === 'solicitudes' ? 'active' : ''; ?> d-flex align-items-center justify-content-center gap-2" 
                            id="tab-solicitudes" 
                            type="button" 
                            role="tab" 
                            aria-controls="contenido-solicitudes" 
                            aria-selected="<?php echo $tab_activo === 'solicitudes' ? 'true' : 'false'; ?>">
                        <span class="hrm-tab-icon">📋</span>
                        <span class="fw-semibold">Solicitudes de Día Completo<?php echo ( $count_dia_completo > 0 ) ? ' (' . intval( $count_dia_completo ) . ')' : ''; ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $tab_activo === 'departamentos' ? 'active' : ''; ?> d-flex align-items-center justify-content-center gap-2" 
                            id="tab-departamentos" 
                            type="button" 
                            role="tab" 
                            aria-controls="contenido-departamentos" 
                            aria-selected="<?php echo $tab_activo === 'departamentos' ? 'true' : 'false'; ?>">
                        <span class="hrm-tab-icon">🏢</span>
                        <span class="fw-semibold">Resumen de Departamentos</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $tab_activo === 'medio-dia' ? 'active' : ''; ?> d-flex align-items-center justify-content-center gap-2" 
                            id="tab-medio-dia" 
                            type="button" 
                            role="tab" 
                            aria-controls="contenido-medio-dia" 
                            aria-selected="<?php echo $tab_activo === 'medio-dia' ? 'true' : 'false'; ?>">
                        <span class="hrm-tab-icon">⏰</span>
                        <span class="fw-semibold">Solicitudes de Medio Día<?php echo ( $count_medio_dia > 0 ) ? ' (' . intval( $count_medio_dia ) . ')' : ''; ?></span>
                    </button>
                </li>
                
            </ul>

            <!-- Tab Content Container -->
            <div class="tab-content">

            <!-- Contenido Tab 1: Solicitudes -->
            <div id="contenido-solicitudes" class="tab-pane fade <?php echo $tab_activo === 'solicitudes' ? 'show active' : ''; ?>" role="tabpanel" aria-labelledby="tab-solicitudes">
    
    <!-- Formulario de búsqueda -->
    <div class="hrm-panel-search shadow border-0 rounded-3 mb-4">
        <div class="hrm-panel-search-body">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">
                
                <div class="col-md-5">
                    <label for="empleado" class="form-label fw-semibold">🔍 Buscar Empleado</label>
                    <input type="text" 
                           id="empleado"
                           name="empleado" 
                           value="<?php echo esc_attr( $_GET['empleado'] ?? '' ); ?>" 
                           placeholder="Buscar por nombre o apellido..." 
                           class="form-control form-control-lg">
                </div>
                
                <div class="col-md-3">
                    <label for="estado" class="form-label fw-semibold">📋 Estado</label>
                    <select name="estado" id="estado" class="form-select form-select-lg hrm-select">
                        <option value="PENDIENTE" <?php selected( $_GET['estado'] ?? 'PENDIENTE', 'PENDIENTE' ); ?>>
                            ⏳ Pendiente (Por Revisar)
                        </option>
                        <option value="APROBADA" <?php selected( $_GET['estado'] ?? '', 'APROBADA' ); ?>>
                            ✅ Aprobada
                        </option>
                        <option value="RECHAZADA" <?php selected( $_GET['estado'] ?? '', 'RECHAZADA' ); ?>>
                            ❌ Rechazada
                        </option>
                        <option value="" <?php selected( $_GET['estado'] ?? '', '' ); ?>>
                            🔄 Todos los estados
                        </option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                        <span>🔍</span> Buscar
                    </button>
                </div>
                
                <?php if ( ! empty( $_GET['empleado'] ) || ! empty( $_GET['estado'] ) ) : ?>
                    <div class="col-md-2">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $_GET['page'] ) ); ?>" 
                           class="btn btn-outline-light btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                            <span>🗑️</span> Limpiar
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabla de solicitudes -->
    <div class="hrm-panel shadow-sm border-0 rounded-3">
        <div class="hrm-panel-header bg-light border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
            <h4 class="mb-0">
                <?php 
                if ( $estado_filtro === 'PENDIENTE' ) {
                    echo '<span style="color: #070707;"> Solicitudes Pendientes</span>';
                } elseif ( $estado_filtro === 'APROBADA' ) {
                    echo '<span style="color: #0b0b0b;"> Solicitudes Aprobadas</span>';
                } elseif ( $estado_filtro === 'RECHAZADA' ) {
                    echo '<span style="color: #080808;"> Solicitudes Rechazadas</span>';
                } else {
                    echo '<span style="color: #060606;">🔄 Todas las Solicitudes</span>';
                }
                ?>
                <span color="black">
                : <strong><?php echo count( $solicitudes ); ?></strong>
            </span>
            </h4>
            
        </div>
        <div class="hrm-panel-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                    <tr class="text-uppercase small text-secondary">
                        <th class="py-3 px-4">👤 Empleado</th>
                        <th class="py-3 px-4"> Tipo</th>
                        <th class="py-3 px-4 text-center">💬 Comentarios</th>
                        <th class="py-3 px-4"> Desde</th>
                        <th class="py-3 px-4"> Hasta</th>
                        <th class="py-3 px-4 text-center"> Días</th>
                        <th class="py-3 px-4 text-center"> Estado</th>
                        <th class="py-3 px-4 text-center hrm-actions-col"> Acciones</th>
                    </tr>
                </thead>
                <tbody class="text-secondary small">
                <?php 
                // Filtrar solicitudes por departamento si es supervisor
                if ( $es_supervisor && ! $es_editor_vacaciones && ! empty( $departamentos_supervisor ) ) {
                    $solicitudes = array_filter( $solicitudes, function( $sol ) use ( $departamentos_supervisor ) {
                        return isset( $sol['departamento'] ) && in_array( $sol['departamento'], $departamentos_supervisor, true );
                    } );
                }
                ?>
                <?php if ( empty( $solicitudes ) ) : ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <div class="fs-1 mb-3 opacity-50">📭</div>
                                <p class="fs-5 fw-semibold mb-2">No hay solicitudes de vacaciones registradas.</p>
                                <?php if ( ! empty( $search_term ) || ! empty( $estado_filtro ) ) : ?>
                                    <p class="text-secondary">Prueba con otros filtros de búsqueda.</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $solicitudes as $s ) : ?>
                        <tr>
                            <!-- Nombre del empleado -->
                            <td class="py-3 px-4 fw-bold text-dark">
                                <?php echo esc_html( $s['nombre'] . ' ' . $s['apellido'] ); ?>
                            </td>
                            
                            <!-- Tipo de ausencia -->
                            <td class="py-3 px-4"><?php echo esc_html( $s['tipo'] ); ?></td>
                            
                            <!-- Botón de comentarios -->
                            <td class="py-3 px-4 text-center">
                                <button class="btn btn-primary btn-sm rounded d-inline-flex align-items-center justify-content-center btn-comentarios" 
                                        data-id="<?php echo esc_attr( $s['id_solicitud'] ); ?>" 
                                        title="Ver comentarios"
                                        style="width: 40px; height: 40px; font-size: 18px; padding: 0;">
                                    💬
                                </button>
                            </td>
                            
                            <!-- Fecha de inicio -->
                            <td class="py-3 px-4">
                                <span class="badge bg-light text-dark border px-3 py-2 font-monospace">
                                    <?php echo esc_html( $s['fecha_inicio'] ); ?>
                                </span>
                            </td>
                            
                            <!-- Fecha de fin -->
                            <td class="py-3 px-4">
                                <span class="badge bg-light text-dark border px-3 py-2 font-monospace">
                                    <?php echo esc_html( $s['fecha_fin'] ); ?>
                                </span>
                            </td>
                            
                            <!-- Total de días -->
                            <td class="py-3 px-4 text-center">
                                <span class="badge bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                      style="width: 45px; height: 45px; font-size: 15px; font-weight: 700;">
                                    <?php echo esc_html( $s['total_dias'] ); ?>
                                </span>
                            </td>
                            
                            <!-- Estado actual -->
                            <td class="py-3 px-4 text-center">
                                <?php 
                                $estado = strtoupper( $s['estado'] ?? '' );
                                $clase_estado = '';
                                
                                if ( $estado === 'APROBADA' ) {
                                    $clase_estado = 'bg-success';
                                } elseif ( $estado === 'RECHAZADA' ) {
                                    $clase_estado = 'bg-danger';
                                } else {
                                    $clase_estado = 'bg-warning';
                                }
                                ?>
                                <span class="badge <?= esc_attr( $clase_estado ) ?> text-white px-3 py-2 text-uppercase fw-bold">
                                    <?php echo esc_html( $s['estado'] ); ?>
                                </span>
                            </td>
                            
                            <!-- Acciones disponibles -->
                            <td class="py-3 px-4 text-center">
                                <!-- Botón VER/EDITAR SOLICITUD -->
                                <a href="<?php echo esc_url( add_query_arg( 'solicitud_id', $s['id_solicitud'], admin_url('admin.php?page=hrm-vacaciones-formulario') ) ); ?>" 
                                   class="btn btn-info btn-sm d-inline-flex align-items-center gap-1 mb-2 mb-md-0"
                                   style="font-size: 0.8rem; min-width: 110px;"
                                   title="Ver o editar solicitud">
                                    <span style="font-size: 0.9rem;">📄</span> Ver/Editar
                                </a>
                                
                                <?php if ( $s['estado'] === 'PENDIENTE' ) : ?>
                                    <?php 
                                    // Validar si la solicitud puede ser aprobada
                                    $validacion = hrm_validar_aprobacion_solicitud( $s['id_solicitud'] );
                                    $puede_aprobar = $validacion['puede_aprobar'];
                                    $razon = $validacion['razon'];
                                    ?>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <!-- Formulario APROBAR -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="d-inline">
                                            <input type="hidden" name="action" value="hrm_aprobar_rechazar_solicitud">
                                            <?php wp_nonce_field( 'hrm_aprobar_solicitud', 'hrm_nonce' ); ?>
                                            <input type="hidden" name="accion" value="aprobar">
                                            <input type="hidden" name="solicitud_id" value="<?php echo esc_attr( $s['id_solicitud'] ); ?>">
                                            <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" 
                                                    style="font-size: 0.8rem; min-width: 90px;"
                                                    <?php disabled( ! $puede_aprobar ); ?>
                                                    title="<?php echo $puede_aprobar ? 'Aprobar solicitud' : esc_attr( $razon ); ?>">
                                                <span style="font-size: 0.9rem;">✅</span> Aprobar
                                            </button>
                                        </form>

                                        <!-- Formulario RECHAZAR -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="d-inline">
                                            <input type="hidden" name="action" value="hrm_aprobar_rechazar_solicitud">
                                            <?php wp_nonce_field( 'hrm_rechazar_solicitud', 'hrm_nonce' ); ?>
                                            <input type="hidden" name="accion" value="rechazar">
                                            <input type="hidden" name="solicitud_id" value="<?php echo esc_attr( $s['id_solicitud'] ); ?>">
                                            <input type="hidden" name="motivo_rechazo" value="">
                                            <button class="btn btn-danger btn-sm d-inline-flex align-items-center gap-1 btn-rechazar-solicitud" 
                                                    style="font-size: 0.8rem; min-width: 90px;"
                                                    data-solicitud-id="<?php echo esc_attr( $s['id_solicitud'] ); ?>"
                                                    title="Rechazar solicitud">
                                                <span style="font-size: 0.9rem;">❌</span> Rechazar
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <!-- ALERTA DE VALIDACIÓN -->
                                    <?php if ( ! $puede_aprobar ) : ?>
                                        <div class="alert alert-warning alert-dismissible fade show mt-2 mb-0 p-2 small" role="alert" style="max-width: 300px; margin: 8px auto 0;">
                                            <div class="d-flex align-items-start gap-2">
                                                <span class="fs-6">⚠️</span>
                                                <div class="small">
                                                    <strong>No se puede aprobar:</strong><br>
                                                    <?php echo esc_html( $razon ); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="badge bg-secondary text-white">Procesada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Controles de Paginación -->
        <?php if ( $total_solicitudes >= $items_por_pagina || $pagina_actual > 1 ) : ?>
            <div class="hrm-panel-footer border-top px-4 py-3">
                <nav aria-label="Paginación de solicitudes">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ( $pagina_actual > 1 ) : ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo esc_url( add_query_arg( 'paged', $pagina_actual - 1 ) ); ?>" aria-label="Anterior">
                                    <span aria-hidden="true">« Anterior</span>
                                </a>
                            </li>
                        <?php else : ?>
                            <li class="page-item disabled">
                                <span class="page-link">« Anterior</span>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item active">
                            <span class="page-link">Página <?php echo $pagina_actual; ?></span>
                        </li>
                        
                        <?php if ( $total_solicitudes >= $items_por_pagina ) : ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo esc_url( add_query_arg( 'paged', $pagina_actual + 1 ) ); ?>" aria-label="Siguiente">
                                    <span aria-hidden="true">Siguiente »</span>
                                </a>
                            </li>
                        <?php else : ?>
                            <li class="page-item disabled">
                                <span class="page-link">Siguiente »</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

            </div> <!-- Cierre del tab-pane contenido-solicitudes -->

            <!-- Contenido Tab 2: Departamentos -->
            <div id="contenido-departamentos" class="tab-pane fade <?php echo $tab_activo === 'departamentos' ? 'show active' : ''; ?>" role="tabpanel" aria-labelledby="tab-departamentos">
                <div class="hrm-panel shadow-sm border-0 rounded-3">
                    <div class="hrm-panel-header bg-light border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                        <h2 class="fs-5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <span>🏢</span> Resumen de Departamentos
                        </h2>
                        <button 
                            id="btnSincronizarPersonal"
                            type="button"
                            class="btn btn-sm btn-primary d-flex align-items-center gap-2"
                            data-nonce="<?php echo esc_attr( wp_create_nonce('hrm_sincronizar_personal') ); ?>"
                            title="Sincronizar datos de personal vigente">
                            <span>🔄</span>
                            <span>Sincronizar Personal</span>
                        </button>
                    </div>
                    <div class="hrm-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 align-middle">
                                <thead class="table-dark">
                                    <tr class="text-uppercase small text-secondary">
                                        <th class="py-3 px-4">Departamento</th>
                                        <th class="py-3 px-4 text-center">Total Empleados</th>
                                        <th class="py-3 px-4 text-center">Personal Activo </th>
                                        <th class="py-3 px-4 text-center">Personal en Vacaciones</th>
                                    </tr>
                                </thead>
                                <tbody class="text-secondary small">
                                    <?php 
                                    $departamentos = hrm_get_all_departamentos();
                                    
                                    // Filtrar departamentos si es supervisor (solo sus departamentos a cargo)
                                    // EXCEPTO si es editor de vacaciones (que ve todos)
                                    if ( $es_supervisor && ! $es_editor_vacaciones && ! empty( $departamentos_supervisor ) ) {
                                        $departamentos = array_filter( $departamentos, function( $depto ) use ( $departamentos_supervisor ) {
                                            return isset( $depto['nombre_departamento'] ) && in_array( $depto['nombre_departamento'], $departamentos_supervisor, true );
                                        } );
                                    }
                                    
                                    if ( empty( $departamentos ) ) : 
                                    ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <div class="text-muted">
                                                    <p class="fs-6 fw-semibold mb-1">No hay departamentos registrados.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ( $departamentos as $depto ) : 
                                            $total = (int) $depto['total_empleados'];
                                            $nombre_depto = $depto['nombre_departamento'];
                                            
                                            // Obtener personal activo hoy (trabajando)
                                            $activo_hoy = hrm_get_personal_activo_hoy( $nombre_depto );
                                            
                                            // Obtener personal en vacaciones hoy
                                            $vacaciones_hoy = hrm_get_personal_vacaciones_hoy( $nombre_depto );
                                            
                                            // Determinar estado visual del personal activo
                                            $estado_activo = $activo_hoy > 0 ? 'success' : ($activo_hoy === 0 && $total > 0 ? 'danger' : 'secondary');
                                            $icono_activo = $activo_hoy > 0 ? '' : '';
                                        ?>
                                            <tr>
                                                <td class="py-3 px-4 fw-bold text-dark">
                                                    <?php echo esc_html( $nombre_depto ); ?>
                                                </td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="text-dark fw-bold" style="font-size: 1.1rem; font-weight: 700;">
                                                        <?php echo esc_html( $total ); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="text-dark" style="font-size: 1.2rem; font-weight: 800;">
                                                        <?php echo $icono_activo; ?> <?php echo esc_html( $activo_hoy ); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-center">
                                                    <span class="fw-bold" style="font-size: 1.1rem;">
                                                        <?php 
                                                        if ( $vacaciones_hoy > 0 ) {
                                                            // Obtener datos para el tooltip - Se actualiza cada vez que se carga la página
                                                            $tooltip_data = hrm_get_tooltip_vacaciones_hoy( $nombre_depto );
                                                            echo '<div class="tooltip-vacaciones hrm-tooltip-dinamico" style="display: inline-block;" data-departamento="' . esc_attr( $nombre_depto ) . '">';
                                                            echo '<button type="button" class="btn btn-link btn-vacaciones-detalle p-0" data-departamento="' . esc_attr( $nombre_depto ) . '" style="color: #0d6efd; text-decoration: none; font-size: 1.1rem; cursor: pointer; border: none; background: none;">🏖️ ' . esc_html( $vacaciones_hoy ) . '</button>';
                                                            echo '<span class="tooltip-text">' . nl2br( esc_html( $tooltip_data ) ) . '</span>';
                                                            echo '</div>';
                                                        } else {
                                                            echo '<span style="color: #12171c;">Sin empleados de vacaciones </span>';
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div> <!-- Cierre del tab-pane contenido-departamentos -->

            <!-- Contenido Tab 3: Solicitudes de Medio Día -->
            <div id="contenido-medio-dia" class="tab-pane fade <?php echo $tab_activo === 'medio-dia' ? 'show active' : ''; ?>" role="tabpanel" aria-labelledby="tab-medio-dia">
                
                <!-- Formulario de búsqueda -->
                <div class="hrm-panel-search shadow border-0 rounded-3 mb-4">
                    <div class="hrm-panel-search-body">
                        <form method="get" class="row g-3 align-items-end">
                            <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">
                            <input type="hidden" name="tab" value="medio-dia">
                            
                            <div class="col-md-5">
                                <label for="empleado_md" class="form-label fw-semibold">🔍 Buscar Empleado</label>
                                <input type="text" 
                                       id="empleado_md"
                                       name="empleado_md" 
                                       value="<?php echo esc_attr( $_GET['empleado_md'] ?? '' ); ?>" 
                                       placeholder="Buscar por nombre o apellido..." 
                                       class="form-control form-control-lg">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="estado_md" class="form-label fw-semibold">📋 Estado</label>
                                <select name="estado_md" id="estado_md" class="form-select form-select-lg hrm-select">
                                    <option value="PENDIENTE" <?php selected( $_GET['estado_md'] ?? 'PENDIENTE', 'PENDIENTE' ); ?>>
                                        ⏳ Pendiente (Por Revisar)
                                    </option>
                                    <option value="APROBADA" <?php selected( $_GET['estado_md'] ?? '', 'APROBADA' ); ?>>
                                        ✅ Aprobada
                                    </option>
                                    <option value="RECHAZADA" <?php selected( $_GET['estado_md'] ?? '', 'RECHAZADA' ); ?>>
                                        ❌ Rechazada
                                    </option>
                                    <option value="" <?php selected( $_GET['estado_md'] ?? '', '' ); ?>>
                                        🔄 Todos los estados
                                    </option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <span class="dashicons dashicons-search me-1"></span> Filtrar
                                </button>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="<?php echo esc_url( remove_query_arg( ['empleado_md', 'estado_md'] ) ); ?>" class="btn btn-outline-secondary btn-lg w-100">
                                    <span class="dashicons dashicons-image-rotate me-1"></span> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Solicitudes de Medio Día -->
                <div class="hrm-panel shadow-sm border-0 rounded-3">
                    <div class="hrm-panel-header bg-light border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                        <h4 class="mb-0">
                            <?php 
                            $estado_md = $_GET['estado_md'] ?? 'PENDIENTE';
                            if ( $estado_md === 'PENDIENTE' ) {
                                echo '<span style="color: #0b0b0b;"> Solicitudes de Medio Día Pendientes</span>';
                            } elseif ( $estado_md === 'APROBADA' ) {
                                echo '<span style="color: #0f100f;"> Solicitudes de Medio Día Aprobadas</span>';
                            } elseif ( $estado_md === 'RECHAZADA' ) {
                                echo '<span style="color: #0f0e0e;"> Solicitudes de Medio Día Rechazadas</span>';
                            } else {
                                echo '<span style="color: #121212;"> Todas las Solicitudes de Medio Día</span>';
                            }
                            ?>
                            
                        </h4>
                        
                    </div>
                    <div class="hrm-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover border">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3 px-4">👤 Empleado</th>
                                        <th class="py-3 px-4 text-center">📅 Fecha</th>
                                        <th class="py-3 px-4 text-center">⏰ Período</th>
                                        <th class="py-3 px-4 text-center">📝 Estado</th>
                                        <th class="py-3 px-4 text-center">⚙️ Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Paginación para medio día
                                    $pagina_md = isset( $_GET['paged_md'] ) ? max( 1, intval( $_GET['paged_md'] ) ) : 1;
                                    
                                    $medio_dias = hrm_get_solicitudes_medio_dia( 
                                        $_GET['empleado_md'] ?? '', 
                                        $_GET['estado_md'] ?? 'PENDIENTE',
                                        $pagina_md,
                                        $items_por_pagina
                                    );
                                    
                                    if ( ! empty( $medio_dias ) ) : 
                                        foreach ( $medio_dias as $solicitud ) :
                                            $nombre_empleado = $solicitud['nombre'] . ' ' . $solicitud['apellido'];
                                            $fecha = date( 'd/m/Y', strtotime( $solicitud['fecha_inicio'] ) );
                                            $periodo = ucfirst( $solicitud['periodo_ausencia'] );
                                    
                                    // Colorear el estado
                                    $class_estado = '';
                                    $icono_estado = '';
                                    if ( $solicitud['estado'] === 'PENDIENTE' ) {
                                        $class_estado = 'badge bg-warning text-dark';
                                        $icono_estado = '⏳';
                                    } elseif ( $solicitud['estado'] === 'APROBADA' ) {
                                        $class_estado = 'badge bg-success';
                                        $icono_estado = '✅';
                                    } elseif ( $solicitud['estado'] === 'RECHAZADA' ) {
                                        $class_estado = 'badge bg-danger';
                                        $icono_estado = '❌';
                                    }
                                    ?>
                                    <tr>
                                        <td class="py-3 px-4"><?php echo esc_html( $nombre_empleado ); ?></td>
                                        <td class="py-3 px-4 text-center"><?php echo esc_html( $fecha ); ?></td>
                                        <td class="py-3 px-4 text-center">
                                            <?php 
                                            if ( $periodo === 'Mañana' ) {
                                                echo '<span color="black"> Mañana</span>';
                                            } else {
                                                echo '<span color="black"> Tarde</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="<?php echo esc_attr( $class_estado ); ?>">
                                                <?php echo esc_html( $icono_estado . ' ' . $solicitud['estado'] ); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <!-- Botón VER/EDITAR -->
                                            
                                            
                                            <?php if ( $solicitud['estado'] === 'PENDIENTE' ) : ?>
                                                <div class="d-flex gap-2 justify-content-center flex-wrap mt-2">
                                                    <!-- Formulario APROBAR -->
                                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="d-inline">
                                                        <input type="hidden" name="action" value="hrm_aprobar_rechazar_medio_dia">
                                                        <?php wp_nonce_field( 'hrm_aprobar_medio_dia_form', 'hrm_nonce' ); ?>
                                                        <input type="hidden" name="accion" value="aprobar">
                                                        <input type="hidden" name="solicitud_id" value="<?php echo esc_attr( $solicitud['id_solicitud'] ); ?>">
                                                        <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" 
                                                                style="font-size: 0.8rem; min-width: 90px;"
                                                                title="Aprobar solicitud">
                                                            <span style="font-size: 0.9rem;">✅</span> Aprobar
                                                        </button>
                                                    </form>

                                                    <!-- Formulario RECHAZAR -->
                                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="d-inline">
                                                        <input type="hidden" name="action" value="hrm_aprobar_rechazar_medio_dia">
                                                        <?php wp_nonce_field( 'hrm_rechazar_medio_dia_form', 'hrm_nonce' ); ?>
                                                        <input type="hidden" name="accion" value="rechazar">
                                                        <input type="hidden" name="solicitud_id" value="<?php echo esc_attr( $solicitud['id_solicitud'] ); ?>">
                                                        <input type="hidden" name="motivo_rechazo" value="">
                                                        <button class="btn btn-danger btn-sm d-inline-flex align-items-center gap-1 btn-rechazar-medio-dia" 
                                                                style="font-size: 0.8rem; min-width: 90px;"
                                                                data-solicitud-id="<?php echo esc_attr( $solicitud['id_solicitud'] ); ?>"
                                                                title="Rechazar solicitud">
                                                            <span style="font-size: 0.9rem;">❌</span> Rechazar
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else : ?>
                                                <span class="badge bg-secondary text-white">Procesada</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="py-4 px-4 text-center text-muted">
                                        <span style="font-size: 2rem;"></span>
                                        <p class="mt-2">No hay solicitudes de medio día que coincidan con los filtros.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Controles de Paginación para Medio Día -->
                        <?php if ( count( $medio_dias ) >= $items_por_pagina || $pagina_md > 1 ) : ?>
                            <div class="hrm-panel-footer border-top px-4 py-3">
                                <nav aria-label="Paginación de solicitudes de medio día">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ( $pagina_md > 1 ) : ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo esc_url( add_query_arg( 'paged_md', $pagina_md - 1 ) ); ?>" aria-label="Anterior">
                                                    <span aria-hidden="true">« Anterior</span>
                                                </a>
                                            </li>
                                        <?php else : ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">« Anterior</span>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item active">
                                            <span class="page-link">Página <?php echo $pagina_md; ?></span>
                                        </li>
                                        
                                        <?php if ( count( $medio_dias ) >= $items_por_pagina ) : ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo esc_url( add_query_arg( 'paged_md', $pagina_md + 1 ) ); ?>" aria-label="Siguiente">
                                                    <span aria-hidden="true">Siguiente »</span>
                                                </a>
                                            </li>
                                        <?php else : ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Siguiente »</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div> <!-- Cierre del tab-pane contenido-medio-dia -->

            </div> <!-- Cierre del tab-content -->

        </div>
    </div>
</div>


<!-- =====================================================
     MODAL DE RECHAZO CON MOTIVO
     ===================================================== -->
<div id="modalRechazo" class="modal-rechazo">
    <div class="modal-contenido bg-white rounded-4 shadow-lg p-4" style="max-width: 650px; width: 90%;">
        <div class="modal-header border-bottom border-3 border-danger pb-3 mb-4">
            <h2 class="modal-titulo fs-4 fw-bold text-danger d-flex align-items-center gap-2 mb-0">
                <span>⛔</span> Rechazar Solicitud de Vacaciones
            </h2>
            <button type="button" class="modal-rechazo-cerrar" aria-label="Close">×</button>
        </div>
        
        <form id="formRechazo" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="hrm_aprobar_rechazar_solicitud">
            <input type="hidden" name="accion" value="rechazar">
            <input type="hidden" id="solicitudIdRechazo" name="solicitud_id" value="">
            <input type="hidden" id="nonceRechazo" name="hrm_nonce" value="">
            
            <div class="modal-body">
                <div class="alert alert-danger border-start border-4 border-danger mb-3">
                    <span class="fw-semibold">📝</span> Por favor ingresa el motivo del rechazo. Este mensaje será enviado al empleado.
                </div>
                
                <div class="mb-3">
                    <label for="motivoRechazo" class="form-label fw-bold">Motivo del Rechazo</label>
                    <textarea 
                        id="motivoRechazo" 
                        name="motivo_rechazo" 
                        class="form-control"
                        placeholder="Ejemplo: Falta de cobertura en el departamento durante esas fechas..."
                        rows="5"
                        maxlength="1000"
                        required
                    ></textarea>
                    <div class="form-text">Mínimo 5 caracteres, máximo 1000</div>
                </div>
            </div>
            
            <div class="modal-footer border-top pt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-secondary btn-cancelar-rechazo">Cancelar</button>
                <button type="button" class="btn btn-danger btn-confirmar-rechazo">Rechazar Solicitud</button>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================
     MODAL DE COMENTARIOS
     ===================================================== -->
<div id="modalComentarios" class="modal-comentarios">
    <div class="modal-contenido bg-white rounded-4 shadow-lg p-4" style="max-width: 600px; width: 90%;">
        <div class="modal-header border-bottom pb-3 mb-4">
            <h2 class="modal-titulo fs-4 fw-bold text-primary d-flex align-items-center gap-2 mb-0">
                <span>💬</span> Comentarios de la Solicitud
            </h2>
            <button type="button" class="modal-cerrar" aria-label="Close">×</button>
        </div>
        
        <div class="modal-body">
            <div class="alert alert-primary mb-3">
                <strong>Empleado:</strong> <span id="modalEmpleado">-</span>
            </div>
            
            <div class="bg-light p-3 rounded-3 border-start border-4 border-primary" id="modalComentarioContenido">
                Cargando comentarios...
            </div>
        </div>
        
        <div class="modal-footer border-top pt-3 d-flex justify-content-end">
            <button type="button" class="btn btn-primary btn-cerrar-modal">Cerrar</button>
        </div>
    </div>
</div>
<!-- =====================================================
     MODAL DE EMPLEADOS EN VACACIONES
     ===================================================== -->
<div id="modalVacacionesDetalle" class="modal-vacaciones-detalle" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-contenido bg-white rounded-4 shadow-lg p-4" style="max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div class="modal-header border-bottom border-3 pb-3 mb-4 d-flex justify-content-between align-items-center">
            <h2 class="modal-titulo fs-4 fw-bold text-info d-flex align-items-center gap-2 mb-0">
                <span>🏖️</span> Empleados en Vacaciones Hoy
            </h2>
            <button type="button" class="modal-vacaciones-cerrar" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
        </div>
        
        <div class="modal-body" id="modalVacacionesContenido">
            <div class="text-center text-muted">
                <p class="spinner-border spinner-border-sm me-2"></p> Cargando información...
            </div>
        </div>
        
        <div class="modal-footer border-top pt-3 d-flex justify-content-end">
            <button type="button" class="btn btn-secondary modal-vacaciones-cerrar">Cerrar</button>
        </div>
    </div>
</div>

<!-- =====================================================
     MODAL VER DETALLES DE SOLICITUD DE MEDIO DÍA
     ===================================================== -->
<div id="modalVerMedioDia" class="modal-ver-medio-dia" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-contenido bg-white rounded-4 shadow-lg p-4" style="max-width: 600px; width: 90%;">
        <div class="modal-header border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
            <h2 class="modal-titulo fs-4 fw-bold text-primary d-flex align-items-center gap-2 mb-0">
                <span>👁️</span> Detalles de Solicitud de Medio Día
            </h2>
            <button type="button" class="btn-cerrar-md" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
        </div>
        
        <div class="modal-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="bg-light p-3 rounded-3">
                        <small class="text-muted fw-bold">👤 EMPLEADO</small>
                        <div class="fw-bold fs-5 text-dark" id="mdEmpleado">-</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="bg-light p-3 rounded-3">
                        <small class="text-muted fw-bold">📅 FECHA</small>
                        <div class="fw-bold fs-5 text-dark" id="mdFecha">-</div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="bg-light p-3 rounded-3">
                        <small class="text-muted fw-bold">⏰ PERÍODO</small>
                        <div class="fw-bold fs-5 text-dark" id="mdPeriodo">-</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="bg-light p-3 rounded-3">
                        <small class="text-muted fw-bold">📝 ESTADO</small>
                        <div class="fw-bold fs-5" id="mdEstado">-</div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <small class="text-muted fw-bold">💬 COMENTARIO DEL EMPLEADO</small>
                <div class="bg-light p-3 rounded-3 border-start border-4 border-primary">
                    <div id="mdComentario" class="text-dark">Sin comentarios</div>
                </div>
            </div>
            
            <div class="mb-3" id="mdRechazoSection" style="display: none;">
                <small class="text-muted fw-bold">❌ MOTIVO DEL RECHAZO</small>
                <div class="bg-light p-3 rounded-3 border-start border-4 border-danger">
                    <div id="mdMotivoRechazo" class="text-dark">-</div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer border-top pt-3 d-flex justify-content-end">
            <button type="button" class="btn btn-secondary btn-cerrar-md">Cerrar</button>
        </div>
    </div>
</div>

<!-- =====================================================
     MODAL DE RECHAZO DE MEDIO DÍA
     ===================================================== -->
<div id="modalRechazoMedioDia" class="modal-rechazo-md" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div class="modal-contenido bg-white rounded-4 shadow-lg p-4" style="max-width: 650px; width: 90%;">
        <div class="modal-header border-bottom border-3 border-danger pb-3 mb-4">
            <h2 class="modal-titulo fs-4 fw-bold text-danger d-flex align-items-center gap-2 mb-0">
                <span>⛔</span> Rechazar Solicitud de Medio Día
            </h2>
            <button type="button" class="btn-cerrar-rechazo-md" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
        </div>
        
        <form id="formRechazoMedioDia" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="hrm_aprobar_rechazar_medio_dia">
            <input type="hidden" name="accion" value="rechazar">
            <input type="hidden" id="solicitudIdRechazoMd" name="solicitud_id" value="">
            <input type="hidden" id="nonceRechazoMd" name="hrm_nonce" value="">
            
            <div class="modal-body">
                <div class="alert alert-danger border-start border-4 border-danger mb-3">
                    <span class="fw-semibold">📝</span> Por favor ingresa el motivo del rechazo. Este mensaje será enviado al empleado.
                </div>
                
                <div class="mb-3">
                    <label for="motivoRechazoMd" class="form-label fw-bold">Motivo del Rechazo</label>
                    <textarea 
                        id="motivoRechazoMd" 
                        name="motivo_rechazo" 
                        class="form-control"
                        placeholder="Ejemplo: La fecha solicitada no es posible....."
                        rows="5"
                        maxlength="1000"
                        required
                    ></textarea>
                    <div class="form-text">Mínimo 5 caracteres, máximo 1000</div>
                </div>
            </div>
            
            <div class="modal-footer border-top pt-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-secondary btn-cancelar-rechazo-md">Cancelar</button>
                <button type="submit" class="btn btn-danger btn-confirmar-rechazo-md">Rechazar Solicitud</button>
            </div>
        </form>
    </div>
</div>
