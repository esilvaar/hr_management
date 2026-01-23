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

<style>
/* Sidebar profile responsive: center on desktop, move to bottom on mobile */
.hrm-nav.d-flex { display: flex; flex-direction: column; }
.hrm-nav .hrm-profile-mid { margin: 0; }

@media (max-width: 767.98px) {
  /* Small screens: place profile section at the bottom */
  .hrm-nav .hrm-profile-mid { margin-top: auto; margin-bottom: 0; }
}

@media (min-width: 768px) {
  /* Medium+ screens: center vertically */
  .hrm-nav .hrm-profile-mid { margin: auto 0; }
}
</style>

<aside class="hrm-sidebar d-flex flex-column flex-shrink-0 border-end bg-light" style="position: relative;">

    <!-- Header -->
    <div class="hrm-sidebar-header d-flex align-items-center justify-content-center p-3 border-bottom">
        <a href="<?= esc_url( admin_url('admin.php?page=hrm-empleados&tab=list') ); ?>" class="d-flex align-items-center justify-content-center">
            <img src="<?= $logo_url; ?>" class="img-fluid" style="max-height:48px;" alt="Logo">
        </a>
    </div>

    <!-- Navegación -->
    <nav class="hrm-nav flex-grow-1 py-2 pb-5 d-flex flex-column">

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
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-empleados', 'profile'); ?>"
                    href="<?= esc_url( $profile_url ); ?>">
                    Perfil del empleado
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-empleados', 'upload'); ?>"
                    href="<?= esc_url( $upload_url ); ?>">
                    Documentos del empleado
                    </a>
                </li>
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-empleados', 'new'); ?>"
                    href="<?= esc_url( admin_url('admin.php?page=hrm-empleados&tab=new') ); ?>">
                        Nuevo empleado
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
                        Solicitudes de Vacaciones
                    </a>
                </li>
            </ul>
        </details>
        <details <?= $section === 'empresa' ? 'open' : ''; ?>>
            <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                <span class="dashicons dashicons-chart-bar"></span>
                <span class="flex-grow-1">Gestión Empresa</span>
            </summary>
            <ul class="list-unstyled px-2 mb-2">
                <li>
                    <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-reportes'); ?>"
                       href="">
                        Crear Documentos Empresa
                    </a>
                </li>
            </ul>

        </details>

        <!-- Centramos la sección 'Mi Perfil' verticalmente usando flexbox (no absoluto) -->
        <div class="hrm-profile-mid" style="margin: auto 0; padding: .5rem 0;">
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
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-mi-perfil-vacaciones'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-perfil-vacaciones') ); ?>">
                            Mis vacaciones
                        </a>
                    </li>
                   
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-mi-documentos-contratos'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-contratos') ); ?>">
                            Contrato
                        </a>
                    </li>
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-mi-documentos-liquidaciones'); ?>"
                           href="<?= esc_url( admin_url('admin.php?page=hrm-mi-documentos-liquidaciones') ); ?>"
                           >
                            Liquidaciones
                        </a>
                    </li>

                    <?php
                    // Insertar dinámicamente los tipos de documento bajo "Mis Documentos"
                    hrm_ensure_db_classes();
                    $db_docs = new HRM_DB_Documentos();
                    $hrm_doc_types = $db_docs->get_all_types();
                    if ( ! empty( $hrm_doc_types ) ) :
                        // Evitar duplicados con los items estáticos (Contrato/Liquidaciones/Licencias)
                        $reserved = array_map( 'strtolower', array( 'contrato', 'contratos', 'liquidacion', 'liquidaciones', 'licencia', 'licencias' ) );
                        foreach ( $hrm_doc_types as $t_id => $t_name ) :
                            if ( in_array( strtolower( trim( $t_name ) ), $reserved, true ) ) continue;
                    ?>
                        <li>
                            <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar( 'hrm-mi-documentos-type-' . intval( $t_id ) ); ?>"
                               href="<?= esc_url( admin_url( 'admin.php?page=hrm-mi-documentos-type-' . intval( $t_id ) ) ); ?>">
                                <?= esc_html( $t_name ) ?>
                            </a>
                        </li>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </ul>
            </details>

            <!-- Documentos-Reglamentos -->
            <details>
                <summary class="d-flex align-items-center gap-2 px-3 py-2 fw-semibold">
                    <span class="dashicons dashicons-book-alt"></span>
                    <span class="flex-grow-1">Documentos-Reglamentos</span>
                </summary>
                <ul class="list-unstyled px-2 mb-2">
                    <li>
                        <a class="nav-link px-3 py-2 <?= hrm_is_active_sidebar('hrm-convivencia'); ?>"
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
