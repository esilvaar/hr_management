document.addEventListener('DOMContentLoaded', () => {
    const modals = document.querySelectorAll('.hrm-detach-modal');

    modals.forEach((modal) => {
        if (modal && !modal.classList.contains('hrm-detached')) {
            document.body.appendChild(modal);
            modal.classList.add('hrm-detached');
        }
    });

    // Debug: permisos enviados desde PHP (solo si existe)
    if ( window.hrmPermDebug ) {
        console.log('[HRM-PERM]', window.hrmPermDebug);
    }
});

/**
 * Gestión de campos dinámicos y departamentos a cargo
 */
document.addEventListener('DOMContentLoaded', function() {
    const departamentoSelect = document.getElementById('departamento');
    const puestoSelect = document.getElementById('puesto');
    const areaGerenciaSelect = document.getElementById('area_gerencia');
    const areaGerenciaContainer = areaGerenciaSelect ? areaGerenciaSelect.closest('.col-md-6') : null;
    const deptosCargoContainer = document.getElementById('deptos_a_cargo_container');
    const deptosCheckboxesDiv = document.getElementById('deptos_checkboxes');
    
    // Lista de todos los departamentos disponibles
    const todosDeptos = hrmEmployeeData.departamentos || [];
    
    // Mapeo de áreas gerenciales con sus departamentos predefinidos
    const deptosPredefinidos = {
        'comercial': ['Soporte', 'Ventas'],
        'proyectos': ['Desarrollo'],
        'operaciones': ['Administracion', 'Gerencia', 'Sistemas']
    };
    
    /**
     * Cargar departamentos ya asignados para esta área
     */
    async function loadAsignedDeparments() {
        const areaValue = areaGerenciaSelect.value;
        if ( areaValue === '' ) {
            return [];
        }
        
        try {
            const response = await fetch(hrmEmployeeData.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=hrm_get_deptos_area&area_gerencial=' + encodeURIComponent(areaValue)
            });
            
            const data = await response.json();
            return data.success ? data.data : [];
        } catch(error) {
            // Error cargando departamentos (silenciado en producción)
            console.error('Error cargando departamentos:', error);
            return [];
        }
    }
    
    /**
     * Cargar y generar checkboxes de departamentos
     */
    async function loadDepartamentosCheckboxes() {
        const areaValue = areaGerenciaSelect.value.toLowerCase().trim();
        
        if ( areaValue === '' ) {
            deptosCheckboxesDiv.innerHTML = '';
            return;
        }
        
        // Obtener departamentos predefinidos para este área
        const deptosAMarcar = deptosPredefinidos[areaValue] || [];
        
        // Obtener departamentos ya asignados (desde la BD)
        const asignados = await loadAsignedDeparments();
        
        // Generar checkboxes para todos los departamentos (excepto Gerencia)
        let html = '';
        todosDeptos.forEach( function( depto, index ) {
            // Excluir Gerencia de los checkboxes
            if ( depto.toLowerCase() === 'gerencia' ) {
                return;
            }
            
            // Verificar si este departamento debe estar marcado:
            // 1. Si ya está asignado en la BD, marcarlo
            // 2. Si es un nuevo gerente, marcar los predefinidos
            const estaAsignado = asignados.includes(depto);
            const estaPredefinido = deptosAMarcar.some( d => d.toLowerCase() === depto.toLowerCase() );
            const checked = (estaAsignado || estaPredefinido) ? 'checked' : '';
            
            const id = 'depto_checkbox_' + index;
            html += `
                <div class="form-check">
                    <input class="form-check-input hrm_depto_checkbox" type="checkbox" name="deptos_a_cargo[]" 
                           value="${depto}" id="${id}" ${checked}>
                    <label class="form-check-label" for="${id}">
                        ${depto}
                    </label>
                </div>
            `;
        });
        
        deptosCheckboxesDiv.innerHTML = html;
    }
    
    /**
     * Alternar visibilidad de área de gerencia según puesto/departamento
     */
    function toggleAreaGerencia() {
        const puestoValue = (puestoSelect && puestoSelect.value) ? puestoSelect.value.toLowerCase().trim() : '';
        const deptoValue = (departamentoSelect && departamentoSelect.value) ? departamentoSelect.value.toLowerCase().trim() : '';
        
        // Mostrar área de gerencia y departamentos solo si:
        // 1. El puesto es "Gerente" O
        // 2. El departamento es "Gerencia"
        const esGerente = puestoValue === 'gerente' || deptoValue === 'gerencia';
        
        if ( esGerente ) {
            if ( areaGerenciaContainer ) areaGerenciaContainer.style.display = 'block';
            // Si hay un área seleccionada, mostrar los checkboxes
            if ( areaGerenciaSelect && areaGerenciaSelect.value !== '' ) {
                if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'block';
                loadDepartamentosCheckboxes();
            }
        } else {
            if ( areaGerenciaContainer ) areaGerenciaContainer.style.display = 'none';
            if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'none';
            if ( areaGerenciaSelect ) areaGerenciaSelect.value = '';
            if ( deptosCheckboxesDiv ) deptosCheckboxesDiv.innerHTML = '';
        }
    }
    
    // Event listeners
    if ( departamentoSelect ) departamentoSelect.addEventListener('change', toggleAreaGerencia);
    if ( puestoSelect ) puestoSelect.addEventListener('change', toggleAreaGerencia);
    
    if ( areaGerenciaSelect ) {
        areaGerenciaSelect.addEventListener('change', function() {
            const puestoValue = puestoSelect ? puestoSelect.value.toLowerCase().trim() : '';
            const deptoValue = departamentoSelect ? departamentoSelect.value.toLowerCase().trim() : '';
            const esGerente = puestoValue === 'gerente' || deptoValue === 'gerencia';
            
            if ( esGerente ) {
                if ( areaGerenciaSelect.value !== '' ) {
                    if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'block';
                    loadDepartamentosCheckboxes();
                } else {
                    if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'none';
                    if ( deptosCheckboxesDiv ) deptosCheckboxesDiv.innerHTML = '';
                }
            }
        });
    }
    
    // Inicializar
    toggleAreaGerencia();
});

