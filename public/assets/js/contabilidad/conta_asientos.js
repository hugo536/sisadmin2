document.addEventListener('DOMContentLoaded', function() {
    // Referencias a los elementos del HTML
    const btnAgregar = document.getElementById('agregar-linea');
    const tbody = document.querySelector('#lineas tbody');
    const sumDebeEl = document.getElementById('sumDebe');
    const sumHaberEl = document.getElementById('sumHaber');
    const balanceEstado = document.getElementById('balanceEstado');
    const form = document.getElementById('form-asiento');

    // 1. Preparar las opciones del menú desplegable de CUENTAS (viene del PHP)
    let cuentasOptions = '<option value="">Seleccione cuenta...</option>';
    if (window.CONTA_CUENTAS) {
        window.CONTA_CUENTAS.forEach(c => {
            cuentasOptions += `<option value="${c.id}">${c.codigo} - ${c.nombre}</option>`;
        });
    }

    // 2. Preparar las opciones del menú desplegable de CENTROS DE COSTO (viene del PHP)
    let centrosOptions = '<option value="0">Sin centro de costo</option>';
    if (window.CONTA_CENTROS) {
        window.CONTA_CENTROS.forEach(cc => {
            centrosOptions += `<option value="${cc.id}">${cc.codigo} - ${cc.nombre}</option>`;
        });
    }

    // 3. Función para sumar las columnas y verificar si el asiento cuadra
    function calcularTotales() {
        let tDebe = 0;
        let tHaber = 0;
        
        document.querySelectorAll('.input-debe').forEach(el => tDebe += parseFloat(el.value || 0));
        document.querySelectorAll('.input-haber').forEach(el => tHaber += parseFloat(el.value || 0));

        sumDebeEl.textContent = tDebe.toFixed(4);
        sumHaberEl.textContent = tHaber.toFixed(4);

        // Si es mayor a 0 y la diferencia es casi nula (para evitar errores de decimales)
        if (tDebe > 0 && Math.abs(tDebe - tHaber) < 0.0001) {
            balanceEstado.textContent = 'Cuadrado';
            balanceEstado.className = 'badge bg-success ms-2 px-3 py-2 fs-6 rounded-pill';
        } else {
            balanceEstado.textContent = 'Descuadrado';
            balanceEstado.className = 'badge bg-danger ms-2 px-3 py-2 fs-6 rounded-pill';
        }
    }

    // 4. Función para inyectar una nueva fila al HTML
    function agregarLinea() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="id_cuenta[]" class="form-select form-select-sm" required>${cuentasOptions}</select></td>
            <td><select name="id_centro_costo[]" class="form-select form-select-sm">${centrosOptions}</select></td>
            <td><input type="number" name="debe[]" class="form-control form-control-sm text-end input-debe" step="0.0001" min="0" value="0.0000"></td>
            <td><input type="number" name="haber[]" class="form-control form-control-sm text-end input-haber" step="0.0001" min="0" value="0.0000"></td>
            <td><input type="text" name="referencia[]" class="form-control form-control-sm" placeholder="Opcional"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-quitar"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(tr);

        // Evento para eliminar la fila
        tr.querySelector('.btn-quitar').addEventListener('click', function() {
            tr.remove();
            calcularTotales();
        });

        // Evento para recalcular si escriben números
        tr.querySelectorAll('.input-debe, .input-haber').forEach(input => {
            input.addEventListener('input', calcularTotales);
        });
    }

    // 5. Iniciar la pantalla
    if (btnAgregar) {
        btnAgregar.addEventListener('click', agregarLinea);
        // La contabilidad por partida doble exige mínimo 2 líneas al inicio
        agregarLinea();
        agregarLinea();
    }

    // 6. Validación antes de enviar a la Base de Datos
    if (form) {
        form.addEventListener('submit', function(e) {
            const tDebe = parseFloat(sumDebeEl.textContent);
            const tHaber = parseFloat(sumHaberEl.textContent);
            
            // Si el asiento no cuadra, detenemos el guardado
            if (tDebe <= 0 || Math.abs(tDebe - tHaber) >= 0.0001) {
                e.preventDefault(); // Detiene el botón submit
                alert('⚠️ El asiento contable no cuadra. El total del DEBE tiene que ser exactamente igual al total del HABER.');
            }
        });
    }
});