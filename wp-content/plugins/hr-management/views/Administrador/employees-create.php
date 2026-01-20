<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Esta vista muestra el formulario para crear un nuevo empleado
// Variables disponibles: $hrm_departamentos, $hrm_puestos, $hrm_tipos_contrato

// Obtener los roles permitidos de forma segura
$wp_roles = function_exists( 'hrm_get_allowed_employee_roles' ) ? hrm_get_allowed_employee_roles() : wp_roles()->get_names();
?>

<div class="d-flex justify-content-center">
    <div class="rounded shadow-sm w-100">
        
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center p-3 bg-dark text-white rounded-top">
            <h2 class="mb-0">Crear Nuevo Empleado</h2>
        </div>
        
        <div class="card-body p-4">

            <form method="post">
                <?php wp_nonce_field( 'hrm_create_employee', 'hrm_create_employee_nonce' ); ?>
                <input type="hidden" name="hrm_action" value="create_employee">
                <!-- Hidden inputs para guardar los años calculados -->
                <input type="hidden" id="hrm_anos_en_la_empresa_hidden" name="anos_en_la_empresa" value="0">
                <input type="hidden" id="hrm_anos_totales_trabajados_hidden" name="anos_totales_trabajados" value="0">
                
                <!-- SECCIÓN 1: DATOS REQUERIDOS -->
                <fieldset class="hrm-form-section mb-4">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hrm_rut" class="form-label">RUT <span class="text-danger">*</span></label>
                            <input id="hrm_rut" name="rut" type="text" placeholder="12345678-9" class="form-control" required>
                            <div id="hrm_rut_feedback" class="mt-2" style="display: none;"></div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="hrm_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input id="hrm_email" name="email" type="email" class="form-control" required title="Ingresa un correo electrónico válido">
                            <div id="hrm_email_feedback" class="mt-2" style="display: none;"></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hrm_nombre" class="form-label">Nombres <span class="text-danger">*</span></label>
                            <input id="hrm_nombre" name="nombre" type="text" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="hrm_apellido" class="form-label">Apellidos <span class="text-danger">*</span></label>
                            <input id="hrm_apellido" name="apellido" type="text" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                                    <label for="hrm_fecha_ingreso" class="form-label">Fecha de Ingreso <span class="text-danger">*</span></label>
                                    <input id="hrm_fecha_ingreso" name="fecha_ingreso" type="date" class="form-control" title="Fecha de ingreso a la empresa" required>
                        </div>
                    </div>
                </fieldset>
                
                <!-- SECCIÓN 2: INFORMACIÓN LABORAL -->
                <fieldset class="hrm-form-section mb-4">
                    <legend class="text-primary fs-5 pb-2 mb-3">
                        <button type="button" id="hrm_toggle_options" class="btn btn-sm btn-outline-secondary float-end">
                            <i class="dashicons dashicons-arrow-down"></i> Más opciones
                        </button>
                    </legend>
                    
                    <div id="hrm_more_options" style="display:none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="hrm_telefono" class="form-label">Teléfono</label>
                                <input id="hrm_telefono" name="telefono" type="text" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="hrm_fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input id="hrm_fecha_nacimiento" name="fecha_nacimiento" type="date" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row border-top pt-3">
                            <strong>Información Laboral</strong>
                            <div class="row mt-3 mb-3">
                                <div class="col-md-6">
                                    <label for="hrm_departamento" class="form-label">Departamento</label>
                                    <select id="hrm_departamento" name="departamento" class="form-select">
                                        <option value="">Selecciona...</option>
                                        <?php foreach ( $hrm_departamentos as $dept ) : ?>
                                            <option value="<?= esc_attr( $dept ) ?>"><?= esc_html( $dept ) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                </div>
                                <div class="col-md-6">
                                    <label for="hrm_puesto" class="form-label">Puesto</label>
                                    <select id="hrm_puesto" name="puesto" class="form-select">
                                        <option value="">Selecciona...</option>
                                        <?php foreach ( $hrm_puestos as $puesto ) : ?>
                                            <option value="<?= esc_attr( $puesto ) ?>"><?= esc_html( $puesto ) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                     
                                </div>
                                
                            </div>
                            

                            <div id="area_gerencia_section" class="col-md-6 mb-3" style="display:none;">
                                <label for="hrm_area_gerencia" class="form-label">Área de Gerencia</label>
                                <select id="hrm_area_gerencia" name="area_gerencia" class="form-select">
                                    <option value="">Selecciona...</option>
                                    <option value="Proyectos">Proyectos</option>
                                    <option value="Comercial">Comercial</option>
                                    <option value="Operaciones">Operaciones</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                            </div>
                            
                            <script>
                            document.addEventListener('DOMContentLoaded', function () {

                                const departamento = document.getElementById('hrm_departamento');
                                const puesto = document.getElementById('hrm_puesto');

                                if (!departamento || !puesto) return;

                                // Guardar todas las opciones originales
                                const opcionesOriginales = Array.from(puesto.options).map(opt => ({
                                    value: opt.value,
                                    text: opt.text
                                }));

                                // Mapeo EXACTO departamento → puestos
                                const mapaPuestos = {
                                    'soporte': [
                                        'Ingeniero de Soporte',
                                        'Practicante'
                                    ],
                                    'desarrollo': [
                                        'Desarrollador de Software',
                                        'Diseñador Gráfico'
                                    ],
                                    'ventas': [
                                        'Asistente Comercial'
                                    ],
                                    'administracion': [
                                        'Administrativo(a) Contable'
                                    ],
                                    'gerencia': [
                                        'Gerente'
                                    ],
                                    'sistemas': [
                                        'Ingeniero en Sistemas'
                                    ]
                                };

                                /**
                                 * Agrega un puesto al select si existe
                                 */
                                function agregarPuesto(nombre) {
                                    const opt = opcionesOriginales.find(o => o.text === nombre);
                                    if (!opt) return;

                                    const option = document.createElement('option');
                                    option.value = opt.value;
                                    option.text = opt.text;
                                    puesto.appendChild(option);
                                }

                                /**
                                 * Restaura todos los puestos
                                 */
                                function restaurarTodos() {
                                    opcionesOriginales.forEach((opt, idx) => {
                                        if (idx === 0) return;
                                        const option = document.createElement('option');
                                        option.value = opt.value;
                                        option.text = opt.text;
                                        puesto.appendChild(option);
                                    });
                                }

                                /**
                                 * Filtra los puestos según el departamento
                                 */
                                function filtrarPuestos() {

                                    const depto = departamento.value.toLowerCase().trim();

                                    // Limpiar select
                                    puesto.innerHTML = '';

                                    // Opción por defecto
                                    const optDefault = document.createElement('option');
                                    optDefault.value = '';
                                    optDefault.text = 'Selecciona...';
                                    puesto.appendChild(optDefault);

                                    // Si existe mapeo, usarlo
                                    if (mapaPuestos[depto]) {
                                        mapaPuestos[depto].forEach(nombrePuesto => {
                                            agregarPuesto(nombrePuesto);
                                        });
                                        return;
                                    }

                                    // Si no hay mapeo → mostrar todos
                                    restaurarTodos();
                                }

                                // Evento
                                departamento.addEventListener('change', filtrarPuestos);

                                // Inicializar
                                filtrarPuestos();
                            });
                            </script>

                        </div>

                        <!-- Sección: Departamentos a cargo (solo para gerentes) -->
                        <div id="hrm_deptos_a_cargo_container" style="display: none;">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label"><strong>Departamentos a Cargo</strong></label>
                                    <div id="hrm_deptos_checkboxes" class="border p-3 rounded">
                                        <!-- Los checkboxes se cargarán dinámicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="hrm_tipo_contrato" class="form-label">Tipo de Contrato</label>
                                <select id="hrm_tipo_contrato" name="tipo_contrato" class="form-select">
                                    <option value="">Selecciona...</option>
                                    <?php if ( ! empty( $hrm_tipos_contrato ) ) : ?>
                                        <?php foreach ( $hrm_tipos_contrato as $contrato ) : ?>
                                            <option value="<?= esc_attr( $contrato ) ?>"><?= esc_html( $contrato ) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="hrm_salario" class="form-label">Salario</label>
                                <input id="hrm_salario" name="salario" type="number" step="0.01" class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <!-- SECCIÓN: ANTIGÜEDAD LABORAL -->
                        <hr class="my-4">
                        <fieldset class="hrm-form-section mb-4">
                            <legend class="h5 mb-3">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                Antigüedad Laboral
                            </legend>
                            <div class="alert alert-info border-start border-4 border-info mb-3">
                                <small><strong>Nota:</strong> Los años en la empresa se actualizan automáticamente cada aniversario de la fecha de ingreso. El total de años trabajados es la suma de años anteriores + años en la empresa.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hrm_anos_acreditados_anteriores" class="form-label">Años Acreditados Anteriores</label>
                                    <input id="hrm_anos_acreditados_anteriores" name="anos_acreditados_anteriores" type="number" step="0.5" min="0" value="0" class="form-control" title="Años de experiencia en otras empresas">
                                    <small class="form-text text-muted d-block mt-1">Experiencia laboral en otras empresas antes de entrar aquí (opcional)</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hrm_anos_en_la_empresa" class="form-label">Años en la Empresa</label>
                                    <div class="input-group">
                                        <input id="hrm_anos_en_la_empresa" name="anos_en_la_empresa" type="number" step="0.1" min="0" value="0" class="form-control" readonly title="Se calcula automáticamente">
                                        <span class="input-group-text"><small>años</small></span>
                                    </div>
                                    <small class="form-text text-muted d-block mt-1">Se actualiza automáticamente</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="hrm_anos_totales_trabajados" class="form-label">Total de Años Trabajados</label>
                                    <div class="input-group">
                                        <input id="hrm_anos_totales_trabajados" name="anos_totales_trabajados" type="number" step="0.1" min="0" value="0" class="form-control" readonly title="Suma de años anteriores + años en la empresa">
                                        <span class="input-group-text"><small>años</small></span>
                                    </div>
                                    <small class="form-text text-muted d-block mt-1">Cálculo automático</small>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </fieldset>

                <!-- SECCIÓN 3: USUARIO WORDPRESS -->
                <fieldset class="hrm-form-section mb-4 border-top pt-4">
                    <legend class="text- fs-5 pb-2 mb-3">
                        <strong>Crear Usuario WordPress</strong>
                    </legend>

                    <div class="mb-3">
                        <div class="d-flex align-items-center form-check">
                            <input
                                type="checkbox"
                                name="crear_usuario_wp"
                                id="hrm_crear_usuario_wp"
                                class="form-check-input"
                            >
                            <label class="form-check-label" for="hrm_crear_usuario_wp">
                                Crear cuenta de usuario en WordPress
                            </label>
                        </div>

                        <small class="form-text text-muted">
                            Si activas esta opción, se creará una cuenta con usuario y contraseña generados automáticamente, y se enviarán las credenciales al email del empleado.
                        </small>
                    </div>


                    <div id="hrm_rol_row" style="display: none;" class="mb-3">
                        <label for="hrm_rol_usuario_wp" class="form-label">Rol en WordPress <span class="text-danger">*</span></label>
                        <select id="hrm_rol_usuario_wp" name="rol_usuario_wp" class="form-select">
                            <option value="">Selecciona un rol...</option>
                            <?php foreach ( $wp_roles as $role_key => $role_name ) : ?>
                                <option value="<?= esc_attr( $role_key ) ?>"><?= esc_html( $role_name ) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            El rol determina qué permisos tendrá el usuario en el sistema
                        </small>
                    </div>
                </fieldset>

                <!-- BOTONES DE ACCIÓN -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="javascript:history.back()" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="dashicons dashicons-plus-alt2"></i> Crear Empleado
                    </button>
                </div>
            </form>

        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var departamento = document.getElementById('hrm_departamento');
            var areaGerenciaSection = document.getElementById('area_gerencia_section');
            function toggleAreaGerencia() {
                if (departamento.value === 'Gerencia') {
                    areaGerenciaSection.style.display = 'block';
                } else {
                    areaGerenciaSection.style.display = 'none';
                }
            }
            toggleAreaGerencia();
            departamento.addEventListener('change', toggleAreaGerencia);
        });
        </script>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
        // Validación de email ahora centralizada en assets/js/employees-create.js
        // (Se mantiene el div #hrm_email_feedback para mostrar mensajes desde el JS centralizado)
        
    const departamentoSelect = document.getElementById('hrm_departamento');
    const puestoSelect = document.getElementById('hrm_puesto');
    const areaGerenciaSelect = document.getElementById('hrm_area_gerencia');
    const areaGerenciaContainer = areaGerenciaSelect ? areaGerenciaSelect.closest('.col-md-6') : null;
    const deptosCargoContainer = document.getElementById('hrm_deptos_a_cargo_container');
    const deptosCheckboxesDiv = document.getElementById('hrm_deptos_checkboxes');
    
    // Lista de todos los departamentos disponibles
    const todosDeptos = <?= json_encode( $hrm_departamentos ) ?>;
    
    // Mapeo de áreas gerenciales con sus departamentos predefinidos
    const deptosPredefinidos = {
        'comercial': ['Soporte', 'Ventas'],
        'proyectos': ['Desarrollo'],
        'operaciones': ['Administracion', 'Gerencia', 'Sistemas']
    };
    
    function loadDepartamentosCheckboxes() {
        const areaValue = areaGerenciaSelect.value.toLowerCase().trim();
        
        if ( areaValue === '' ) {
            deptosCheckboxesDiv.innerHTML = '';
            return;
        }
        
        // Obtener departamentos predefinidos para este área
        const deptosAMarcar = deptosPredefinidos[areaValue] || [];
        
        // Generar checkboxes para todos los departamentos (excepto Gerencia)
        let html = '';
        todosDeptos.forEach( function( depto, index ) {
            // Excluir Gerencia de los checkboxes
            if ( depto.toLowerCase() === 'gerencia' ) {
                return;
            }
            
            // Verificar si este departamento debe estar marcado
            const estaPredefinido = deptosAMarcar.some( d => d.toLowerCase() === depto.toLowerCase() );
            const checked = estaPredefinido ? 'checked' : '';
            
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
    
    function toggleAreaGerencia() {
        const deptoValue = departamentoSelect.value.toLowerCase().trim();
        
        // Mostrar área de gerencia y departamentos a cargo SOLO si el departamento seleccionado es "Gerencia"
        const esGerencia = deptoValue === 'gerencia';
        
        if ( esGerencia ) {
            if ( areaGerenciaContainer ) areaGerenciaContainer.style.display = 'block';
            if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'block';
            loadDepartamentosCheckboxes();
        } else {
            if ( areaGerenciaContainer ) areaGerenciaContainer.style.display = 'none';
            if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'none';
            if ( areaGerenciaSelect ) areaGerenciaSelect.value = '';
            if ( deptosCheckboxesDiv ) deptosCheckboxesDiv.innerHTML = '';
        }
    }
    
    // Event listeners
    departamentoSelect.addEventListener('change', toggleAreaGerencia);
    puestoSelect.addEventListener('change', toggleAreaGerencia);
    
    areaGerenciaSelect.addEventListener('change', function() {
        const deptoValue = departamentoSelect.value.toLowerCase().trim();
        
        if ( deptoValue === 'gerencia' ) {
            if ( areaGerenciaSelect.value !== '' ) {
                if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'block';
                loadDepartamentosCheckboxes();
            } else {
                if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'none';
                if ( deptosCheckboxesDiv ) deptosCheckboxesDiv.innerHTML = '';
            }
        }
    });
    
    toggleAreaGerencia();

    // ========================================
    // CÁLCULO INTELIGENTE DE AÑOS EN LA EMPRESA
    // ========================================
    const fechaIngresoInput = document.getElementById('hrm_fecha_ingreso');
    const anosAnterioresInput = document.getElementById('hrm_anos_acreditados_anteriores');
    const anosEmpresaInput = document.getElementById('hrm_anos_en_la_empresa');
    const anosTotalesInput = document.getElementById('hrm_anos_totales_trabajados');

    /**
     * Calcula los años en la empresa basado en la fecha de ingreso
     * Solo muestra AÑOS COMPLETOS (sin decimales)
     * Se actualiza solo cuando se cumple un aniversario
     */
    function calcularAnosEnEmpresa() {
        if (!fechaIngresoInput.value) {
            anosEmpresaInput.value = '0';
            document.getElementById('hrm_anos_en_la_empresa_hidden').value = '0';
            anosEmpresaInput.title = 'Selecciona una fecha de ingreso';
            return 0;
        }

        // Validar que la fecha no sea futura
        const fechaIngresoObj = new Date(fechaIngresoInput.value + 'T00:00:00');
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        if (fechaIngresoObj > hoy) {
            anosEmpresaInput.value = '0';
            document.getElementById('hrm_anos_en_la_empresa_hidden').value = '0';
            anosEmpresaInput.title = 'Error: La fecha de ingreso no puede ser futura';
            anosEmpresaInput.classList.add('is-invalid');
            fechaIngresoInput.classList.add('is-invalid');
            return 0;
        }

        // Remover clase de error si la fecha es válida
        anosEmpresaInput.classList.remove('is-invalid');
        fechaIngresoInput.classList.remove('is-invalid');

        // Cálculo de años completos SOLO
        const diffMs = hoy - fechaIngresoObj;
        const diffDias = diffMs / (1000 * 60 * 60 * 24);
        const anos = (diffDias / 365.25);
        
        // Solo usar la parte ENTERA (años completos)
        const anosCompletos = Math.floor(anos);
        
        anosEmpresaInput.value = anosCompletos;
        // Sincronizar con el hidden input
        document.getElementById('hrm_anos_en_la_empresa_hidden').value = anosCompletos;
        
        // Actualizar el título con información sobre el próximo aniversario
        const proximoAniversario = new Date(fechaIngresoObj);
        proximoAniversario.setFullYear(proximoAniversario.getFullYear() + anosCompletos + 1);
        const diasFaltantes = Math.ceil((proximoAniversario - hoy) / (1000 * 60 * 60 * 24));
        
        anosEmpresaInput.title = `${anosCompletos} año(s) en la empresa. Próximo aniversario en ${diasFaltantes} días`;
        
        return anosCompletos;
    }

    /**
     * Calcula el total de años trabajados
     * Solo suma AÑOS COMPLETOS (sin decimales)
     */
    function calcularTotalAnos() {
        const anosAnteriores = parseInt(anosAnterioresInput.value) || 0;
        const anosEmpresa = parseInt(anosEmpresaInput.value) || 0;
        
        const totalAnos = anosAnteriores + anosEmpresa;
        
        anosTotalesInput.value = totalAnos;
        // Sincronizar con el hidden input
        document.getElementById('hrm_anos_totales_trabajados_hidden').value = totalAnos;
        
        // Actualizar el título con información detallada
        anosTotalesInput.title = `${totalAnos} año(s) de experiencia laboral total`;
    }

    /**
     * Actualiza todos los cálculos
     */
    function actualizarCalculosAnos() {
        calcularAnosEnEmpresa();
        calcularTotalAnos();
    }

    // Event listeners para cálculo dinámico
    fechaIngresoInput.addEventListener('change', actualizarCalculosAnos);
    fechaIngresoInput.addEventListener('blur', actualizarCalculosAnos);
    anosAnterioresInput.addEventListener('change', actualizarCalculosAnos);
    anosAnterioresInput.addEventListener('input', actualizarCalculosAnos);
    anosAnterioresInput.addEventListener('blur', actualizarCalculosAnos);

    // Calcular al cargar la página
    actualizarCalculosAnos();
    
    // IMPORTANTE: Sincronizar valores justo antes de enviar el formulario
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        // Asegurar que los valores estén actualizados antes de enviar
        document.getElementById('hrm_anos_en_la_empresa_hidden').value = anosEmpresaInput.value;
        document.getElementById('hrm_anos_totales_trabajados_hidden').value = anosTotalesInput.value;
    });
</script>