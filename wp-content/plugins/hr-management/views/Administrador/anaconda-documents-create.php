<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user = wp_get_current_user();
// Enqueue per-view styles
wp_enqueue_style( 'hrm-anaconda-documents-create', plugins_url( 'hr-management/assets/css/anaconda-documents-create.css' ), array(), '1.0.0' );
// Per-view JS: modal and upload interactions (extracted from inline script)
wp_enqueue_script( 'hrm-anaconda-documents-create', HRM_PLUGIN_URL . 'assets/js/anaconda-documents-create.js', array(), HRM_PLUGIN_VERSION, true );
wp_localize_script( 'hrm-anaconda-documents-create', 'anacondaDocsData', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'anaconda_documents_edit' )
) );
?> 
<div class="wrap hrm-admin-wrap">
        <div class="hrm-admin-layout">
                <?php hrm_get_template_part('partials/sidebar-loader'); ?>
                <main class="hrm-content">
                        <div class="anaconda-header">
                            <h1 id="page-title">Crear Documento Empresa</h1>
                        </div>
                        <!-- Modal: formulario de creación -->
                        <div id="createModal" class="anaconda-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modal-title">
                            <div class="anaconda-modal-overlay" data-close="true"></div>
                            <div class="anaconda-modal-dialog" role="document">
                                <div class="anaconda-modal-header">
                                    <h3 id="modal-title">Crear Documento Empresa</h3>
                                    <button id="modal-close" aria-label="Cerrar" class="anaconda-modal-close">&times;</button>

                                </div>
                                <div class="anaconda-modal-body">
                                    <form id="anaconda-doc-create" class="anaconda-form" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post" enctype="multipart/form-data" novalidate>
                                        <?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'anaconda_documents_create', 'anaconda_documents_nonce' ); ?>
                                        <input type="hidden" name="action" value="anaconda_documents_create">

                                        <label for="doc_title">Título</label>
                                        <input id="doc_title" name="doc_title" type="text" maxlength="191" required />


                                        <label>Documento (PDF)</label>
                                        <div id="drop-area" class="drop-area" tabindex="0" aria-label="Área para arrastrar o seleccionar PDF">
                                            Arrastra tu PDF aquí o haz clic para seleccionar.
                                            <input id="doc_file" name="doc_file" type="file" accept="application/pdf" class="d-none" required />
                                        </div>
                                        <div id="file-info" class="file-info" aria-live="polite"></div>
                                        <div id="file-error" class="error" aria-live="assertive"></div>

                                        <div class="anaconda-modal-actions">
                                            <button type="button" id="modal-cancel" class="anaconda-cancel">Cancelar</button>
                                            <button id="submit-btn" class="anaconda-submit" type="submit">Crear documento</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>



