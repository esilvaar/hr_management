<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Obtener empleado del usuario actual
$db_emp  = new HRM_DB_Empleados();
$db_docs = new HRM_DB_Documentos();

$current_user_id = get_current_user_id();
$employee = $db_emp->get_by_user_id( $current_user_id );

if ( ! $employee ) {
    echo '<div class="notice notice-warning"><p>No se encontró tu registro de empleado.</p></div>';
    return;
}

// Obtener documentos del empleado
$documents = $db_docs->get_by_rut( $employee->rut );

// Pasar variables al JavaScript
wp_localize_script( 'hrm-mis-documentos', 'hrmMisDocsData', array(
    'employeeRut' => $employee->rut,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
) );

$hrm_sidebar_logo = esc_url( plugins_url( 'assets/images/logo.webp', dirname( __FILE__, 2 ) ) );
?>

<div class="card shadow-sm mx-auto mt-3" style="max-width: 900px;">
    <div class="card-header bg-primary text-white">
        <div class="d-flex align-items-center gap-3">
            <div>
                <div class="fw-bold fs-5">Mis Documentos</div>
                <small><?= esc_html( $employee->nombre . ' ' . $employee->apellido ) ?> (RUT: <?= esc_html( $employee->rut ) ?>)</small>
            </div>
        </div>
    </div>
    <div class="card-body">
        
        <!-- Filtros -->
        <div class="mb-3 p-3 border-bottom">
            <div class="row g-3">
                <!-- Filtro por Categoría -->
                <div class="col-md-8">
                <h6 class="fw-bold mb-2">Filtrar por Categoría</h6>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm hrm-mis-doc-category-btn" data-category="contrato"><span class="dashicons dashicons-media-document"></span> Contrato</button>
                    <button class="btn btn-outline-secondary btn-sm hrm-mis-doc-category-btn" data-category="liquidaciones"><span class="dashicons dashicons-media-document"></span> Liquidaciones</button>
                    <button class="btn btn-outline-secondary btn-sm hrm-mis-doc-category-btn" data-category="licencia"><span class="dashicons dashicons-media-document"></span> Licencias</button>
                    <button class="btn btn-outline-secondary btn-sm hrm-mis-doc-category-btn" data-category=""><span class="dashicons dashicons-trash"></span> Limpiar</button>
                </div>
                </div>
                
                <!-- Filtro por Año -->
                <div class="col-md-4">
                <h6 class="fw-bold mb-2">Filtrar por Año</h6>
                <div style="position: relative;">
                    <input type="text" class="form-control" id="hrm-mis-doc-year-filter-search" placeholder="Buscar año..." autocomplete="off">
                    <div id="hrm-mis-doc-year-filter-items" style="position: absolute; top: 100%; left: 0; width: 100%; background: white; border: 1px solid #dee2e6; border-top: none; max-height: 300px; overflow-y: auto; z-index: 1000; display: none;"></div>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Sección de Documentos -->
        <div class="hrm-mis-documents-section">
            
            <!-- Listado de Documentos -->
            <div id="hrm-mis-documents-container" class="p-3">
                <?php if ( ! empty( $documents ) ) : ?>
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Año</th>
                                <th>Archivo</th>
                                <th>Fecha de Carga</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $documents as $doc ) : ?>
                                <tr data-type="<?= esc_attr( strtolower( $doc->tipo ) ) ?>" data-year="<?= esc_attr( $doc->anio ) ?>">
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= esc_html( $doc->tipo ) ?>
                                        </span>
                                    </td>
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="alert alert-info text-center py-4">
                        <span class="dashicons dashicons-media-document" style="font-size: 48px; opacity: 0.5;"></span>
                        <p class="mt-2 mb-0">No hay documentos disponibles.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<style>
.hrm-mis-documents-section {
    overflow-x: auto;
}

.hrm-mis-documents-section table {
    margin-bottom: 0;
}

.hrm-mis-doc-category-btn {
    transition: all 0.2s ease;
}

.hrm-mis-doc-category-btn.active {
    background-color: #0d6efd;
    color: white;
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
    // Elementos DOM
    const categoryBtns = document.querySelectorAll( '.hrm-mis-doc-category-btn' );
    const yearSearch = document.getElementById( 'hrm-mis-doc-year-filter-search' );
    const yearDropdown = document.getElementById( 'hrm-mis-doc-year-filter-items' );
    const docTable = document.querySelectorAll( '#hrm-mis-documents-container table tbody tr' );
    
    // Datos disponibles
    const allYears = new Set();
    
    // Construir lista de años disponibles
    docTable.forEach( row => {
        const year = row.getAttribute( 'data-year' );
        if ( year ) allYears.add( year );
    } );
    
    // Ordenar años de mayor a menor
    const sortedYears = Array.from( allYears ).sort( (a, b) => b - a );
    
    // ===== FILTRO DE CATEGORÍA =====
    // Resetear todos los botones al inicio
    categoryBtns.forEach( btn => {
        btn.classList.remove( 'active' );
        btn.classList.remove( 'btn-primary' );
        btn.classList.add( 'btn-outline-secondary' );
    });
    
    categoryBtns.forEach( btn => {
        btn.addEventListener( 'click', function() {
            const selectedCategory = this.getAttribute( 'data-category' );
            
            // Actualizar estilos de botones
            categoryBtns.forEach( b => {
                b.classList.remove( 'active' );
                b.classList.remove( 'btn-primary' );
                b.classList.add( 'btn-outline-secondary' );
            });
            if ( selectedCategory !== '' ) {
                this.classList.remove( 'btn-outline-secondary' );
                this.classList.add( 'btn-primary' );
                this.classList.add( 'active' );
            }
            
            // Filtrar tabla
            docTable.forEach( row => {
                const rowType = row.getAttribute( 'data-type' );
                if ( selectedCategory === '' ) {
                    row.style.display = '';
                } else {
                    row.style.display = rowType === selectedCategory ? '' : 'none';
                }
            } );
            
            // Reconstruir lista de años según los visibles
            applyYearFilter();
        } );
    } );
    
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
            if ( row.style.display === 'none' ) return; // Ya filtrado por categoría
            
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
} );
</script>
