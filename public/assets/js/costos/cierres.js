/**
 * SISTEMA SISADMIN2 - Módulo de Cierres de Costos
 * Archivo: public/assets/js/costos/cierres.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Variables del DOM
    const btnAnalizar = document.getElementById('btnAnalizarPeriodo');
    const inputPeriodo = document.getElementById('cierrePeriodo');
    const cajaAnalisis = document.getElementById('cajaAnalisis');

    const inModAbs = document.getElementById('inputModAbs');
    const inModReal = document.getElementById('inputModReal');
    const lblModVar = document.getElementById('lblModVar');

    const inCifAbs = document.getElementById('inputCifAbs');
    const inCifReal = document.getElementById('inputCifReal');
    const lblCifVar = document.getElementById('lblCifVar');

    if (!btnAnalizar) return; // Evita errores si el script carga en otra pantalla

    // 1. EVENTO: Click en "Analizar Datos del mes"
    btnAnalizar.addEventListener('click', async function() {
        const periodo = inputPeriodo.value;
        if (!periodo) {
            alert("Por favor selecciona un periodo (Mes y Año).");
            return;
        }

        // Estado de carga
        btnAnalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Buscando...';
        btnAnalizar.disabled = true;

        try {
            const fd = new FormData();
            fd.append('accion', 'obtener_absorbido_ajax');
            fd.append('periodo', periodo);

            // Hacemos la petición POST a la misma URL
            const res = await fetch(window.location.href, { 
                method: 'POST', 
                body: fd 
            }).then(r => r.json());

            if (res.success) {
                // Rellenar datos devueltos por el servidor
                document.getElementById('lblTotalOrdenes').textContent = res.data.total_ordenes;
                
                const mod = parseFloat(res.data.total_mod_absorbida) || 0;
                const cif = parseFloat(res.data.total_cif_absorbido) || 0;

                // Setear MOD
                document.getElementById('lblModAbs').textContent = mod.toFixed(2);
                inModAbs.value = mod;
                inModReal.value = ''; // Resetea el input para que el usuario escriba
                lblModVar.textContent = 'S/ 0.00';
                lblModVar.className = 'mb-0 fw-bold text-muted';

                // Setear CIF
                document.getElementById('lblCifAbs').textContent = cif.toFixed(2);
                inCifAbs.value = cif;
                inCifReal.value = '';
                lblCifVar.textContent = 'S/ 0.00';
                lblCifVar.className = 'mb-0 fw-bold text-muted';

                // Mostrar la caja con la información
                cajaAnalisis.classList.remove('d-none');
            } else {
                alert(res.message || "No se pudo obtener la información.");
            }
        } catch (e) {
            console.error(e);
            alert("Error crítico al conectar con el servidor.");
        } finally {
            // Restaurar botón
            btnAnalizar.innerHTML = '<i class="bi bi-search me-2"></i>Analizar Datos del Mes';
            btnAnalizar.disabled = false;
        }
    });

    // 2. FUNCIÓN: Calcular variación en vivo
    function calcularVariacion(inputAbs, inputReal, lblVar) {
        const abs = parseFloat(inputAbs.value) || 0;
        const real = parseFloat(inputReal.value) || 0;
        
        if (inputReal.value === '') {
            lblVar.textContent = 'S/ 0.00';
            lblVar.className = 'mb-0 fw-bold text-muted';
            return;
        }

        // FÓRMULA: Variación = Absorbido (Cobrado al Producto) - Costo Real (Pagado en Banco)
        // Positivo = Favorable (Ahorro/Eficiencia) | Negativo = Desfavorable (Pérdida/Ineficiencia)
        const variacion = abs - real;
        lblVar.textContent = 'S/ ' + variacion.toFixed(2);

        if (variacion > 0) {
            lblVar.className = 'mb-0 fw-bold text-success'; 
        } else if (variacion < 0) {
            lblVar.className = 'mb-0 fw-bold text-danger'; 
        } else {
            lblVar.className = 'mb-0 fw-bold text-dark'; 
        }
    }

    // 3. EVENTOS: Escuchar cuando el usuario escribe los costos reales
    if(inModReal) inModReal.addEventListener('input', () => calcularVariacion(inModAbs, inModReal, lblModVar));
    if(inCifReal) inCifReal.addEventListener('input', () => calcularVariacion(inCifAbs, inCifReal, lblCifVar));

    // 4. EVENTO: Limpiar todo al cerrar el modal
    const modalEl = document.getElementById('modalNuevoCierre');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            cajaAnalisis.classList.add('d-none');
            document.getElementById('formCierre').reset();
        });
    }
});