<?php
// Los estilos se cargan en functions.php mediante hooks de WordPress
// No encolar estilos aquí, ya que es demasiado tarde en el ciclo

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

$solicitudes = hrm_get_all_vacaciones( $search_term, $estado_filtro );

$total_solicitudes = count( $solicitudes );

// Contadores visibles (respetan permisos por rol y departamento)
$count_dia_completo = function_exists( 'hrm_count_vacaciones_visibles' ) ? hrm_count_vacaciones_visibles() : 0;
$count_medio_dia = function_exists( 'hrm_count_medio_dia_visibles' ) ? hrm_count_medio_dia_visibles() : 0;
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
                        <span style="font-size: 1.2rem;">📋</span>
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
                        <span style="font-size: 1.2rem;">🏢</span>
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
                        <span style="font-size: 1.2rem;">⏰</span>
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
                        <th class="py-3 px-4 text-center" style="min-width: 220px;"> Acciones</th>
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
                                    $medio_dias = hrm_get_solicitudes_medio_dia( 
                                        $_GET['empleado_md'] ?? '', 
                                        $_GET['estado_md'] ?? 'PENDIENTE'
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
                    </div>
                </div>

            </div> <!-- Cierre del tab-pane contenido-medio-dia -->

            </div> <!-- Cierre del tab-content -->

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // =====================================================
    // LÓGICA DE RECHAZO DE SOLICITUDES (Antes de cualquier otra lógica)
    // =====================================================
    setTimeout(function() {
        const botonesRechazarSolicitud = document.querySelectorAll('.btn-rechazar-solicitud');
        const botonesRechazarMedioDia = document.querySelectorAll('.btn-rechazar-medio-dia');
        
        // Manejador para botones de rechazo de solicitudes completas
        botonesRechazarSolicitud.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const motivo = prompt('Por favor ingresa el motivo del rechazo:');
                
                if (motivo === null) {
                    return;
                }
                
                if (motivo.trim() === '') {
                    alert('El motivo del rechazo es obligatorio.');
                    return;
                }
                
                if (motivo.trim().length < 5) {
                    alert('El motivo debe tener al menos 5 caracteres.');
                    return;
                }
                
                const form = this.closest('form');
                form.querySelector('input[name="motivo_rechazo"]').value = motivo;
                form.submit();
            });
        });
        
        // Manejador para botones de rechazo de medio día
        botonesRechazarMedioDia.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const motivo = prompt('Por favor ingresa el motivo del rechazo:');
                
                if (motivo === null) {
                    return;
                }
                
                if (motivo.trim() === '') {
                    alert('El motivo del rechazo es obligatorio.');
                    return;
                }
                
                if (motivo.trim().length < 5) {
                    alert('El motivo debe tener al menos 5 caracteres.');
                    return;
                }
                
                const form = this.closest('form');
                form.querySelector('input[name="motivo_rechazo"]').value = motivo;
                form.submit();
            });
        });
    }, 100);

    // =====================================================
    // SINCRONIZACIÓN AUTOMÁTICA DE PERSONAL VIGENTE
    // =====================================================
    let ultimaSincronizacion = 0; // Timestamp de la última sincronización
    const INTERVALO_MINIMO = 5000; // 5 segundos mínimo entre sincronizaciones
    
    function sincronizarPersonalAutomatico() {
        const ahora = Date.now();
        
        // Evitar sincronizaciones muy frecuentes
        if (ahora - ultimaSincronizacion < INTERVALO_MINIMO) {
            console.log('Sincronización ignorada (demasiado frecuente)');
            return;
        }
        
        ultimaSincronizacion = ahora;
        
        // Obtener el nonce del botón
        const btnSincronizar = document.getElementById('btnSincronizarPersonal');
        if (!btnSincronizar) return;
        
        let nonce = btnSincronizar.getAttribute('data-nonce');
        if (!nonce) {
            nonce = '<?php echo esc_js( wp_create_nonce('hrm_sincronizar_personal') ); ?>';
        }
        
        console.log('Sincronización automática de personal vigente...');
        
        // Realizar la solicitud AJAX silenciosamente
        fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'hrm_sincronizar_personal_vigente',
                nonce: nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Sincronización automática completada:', data);
            if (!data.success) {
                console.warn('Error en sincronización automática:', data);
            }
        })
        .catch(error => {
            console.error('Error en sincronización automática:', error);
        });
    }
    
    // Sincronizar automáticamente al cargar la página
    sincronizarPersonalAutomatico();
    
    // Sincronizar automáticamente cada 5 minutos (300000 ms)
    setInterval(sincronizarPersonalAutomatico, 300000);

    // =====================================================
    // EVENT LISTENER PARA BOTÓN DE SINCRONIZACIÓN MANUAL
    // =====================================================
    const btnSincronizar = document.getElementById('btnSincronizarPersonal');
    if (btnSincronizar) {
        btnSincronizar.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Cambiar estado del botón
            const textoOriginal = btnSincronizar.innerHTML;
            btnSincronizar.disabled = true;
            btnSincronizar.innerHTML = '<span>⏳</span> <span>Sincronizando...</span>';
            
            let nonce = btnSincronizar.getAttribute('data-nonce');
            if (!nonce) {
                nonce = '<?php echo esc_js( wp_create_nonce('hrm_sincronizar_personal') ); ?>';
            }
            
            console.log('Sincronización manual iniciada...');
            
            // Realizar la solicitud AJAX
            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'hrm_sincronizar_personal_vigente',
                    nonce: nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Sincronización completada:', data);
                
                // Mostrar mensaje de éxito
                if (data.success) {
                    btnSincronizar.classList.add('btn-success');
                    btnSincronizar.classList.remove('btn-primary');
                    btnSincronizar.innerHTML = '<span>✅</span> <span>¡Sincronizado!</span>';
                    
                    // Mostrar notificación con detalles
                    if (data.data && data.data.detalles) {
                        let detalles = data.data.detalles.map(d => ({
                            nombre: d.nombre,
                            total_empleados: d.total_empleados_activos || d.total_empleados,
                            personal_vigente: d.personal_vigente
                        }));
                        mostrarNotificacionExito(
                            '✅ Sincronización Exitosa',
                            'Personal vigente actualizado correctamente',
                            detalles
                        );
                    }
                    
                    // Esperar 2 segundos y volver al estado normal
                    setTimeout(() => {
                        btnSincronizar.classList.remove('btn-success');
                        btnSincronizar.classList.add('btn-primary');
                        btnSincronizar.innerHTML = textoOriginal;
                        btnSincronizar.disabled = false;
                    }, 2000);
                } else {
                    btnSincronizar.classList.add('btn-danger');
                    btnSincronizar.classList.remove('btn-primary');
                    btnSincronizar.innerHTML = '<span>❌</span> <span>Error en sincronización</span>';
                    
                    // Mostrar notificación de error
                    if (data.data && data.data.errores) {
                        mostrarNotificacionError(
                            '❌ Error en Sincronización',
                            data.data.mensaje || 'No se pudo sincronizar el personal',
                            data.data.errores
                        );
                    }
                    
                    // Volver al estado normal después de 3 segundos
                    setTimeout(() => {
                        btnSincronizar.classList.remove('btn-danger');
                        btnSincronizar.classList.add('btn-primary');
                        btnSincronizar.innerHTML = textoOriginal;
                        btnSincronizar.disabled = false;
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error en sincronización:', error);
                
                btnSincronizar.classList.add('btn-danger');
                btnSincronizar.classList.remove('btn-primary');
                btnSincronizar.innerHTML = '<span>❌</span> <span>Error en solicitud</span>';
                
                mostrarNotificacionError(
                    '❌ Error de Conexión',
                    'No se pudo conectar con el servidor',
                    [error.message]
                );
                
                // Volver al estado normal después de 3 segundos
                setTimeout(() => {
                    btnSincronizar.classList.remove('btn-danger');
                    btnSincronizar.classList.add('btn-primary');
                    btnSincronizar.innerHTML = textoOriginal;
                    btnSincronizar.disabled = false;
                }, 3000);
            });
        });
    }

    // =====================================================
    // LÓGICA DE NAVEGACIÓN DE TABS
    // =====================================================
    const tabButtons = document.querySelectorAll('[role="tab"]');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('aria-controls');
            const tabName = this.id.replace('tab-', '');
            console.log('Click en tab:', this.id, '-> mostrando:', targetId);
            
            // Remover active de todos los buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
            });
            
            // Ocultar todos los panes
            document.querySelectorAll('[role="tabpanel"]').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            // Activar el botón actual
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            
            // Mostrar el pane correspondiente
            const targetPane = document.getElementById(targetId);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
                console.log('Tab mostrado:', targetId);
                
                // Actualizar URL sin recargar la página
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url);
            } else {
                console.error('Pane no encontrado:', targetId);
            }
        });
    });

    // =====================================================
    // LÓGICA DEL CALENDARIO
    // =====================================================
    let mesActual = new Date().getMonth();
    let anoActual = new Date().getFullYear();
    let feriados = {}; // Objeto para almacenar feriados
    let vacacionesAprobadas = []; // Variable para almacenar vacaciones dinámicamente
    let departamentoFiltro = ''; // Variable para almacenar el departamento seleccionado

    // Función para cargar vacaciones del departamento seleccionado
    function cargarVacacionesPorDepartamento(departamento) {
        return fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'hrm_get_vacaciones_calendario',
                departamento: departamento
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                vacacionesAprobadas = data.data;
                return true;
            }
            return false;
        })
        .catch(error => {
            console.error('Error cargando vacaciones:', error);
            return false;
        });
    }

    // Event listener para el selector de departamento
    const selectorDepartamento = document.getElementById('filtroCalendarioDepartamento');
    if (selectorDepartamento) {
        selectorDepartamento.addEventListener('change', function() {
            departamentoFiltro = this.value;
            cargarVacacionesPorDepartamento(departamentoFiltro).then(() => {
                renderizarCalendario(mesActual, anoActual);
            });
        });
    }

    // Función para cargar feriados del año especificado
    function cargarFeriadosDelAno(ano) {
        return fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'hrm_get_feriados',
                ano: ano
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                feriados = data.data;
                return true;
            }
            return false;
        })
        .catch(error => {
            console.error('Error cargando feriados:', error);
            return false;
        });
    }

    // Cargar feriados iniciales y vacaciones del departamento seleccionado
    cargarFeriadosDelAno(anoActual).then(() => {
        cargarVacacionesPorDepartamento(departamentoFiltro);
    });

    function renderizarCalendario(mes, ano) {
        const primerDia = new Date(ano, mes, 1);
        const ultimoDia = new Date(ano, mes + 1, 0);
        const diasEnMes = ultimoDia.getDate();
        
        // getDay() retorna: 0=domingo, 1=lunes, ..., 6=sábado
        // Pero nuestra tabla empieza en lunes (posición 0)
        // Convertir: domingo (0) -> 6, lunes (1) -> 0, martes (2) -> 1, etc.
        let diaInicio = primerDia.getDay();
        diaInicio = diaInicio === 0 ? 6 : diaInicio - 1;
        
        // Nombres de meses en español
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        document.getElementById('mesesTitulo').textContent = meses[mes] + ' ' + ano;
        
        let html = '';
        let diaActual = 1;
        
        // Calcular número de semanas
        const totalCeldas = Math.ceil((diasEnMes + diaInicio) / 7) * 7;
        
        for (let i = 0; i < totalCeldas / 7; i++) {
            html += '<tr>';
            
            for (let j = 0; j < 7; j++) {
                const indice = i * 7 + j;
                
                if (indice < diaInicio || diaActual > diasEnMes) {
                    html += '<td class="other-month"></td>';
                } else {
                    const fecha = new Date(ano, mes, diaActual);
                    const fechaStr = ano + '-' + String(mes + 1).padStart(2, '0') + '-' + String(diaActual).padStart(2, '0');
                    
                    let clasesCelda = '';
                    let contenido = '<span class="dia-numero">' + diaActual + '</span>';
                    
                    // Verificar si es un feriado
                    let esFeriado = false;
                    let nombreFeriado = '';
                    
                    if (fechaStr in feriados) {
                        esFeriado = true;
                        nombreFeriado = feriados[fechaStr];
                        clasesCelda += ' feriado';
                        contenido += '<div class="dia-info">🎉 Feriado</div>';
                    }
                    
                    // Verificar si es fin de semana (sábado=6, domingo=0)
                    if (j === 5 || j === 6) {
                        clasesCelda += ' fin-semana';
                    }
                    
                    // Verificar si hay vacaciones en este día (solo si no es feriado)
                    let tieneVacaciones = false;
                    let empleadosVacaciones = [];
                    
                    if (!esFeriado) {
                        for (let vac of vacacionesAprobadas) {
                            if (fechaStr >= vac.fecha_inicio && fechaStr <= vac.fecha_fin) {
                                tieneVacaciones = true;
                                empleadosVacaciones.push(vac.empleado);
                            }
                        }
                        
                        if (tieneVacaciones) {
                            clasesCelda += ' vacaciones';
                            contenido += '<div class="dia-info">🏖️ ' + empleadosVacaciones.length + ' empleado(s)</div>';
                        }
                    }
                    
                    // Verificar si es hoy
                    const hoy = new Date();
                    if (fecha.toDateString() === hoy.toDateString()) {
                        clasesCelda += ' hoy';
                    }
                    
                    // Agregar título (tooltip) con nombre del feriado
                    const titulo = esFeriado ? ` title="${nombreFeriado}"` : '';
                    
                    html += '<td class="' + clasesCelda + '"' + titulo + '>' + contenido + '</td>';
                    diaActual++;
                }
            }
            
            html += '</tr>';
        }
        
        document.getElementById('diasCalendario').innerHTML = html;
    }
    
    // Botones de navegación
    document.getElementById('btnMesAnterior').addEventListener('click', function() {
        const anoAnterior = anoActual;
        
        mesActual--;
        if (mesActual < 0) {
            mesActual = 11;
            anoActual--;
        }
        
        // Si cambió el año, cargar feriados del nuevo año
        if (anoActual !== anoAnterior) {
            cargarFeriadosDelAno(anoActual).then(() => {
                renderizarCalendario(mesActual, anoActual);
            });
        } else {
            renderizarCalendario(mesActual, anoActual);
        }
    });
    
    document.getElementById('btnMesSiguiente').addEventListener('click', function() {
        const anoAnterior = anoActual;
        
        mesActual++;
        if (mesActual > 11) {
            mesActual = 0;
            anoActual++;
        }
        
        // Si cambió el año, cargar feriados del nuevo año
        if (anoActual !== anoAnterior) {
            cargarFeriadosDelAno(anoActual).then(() => {
                renderizarCalendario(mesActual, anoActual);
            });
        } else {
            renderizarCalendario(mesActual, anoActual);
        }
    });

    // =====================================================
    // LÓGICA DE NOTIFICACIONES
    // =====================================================
    
    // Función para mostrar notificación de éxito
    function mostrarNotificacionExito(titulo, mensaje, detalles) {
        const notif = document.createElement('div');
        notif.className = 'alert alert-success alert-dismissible fade show shadow-lg';
        notif.setAttribute('role', 'alert');
        notif.style.position = 'fixed';
        notif.style.top = '20px';
        notif.style.right = '20px';
        notif.style.zIndex = '9999';
        notif.style.minWidth = '400px';
        
        let detalleHtml = '';
        if (detalles && detalles.length > 0) {
            detalleHtml = '<ul class="mb-0 mt-2 small">';
            detalles.forEach(detalle => {
                detalleHtml += `<li>${detalle.nombre}: ${detalle.total_empleados} total, ${detalle.personal_vigente} vigente</li>`;
            });
            detalleHtml += '</ul>';
        }
        
        notif.innerHTML = `
            <div class="d-flex gap-2 align-items-start">
                <span style="font-size: 1.5rem;">✅</span>
                <div class="flex-grow-1">
                    <strong>${titulo}</strong>
                    <p class="mb-0">${mensaje}</p>
                    ${detalleHtml}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notif);
        
        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            notif.remove();
        }, 5000);
    }
    
    // Función para mostrar notificación de error
    function mostrarNotificacionError(titulo, mensaje, errores) {
        const notif = document.createElement('div');
        notif.className = 'alert alert-danger alert-dismissible fade show shadow-lg';
        notif.setAttribute('role', 'alert');
        notif.style.position = 'fixed';
        notif.style.top = '20px';
        notif.style.right = '20px';
        notif.style.zIndex = '9999';
        notif.style.minWidth = '400px';
        
        let erroresHtml = '';
        if (errores && errores.length > 0) {
            erroresHtml = '<ul class="mb-0 mt-2 small">';
            errores.forEach(error => {
                erroresHtml += `<li>${error}</li>`;
            });
            erroresHtml += '</ul>';
        }
        
        notif.innerHTML = `
            <div class="d-flex gap-2 align-items-start">
                <span style="font-size: 1.5rem;">❌</span>
                <div class="flex-grow-1">
                    <strong>${titulo}</strong>
                    <p class="mb-0">${mensaje}</p>
                    ${erroresHtml}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(notif);
        
        // Auto-cerrar después de 6 segundos
        setTimeout(() => {
            notif.remove();
        }, 6000);
    }

    // =====================================================
    // LÓGICA DEL MODAL DE COMENTARIOS
    // =====================================================
    
    // Datos de las solicitudes con comentarios
    const solicitudesData = {
        <?php 
        $primera = true;
        foreach ( $solicitudes as $s ) {
            if ( ! $primera ) echo ",";
            echo "'" . esc_js( $s['id_solicitud'] ) . "': {";
            echo "nombre: '" . esc_js( $s['nombre'] . ' ' . $s['apellido'] ) . "',";
            echo "comentario: '" . esc_js( $s['comentario_empleado'] ?? '' ) . "'";
            echo "}";
            $primera = false;
        }
        ?>
    };
    
    const botonesComentarios = document.querySelectorAll('.btn-comentarios');
    const modal = document.getElementById('modalComentarios');
    const btnCerrar = document.querySelector('.modal-cerrar');
    const btnCerrarModal = document.querySelector('.btn-cerrar-modal');
    
    // Abrir modal
    botonesComentarios.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const datos = solicitudesData[id];
            
            if (datos) {
                document.getElementById('modalEmpleado').textContent = datos.nombre;
                const contenidoComentario = document.getElementById('modalComentarioContenido');
                
                if (datos.comentario.trim() === '') {
                    contenidoComentario.innerHTML = '<div class="text-muted fst-italic text-center py-4">No hay comentarios agregados</div>';
                } else {
                    contenidoComentario.textContent = datos.comentario;
                }
                
                modal.classList.add('activo');
            }
        });
    });
    
    // Cerrar modal - botón X
    if (btnCerrar) {
        btnCerrar.addEventListener('click', function() {
            modal.classList.remove('activo');
        });
    }
    
    // Cerrar modal - botón Cerrar
    if (btnCerrarModal) {
        btnCerrarModal.addEventListener('click', function() {
            modal.classList.remove('activo');
        });
    }
    
    // Cerrar modal - click fuera del modal
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('activo');
            }
        });
    }
    
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('activo')) {
            modal.classList.remove('activo');
        }
    });
    
    // =====================================================
    // LÓGICA DEL MODAL DE RECHAZO CON MOTIVO
    // =====================================================
    
    const modalRechazo = document.getElementById('modalRechazo');
    const botonesModalRechazo = document.querySelectorAll('.btn-modal-rechazo');
    const btnCerrarRechazo = document.querySelector('.modal-rechazo-cerrar');
    const btnCancelarRechazo = document.querySelector('.btn-cancelar-rechazo');
    const btnConfirmarRechazo = document.querySelector('.btn-confirmar-rechazo');
    const formRechazo = document.getElementById('formRechazo');
    
    // Abrir modal de rechazo
    botonesModalRechazo.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const solicitudId = this.getAttribute('data-solicitud-id');
            const nonce = this.getAttribute('data-nonce');
            
            // Establecer valores en el formulario
            document.getElementById('solicitudIdRechazo').value = solicitudId;
            document.getElementById('nonceRechazo').value = nonce;
            document.getElementById('motivoRechazo').value = ''; // Limpiar campo
            
            // Mostrar modal
            modalRechazo.classList.add('activo');
        });
    });
    
    // Cerrar modal - botón X
    if (btnCerrarRechazo) {
        btnCerrarRechazo.addEventListener('click', function() {
            modalRechazo.classList.remove('activo');
        });
    }
    
    // Cerrar modal - botón Cancelar
    if (btnCancelarRechazo) {
        btnCancelarRechazo.addEventListener('click', function() {
            modalRechazo.classList.remove('activo');
        });
    }
    
    // Cerrar modal - click fuera del modal
    if (modalRechazo) {
        modalRechazo.addEventListener('click', function(e) {
            if (e.target === modalRechazo) {
                modalRechazo.classList.remove('activo');
            }
        });
    }
    
    // Confirmar rechazo - enviar formulario
    if (btnConfirmarRechazo) {
        btnConfirmarRechazo.addEventListener('click', function() {
            const motivo = document.getElementById('motivoRechazo').value.trim();
            
            // Validación simple
            if (motivo === '') {
                alert('Por favor ingresa un motivo para el rechazo.');
                return;
            }
            
            if (motivo.length < 5) {
                alert('El motivo debe tener al menos 5 caracteres.');
                return;
            }
            
            // Enviar el formulario
            formRechazo.submit();
        });
    }
    
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modalRechazo && modalRechazo.classList.contains('activo')) {
            modalRechazo.classList.remove('activo');
        }
    });
    
    // =====================================================
    // LÓGICA DEL MODAL DE EMPLEADOS EN VACACIONES
    // =====================================================
    const modalVacacionesDetalle = document.getElementById('modalVacacionesDetalle');
    const botonesVacacionesDetalle = document.querySelectorAll('.btn-vacaciones-detalle');
    const btnCerrarVacaciones = document.querySelectorAll('.modal-vacaciones-cerrar');
    
    // Abrir modal de detalles de vacaciones
    botonesVacacionesDetalle.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const departamento = this.getAttribute('data-departamento');
            
            // Cambiar contenido a "Cargando..."
            document.getElementById('modalVacacionesContenido').innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm me-2"></span> Cargando información...</div>';
            
            // Mostrar modal
            modalVacacionesDetalle.style.display = 'flex';
            
            // Cargar datos
            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'hrm_get_empleados_vacaciones_hoy',
                    departamento: departamento
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let html = '<div class="list-group">';
                    
                    data.data.forEach(function(empleado) {
                        // Parsear fecha manualmente para evitar problemas de zona horaria
                        const [year, month, day] = empleado.fecha_inicio.split('-');
                        const fechaInicio = new Date(year, month - 1, day);
                        
                        const [yearFin, monthFin, dayFin] = empleado.fecha_fin.split('-');
                        const fechaFin = new Date(yearFin, monthFin - 1, dayFin);
                        
                        const opciones = { year: 'numeric', month: 'long', day: 'numeric' };
                        const fechaInicioFormato = fechaInicio.toLocaleDateString('es-ES', opciones);
                        const fechaFinFormato = fechaFin.toLocaleDateString('es-ES', opciones);
                        
                        html += '<div class="list-group-item px-3 py-3 border-bottom">';
                        html += '<h6 class="fw-bold text-dark mb-1">' + empleado.nombre + '</h6>';
                        html += '<small class="text-muted d-block">📅 ' + fechaInicioFormato + ' hasta ' + fechaFinFormato + '</small>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    document.getElementById('modalVacacionesContenido').innerHTML = html;
                } else {
                    document.getElementById('modalVacacionesContenido').innerHTML = '<div class="alert alert-info">No hay empleados en vacaciones hoy</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('modalVacacionesContenido').innerHTML = '<div class="alert alert-danger">Error al cargar la información</div>';
            });
        });
    });
    
    // Cerrar modal - botones cerrar
    btnCerrarVacaciones.forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalVacacionesDetalle.style.display = 'none';
        });
    });
    
    // Cerrar modal - click fuera del modal
    if (modalVacacionesDetalle) {
        modalVacacionesDetalle.addEventListener('click', function(e) {
            if (e.target === modalVacacionesDetalle) {
                modalVacacionesDetalle.style.display = 'none';
            }
        });
    }
    
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modalVacacionesDetalle && modalVacacionesDetalle.style.display === 'flex') {
            modalVacacionesDetalle.style.display = 'none';
        }
    });

    // =====================================================
    // LÓGICA DE APROBACIÓN/RECHAZO DE MEDIO DÍA
    // =====================================================
    const modalVerMedioDia = document.getElementById('modalVerMedioDia');
    const modalRechazoMedioDia = document.getElementById('modalRechazoMedioDia');
    const botonesVerMedioDia = document.querySelectorAll('.btn-ver-medio-dia');
    const botonesRechazarMd = document.querySelectorAll('.btn-modal-rechazo-md');
    const btnCerrarMd = document.querySelectorAll('.btn-cerrar-md');
    const btnCerrarRechazoMd = document.querySelectorAll('.btn-cerrar-rechazo-md');
    const btnCancelarRechazoMd = document.querySelectorAll('.btn-cancelar-rechazo-md');
    const btnConfirmarRechazoMd = document.querySelector('.btn-confirmar-rechazo-md');

    // Ver detalles de solicitud de medio día
    botonesVerMedioDia.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const solicitudId = this.getAttribute('data-solicitud-id');
            
            // Hacer solicitud AJAX para obtener los detalles
            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'hrm_get_detalles_medio_dia',
                    solicitud_id: solicitudId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const solicitud = data.data;
                    
                    // Llenar los campos del modal
                    document.getElementById('mdEmpleado').textContent = solicitud.nombre + ' ' + solicitud.apellido;
                    document.getElementById('mdFecha').textContent = new Date(solicitud.fecha_inicio).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
                    
                    const periodo = solicitud.periodo_ausencia.charAt(0).toUpperCase() + solicitud.periodo_ausencia.slice(1);
                    const horario = periodo === 'Mañana' ? '🌅 08:00 - 12:00' : '🌆 14:00 - 18:00';
                    document.getElementById('mdPeriodo').textContent = horario;
                    
                    const badgeEstado = document.getElementById('mdEstado');
                    badgeEstado.className = '';
                    if (solicitud.estado === 'PENDIENTE') {
                        badgeEstado.className = 'badge bg-warning text-dark';
                    } else if (solicitud.estado === 'APROBADA') {
                        badgeEstado.className = 'badge bg-success';
                    } else if (solicitud.estado === 'RECHAZADA') {
                        badgeEstado.className = 'badge bg-danger';
                    }
                    badgeEstado.textContent = solicitud.estado;
                    
                    document.getElementById('mdComentario').textContent = solicitud.comentario_empleado || 'Sin comentarios';
                    
                    // Mostrar motivo de rechazo si está rechazada
                    const rechazoSection = document.getElementById('mdRechazoSection');
                    if (solicitud.estado === 'RECHAZADA' && solicitud.motivo_rechazo) {
                        rechazoSection.style.display = 'block';
                        document.getElementById('mdMotivoRechazo').textContent = solicitud.motivo_rechazo;
                    } else {
                        rechazoSection.style.display = 'none';
                    }
                    
                    // Mostrar modal
                    modalVerMedioDia.style.display = 'flex';
                } else {
                    alert('Error al cargar los detalles: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar los detalles');
            });
        });
    });

    // Cerrar modal Ver Detalles
    btnCerrarMd.forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalVerMedioDia.style.display = 'none';
        });
    });

    // Cerrar modal con click fuera
    if (modalVerMedioDia) {
        modalVerMedioDia.addEventListener('click', function(e) {
            if (e.target === modalVerMedioDia) {
                modalVerMedioDia.style.display = 'none';
            }
        });
    }

    // Abrir modal de rechazo
    botonesRechazarMd.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const solicitudId = this.getAttribute('data-solicitud-id');
            const nonce = this.getAttribute('data-nonce');
            
            document.getElementById('solicitudIdRechazoMd').value = solicitudId;
            document.getElementById('nonceRechazoMd').value = nonce;
            document.getElementById('motivoRechazoMd').value = '';
            
            modalRechazoMedioDia.style.display = 'flex';
        });
    });

    // Cerrar modal de rechazo
    btnCerrarRechazoMd.forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalRechazoMedioDia.style.display = 'none';
        });
    });

    btnCancelarRechazoMd.forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalRechazoMedioDia.style.display = 'none';
        });
    });

    // Cerrar modal con click fuera
    if (modalRechazoMedioDia) {
        modalRechazoMedioDia.addEventListener('click', function(e) {
            if (e.target === modalRechazoMedioDia) {
                modalRechazoMedioDia.style.display = 'none';
            }
        });
    }

    // Validar y enviar rechazo
    if (btnConfirmarRechazoMd) {
        btnConfirmarRechazoMd.addEventListener('click', function(e) {
            e.preventDefault();
            const motivo = document.getElementById('motivoRechazoMd').value.trim();
            
            if (motivo === '') {
                alert('Por favor ingresa un motivo para el rechazo.');
                return;
            }
            
            if (motivo.length < 5) {
                alert('El motivo debe tener al menos 5 caracteres.');
                return;
            }
            
            document.getElementById('formRechazoMedioDia').submit();
        });
    }
});
</script>

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
