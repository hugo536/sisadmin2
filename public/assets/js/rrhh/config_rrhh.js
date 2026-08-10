/**
 * LÓGICA PARA POLÍTICAS DE RRHH
 * Archivo: public/assets/js/rrhh/config_rrhh.js
 * Compatible con arquitectura SPA
 */

(function() {
    'use strict';

    function iniciarModuloConfigRRHH() {
        const formConfig = document.getElementById('formConfigRRHH');
        
        // Evitar inicialización doble en la SPA
        if (!formConfig || formConfig.dataset.iniciado === '1') return;
        formConfig.dataset.iniciado = '1';

        const selectBloque = document.querySelector('[name="bloque_minutos"]');
        const inputTolEntrada = document.querySelector('[name="tolerancia_entrada"]');
        const inputTolSalida = document.querySelector('[name="tolerancia_salida"]');

        if (selectBloque && inputTolEntrada && inputTolSalida) {
            
            // ==========================================
            // 1. MOTOR DEL EJEMPLO DINÁMICO
            // ==========================================
            const actualizarEjemplo = () => {
                const b = parseInt(selectBloque.value) || 0;
                const te = parseInt(inputTolEntrada.value) || 0;
                const ts = parseInt(inputTolSalida.value) || 0;

                // Actualizar etiquetas de texto base
                document.getElementById('lblEjemploBloque').textContent = b + ' min';
                document.getElementById('lblEjemploTolEntrada').textContent = te + ' min';
                document.getElementById('lblEjemploTolSalida').textContent = ts + ' min';

                // Helper para sumar/restar minutos a una hora fija fácilmente
                const formatTime = (hour, min) => {
                    let d = new Date();
                    d.setHours(hour, min, 0, 0); // JS maneja minutos negativos automáticamente
                    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
                };

                // Cálculos para la Entrada (Base 08:00)
                document.getElementById('lblEjemploEntradaOk').textContent = formatTime(8, te);
                document.getElementById('lblEjemploEntradaTarde').textContent = formatTime(8, te + 1);
                document.getElementById('lblEjemploEntradaCastigo').textContent = formatTime(8, b);

                // Cálculos para la Salida (Base 17:00)
                // Se resta la tolerancia a la hora de salida para ver desde qué minuto pueden salir
                document.getElementById('lblEjemploSalidaOk').textContent = formatTime(17, -ts);
                document.getElementById('lblEjemploSalidaTemprano').textContent = formatTime(17, -ts - 1);
                document.getElementById('lblEjemploSalidaCastigo').textContent = formatTime(17, -b); 
            };

            // Escuchar cambios en los inputs para actualizar en tiempo real
            selectBloque.addEventListener('change', actualizarEjemplo);
            inputTolEntrada.addEventListener('input', actualizarEjemplo);
            inputTolSalida.addEventListener('input', actualizarEjemplo);
            
            // Ejecutar una vez al cargar la vista
            actualizarEjemplo();

            // ==========================================
            // 2. VALIDACIÓN ANTES DE GUARDAR
            // ==========================================
            formConfig.addEventListener('submit', function(e) {
                const bloque = parseInt(selectBloque.value) || 0;
                const tolEntrada = parseInt(inputTolEntrada.value) || 0;
                const tolSalida = parseInt(inputTolSalida.value) || 0;
                
                // Ninguna tolerancia puede ser mayor o igual al bloque
                if (tolEntrada >= bloque || tolSalida >= bloque) {
                    e.preventDefault(); 
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Configuración Inválida',
                            text: 'Ambas tolerancias (entrada y salida) deben ser estrictamente menores al tamaño del bloque.',
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        alert('Ambas tolerancias (entrada y salida) deben ser estrictamente menores al tamaño del bloque.');
                    }
                    
                    if (tolEntrada >= bloque) {
                        inputTolEntrada.focus();
                    } else {
                        inputTolSalida.focus();
                    }
                }
            });
        }
    }

    // ==========================================
    // INICIALIZACIÓN COMPATIBLE CON SPA
    // ==========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarModuloConfigRRHH);
    } else {
        iniciarModuloConfigRRHH();
    }
})();