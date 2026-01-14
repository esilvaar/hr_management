<?php
/**
 * Sidebar para EMPLEADOS
 * Capabilities: view_hrm_own_profile (rol: empleado)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

function hrm_emp_is_active( $slug ) {
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    return ( $page === $slug ) ? 'active' : '';
}

$section = 'perfil';
if ( $current_page === 'hrm-mi-perfil-vacaciones' ) {
    $section = 'vacaciones';
}

$logo_url = esc_url(
    plugins_url( 'assets/images/logo.webp', dirname( __FILE__, 2 ) )
);
?>

<aside class="hrm-sidebar d-flex flex-column flex-shrink-0 border-end bg-light">

    <!-- Header -->
    <div class="hrm-sidebar-header d-flex align-items-center justify-content-center p-3 border-bottom">
        <img src="<?= $logo_url; ?>" class="img-fluid" style="max-height:48px;" alt="Logo">
    </div>

    <!-- Navegación -->
    <nav class="hrm-nav flex-grow-1 py-2">

        <!-- Mi Perfil -->
        <details <?= $section === 'perfil' ? 'open' : ''; ?>>
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold user-select-none">
                <span class="dashicons dashicons-admin-users"></span>
                <span class="flex-grow-1">Mi Perfil</span>
            </summary>
            <ul class="list-unstyled px-2 mb-2">
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_emp_is_active('hrm-mi-perfil'); ?> <?= hrm_emp_is_active('hrm-mi-perfil-info'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-mi-perfil-info') ); ?>">
                        Mi Información
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_emp_is_active('hrm-mi-perfil-vacaciones'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-mi-perfil-vacaciones') ); ?>">
                        Mis Vacaciones
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_emp_is_active('hrm-mi-documentos'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos') ); ?>">
                        Mis Documentos
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_emp_is_active('hrm-mi-documentos-contratos'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-contratos') ); ?>"
                       style="margin-left: 1rem; font-size: 0.9rem;">
                        Contratos
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_emp_is_active('hrm-mi-documentos-liquidaciones'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-liquidaciones') ); ?>"
                       style="margin-left: 1rem; font-size: 0.9rem;">
                        Liquidaciones
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_emp_is_active('hrm-mi-documentos-licencias'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-licencias') ); ?>"
                       style="margin-left: 1rem; font-size: 0.9rem;">
                        Licencias
                    </a>
                </li>
            </ul>
        </details>

        <div class="mt-auto pt-2">
            <!-- Convivencia -->
            <details>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold user-select-none">
                    <span class="dashicons dashicons-book-alt"></span>
                    <span class="flex-grow-1">Convivencia</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_emp_is_active('hrm-convivencia'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-convivencia') ); ?>">
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
