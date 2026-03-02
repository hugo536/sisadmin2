(function(){
  const tbody = document.querySelector('#lineas tbody');
  if(!tbody) return;
  const cuentas = window.CONTA_CUENTAS || [];
  const centros = window.CONTA_CENTROS || [];
  const btn = document.getElementById('agregar-linea');
  const sumDebe = document.getElementById('sumDebe');
  const sumHaber = document.getElementById('sumHaber');
  const balanceEstado = document.getElementById('balanceEstado');
  const form = document.getElementById('form-asiento');
  const submitBtn = form ? form.querySelector('button[type="submit"], button.btn-primary') : null;

  function renderCuentaOptions(){
    return cuentas.map(c => `<option value="${c.id}">${c.codigo} - ${c.nombre}</option>`).join('');
  }

  function renderCentroOptions(){
    return '<option value="">-</option>' + centros.filter(c => Number(c.estado || 0) === 1)
      .map(c => `<option value="${c.id}">${c.codigo} - ${c.nombre}</option>`).join('');
  }

  function recalc(){
    let debe = 0, haber = 0;
    let lineasValidas = true;
    tbody.querySelectorAll('tr').forEach(tr=>{
      const inDebe = tr.querySelector('[name="debe[]"]');
      const inHaber = tr.querySelector('[name="haber[]"]');
      const d = parseFloat(inDebe.value || '0');
      const h = parseFloat(inHaber.value || '0');
      debe += d;
      haber += h;
      const okLinea = (d > 0 && h === 0) || (h > 0 && d === 0);
      lineasValidas = lineasValidas && okLinea;
      inDebe.classList.toggle('is-invalid', !okLinea && (d > 0 || h > 0));
      inHaber.classList.toggle('is-invalid', !okLinea && (d > 0 || h > 0));
    });
    sumDebe.textContent = debe.toFixed(4);
    sumHaber.textContent = haber.toFixed(4);

    const balanceado = (debe.toFixed(4) === haber.toFixed(4)) && debe > 0 && lineasValidas;
    if (balanceEstado){
      balanceEstado.textContent = balanceado ? 'Balanceado' : 'Desbalanceado';
      balanceEstado.className = 'badge ms-2 ' + (balanceado ? 'bg-success' : 'bg-danger');
    }
    if (submitBtn){
      submitBtn.disabled = !balanceado;
    }
  }

  function addRow(){
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><select class="form-select form-select-sm" name="id_cuenta[]">${renderCuentaOptions()}</select></td>
      <td><select class="form-select form-select-sm" name="id_centro_costo[]">${renderCentroOptions()}</select></td>
      <td><input class="form-control form-control-sm" type="number" step="0.0001" name="debe[]" value="0"></td>
      <td><input class="form-control form-control-sm" type="number" step="0.0001" name="haber[]" value="0"></td>
      <td><input class="form-control form-control-sm" name="referencia[]"></td>
      <td><button type="button" class="btn btn-sm btn-outline-danger">x</button></td>`;
    tr.querySelectorAll('input').forEach(i => i.addEventListener('input', recalc));
    tr.querySelector('button').addEventListener('click', ()=>{ tr.remove(); recalc(); });
    tbody.appendChild(tr);
    recalc();
  }

  btn.addEventListener('click', addRow);
  if (form){
    form.addEventListener('submit', function(e){
      recalc();
      if (submitBtn && submitBtn.disabled){
        e.preventDefault();
        alert('El asiento debe estar balanceado y cada línea debe tener Debe o Haber, no ambos.');
      }
    });
  }
  addRow(); addRow();
})();
