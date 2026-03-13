(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const sw = document.getElementById('esRecurrente');
    const bloque = document.getElementById('bloqueRecurrente');
    if (sw && bloque) {
      const sync = ()=> bloque.classList.toggle('d-none', !sw.checked);
      sw.addEventListener('change', sync); sync();
    }

    const conceptoSelect = document.getElementById('idConceptoGasto');
    if (conceptoSelect && window.TomSelect) {
      new TomSelect(conceptoSelect, {create:false, sortField:{field:'text',direction:'asc'}});
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el)=>{
      if (window.bootstrap) new bootstrap.Tooltip(el);
    });
  });
})();
