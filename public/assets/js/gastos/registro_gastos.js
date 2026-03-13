(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const conceptoSelect = document.getElementById('idConceptoGasto');
    if (conceptoSelect && window.TomSelect) {
      new TomSelect(conceptoSelect, {create:false, sortField:{field:'text',direction:'asc'}});
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el)=>{
      if (window.bootstrap) new bootstrap.Tooltip(el);
    });
  });
})();
