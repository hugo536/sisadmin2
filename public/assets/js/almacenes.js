(function () {
  const modal = document.getElementById('modalAlmacen');
  if (!modal) return;

  const id = document.getElementById('almacenId');
  const codigo = document.getElementById('almacenCodigo');
  const nombre = document.getElementById('almacenNombre');
  const descripcion = document.getElementById('almacenDescripcion');
  const estado = document.getElementById('almacenEstado');
  const titulo = modal.querySelector('.modal-title');

  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    if (!btn || !btn.classList.contains('btn-editar')) {
      id.value = '0';
      codigo.value = '';
      nombre.value = '';
      descripcion.value = '';
      estado.value = '1';
      if (titulo) titulo.textContent = 'Nuevo almacén';
      return;
    }

    id.value = btn.dataset.id || '0';
    codigo.value = btn.dataset.codigo || '';
    nombre.value = btn.dataset.nombre || '';
    descripcion.value = btn.dataset.descripcion || '';
    estado.value = btn.dataset.estado || '1';
    if (titulo) titulo.textContent = 'Editar almacén';
  });
})();
