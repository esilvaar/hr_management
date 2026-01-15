<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Obtener empleado a consultar
$db_emp  = new HRM_DB_Empleados();
$db_docs = new HRM_DB_Documentos();

$current_user_id = get_current_user_id();

// Si viene employee_id (admin viendo documentos de otro empleado), usar ese
// Si no, usar el usuario actual
$employee_id = isset( $_GET['employee_id'] ) ? absint( $_GET['employee_id'] ) : null;

if ( $employee_id ) {
    // Admin consultando documento de otro empleado
    $employee = $db_emp->get( $employee_id );
    if ( ! $employee ) {
        echo '<div class="notice notice-error"><p>Empleado no encontrado.</p></div>';
        return;
    }
} else {
    // Usuario viendo sus propios documentos
    $employee = $db_emp->get_by_user_id( $current_user_id );
    if ( ! $employee ) {
        echo '<div class="notice notice-warning"><p>No se encontró tu registro de empleado.</p></div>';
        return;
    }
}

// Obtener documentos del empleado (solo Liquidaciones)
$documents = $db_docs->get_by_rut( $employee->rut, 'liquidaciones' );

// Pasar variables al JavaScript
wp_localize_script( 'hrm-mis-documentos', 'hrmMisDocsData', array(
    'employeeRut' => $employee->rut,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
) );

$hrm_sidebar_logo = esc_url( plugins_url( 'assets/images/logo.webp', dirname( __FILE__, 2 ) ) );
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- CONTENIDO PRINCIPAL -->
        <div class="col-12">
            <!-- TARJETA: Mis Liquidaciones -->
            <div class="hrm-panel mb-3">
                <div class="hrm-panel-header">
                    <h5 class="mb-0">
                        <span class="dashicons dashicons-media-document"></span>
                        Mis Liquidaciones
                    </h5>
                    <small class="text-muted d-block mt-2">
                        <?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?> (RUT: <?= esc_html( $employee->rut ) ?>)
                    </small>
                </div>
                <div class="hrm-panel-body">
                    

                    <!-- Opciones de descarga múltiple ocultas temporalmente -->
                    <div style="display:none">
                        <div class="d-flex flex-wrap gap-2 align-items-end">
                            <button id="btn-descargar-ultima-liquidacion" class="btn btn-outline-success" type="button">
                                <span class="dashicons dashicons-download"></span> Descargar Última Liquidación
                            </button>
                            <select id="select-cantidad-liquidaciones" class="form-select form-select-sm" style="width:auto;">
                                <option value="3">Últimas 3</option>
                                <option value="6">Últimas 6</option>
                            </select>
                            <button id="btn-descargar-varias-liquidaciones" class="btn btn-outline-primary btn-sm" type="button">
                                <span class="dashicons dashicons-download"></span> Descargar Selección
                            </button>
                        </div>
                    </div>

                    <!-- Listado de Documentos -->
                    <div id="hrm-mis-documents-container">
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
                                                       rel="noopener noreferrer"
                                                       title="Descargar documento">
                                                        <span class="dashicons dashicons-download"></span> Descargar
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-secondary btn-preview-doc ms-2" data-url="<?= esc_url( $doc->url ) ?>">
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
                                <span class="dashicons dashicons-media-document" style="font-size: 48px; opacity: 0.5;"></span>
                                <p class="mt-2 mb-0">No hay liquidaciones disponibles.</p>
                            </div>
                        <?php endif; ?>

                </div>
            </div>
            <!-- IFRAME de previsualización -->
            <div class="mt-4" id="hrm-preview-panel" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">Previsualización de documento</h6>
                    <button type="button" id="btn-cerrar-preview" class="btn btn-sm btn-outline-secondary">Cerrar</button>
                </div>
                <iframe id="hrm-preview-iframe" src="" style="width:100%; min-height:600px; border:1px solid #ccc; background:#fff;"></iframe>
            </div>
        </div>
    </div>
</div>

<style>
.hrm-mis-documents-section table {
    margin-bottom: 0;
}

#hrm-mis-doc-year-filter-items .dropdown-item {
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

#hrm-mis-doc-year-filter-items .dropdown-item:hover {
    background-color: #f8f9fa;
}
</style>

