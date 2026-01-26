<?php
// File: /home/rrhhanacondaweb/public_html/wp-content/plugins/hr-management/views/Administrador/anaconda-documents-create.php
// Mini formulario para crear una instancia de documento (2 inputs + upload PDF con drag & drop).
// Nota: el procesamiento (guardar archivo y datos) debe implementarse en el handler de admin_post.
// Ejemplo de hook en el plugin:
// add_action('admin_post_anaconda_documents_create', 'anaconda_documents_create_handler');
// function anaconda_documents_create_handler() { /* verificar nonce, sanear, mover archivo, guardar metadatos, redirección */ }

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acceso directo.
}
?>

<style>
.anaconda-form { max-width:480px; font-family: Arial, sans-serif; }
.anaconda-form label { display:block; margin-bottom:6px; font-weight:600; }
.anaconda-form input[type="text"]{ width:100%; padding:8px; margin-bottom:12px; box-sizing:border-box; }
.drop-area {
  border:2px dashed #bbb; padding:18px; text-align:center; cursor:pointer;
  color:#444; background:#fafafa; margin-bottom:8px;
}
.drop-area.dragover { border-color:#2b90d9; background:#f0fbff; }
.file-info { font-size:13px; color:#222; margin-top:6px; }
.error { color:#c00; font-size:13px; margin-top:6px; }
button.anaconda-submit { background:#0073aa; color:#fff; padding:8px 12px; border:0; cursor:pointer; }
button.anaconda-submit:disabled { opacity:0.6; cursor:not-allowed; }
</style>

<?php if ( isset( $_GET['result'] ) ) : $res = sanitize_text_field( wp_unslash( $_GET['result'] ) ); ?>
    <?php if ( $res === 'success' ) : ?>
        <div class="notice notice-success" style="margin-bottom:12px;padding:12px;border-left:4px solid #46b450;background:#ecfbe9;">Documento creado correctamente.</div>
    <?php elseif ( $res === 'upload_error' ) : ?>
        <div class="notice notice-error" style="margin-bottom:12px;padding:12px;border-left:4px solid #c00;background:#fff0f0;">Error al subir el archivo. Revisa el tipo/tamaño e inténtalo de nuevo.</div>
    <?php elseif ( $res === 'no_file' ) : ?>
        <div class="notice notice-error" style="margin-bottom:12px;padding:12px;border-left:4px solid #c00;background:#fff0f0;">No se encontró archivo. Por favor selecciona un PDF.</div>
    <?php endif; ?>
<?php endif; ?>

<form id="anaconda-doc-create" class="anaconda-form" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post" enctype="multipart/form-data" novalidate>
    <?php wp_nonce_field( 'anaconda_documents_create', 'anaconda_documents_nonce' ); ?>
    <input type="hidden" name="action" value="anaconda_documents_create">

    <label for="doc_title">Título</label>
    <input id="doc_title" name="doc_title" type="text" maxlength="191" required />

    <label for="doc_description">Descripción</label>
    <input id="doc_description" name="doc_description" type="text" />

    <label>Documento (PDF)</label>
    <div id="drop-area" class="drop-area" tabindex="0" aria-label="Área para arrastrar o seleccionar PDF">
        Arrastra tu PDF aquí o haz clic para seleccionar.
        <input id="doc_file" name="doc_file" type="file" accept="application/pdf" style="display:none;" required />
    </div>
    <div id="file-info" class="file-info" aria-live="polite"></div>
    <div id="file-error" class="error" aria-live="assertive"></div>

    <button id="submit-btn" class="anaconda-submit" type="submit">Crear documento</button>
</form>

<script>
(function(){
  const dropArea = document.getElementById('drop-area');
  const fileInput = document.getElementById('doc_file');
  const fileInfo = document.getElementById('file-info');
  const fileError = document.getElementById('file-error');
  const submitBtn = document.getElementById('submit-btn');
  const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

  function showError(msg){
    fileError.textContent = msg;
    fileInfo.textContent = '';
    submitBtn.disabled = true;
  }
  function clearError(){
    fileError.textContent = '';
    submitBtn.disabled = false;
  }
  function showFile(file){
    fileInfo.textContent = file.name + ' — ' + Math.round(file.size / 1024) + ' KB';
    clearError();
  }
  function validateFile(file){
    if (!file) return showError('No se seleccionó archivo.');
    if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)){
      return showError('Solo se permiten archivos PDF.');
    }
    if (file.size > MAX_SIZE){
      return showError('El archivo excede el tamaño máximo (10 MB).');
    }
    showFile(file);
    return true;
  }

  dropArea.addEventListener('click', () => fileInput.click());
  dropArea.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') fileInput.click(); });

  ['dragenter','dragover'].forEach(evt => {
    dropArea.addEventListener(evt, (e) => {
      e.preventDefault(); e.stopPropagation();
      dropArea.classList.add('dragover');
    });
  });
  ['dragleave','drop'].forEach(evt => {
    dropArea.addEventListener(evt, (e) => {
      e.preventDefault(); e.stopPropagation();
      dropArea.classList.remove('dragover');
    });
  });

  dropArea.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    if (!dt || !dt.files || !dt.files.length) return;
    const file = dt.files[0];
    if (validateFile(file)){
      // asignar al input para que el form lo envíe
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      fileInput.files = dataTransfer.files;
    }
  });

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    validateFile(file);
  });

  // Validación final antes de enviar
  document.getElementById('anaconda-doc-create').addEventListener('submit', function(e){
    fileError.textContent = '';
    const file = fileInput.files[0];
    if (!validateFile(file)){
      e.preventDefault();
    }
  });
})();
</script>