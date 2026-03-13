(function(){
  document.addEventListener('DOMContentLoaded', function(){
    
    // 1. Lógica para el interruptor de "Gasto Recurrente"
    const sw = document.getElementById('esRecurrente');
    const bloque = document.getElementById('bloqueRecurrente');
    if (sw && bloque) {

      const sync = () => bloque.classList.toggle('d-none', !sw.checked);
      sw.addEventListener('change', sync); 
      sync(); // Ejecutar al cargar la página para establecer el estado inicial
    }

    // 2. Inicialización del selector avanzado (TomSelect)
    const conceptoSelect = document.getElementById('idConceptoGasto');
    if (conceptoSelect && window.TomSelect) {
      new TomSelect(conceptoSelect, {
        create: false, 
        sortField: { field: 'text', direction: 'asc' }
      });

      const sync = ()=> bloque.classList.toggle('d-none', !sw.checked);
      sw.addEventListener('change', sync);
      sync();

    }

    // Nota: Ya no inicializamos los tooltips aquí porque 
    // public/assets/js/tablas/renderizadores.js ya lo hace globalmente.
    
  });
})();