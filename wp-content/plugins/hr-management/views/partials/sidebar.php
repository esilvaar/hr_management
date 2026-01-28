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

$section = 'empleados';
if (in_array($current_page, ['hrm-vacaciones'], true)) {
    $section = 'vacaciones';
} elseif (
    in_array($current_page, [
        'hrm-mi-perfil',
        'hrm-mi-perfil-info',
        'hrm-mi-perfil-vacaciones',
        'hrm-debug-vacaciones-empleado'
    ], true)
) {
    $section = 'perfil';
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

<style>
    /* Responsive unified sidebar: center sections vertically on desktop, move certain blocks to bottom on mobile */
    .hrm-nav.d-flex {
        display: flex;
        flex-direction: column;
    }

    .hrm-nav .hrm-profile-mid,
    .hrm-nav .hrm-convivencia-mid {
        margin: 0;
    }

    @media (max-width: 767.98px) {

        .hrm-nav .hrm-profile-mid,
        .hrm-nav .hrm-convivencia-mid {
            margin-top: auto;
            margin-bottom: 0;
        }
    }

    @media (min-width: 768px) {

        .hrm-nav .hrm-profile-mid,
        .hrm-nav .hrm-convivencia-mid {
            margin: auto 0;
        }
    }
</style>

<aside class="hrm-sidebar d-flex flex-column flex-shrink-0 border-end bg-light">

    <!-- Header -->
    <div class="hrm-sidebar-header d-flex align-items-center justify-content-center p-3 border-bottom">
        <a href="<?= esc_url(admin_url('admin.php?page=hrm-empleados&tab=list')); ?>"
            class="d-flex align-items-center justify-content-center">
            <img src="<?= $logo_url; ?>" class="img-fluid" style="max-height:48px;" alt="Logo">
        </a>
    </div>

    <!-- Navegación -->
    <nav class="hrm-nav flex-grow-1 py-2 pb-5 d-flex flex-column">

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
        $profile_first = (bool) ($is_employee_role && !$is_anaconda);

        // Preparar HTML del bloque "Mi Perfil" para decidir dónde renderizarlo
        ob_start();
        if ($can_employee || $can_vacation || $can_admin_views || $can_supervisor): ?>
            <details <?= $section === 'perfil' ? 'open' : ''; ?>>
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
        if ($can_admin_views || $can_supervisor): ?>
            <details <?= $section === 'empleados' ? 'open' : ''; ?>>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-businessman"></span>
                    <span class="flex-grow-1">Gestión de Empleados</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= in_array($current_page, array('hrm-empleados'), true) ? 'active' : '' ?>"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-empleados&tab=list')); ?>">
                            Lista de empleados
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-empleados', 'profile'); ?>"
                            href="<?= esc_url($profile_url); ?>">
                            Perfil del Empleado
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-empleados', 'upload'); ?>"
                            href="<?= esc_url($upload_url); ?>">
                            Documentos del Empleado
                        </a>
                    </li>
                    <?php if ($can_admin_views):  // Solo quien pueda administrar puede crear nuevos ?>
                        <li>
                            <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-empleados', 'new'); ?>"
                                href="<?= esc_url(admin_url('admin.php?page=hrm-empleados&tab=new')); ?>">
                                Nuevo empleado
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </details>
        <?php endif; ?>

        <?php if ($can_vacation || $can_admin_views): ?>
            <!-- Vacaciones -->
            <details <?= $section === 'vacaciones' ? 'open' : ''; ?>>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <span class="flex-grow-1">Gestión de Vacaciones</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sidebar_is_active('hrm-vacaciones'); ?>"
                            href="<?= esc_url(admin_url('admin.php?page=hrm-vacaciones')); ?>">
                            Solicitudes de Vacaciones
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
        <div class="hrm-profile-mid" style="margin: auto 0; padding: .5rem 0;">
            <!-- Perfil (visible para empleados, editores de vacaciones y administradores) -->
            <?php if (!$profile_first) {
                echo $profile_html;
            } ?>

            <!-- Documentos-Reglamentos -->
            <details>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-book-alt"></span>
                    <span class="flex-grow-1">Documentos-Reglamentos</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <?php
                    // Mostrar documentos guardados en la tabla personalizada
                    global $wpdb;
                    $table = $wpdb->prefix . 'rrhh_documentos_empresa';
                    $docs = $wpdb->get_results( "SELECT id, titulo FROM {$table} ORDER BY fecha_creacion DESC" );
                    if ( ! empty( $docs ) ) :
                        foreach ( $docs as $d ) :
                            $doc_id = intval( $d->id );
                            $title = esc_html( $d->titulo ? $d->titulo : 'Documento ' . $doc_id );
                            $is_active = (string) hrm_get_query_var('doc_id') === (string) $doc_id ? ' active' : '';
                            $href = esc_url( add_query_arg( array( 'page' => 'hrm-convivencia', 'doc_id' => $doc_id ), admin_url('admin.php') ) );
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

    </nav>

    <!-- Logout -->
    <div class="p-3 border-top">
        <a class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2"
            href="<?= esc_url(wp_logout_url()); ?>">
            <span class="dashicons dashicons-migrate"></span>
            Cerrar sesión
        </a>
    </div>

</aside>