<script>
document.addEventListener( 'DOMContentLoaded', function() {
    // Previsualización de documentos
    const previewPanel = document.getElementById('hrm-preview-panel');
    const previewIframe = document.getElementById('hrm-preview-iframe');
    document.querySelectorAll('.btn-preview-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            if (url) {
                previewIframe.src = url;
                previewPanel.style.display = 'block';
                previewPanel.scrollIntoView({behavior:'smooth'});
            }
        });
    });
    // Botón para cerrar la previsualización
    const btnCerrarPreview = document.getElementById('btn-cerrar-preview');
    if (btnCerrarPreview) {
        btnCerrarPreview.addEventListener('click', function() {
            previewPanel.style.display = 'none';
            previewIframe.src = '';
        });
    }
    // Elementos DOM
    const yearSearch = document.getElementById( 'hrm-mis-doc-year-filter-search' );
    const yearDropdown = document.getElementById( 'hrm-mis-doc-year-filter-items' );
    const docTable = document.querySelectorAll( '#hrm-mis-documents-container table tbody tr' );
    const btnUltima = document.getElementById('btn-descargar-ultima-liquidacion');
    const btnVarias = document.getElementById('btn-descargar-varias-liquidaciones');
    const selectCantidad = document.getElementById('select-cantidad-liquidaciones');
    // ===== BOTÓN: Descargar Varias Liquidaciones (ZIP) =====
    if (btnVarias && selectCantidad) {
        btnVarias.addEventListener('click', function() {
            let year = '';
            if (yearSearch && yearSearch.value) {
                year = yearSearch.value;
            } else if (docTable.length > 0) {
                year = docTable[0].getAttribute('data-year');
            } else {
                year = new Date().getFullYear();
            }
            let cantidad = selectCantidad.value || 'all';
            let url = hrmMisDocsData.ajaxUrl + '?action=hrm_descargar_liquidaciones&year=' + encodeURIComponent(year) + '&cantidad=' + encodeURIComponent(cantidad);
            window.location.href = url;
        });
    }

    // Datos disponibles
    const allYears = new Set();
    const meses = [
        'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'
    ];

    // Construir lista de años disponibles
    docTable.forEach( row => {
        const year = row.getAttribute( 'data-year' );
        if ( year ) allYears.add( year );
    } );

    // Ordenar años de mayor a menor
    const sortedYears = Array.from( allYears ).sort( (a, b) => b - a );

    // ===== FILTRO DE AÑO =====
    function buildYearList() {
        yearDropdown.innerHTML = '';

        sortedYears.forEach( year => {
            const link = document.createElement( 'a' );
            link.href = '#';
            link.className = 'dropdown-item py-2 px-3';
            link.textContent = year;
            link.addEventListener( 'click', function( e ) {
                e.preventDefault();
                yearSearch.value = year;
                yearDropdown.style.display = 'none';
                applyYearFilter();
            } );
            yearDropdown.appendChild( link );
        } );
    }

    function applyYearFilter() {
        const selectedYear = yearSearch.value;

        docTable.forEach( row => {
            const rowYear = row.getAttribute( 'data-year' );
            if ( selectedYear === '' ) {
                row.style.display = '';
            } else {
                row.style.display = rowYear === selectedYear ? '' : 'none';
            }
        } );
    }

    // Evento de búsqueda de año
    if ( yearSearch ) {
        yearSearch.addEventListener( 'focus', function() {
            buildYearList();
            yearDropdown.style.display = 'block';
        } );

        yearSearch.addEventListener( 'blur', function() {
            setTimeout( () => {
                yearDropdown.style.display = 'none';
            }, 100 );
        } );

        yearSearch.addEventListener( 'input', function() {
            const q = this.value.toLowerCase();
            const items = yearDropdown.querySelectorAll( 'a' );
            items.forEach( item => {
                const txt = item.textContent.toLowerCase();
                item.style.display = txt.includes( q ) ? '' : 'none';
            } );
        } );
    }

    // Inicial
    buildYearList();

    // ===== ESTABLECER AÑO POR DEFECTO (2026) =====
    if ( yearSearch && sortedYears.includes( '2026' ) ) {
        yearSearch.value = '2026';
        applyYearFilter();
    }

    // ===== BOTÓN: Descargar Última Liquidación (ZIP, solo 1) =====
    if (btnUltima) {
        btnUltima.addEventListener('click', function() {
            let year = '';
            if (yearSearch && yearSearch.value) {
                year = yearSearch.value;
            } else if (docTable.length > 0) {
                year = docTable[0].getAttribute('data-year');
            } else {
                year = new Date().getFullYear();
            }
            let url = hrmMisDocsData.ajaxUrl + '?action=hrm_descargar_liquidaciones&year=' + encodeURIComponent(year) + '&cantidad=1';
            window.location.href = url;
        });
    }
});
</script>