// Small helpers: select-on-click and avatar input submit
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.hrm-select-on-click').forEach(function(el){ el.addEventListener('click', function(){ this.select(); }); });
    document.querySelectorAll('.hrm-avatar-input').forEach(function(el){ el.addEventListener('change', function(){ this.form.submit(); }); });
});

// Employee detail: antiguedad, password modal, toggle estado y color de iconos
document.addEventListener('DOMContentLoaded', function() {
    // 1) Cálculo de años de antigüedad
    const fi = document.getElementById('fecha_ingreso');
    const ap = document.getElementById('anos_acreditados_anteriores');
    const ae = document.getElementById('anos_en_la_empresa');
    const at = document.getElementById('anos_totales_trabajados');
    const h_ae = document.getElementById('hrm_anos_en_la_empresa_hidden');
    const h_at = document.getElementById('hrm_anos_totales_trabajados_hidden');

    function calcular() {
        if(!fi || !fi.value) return;
        const ingreso = new Date(fi.value + 'T00:00:00');
        const hoy = new Date();
        if(ingreso > hoy) { if(ae) ae.value = 0; return; }
        const diff = hoy - ingreso;
        const anos = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
        if (ae) ae.value = anos;
        if (h_ae) h_ae.value = anos;
        const previos = parseFloat(ap ? ap.value : 0) || 0;
        const total = anos + previos;
        if (at) at.value = total;
        if (h_at) h_at.value = total;
    }

    if(fi) fi.addEventListener('change', calcular);
    if(ap) ap.addEventListener('input', calcular);
    calcular();

    // 2) Modal contraseña
    const openBtn = document.getElementById('hrm-open-pass-modal');
    const panel = document.getElementById('hrm-pass-panel');
    const closeBtn = document.getElementById('hrm-close-pass-panel');
    const cancelBtn = document.getElementById('hrm_panel_cancel');
    const saveModalBtn = document.getElementById('hrm_panel_save');
    const inputNew = document.getElementById('hrm_panel_new_password');
    const inputConfirm = document.getElementById('hrm_panel_confirm_password');
    const inputNotify = document.getElementById('hrm_panel_notify_user');
    const feedback = document.getElementById('hrm_panel_pass_feedback');
    const hiddenPass = document.getElementById('hrm_new_password');
    const hiddenConf = document.getElementById('hrm_confirm_password');
    const hiddenNotify = document.getElementById('hrm_notify_user');
    const mainForm = document.querySelector('form[name="hrm_update_employee_form"]');

    if(openBtn) {
        openBtn.addEventListener('click', function(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();
            if (panel) panel.style.display = 'block';
            if(inputNew) inputNew.value = '';
            if(inputConfirm) inputConfirm.value = '';
            if(feedback) feedback.style.display = 'none';
        });
    }
    function closePanel() { if (panel) panel.style.display = 'none'; }
    if(closeBtn) closeBtn.addEventListener('click', closePanel);
    if(cancelBtn) cancelBtn.addEventListener('click', closePanel);

    if(saveModalBtn) {
        saveModalBtn.addEventListener('click', function() {
            const pass = inputNew ? inputNew.value.trim() : '';
            const conf = inputConfirm ? inputConfirm.value.trim() : '';

            if(pass.length < 8) {
                if(feedback){ feedback.textContent = 'La contraseña debe tener al menos 8 caracteres.'; feedback.style.display = 'block'; }
                return;
            }
            if(pass !== conf) {
                if(feedback){ feedback.textContent = 'Las contraseñas no coinciden.'; feedback.style.display = 'block'; }
                return;
            }

            if(hiddenPass) hiddenPass.value = pass;
            if(hiddenConf) hiddenConf.value = conf;
            if(inputNotify && hiddenNotify) hiddenNotify.value = inputNotify.checked ? '1' : '0';
            if(mainForm) mainForm.submit();
        });
    }

    // 3) Toggle estado
    const btnDes = document.getElementById('btn-desactivar-empleado');
    const btnAct = document.getElementById('btn-activar-empleado');
    const togglePanel = document.getElementById('hrm-toggle-panel');
    const btnCancelToggle = document.getElementById('btn-cancelar-toggle');
    const toggleTitle = document.getElementById('hrm-toggle-title');
    const toggleMsg = document.getElementById('hrm-toggle-msg');
    const toggleConfirmBtn = document.getElementById('btn-confirmar-toggle');
    const inputEstado = document.getElementById('input-current-estado');

    if (btnDes) {
        btnDes.addEventListener('click', function(){
            if(inputEstado) inputEstado.value = '1';
            if(toggleTitle) { toggleTitle.innerHTML = 'Desactivar Empleado'; toggleTitle.className = 'mb-3 text-danger'; }
            if(toggleMsg) toggleMsg.innerHTML = '¿Seguro que deseas bloquear el acceso a este empleado?';
            if(toggleConfirmBtn) toggleConfirmBtn.className = 'btn btn-danger';
            if(togglePanel) togglePanel.style.display = 'block';
        });
    }
    if (btnAct) {
        btnAct.addEventListener('click', function(){
            if(inputEstado) inputEstado.value = '0';
            if(toggleTitle) { toggleTitle.innerHTML = 'Activar Empleado'; toggleTitle.className = 'mb-3 text-success'; }
            if(toggleMsg) toggleMsg.innerHTML = '¿Reactivar acceso al sistema?';
            if(toggleConfirmBtn) toggleConfirmBtn.className = 'btn btn-success';
            if(togglePanel) togglePanel.style.display = 'block';
        });
    }
    if (btnCancelToggle) btnCancelToggle.addEventListener('click', function(){ if(togglePanel) togglePanel.style.display = 'none'; });

    // 4) Colorear iconos documentos
    const ENFORCED_DOC_ICON = '#b0b5bd';
    function applyDocIconColor(el) {
        const icon = el.querySelector('.hrm-doc-btn-icon');
        if ( icon ) {
            try { el.style.setProperty('--hrm-doc-icon', ENFORCED_DOC_ICON); } catch (e) {}
            try { icon.style.backgroundColor = ENFORCED_DOC_ICON; } catch (e) {}
            try { el.setAttribute('data-icon-color', ENFORCED_DOC_ICON); } catch (e) {}
        }
    }
    document.querySelectorAll('.hrm-doc-btn').forEach(applyDocIconColor);

    const observer = new MutationObserver(mutations => {
        for (const m of mutations) {
            if (!m.addedNodes || !m.addedNodes.length) continue;
            m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.classList && node.classList.contains('hrm-doc-btn')) applyDocIconColor(node);
                const children = node.querySelectorAll ? node.querySelectorAll('.hrm-doc-btn') : [];
                children.forEach(ch => applyDocIconColor(ch));
            });
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });
});
