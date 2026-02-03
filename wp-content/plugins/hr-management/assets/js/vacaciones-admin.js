(function(){
    'use strict';

    function safeGet(obj, key, fallback) {
        try { return obj && obj[key] !== undefined ? obj[key] : fallback; } catch(e) { return fallback; }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const data = window.hrmVacacionesAdminData || {};
        const ajaxUrl = safeGet(data, 'ajaxUrl', '/wp-admin/admin-ajax.php');
        const sincronizarNonce = safeGet(data, 'sincronizarNonce', '');
        const solicitudesData = safeGet(data, 'solicitudesData', {});

        // =====================================================
        // LÓGICA DE RECHAZO DE SOLICITUDES
        // =====================================================
        setTimeout(function() {
            const botonesRechazarSolicitud = document.querySelectorAll('.btn-rechazar-solicitud');
            const botonesRechazarMedioDia = document.querySelectorAll('.btn-rechazar-medio-dia');

            function attachRejectHandler(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const motivo = prompt('Por favor ingresa el motivo del rechazo:');
                    if (motivo === null) return;
                    if (motivo.trim() === '') { alert('El motivo del rechazo es obligatorio.'); return; }
                    if (motivo.trim().length < 5) { alert('El motivo debe tener al menos 5 caracteres.'); return; }
                    const form = this.closest('form');
                    if (form) {
                        const field = form.querySelector('input[name="motivo_rechazo"]');
                        if (field) field.value = motivo;
                        form.submit();
                    }
                });
            }

            botonesRechazarSolicitud.forEach(attachRejectHandler);
            botonesRechazarMedioDia.forEach(attachRejectHandler);
        }, 100);

        // =====================================================
        // SINCRONIZACIÓN AUTOMÁTICA DE PERSONAL VIGENTE
        // =====================================================
        let ultimaSincronizacion = 0;
        const INTERVALO_MINIMO = 5000;

        function sincronizarPersonalAutomatico() {
            const ahora = Date.now();
            if (ahora - ultimaSincronizacion < INTERVALO_MINIMO) return;
            ultimaSincronizacion = ahora;

            const btnSincronizar = document.getElementById('btnSincronizarPersonal');
            if (!btnSincronizar) return;

            let nonce = btnSincronizar.getAttribute('data-nonce') || sincronizarNonce || '';

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'hrm_sincronizar_personal_vigente', nonce: nonce })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) console.warn('Error en sincronización automática:', data);
            })
            .catch(err => console.error('Error en sincronización automática:', err));
        }

        sincronizarPersonalAutomatico();
        setInterval(sincronizarPersonalAutomatico, 300000);

        // Manual sync button
        const btnSincronizar = document.getElementById('btnSincronizarPersonal');
        if (btnSincronizar) {
            btnSincronizar.addEventListener('click', function(e) {
                e.preventDefault();
                const textoOriginal = btnSincronizar.innerHTML;
                btnSincronizar.disabled = true;
                btnSincronizar.innerHTML = '<span>⏳</span> <span>Sincronizando...</span>';
                let nonce = btnSincronizar.getAttribute('data-nonce') || sincronizarNonce || '';

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'hrm_sincronizar_personal_vigente', nonce: nonce })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        btnSincronizar.classList.add('btn-success');
                        btnSincronizar.classList.remove('btn-primary');
                        btnSincronizar.innerHTML = '<span>✅</span> <span>¡Sincronizado!</span>';
                        if (res.data && res.data.detalles) {
                            const detalles = res.data.detalles.map(d => ({ nombre: d.nombre, total_empleados: d.total_empleados_activos || d.total_empleados, personal_vigente: d.personal_vigente }));
                            mostrarNotificacionExito('✅ Sincronización Exitosa','Personal vigente actualizado correctamente', detalles);
                        }
                        setTimeout(() => { btnSincronizar.classList.remove('btn-success'); btnSincronizar.classList.add('btn-primary'); btnSincronizar.innerHTML = textoOriginal; btnSincronizar.disabled = false; }, 2000);
                    } else {
                        btnSincronizar.classList.add('btn-danger');
                        btnSincronizar.classList.remove('btn-primary');
                        btnSincronizar.innerHTML = '<span>❌</span> <span>Error en sincronización</span>';
                        if (res.data && res.data.errores) mostrarNotificacionError('❌ Error en Sincronización', res.data.mensaje || 'No se pudo sincronizar el personal', res.data.errores);
                        setTimeout(() => { btnSincronizar.classList.remove('btn-danger'); btnSincronizar.classList.add('btn-primary'); btnSincronizar.innerHTML = textoOriginal; btnSincronizar.disabled = false; }, 3000);
                    }
                })
                .catch(err => {
                    console.error('Error en sincronización:', err);
                    btnSincronizar.classList.add('btn-danger'); btnSincronizar.classList.remove('btn-primary'); btnSincronizar.innerHTML = '<span>❌</span> <span>Error en solicitud</span>';
                    mostrarNotificacionError('❌ Error de Conexión','No se pudo conectar con el servidor',[err.message]);
                    setTimeout(() => { btnSincronizar.classList.remove('btn-danger'); btnSincronizar.classList.add('btn-primary'); btnSincronizar.innerHTML = textoOriginal; btnSincronizar.disabled = false; }, 3000);
                });
            });
        }

        // =====================================================
        // TABS
        // =====================================================
        const tabButtons = document.querySelectorAll('[role="tab"]');
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('aria-controls');
                const tabName = this.id.replace('tab-', '');
                tabButtons.forEach(btn => { btn.classList.remove('active'); btn.setAttribute('aria-selected', 'false'); });
                document.querySelectorAll('[role="tabpanel"]').forEach(pane => { pane.classList.remove('show','active'); });
                this.classList.add('active'); this.setAttribute('aria-selected','true');
                const targetPane = document.getElementById(targetId);
                if (targetPane) { targetPane.classList.add('show','active'); const url = new URL(window.location); url.searchParams.set('tab', tabName); window.history.replaceState({}, '', url); }
            });
        });

        // =====================================================
        // CALENDARIO + helpers
        // =====================================================
        let mesActual = new Date().getMonth();
        let anoActual = new Date().getFullYear();
        let feriados = {};
        let vacacionesAprobadas = [];
        let departamentoFiltro = '';

        function cargarVacacionesPorDepartamento(departamento) {
            return fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'hrm_get_vacaciones_calendario', departamento: departamento }) })
            .then(r => r.json()).then(res => { if (res.success) { vacacionesAprobadas = res.data; return true; } return false; }).catch(e => { console.error('Error cargando vacaciones:', e); return false; });
        }

        const selectorDepartamento = document.getElementById('filtroCalendarioDepartamento');
        if (selectorDepartamento) selectorDepartamento.addEventListener('change', function() { departamentoFiltro = this.value; cargarVacacionesPorDepartamento(departamentoFiltro).then(() => renderizarCalendario(mesActual, anoActual)); });

        function cargarFeriadosDelAno(ano) { return fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'hrm_get_feriados', ano: ano }) }) .then(r => r.json()).then(res => { if (res.success) { feriados = res.data; return true; } return false; }).catch(e => { console.error('Error cargando feriados:', e); return false; }); }

        cargarFeriadosDelAno(anoActual).then(() => { cargarVacacionesPorDepartamento(departamentoFiltro); });

        function renderizarCalendario(mes, ano) {
            const primerDia = new Date(ano, mes, 1);
            const ultimoDia = new Date(ano, mes + 1, 0);
            const diasEnMes = ultimoDia.getDate();
            let diaInicio = primerDia.getDay(); diaInicio = diaInicio === 0 ? 6 : diaInicio - 1;
            const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            if (document.getElementById('mesesTitulo')) document.getElementById('mesesTitulo').textContent = meses[mes] + ' ' + ano;
            let html = ''; let diaActual = 1; const totalCeldas = Math.ceil((diasEnMes + diaInicio) / 7) * 7;
            for (let i = 0; i < totalCeldas / 7; i++) {
                html += '<tr>';
                for (let j = 0; j < 7; j++) {
                    const indice = i * 7 + j;
                    if (indice < diaInicio || diaActual > diasEnMes) { html += '<td class="other-month"></td>'; } else {
                        const fecha = new Date(ano, mes, diaActual);
                        const fechaStr = ano + '-' + String(mes + 1).padStart(2,'0') + '-' + String(diaActual).padStart(2,'0');
                        let clasesCelda = ''; let contenido = '<span class="dia-numero">' + diaActual + '</span>';
                        let esFeriado = false; let nombreFeriado = '';
                        if (fechaStr in feriados) { esFeriado = true; nombreFeriado = feriados[fechaStr]; clasesCelda += ' feriado'; contenido += '<div class="dia-info">🎉 Feriado</div>'; }
                        if (j === 5 || j === 6) { clasesCelda += ' fin-semana'; }
                        let tieneVacaciones = false; let empleadosVacaciones = [];
                        if (!esFeriado) { for (let vac of vacacionesAprobadas) { if (fechaStr >= vac.fecha_inicio && fechaStr <= vac.fecha_fin) { tieneVacaciones = true; empleadosVacaciones.push(vac.empleado); } } if (tieneVacaciones) { clasesCelda += ' vacaciones'; contenido += '<div class="dia-info">🏖️ ' + empleadosVacaciones.length + ' empleado(s)</div>'; } }
                        const hoy = new Date(); if (fecha.toDateString() === hoy.toDateString()) { clasesCelda += ' hoy'; }
                        const titulo = esFeriado ? ` title="${nombreFeriado}"` : '';
                        html += '<td class="' + clasesCelda + '"' + titulo + '>' + contenido + '</td>';
                        diaActual++;
                    }
                }
                html += '</tr>';
            }
            if (document.getElementById('diasCalendario')) document.getElementById('diasCalendario').innerHTML = html;
        }

        const btnMesAnterior = document.getElementById('btnMesAnterior');
        if (btnMesAnterior) btnMesAnterior.addEventListener('click', function() { const anoAnterior = anoActual; mesActual--; if (mesActual < 0) { mesActual = 11; anoActual--; } if (anoActual !== anoAnterior) { cargarFeriadosDelAno(anoActual).then(() => { renderizarCalendario(mesActual, anoActual); }); } else { renderizarCalendario(mesActual, anoActual); } });

        const btnMesSiguiente = document.getElementById('btnMesSiguiente');
        if (btnMesSiguiente) btnMesSiguiente.addEventListener('click', function() { const anoAnterior = anoActual; mesActual++; if (mesActual > 11) { mesActual = 0; anoActual++; } if (anoActual !== anoAnterior) { cargarFeriadosDelAno(anoActual).then(() => { renderizarCalendario(mesActual, anoActual); }); } else { renderizarCalendario(mesActual, anoActual); } });

        // =====================================================
        // NOTIFICACIONES helpers
        // =====================================================
        function mostrarNotificacionExito(titulo, mensaje, detalles) { const notif = document.createElement('div'); notif.className = 'alert alert-success alert-dismissible fade show shadow-lg myplugin-toast'; notif.setAttribute('role','alert'); let detalleHtml = ''; if (detalles && detalles.length > 0) { detalleHtml = '<ul class="mb-0 mt-2 small">'; detalles.forEach(det => { detalleHtml += `<li>${det.nombre}: ${det.total_empleados} total, ${det.personal_vigente} vigente</li>`; }); detalleHtml += '</ul>'; } notif.innerHTML = `<div class="d-flex gap-2 align-items-start"><span class="myplugin-toast-icon">✅</span><div class="flex-grow-1"><strong>${titulo}</strong><p class="mb-0">${mensaje}</p>${detalleHtml}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`; document.body.appendChild(notif); setTimeout(() => { notif.remove(); }, 5000); }
        function mostrarNotificacionError(titulo, mensaje, errores) { const notif = document.createElement('div'); notif.className = 'alert alert-danger alert-dismissible fade show shadow-lg myplugin-toast'; notif.setAttribute('role','alert'); let erroresHtml = ''; if (errores && errores.length > 0) { erroresHtml = '<ul class="mb-0 mt-2 small">'; errores.forEach(error => { erroresHtml += `<li>${error}</li>`; }); erroresHtml += '</ul>'; } notif.innerHTML = `<div class="d-flex gap-2 align-items-start"><span class="myplugin-toast-icon">❌</span><div class="flex-grow-1"><strong>${titulo}</strong><p class="mb-0">${mensaje}</p>${erroresHtml}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`; document.body.appendChild(notif); setTimeout(() => { notif.remove(); }, 6000); }

        // =====================================================
        // MODAL COMENTARIOS
        // =====================================================
        const botonesComentarios = document.querySelectorAll('.btn-comentarios');
        const modal = document.getElementById('modalComentarios');
        const btnCerrar = document.querySelector('.modal-cerrar');
        const btnCerrarModal = document.querySelector('.btn-cerrar-modal');

        botonesComentarios.forEach(function(btn){ btn.addEventListener('click', function(){ const id = this.getAttribute('data-id'); const datos = solicitudesData[id]; if (datos){ if (document.getElementById('modalEmpleado')) document.getElementById('modalEmpleado').textContent = datos.nombre; const contenidoComentario = document.getElementById('modalComentarioContenido'); if (datos.comentario.trim() === '') { if (contenidoComentario) contenidoComentario.innerHTML = '<div class="text-muted fst-italic text-center py-4">No hay comentarios agregados</div>'; } else { if (contenidoComentario) contenidoComentario.textContent = datos.comentario; } if (modal) modal.classList.add('activo'); } }); });

        if (btnCerrar) btnCerrar.addEventListener('click', function(){ if (modal) modal.classList.remove('activo'); });
        if (btnCerrarModal) btnCerrarModal.addEventListener('click', function(){ if (modal) modal.classList.remove('activo'); });
        if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) modal.classList.remove('activo'); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal && modal.classList.contains('activo')) modal.classList.remove('activo'); });

        // =====================================================
        // MODAL RECHAZO
        // =====================================================
        const modalRechazo = document.getElementById('modalRechazo');
        const botonesModalRechazo = document.querySelectorAll('.btn-modal-rechazo');
        const btnCerrarRechazo = document.querySelector('.modal-rechazo-cerrar');
        const btnCancelarRechazo = document.querySelector('.btn-cancelar-rechazo');
        const btnConfirmarRechazo = document.querySelector('.btn-confirmar-rechazo');
        const formRechazo = document.getElementById('formRechazo');

        botonesModalRechazo.forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); const solicitudId = this.getAttribute('data-solicitud-id'); const nonce = this.getAttribute('data-nonce'); if (document.getElementById('solicitudIdRechazo')) document.getElementById('solicitudIdRechazo').value = solicitudId; if (document.getElementById('nonceRechazo')) document.getElementById('nonceRechazo').value = nonce; if (document.getElementById('motivoRechazo')) document.getElementById('motivoRechazo').value = ''; if (modalRechazo) modalRechazo.classList.add('activo'); }); });
        if (btnCerrarRechazo) btnCerrarRechazo.addEventListener('click', function(){ if (modalRechazo) modalRechazo.classList.remove('activo'); });
        if (btnCancelarRechazo) btnCancelarRechazo.addEventListener('click', function(){ if (modalRechazo) modalRechazo.classList.remove('activo'); });
        if (modalRechazo) modalRechazo.addEventListener('click', function(e){ if (e.target === modalRechazo) modalRechazo.classList.remove('activo'); });
        if (btnConfirmarRechazo) btnConfirmarRechazo.addEventListener('click', function(){ const motivo = (document.getElementById('motivoRechazo') || {}).value || ''; if (motivo.trim() === ''){ alert('Por favor ingresa un motivo para el rechazo.'); return; } if (motivo.length < 5){ alert('El motivo debe tener al menos 5 caracteres.'); return; } if (formRechazo) formRechazo.submit(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modalRechazo && modalRechazo.classList.contains('activo')) modalRechazo.classList.remove('activo'); });

        // =====================================================
        // MODAL VACACIONES DETALLE
        // =====================================================
        const modalVacacionesDetalle = document.getElementById('modalVacacionesDetalle');
        const botonesVacacionesDetalle = document.querySelectorAll('.btn-vacaciones-detalle');
        const btnCerrarVacaciones = document.querySelectorAll('.modal-vacaciones-cerrar');

        botonesVacacionesDetalle.forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); const departamento = this.getAttribute('data-departamento'); if (document.getElementById('modalVacacionesContenido')) document.getElementById('modalVacacionesContenido').innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm me-2"></span> Cargando información...</div>'; if (modalVacacionesDetalle) modalVacacionesDetalle.style.display = 'flex'; fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'hrm_get_empleados_vacaciones_hoy', departamento: departamento }) }).then(r => r.json()).then(data => { if (data.success && data.data.length > 0) { let html = '<div class="list-group">'; data.data.forEach(function(empleado){ const [year, month, day] = empleado.fecha_inicio.split('-'); const fechaInicio = new Date(year, month-1, day); const [yearFin, monthFin, dayFin] = empleado.fecha_fin.split('-'); const fechaFin = new Date(yearFin, monthFin-1, dayFin); const opciones = { year: 'numeric', month: 'long', day: 'numeric' }; const fechaInicioFormato = fechaInicio.toLocaleDateString('es-ES', opciones); const fechaFinFormato = fechaFin.toLocaleDateString('es-ES', opciones); html += '<div class="list-group-item px-3 py-3 border-bottom">'; html += '<h6 class="fw-bold text-dark mb-1">' + empleado.nombre + '</h6>'; html += '<small class="text-muted d-block">📅 ' + fechaInicioFormato + ' hasta ' + fechaFinFormato + '</small>'; html += '</div>'; }); html += '</div>'; if (document.getElementById('modalVacacionesContenido')) document.getElementById('modalVacacionesContenido').innerHTML = html; } else { if (document.getElementById('modalVacacionesContenido')) document.getElementById('modalVacacionesContenido').innerHTML = '<div class="alert alert-info">No hay empleados en vacaciones hoy</div>'; } }).catch(err => { console.error('Error:', err); if (document.getElementById('modalVacacionesContenido')) document.getElementById('modalVacacionesContenido').innerHTML = '<div class="alert alert-danger">Error al cargar la información</div>'; }); }); });

        btnCerrarVacaciones.forEach(function(btn){ btn.addEventListener('click', function(){ if (modalVacacionesDetalle) modalVacacionesDetalle.style.display = 'none'; }); });
        if (modalVacacionesDetalle) modalVacacionesDetalle.addEventListener('click', function(e){ if (e.target === modalVacacionesDetalle) modalVacacionesDetalle.style.display = 'none'; });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modalVacacionesDetalle && modalVacacionesDetalle.style.display === 'flex') modalVacacionesDetalle.style.display = 'none'; });

        // =====================================================
        // MEDIO DÍA APPROVE/REJECT Logic
        // =====================================================
        const modalVerMedioDia = document.getElementById('modalVerMedioDia');
        const modalRechazoMedioDia = document.getElementById('modalRechazoMedioDia');
        const botonesVerMedioDia = document.querySelectorAll('.btn-ver-medio-dia');
        const botonesRechazarMd = document.querySelectorAll('.btn-modal-rechazo-md');
        const btnCerrarMd = document.querySelectorAll('.btn-cerrar-md');
        const btnCerrarRechazoMd = document.querySelectorAll('.btn-cerrar-rechazo-md');
        const btnCancelarRechazoMd = document.querySelectorAll('.btn-cancelar-rechazo-md');
        const btnConfirmarRechazoMd = document.querySelector('.btn-confirmar-rechazo-md');

        botonesVerMedioDia.forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); const id = this.getAttribute('data-id'); const emp = this.getAttribute('data-employee'); const fecha = this.getAttribute('data-fecha'); if (modalVerMedioDia) modalVerMedioDia.style.display = 'flex'; if (document.getElementById('modalVerMdContenido')) document.getElementById('modalVerMdContenido').innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm me-2"></span> Cargando...</div>'; fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'hrm_get_medio_dia_details', id: id }) }).then(r => r.json()).then(json => { if (json.success) { const data = json.data; const html = '<h5>' + emp + ' — ' + fecha + '</h5>' + '<p>' + (data.comentario || 'Sin comentario') + '</p>'; if (document.getElementById('modalVerMdContenido')) document.getElementById('modalVerMdContenido').innerHTML = html; } else { if (document.getElementById('modalVerMdContenido')) document.getElementById('modalVerMdContenido').innerHTML = '<div class="alert alert-info">Detalle no disponible</div>'; } }).catch(err => { console.error(err); if (document.getElementById('modalVerMdContenido')) document.getElementById('modalVerMdContenido').innerHTML = '<div class="alert alert-danger">Error al cargar detalle</div>'; }); }); });

        btnCerrarMd.forEach(function(btn){ btn.addEventListener('click', function(){ if (modalVerMedioDia) modalVerMedioDia.style.display = 'none'; }); });
        if (modalVerMedioDia) modalVerMedioDia.addEventListener('click', function(e){ if (e.target === modalVerMedioDia) modalVerMedioDia.style.display = 'none'; });

        botonesRechazarMd.forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); const id = this.getAttribute('data-id'); const nonce = this.getAttribute('data-nonce'); if (document.getElementById('solicitudIdRechazoMd')) document.getElementById('solicitudIdRechazoMd').value = id; if (document.getElementById('nonceRechazoMd')) document.getElementById('nonceRechazoMd').value = nonce; if (document.getElementById('motivoRechazoMd')) document.getElementById('motivoRechazoMd').value = ''; if (modalRechazoMedioDia) modalRechazoMedioDia.style.display = 'flex'; }); });

        btnCerrarRechazoMd.forEach(function(btn){ btn.addEventListener('click', function(){ if (modalRechazoMedioDia) modalRechazoMedioDia.style.display = 'none'; }); });
        if (modalRechazoMedioDia) modalRechazoMedioDia.addEventListener('click', function(e){ if (e.target === modalRechazoMedioDia) modalRechazoMedioDia.style.display = 'none'; });
        btnCancelarRechazoMd.forEach(function(btn){ btn.addEventListener('click', function(){ if (modalRechazoMedioDia) modalRechazoMedioDia.style.display = 'none'; }); });
        if (btnConfirmarRechazoMd) btnConfirmarRechazoMd.addEventListener('click', function(){ const motivo = (document.getElementById('motivoRechazoMd') || {}).value || ''; if (motivo.trim() === ''){ alert('Por favor ingresa un motivo para el rechazo.'); return; } if (motivo.length < 5){ alert('El motivo debe tener al menos 5 caracteres.'); return; } const form = document.getElementById('formRechazoMedioDia'); if (form) form.submit(); });

    });
})();
