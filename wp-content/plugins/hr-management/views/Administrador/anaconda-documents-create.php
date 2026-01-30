<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user = wp_get_current_user();
// Enqueue per-view styles
wp_enqueue_style( 'hrm-anaconda-documents-create', plugins_url( 'hr-management/assets/css/anaconda-documents-create.css' ), array(), '1.0.0' );
// Per-view JS: modal and upload interactions (extracted from inline script)
wp_enqueue_script( 'hrm-anaconda-documents-create', HRM_PLUGIN_URL . 'assets/js/anaconda-documents-create.js', array(), HRM_PLUGIN_VERSION, true );
?> 
<div class="wrap hrm-admin-wrap">
        <div class="hrm-admin-layout">
                <?php hrm_get_template_part('partials/sidebar-loader'); ?>
                <main class="hrm-content">
                        <div class="anaconda-header">
                            <h1>Crear Documento Empresa</h1>
                            <div class="anaconda-header-actions">
                                <button id="open-create-modal" class="anaconda-open-btn btn btn-sm btn-primary">Nuevo documento</button>
                            </div>
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

                                        <label for="doc_description">Descripción (opcional)</label>
                                        <input id="doc_description" name="doc_description" type="text" />

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
            <section id="anaconda-documents-admin" class="anaconda-admin">
                <h2>Documentos registrados</h2>

                <div class="admin-controls anaconda-admin-controls">
                
                </div>

                    <div class="table-wrap anaconda-table-wrap">
                        <table id="anaconda-documents-table" class="table table-striped table-hover anaconda-table">
                        <thead class="table-dark">
                            <tr>
                                <th>Título</th>
                                <th>Archivo</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="anaconda-documents-table-body">
                            <?php
                            // List files from uploads/hrm_docs/empresa
                            $upload_dir = wp_upload_dir();
                            $empresa_dir = trailingslashit( $upload_dir['basedir'] ) . 'hrm_docs/empresa';
                            $empresa_url = trailingslashit( $upload_dir['baseurl'] ) . 'hrm_docs/empresa';

                            $rows = array();
                            if ( is_dir( $empresa_dir ) ) {
                                $files = glob( $empresa_dir . '/*.{pdf,PDF}', GLOB_BRACE );
                                if ( $files ) {
                                    usort( $files, function( $a, $b ) { return filemtime($b) - filemtime($a); } );
                                    $i = 0;
                                    foreach ( $files as $f ) {
                                        $i++;
                                        $basename = basename( $f );
                                        $meta = array();
                                        $meta_path = $f . '.json';
                                        if ( file_exists( $meta_path ) ) {
                                            $raw = @file_get_contents( $meta_path );
                                            $decoded = json_decode( $raw, true );
                                            if ( is_array( $decoded ) ) $meta = $decoded;
                                        }
                                        $title = ! empty( $meta['title'] ) ? $meta['title'] : pathinfo( $basename, PATHINFO_FILENAME );
                                        $uploaded_by = ! empty( $meta['uploaded_by'] ) ? intval( $meta['uploaded_by'] ) : 0;
                                        $uploaded_by_name = $uploaded_by ? esc_html( get_the_author_meta( 'display_name', $uploaded_by ) ) : '-';
                                        $date = date( 'Y-m-d H:i', filemtime( $f ) );
                                        $file_url = $empresa_url . '/' . rawurlencode( $basename );

                                        echo '<tr data-fpath="' . esc_attr( $f ) . '">';
                                        echo '<td class="anaconda-td">' . esc_html( $title ) . '</td>';
                                        echo '<td class="anaconda-td">' . '<a href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener">' . esc_html( $basename ) . '</a>' . '</td>';
                                        // Actions: download and delete
                                        echo '<td class="anaconda-td text-end">';
                                        echo '<a href="' . esc_url( $file_url ) . '" class="btn btn-sm btn-outline-secondary me-1" target="_blank">Descargar</a>'; 
                                        if ( current_user_can( 'manage_options' ) ) {
                                            $delete_url = esc_url( admin_url( 'admin-post.php?action=anaconda_documents_delete&file=' . rawurlencode( $basename ) . '&_wpnonce=' . wp_create_nonce( 'anaconda_documents_delete' ) ) );
                                            echo '<a href="' . $delete_url . '" class="btn btn-sm btn-danger" style="margin-left:8px;">Eliminar</a>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                }
                            }

                                if ( empty( $files ) ) {
                                    echo '<tr class="no-data"><td colspan="3" class="p-3 text-center text-muted">No hay documentos registrados.</td></tr>';
                                }
                            ?>
                        </tbody>
                    </table>
                </div>




<?php // JS moved to assets/js/anaconda-documents-create.js (placeholder removed) ?>
			</main>
        </div>
</div>

