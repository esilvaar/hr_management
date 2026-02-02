/**
 * Scripts para el formulario de crear empleados
 * Incluye validación de RUT y manejo de eventos
 */

/**
 * Calcula el dígito verificador de un RUT usando el algoritmo Módulo 11
 */
function calcularDigitoVerificadorRUT(numeros) {
    numeros = String(numeros);
    const digitos = numeros.split('').reverse();
    const multiplicadores = [2, 3, 4, 5, 6, 7];
    
    let suma = 0;
    for (let i = 0; i < digitos.length; i++) {
        const multiplicador = multiplicadores[i % 6];
        suma += parseInt(digitos[i]) * multiplicador;
    }
    
    const resto = suma % 11;
    const digito = 11 - resto;
    
    if (digito === 11) return '0';
    if (digito === 10) return 'K';
    return String(digito);
}

/**
 * Valida un RUT chileno - Formato OBLIGATORIO: 12345678-9 (SIN puntos)
 */
function validarRUT(rut) {
    if (!rut) return false;
    
    // Convertir a mayúsculas y remover espacios
    rut = rut.toUpperCase().trim();
    
    // Validar formato EXACTO: números-dígito (SIN puntos)
    const regex = /^(\d{1,8})-([0-9K])$/;
    const match = rut.match(regex);
    
    if (!match) return false;
    
    const numeros = match[1];
    const digitoIngresado = match[2];
    const digitoCalculado = calcularDigitoVerificadorRUT(numeros);
    
    return digitoIngresado === digitoCalculado;
}

/**
 * Formatea un RUT al formato estándar: 12345678-9 (SIN puntos)
 */
function formatearRUT(rut) {
    if (!rut) return '';
    
    // Remover espacios y puntos
    rut = rut.replace(/\s|\./g, '').toUpperCase().trim();
    
    // Si no tiene guión, agregarlo antes del último dígito
    if (rut.indexOf('-') === -1 && rut.length > 1) {
        rut = rut.slice(0, -1) + '-' + rut.slice(-1);
    }
    
    return rut;
}

