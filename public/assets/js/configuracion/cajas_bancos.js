(function () {
  const modal = document.getElementById('modalCajaBanco');
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
