(function(){
  document.addEventListener('DOMContentLoaded', function(){
    
    // ==========================================
    // 1. Inicialización de selectores avanzados (TomSelect)
    // ==========================================
    if (window.TomSelect) {
      // Agregamos los IDs de todos los selects que queremos mejorar
      const selectoresAvanzados = ['idConceptoGasto', 'id_proveedor'];

      selectoresAvanzados.forEach(function(id) {
        const elemento = document.getElementById(id);
        
        // Si el elemento existe en esta vista, lo inicializamos
        if (elemento) {
          new TomSelect(elemento, {
            create: false, 
            sortField: { field: 'text', direction: 'asc' },
            placeholder: elemento.getAttribute('placeholder') || 'Seleccione una opción...'
          });
        }
      });
    }

    // ==========================================
    // 2. Notas de estandarización global
    // ==========================================
    // Nota: Los tooltips y la inicialización de la tabla de registros
    // (con su buscador) ya son manejados globalmente por renderizadores.js
    
  });
})();