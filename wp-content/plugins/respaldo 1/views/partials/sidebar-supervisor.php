<?php
/**
 * Sidebar para SUPERVISORES
 * Capabilities: edit_hrm_employees, view_hrm_employee_admin
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

function hrm_sup_is_active( $slug, $check_tab = null ) {
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
    
    if ( $page !== $slug ) return '';
    if ( $check_tab !== null && $current_tab !== $check_tab ) return '';
    
    return 'active';
}

$section = 'empleados';
if ( in_array( $current_page, array( 'hrm-mi-perfil', 'hrm-mi-perfil-info', 'hrm-mi-perfil-vacaciones' ), true ) ) {
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
        <img src="<?= $logo_url; ?>" class="img-fluid" style="max-height:48px;" alt="Logo">
    </div>

    <!-- Navegación -->
    <nav class="hrm-nav flex-grow-1 py-2">

        <!-- Gestión de Empleados -->
        <details <?= $section === 'empleados' ? 'open' : ''; ?>>
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="dashicons dashicons-businessman"></span>
                <span class="flex-grow-1">Empleados</span>
            </summary>
            <ul class="list-unstyled px-2 mb-2">
                <li>
                    <a class="nav-link px-3 py-2 <?= in_array( $current_page, array( 'hrm-empleados' ), true ) ? 'active' : '' ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-empleados&tab=list') ); ?>">
                        Lista de empleados
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_sup_is_active('hrm-empleados', 'profile'); ?>"
                       href="<?= esc_url( $profile_url ); ?>">
                        Perfil del Empleado
                    </a>
                </li>
                <li>
                          <a class="nav-link px-3 py-2 <?= hrm_sup_is_active('hrm-empleados', 'upload'); ?>"
                              href="<?= esc_url( $upload_url ); ?>">
                                Documentos
                    </a>
                </li>
            </ul>
        </details>

        <!-- Vacaciones -->
        <details <?= in_array( $current_page, array( 'hrm-vacaciones', 'hrm-mi-perfil-vacaciones' ), true ) ? 'open' : ''; ?>>
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span class="flex-grow-1">Gestión de Vacaciones</span>
            </summary>
            <ul class="list-unstyled px-2 mb-2">
                <li>
                    <a class="nav-link px-3 py-2 <?= in_array( $current_page, array( 'hrm-vacaciones' ), true ) ? 'active' : '' ?>"
                       href="<?= esc_url( admin_url('admin.php?page=hrm-vacaciones') ); ?>">
                        Solicitudes de Vacaciones
                    </a>
                </li>
            </ul>
        </details>

        <div class="mt-auto pt-2">
            <!-- Mi Perfil -->
            <details <?= $section === 'perfil' ? 'open' : ''; ?>>
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
                        <a class="nav-link px-3 py-2 <?= hrm_sup_is_active('hrm-mi-perfil-vacaciones'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-perfil-vacaciones') ); ?>">
                            Mis Vacaciones
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sup_is_active('hrm-mi-documentos-contratos'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-contratos') ); ?>">
                            Contratos
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sup_is_active('hrm-mi-documentos-liquidaciones'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-liquidaciones') ); ?>">
                            Liquidaciones
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_sup_is_active('hrm-mi-documentos-licencias'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-licencias') ); ?>">
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
                        <a class="nav-link px-3 py-2 <?= hrm_sup_is_active('hrm-convivencia'); ?>"
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
