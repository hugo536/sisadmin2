document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap no está cargado.');
        return;
    }

    const app = document.getElementById('appProrrateos');
    if (!app) return;

    const centros = JSON.parse(app.dataset.centros || '[]');
    const urlGuardar = app.dataset.urlGuardar || '';

    const modalEl = document.getElementById('modalProrrateo');
    const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

    const form = document.getElementById('formProrrateo');
    const tbody = document.querySelector('#tablaDetalleProrrateo tbody');
    const totalLabel = document.getElementById('totalProrrateo');
    const btnGuardar = document.getElementById('btnGuardarProrrateo');

    // Función auxiliar para mostrar errores con SweetAlert2
    const mostrarError = (mensaje) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Atención', text: mensaje });
        } else {
            alert(mensaje);
        }
    };

    const resetForm = () => {
        form.reset();
        document.getElementById('prorrateo_id').value = '0';
        tbody.innerHTML = '';
        document.getElementById('tituloModalProrrateo').innerHTML = '<i class="bi bi-pie-chart me-2"></i>Nueva Regla de Prorrateo';
        addRow();
        recalcTotal();
    };

    const optionCentrosHtml = (selectedId) => {
        let html = '<option value="">Seleccione...</option>';
        centros.forEach((c) => {
            const id = String(c.id || '');
            const selected = String(selectedId || '') === id ? 'selected' : '';
            html += `<option value="${id}" ${selected}>${c.codigo} - ${c.nombre}</option>`;
        });
        return html;
    };

    const addRow = (detalle = null) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select class="form-select form-select-sm shadow-none detalle-centro" required>
                    ${optionCentrosHtml(detalle?.centro_destino_id || '')}
                </select>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.01" min="0.01" max="100" class="form-control shadow-none detalle-pct text-end"
                           value="${detalle?.porcentaje ? Number(detalle.porcentaje).toFixed(2) : ''}" required>
                    <span class="input-group-text bg-light text-muted">%</span>
                </div>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila border-0 rounded-circle" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        
        // Limpiar el borde rojo si el usuario empieza a corregir el error
        tr.querySelector('.detalle-centro').addEventListener('change', function() { this.classList.remove('is-invalid'); });
        tr.querySelector('.detalle-pct').addEventListener('input', function() { 
            this.classList.remove('is-invalid'); 
            recalcTotal(); 
        });

        tr.querySelector('.btn-eliminar-fila').addEventListener('click', () => {
            tr.remove();
            recalcTotal();
        });
    };

    const recalcTotal = () => {
        const inputs = tbody.querySelectorAll('.detalle-pct');
        let total = 0;
        inputs.forEach((input) => {
            total += Number(input.value || 0);
        });

        const valid = Math.abs(total - 100) <= 0.01;
        totalLabel.textContent = `Total: ${total.toFixed(2)}%`;
        
        // Estilos mejorados para el texto del total
        if (valid) {
            totalLabel.className = 'fw-bold text-success border border-success px-2 py-1 rounded bg-success-subtle';
        } else {
            totalLabel.className = 'fw-bold text-danger border border-danger px-2 py-1 rounded bg-danger-subtle';
        }

        btnGuardar.disabled = !valid || inputs.length === 0;
    };

    const cargarRegla = (regla) => {
        form.reset();
        tbody.innerHTML = '';

        document.getElementById('prorrateo_id').value = String(regla.id || 0);
        document.getElementById('prorrateo_nombre').value = regla.nombre || '';
        document.getElementById('prorrateo_origen').value = String(regla.centro_origen_id || '');
        document.getElementById('prorrateo_estado').value = String(regla.estado ?? 1);
        document.getElementById('tituloModalProrrateo').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Regla de Prorrateo';

        (regla.detalles || []).forEach(addRow);
        if ((regla.detalles || []).length === 0) {
            addRow();
        }

        recalcTotal();
        if (modal) modal.show();
    };

    document.getElementById('btnAgregarDestino')?.addEventListener('click', () => {
        addRow();
        recalcTotal();
    });

    document.getElementById('btnNuevaRegla')?.addEventListener('click', resetForm);

    document.querySelectorAll('.btn-editar-regla').forEach((btn) => {
        btn.addEventListener('click', function () {
            const reglaRaw = this.dataset.regla || '{}';
            try {
                cargarRegla(JSON.parse(reglaRaw));
            } catch (error) {
                console.error('Error al parsear regla:', error);
            }
        });
    });

    form?.addEventListener('submit', function (e) {
        e.preventDefault();

        const detalles = [];
        const usados = new Set();
        let hasError = false;

        for (const tr of tbody.querySelectorAll('tr')) {
            const selectCentro = tr.querySelector('.detalle-centro');
            const inputPct = tr.querySelector('.detalle-pct');
            
            const centro = selectCentro.value;
            const pct = Number(inputPct.value || 0);

            // Validar campos vacíos
            if (!centro || pct <= 0) {
                mostrarError('Todos los destinos deben tener centro y porcentaje válido.');
                if (!centro) selectCentro.classList.add('is-invalid');
                if (pct <= 0) inputPct.classList.add('is-invalid');
                hasError = true;
                break; // Detener el loop
            }

            // Validar duplicados
            if (usados.has(centro)) {
                mostrarError('No puede repetir el mismo centro de destino en más de una fila.');
                selectCentro.classList.add('is-invalid');
                hasError = true;
                break; // Detener el loop
            }
            
            usados.add(centro);

            detalles.push({
                centro_destino_id: Number(centro),
                porcentaje: Number(pct.toFixed(2))
            });
        }

        // Si hubo error en el loop, abortamos el guardado
        if (hasError) return;

        const total = detalles.reduce((acc, d) => acc + d.porcentaje, 0);
        if (Math.abs(total - 100) > 0.01) {
            mostrarError('La suma de los porcentajes debe ser exactamente 100%.');
            return;
        }

        const payload = new FormData(form);
        payload.append('detalles', JSON.stringify(detalles));

        const original = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

        fetch(urlGuardar, {
            method: 'POST',
            body: payload,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then((response) => {
            if (!response.ok) {
                return response.json().then((err) => {
                    throw new Error(err.message || 'Error en el servidor');
                });
            }
            return response.json();
        })
        .then((data) => {
            if (data.status === 'success') {
                if (modal) modal.hide();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message || 'Regla guardada correctamente.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => window.location.reload());
                } else {
                    alert(data.message || 'Guardado correctamente.');
                    window.location.reload();
                }
            } else {
                throw new Error(data.message || 'No se pudo guardar.');
            }
        })
        .catch((error) => {
            mostrarError(error.message);
        })
        .finally(() => {
            btnGuardar.innerHTML = original;
            recalcTotal();
        });
    });
});