<?php // JS moved to assets/js/anaconda-documents-create.js (modal + upload handling) ?>


            <!-- Sección: Administrar documentos registrados -->
            <section id="anaconda-documents-admin" class="anaconda-admin rounded shadow-sm mx-auto mt-3">
                
                <!-- HEADER -->
                <div class="d-flex justify-content-between align-items-center p-3 bg-dark text-white rounded-top">
                    <h2 class="mb-0">Documentos registrados</h2>
                    <div class="d-flex gap-2">
                        <button id="open-create-modal" class="btn text-black btn-light btn-sm">
                            <span class="dashicons dashicons-plus-alt2"></span> Nuevo documento
                        </button>
                    </div>
                </div>

                <div class="p-0">
                    <div class="table-responsive">
                        <table id="anaconda-documents-table" class="rounded table table-hover table-striped mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col">Título</th>
                                <th scope="col">Archivo</th>
                                <th scope="col" class="text-center myplugin-col-200">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="anaconda-documents-table-body">
                            <?php
                            global $wpdb;
                            // List documents from database
                            $table = $wpdb->prefix . 'rrhh_documentos_empresa';
                            $docs = $wpdb->get_results( "SELECT id, titulo, ruta FROM {$table} ORDER BY id DESC" );
                            
                            if ( ! empty( $docs ) ) {
                                foreach ( $docs as $doc ) {
                                    $doc_id = intval( $doc->id );
                                    $titulo = ! empty( $doc->titulo ) ? esc_html( $doc->titulo ) : 'Sin título';
                                    $ruta = ! empty( $doc->ruta ) ? $doc->ruta : '';
                                    $basename = ! empty( $ruta ) ? basename( $ruta ) : 'archivo.pdf';
                                    
                                    // Build file URL
                                    $upload_dir = wp_upload_dir();
                                    $empresa_url = trailingslashit( $upload_dir['baseurl'] ) . 'hrm_docs/empresa';
                                    $file_url = $empresa_url . '/' . rawurlencode( $basename );

                                    echo '<tr data-doc-id="' . esc_attr( $doc_id ) . '" data-filename="' . esc_attr( $basename ) . '">';
                                    echo '<td class="px-3 py-3">' . $titulo . '</td>';
                                    echo '<td class="px-3 py-3"><span class="anaconda-filename">' . esc_html( $basename ) . '</span></td>';
                                    // Actions: dropdown menu
                                    echo '<td class="px-3 py-3 text-center">';
                                    echo '<div class="dropdown-wrapper">';
                                    echo '<button class="btn btn-sm btn-outline-secondary dropdown-toggle-btn" data-doc-id="' . esc_attr( $doc_id ) . '" data-file-url="' . esc_attr( $file_url ) . '" data-title="' . esc_attr( $doc->titulo ) . '" data-ruta="' . esc_attr( $ruta ) . '">⋮</button>';
                                    echo '<div class="dropdown-menu myplugin-hidden">';
                                    echo '<a href="' . esc_url( $file_url ) . '" class="dropdown-item" target="_blank"><span class="dashicons dashicons-download"></span> Descargar</a>';
                                    echo '<button class="dropdown-item edit-doc-btn" data-doc-id="' . esc_attr( $doc_id ) . '" data-title="' . esc_attr( $doc->titulo ) . '" data-ruta="' . esc_attr( $ruta ) . '"><span class="dashicons dashicons-edit"></span> Editar</button>';
                                    if ( current_user_can( 'manage_options' ) ) {
                                        $delete_url = esc_url( admin_url( 'admin-post.php?action=anaconda_documents_delete&file=' . rawurlencode( $basename ) . '&_wpnonce=' . wp_create_nonce( 'anaconda_documents_delete' ) ) );
                                        echo '<a href="' . $delete_url . '" class="dropdown-item text-danger" onclick="return confirm(\'¿Estás seguro de que deseas eliminar este documento?\')"><span class="dashicons dashicons-trash"></span> Eliminar</a>';
                                    }
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr class="no-data"><td colspan="3" class="p-3 text-center text-muted">No hay documentos registrados.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Modal: editar documento -->
                <div id="editDocModal" class="anaconda-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="edit-doc-modal-title">
                    <div class="anaconda-modal-overlay" data-close="true"></div>
                    <div class="anaconda-modal-dialog" role="document">
                        <div class="anaconda-modal-header">
                            <h3 id="edit-doc-modal-title">Editar Documento</h3>
                            <button id="edit-doc-close" aria-label="Cerrar" class="anaconda-modal-close">&times;</button>
                        </div>
                        <div class="anaconda-modal-body">
                            <form id="edit-doc-form" class="anaconda-form" novalidate>
                                <input type="hidden" id="edit_doc_id" name="doc_id" />
                                
                                <label for="edit_doc_title">Título</label>
                                <input id="edit_doc_title" name="title" type="text" maxlength="191" required />

                                <label for="edit_doc_file">Documento (PDF)</label>
                                <div id="edit-drop-area" class="drop-area" tabindex="0" aria-label="Área para arrastrar o seleccionar PDF">
                                    Arrastra tu PDF aquí o haz clic para seleccionar (opcional).
                                    <input id="edit_doc_file" name="doc_file" type="file" accept="application/pdf" class="d-none" />
                                </div>
                                <div id="edit-file-info" class="file-info" aria-live="polite"></div>
                                <div id="edit-file-error" class="error" aria-live="assertive"></div>

                                <div class="anaconda-modal-actions">
                                    <button type="button" id="edit-doc-cancel" class="anaconda-cancel">Cancelar</button>
                                    <button type="submit" class="anaconda-submit">Guardar cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

<?php // JS moved to assets/js/anaconda-documents-create.js (modal + upload handling) ?>
			</main>
        </div>
</div>

