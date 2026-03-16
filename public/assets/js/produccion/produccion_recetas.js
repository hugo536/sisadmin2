/**
 * SISTEMA SISADMIN2 - Módulo de Producción (Recetas BOM)
 * Optimizado con Tom Select AJAX y SweetAlert2.
 * Versión FINAL CORREGIDA - Costeo Operativo Estándar
 */

document.addEventListener('DOMContentLoaded', function() {
    initFiltrosTablas();
    initFormularioRecetas();
    initAccionesRecetaPendiente();
    initGestionParametrosCatalogo();
    initGuardadoReceta(); 
});

function initFiltrosTablas() {
    const input = document.getElementById('recetaSearch');
    const select = document.getElementById('recetaFiltroEstado');
    const table = document.getElementById('tablaRecetas');
    if (!input || !table) return;

    const filterFn = () => {
        const term = input.value.toLowerCase();
        const estado = select ? select.value : '';
        table.querySelectorAll('tbody tr').forEach(row => {
            const matchText = (row.getAttribute('data-search') || '').toLowerCase().includes(term);
            const matchEstado = estado === '' || (row.getAttribute('data-estado') || '') === estado;
            row.style.display = (matchText && matchEstado) ? '' : 'none';
        });
    };

    input.addEventListener('keyup', filterFn);
    if (select) select.addEventListener('change', filterFn);
}

