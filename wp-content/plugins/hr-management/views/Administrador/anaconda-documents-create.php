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
                        <h1>Crear Documento Empresa</h1>
                        <p>Bienvenido, <strong><?php echo esc_html( $current_user->display_name ?: $current_user->user_login ); ?></strong>.</p>

                        <div style="margin:12px 0 18px 0;">
                            <button id="open-create-modal" class="anaconda-open-btn" style="background:#0073aa;color:#fff;padding:8px 12px;border:0;cursor:pointer;">Nuevo documento</button>
                        </div>

                        <!-- Modal: formulario de creación -->
                        <div id="createModal" class="anaconda-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modal-title" style="display:none;">
                            <div class="anaconda-modal-overlay" data-close="true" style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;"></div>
                            <div class="anaconda-modal-dialog" role="document" style="position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:1001;background:#fff;border-radius:6px;max-width:640px;width:94%;box-shadow:0 10px 30px rgba(0,0,0,0.2);">
                                <div style="padding:14px 18px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;">
                                    <h3 id="modal-title" style="margin:0;font-size:16px;">Crear Documento Empresa</h3>
                                    <button id="modal-close" aria-label="Cerrar" style="background:transparent;border:0;font-size:20px;line-height:1;cursor:pointer;">&times;</button>
                                </div>
                                <div class="anaconda-modal-body" style="padding:16px;">
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

                                        <div style="margin-top:12px;text-align:right;">
                                            <button type="button" id="modal-cancel" style="margin-right:8px;padding:8px 12px;">Cancelar</button>
                                            <button id="submit-btn" class="anaconda-submit" type="submit">Crear documento</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <style>
                        .anaconda-form { max-width:520px; font-family: Arial, sans-serif; }
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
                    <div style="flex:1;min-width:200px;">
                        <label for="adm_search">Buscar</label>
                        <input id="adm_search" type="search" placeholder="Buscar por título, usuario o archivo..." style="width:100%;padding:8px;box-sizing:border-box;" />
                    </div>

                    <div>
                        <label for="adm_filter">Filtro</label>
                        <select id="adm_filter" style="padding:8px;">
                            <option value="all">Todos</option>
                            <option value="active">Activos</option>
                            <option value="archived">Archivados</option>
                        </select>
                    </div>

                    <div>
                        <label for="bulk_action">Acciones</label>
                        <select id="bulk_action" style="padding:8px;">
                            <option value="">Seleccionar acción</option>
                            <option value="delete">Eliminar</option>
                            <option value="archive">Archivar</option>
                        </select>
                        <button id="bulk_apply" style="margin-left:6px;padding:8px 10px;">Aplicar</button>
                    </div>
                </div>

                <div class="table-wrap" style="overflow:auto;border:1px solid #e5e5e5;border-radius:4px;">
                    <table id="anaconda-documents-table" style="width:100%;border-collapse:collapse;min-width:720px;">
                        <thead style="background:#f7f7f7;">
                            <tr>
                                <th style="padding:10px;border-bottom:1px solid #eee;width:40px;text-align:center;"><input id="select_all" type="checkbox" /></th>
                                <th style="padding:10px;border-bottom:1px solid #eee;text-align:left;">ID</th>
                                <th style="padding:10px;border-bottom:1px solid #eee;text-align:left;">Título</th>
                                <th style="padding:10px;border-bottom:1px solid #eee;text-align:left;">Archivo</th>
                                <th style="padding:10px;border-bottom:1px solid #eee;text-align:left;">Subido por</th>
                                <th style="padding:10px;border-bottom:1px solid #eee;text-align:left;">Fecha</th>
                                <th style="padding:10px;border-bottom:1px solid #eee;text-align:left;">Estado</th>
                                <th style="padding:10px;border-bottom:1px solid #eee;text-align:left;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="anaconda-documents-table-body">
                            <tr class="no-data">
                                <td colspan="8" style="padding:18px;text-align:center;color:#666;">No hay documentos registrados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="admin-pagination" style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                    <div class="pagination-info" style="color:#666;font-size:13px;">Mostrando 0 - 0 de 0</div>
                    <div class="pagination-controls">
                        <button id="prev_page" style="padding:6px 10px;margin-right:6px;">Anterior</button>
                        <button id="next_page" style="padding:6px 10px;">Siguiente</button>
                    </div>
                </div>

                <style>
                /* Administración: estilos básicos */
                #anaconda-documents-table th, #anaconda-documents-table td { font-size:13px; }
                #anaconda-documents-table tbody tr:nth-child(odd) { background:#fff; }
                #anaconda-documents-table tbody tr:nth-child(even) { background:#fbfbfb; }
                .anaconda-admin h2 { margin-top:18px; margin-bottom:8px; }
                .anaconda-admin .actions a { margin-right:8px; color:#0073aa; text-decoration:none; }
                </style>

                <script>
                (function(){
                    // Placeholder: aquí se cargarán los documentos vía AJAX o server-side.
                    // Por ahora solo esquema HTML; la lógica de obtención, paginado y acciones se implementará según necesidad.
                    const tableBody = document.getElementById('anaconda-documents-table-body');
                    const selectAll = document.getElementById('select_all');
                    selectAll && selectAll.addEventListener('change', function(){
                        const checked = this.checked;
                        document.querySelectorAll('#anaconda-documents-table-body input[type="checkbox"]').forEach(cb => cb.checked = checked);
                    });
                })();
                </script>
			</main>
        </div>
</div>

