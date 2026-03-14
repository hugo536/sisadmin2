(function(){
  document.addEventListener('DOMContentLoaded', function(){
    
    // ==========================================
    // 1. Lógica para el interruptor de "Gasto Recurrente"
    // ==========================================
    const sw = document.getElementById('esRecurrente');
    const bloque = document.getElementById('bloqueRecurrente');
    
    // Verificamos que ambos elementos existan en el DOM antes de actuar
    if (sw && bloque) {
      const sync = () => bloque.classList.toggle('d-none', !sw.checked);
      sw.addEventListener('change', sync); 
      sync(); // Ejecutar al inicio para establecer el estado correcto
    }

    // ==========================================
    // 2. Inicialización del selector avanzado (TomSelect)
    // ==========================================
    if (window.TomSelect) {
      // AQUÍ LA CORRECCIÓN: Apuntamos al Centro de Costo
      const selectoresAvanzados = ['id_centro_costo'];

      selectoresAvanzados.forEach(function(id) {
        const elemento = document.getElementById(id);
        
        if (elemento) {
          new TomSelect(elemento, {
            create: false, 
            sortField: { field: 'text', direction: 'asc' },
            placeholder: elemento.getAttribute('placeholder') || 'Seleccionar...'
          });
        }
      });
    }

    // ==========================================
    // 3. Notas de estandarización global
    // ==========================================
    // Nota: Los tooltips de Bootstrap y la inicialización de tablas 
    // (incluyendo el buscador dinámico) se manejan automáticamente a través de:
    // - public/assets/js/tablas/renderizadores.js
    
  });
})();