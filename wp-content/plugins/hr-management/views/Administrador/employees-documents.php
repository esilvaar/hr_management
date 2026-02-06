<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Forzar carga del JS de subida de documentos siempre
wp_enqueue_script(
    'hrm-documents-upload',
    HRM_PLUGIN_URL . 'assets/js/documents-upload.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// Forzar carga del JS de lista de documentos (core)
wp_enqueue_script(
    'hrm-documents-list',
    HRM_PLUGIN_URL . 'assets/js/documents-list.js',
    array('jquery'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// Init script depende del core para garantizar que las funciones estén disponibles
wp_enqueue_script(
    'hrm-documents-list-init',
    HRM_PLUGIN_URL . 'assets/js/documents-list-init.js',
    array('jquery', 'hrm-documents-list'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// Per-view admin behavior for documents (migrated from inline script)
wp_enqueue_script(
    'hrm-employees-documents-admin',
    HRM_PLUGIN_URL . 'assets/js/employees-documents.js',
    array('jquery','hrm-documents-list-init','hrm-documents-list'),
    defined('HRM_PLUGIN_VERSION') ? HRM_PLUGIN_VERSION : '1.0.0',
    true
);

// No terminamos la ejecución aquí: permitimos mostrar filtros y botón incluso sin un empleado seleccionado.
$has_employee = ! empty( $employee );
$employee_id  = $has_employee ? intval( $employee->id ) : 0;
$current_user_roles = (array) wp_get_current_user()->roles;
$is_editor_vacaciones = in_array( 'editor_vacaciones', $current_user_roles ) || in_array( 'editor', $current_user_roles );

// Pasar variables al JavaScript mediante wp_localize_script
// Preparar lista de tipos para JS: normalizar a array de objetos { id, name }
$doc_types_js = array();
if ( ! empty( $hrm_tipos_documento ) ) {
    foreach ( $hrm_tipos_documento as $k => $v ) {
        if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
            $doc_types_js[] = array( 'id' => (int) $k, 'name' => (string) $v );
        } elseif ( is_array( $v ) && isset( $v['id'] ) ) {
            $doc_types_js[] = array( 'id' => (int) $v['id'], 'name' => (string) ( $v['nombre'] ?? $v['name'] ?? '' ) );
        } else {
            $doc_types_js[] = array( 'id' => '', 'name' => (string) $v );
        }
    }
}

// Remove 'Empresa' type from JS and selectors so it is not used client-side
if ( ! empty( $doc_types_js ) ) {
    $doc_types_js = array_filter( $doc_types_js, function( $t ) use ($is_editor_vacaciones) {
        $name = strtolower( trim( (string) ($t['name'] ?? '') ) );
        if ( $name === 'empresa' ) return false;
        
        // Si es editor_vacaciones, solo conservar 'Contratos'
        if ( $is_editor_vacaciones ) {
            return in_array( $name, [ 'contrato', 'contratos' ], true );
        }
        
        return true;
    } );
    // Reindex
    $doc_types_js = array_values( $doc_types_js );
}

wp_localize_script( 'hrm-documents-list-init', 'hrmDocsListData', array(
    'employeeId' => $employee_id,
    'hasEmployee' => $has_employee,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'hrm_get_documents' ),
    'deleteNonce' => wp_create_nonce( 'hrm_delete_file' ),
    'createTypeNonce' => wp_create_nonce( 'hrm_create_type' ),
    'deleteTypeNonce' => wp_create_nonce( 'hrm_delete_type' ),
    'types' => $doc_types_js,
    'currentUserId' => intval( get_current_user_id() ),
    'canViewOthers' => ( current_user_can('manage_options') || current_user_can('edit_hrm_employees') ),
) );
?>

<!-- Sección de Documentos -->
<div class="hrm-documents-section">
    <div class="d-flex align-items-center justify-content-between mb-3 p-3">
        <h5 class="mb-0 text-black">Documentos del Empleado</h5>
    </div>

    <!-- Filtros (Tipo y Año) en la misma fila -->
    <div class="mb-3 p-3">
        <div class="row     ">
            <div class="col-12 mb-2">
                <h6 class="fw-bold mb-2">Filtros</h6>
            </div>

            <div class="col-md d-flex align-items-center justify-content-between gap-2">
                <div id="hrm-doc-filters-row" class="d-flex gap-2 flex-wrap align-items-center">
                    <div class="hrm-filter-container hrm-filter-container-type">
                        <input type="text" class="form-control hrm-filter-input" id="hrm-doc-type-filter-search" placeholder="Buscar tipo" autocomplete="off">
                        <div id="hrm-doc-type-filter-items"></div>
                        <button type="button" class="btn hrm-filter-clear hrm-filter-clear-inline" data-filter="type" title="Limpiar tipo">&times;</button>
                    </div>

                    <div class="hrm-filter-container hrm-filter-container-year">
                        <input type="text" id="hrm-doc-year-filter-search" class="form-control" placeholder="Selecciona año" autocomplete="off">
                        <div id="hrm-doc-year-filter-items"></div>
                        <button type="button" class="btn hrm-filter-clear hrm-filter-clear-inline" data-filter="year" title="Limpiar año">&times;</button>
                    </div>
                    
                    <input type="hidden" id="hrm-doc-filter-type-id" value="">
                    <input type="hidden" id="hrm-doc-filter-year" value="<?= esc_attr( date('Y') ); ?>">
                    <button id="hrm-doc-filters-clear-all" type="button" class="btn btn-sm btn-outline-secondary ms-1" title="Limpiar todos">&times;</button>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-success btn-sm" id="btn-nuevo-documento" data-employee-id="<?= esc_attr( $employee_id ) ?>" data-has-employee="<?= $has_employee ? '1' : '0' ?>" <?= ! $has_employee ? 'disabled aria-disabled="true"' : '' ?> title="<?= ! $has_employee ? 'Selecciona un usuario para habilitar' : 'Subir Documento' ?>">Subir Documento</button>
                    <?php if ( ! $is_editor_vacaciones ) : ?>
                        <button class="btn btn-secondary btn-sm" id="btn-nuevo-directorio" data-has-employee="1" data-employee-id="" title="Crear Directorio">Crear Directorio</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <!-- Panel de subida de documentos -->
    <div id="hrm-upload-panel" class="border rounded shadow p-4 mb-4 bg-white hrm-upload-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><span class="dashicons dashicons-upload"></span> SubirDocumento</h5>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-cerrar-upload">Cerrar</button>
        </div>
        <form method="post" enctype="multipart/form-data" id="hrm-upload-form">
            <?php wp_nonce_field( 'hrm_upload_file', 'hrm_upload_nonce' ); ?>
            <input type="hidden" name="employee_id" id="hrm_upload_employee_id" value="<?= esc_attr( $employee_id ) ?>">
            <input type="hidden" name="hrm_action" value="upload_document">
            <div class="mb-3">
                <label class="form-label fw-bold" for="hrm-tipo-search">Tipo de Documento *</label>
                <div class="hrm-filter-container hrm-filter-container-type">
                    <input 
                        type="text" 
                        class="form-control" 
                        id="hrm-tipo-search" 
                        placeholder="Selecciona o escribe tipo..."
                        autocomplete="off">
                    <div id="hrm-tipo-items">
                        <?php
                        // $hrm_tipos_documento puede ser array asociativo id=>nombre o lista de strings (legacy)
                        foreach ( $hrm_tipos_documento as $k => $v ) :
                                if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
                                    $tipo_id = (int) $k;
                                    $tipo_name = (string) $v;
                                } elseif ( is_array( $v ) && isset( $v['id'] ) ) {
                                    $tipo_id = (int) $v['id'];
                                    $tipo_name = (string) ( $v['nombre'] ?? $v['name'] ?? '' );
                                } else {
                                    // Legacy: flat array of names
                                    $tipo_id = '';
                                    $tipo_name = (string) $v;
                                }
                                // Omitir tipo 'Empresa' completamente en el selector de subida
                                if ( strtolower( trim( $tipo_name ) ) === 'empresa' ) continue;
                                
                                // Restricción para editor_vacaciones: SOLO puede subir 'Contratos'
                                if ( $is_editor_vacaciones && ! in_array( strtolower( trim( $tipo_name ) ), [ 'contrato', 'contratos' ], true ) ) continue;
                            ?>
                                <a class="dropdown-item py-2 px-3 hrm-tipo-item" href="#" data-tipo-id="<?= esc_attr( $tipo_id ) ?>" data-tipo-name="<?= esc_attr( $tipo_name ) ?>">
                                    <strong><?= esc_html( $tipo_name ) ?></strong>
                                </a>
                            <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <input type="hidden" id="hrm_tipo_documento" name="tipo_documento" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold" for="hrm-anio-search">Año del Documento *</label>
                <div class="hrm-filter-container">
                    <input 
                        type="text" 
                        class="form-control" 
                        id="hrm-anio-search" 
                        placeholder="Selecciona o escribe año..."
                        autocomplete="off">
                    <div id="hrm-anio-items">
                        <?php
                        $anio_actual = (int)date('Y');
                        for ($y = $anio_actual; $y >= 2000; $y--) {
                            echo '<a class="dropdown-item py-2 px-3 hrm-anio-item" href="#" data-anio="' . $y . '"><strong>' . $y . '</strong></a>';
                        }
                        ?>
                    </div>
                </div>
                <input type="hidden" id="hrm_anio_documento" name="anio_documento" required>
            </div>
            <div class="mb-3">
                <label for="hrm_archivos_subidos" class="form-label fw-bold">Archivos (PDF) *</label>
                <input id="hrm_archivos_subidos"
                       type="file"
                       name="archivos_subidos[]"
                       multiple
                       required
                       accept=".pdf"
                       class="form-control">
            </div>
            <div id="hrm-upload-message" class="mt-3"></div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-secondary" id="btn-cancelar-upload">Cancelar</button>
                <button type="submit" class="btn btn-success">
                    <span class="dashicons dashicons-upload"></span> Subir Documentos
                </button>
            </div>
        </form>
    </div>
    <!-- Panel de Creacion de Directorios (modal similar a Subir) -->
    <?php if ( ! $is_editor_vacaciones ) : ?>
    <div id="hrm-create-type-panel" class="border rounded shadow p-4 mb-4 bg-white hrm-create-type-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><span ></span> Gestión de Directorios</h5>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Tipos existentes</label>
            <div id="hrm-create-type-list" class="hrm-create-type-list">
                <?php foreach ( $hrm_tipos_documento as $k => $v ) :
                    if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
                        $tipo_id = (int) $k;
                        $tipo_name = (string) $v;
                    } elseif ( is_array( $v ) && isset( $v['id'] ) ) {
                        $tipo_id = (int) $v['id'];
                        $tipo_name = (string) ( $v['nombre'] ?? $v['name'] ?? '' );
                    } else {
                        $tipo_id = '';
                        $tipo_name = (string) $v;
                    }
                    // Omitir 'Empresa' de la lista de gestión para que no aparezca en la UI
                    if ( strtolower( trim( $tipo_name ) ) === 'empresa' ) continue;
                ?>
                    <div class="d-flex align-items-center justify-content-between py-1 hrm-type-row" data-type-id="<?= esc_attr( $tipo_id ) ?>">
                        <div class="text-start"><?= esc_html( $tipo_name ) ?></div>
                        <div>
                            <?php if ( $tipo_id ) : ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-type" data-type-id="<?= esc_attr( $tipo_id ) ?>" title="Eliminar tipo">Eliminar</button>
                            <?php else : ?>
                                <button type="button" class="btn btn-sm btn-secondary" disabled>Legacy</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <hr>

        <form id="hrm-create-type-form">
            <?php wp_nonce_field( 'hrm_create_type', 'hrm_create_type_nonce' ); ?>
            <div class="mb-3">
                <label class="form-label fw-bold" for="hrm-create-tipo-name">Crear nuevo tipo</label>
                <div class="input-group">
                    <input type="text" id="hrm-create-tipo-name" class="form-control" required placeholder="Escribe el nombre...">
                    <button type="button" class="btn btn-success" id="btn-crear-tipo-dir">Crear tipo</button>
                </div>
            </div>
            <div id="hrm-create-type-message" class="mt-1"></div>
            <div class="d-flex justify-content-end gap-2 mt-2">
                <button type="button" class="btn btn-secondary" id="btn-cancelar-create-type">Cerrar</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Listado de Documentos -->
    <div id="hrm-documents-message"></div>
    <div id="hrm-documents-container" class="p-3 text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>
</div>