document.addEventListener('DOMContentLoaded', function() {
    const rutInput = document.getElementById('hrm_rut');
    const rutFeedback = document.getElementById('hrm_rut_feedback');
    const form = document.querySelector('form');

    // Validación en tiempo real del RUT
    if (rutInput && rutFeedback) {
        rutInput.addEventListener('blur', function() {
            const rut = this.value.trim();
            
            if (!rut) {
                rutFeedback.style.display = 'none';
                rutInput.classList.remove('is-invalid', 'is-valid');
                return;
            }

            // Formatear el RUT
            const rutFormateado = formatearRUT(rut);
            this.value = rutFormateado;

            // Validar
            if (validarRUT(rutFormateado)) {
                rutInput.classList.remove('is-invalid');
                rutInput.classList.add('is-valid');
                rutFeedback.className = 'mt-2 alert alert-success alert-sm mb-0';
                rutFeedback.innerHTML = '<i class="dashicons dashicons-yes"></i> RUT válido';
                rutFeedback.style.display = 'block';
            } else {
                rutInput.classList.remove('is-valid');
                rutInput.classList.add('is-invalid');
                rutFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                rutFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> RUT inválido (dígito verificador incorrecto)';
                rutFeedback.style.display = 'block';
            }
        });


        // Formatear mientras escribe
        rutInput.addEventListener('input', function() {
            let rut = this.value;
            
            // Si el usuario escribe puntos, eliminarlos
            if (rut.includes('.')) {
                rut = rut.replace(/\./g, '');
                this.value = rut;
            }
        });
    }

    // ===== Validación de Email (misma lógica que RUT) =====
    const emailInput = document.getElementById('hrm_email');
    const emailFeedback = document.getElementById('hrm_email_feedback');
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    function validarEmail(email) {
        if (!email) return false;
        return emailRegex.test(email.trim());
    }

    if (emailInput && emailFeedback) {
        emailInput.addEventListener('blur', async function() {
            const email = this.value.trim();

            if (!email) {
                emailFeedback.style.display = 'none';
                emailInput.classList.remove('is-invalid', 'is-valid');
                return;
            }

            if (!validarEmail(email)) {
                emailInput.classList.remove('is-valid');
                emailInput.classList.add('is-invalid');
                emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Email inválido (formato incorrecto)';
                emailFeedback.style.display = 'block';
                return;
            }

            // Si formato OK, consultar al servidor si ya existe
            try {
                console.debug('hrm_check_email (blur) sending', email);
                const resp = await fetch(hrmCreateData.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ action: 'hrm_check_email', email_b64: btoa(email), nonce: hrmCreateData.nonce }).toString()
                });

                if ( !resp.ok ) {
                    const text = await resp.text();
                    console.error('hrm_check_email failed (blur): status=' + resp.status, text);
                    emailInput.classList.remove('is-valid');
                    emailInput.classList.add('is-invalid');
                    emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                    emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email (servidor ' + resp.status + ')';
                    emailFeedback.style.display = 'block';
                    return;
                }

                let json;
                try {
                    json = await resp.json();
                } catch (e) {
                    const text = await resp.text();
                    console.error('hrm_check_email returned non-JSON (blur):', text);
                    emailInput.classList.remove('is-valid');
                    emailInput.classList.add('is-invalid');
                    emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                    emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email (respuesta inválida)';
                    emailFeedback.style.display = 'block';
                    return;
                }

                if ( json.success && json.data ) {
                    const d = json.data;
                    // Si existe en tabla empleados, prioridad para ese mensaje
                    if ( d.exists_emp ) {
                        emailInput.classList.remove('is-valid');
                        emailInput.classList.add('is-invalid');
                        emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                        emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Email ya asociado a un empleado (ID: ' + d.employee_id + ')';
                        emailFeedback.style.display = 'block';
                    } else if ( d.exists_wp ) {
                        emailInput.classList.remove('is-valid');
                        emailInput.classList.add('is-invalid');
                        emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                        emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Email ya registrado en WordPress (ID: ' + d.wp_user_id + ')';
                        emailFeedback.style.display = 'block';
                    } else {
                        emailInput.classList.remove('is-invalid');
                        emailInput.classList.add('is-valid');
                        emailFeedback.className = 'mt-2 alert alert-success alert-sm mb-0';
                        emailFeedback.innerHTML = '<i class="dashicons dashicons-yes"></i> Email válido y disponible';
                        emailFeedback.style.display = 'block';
                    }
                } else {
                    // Error de servidor (JSON de error)
                    console.error('hrm_check_email returned success=false', json);
                    emailInput.classList.remove('is-valid');
                    emailInput.classList.add('is-invalid');
                    emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                    emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email';
                    emailFeedback.style.display = 'block';
                }
            } catch (err) {
                console.error('hrm_check_email fetch error (blur):', err);
                emailInput.classList.remove('is-valid');
                emailInput.classList.add('is-invalid');
                emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email';
                emailFeedback.style.display = 'block';
            }
        });

        emailInput.addEventListener('input', function() {
            // Ocultar feedback mientras se escribe
            emailFeedback.style.display = 'none';
            emailInput.classList.remove('is-valid', 'is-invalid');
        });
    }

    // Validación al enviar el formulario
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validar RUT
            if (rutInput && rutInput.value.trim()) {
                const rut = rutInput.value.trim();

                if (!validarRUT(rut)) {
                    e.preventDefault();
                    rutInput.classList.add('is-invalid');
                    rutFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                    rutFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> RUT inválido. Debe cumplir el formato 12345678-9';
                    rutFeedback.style.display = 'block';

                    // Hacer scroll al campo de error
                    rutInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
            }

            // Validar Email (misma lógica centralizada)
            if (emailInput && emailInput.value.trim()) {
                const email = emailInput.value.trim();

                if (!validarEmail(email)) {
                    e.preventDefault();
                    emailInput.classList.add('is-invalid');
                    emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                    emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Email inválido. Debe cumplir el formato usuario@dominio.com';
                    emailFeedback.style.display = 'block';

                    // Hacer scroll al campo de error
                    emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }

                // Verificar existencia en servidor (sincronizar)
                e.preventDefault();
                (async function() {
                        try {
                        console.debug('hrm_check_email (submit) sending', email);
                        const resp = await fetch(hrmCreateData.ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: new URLSearchParams({ action: 'hrm_check_email', email_b64: btoa(email), nonce: hrmCreateData.nonce }).toString()
                        });

                        if ( !resp.ok ) {
                            const text = await resp.text();
                            console.error('hrm_check_email failed (submit): status=' + resp.status, text);
                            emailInput.classList.remove('is-valid');
                            emailInput.classList.add('is-invalid');
                            emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                            emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email (servidor ' + resp.status + ')';
                            emailFeedback.style.display = 'block';
                            emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            return false;
                        }

                        let json;
                        try {
                            json = await resp.json();
                        } catch (e) {
                            const text = await resp.text();
                            console.error('hrm_check_email returned non-JSON (submit):', text);
                            emailInput.classList.remove('is-valid');
                            emailInput.classList.add('is-invalid');
                            emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                            emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email (respuesta inválida)';
                            emailFeedback.style.display = 'block';
                            emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            return false;
                        }

                        if ( json.success && json.data ) {
                            const d = json.data;
                            if ( d.exists_emp ) {
                                emailInput.classList.remove('is-valid');
                                emailInput.classList.add('is-invalid');
                                emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                                emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Email ya asociado a un empleado (ID: ' + d.employee_id + (d.employee_name ? ' - ' + d.employee_name : '') + ')';
                                emailFeedback.style.display = 'block';
                                emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                return false;
                            } else if ( d.exists_wp ) {
                                emailInput.classList.remove('is-valid');
                                emailInput.classList.add('is-invalid');
                                emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                                emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Email ya registrado en WordPress (ID: ' + d.wp_user_id + ')';
                                emailFeedback.style.display = 'block';
                                emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                return false;
                            }

                            // Si todo OK, reintentar submit
                            e.target.submit();
                        } else {
                            // Error de servidor
                            console.error('hrm_check_email returned success=false (submit)', json);
                            emailInput.classList.remove('is-valid');
                            emailInput.classList.add('is-invalid');
                            emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                            emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email';
                            emailFeedback.style.display = 'block';
                        }
                    } catch (err) {
                        console.error('hrm_check_email fetch error (submit):', err);
                        emailInput.classList.remove('is-valid');
                        emailInput.classList.add('is-invalid');
                        emailFeedback.className = 'mt-2 alert alert-danger alert-sm mb-0';
                        emailFeedback.innerHTML = '<i class="dashicons dashicons-no"></i> Error verificando email';
                        emailFeedback.style.display = 'block';
                    }
                })();
                return false;
            }
        });
    }

    // Toggle para crear usuario WordPress
    const checkboxCrearUsuario = document.getElementById('hrm_crear_usuario_wp');
    const rolRow = document.getElementById('hrm_rol_row');
    
    if (checkboxCrearUsuario && rolRow) {
        // Inicializar el estado en carga (soporta checkbox marcado por defecto)
        rolRow.style.display = checkboxCrearUsuario.checked ? 'block' : 'none';
        const selectRolInit = document.getElementById('hrm_rol_usuario_wp');
        if ( selectRolInit ) selectRolInit.required = checkboxCrearUsuario.checked;

        checkboxCrearUsuario.addEventListener('change', function() {
            rolRow.style.display = this.checked ? 'block' : 'none';
            // Si se marca, hacer el select requerido; si no, no requerido
            const selectRol = document.getElementById('hrm_rol_usuario_wp');
            if (selectRol) {
                // Si el select está deshabilitado (supervisor), no forzar required
                selectRol.required = this.checked && !selectRol.disabled;
                if (!this.checked) {
                    selectRol.value = '';
                }
            }
        });
    }

    // Validar que si se marca crear usuario, el rol sea obligatorio
    if (form) {
        form.addEventListener('submit', function(e) {
            if (checkboxCrearUsuario && checkboxCrearUsuario.checked) {
                const selectRol = document.getElementById('hrm_rol_usuario_wp');
                if (selectRol && !selectRol.value) {
                    e.preventDefault();
                    selectRol.classList.add('is-invalid');
                    selectRol.focus();
                    // Mostrar feedback visual
                    let feedback = selectRol.nextElementSibling;
                    if (feedback && feedback.classList.contains('form-text')) {
                        feedback.innerHTML = '<span class="text-danger">Debes seleccionar un rol para el usuario de WordPress.</span>';
                    }
                    return false;
                } else if (selectRol) {
                    selectRol.classList.remove('is-invalid');
                }
            }
        });
    }

    // Toggle para más opciones
    const toggleBtn = document.getElementById('hrm_toggle_options');
    const moreOptions = document.getElementById('hrm_more_options');
    
    if (toggleBtn && moreOptions) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const isHidden = moreOptions.style.display === 'none';
            moreOptions.style.display = isHidden ? 'block' : 'none';
            
            // Cambiar el texto del botón
            const icon = toggleBtn.querySelector('i');
            if (isHidden) {
                toggleBtn.innerHTML = '<i class="dashicons dashicons-arrow-up"></i> Ocultar opciones';
            } else {
                toggleBtn.innerHTML = '<i class="dashicons dashicons-arrow-down"></i> Más opciones';
            }
        });
    }
});

