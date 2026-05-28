// validaciones.js - Validación y formateo de RUT Chileno y utilidades de formularios

/**
 * Valida un RUT chileno usando el algoritmo Módulo 11
 */
function validarRutChileno(rut) {
    if (!rut || typeof rut !== 'string') return false;
    
    // Eliminar puntos, guiones y espacios
    let valor = rut.replace(/[^0-9kK]/g, '');
    if (valor.length < 2) return false;
    
    let cuerpo = valor.slice(0, -1);
    let dv = valor.slice(-1).toUpperCase();
    
    // Si el cuerpo no es numérico, es inválido
    if (isNaN(cuerpo)) return false;
    
    // Calcular dígito verificador
    let suma = 0;
    let multiplo = 2;
    
    for (let i = cuerpo.length - 1; i >= 0; i--) {
        suma += multiplo * parseInt(cuerpo.charAt(i), 10);
        multiplo = (multiplo === 7) ? 2 : multiplo + 1;
    }
    
    let resto = 11 - (suma % 11);
    let dvEsperado = '';
    if (resto === 11) {
        dvEsperado = '0';
    } else if (resto === 10) {
        dvEsperado = 'K';
    } else {
        dvEsperado = resto.toString();
    }
    
    return dv === dvEsperado;
}

/**
 * Formatea un RUT al formato estándar 12.345.678-9
 */
function formatearRutChileno(rut) {
    let valor = rut.replace(/[^0-9kK]/g, '');
    if (valor.length < 2) return rut;
    
    let cuerpo = valor.slice(0, -1);
    let dv = valor.slice(-1).toUpperCase();
    
    // Dar formato de miles al cuerpo
    let cuerpoFormateado = '';
    let cont = 0;
    for (let i = cuerpo.length - 1; i >= 0; i--) {
        cuerpoFormateado = cuerpo.charAt(i) + cuerpoFormateado;
        cont++;
        if (cont === 3 && i !== 0) {
            cuerpoFormateado = '.' + cuerpoFormateado;
            cont = 0;
        }
    }
    
    return cuerpoFormateado + '-' + dv;
}

/**
 * Vincula la validación en tiempo real a un elemento input de RUT
 */
function aplicarValidacionRut(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('blur', function() {
        let originalVal = this.value.trim();
        if (originalVal === '') return;
        
        let formatted = formatearRutChileno(originalVal);
        this.value = formatted;
        
        if (!validarRutChileno(formatted)) {
            this.classList.add('is-invalid');
            this.style.borderColor = 'var(--danger, #ff4d4d)';
            
            // Mostrar tooltip o alerta flotante si no es válido
            let feedback = document.getElementById(inputId + '-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.id = inputId + '-feedback';
                feedback.className = 'invalid-feedback';
                feedback.style.color = 'var(--danger, #ff4d4d)';
                feedback.style.fontSize = '0.85rem';
                feedback.style.marginTop = '4px';
                feedback.innerHTML = '⚠️ RUT inválido. Verifique el formato y dígito verificador.';
                input.parentNode.appendChild(feedback);
            }
        } else {
            this.classList.remove('is-invalid');
            this.style.borderColor = 'var(--success, #2ecc71)';
            const feedback = document.getElementById(inputId + '-feedback');
            if (feedback) feedback.remove();
        }
    });

    input.addEventListener('input', function() {
        // Permitir limpiar estilos mientras escribe
        this.style.borderColor = '';
        const feedback = document.getElementById(inputId + '-feedback');
        if (feedback) feedback.remove();
    });
}