function initFormularioRecetas() {
    const parseNumero = (valor) => {
        if (typeof valor === 'number') return Number.isFinite(valor) ? valor : 0;
        const normalizado = String(valor ?? '').trim().replace(',', '.');
        const numero = parseFloat(normalizado);
        return Number.isFinite(numero) ? numero : 0;
    };

    const templateInsumo = document.getElementById('detalleRecetaTemplate');
    const resumenItems = document.getElementById('bomResumen');
    const costoTotalEl = document.getElementById('costoTotalCalculado');
    const btnAgregarInsumo = document.getElementById('btnAgregarInsumo');
    const listaInsumos = document.getElementById('listaInsumosReceta');
    const inputProducto = document.getElementById('newProducto');

    const instanciasTomSelect = new WeakMap();
    let tomProductoDestino = null;

    const btnAgregarParametro = document.getElementById('btnAgregarParametro');
    const contenedorParametros = document.getElementById('contenedorParametros');
    const templateParametro = document.getElementById('parametroTemplate');
    const emptyParametros = document.getElementById('emptyParametros');
    
    const btnAgregarMod = document.getElementById('btnAgregarMod');
    const contenedorMod = document.getElementById('contenedorMod');
    const templateMod = document.getElementById('modTemplate');
    
    const btnAgregarCif = document.getElementById('btnAgregarCif');
    const contenedorCif = document.getElementById('contenedorCif');
    const templateCif = document.getElementById('cifTemplate');

    const inputRendimientoBase = document.getElementById('newRendimientoBase');
    const inputTiempoProduccionHoras = document.getElementById('newTiempoProduccionHoras');
    const totalBomEl = document.getElementById('totalBomCalculado');
    const totalBomUnitEl = document.getElementById('totalBomUnitCalculado');
    const totalModEl = document.getElementById('totalModCalculado');
    const totalModUnitEl = document.getElementById('totalModUnitCalculado');
    const totalCifEl = document.getElementById('totalCifCalculado');
    const totalCifUnitEl = document.getElementById('totalCifUnitCalculado');
    const costoTotalUnitarioEl = document.getElementById('costoTotalUnitarioCalculado');
    const tabsRecetaCostos = document.getElementById('tabsRecetaCostos');

    function initTomSelectAjax(selectEl, placeholderText, onChangeCallback = null) {
        if (!selectEl || typeof TomSelect === 'undefined') return null;
        selectEl.classList.remove('form-select', 'form-control', 'form-select-sm');
        return new TomSelect(selectEl, {
            valueField: 'id',
            labelField: 'nombre',
            searchField: ['nombre', 'sku'],
            maxItems: 1,
            maxOptions: 30,
            loadThrottle: 300,
            placeholder: placeholderText,
            preload: true,
            load: function(query, callback) {
                const url = new URL(window.location.href);
                url.searchParams.set('accion', 'buscar_insumos_ajax');
                url.searchParams.set('q', query);
                fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(json => callback(json.success && json.data ? json.data : []))
                    .catch(() => callback());
            },
            render: {
                option: (item, escape) => `<div class="p-2 border-bottom">
                    <div class="fw-bold text-dark">${escape(item.nombre)}</div>
                    <div class="small text-muted d-flex justify-content-between">
                        <span>SKU: ${escape(item.sku || '-')}</span>
                        <span class="text-primary fw-semibold">S/ ${Number(item.costo_calculado || 0).toFixed(4)}</span>
                    </div>
                </div>`,
                item: (item, escape) => `<span class="text-truncate d-inline-block align-middle" style="max-width: 95%;" data-costo="${item.costo_calculado || 0}">${escape(item.nombre)}</span>`
            },
           onChange: function(value) { if (onChangeCallback) onChangeCallback(value, this); }
        });
    }

    if (inputProducto) {
        tomProductoDestino = initTomSelectAjax(inputProducto, 'Seleccione producto a fabricar...');
    }

    const getProductoDestinoId = () => {
        const hidden = document.getElementById('newIdProductoHidden');
        if (hidden && hidden.value) return hidden.value;
        return tomProductoDestino ? tomProductoDestino.getValue() : '';
    };

    const getTiempoProduccionHoras = () => {
        return Math.max(parseNumero(inputTiempoProduccionHoras?.value || 0), 0);
    };

    const actualizarEstadoTabsCostos = () => {
        if (!tabsRecetaCostos) return;
        tabsRecetaCostos.querySelectorAll('.nav-link').forEach((tabBtn) => {
            const activo = tabBtn.classList.contains('active');
            tabBtn.classList.toggle('text-primary', activo);
            tabBtn.classList.toggle('text-secondary', !activo);
        });
    };

    tabsRecetaCostos?.querySelectorAll('[data-bs-toggle="tab"]').forEach((tabBtn) => {
        tabBtn.addEventListener('shown.bs.tab', actualizarEstadoTabsCostos);
    });
    actualizarEstadoTabsCostos();

    const limpiarFormularioReceta = () => {
        const form = document.getElementById('formCrearReceta');
        if (form) form.reset();

        listaInsumos.innerHTML = '';
        contenedorParametros.innerHTML = '';
        if (contenedorMod) contenedorMod.innerHTML = '';
        if (contenedorCif) contenedorCif.innerHTML = '';
        if (emptyParametros) emptyParametros.style.display = 'block';
        if (resumenItems) resumenItems.textContent = '0 insumos agregados.';
        if (costoTotalEl) costoTotalEl.textContent = 'S/ 0.0000';
        if (totalBomEl) totalBomEl.textContent = 'S/ 0.0000';
        if (totalModEl) totalModEl.textContent = 'S/ 0.0000';
        if (totalCifEl) totalCifEl.textContent = 'S/ 0.0000';
        actualizarEstadoTabsCostos();

        const inputCodigo = document.getElementById('newCodigo');
        if (inputCodigo) { inputCodigo.removeAttribute('readonly'); inputCodigo.classList.remove('bg-light'); }

        const inputVersion = document.getElementById('newVersion');
        if (inputVersion) { inputVersion.removeAttribute('readonly'); inputVersion.classList.remove('bg-light'); }

        const inputUnidad = document.getElementById('newUnidadRendimiento');
        if (inputUnidad) { inputUnidad.removeAttribute('readonly'); inputUnidad.classList.remove('bg-light'); inputUnidad.value = 'UND'; }
        if (inputRendimientoBase) inputRendimientoBase.value = '1';
        if (inputTiempoProduccionHoras) inputTiempoProduccionHoras.value = '1';

        const hiddenIdBase = document.getElementById('newIdRecetaBase');
        if (hiddenIdBase) hiddenIdBase.value = '0';
        
        const hiddenIdProd = document.getElementById('newIdProductoHidden');
        if (hiddenIdProd) hiddenIdProd.value = '';

        if (tomProductoDestino) tomProductoDestino.clear(true);

        const selectCont = document.getElementById('productoSelectContainer');
        const displayCont = document.getElementById('productoDisplayContainer');
        const displayNombre = document.getElementById('newProductoNombreDisplay');

        if (selectCont) selectCont.style.display = '';
        if (displayCont) displayCont.style.display = 'none';
        if (displayNombre) displayNombre.textContent = '';

        const selectProducto = document.getElementById('newProducto');
        if (selectProducto) {
            selectProducto.disabled = false;
            selectProducto.required = true;
        }
    };

    const calcularResumenYCostos = () => {
        const rows = document.querySelectorAll('.detalle-row');
        let totalItems = 0, costoTotal = 0;

        rows.forEach(row => {
            const select = row.querySelector('.select-insumo');
            const inputCant = row.querySelector('.input-cantidad');
            const inputMerma = row.querySelector('.input-merma');
            const inputCostoUnitario = row.querySelector('.input-costo-unitario');
            const inputCostoItem = row.querySelector('.input-costo-item');

            if (select && select.value && inputCant) {
                totalItems++;
                const cantidad = parseNumero(inputCant.value);
                const merma = parseNumero(inputMerma.value);
                const costoUnitario = parseNumero(inputCostoUnitario.value);

                const cantidadReal = cantidad * (1 + (merma / 100));
                const costoItem = cantidadReal * costoUnitario;
                costoTotal += costoItem;

                if (inputCostoItem) inputCostoItem.value = costoItem.toFixed(4);
            } else if (inputCostoItem) {
                inputCostoItem.value = '0.0000';
            }
        });

        if (resumenItems) resumenItems.textContent = `${totalItems} insumos agregados.`;
        let costoMod = 0;
        document.querySelectorAll('.mod-row').forEach(row => {
            const costoBase = parseNumero(row.querySelector('.mod-costo')?.value);
            costoMod += costoBase;
        });
        let costoCif = 0;
        document.querySelectorAll('.cif-row').forEach(row => {
            costoCif += parseNumero(row.querySelector('.cif-costo')?.value);
        });

        // NUEVO CÓDIGO CORREGIDO:
        const rendimientoBase = Math.max(parseNumero(inputRendimientoBase?.value || 1), 0);
        const divisorUnidad = rendimientoBase > 0 ? rendimientoBase : 1;
        
        const costoBomUnit = costoTotal / divisorUnidad;
        // Corrección: Ahora el MOD también se divide entre la cantidad del lote (Rendimiento Base)
        const costoModUnit = costoMod / divisorUnidad; 
        const costoCifUnit = costoCif / divisorUnidad;
        const costoTotalReceta = costoTotal + costoMod + costoCif;
        const costoTotalUnitario = costoBomUnit + costoModUnit + costoCifUnit;

        if (totalBomEl) totalBomEl.textContent = `S/ ${costoTotal.toFixed(4)}`;
        if (totalBomUnitEl) totalBomUnitEl.textContent = `S/ ${costoBomUnit.toFixed(4)}`;
        if (totalModEl) totalModEl.textContent = `S/ ${costoMod.toFixed(4)}`;
        if (totalModUnitEl) totalModUnitEl.textContent = `S/ ${costoModUnit.toFixed(4)}`;
        if (totalCifEl) totalCifEl.textContent = `S/ ${costoCif.toFixed(4)}`;
        if (totalCifUnitEl) totalCifUnitEl.textContent = `S/ ${costoCifUnit.toFixed(4)}`;
        if (costoTotalEl) costoTotalEl.textContent = `S/ ${costoTotalReceta.toFixed(4)}`;
        if (costoTotalUnitarioEl) costoTotalUnitarioEl.textContent = `S/ ${costoTotalUnitario.toFixed(4)}`;
    };

    const validarInsumoSeleccionado = (valorSeleccionado, tomInstance) => {
        if (!valorSeleccionado) return true;
        const idProductoFinal = getProductoDestinoId();
        if (idProductoFinal !== '' && valorSeleccionado === idProductoFinal) {
            Swal.fire({ icon: 'error', title: 'Acción no permitida', text: 'No puedes usar el producto final como insumo de su propia receta.' });
            tomInstance.clear(true);
            return false;
        }
        let coincidencias = 0;
        document.querySelectorAll('.select-insumo').forEach(sel => { if (sel.value === valorSeleccionado) coincidencias++; });
        if (coincidencias > 1) {
            Swal.fire({ icon: 'warning', title: 'Insumo duplicado', text: 'Este insumo ya fue agregado a la receta.' });
            tomInstance.clear(true);
            return false;
        }
        return true;
    };

    const crearFilaInsumo = () => {
        if (!getProductoDestinoId()) {
            Swal.fire({ icon: 'info', title: 'Atención', text: 'Por favor, seleccione el "Producto Destino" primero.', confirmButtonText: 'Entendido' });
            return null;
        }

        if (!templateInsumo || !listaInsumos) return null;
        const fragment = templateInsumo.content.cloneNode(true);
        
        let row = fragment.querySelector('.detalle-row');
        if (!row) row = fragment.firstElementChild; 
        
        if (!row) {
            console.error('El template de insumos está vacío o es inválido en el HTML.');
            return null;
        }

        const selectInsumo = row.querySelector('.select-insumo');
        if (!selectInsumo) return null;

        const inputCostoUni = row.querySelector('.input-costo-unitario');
        if (inputCostoUni) {
            inputCostoUni.removeAttribute('readonly');
            inputCostoUni.classList.remove('bg-light');
            inputCostoUni.addEventListener('input', calcularResumenYCostos);
        }

        const inputCant = row.querySelector('.input-cantidad');
        if (inputCant) inputCant.addEventListener('input', calcularResumenYCostos);
        
        const inputMerma = row.querySelector('.input-merma');
        if (inputMerma) inputMerma.addEventListener('input', calcularResumenYCostos);

        listaInsumos.appendChild(fragment);

        const tom = initTomSelectAjax(selectInsumo, 'Buscar insumo...', (value, tomInstance) => {
            if (!validarInsumoSeleccionado(value, tomInstance)) return;
            let costoBD = 0;
            if (value) {
                const opt = tomInstance.options[value];
                if (opt) costoBD = parseNumero(opt.costo_calculado);
            }
            if (inputCostoUni) inputCostoUni.value = costoBD.toFixed(4);
            calcularResumenYCostos();
        });

        instanciasTomSelect.set(selectInsumo, tom);
        calcularResumenYCostos();
        return row;
    };

    if (btnAgregarInsumo) btnAgregarInsumo.addEventListener('click', crearFilaInsumo);
    inputRendimientoBase?.addEventListener('input', calcularResumenYCostos);
    inputTiempoProduccionHoras?.addEventListener('input', calcularResumenYCostos);

    // --- EL NUEVO EVENT LISTENER PARA EL CIF ---
    document.addEventListener('change', e => {
        if (e.target && e.target.classList.contains('cif-concepto-select')) {
            const option = e.target.options[e.target.selectedIndex];
            const nombre = option.getAttribute('data-nombre') || '';
            const row = e.target.closest('.cif-row');
            if (row) {
                const hiddenInput = row.querySelector('.cif-concepto-nombre');
                if (hiddenInput) {
                    hiddenInput.value = nombre;
                }
            }
        }
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-remove-row');
        if (btn) {
            const row = btn.closest('.detalle-row');
            if (row) {
                const select = row.querySelector('.select-insumo');
                const tom = select ? instanciasTomSelect.get(select) : null;
                if (tom) tom.destroy();
                row.remove();
                calcularResumenYCostos();
            }
        }
    });

    const crearFilaParametro = (data = null) => {
        if (!templateParametro || !contenedorParametros) return;
        const fragment = templateParametro.content.cloneNode(true);
        
        let row = fragment.querySelector('.parametro-row');
        if (!row) row = fragment.firstElementChild;
        if (!row) return;

        const selectParametro = row.querySelector('select[name="parametro_id[]"]');
        const inputValor = row.querySelector('input[name="parametro_valor[]"]');

        if (selectParametro && data?.id_parametro) selectParametro.value = String(data.id_parametro);
        if (inputValor && data?.valor_objetivo !== undefined) inputValor.value = parseNumero(data.valor_objetivo).toFixed(4);

        contenedorParametros.appendChild(fragment);
        if (emptyParametros) emptyParametros.style.display = 'none';
    };

    if (btnAgregarParametro) btnAgregarParametro.addEventListener('click', () => crearFilaParametro());

    if (btnAgregarMod) btnAgregarMod.addEventListener('click', () => {
        if (!templateMod || !contenedorMod) return;
        const fragment = templateMod.content.cloneNode(true);
        fragment.querySelectorAll('input').forEach(inp => inp.addEventListener('input', calcularResumenYCostos));
        contenedorMod.appendChild(fragment);
        calcularResumenYCostos();
    });

    // --- NUEVA LÓGICA DE AGREGAR CIF (Simplificada) ---
    if (btnAgregarCif) btnAgregarCif.addEventListener('click', () => {
        if (!templateCif || !contenedorCif) return;
        const fragment = templateCif.content.cloneNode(true);
        fragment.querySelectorAll('input').forEach(inp => inp.addEventListener('input', calcularResumenYCostos));
        contenedorCif.appendChild(fragment);
        calcularResumenYCostos();
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('.js-remove-param');
        if (btn) {
            const row = btn.closest('.parametro-row');
            if (row) {
                row.remove();
                if (emptyParametros) emptyParametros.style.display = contenedorParametros.querySelectorAll('.parametro-row').length === 0 ? 'block' : 'none';
            }
        }

        const btnRemoveMod = e.target.closest('.js-remove-mod');
        if (btnRemoveMod) {
            btnRemoveMod.closest('.mod-row')?.remove();
            calcularResumenYCostos();
        }

        const btnRemoveCif = e.target.closest('.js-remove-cif');
        if (btnRemoveCif) {
            btnRemoveCif.closest('.cif-row')?.remove();
            calcularResumenYCostos();
        }
    });

    const crearFilaInsumoConData = (detalle = null) => {
        const row = crearFilaInsumo();
        if (!detalle || !row) return;

        const select = row.querySelector('.select-insumo');
        if (select) {
            const tom = instanciasTomSelect.get(select);
            if (tom) {
                tom.addOption({
                    id: detalle.id_insumo,
                    nombre: detalle.insumo_nombre || `Insumo ID: ${detalle.id_insumo}`,
                    sku: detalle.sku || '',
                    costo_calculado: detalle.costo_unitario
                });
                tom.setValue(String(detalle.id_insumo), true);
            }
        }

        const inputEtapa = row.querySelector('.input-etapa-hidden');
        if(inputEtapa) inputEtapa.value = detalle.etapa || 'General';
        
        const inputCantidad = row.querySelector('.input-cantidad');
        if (inputCantidad) inputCantidad.value = parseNumero(detalle.cantidad_por_unidad).toFixed(4);
        
        const inputMerma = row.querySelector('.input-merma');
        if (inputMerma) inputMerma.value = parseNumero(detalle.merma_porcentaje).toFixed(4);
        
        const inputCostoUnitario = row.querySelector('.input-costo-unitario');
        if (inputCostoUnitario) inputCostoUnitario.value = parseNumero(detalle.costo_unitario).toFixed(4);

        calcularResumenYCostos();
    };

    window.produccionRecetaFormAPI = {
        limpiarFormularioReceta,
        setCabecera(data) {
            if (!data) return;
            const inputCodigo = document.getElementById('newCodigo');
            const inputVersion = document.getElementById('newVersion');
            const hiddenIdProd = document.getElementById('newIdProductoHidden');
            const inputUnidad = document.getElementById('newUnidadRendimiento');
            const inputRendimiento = document.getElementById('newRendimientoBase');
            const inputTiempo = document.getElementById('newTiempoProduccionHoras');
            
            if(inputCodigo) {
                inputCodigo.value = data.codigo || '';
                inputCodigo.setAttribute('readonly', 'true');
                inputCodigo.classList.add('bg-light');
            }
            if(inputVersion) {
                inputVersion.value = data.version || '1';
                inputVersion.setAttribute('readonly', 'true');
                inputVersion.classList.add('bg-light');
            }
            if(inputUnidad) {
                inputUnidad.value = data.unidad || data.unidad_rendimiento || 'UND';
                inputUnidad.setAttribute('readonly', 'true');
                inputUnidad.classList.add('bg-light');
            }
            if (inputRendimiento) inputRendimiento.value = parseNumero(data.rendimiento_base || 1).toFixed(4);
            if (inputTiempo) inputTiempo.value = parseNumero(data.tiempo_produccion_horas || 1).toFixed(4);

            if(hiddenIdProd) hiddenIdProd.value = data.id_producto || '';

            const containerSelect = document.getElementById('productoSelectContainer');
            const containerDisplay = document.getElementById('productoDisplayContainer');
            const displayNombre = document.getElementById('newProductoNombreDisplay');

            if (containerSelect) containerSelect.style.display = 'none';
            if (containerDisplay) containerDisplay.style.display = 'block';
            if (displayNombre) displayNombre.textContent = data.producto_nombre || 'Producto';

            const selectProducto = document.getElementById('newProducto');
            if (selectProducto) {
                selectProducto.disabled = true;
                selectProducto.required = false;
            }
        },
        setCabeceraNuevaVersion(data) {
            if (!data) return;
            const inputCodigo = document.getElementById('newCodigo');
            const inputVersion = document.getElementById('newVersion');
            const hiddenIdProd = document.getElementById('newIdProductoHidden');
            const inputUnidad = document.getElementById('newUnidadRendimiento');
            const inputRendimiento = document.getElementById('newRendimientoBase');
            const inputTiempo = document.getElementById('newTiempoProduccionHoras');
            
            if (inputCodigo) {
                inputCodigo.value = data.codigo || '';
                inputCodigo.setAttribute('readonly', 'true');
                inputCodigo.classList.add('bg-light');
            }
            if (inputVersion) {
                inputVersion.value = data.version || '';
                inputVersion.setAttribute('readonly', 'true');
                inputVersion.classList.add('bg-light');
            }
            if (hiddenIdProd) hiddenIdProd.value = data.id_producto || '';
            if (inputUnidad) inputUnidad.value = data.unidad_rendimiento || 'UND';
            if (inputRendimiento) inputRendimiento.value = parseNumero(data.rendimiento_base || 1).toFixed(4);
            if (inputTiempo) inputTiempo.value = parseNumero(data.tiempo_produccion_horas || 1).toFixed(4);

            const containerSelect = document.getElementById('productoSelectContainer');
            const containerDisplay = document.getElementById('productoDisplayContainer');
            const displayNombre = document.getElementById('newProductoNombreDisplay');

            if (containerSelect) containerSelect.style.display = 'none';
            if (containerDisplay) containerDisplay.style.display = 'block';
            if (displayNombre) displayNombre.textContent = data.producto_nombre || 'Producto';

            const selectProducto = document.getElementById('newProducto');
            if (selectProducto) {
                selectProducto.disabled = true;
                selectProducto.required = false;
            }
        },
        setIdRecetaBase(idReceta) {
            const hiddenBase = document.getElementById('newIdRecetaBase');
            if(hiddenBase) hiddenBase.value = String(idReceta || 0);
        },
        cargarDetalles(detalles) {
            listaInsumos.innerHTML = '';
            (detalles || []).forEach(d => crearFilaInsumoConData(d));
            calcularResumenYCostos();
        },
        cargarParametros(parametros) {
            contenedorParametros.innerHTML = '';
            (parametros || []).forEach(p => crearFilaParametro(p));
            if (emptyParametros) emptyParametros.style.display = contenedorParametros.querySelectorAll('.parametro-row').length === 0 ? 'block' : 'none';
        },
        cargarMod(mod) {
            if (!contenedorMod) return;
            contenedorMod.innerHTML = '';
            (mod || []).forEach((fila) => {
                btnAgregarMod?.click();
                const row = contenedorMod.querySelector('.mod-row:last-child');
                if (!row) return;
                row.querySelector('input[name="mod_perfil_puesto[]"]').value = fila.perfil_puesto || '';
                row.querySelector('input[name="mod_costo_hora_estimado[]"]').value = parseNumero(fila.costo_hora_estimado).toFixed(4);
            });
        },
        cargarCif(cif) {
            if (!contenedorCif) return;
            contenedorCif.innerHTML = '';
            (cif || []).forEach((fila) => {
                btnAgregarCif?.click();
                const row = contenedorCif.querySelector('.cif-row:last-child');
                if (!row) return;
                
                // Actualizado para rellenar el select de conceptos
                const selectConcepto = row.querySelector('select[name="cif_id_activo[]"]');
                if (selectConcepto) {
                    selectConcepto.value = fila.id_activo || ''; // Usamos id_activo que guarda el ID de tu nueva tabla
                }
                const inputNombre = row.querySelector('input[name="cif_concepto[]"]');
                if(inputNombre) inputNombre.value = fila.concepto || '';
                
                const inputCosto = row.querySelector('input[name="cif_costo_estimado[]"]');
                if(inputCosto) inputCosto.value = parseNumero(fila.costo_estimado).toFixed(4);
            });
        }
    };
}

