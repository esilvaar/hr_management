(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Modal open/close & focus handling
        const openBtn = document.getElementById('open-create-modal');
        const modal = document.getElementById('createModal');
        const closeBtn = document.getElementById('modal-close');
        const cancelBtn = document.getElementById('modal-cancel');
        const overlay = modal && modal.querySelector('.anaconda-modal-overlay');
        let lastFocus = null;

        function openModal(){
            if (!modal) return;
            lastFocus = document.activeElement;
            modal.style.display = '';
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('anaconda-modal-open');
            const firstField = modal.querySelector('input,button,select,textarea');
            firstField && firstField.focus();
        }
        function closeModal(){
            if (!modal) return;
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

        // File drag/drop + validation
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('doc_file');
        const fileInfo = document.getElementById('file-info');
        const fileError = document.getElementById('file-error');
        const submitBtn = document.getElementById('submit-btn');
        const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

        function showError(msg){ fileError.textContent = msg; fileInfo.textContent = ''; if (submitBtn) submitBtn.disabled = true; }
        function clearError(){ fileError.textContent = ''; if (submitBtn) submitBtn.disabled = false; }
        function showFile(file){ fileInfo.textContent = file.name + ' — ' + Math.round(file.size / 1024) + ' KB'; clearError(); }
        function validateFile(file){ if (!file) return showError('No se seleccionó archivo.'); if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)){ return showError('Solo se permiten archivos PDF.'); } if (file.size > MAX_SIZE){ return showError('El archivo excede el tamaño máximo (10 MB).'); } showFile(file); return true; }

        dropArea && dropArea.addEventListener('click', () => fileInput && fileInput.click());
        dropArea && dropArea.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') fileInput && fileInput.click(); });

        ['dragenter','dragover'].forEach(evt => { dropArea && dropArea.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); dropArea.classList.add('dragover'); }); });
        ['dragleave','drop'].forEach(evt => { dropArea && dropArea.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); dropArea.classList.remove('dragover'); }); });

        dropArea && dropArea.addEventListener('drop', (e) => { const dt = e.dataTransfer; if (!dt || !dt.files || !dt.files.length) return; const file = dt.files[0]; if (validateFile(file)){ const dataTransfer = new DataTransfer(); dataTransfer.items.add(file); fileInput.files = dataTransfer.files; } });

        fileInput && fileInput.addEventListener('change', () => { const file = fileInput.files[0]; validateFile(file); });

        const form = document.getElementById('anaconda-doc-create');
        form && form.addEventListener('submit', function(e){ fileError.textContent = ''; const file = fileInput.files[0]; if (!validateFile(file)){ e.preventDefault(); } });
    });
})();
