document.addEventListener('DOMContentLoaded', () => {
    const modals = document.querySelectorAll('.hrm-detach-modal');

    modals.forEach((modal) => {
        if (modal && !modal.classList.contains('hrm-detached')) {
            document.body.appendChild(modal);
            modal.classList.add('hrm-detached');
        }
    });
});

/**
 * Gestión de campos dinámicos y departamentos a cargo
 */
document.addEventListener('DOMContentLoaded', function() {
    const departamentoSelect = document.getElementById('departamento');
    const puestoSelect = document.getElementById('puesto');
    const areaGerenciaSelect = document.getElementById('area_gerencia');
    const areaGerenciaContainer = areaGerenciaSelect.closest('.col-md-6');
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
            console.log('Error cargando departamentos:', error);
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
        const puestoValue = puestoSelect.value.toLowerCase().trim();
        const deptoValue = departamentoSelect.value.toLowerCase().trim();
        
        // Mostrar área de gerencia y departamentos solo si:
        // 1. El puesto es "Gerente" O
        // 2. El departamento es "Gerencia"
        const esGerente = puestoValue === 'gerente' || deptoValue === 'gerencia';
        
        if ( esGerente ) {
            areaGerenciaContainer.style.display = 'block';
            // Si hay un área seleccionada, mostrar los checkboxes
            if ( areaGerenciaSelect.value !== '' ) {
                deptosCargoContainer.style.display = 'block';
                loadDepartamentosCheckboxes();
            }
        } else {
            areaGerenciaContainer.style.display = 'none';
            deptosCargoContainer.style.display = 'none';
            areaGerenciaSelect.value = '';
            deptosCheckboxesDiv.innerHTML = '';
        }
    }
    
    // Event listeners
    departamentoSelect.addEventListener('change', toggleAreaGerencia);
    puestoSelect.addEventListener('change', toggleAreaGerencia);
    
    areaGerenciaSelect.addEventListener('change', function() {
        const puestoValue = puestoSelect.value.toLowerCase().trim();
        const deptoValue = departamentoSelect.value.toLowerCase().trim();
        const esGerente = puestoValue === 'gerente' || deptoValue === 'gerencia';
        
        if ( esGerente ) {
            if ( areaGerenciaSelect.value !== '' ) {
                deptosCargoContainer.style.display = 'block';
                loadDepartamentosCheckboxes();
            } else {
                deptosCargoContainer.style.display = 'none';
                deptosCheckboxesDiv.innerHTML = '';
            }
        }
    });
    
    // Inicializar
    toggleAreaGerencia();
});