function initAccionesRecetaPendiente() {
    const modalEl = document.getElementById('modalCrearReceta');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const form = document.getElementById('formCrearReceta');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const selectVersionBase = document.getElementById('newVersionBase');
    const contenedorVersionesPrevias = document.getElementById('contenedorVersionesPrevias');
    const ayudaVersionesPrevias = document.getElementById('ayudaVersionesPrevias');
    const modalTitle = document.getElementById('modalCrearRecetaTitle');

    const postAccion = async (accion, data = {}) => {
        const body = new URLSearchParams();
        body.append('accion', accion);
        if (data && typeof data === 'object') {
            Object.entries(data).forEach(([k, v]) => {
                if (v !== undefined && v !== null) body.append(k, String(v));
            });
        }

        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString(),
        });

        const raw = await resp.text();
        let json;
        try { json = JSON.parse(raw); } catch (e) { throw new Error('Respuesta inválida del servidor.'); }
        if (!json || json.success !== true) throw new Error((json && json.message) || 'Error en la operación.');
        return json.data;
    };

    const cargarRecetaEnFormulario = async (idReceta) => {
        const api = window.produccionRecetaFormAPI;
        if (!api) return;
        const dataReceta = await postAccion('obtener_receta_version_ajax', { id_receta: idReceta });
        if (!dataReceta || typeof dataReceta !== 'object') throw new Error('No se pudo cargar la receta seleccionada.');

        api.setCabecera(dataReceta);
        api.setIdRecetaBase(idReceta);
        api.cargarDetalles(dataReceta.detalles || []);
        api.cargarParametros(dataReceta.parametros || []);
        api.cargarMod(dataReceta.mano_obra || []);
        api.cargarCif(dataReceta.cif || []);
    };

    modalEl.addEventListener('hidden.bs.modal', function () {
        const api = window.produccionRecetaFormAPI;
        if (api) api.limpiarFormularioReceta();
        else if (form) form.reset();

        if (selectVersionBase) {
            selectVersionBase.innerHTML = '<option value="">Seleccione versión...</option>';
            selectVersionBase.value = '';
        }
        if (contenedorVersionesPrevias) contenedorVersionesPrevias.style.display = 'none';
        if (ayudaVersionesPrevias) ayudaVersionesPrevias.style.display = 'none';
        if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nueva receta';
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-agregar-receta');
        if (!btn || !form) return;
        const api = window.produccionRecetaFormAPI;
        if (api) {
            api.setCabecera({
                codigo: btn.getAttribute('data-codigo') || '',
                version: btn.getAttribute('data-version') || '1',
                id_producto: btn.getAttribute('data-id-producto') || '',
                producto_nombre: btn.getAttribute('data-producto') || '',
                unidad: btn.getAttribute('data-unidad') || 'UND',
                rendimiento_base: 1,
                tiempo_produccion_horas: 1
            });
        }
        modal.show();
    });

    const btnPrincipalNuevaReceta = document.getElementById('btnNuevaReceta');
    if (btnPrincipalNuevaReceta) {
        btnPrincipalNuevaReceta.addEventListener('click', async function () {
            const inputCodigo = document.getElementById('newCodigo');
            if (inputCodigo) {
                inputCodigo.value = 'Generando...';
                inputCodigo.setAttribute('readonly', 'true');
                inputCodigo.classList.add('bg-light'); 
            }
            
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('accion', 'obtener_siguiente_codigo_ajax');
                
                const resp = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const data = await resp.json();
                
                if (data.success && data.codigo && inputCodigo) {
                    inputCodigo.value = data.codigo;
                } else {
                    if (inputCodigo) inputCodigo.value = 'REC-XXXX'; 
                }
            } catch (error) {
                console.error("Error al obtener el código autogenerado:", error);
                if (inputCodigo) inputCodigo.value = 'REC-XXXX'; 
            }
            
            const api = window.produccionRecetaFormAPI;
            if (api) {
                api.cargarDetalles([]);
                api.cargarParametros([]);
                api.cargarMod([]);
                api.cargarCif([]);
                api.setIdRecetaBase(0);
                
                if (window.tomProductoDestino) window.tomProductoDestino.clear(true);
            }
        });
    }

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-nueva-version');
        if (!btn || !form) return;
        const idRecetaBase = parseInt(btn.getAttribute('data-id-receta') || '0', 10);
        if (!idRecetaBase) return;

        try {
            if (modalTitle) modalTitle.innerHTML = '<i class="bi bi-files me-2"></i>Nueva versión de receta';
            if (contenedorVersionesPrevias) contenedorVersionesPrevias.style.display = '';
            if (ayudaVersionesPrevias) ayudaVersionesPrevias.style.display = '';

            const datosNuevaVersion = await postAccion('obtener_datos_nueva_version_ajax', { id_receta_base: idRecetaBase });
            const api = window.produccionRecetaFormAPI;
            
            api.setCabeceraNuevaVersion(datosNuevaVersion);
            api.setIdRecetaBase(idRecetaBase);
            api.cargarDetalles(datosNuevaVersion.detalles || []);
            api.cargarParametros(datosNuevaVersion.parametros || []);
            api.cargarMod(datosNuevaVersion.mano_obra || []);
            api.cargarCif(datosNuevaVersion.cif || []);

            const versiones = await postAccion('listar_versiones_receta_ajax', { id_receta_base: idRecetaBase });
            if (selectVersionBase) {
                selectVersionBase.innerHTML = '';
                (Array.isArray(versiones) ? versiones : []).forEach((version) => {
                    const option = document.createElement('option');
                    option.value = String(version.id);
                    option.textContent = `v.${version.version} - ${version.codigo}${Number(version.estado) === 1 ? ' (Activa)' : ''}`;
                    selectVersionBase.appendChild(option);
                });
                if (selectVersionBase.options.length > 0) selectVersionBase.value = selectVersionBase.options[0].value;
            }
            modal.show();
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error de Carga', text: error.message || 'No se pudo cargar la receta para versionar.' });
        }
    });

    selectVersionBase?.addEventListener('change', async function () {
        const idReceta = parseInt(this.value || '0', 10);
        if (!idReceta) return;
        try { await cargarRecetaEnFormulario(idReceta); } 
        catch (error) { Swal.fire({ icon: 'error', title: 'Error de Versión', text: error.message || 'Error al cargar versión.' }); }
    });
}

