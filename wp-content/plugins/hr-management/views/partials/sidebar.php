<?php
/**
 * Sidebar unificada
 * Consolida las sidebars por rol/capabilities en un único partial.
 */
if (!defined('ABSPATH'))
    exit;

// Helpers seguros
function hrm_get_query_var($key)
{
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : '';
}

function hrm_sidebar_is_active($slug, $check_tab = null)
{
    $page = hrm_get_query_var('page');
    $tab = hrm_get_query_var('tab');

    if ($page !== $slug)
        return '';
    if ($check_tab !== null && $tab !== $check_tab)
        return '';

    return 'active';
}

// Estado actual
$current_page = hrm_get_query_var('page');
$tab = hrm_get_query_var('tab');
$doc_id = hrm_get_query_var('doc_id');

// Determinar cuál sección debe estar abierta según la página actual
$section = 'empleados';
$subsection = null; // Para diferenciar subsecciones dentro de 'perfil'

if (in_array($current_page, ['hrm-vacaciones'], true)) {
    $section = 'vacaciones';
} elseif (in_array($current_page, ['hrm-convivencia'], true) || !empty($doc_id)) {
    // Si estamos viendo documentos-reglamentos, abrimos esa sección específica
    $section = 'perfil';
    $subsection = 'documentos-reglamentos';
} elseif (
    in_array($current_page, [
        'hrm-mi-perfil',
        'hrm-mi-perfil-info',
        'hrm-mi-perfil-vacaciones',
        'hrm-mi-documentos-contratos',
        'hrm-mi-documentos-liquidaciones',
        'hrm-debug-vacaciones-empleado'
    ], true)
) {
    $section = 'perfil';
    $subsection = 'mi-perfil';
} elseif (strpos($current_page, 'hrm-mi-documentos-type-') === 0) {
    // Páginas dinámicas de tipos de documento
    $section = 'perfil';
    $subsection = 'mi-perfil';
} elseif (in_array($current_page, ['hrm-anaconda-documents'], true)) {
    $section = 'empresa';
}

$emp_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$profile_url = admin_url('admin.php?page=hrm-empleados&tab=profile');
$upload_url = admin_url('admin.php?page=hrm-empleados&tab=upload');
if ($emp_id) {
    $profile_url = add_query_arg('id', $emp_id, $profile_url);
    $upload_url = add_query_arg('id', $emp_id, $upload_url);
}

$logo_url = esc_url(plugins_url('assets/images/logo.webp', dirname(__FILE__, 2)));

?>

<!-- Sidebar styles moved to assets/css/sidebar.css -->

<!-- Mobile toggle button (visible on small screens) -->
<button class="hrm-mobile-toggle btn btn-primary d-md-none" aria-controls="hrm-sidebar" aria-expanded="false" aria-label="Abrir menú">
    <span class="dashicons dashicons-menu"></span>
</button>

