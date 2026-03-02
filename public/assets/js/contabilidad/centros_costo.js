(function(){
  document.querySelectorAll('.btn-editar-cc').forEach((btn) => {
    btn.addEventListener('click', function(){
      document.getElementById('cc_id').value = this.dataset.id || '0';
      document.getElementById('cc_codigo').value = this.dataset.codigo || '';
      document.getElementById('cc_nombre').value = this.dataset.nombre || '';
      document.getElementById('cc_estado').value = this.dataset.estado || '1';
    });
  });
})();
