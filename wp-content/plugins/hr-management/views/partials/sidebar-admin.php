<?php
/**
 * Sidebar para ADMINISTRADORES
 * Capabilities: manage_options
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helpers seguros
 */
function hrm_get_query_var( $key ) {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : '';
}

function hrm_is_active_sidebar( $slug, $check_tab = null ) {
    $page = hrm_get_query_var('page');
    $tab  = hrm_get_query_var('tab');

    if ( $page !== $slug ) return '';
    if ( $check_tab !== null && $tab !== $check_tab ) return '';

    return 'active';
}

/**
 * Estado actual
 */
$current_page = hrm_get_query_var('page');

$section = 'empleados';
if ( in_array( $current_page, ['hrm-vacaciones'], true ) ) {
    $section = 'vacaciones';
} elseif ( in_array( $current_page, [
    'hrm-mi-perfil',
    'hrm-mi-perfil-info',
    'hrm-debug-vacaciones-empleado'
], true ) ) {
    $section = 'perfil';
}

$emp_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$profile_url = admin_url('admin.php?page=hrm-empleados&tab=profile');
$upload_url  = admin_url('admin.php?page=hrm-empleados&tab=upload');

if ( $emp_id ) {
    $profile_url = add_query_arg( 'id', $emp_id, $profile_url );
    $upload_url  = add_query_arg( 'id', $emp_id, $upload_url );
}

$logo_url = esc_url(
    plugins_url( 'assets/images/logo.webp', dirname( __FILE__, 2 ) )
);
?>

<aside class="hrm-sidebar d-flex flex-column flex-shrink-0 border-end bg-light">

    <!-- Header -->
    <div class="hrm-sidebar-header d-flex align-items-center justify-content-center p-3 border-bottom">
        <a href="<?= esc_url( admin_url('admin.php?page=hrm-empleados&tab=list') ); ?>" class="d-flex align-items-center justify-content-center">
            <img src="<?= $logo_url; ?>" class="img-fluid" style="max-height:48px;" alt="Logo">
        </a>
    </div>

    <!-- Navegación -->
    <nav class="hrm-nav flex-grow-1 py-2">

        <!-- Gestión de Empleados -->
        <details <?= $section === 'empleados' ? 'open' : ''; ?>>
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="dashicons dashicons-businessman"></span>
                <span class="flex-grow-1">Gestión de Empleados</span>
            </summary>
            <ul class="list-unstyled px-2 mb-2">
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-empleados', 'list'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-empleados&tab=list') ); ?>">
                        Lista de empleados
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-empleados', 'new'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-empleados&tab=new') ); ?>">
                        Nuevo empleado
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-empleados', 'profile'); ?>"
                       href="<?= esc_url( $profile_url ); ?>">
                        Perfil del empleado
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-empleados', 'upload'); ?>"
                       href="<?= esc_url( $upload_url ); ?>">
                        Documentos
                    </a>
                </li>
            </ul>
        </details>

        <!-- Vacaciones -->
        <details <?= $section === 'vacaciones' ? 'open' : ''; ?>>
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span class="flex-grow-1">Gestión de Vacaciones</span>
            </summary>
            <ul class="list-unstyled px-2 mb-2">
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-vacaciones'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-vacaciones') ); ?>">
                        Todas las solicitudes
                    </a>
                </li>
            </ul>
        </details>

        <div class="mt-auto pt-2">
            <!-- Perfil -->
            <details <?= $section === 'perfil' ? 'open' : ''; ?>>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-admin-users"></span>
                    <span class="flex-grow-1">Mi Perfil</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-mi-perfil-info'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-perfil-info') ); ?>">
                            Mi información
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-debug-vacaciones-empleado'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-debug-vacaciones-empleado') ); ?>">
                            Mis vacaciones
                        </a>
                    </li>
                   
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-mis-documentos-contratos'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mis-documentos-contratos') ); ?>">
                            Contrato
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-mis-documentos-liquidaciones'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mis-documentos-liquidaciones') ); ?>"
                           >
                            Liquidaciones
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-mis-documentos-licencias'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mis-documentos-licencias') ); ?>"
                           >
                            Licencias
                        </a>
                    </li>
                </ul>
            </details>

            <!-- Convivencia -->
            <details>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-book-alt"></span>
                    <span class="flex-grow-1">Convivencia</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-reglamento-interno'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-reglamento-interno') ); ?>">
                            Reglamento Interno
                        </a>
                    </li>
                </ul>
            </details>
        </div>

    </nav>

    <!-- Logout -->
    <div class="p-3 border-top">
        <a class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2"
           href="<?= esc_url( wp_logout_url() ); ?>">
            <span class="dashicons dashicons-migrate"></span>
            Cerrar sesión
        </a>
    </div>

</aside>