function initGestionParametrosCatalogo() {
    const form = document.getElementById('formGestionParametroCatalogo');
    if (!form) return;

    const idInput = document.getElementById('idParametroCatalogo');
    const accionInput = document.getElementById('accionParametroCatalogo');
    const btnGuardar = document.getElementById('btnGuardarParametroCatalogo');

    const resetForm = () => {
        form.reset();
        if (idInput) idInput.value = '';
        if (accionInput) accionInput.value = 'crear_parametro_catalogo';
        if (btnGuardar) btnGuardar.textContent = 'Guardar';
    };

    document.getElementById('btnResetParametroCatalogo')?.addEventListener('click', resetForm);
    document.getElementById('modalGestionParametrosCatalogo')?.addEventListener('show.bs.modal', resetForm);

    document.addEventListener('click', function (e) {
        const btnEdit = e.target.closest('.js-editar-param-catalogo');
        if (!btnEdit) return;
        if (idInput) idInput.value = btnEdit.getAttribute('data-id') || '';
        if (accionInput) accionInput.value = 'editar_parametro_catalogo';
        document.getElementById('nombreParametroCatalogo').value = btnEdit.getAttribute('data-nombre') || '';
        document.getElementById('unidadParametroCatalogo').value = btnEdit.getAttribute('data-unidad') || '';
        document.getElementById('descripcionParametroCatalogo').value = btnEdit.getAttribute('data-descripcion') || '';
        if (btnGuardar) btnGuardar.textContent = 'Actualizar';
    });
}

