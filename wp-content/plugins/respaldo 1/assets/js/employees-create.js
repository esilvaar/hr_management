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

    // Validación al enviar el formulario
    if (form) {
        form.addEventListener('submit', function(e) {
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
        });
    }

    // Toggle para crear usuario WordPress
    const checkboxCrearUsuario = document.getElementById('hrm_crear_usuario_wp');
    const rolRow = document.getElementById('hrm_rol_row');
    
    if (checkboxCrearUsuario && rolRow) {
        checkboxCrearUsuario.addEventListener('change', function() {
            rolRow.style.display = this.checked ? 'block' : 'none';
            // Si se marca, hacer el select requerido; si no, no requerido
            const selectRol = document.getElementById('hrm_rol_usuario_wp');
            if (selectRol) {
                selectRol.required = this.checked;
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
