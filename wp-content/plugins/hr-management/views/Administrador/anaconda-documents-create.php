<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user = wp_get_current_user();
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
                        <div id="createModal" class="anaconda-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modal-title" style="display:none;">
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
                                            <input id="doc_file" name="doc_file" type="file" accept="application/pdf" style="display:none;" required />
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

                        <style>
                        /* Scoped: normalize headings in HRM admin area */
                        .hrm-admin-wrap h1 { font-size:22px; font-weight:700; margin:0 0 12px; }
                        .hrm-admin-wrap h2 { font-size:16px; font-weight:600; margin:18px 0 8px; }
                        .hrm-admin-wrap h3 { font-size:15px; font-weight:600; margin:0; }

                        /* Header layout */
                        .anaconda-header { display:flex; align-items:center; justify-content:space-between; margin:12px 0 18px 0; max-width:980px; margin-left:auto; margin-right:auto; }
                        .anaconda-header-actions { margin-left:12px; }
                        .anaconda-open-btn { padding:6px 10px; background:#0073aa; color:#fff; border:0; }

                        /* Modal: remove inline styles and center content */
                        .anaconda-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1000; }
                        .anaconda-modal-dialog { position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); z-index:1001; background:#fff; border-radius:6px; max-width:640px; width:94%; box-shadow:0 10px 30px rgba(0,0,0,0.2); }
                        .anaconda-modal-header { padding:14px 18px; border-bottom:1px solid #eee; display:flex; align-items:center; justify-content:space-between; }
                        .anaconda-modal-header h3 { margin:0; font-size:16px; }
                        .anaconda-modal-close { background:transparent; border:0; font-size:20px; line-height:1; cursor:pointer; }
                        .anaconda-modal-body { padding:16px; }
                        .anaconda-modal-actions { margin-top:12px; text-align:right; }
                        .anaconda-cancel { margin-right:8px; padding:8px 12px; }
                        .anaconda-form { max-width:520px; font-family: Arial, sans-serif; margin:0 auto; }
                        .anaconda-form label { display:block; margin-top:12px; margin-bottom:6px; font-weight:600; }
                        .anaconda-form input[type="text"]{ width:100%; padding:8px; margin-bottom:8px; box-sizing:border-box; }
                        .drop-area { border:2px dashed #bbb; padding:18px; text-align:center; cursor:pointer; color:#444; background:#fafafa; margin-bottom:8px; }
                        .drop-area.dragover { border-color:#2b90d9; background:#f0fbff; }
                        .file-info { font-size:13px; color:#222; margin-top:6px; }
                        .error { color:#c00; font-size:13px; margin-top:6px; }
                        button.anaconda-submit { background:#0073aa; color:#fff; padding:8px 12px; border:0; cursor:pointer; }
                        button.anaconda-submit:disabled { opacity:0.6; cursor:not-allowed; }
                        .anaconda-modal-open { overflow:hidden; }
                        </style>

                        <script>
                        (function(){
                            // Modal open/close & focus handling
                            const openBtn = document.getElementById('open-create-modal');
                            const modal = document.getElementById('createModal');
                            const closeBtn = document.getElementById('modal-close');
                            const cancelBtn = document.getElementById('modal-cancel');
                            const overlay = modal && modal.querySelector('.anaconda-modal-overlay');
                            let lastFocus = null;

                            function openModal(){
                                lastFocus = document.activeElement;
                                modal.style.display = '';
                                modal.setAttribute('aria-hidden','false');
                                document.body.classList.add('anaconda-modal-open');
                                const firstField = modal.querySelector('input,button,select,textarea');
                                firstField && firstField.focus();
                            }
                            function closeModal(){
                                modal.setAttribute('aria-hidden','true');
                                modal.style.display = 'none';
                                document.body.classList.remove('anaconda-modal-open');
                                lastFocus && lastFocus.focus();
                            }

                            openBtn && openBtn.addEventListener('click', openModal);
                            closeBtn && closeBtn.addEventListener('click', closeModal);
                            cancelBtn && cancelBtn.addEventListener('click', closeModal);
                            overlay && overlay.addEventListener('click', closeModal);
                            document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal && modal.style.display !== 'none'){ closeModal(); } });

                            // File drag/drop + validation (same logic as before)
                            const dropArea = document.getElementById('drop-area');
                            const fileInput = document.getElementById('doc_file');
                            const fileInfo = document.getElementById('file-info');
                            const fileError = document.getElementById('file-error');
                            const submitBtn = document.getElementById('submit-btn');
                            const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

                            function showError(msg){ fileError.textContent = msg; fileInfo.textContent = ''; submitBtn.disabled = true; }
                            function clearError(){ fileError.textContent = ''; submitBtn.disabled = false; }
                            function showFile(file){ fileInfo.textContent = file.name + ' — ' + Math.round(file.size / 1024) + ' KB'; clearError(); }
                            function validateFile(file){ if (!file) return showError('No se seleccionó archivo.'); if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)){ return showError('Solo se permiten archivos PDF.'); } if (file.size > MAX_SIZE){ return showError('El archivo excede el tamaño máximo (10 MB).'); } showFile(file); return true; }

                            dropArea && dropArea.addEventListener('click', () => fileInput.click());
                            dropArea && dropArea.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') fileInput.click(); });

                            ['dragenter','dragover'].forEach(evt => { dropArea && dropArea.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); dropArea.classList.add('dragover'); }); });
                            ['dragleave','drop'].forEach(evt => { dropArea && dropArea.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); dropArea.classList.remove('dragover'); }); });

                            dropArea && dropArea.addEventListener('drop', (e) => { const dt = e.dataTransfer; if (!dt || !dt.files || !dt.files.length) return; const file = dt.files[0]; if (validateFile(file)){ const dataTransfer = new DataTransfer(); dataTransfer.items.add(file); fileInput.files = dataTransfer.files; } });

                            fileInput && fileInput.addEventListener('change', () => { const file = fileInput.files[0]; validateFile(file); });

                            const form = document.getElementById('anaconda-doc-create');
                            form && form.addEventListener('submit', function(e){ fileError.textContent = ''; const file = fileInput.files[0]; if (!validateFile(file)){ e.preventDefault(); } });
                        })();
                        </script>

            <!-- Sección: Administrar documentos registrados -->
            <section id="anaconda-documents-admin" class="anaconda-admin">
                <h2>Documentos registrados</h2>

                <div class="admin-controls" style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
                
                </div>

                    <div class="table-wrap" style="overflow:auto;border:1px solid #e5e5e5;border-radius:4px; max-width:980px; margin:0 auto;">
                        <table id="anaconda-documents-table" class="table table-striped table-hover" style="width:100%;border-collapse:collapse;min-width:720px;">
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
                                        echo '<td style="padding:10px;">' . esc_html( $title ) . '</td>';
                                        echo '<td style="padding:10px;">' . '<a href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener">' . esc_html( $basename ) . '</a>' . '</td>';
                                        // Actions: download and delete
                                        echo '<td style="padding:10px; text-align:right;">';
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
                                    echo '<tr class="no-data"><td colspan="3" style="padding:18px;text-align:center;color:#666;">No hay documentos registrados.</td></tr>';
                                }
                            ?>
                        </tbody>
                    </table>
                </div>


                <style>
                /* Administración: estilos tipo tabla con colores */
                #anaconda-documents-table th, #anaconda-documents-table td { font-size:13px; padding:10px; vertical-align:middle; }
                #anaconda-documents-table thead.table-dark { background:#343a40; color:#fff; }
                #anaconda-documents-table tbody tr:nth-child(odd) { background:#fff; }
                #anaconda-documents-table tbody tr:nth-child(even) { background:#fbfbfb; }
                .anaconda-admin h2 { margin-top:18px; margin-bottom:8px; }
                .anaconda-admin .actions a { margin-right:8px; color:#0073aa; text-decoration:none; }
                .anaconda-open-btn { background:#0073aa; color:#fff; border:0; }
                </style>

                    <script>
                (function(){
                    // Placeholder for future JS enhancements (pagination, ajax loading, etc.)
                })();
                </script>
			</main>
        </div>
</div>