function initGuardadoReceta() {
    const form = document.getElementById('formCrearReceta');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const idProductoDestino = document.getElementById('newIdProductoHidden')?.value 
                               || document.getElementById('newProducto')?.tomselect?.getValue();
        
        if (!idProductoDestino) {
            Swal.fire({ icon: 'warning', title: 'Faltan datos', text: 'Debes seleccionar un Producto Destino.' });
            return;
        }

        const filasInsumos = document.querySelectorAll('.detalle-row');
        if (filasInsumos.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Receta vacía', text: 'Debes agregar al menos un insumo (BOM).' });
            return;
        }

        let filasValidas = true, mensajeError = '';

        filasInsumos.forEach((row, index) => {
            const selectInsumo = row.querySelector('.select-insumo');
            const inputCantidad = row.querySelector('.input-cantidad');

            if (!selectInsumo || !selectInsumo.value) {
                filasValidas = false; mensajeError = `Falta seleccionar el insumo en la fila ${index + 1}.`; return;
            }
            const cantidad = parseFloat(inputCantidad.value);
            if (isNaN(cantidad) || cantidad <= 0) {
                filasValidas = false; mensajeError = `La cantidad en la fila ${index + 1} debe ser mayor a 0.`; return;
            }
        });

        if (!filasValidas) {
            Swal.fire({ icon: 'warning', title: 'Revisa los insumos', text: mensajeError });
            return;
        }

        const filasMod = document.querySelectorAll('.mod-row');
        for (let i = 0; i < filasMod.length; i++) {
            const perfil = filasMod[i].querySelector('input[name="mod_perfil_puesto[]"]')?.value?.trim() || '';
            const costoBase = parseFloat(filasMod[i].querySelector('input[name="mod_costo_hora_estimado[]"]')?.value || '0');
            if (!perfil || !Number.isFinite(costoBase) || costoBase <= 0) {
                Swal.fire({ icon: 'warning', title: 'Revisa Mano de Obra (MOD)', text: `Completa perfil y costo base válido en la fila ${i + 1} de MOD.` });
                return;
            }
        }

        const filasCif = document.querySelectorAll('.cif-row');
        for (let i = 0; i < filasCif.length; i++) {
            const concepto = filasCif[i].querySelector('input[name="cif_concepto[]"]')?.value?.trim() || '';
            const costo = parseFloat(filasCif[i].querySelector('input[name="cif_costo_estimado[]"]')?.value || '0');
            if (!concepto || !Number.isFinite(costo) || costo <= 0) {
                Swal.fire({ icon: 'warning', title: 'Revisa Costos Indirectos (CIF)', text: `Completa concepto y costo válido en la fila ${i + 1} de CIF.` });
                return;
            }
        }

        try {
            const btnSubmit = form.querySelector('button[type="submit"]');
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            }

            const formData = new FormData(form);
            formData.append('accion', 'guardar_receta_ajax');
            formData.set('id_producto', idProductoDestino);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: '¡Guardado!', 
                    text: 'La receta se guardó correctamente.', 
                    showConfirmButton: true,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#0d6efd' // Color azul primary (puedes cambiarlo si usas otro)
                }).then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('modalCrearReceta'))?.hide();
                    window.location.reload(); 
                });
            } else {
                throw new Error(data.message || 'Error desconocido.');
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error al guardar', text: error.message });
        } finally {
            const btnSubmit = form.querySelector('button[type="submit"]');
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-save me-2"></i>Guardar Receta';
            }
        }
    });
}