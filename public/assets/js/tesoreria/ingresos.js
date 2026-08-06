(function iniciarIngresosExtraordinarios() {
    'use strict';

    const app = document.getElementById('ingresosExtraApp');
    if (!app) return;

    const modalEl = document.getElementById('modalIngreso');
    const form = document.getElementById('formNuevoIngreso');
    const btnNuevo = document.getElementById('btnNuevoIngreso');
    const btnGuardar = document.getElementById('btnGuardarIngreso');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

    btnNuevo?.addEventListener('click', () => {
        form?.reset();
        const fecha = document.getElementById('ingresoFecha');
        if (fecha && !fecha.value) fecha.value = new Date().toISOString().slice(0, 10);
        modal?.show();
    });

    btnGuardar?.addEventListener('click', async () => {
        if (!form || !form.reportValidity()) return;

        const payload = new FormData();
        payload.set('fecha', document.getElementById('ingresoFecha')?.value || '');
        payload.set('monto', document.getElementById('ingresoMonto')?.value || '');
        payload.set('id_cuenta', document.getElementById('ingresoCuenta')?.value || '');
        payload.set('concepto', document.getElementById('ingresoConcepto')?.value || '');

        btnGuardar.disabled = true;
        try {
            const res = await fetch(app.dataset.urlGuardar, { method: 'POST', body: payload, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.mensaje || 'No se pudo guardar el ingreso.');
            await Swal.fire({ icon: 'success', title: 'Ingreso registrado', text: data.mensaje, confirmButtonText: 'Aceptar' });
            window.location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'No se pudo guardar el ingreso.' });
        } finally {
            btnGuardar.disabled = false;
        }
    });

    document.querySelectorAll('.btn-anular').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const confirm = await Swal.fire({
                icon: 'warning',
                title: '¿Anular ingreso?',
                text: 'El ingreso dejará de sumar en la cuenta de tesorería.',
                showCancelButton: true,
                confirmButtonText: 'Sí, anular',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            });
            if (!confirm.isConfirmed) return;

            const payload = new FormData();
            payload.set('id', btn.dataset.id || '0');
            try {
                const res = await fetch(app.dataset.urlAnular, { method: 'POST', body: payload, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.mensaje || 'No se pudo anular el ingreso.');
                await Swal.fire({ icon: 'success', title: 'Ingreso anulado', text: data.mensaje, confirmButtonText: 'Aceptar' });
                window.location.reload();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'No se pudo anular el ingreso.' });
            }
        });
    });
})();
