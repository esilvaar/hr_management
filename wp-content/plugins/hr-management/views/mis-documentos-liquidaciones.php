<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// DB
$db_emp  = new HRM_DB_Empleados();
$db_docs = new HRM_DB_Documentos();

$current_user_id = get_current_user_id();

// Admin puede consultar otro empleado
$employee_id = isset( $_GET['employee_id'] ) ? absint( $_GET['employee_id'] ) : null;

if ( $employee_id ) {
    $employee = $db_emp->get( $employee_id );
    if ( ! $employee ) {
        echo '<div class="notice notice-error"><p>Empleado no encontrado.</p></div>';
        return;
    }
} else {
    $employee = $db_emp->get_by_user_id( $current_user_id );
    if ( ! $employee ) {
        echo '<div class="notice notice-warning"><p>No se encontró tu registro de empleado.</p></div>';
        return;
    }
}

// Obtener liquidaciones
$documents = $db_docs->get_by_rut( $employee->rut, 'liquidaciones' );

// Ordenar documentos por mes (diciembre primero) — mantener consistencia con el listado AJAX
$month_order = array(
    'diciembre' => 1,
    'noviembre' => 2,
    'octubre'   => 3,
    'septiembre'=> 4,
    'agosto'    => 5,
    'julio'     => 6,
    'junio'     => 7,
    'mayo'      => 8,
    'abril'     => 9,
    'marzo'     => 10,
    'febrero'   => 11,
    'enero'     => 12
);

if ( ! empty( $documents ) && is_array( $documents ) ) {
    usort( $documents, function( $a, $b ) use ( $month_order ) {
        $mes_a = '';
        $mes_b = '';
        foreach ( $month_order as $mes => $ord ) {
            if ( stripos( $a->nombre, $mes ) !== false ) { $mes_a = $mes; break; }
        }
        foreach ( $month_order as $mes => $ord ) {
            if ( stripos( $b->nombre, $mes ) !== false ) { $mes_b = $mes; break; }
        }
        $orden_a = $mes_a ? $month_order[ $mes_a ] : 99;
        $orden_b = $mes_b ? $month_order[ $mes_b ] : 99;
        return $orden_a - $orden_b;
    } );
}

// JS data
wp_localize_script( 'hrm-mis-documentos', 'hrmMisDocsData', array(
    'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
    'employeeId' => isset( $employee->id ) ? intval( $employee->id ) : 0,
) );
// Enqueue per-view styles
// Styles for liquidaciones merged into plugin-common.css (assets/css/plugin-common.css) - removed specific enqueue to reduce small files. ?>

<div class="container-fluid mt-4">
    <?php
    // Mostrar botón Volver si venimos desde un perfil específico (employee_id en URL)
    if ( isset( $_GET['employee_id'] ) ) : 
        $back_page = isset( $_GET['source_page'] ) ? sanitize_text_field( $_GET['source_page'] ) : 'hrm-empleados';
    ?>
        <div class="mb-3">
            <a href="<?= esc_url( admin_url( 'admin.php?page=' . $back_page . '&tab=profile&id=' . absint( $employee->id ) ) ) ?>" class="btn btn-secondary btn-sm">
                <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: text-bottom;"></span> Volver al Perfil
            </a>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">

            <div class="hrm-panel mb-3">
                <div class="hrm-panel-header">
                    <h5 class="mb-0">
                        <span class="dashicons dashicons-media-document"></span>
                        Mis Liquidaciones
                    </h5>
                    <small class="text-muted d-block mt-2">
                        <?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?>
                        (RUT: <?= esc_html( $employee->rut ) ?>)
                    </small>
                </div>

                <div class="hrm-panel-body">

                    <div class="mb-3 d-flex align-items-center gap-2">
                        <label for="hrm-mis-year-select" class="me-2 mb-0 fw-bold">Filtrar por año:</label>
                        <select id="hrm-mis-year-select" class="form-select hrm-year-select">
                            <option value="">Todos</option>
                            <?php $anio_actual = (int) date('Y'); for ($y = $anio_actual; $y >= 2000; $y--) : ?>
                                <option value="<?= esc_attr( $y ); ?>" <?= $y === $anio_actual ? 'selected' : ''; ?>><?= esc_html( $y ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <div id="hrm-mis-download" class="dropdown ms-2">
                            <button id="hrm-mis-download-btn" class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Descargar</button>
                            <ul id="hrm-mis-download-menu" class="dropdown-menu">
                                <li><a href="#" class="dropdown-item" data-cantidad="1">Descargar última</a></li>
                                <li><a href="#" class="dropdown-item" data-cantidad="3">Descargar últimas 3</a></li>
                                <li><a href="#" class="dropdown-item" data-cantidad="6">Descargar últimas 6</a></li>
                                <li><a href="#" class="dropdown-item" data-cantidad="all">Descargar todas</a></li>
                            </ul>
                        </div>
                    </div>

                    <div id="hrm-mis-documents-container" class="hrm-mis-documents-container d-none">
                        <?php if ( ! empty( $documents ) ) : ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Año</th>
                                            <th>Archivo</th>
                                            <th>Fecha de Carga</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $documents as $doc ) : ?>
                                            <tr data-year="<?= esc_attr( $doc->anio ) ?>">
                                                <td><?= esc_html( $doc->anio ) ?></td>
                                                <td>
                                                    <span class="dashicons dashicons-media-document"></span>
                                                    <?= esc_html( $doc->nombre ) ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php
                                                        $date = strtotime( $doc->fecha_carga ?? 'now' );
                                                        echo date_i18n( 'd/m/Y H:i', $date );
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="<?= esc_url( $doc->url ) ?>"
                                                       class="btn btn-sm btn-outline-primary"
                                                       target="_blank"
                                                       rel="noopener noreferrer">
                                                        <span class="dashicons dashicons-download"></span> Descargar
                                                    </a>

                                                    <button type="button"
                                                            class="btn btn-sm btn-secondary btn-preview-doc ms-2"
                                                            data-url="<?= esc_url( $doc->url ) ?>">
                                                        <span class="dashicons dashicons-visibility"></span> Previsualizar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else : ?>
                            <div class="alert alert-info text-center py-4">
                                <span class="dashicons dashicons-media-document fs-1 opacity-50"></span>
                                <p class="mt-2 mb-0">No hay liquidaciones disponibles.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PREVISUALIZACIÓN -->
                    <?php if ( empty( $GLOBALS['hrm_doc_preview_rendered'] ) ) : $GLOBALS['hrm_doc_preview_rendered'] = true; ?>
                    <div class="mt-4 hrm-preview-panel d-none" id="hrm-preview-panel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">Previsualización de documento</h6>
                            <button type="button" id="btn-cerrar-preview" class="btn btn-sm btn-outline-secondary">
                                Cerrar
                            </button>
                        </div>
                        <iframe id="hrm-preview-iframe" class="hrm-preview-iframe"></iframe>
                    </div>
                    <?php else:
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( '[HRM-DEBUG] Skipping duplicated liquidations preview render for employee id=' . intval( $employee->id ) );
                        }
                    endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>


<?php // JS moved to assets/js/mis-documentos.js and enqueued via hrm_enqueue_mis_documentos_admin ?>
