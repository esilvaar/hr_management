(function(){
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // Preview
        (function(){
            const previewPanel  = document.getElementById('hrm-preview-panel');
            const previewIframe = document.getElementById('hrm-preview-iframe');
            const closeBtn      = document.getElementById('btn-cerrar-preview');

            if (!previewPanel || !previewIframe) return;

            document.querySelectorAll('.btn-preview-doc').forEach(btn => {
                btn.addEventListener('click', function () {
                    const url = this.dataset.url;
                    if (!url) return;

                    previewIframe.src = url;
                    previewPanel.classList.remove('d-none');

                    setTimeout(() => {
                        previewPanel.scrollIntoView({ behavior: 'smooth' });
                    }, 50);
                });
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    previewPanel.classList.add('d-none');
                    previewIframe.src = '';
                });
            }
        })();

        // Filtrado por año
        (function(){
            const yearSelect = document.getElementById('hrm-mis-year-select');
            const container = document.getElementById('hrm-mis-documents-container');
            if (!yearSelect || !container) return;

            function filterRowsByYear(){
                const val = String(yearSelect.value);
                const rows = container.querySelectorAll('table tbody tr[data-year]');
                let visible = 0;
                rows.forEach(r => {
                    if (!val || String(r.dataset.year) === val) { r.style.display = ''; visible++; } else { r.style.display = 'none'; }
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
            filterRowsByYear();
            try { container.classList.remove('d-none'); } catch(e) { /* no-op */ }
        })();

        // Descargas: manejar menú de descarga (última/3/6/todas)
        (function(){
            const btn = document.getElementById('hrm-mis-download-btn');
            const menu = document.getElementById('hrm-mis-download-menu');
            const yearSelect = document.getElementById('hrm-mis-year-select');
            const employeeId = (window.hrmMisDocsData && window.hrmMisDocsData.employeeId) ? parseInt(window.hrmMisDocsData.employeeId,10) : 0;
            const ajaxUrl = (window.hrmMisDocsData && window.hrmMisDocsData.ajaxUrl) ? String(window.hrmMisDocsData.ajaxUrl) : '';
            if (!btn || !menu || !yearSelect) return;

            const menuItems = menu.querySelectorAll('a[data-cantidad]');
            function updateDownloadMenuVisibility(){
                const year = yearSelect.value || '';
                const rows = document.querySelectorAll('#hrm-mis-documents-container table tbody tr');
                let count = 0;
                rows.forEach(function(r){ if (!year || String(r.dataset.year) === String(year)) count++; });

                menuItems.forEach(function(mi){
                    const li = mi.closest('li') || mi;
                    const c = mi.getAttribute('data-cantidad');
                    if (c === 'all') {
                        li.style.display = (count > 0) ? 'block' : 'none';
                    } else {
                        const n = parseInt(c,10) || 0;
                        li.style.display = (count >= n) ? 'block' : 'none';
                    }
                });

                const visibleCount = Array.from(menuItems).filter(m => {
                    const li = m.closest('li') || m;
                    return li.style.display !== 'none';
                }).length;

                btn.disabled = visibleCount === 0;
            }

            yearSelect.addEventListener('change', updateDownloadMenuVisibility);

            menuItems.forEach(function(a){
                a.addEventListener('click', function(e){
                    e.preventDefault();
                    const cantidad = this.getAttribute('data-cantidad');
                    const year = yearSelect.value || '';
                    let url = ajaxUrl;
                    if ( url.indexOf('?') === -1 ) url += '?'; else url += '&';
                    url += 'action=hrm_descargar_liquidaciones&cantidad=' + encodeURIComponent(cantidad);
                    if ( year ) url += '&year=' + encodeURIComponent(year);
                    if ( employeeId ) url += '&employee_id=' + encodeURIComponent(employeeId);
                    window.open(url, '_blank');
                    try { const bsDropdown = bootstrap.Dropdown.getInstance(btn) || new bootstrap.Dropdown(btn); bsDropdown.hide(); } catch(e) { /* no-op */ }
                });
            });

            try { updateDownloadMenuVisibility(); } catch(e) { /* no-op */ }
        })();

    });
})();
