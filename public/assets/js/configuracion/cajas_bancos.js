(function () {
  const modal = document.getElementById('modalCajaBanco');
  const filtrosForm = document.getElementById('cbFiltersForm');

  if (filtrosForm) {
    const campoBusqueda = document.getElementById('cbFiltroBusqueda');
    const campoTipo = document.getElementById('cbFiltroTipo');
    const campoEstado = document.getElementById('cbFiltroEstado');
    let filtroTimer = null;

    const enviarFiltros = () => {
      if (filtroTimer) {
        window.clearTimeout(filtroTimer);
      }
      filtrosForm.submit();
    };

    if (campoBusqueda) {
      campoBusqueda.addEventListener('input', () => {
        if (filtroTimer) {
          window.clearTimeout(filtroTimer);
        }
        filtroTimer = window.setTimeout(() => {
          filtrosForm.submit();
        }, 350);
      });

      campoBusqueda.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          enviarFiltros();
        }
      });
    }

    [campoTipo, campoEstado].forEach((select) => {
      if (!select) return;
      select.addEventListener('change', enviarFiltros);
    });
  }

  document.querySelectorAll('.switch-estado-cb').forEach((switchInput) => {
    switchInput.addEventListener('change', async function () {
      const id = Number(this.dataset.id || 0);
      const nuevoEstado = this.checked ? 1 : 0;
      const estadoPrevio = this.checked ? 0 : 1;
      const badge = document.getElementById(`badge_status_cb_${id}`);

      if (!id) {
        this.checked = !this.checked;
        return;
      }

      try {
        const formData = new FormData();
        formData.append('id', String(id));
        formData.append('estado', String(nuevoEstado));

        const response = await fetch(`${window.BASE_URL}?ruta=cajas_bancos/toggle_estado`, {
          method: 'POST',
          body: formData,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await response.json();
        if (!response.ok || !data.ok) {
          throw new Error(data.mensaje || 'No se pudo actualizar el estado.');
        }

        if (badge) {
          if (nuevoEstado === 1) {
            badge.className = 'badge bg-success-subtle text-success-emphasis border';
            badge.textContent = 'Activo';
          } else {
            badge.className = 'badge bg-secondary-subtle text-secondary-emphasis border';
            badge.textContent = 'Inactivo';
          }
        }
      } catch (error) {
        this.checked = estadoPrevio === 1;
        if (window.Swal) {
          window.Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'No se pudo actualizar el estado.' });
        }
      }
    });
  });

  if (!modal) return;

  const id = document.getElementById('cbId');
  const codigo = document.getElementById('cbCodigo');
  const nombre = document.getElementById('cbNombre');
  const tipo = document.getElementById('cbTipo');
  const entidad = document.getElementById('cbEntidad');
  const tipoCuenta = document.getElementById('cbTipoCuenta');
  const moneda = document.getElementById('cbMoneda');
  const titular = document.getElementById('cbTitular');
  const numeroCuenta = document.getElementById('cbNumeroCuenta');
  const observaciones = document.getElementById('cbObservaciones');
  const permiteCobros = document.getElementById('cbPermiteCobros');
  const permitePagos = document.getElementById('cbPermitePagos');
  const estado = document.getElementById('cbEstado');
  const titulo = modal.querySelector('.modal-title');

  const reset = () => {
    id.value = '0';
    codigo.value = '';
    nombre.value = '';
    tipo.value = 'BANCO';
    entidad.value = '';
    tipoCuenta.value = '';
    moneda.value = 'PEN';
    titular.value = '';
    numeroCuenta.value = '';
    observaciones.value = '';
    permiteCobros.checked = true;
    permitePagos.checked = true;
    estado.value = '1';
    if (titulo) titulo.textContent = 'Nuevo registro';
  };

  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    if (!btn || !btn.classList.contains('btn-editar-cb')) {
      reset();
      return;
    }

    id.value = btn.dataset.id || '0';
    codigo.value = btn.dataset.codigo || '';
    nombre.value = btn.dataset.nombre || '';
    tipo.value = btn.dataset.tipo || 'BANCO';
    entidad.value = btn.dataset.entidad || '';
    tipoCuenta.value = btn.dataset.tipoCuenta || '';
    moneda.value = btn.dataset.moneda || 'PEN';
    titular.value = btn.dataset.titular || '';
    numeroCuenta.value = btn.dataset.numeroCuenta || '';
    observaciones.value = btn.dataset.observaciones || '';
    permiteCobros.checked = (btn.dataset.permiteCobros || '0') === '1';
    permitePagos.checked = (btn.dataset.permitePagos || '0') === '1';
    estado.value = btn.dataset.estado || '1';

    if (titulo) titulo.textContent = 'Editar registro';
  });
})();
