(function () {
  const modal = document.getElementById('modalAlmacen');
  if (!modal) return;

  const id = document.getElementById('almacenId');
  const codigo = document.getElementById('almacenCodigo');
  const nombre = document.getElementById('almacenNombre');
  // NUEVO: Seleccionamos el campo tipo
  const tipo = document.getElementById('almacenTipo'); 
  const descripcion = document.getElementById('almacenDescripcion');
  const estado = document.getElementById('almacenEstado');
  const titulo = modal.querySelector('.modal-title');

  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    
    // Si NO es el botón de editar, limpiamos el formulario para un "Nuevo almacén"
    if (!btn || !btn.classList.contains('btn-editar')) {
      id.value = '0';
      codigo.value = '';
      nombre.value = '';
      // NUEVO: Asignamos 'General' como valor por defecto al crear uno nuevo
      if (tipo) tipo.value = 'General'; 
      descripcion.value = '';
      estado.value = '1';
      if (titulo) titulo.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nuevo almacén';
      return;
    }

    // Si ES el botón de editar, cargamos los datos del botón (dataset) al formulario
    id.value = btn.dataset.id || '0';
    codigo.value = btn.dataset.codigo || '';
    nombre.value = btn.dataset.nombre || '';
    // NUEVO: Cargamos el tipo desde el atributo data-tipo del botón
    if (tipo) tipo.value = btn.dataset.tipo || 'General'; 
    descripcion.value = btn.dataset.descripcion || '';
    estado.value = btn.dataset.estado || '1';
    if (titulo) titulo.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar almacén';
  });
})();