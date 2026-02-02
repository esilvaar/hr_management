(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const ajaxUrl = window.anacondaDocsData ? window.anacondaDocsData.ajaxUrl : '';
        const nonce = window.anacondaDocsData ? window.anacondaDocsData.nonce : '';

        // Modal open/close & focus handling (create document modal)
        const openBtn = document.getElementById('open-create-modal');
        const modal = document.getElementById('createModal');
        const closeBtn = document.getElementById('modal-close');
        const cancelBtn = document.getElementById('modal-cancel');
        const overlay = modal && modal.querySelector('.anaconda-modal-overlay');
        let lastFocus = null;

        function openModal(){
            if (!modal) return;
            lastFocus = document.activeElement;
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('anaconda-modal-open');
            const firstField = modal.querySelector('input,button,select,textarea');
            firstField && firstField.focus();
        }
        function closeModal(){
            if (!modal) return;
            modal.setAttribute('aria-hidden','true');
            document.body.classList.remove('anaconda-modal-open');
            lastFocus && lastFocus.focus();
        }

        openBtn && openBtn.addEventListener('click', openModal);
        closeBtn && closeBtn.addEventListener('click', closeModal);
        cancelBtn && cancelBtn.addEventListener('click', closeModal);
        overlay && overlay.addEventListener('click', closeModal);
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal && modal.getAttribute('aria-hidden') === 'false'){ closeModal(); } });

        // Edit document modal handlers
        const editDocModal = document.getElementById('editDocModal');
        const editDocForm = document.getElementById('edit-doc-form');
        const editDocTitle = document.getElementById('edit_doc_title');
        const editDocFile = document.getElementById('edit_doc_file');
        const editDropArea = document.getElementById('edit-drop-area');
        const editFileInfo = document.getElementById('edit-file-info');
        const editFileError = document.getElementById('edit-file-error');
        const editDocId = document.getElementById('edit_doc_id');
        const editDocCloseBtn = document.getElementById('edit-doc-close');
        const editDocCancelBtn = document.getElementById('edit-doc-cancel');
        const editDocOverlay = editDocModal && editDocModal.querySelector('.anaconda-modal-overlay');
        const MAX_SIZE = 10 * 1024 * 1024;

        function openEditModal(docId, title, ruta){
            if (!editDocModal) return;
            lastFocus = document.activeElement;
            editDocId.value = docId;
            editDocTitle.value = title;
            editDocFile.value = '';
            editFileInfo.textContent = '';
            editFileError.textContent = '';
            editDocModal.setAttribute('aria-hidden','false');
            document.body.classList.add('anaconda-modal-open');
            editDocTitle.focus();
        }

        function closeEditModal(){
            if (!editDocModal) return;
            editDocModal.setAttribute('aria-hidden','true');
            document.body.classList.remove('anaconda-modal-open');
            lastFocus && lastFocus.focus();
        }

        editDocCloseBtn && editDocCloseBtn.addEventListener('click', closeEditModal);
        editDocCancelBtn && editDocCancelBtn.addEventListener('click', closeEditModal);
        editDocOverlay && editDocOverlay.addEventListener('click', closeEditModal);
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && editDocModal && editDocModal.getAttribute('aria-hidden') === 'false'){ closeEditModal(); } });

        // Dropdown menu handlers
        document.addEventListener('click', function(e){
            // Toggle dropdown on button click
            if (e.target.classList.contains('dropdown-toggle-btn')) {
                e.stopPropagation();
                const btn = e.target;
                const menu = btn.nextElementSibling;
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });
                
                // Position dropdown
                if (!menu.classList.contains('show')) {
                    const rect = btn.getBoundingClientRect();
                    menu.style.top = (rect.bottom + window.scrollY) + 'px';
                    menu.style.left = (rect.right - 160) + 'px'; // Align to right edge of button
                }
                
                // Toggle current dropdown
                menu.classList.toggle('show');
            }
            
            // Handle dropdown item clicks
            if (e.target.classList.contains('dropdown-item')) {
                // For button items (like edit), prevent default and call handler
                if (e.target.tagName === 'BUTTON') {
                    e.preventDefault();
                }
                // Close dropdown after click
                const menu = e.target.closest('.dropdown-menu');
                if (menu) menu.classList.remove('show');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e){
            if (!e.target.closest('.dropdown-wrapper')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
            }
        });

        // Edit document button handlers
        document.addEventListener('click', function(e){
            if (e.target.classList.contains('edit-doc-btn')) {
                e.preventDefault();
                const docId = e.target.getAttribute('data-doc-id');
                const title = e.target.getAttribute('data-title');
                const ruta = e.target.getAttribute('data-ruta');
                openEditModal(docId, title, ruta);
            }
        });

        // File handling for edit modal
        function showEditError(msg){ editFileError.textContent = msg; editFileInfo.textContent = ''; }
        function clearEditError(){ editFileError.textContent = ''; }
        function showEditFile(file){ editFileInfo.textContent = file.name + ' — ' + Math.round(file.size / 1024) + ' KB'; clearEditError(); }
        function validateEditFile(file){
            if (!file) return true; // File is optional
            if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)){ 
                return showEditError('Solo se permiten archivos PDF.'); 
            }
            if (file.size > MAX_SIZE){ 
                return showEditError('El archivo excede el tamaño máximo (10 MB).'); 
            }
            showEditFile(file);
            return true;
        }

        editDropArea && editDropArea.addEventListener('click', () => editDocFile && editDocFile.click());
        editDropArea && editDropArea.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') editDocFile && editDocFile.click(); });

        ['dragenter','dragover'].forEach(evt => { 
            editDropArea && editDropArea.addEventListener(evt, (e) => { 
                e.preventDefault(); 
                e.stopPropagation(); 
                editDropArea.classList.add('dragover'); 
            }); 
        });

        ['dragleave','drop'].forEach(evt => { 
            editDropArea && editDropArea.addEventListener(evt, (e) => { 
                e.preventDefault(); 
                e.stopPropagation(); 
                editDropArea.classList.remove('dragover'); 
            }); 
        });

        editDropArea && editDropArea.addEventListener('drop', (e) => { 
            const dt = e.dataTransfer; 
            if (!dt || !dt.files || !dt.files.length) return; 
            const file = dt.files[0]; 
            if (validateEditFile(file)){ 
                const dataTransfer = new DataTransfer(); 
                dataTransfer.items.add(file); 
                editDocFile.files = dataTransfer.files; 
            } 
        });

        editDocFile && editDocFile.addEventListener('change', () => { 
            const file = editDocFile.files[0]; 
            if (file) validateEditFile(file);
            else editFileInfo.textContent = '';
        });

        // Handle edit document form submission
        editDocForm && editDocForm.addEventListener('submit', function(e){
            e.preventDefault();
            
            const docId = editDocId.value;
            const title = editDocTitle.value.trim();
            
            if (!docId || !title) {
                alert('El título es requerido');
                return;
            }

            const data = new FormData();
            data.append('action', 'anaconda_documents_edit_doc');
            data.append('doc_id', docId);
            data.append('title', title);
            data.append('nonce', nonce);
            
            const file = editDocFile.files[0];
            if (file) {
                data.append('doc_file', file);
            }

            console.log('Submitting edit form:', {
                action: 'anaconda_documents_edit_doc',
                doc_id: docId,
                title: title,
                has_file: !!file,
                file_name: file ? file.name : null,
                nonce: nonce
            });

            fetch(ajaxUrl, {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(response => {
                console.log('Response:', response);
                if (response.success) {
                    closeEditModal();
                    // Reload page to reflect changes
                    location.reload();
                } else {
                    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'No se pudo actualizar el documento'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error al actualizar el documento');
            });
        });

        // File drag/drop + validation for create modal
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('doc_file');
        const fileInfo = document.getElementById('file-info');
        const fileError = document.getElementById('file-error');
        const submitBtn = document.getElementById('submit-btn');

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
