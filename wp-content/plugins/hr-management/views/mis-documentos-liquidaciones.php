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
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
) );
?>

<div class="container-fluid mt-4">
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
                        <select id="hrm-mis-year-select" class="form-select" style="max-width:160px;">
                            <option value="">Todos</option>
                            <?php $anio_actual = (int) date('Y'); for ($y = $anio_actual; $y >= 2000; $y--) : ?>
                                <option value="<?= esc_attr( $y ); ?>" <?= $y === $anio_actual ? 'selected' : ''; ?>><?= esc_html( $y ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <div id="hrm-mis-download" style="position:relative;">
                            <button id="hrm-mis-download-btn" class="btn btn-outline-primary btn-sm ms-2">Descargar ▾</button>
                            <div id="hrm-mis-download-menu" style="display:none; position:absolute; top:calc(100% + 6px); left:0; background:#fff; border:1px solid #ddd; box-shadow:0 6px 18px rgba(0,0,0,0.08); z-index:1200; min-width:200px;">
                                <a href="#" class="dropdown-item p-2" data-cantidad="1">Descargar última</a>
                                <a href="#" class="dropdown-item p-2" data-cantidad="3">Descargar últimas 3</a>
                                <a href="#" class="dropdown-item p-2" data-cantidad="6">Descargar últimas 6</a>
                                <a href="#" class="dropdown-item p-2" data-cantidad="all">Descargar todas</a>
                            </div>
                        </div>
                    </div>

                    <div id="hrm-mis-documents-container" style="visibility:hidden;">
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
                                <span class="dashicons dashicons-media-document" style="font-size:48px;opacity:.5;"></span>
                                <p class="mt-2 mb-0">No hay liquidaciones disponibles.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PREVISUALIZACIÓN -->
                    <?php if ( empty( $GLOBALS['hrm_doc_preview_rendered'] ) ) : $GLOBALS['hrm_doc_preview_rendered'] = true; ?>
                    <div class="mt-4" id="hrm-preview-panel" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">Previsualización de documento</h6>
                            <button type="button" id="btn-cerrar-preview" class="btn btn-sm btn-outline-secondary">
                                Cerrar
                            </button>
                        </div>
                        <iframe id="hrm-preview-iframe"
                                style="width:100%;min-height:600px;border:1px solid #ccc;background:#fff;"></iframe>
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

<script>
document.addEventListener('DOMContentLoaded', function () {

    const previewPanel  = document.getElementById('hrm-preview-panel');
    const previewIframe = document.getElementById('hrm-preview-iframe');
    const closeBtn      = document.getElementById('btn-cerrar-preview');

    document.querySelectorAll('.btn-preview-doc').forEach(btn => {
        btn.addEventListener('click', function () {
            const url = this.dataset.url;
            if (!url) return;

            previewIframe.src = url;
            previewPanel.style.display = 'block';

            setTimeout(() => {
                previewPanel.scrollIntoView({ behavior: 'smooth' });
            }, 50);
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            previewPanel.style.display = 'none';
            previewIframe.src = '';
        });
    }

    // Filtrado por año (cliente) para la tabla de liquidaciones
    (function(){
        const yearSelect = document.getElementById('hrm-mis-year-select');
        const container = document.getElementById('hrm-mis-documents-container');
        if (!yearSelect || !container) return;

        function filterRowsByYear(){
            const val = yearSelect.value;
            const rows = container.querySelectorAll('table tbody tr[data-year]');
            let visible = 0;
            rows.forEach(r => {
                if (!val || r.dataset.year === val) { r.style.display = ''; visible++; } else { r.style.display = 'none'; }
            });

            // Mostrar mensaje si no hay resultados
            let noEl = container.querySelector('.hrm-no-results');
            if ( visible === 0 ) {
                if (!noEl) {
                    noEl = document.createElement('div');
                    noEl.className = 'alert alert-info hrm-no-results text-center';
                    noEl.innerHTML = '<p class="mb-0">No hay documentos para el año seleccionado.</p>';
                    container.appendChild(noEl);
                }
            } else if ( noEl ) {
                noEl.remove();
            }
        }

        yearSelect.addEventListener('change', filterRowsByYear);
        // Inicializar filtro (el select viene por defecto con el año actual seleccionado)
        filterRowsByYear();
        // Mostrar el contenedor una vez aplicado el filtro para evitar mostrar todos brevemente
        try { container.style.visibility = 'visible'; } catch(e) { /* no-op */ }
    })();

    // Descargas: manejar el menú de descarga para liquidaciones (última/3/6/todas)
    (function(){
        const btn = document.getElementById('hrm-mis-download-btn');
        const menu = document.getElementById('hrm-mis-download-menu');
        const yearSelect = document.getElementById('hrm-mis-year-select');
        const employeeId = <?= isset($employee->id) ? intval($employee->id) : 0; ?>;
        if (!btn || !menu || !yearSelect) return;

        btn.addEventListener('click', function(e){
            e.preventDefault();
            // Antes de mostrar, actualizar visibilidad de opciones según documentos disponibles
            updateDownloadMenuVisibility();
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        });

        document.addEventListener('click', function(e){
            if (!e.target.closest || (!e.target.closest('#hrm-mis-download') && !e.target.closest('#hrm-mis-download-menu') )) {
                menu.style.display = 'none';
            }
        });

        const menuItems = menu.querySelectorAll('a[data-cantidad]');
        function updateDownloadMenuVisibility(){
            // Contar filas disponibles según el año seleccionado
            const year = yearSelect.value || '';
            const rows = document.querySelectorAll('#hrm-mis-documents-container table tbody tr');
            let count = 0;
            rows.forEach(function(r){ if (!year || String(r.dataset.year) === String(year)) count++; });

            // Mostrar solo las opciones pertinentes
            menuItems.forEach(function(mi){
                const c = mi.getAttribute('data-cantidad');
                if (c === 'all') {
                    mi.style.display = (count > 0) ? 'block' : 'none';
                } else {
                    const n = parseInt(c, 10) || 0;
                    mi.style.display = (count >= n) ? 'block' : 'none';
                }
            });

            // Si no hay opciones visibles, deshabilitar el botón
            const visibleCount = Array.from(menuItems).filter(m => m.style.display !== 'none').length;
            btn.disabled = visibleCount === 0;
        }

        // Actualizar al cambiar año
        yearSelect.addEventListener('change', function(){ updateDownloadMenuVisibility(); });

        menuItems.forEach(function(a){
            a.addEventListener('click', function(e){
                e.preventDefault();
                const cantidad = this.getAttribute('data-cantidad');
                const year = yearSelect.value || '';
                // Construir URL al endpoint que genera el ZIP
                let url = '<?= admin_url( "admin-ajax.php" ); ?>?action=hrm_descargar_liquidaciones&cantidad=' + encodeURIComponent(cantidad);
                if ( year ) url += '&year=' + encodeURIComponent(year);
                if ( employeeId ) url += '&employee_id=' + encodeURIComponent(employeeId);
                // Abrir en nueva ventana/pestaña para permitir descarga sin navegar fuera
                window.open(url, '_blank');
                menu.style.display = 'none';
            });
        });
        // Inicializar visibilidad
        try { updateDownloadMenuVisibility(); } catch(e) { /* no-op */ }
    })();

});
</script>