<aside id="hrm-sidebar" class="hrm-sidebar d-flex flex-column flex-shrink-0 border-end bg-light" role="complementary" data-accordion-mode="<?= in_array('empleado', (array) wp_get_current_user()->roles, true) ? 'all-open' : 'single'; ?>">

    <!-- Header -->
    <div class="hrm-sidebar-header d-flex align-items-center justify-content-center p-3 border-bottom">
        <?php
        // Determinar URL del logo según el rol del usuario
        $logo_href = admin_url('admin.php?page=hrm-mi-perfil-info'); // Por defecto empleado
        
        if (current_user_can('manage_options') || current_user_can('edit_hrm_employees')) {
            // Admin o Supervisor: ir a lista de empleados
            $logo_href = admin_url('admin.php?page=hrm-empleados&tab=list');
        } elseif (in_array('editor_vacaciones', (array) wp_get_current_user()->roles, true)) {
            // Editor de vacaciones: ir a gestión de vacaciones
            $logo_href = admin_url('admin.php?page=hrm-vacaciones');
        } elseif (current_user_can('manage_hrm_vacaciones')) {
            // Gestor de vacaciones: ir a vacaciones
            $logo_href = admin_url('admin.php?page=hrm-vacaciones');
        }
        ?>
        <a href="<?= esc_url($logo_href); ?>"
            class="d-flex align-items-center justify-content-center">
            <img src="<?= $logo_url; ?>" class="img-fluid hrm-logo hrm-logo-light" alt="Logo">
            <img src="<?= esc_url(plugins_url('assets/images/logo-blanco.png', dirname(__FILE__, 2))); ?>" class="img-fluid hrm-logo hrm-logo-dark" alt="Logo">
        </a>
    </div>

    <!-- Navegación -->
    <nav class="hrm-nav myplugin-nav flex-grow-1 py-2 d-flex flex-column">

        <?php
        // --- SECCIONES DISPONIBLES SEGÚN CAPABILITIES ---
        $can_admin_views = current_user_can('manage_options') || current_user_can('manage_hrm_employees') || current_user_can('view_hrm_admin_views');
        $can_supervisor = current_user_can('edit_hrm_employees');
        $can_vacation = current_user_can('manage_hrm_vacaciones');
        $can_employee = current_user_can('view_hrm_own_profile') || is_user_logged_in();
        // Rol específico: administrador_anaconda
        $is_anaconda = in_array('administrador_anaconda', (array) wp_get_current_user()->roles, true);
        // Rol específico: empleado (para reordenar sidebar)
        $is_employee_role = in_array('empleado', (array) wp_get_current_user()->roles, true);
        // Rol específico: editor_vacaciones
        $is_editor_role = in_array('editor_vacaciones', (array) wp_get_current_user()->roles, true);
        $profile_first = (bool) ($is_employee_role && !$is_anaconda);

        // Preparar HTML del bloque "Mi Perfil" para decidir dónde renderizarlo
        ob_start();
        if ($can_employee || $can_vacation || $can_admin_views || $can_supervisor): ?>
            <details <?= ($subsection === 'mi-perfil' || $is_employee_role) ? 'open' : ''; ?>>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-admin-users"></span>
                    <span class="flex-grow-1">Mi Perfil</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= (in_array($current_page, array('hrm-mi-perfil', 'hrm-mi-perfil-info'), true) ? 'active' : '') ?>"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-mi-perfil-info')); ?>">
                            Mi información
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-mi-perfil-vacaciones'); ?>"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-mi-perfil-vacaciones')); ?>">
                            Mis vacaciones
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-mi-documentos-contratos'); ?>"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-mi-documentos-contratos')); ?>">
                            Mi Contrato
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-mi-documentos-liquidaciones'); ?>"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-mi-documentos-liquidaciones')); ?>">
                            Mis Liquidaciones
                        </a>
                    </li>

                    <?php
                    // Insertar dinámicamente los tipos de documento bajo "Mis Documentos"
                    hrm_ensure_db_classes();
                    $db_docs = new HRM_DB_Documentos();
                    $hrm_doc_types = $db_docs->get_all_types();
                    if (!empty($hrm_doc_types)):
                        // Evitar duplicados con los items estáticos (Contrato/Liquidaciones/Licencias)
                        $reserved = array_map('strtolower', array('contrato', 'contratos', 'liquidacion', 'liquidaciones', 'licencia', 'licencias'));
                        foreach ($hrm_doc_types as $t_id => $t_name):
                            // Omitir nombres reservados y el tipo 'Empresa'
                            if (in_array(strtolower(trim($t_name)), $reserved, true))
                                continue;
                            if (strtolower(trim($t_name)) === 'empresa')
                                continue;
                            ?>
                            <li>
                                <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-mi-documentos-type-' . intval($t_id)); ?>"
                                    href="<?= esc_url(admin_url('admin.php?page=hrm-mi-documentos-type-' . intval($t_id))); ?>">
                                    <?= esc_html($t_name) ?>
                                </a>
                            </li>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </ul>
            </details>
        <?php endif;
        $profile_html = ob_get_clean();

        // Si corresponde, renderizamos al inicio
        if ($profile_first) {
            echo $profile_html;
        }

        // Empleados / Gestión (visible para roles administradores y supervisores)
        // Determinar qué tab es active para marcar solo el correcto
        $is_list_active = $current_page === 'hrm-empleados' && (!$tab || $tab === 'list') ? 'active' : '';
        $is_profile_active = $current_page === 'hrm-empleados' && $tab === 'profile' ? 'active' : '';
        $is_upload_active = $current_page === 'hrm-empleados' && $tab === 'upload' ? 'active' : '';
        $is_new_active = $current_page === 'hrm-empleados' && $tab === 'new' ? 'active' : '';
        ?>

        <?php if ($can_admin_views || $can_supervisor || $is_editor_role): ?>
            <details <?= $section === 'empleados' ? 'open' : ''; ?>>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-businessman"></span>
                    <span class="flex-grow-1">Gestión de Empleados</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <?php if (!$is_editor_role): ?>
                        <li>
                            <a class="nav-link px-3 py-2 <?= $is_list_active ?>"
                                href="<?= esc_url(admin_url('admin.php?page=hrm-empleados&tab=list')); ?>">
                                Lista de empleados
                            </a>
                        </li>
                        <li>
                            <a class="nav-link px-3 py-2 <?= $is_profile_active ?>"
                                href="<?= esc_url($profile_url); ?>">
                                Perfil del Empleado
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="nav-link px-3 py-2 <?= $is_upload_active ?>"
                            href="<?= esc_url($upload_url); ?>">
                            Documentos del Empleado
                        </a>
                    </li>
                    <?php if ($can_admin_views):  // Solo quien pueda administrar puede crear nuevos ?>
                        <li>
                            <a class="nav-link px-3 py-2 <?= $is_new_active ?>"
                                href="<?= esc_url(admin_url('admin.php?page=hrm-empleados&tab=new')); ?>">
                                Nuevo empleado
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </details>
        <?php endif; ?>

        <?php 
        // Vacaciones: determinar si está activa
        $is_vacaciones_active = $current_page === 'hrm-vacaciones' ? 'active' : '';
        ?>

        <?php if ($can_vacation || $can_admin_views || $is_editor_role): ?>
            <!-- Vacaciones -->
            <?php 
            $count_pendientes = function_exists('hrm_contar_solicitudes_pendientes') ? hrm_contar_solicitudes_pendientes() : 0;
            $mostrar_dot = function_exists('hrm_mostrar_dot_notificacion') ? hrm_mostrar_dot_notificacion() : false;
            ?>
            <details <?= $section === 'vacaciones' ? 'open' : ''; ?> 
                     id="hrmVacacionesDetails"
                     data-hrm-section="vacaciones">
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold position-relative">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <span class="flex-grow-1">Gestión de Vacaciones</span>
                    <?php if ( $mostrar_dot ): ?>
                        <span class="hrm-notification-dot" 
                              id="hrmNotificationDot"
                              aria-label="Hay solicitudes pendientes"
                              title="Nuevas solicitudes pendientes"></span>
                    <?php endif; ?>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= $is_vacaciones_active ?> d-flex align-items-center justify-content-between"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-vacaciones')); ?>">
                            <span>Solicitudes de Vacaciones</span>
                            <?php if ( $count_pendientes > 0 ): ?>
                                <span class="hrm-notification-badge badge bg-danger rounded-pill">
                                    <?= esc_html( $count_pendientes ); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </details>
        <?php endif; ?>

        <?php if ($is_anaconda || $can_admin_views): ?>
            <!-- Empresa -->
            <details <?= $section === 'empresa' ? 'open' : ''; ?>>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-media-document"></span>
                    <span class="flex-grow-1">Documentos empresa</span>
                </summary>
                <ul class="list-unstyled px-2 mb-0">
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-anaconda-documents'); ?>"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-anaconda-documents')); ?>">
                            Crear / Gestionar Documentos empresa
                        </a>
                    </li>
                </ul>
            </details>
        <?php endif; ?>

        <!-- Centramos la sección 'Mi Perfil' verticalmente usando flexbox (no absoluto) -->
        <div class="hrm-profile-mid">
            <!-- Perfil (visible para empleados, editores de vacaciones y administradores) -->
            <?php if (!$profile_first) {
                echo $profile_html;
            } ?>

            <!-- Documentos-Reglamentos -->
            <details <?= ($subsection === 'documentos-reglamentos' || $is_employee_role) ? 'open' : ''; ?>>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-book-alt"></span>
                    <span class="flex-grow-1">Documentos-Reglamentos</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <?php
                    // Mostrar documentos guardados en la tabla personalizada (listar todos y cachear resultados)
                    global $wpdb;
                    $table = $wpdb->prefix . 'rrhh_documentos_empresa';
                    $cache_key = 'hrm_sidebar_docs_' . md5( $table );
                    $docs = get_transient( $cache_key );
                    if ( false === $docs ) {
                        // Obtener todos los documentos (ordenados por fecha desc)
                        $sql = "SELECT id, titulo FROM {$table} ORDER BY fecha_creacion DESC";
                        $docs = $wpdb->get_results( $sql );
                        $timeout = defined( 'HRM_CACHE_TIMEOUT' ) ? HRM_CACHE_TIMEOUT : HOUR_IN_SECONDS;
                        set_transient( $cache_key, $docs, $timeout );
                    }
                    if ( ! empty( $docs ) ) :
                        foreach ( $docs as $d ) :
                            $doc_id = intval( $d->id );
                            $title = esc_html( $d->titulo ? $d->titulo : 'Documento ' . $doc_id );
                            $is_active = (string) hrm_get_query_var( 'doc_id' ) === (string) $doc_id ? ' active' : '';
                            $href = esc_url( add_query_arg( array( 'page' => 'hrm-convivencia', 'doc_id' => $doc_id ), admin_url( 'admin.php' ) ) );
                            ?>
                            <li>
                                <a class="nav-link px-3 py-2<?= $is_active ?>" href="<?= $href ?>"><?= $title ?></a>
                            </li>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </ul>
            </details>
        </div>

        <!-- Ajustes -->
        <!-- <details class="myplugin-settings">
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="dashicons dashicons-admin-generic"></span>
                <span class="flex-grow-1">Ajustes</span>
            </summary> -->
            <div class="px-3 py-2">
                <div class="myplugin-settings-panel" id="myplugin-dark-toggle-slot"></div>
            </div>
        <!-- </details> -->

    </nav>

<!-- Overlay para cerrar al tocar fuera (móvil) -->
<div class="hrm-sidebar-overlay" aria-hidden="true"></div>

<?php // JS moved to assets/js/sidebar.js - enqueued globally in includes/functions.php or per-view as needed ?>

    <!-- Logout -->
    <div class="p-3 border-top">
        <a class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2"
            href="<?= esc_url(wp_logout_url()); ?>">
            <span class="dashicons dashicons-migrate"></span>
            Cerrar sesión
        </a>
    </div>

</aside>