// -----------------------------
// Comportamiento adicional movido aquí
// -----------------------------

document.addEventListener('DOMContentLoaded', function() {
    var data = window.hrmCreateData || {};
    var todosDeptos = data.todosDeptos || [];
    var deptosPredef = data.deptosPredefinidos || {};
    var mapaPuestos = data.mapaPuestos || {};
    if (typeof console !== 'undefined' && console.debug) console.debug('[HRM] mapaPuestos loaded:', mapaPuestos);

    var departamento = document.getElementById('hrm_departamento');
    var puesto = document.getElementById('hrm_puesto');
    var areaGerenciaSelect = document.getElementById('hrm_area_gerencia');
    var deptosCheckboxesDiv = document.getElementById('hrm_deptos_checkboxes');
    var deptosCargoContainer = document.getElementById('hrm_deptos_a_cargo_container');
    var areaGerenciaSection = document.getElementById('area_gerencia_section');

    if (!departamento || !puesto) return;

    var opcionesOriginales = Array.from(puesto.options).map(function(opt){ return { value: opt.value, text: opt.text }; });

    // Si por alguna razón el <select> fue renderizado sin opciones (o con solo el default),
    // usar el fallback `data-all-puestos` del atributo o la localización `todosDeptos`.
    if (!opcionesOriginales || opcionesOriginales.length <= 1) {
        var fallbackPuestos = [];
        try {
            if (puesto.dataset && puesto.dataset.allPuestos) {
                fallbackPuestos = JSON.parse(puesto.dataset.allPuestos);
            } else if (data && data.todosDeptos) {
                // historic key: todosDeptos contains the list of puestos fallback
                fallbackPuestos = data.todosDeptos;
            }
        } catch (e) { fallbackPuestos = []; }

        if (fallbackPuestos && fallbackPuestos.length) {
            opcionesOriginales = [{ value: '', text: 'Selecciona...' }].concat(fallbackPuestos.map(function(p){ return { value: p, text: p }; }));
        }
    }

    function normalizeText(str) {
        if (!str) return '';
        try {
            // Normalizar y eliminar marcas diacríticas (más compatible que \p{Diacritic})
            return str.toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        } catch (e) {
            // Fallback sencillo
            return String(str).toLowerCase().trim();
        }
    }

    function agregarPuesto(nombre) {
        if (!nombre) return;
        var normalizedNombre = normalizeText(nombre);
        // Buscar por coincidencia insensible (sin acentos)
        var opt = opcionesOriginales.find(function(o){ return normalizeText(o.text) === normalizedNombre; });
        if (!opt) {
            // Intentar búsqueda por substring
            opt = opcionesOriginales.find(function(o){ return normalizeText(o.text).indexOf(normalizedNombre) !== -1; });
        }

        var option = document.createElement('option');
        if (opt) {
            option.value = opt.value;
            option.text = opt.text;
        } else {
            // Si no se encontró una opción original, añadir un option con el texto proporcionado
            option.value = nombre;
            option.text = nombre;
        }
        puesto.appendChild(option);
    }

    function restaurarTodos() {
        opcionesOriginales.forEach(function(opt, idx) {
            if (idx === 0) return;
            var option = document.createElement('option');
            option.value = opt.value; option.text = opt.text;
            puesto.appendChild(option);
        });
    }

    function filtrarPuestos() {
        try {
            var depto = (departamento.value || '').toLowerCase().trim();
            puesto.innerHTML = '';
            var optDefault = document.createElement('option'); optDefault.value = ''; optDefault.text = 'Selecciona...'; puesto.appendChild(optDefault);
            // Preferir mapaPuestos (department -> puestos). Si no existe, intentar heurística o mostrar todos.
            if (mapaPuestos[depto]) {
                mapaPuestos[depto].forEach(function(nombre){ agregarPuesto(nombre); });
                return;
            }
            // Si no hay mapping explícito, intentar usar las opciones originales por heurística (mostrar puestos que contengan parte del depto)
            var anyAdded = false;
            opcionesOriginales.forEach(function(opt){
                try {
                    if (opt.text.toLowerCase().indexOf(depto) !== -1) {
                        var option = document.createElement('option'); option.value = opt.value; option.text = opt.text; puesto.appendChild(option); anyAdded = true;
                    }
                } catch (e) { console.warn('error comparando opción de puesto', e); }
            });
            if (anyAdded) return;
            // Fallback: restaurar todos
            restaurarTodos();

            // Si después de todo no hay opciones (solo el default), intentar un fallback con todos los puestos conocidos
            if (puesto && puesto.options && puesto.options.length <= 1) {
                var fallbackAll = [];
                try {
                    if (puesto.dataset && puesto.dataset.allPuestos) {
                        fallbackAll = JSON.parse(puesto.dataset.allPuestos);
                    } else if (data && data.todosDeptos) {
                        fallbackAll = data.todosDeptos;
                    }
                } catch (e) { fallbackAll = []; }

                if (fallbackAll && fallbackAll.length) {
                    fallbackAll.forEach(function(p){ var o = document.createElement('option'); o.value = p; o.text = p; puesto.appendChild(o); });
                }
            }
        } catch (err) {
            // En caso de error, dejar el select con fallback general
            console.error('filtrarPuestos error:', err);
            if (puesto && puesto.dataset && puesto.dataset.allPuestos) {
                try { var fallback = JSON.parse(puesto.dataset.allPuestos); fallback.forEach(function(p){ var o = document.createElement('option'); o.value = p; o.text = p; puesto.appendChild(o); }); } catch(e){}
            }
        }
    }

    function loadDepartamentosCheckboxes() {
        if (!areaGerenciaSelect || !deptosCheckboxesDiv) return;
        var areaVal = (areaGerenciaSelect.value || '').toLowerCase().trim();
        if (areaVal === '') { deptosCheckboxesDiv.innerHTML = ''; return; }
        var deptosAMarcar = deptosPredef[areaVal] || [];
        var html = '';
        todosDeptos.forEach(function(depto, index){
            if (depto.toLowerCase() === 'gerencia') return;
            var estaPredefinido = deptosAMarcar.some(function(d){ return d.toLowerCase() === depto.toLowerCase(); });
            var checked = estaPredefinido ? 'checked' : '';
            var id = 'depto_checkbox_' + index;
            html += '<div class="form-check"><input class="form-check-input hrm_depto_checkbox" type="checkbox" name="deptos_a_cargo[]" value="' + depto + '" id="' + id + '" ' + checked + '><label class="form-check-label" for="' + id + '">' + depto + '</label></div>';
        });
        deptosCheckboxesDiv.innerHTML = html;
    }

    function toggleAreaGerencia() {
        var deptoValue = (departamento.value || '').toLowerCase().trim();
        var esGerencia = deptoValue === 'gerencia';
        if (esGerencia) {
            if (areaGerenciaSection) areaGerenciaSection.style.display = 'block';
            if (deptosCargoContainer) deptosCargoContainer.style.display = 'block';
            loadDepartamentosCheckboxes();
        } else {
            if (areaGerenciaSection) areaGerenciaSection.style.display = 'none';
            if (deptosCargoContainer) deptosCargoContainer.style.display = 'none';
            if (areaGerenciaSelect) areaGerenciaSelect.value = '';
            if (deptosCheckboxesDiv) deptosCheckboxesDiv.innerHTML = '';
        }
    }

    function togglePracticanteBehavior() {
        var selectedText = (puesto.options[puesto.selectedIndex] || {}).text || '';
        var esPracticante = selectedText.toLowerCase().indexOf('practicante') !== -1;
        var antig = document.getElementById('hrm_antiguedad_section');
        var fechaRow = document.getElementById('hrm_fecha_termino_row');
        var fechaInput = document.getElementById('hrm_fecha_termino');
        var anosEmpresa = document.getElementById('hrm_anos_en_la_empresa');
        var anosTotales = document.getElementById('hrm_anos_totales_trabajados');

        if (esPracticante) {
            if (antig) antig.style.display = 'none';
            if (fechaRow) fechaRow.style.display = 'block';
            if (fechaInput) fechaInput.required = true;
            if (anosEmpresa) anosEmpresa.value = '0';
            if (anosTotales) anosTotales.value = '0';
            var h1 = document.getElementById('hrm_anos_en_la_empresa_hidden'); if (h1) h1.value = '0';
            var h2 = document.getElementById('hrm_anos_totales_trabajados_hidden'); if (h2) h2.value = '0';
        } else {
            if (antig) antig.style.display = '';
            if (fechaRow) fechaRow.style.display = 'none';
            if (fechaInput) { fechaInput.required = false; fechaInput.value = ''; }
            // trigger existing recalculation if available
            var evt = document.createEvent('Event'); evt.initEvent('change', true, true); var fi = document.getElementById('hrm_fecha_ingreso'); if (fi) fi.dispatchEvent(evt);
        }
    }

    departamento.addEventListener('change', function(){ filtrarPuestos(); toggleAreaGerencia(); });
    puesto.addEventListener('change', function(){ toggleAreaGerencia(); togglePracticanteBehavior(); });
    if (areaGerenciaSelect) areaGerenciaSelect.addEventListener('change', function(){ if ((departamento.value || '').toLowerCase().trim() === 'gerencia') { if ( areaGerenciaSelect.value !== '' ) { if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'block'; loadDepartamentosCheckboxes(); } else { if ( deptosCargoContainer ) deptosCargoContainer.style.display = 'none'; if ( deptosCheckboxesDiv ) deptosCheckboxesDiv.innerHTML = ''; } } });

    // init
    filtrarPuestos();
    toggleAreaGerencia();
    togglePracticanteBehavior();
});
