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
                <input type="number" step="0.01" min="0.01" max="100" class="form-control form-control-sm shadow-none detalle-pct"
                       value="${detalle?.porcentaje ? Number(detalle.porcentaje).toFixed(2) : ''}" required>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-fila" title="Eliminar">
                    <i class="bi bi-dash-circle"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        tr.querySelector('.detalle-pct').addEventListener('input', recalcTotal);
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
        totalLabel.classList.toggle('text-danger', !valid);
        totalLabel.classList.toggle('text-success', valid);

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

        for (const tr of tbody.querySelectorAll('tr')) {
            const centro = tr.querySelector('.detalle-centro').value;
            const pct = Number(tr.querySelector('.detalle-pct').value || 0);

            if (!centro || pct <= 0) {
                alert('Todos los destinos deben tener centro y porcentaje válido.');
                return;
            }

            if (usados.has(centro)) {
                alert('No puede repetir el mismo centro de destino en más de una fila.');
                return;
            }
            usados.add(centro);

            detalles.push({
                centro_destino_id: Number(centro),
                porcentaje: Number(pct.toFixed(2))
            });
        }

        const total = detalles.reduce((acc, d) => acc + d.porcentaje, 0);
        if (Math.abs(total - 100) > 0.01) {
            alert('La suma debe ser 100%.');
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
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Atención', text: error.message });
                } else {
                    alert(error.message);
                }
            })
            .finally(() => {
                btnGuardar.innerHTML = original;
                recalcTotal();
            });
    });

    addRow();
    recalcTotal();
});
