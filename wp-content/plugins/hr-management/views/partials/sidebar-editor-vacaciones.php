<?php
/**
 * Sidebar para EDITOR DE VACACIONES
 * Capabilities: manage_hrm_vacaciones
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

function hrm_edvac_is_active( $slug ) {
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    return ( $page === $slug ) ? 'active' : '';
}

$section = 'vacaciones';
if ( in_array( $current_page, array( 'hrm-mi-perfil', 'hrm-mi-perfil-info', 'hrm-mi-perfil-vacaciones' ), true ) ) {
    $section = 'perfil';
}

$logo_url = esc_url(
    plugins_url( 'assets/images/logo.webp', dirname( __FILE__, 2 ) )
);
?>

<style>
/* Sidebar profile/convivencia responsive: center on desktop, move to bottom on mobile */
.hrm-nav.d-flex { display: flex; flex-direction: column; }
.hrm-nav .hrm-profile-mid, .hrm-nav .hrm-convivencia-mid { margin: 0; }

@media (max-width: 767.98px) {
  /* Small screens: place profile/convivencia section at the bottom */
  .hrm-nav .hrm-profile-mid, .hrm-nav .hrm-convivencia-mid { margin-top: auto; margin-bottom: 0; }
}

@media (min-width: 768px) {
  /* Medium+ screens: center vertically */
  .hrm-nav .hrm-profile-mid, .hrm-nav .hrm-convivencia-mid { margin: auto 0; }
}
</style>

<aside class="hrm-sidebar d-flex flex-column flex-shrink-0 border-end bg-light" style="position: relative;">

    <!-- Header -->
    <div class="hrm-sidebar-header d-flex align-items-center justify-content-center p-3 border-bottom">
        <img src="<?= $logo_url; ?>" class="img-fluid" style="max-height:48px;" alt="Logo">
    </div>

    <!-- Navegación -->
    <nav class="hrm-nav flex-grow-1 py-2 pb-5 d-flex flex-column">

        <!-- Gestión de Vacaciones -->
        <details <?= $section === 'vacaciones' ? 'open' : ''; ?>>
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span class="flex-grow-1">Gestión de Vacaciones</span>
            </summary>
            <ul class="list-unstyled px-2 mb-0">
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_edvac_is_active('hrm-vacaciones'); ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-vacaciones') ); ?>">
                        Solicitudes de Vacaciones
                    </a>
                </li>
            </ul>
        </details>

        <!-- Mi Perfil (debajo de Gestión de Vacaciones) -->
        <div class="hrm-profile-mid" style="padding: 0; margin-top: 0;">
            <details <?= $section === 'perfil' ? 'open' : ''; ?> >
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-admin-users"></span>
                    <span class="flex-grow-1">Mi Perfil</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= in_array( $current_page, array( 'hrm-mi-perfil', 'hrm-mi-perfil-info' ), true ) ? 'active' : '' ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-perfil-info') ); ?>">
                            Mi Información
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_edvac_is_active('hrm-mi-perfil-vacaciones'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-perfil-vacaciones') ); ?>">
                            Mis Vacaciones
                        </a>
                    </li>
                    
                    <li>
                                <a class="nav-link px-3 py-2 <?= hrm_edvac_is_active('hrm-mi-documentos-contratos'); ?>"
                                    href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-contratos') ); ?>"
                           >
                            Contrato
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_edvac_is_active('hrm-mi-documentos-liquidaciones'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-liquidaciones') ); ?>"
                           >
                            Liquidaciones
                        </a>
                    </li>                    
                </ul>
            </details>
        </div>

        <!-- Convivencia centrada verticalmente -->
        <div class="hrm-convivencia-mid" style="margin: auto 0; padding: .5rem 0;">
            <details>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-book-alt"></span>
                    <span class="flex-grow-1">Documentos-Reglamentos</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_edvac_is_active('hrm-convivencia'); ?>"
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
