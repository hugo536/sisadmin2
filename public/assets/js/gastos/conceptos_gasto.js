(function(){
  'use strict';

  const bindOnce = function(element, eventName, handler) {
    if (!element) return;
    const key = `conceptosBound${eventName}`;
    if (element.dataset[key] === '1') return;
    element.addEventListener(eventName, handler);
    element.dataset[key] = '1';
  };

  const initConceptosGasto = function(){
    const app = document.getElementById('gastosConceptosApp');
    if (!app || app.dataset.conceptosInitialized === '1') {
      return;
    }
    app.dataset.conceptosInitialized = '1';

    // ==========================================
    // 1. TomSelect y Lógica de Recurrencia
    // ==========================================
    const sw = document.getElementById('esRecurrente');
    const bloque = document.getElementById('bloqueRecurrente');
    const editarSw = document.getElementById('editarEsRecurrente');
    const editarBloque = document.getElementById('editarBloqueRecurrente');

    if (sw && bloque) {
      const sync = () => bloque.classList.toggle('d-none', !sw.checked);
      bindOnce(sw, 'change', sync);
      sync();
    }

    if (editarSw && editarBloque) {
      const syncEdit = () => editarBloque.classList.toggle('d-none', !editarSw.checked);
      bindOnce(editarSw, 'change', syncEdit);
      syncEdit();
    }

    const tomSelects = {};
    if (window.TomSelect) {
      ['id_centro_costo', 'editar_id_centro_costo'].forEach(function(id) {
        const elemento = document.getElementById(id);
        if (!elemento) return;
        if (elemento.tomselect) {
          tomSelects[id] = elemento.tomselect;
          return;
        }
        tomSelects[id] = new TomSelect(elemento, {
          create: false,
          sortField: { field: 'text', direction: 'asc' },
          placeholder: elemento.getAttribute('placeholder') || 'Seleccionar...'
        });
      });
    }

    // ==========================================
    // 2. Modales
    // ==========================================
    const modalEditarEl = document.getElementById('modalEditarConcepto');
    const modalEditar = (window.bootstrap && modalEditarEl) ? bootstrap.Modal.getOrCreateInstance(modalEditarEl) : null;
    const modalNuevoEl = document.getElementById('modalNuevoConcepto');
    const modalNuevo = (window.bootstrap && modalNuevoEl) ? bootstrap.Modal.getOrCreateInstance(modalNuevoEl) : null;

    const campoId = document.getElementById('editarConceptoId');
    const campoCodigo = document.getElementById('editarConceptoCodigo');
    const campoNombre = document.getElementById('editarConceptoNombre');
    const campoCentro = document.getElementById('editar_id_centro_costo');
    const campoDiaVenc = document.getElementById('editarDiaVencimiento');
    const campoDiasAnt = document.getElementById('editarDiasAnticipacion');

    // Resetear form al cerrar nuevo modal
    if (modalNuevoEl) {
        modalNuevoEl.addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('formNuevoConcepto');
            if (form) form.reset();
            if (tomSelects.id_centro_costo) tomSelects.id_centro_costo.clear(true);
            if (sw && bloque) { sw.checked = false; bloque.classList.add('d-none'); }
        });
    }

    // ==========================================
    // 3. Recarga Silenciosa de Tabla
    // ==========================================
    async function recargarTablaConceptos() {
        const nextUrl = new URL(window.location.href);
        const urlParams = nextUrl.searchParams;

        urlParams.delete('accion');

        const filtroBusqueda = document.getElementById('buscarConcepto');
        const filtroCentroCosto = document.getElementById('filtroCentroCosto');
        const filtroRecurrente = document.getElementById('filtroRecurrente');

        if (filtroBusqueda && filtroBusqueda.value.trim()) urlParams.set('q', filtroBusqueda.value.trim()); else urlParams.delete('q');
        if (filtroCentroCosto && filtroCentroCosto.value !== '') urlParams.set('centro', filtroCentroCosto.value); else urlParams.delete('centro');
        if (filtroRecurrente && filtroRecurrente.value !== '') urlParams.set('recurrente', filtroRecurrente.value); else urlParams.delete('recurrente');

        const tbodyActual = document.querySelector('#conceptosTableBody');
        if (tbodyActual) tbodyActual.style.opacity = '0.4'; 

        try {
            const response = await fetch(nextUrl.toString());
            if (!response.ok) throw new Error('Error en red');
            const html = await response.text();
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const nuevoTbody = doc.querySelector('#conceptosTableBody');
            if (tbodyActual && nuevoTbody) {
                tbodyActual.innerHTML = nuevoTbody.innerHTML;
                tbodyActual.style.opacity = '1';
            }

            window.history.pushState({}, '', nextUrl.toString());
        } catch (error) {
            console.error('Fallo en recarga silenciosa:', error);
            window.location.href = nextUrl.toString();
        }
    }

    // ==========================================
    // 4. Función Genérica para Formularios AJAX
    // ==========================================
    const manejarFormularioAjax = async (formElement, modalInstance) => {
        try {
            const btnSubmit = formElement.querySelector('button[type="submit"]');
            const btnTextOriginal = btnSubmit ? btnSubmit.innerHTML : 'Guardar';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            }

            const formData = new FormData(formElement);
            const response = await fetch(formElement.action || window.location.href, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error('Ocurrió un error de red.');
            const data = await response.json();

            if (data.status === 'success') {
                if (modalInstance) modalInstance.hide();
                await Swal.fire('Éxito', data.mensaje, 'success');
                recargarTablaConceptos();
            } else {
                throw new Error(data.mensaje || 'Error en el servidor.');
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        } finally {
            const btnSubmit = formElement.querySelector('button[type="submit"]');
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = btnSubmit.dataset.originalHtml || '<i class="bi bi-save me-2"></i>Guardar';
            }
        }
    };

    // Vincular forms
    const formNuevo = document.getElementById('formNuevoConcepto');
    if (formNuevo) {
        bindOnce(formNuevo, 'submit', (e) => {
            e.preventDefault();
            formNuevo.querySelector('button[type="submit"]').dataset.originalHtml = formNuevo.querySelector('button[type="submit"]').innerHTML;
            manejarFormularioAjax(formNuevo, modalNuevo);
        });
    }

    const formEditar = document.getElementById('formEditarConcepto');
    if (formEditar) {
        bindOnce(formEditar, 'submit', (e) => {
            e.preventDefault();
            formEditar.querySelector('button[type="submit"]').dataset.originalHtml = formEditar.querySelector('button[type="submit"]').innerHTML;
            manejarFormularioAjax(formEditar, modalEditar);
        });
    }

    // ==========================================
    // 5. Delegación de Eventos en la Tabla (Editar, Toggle, Eliminar)
    // ==========================================
    app.addEventListener('click', async function(ev) {
        
        // --- EDITAR ---
        const btnEditar = ev.target.closest('.js-editar-concepto');
        if (btnEditar && !btnEditar.disabled && modalEditar) {
            const esRecurrente = String(btnEditar.dataset.esRecurrente || '0') === '1';
            
            if (campoId) campoId.value = btnEditar.dataset.id || '';
            if (campoCodigo) campoCodigo.value = btnEditar.dataset.codigo || '';
            if (campoNombre) campoNombre.value = btnEditar.dataset.nombre || '';
            if (editarSw) editarSw.checked = esRecurrente;
            if (campoDiaVenc) campoDiaVenc.value = btnEditar.dataset.diaVencimiento || '';
            if (campoDiasAnt) campoDiasAnt.value = btnEditar.dataset.diasAnticipacion || '0';

            if (tomSelects.editar_id_centro_costo) {
                tomSelects.editar_id_centro_costo.setValue(btnEditar.dataset.idCentro || '', true);
            } else if (campoCentro) {
                campoCentro.value = btnEditar.dataset.idCentro || '';
            }

            if (editarBloque) {
                editarBloque.classList.toggle('d-none', !esRecurrente);
            }
            modalEditar.show();
        }

        // --- ELIMINAR ---
        const btnEliminar = ev.target.closest('.js-eliminar-concepto');
        if (btnEliminar && !btnEliminar.disabled) {
            const id = btnEliminar.dataset.id;
            const res = await Swal.fire({
                title: '¿Estás seguro?',
                text: "No podrás revertir esto. El concepto será eliminado.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (res.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('id', id);
                    // IMPORTANTE: Asegúrate de que esta URL sea válida en tu app JS global o cámbiala hardcodeada si falla
                    const response = await fetch(window.location.pathname + '?ruta=gastos/eliminar_concepto', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        Swal.fire('Eliminado', data.mensaje, 'success');
                        recargarTablaConceptos();
                    } else {
                        throw new Error(data.mensaje);
                    }
                } catch (error) {
                    Swal.fire('Error', error.message || 'No se pudo eliminar.', 'error');
                }
            }
        }

        // --- TOGGLE ESTADO ---
        const btnToggle = ev.target.closest('.js-toggle-estado');
        if (btnToggle && !btnToggle.disabled) {
            const id = btnToggle.dataset.id;
            const estado = btnToggle.dataset.estado;
            
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('estado', estado);
                const response = await fetch(window.location.pathname + '?ruta=gastos/toggle_estado_concepto', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.status === 'success') {
                    // Actualizamos la tabla sin decir nada, o puedes mostrar un toast/swal pequeñito
                    recargarTablaConceptos();
                } else {
                    throw new Error(data.mensaje);
                }
            } catch (error) {
                Swal.fire('Error', error.message || 'No se pudo cambiar el estado.', 'error');
            }
        }
    });

  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initConceptosGasto);
  } else {
    initConceptosGasto();
  }

  document.addEventListener('sisadmin:route-loaded', initConceptosGasto);
})();