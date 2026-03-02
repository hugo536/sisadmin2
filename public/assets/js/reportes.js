(() => {
  const forms = document.querySelectorAll('form[action*="reportes/"]');
  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      const desde = form.querySelector('input[name="fecha_desde"]');
      const hasta = form.querySelector('input[name="fecha_hasta"]');
      if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
        e.preventDefault();
        alert('La fecha desde no puede ser mayor que la fecha hasta.');
      }
    });
  });
})();
