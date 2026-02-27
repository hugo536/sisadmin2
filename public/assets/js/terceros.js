(function () {
    'use strict';

    // Configuraciones y Constantes
    const TIPOS_ENTIDAD_CUENTA = ['Banco', 'Caja', 'Billetera Digital', 'Otros'];
    let catalogosFinancieros = { BANCO: [], CAJA: [], BILLETERA: [], OTROS: [] };

    let ENTIDADES_FINANCIERAS = {
        Banco: ['BCP', 'Interbank', 'BBVA', 'Scotiabank', 'Banco de la Nación', 'BanBif', 'Pichincha'],
        Caja: ['Caja Arequipa', 'Caja Huancayo', 'Caja Piura', 'Caja Trujillo', 'Caja Sullana', 'Caja Tacna', 'Caja Ica'],
        'Billetera Digital': ['Yape', 'Plin', 'Tunki', 'Bim', 'Lukita', 'Mercado Pago'],
        Otros: []
    };
    let CATALOGO_CAJAS_BANCOS = {
        Banco: [],
        Caja: [],
        'Billetera Digital': [],
        Otros: []
    };
    const TIPOS_CUENTA_BANCO = ['Ahorros', 'Corriente', 'CTS', 'Detracción', 'Sueldo'];
    const TIPOS_CUENTA_BILLETERA = ['Personal', 'Empresarial'];

    // =========================================================================
    // 1. UTILIDADES Y FETCH INICIAL
    // =========================================================================

    async function fetchCatalogos() {
        const fd = new FormData();
        fd.append('accion', 'cargar_catalogos_financieros');
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const { data } = await parseJsonResponse(response);
            if (data.ok && data.data) {
                catalogosFinancieros = data.data;
            }
        } catch (error) {
            console.error('Error al cargar catálogos financieros:', error);
        }
    }

    async function parseJsonResponse(response) {
        const text = await response.text();
        try {
            return { ok: response.ok, data: JSON.parse(text) };
        } catch (e) {
            return { ok: false, data: { mensaje: 'Error respuesta servidor: ' + text.substring(0, 50) } };
        }
    }

    function sanitizeDigits(value) {
        return (value || '').replace(/\D/g, '');
    }

    function normalizeDateForInput(value) {
        const raw = (value || '').toString().trim();
        if (!raw) return '';
        const iso = raw.match(/^(\d{4}-\d{2}-\d{2})/);
        if (iso) return iso[1];
        const latin = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (latin) return `${latin[3]}-${latin[2]}-${latin[1]}`;
        return '';
    }

    function safeJsonParse(raw, fallback = []) {
        if (!raw) return fallback;
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : fallback;
        } catch (_err) {
            return fallback;
        }
    }

    function normalizeTipoEntidad(value) {
        const v = (value || '').toString().trim();
        if (v.match(/banco/i)) return 'Banco';
        if (v.match(/caja/i)) return 'Caja';
        if (v.match(/billetera/i)) return 'Billetera Digital';
        return 'Otros';
    }

    function isBilleteraTipo(tipo) {
        return normalizeTipoEntidad(tipo) === 'Billetera Digital';
    }

    function normalizeTipoCatalogo(value) {
        const v = (value || '').toString().trim().toUpperCase();
        if (v === 'BANCO') return 'Banco';
        if (v === 'CAJA') return 'Caja';
        if (v === 'BILLETERA') return 'Billetera Digital';
        return 'Otros';
    }

    function normalizeCatalogItem(item = {}) {
        const entidad = (item.entidad || item.nombre || item.codigo || '').toString().trim();
        return {
            id: Number(item.id || 0),
            tipo: normalizeTipoCatalogo(item.tipo),
            entidad,
            tipoCuenta: (item.tipo_cuenta || '').toString().trim(),
            moneda: (item.moneda || '').toString().trim().toUpperCase() || 'PEN',
            nombre: (item.nombre || entidad).toString().trim()
        };
    }

    async function cargarCatalogoCajasBancos() {
        const fd = new FormData();
        fd.append('accion', 'cargar_catalogo_cajas_bancos');
        const response = await fetch(window.location.href, {
            method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const { data } = await parseJsonResponse(response);
        if (!data.ok || !Array.isArray(data.data)) {
            throw new Error(data.mensaje || 'No se pudo cargar la configuración de Cajas y Bancos.');
        }

        const grouped = { Banco: [], Caja: [], 'Billetera Digital': [], Otros: [] };
        data.data.forEach((raw) => {
            const item = normalizeCatalogItem(raw);
            if (!item.entidad) return;
            grouped[item.tipo].push(item);
        });

        CATALOGO_CAJAS_BANCOS = grouped;
        ENTIDADES_FINANCIERAS = {
            Banco: grouped.Banco.map((x) => x.entidad),
            Caja: grouped.Caja.map((x) => x.entidad),
            'Billetera Digital': grouped['Billetera Digital'].map((x) => x.entidad),
            Otros: grouped.Otros.map((x) => x.entidad)
        };
    }

    function normalizeCatalogValue(value) {
        return (value || '').toString().trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
    }

    function normalizeTipoPersona(value) {
        const v = normalizeCatalogValue(value);
        return (v === 'NATURAL' || v === 'JURIDICA') ? v : '';
    }

    function normalizeGenero(value) {
        const v = normalizeCatalogValue(value);
        return (v === 'MASCULINO' || v === 'FEMENINO' || v === 'OTRO') ? v : '';
    }

    function normalizeEstadoCivil(value) {
        const v = normalizeCatalogValue(value);
        return ['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO', 'CONVIVIENTE'].includes(v) ? v : '';
    }

    function normalizeEstadoLaboral(value) {
        const v = normalizeCatalogValue(value);
        if (v === 'ACTIVO') return 'activo';
        if (v === 'CESADO') return 'cesado';
        if (v === 'SUSPENDIDO') return 'suspendido';
        return 'activo';
    }

    function normalizeTipoComisionAfp(value) {
        const v = normalizeCatalogValue(value);
        if (v.includes('FLUJO')) return 'FLUJO';
        if (v.includes('MIXTA')) return 'MIXTA';
        return '';
    }

    function normalizeTipoContrato(value) {
        const v = normalizeCatalogValue(value).replace(/\s+/g, '_');
        const map = {
            INDETERMINADO: 'INDETERMINADO', PLAZO_FIJO: 'PLAZO_FIJO', PART_TIME: 'PART_TIME',
            LOCACION: 'LOCACION', LOCACION_DE_SERVICIOS: 'LOCACION', PRACTICANTE: 'PRACTICANTE'
        };
        return map[v] || '';
    }

    function normalizeTipoPago(value) {
        const v = normalizeCatalogValue(value);
        return ['MENSUAL', 'QUINCENAL', 'DIARIO'].includes(v) ? v : 'MENSUAL';
    }

    function normalizeMoneda(value) {
        const v = normalizeCatalogValue(value);
        if (v === 'USD' || v.includes('DOLAR')) return 'USD';
        return 'PEN';
    }

    function normalizeRegimenPensionario(value) {
        const v = normalizeCatalogValue(value).replace(/\s+/g, '_').replace(/^SNP$/, 'ONP').replace(/^AFP$/, '');
        const map = {
            ONP: 'ONP', AFP_INTEGRA: 'AFP_INTEGRA', INTEGRA: 'AFP_INTEGRA', AFP_PRIMA: 'AFP_PRIMA',
            PRIMA: 'AFP_PRIMA', AFP_PROFUTURO: 'AFP_PROFUTURO', PROFUTURO: 'AFP_PROFUTURO',
            AFP_HABITAT: 'AFP_HABITAT', HABITAT: 'AFP_HABITAT'
        };
        return map[v] || '';
    }

    function setInvalid(input, msg) {
        if (!input) return;
        input.classList.add('is-invalid');
        const feedback = input.nextElementSibling?.classList.contains('invalid-feedback') 
            ? input.nextElementSibling 
            : input.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.textContent = msg;
    }

    function clearInvalid(input) {
        if (!input) return;
        input.classList.remove('is-invalid');
    }

    // =========================================================================
    // 2. LÓGICA DE PESTAÑAS DINÁMICAS (CORE)
    // =========================================================================

    function syncRoleTabs(prefix) {
        const map = [
            { check: `${prefix}EsCliente`, tab: `${prefix}-tab-header-cliente` },
            { check: `${prefix}EsDistribuidor`, tab: `${prefix}-tab-header-distribuidor` },
            { check: `${prefix}EsProveedor`, tab: `${prefix}-tab-header-proveedor` },
            { check: `${prefix}EsEmpleado`, tab: `${prefix}-tab-header-empleado` }
        ];

        let activeTabHidden = false;

        map.forEach(item => {
            const checkbox = document.getElementById(item.check);
            const tabItem = document.getElementById(item.tab);
            if (checkbox && tabItem) {
                const show = checkbox.checked;
                if (show) {
                    tabItem.classList.remove('d-none');
                } else {
                    tabItem.classList.add('d-none');
                }
                const link = tabItem.querySelector('.nav-link');
                if (!show && link && link.classList.contains('active')) {
                    activeTabHidden = true;
                }
            }
        });

        if (activeTabHidden) {
            const firstTabBtn = document.getElementById(`${prefix}-tab-identificacion`);
            if (firstTabBtn) {
                const tabInstance = bootstrap.Tab.getOrCreateInstance(firstTabBtn);
                tabInstance.show();
            }
        }
        syncEmpleadoRequiredFields(prefix);
    }

    function syncEmpleadoRequiredFields(prefix) {
        const empleadoCheckbox = document.getElementById(`${prefix}EsEmpleado`);
        const form = empleadoCheckbox?.closest('form');
        if (!empleadoCheckbox || !form) return;

        const requireEmpleado = empleadoCheckbox.checked;
        form.querySelectorAll('[data-required-empleado="1"]').forEach((field) => {
            field.required = requireEmpleado;
            if (!requireEmpleado) field.setCustomValidity('');
        });
    }

    // =========================================================================
    // 3. GENERADORES DE FILAS (Teléfonos y Cuentas)
    // =========================================================================

    function createRemoveButton(onClick) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-danger btn-sm';
        btn.innerHTML = '<i class="bi bi-trash"></i>';
        btn.onclick = onClick;
        return btn;
    }

    function buildTelefonoRow(data = {}, onRemove) {
        const wrapper = document.createElement('div');
        wrapper.className = 'input-group input-group-sm mb-1';
        
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.name = 'telefonos[]';
        input.placeholder = 'Número';
        input.value = data.telefono || '';

        const select = document.createElement('select');
        select.className = 'form-select';
        select.name = 'telefono_tipos[]';
        ['Móvil', 'Fijo', 'WhatsApp', 'Trabajo'].forEach(t => {
            const opt = new Option(t, t);
            if (t === data.tipo) opt.selected = true;
            select.add(opt);
        });

        wrapper.appendChild(input);
        wrapper.appendChild(select);
        wrapper.appendChild(createRemoveButton(() => { wrapper.remove(); if(onRemove) onRemove(); }));
        return wrapper;
    }

    function buildCuentaRow(data = {}, onRemove) {
        const card = document.createElement('div');
        card.className = 'card mb-2 border shadow-sm';
        const tipoEntidadVal = normalizeTipoEntidad(data.tipo_entidad || (data.billetera_digital == 1 ? 'Billetera Digital' : 'Banco'));
        
        const body = document.createElement('div'); body.className = 'card-body p-2';
        const row = document.createElement('div'); row.className = 'row g-2 align-items-center';

        const colType = document.createElement('div'); colType.className = 'col-md-2';
        const selType = document.createElement('select'); 
        selType.className = 'form-select form-select-sm fw-bold bg-light';
        selType.name = 'cuenta_tipo[]';
        TIPOS_ENTIDAD_CUENTA.forEach(t => selType.add(new Option(t, t, false, t === tipoEntidadVal)));
        colType.appendChild(selType);

        const colEnt = document.createElement('div'); colEnt.className = 'col-md-3';
        const selEnt = document.createElement('select');
        selEnt.className = 'form-select form-select-sm';
        selEnt.name = 'cuenta_config_banco_id[]'; 
        
        const hidEntidadTexto = document.createElement('input');
        hidEntidadTexto.type = 'hidden'; hidEntidadTexto.name = 'cuenta_entidad[]';
        
        colEnt.append(selEnt, hidEntidadTexto);

        const inpConfigBanco = document.createElement('input');
        inpConfigBanco.type = 'hidden'; inpConfigBanco.name = 'cuenta_config_banco_id[]';
        inpConfigBanco.value = String(data.config_banco_id || '0');
        colEnt.appendChild(inpConfigBanco);

        const colTipoCta = document.createElement('div'); colTipoCta.className = 'col-md-2';
        const selTipoCta = document.createElement('select');
        selTipoCta.className = 'form-select form-select-sm'; selTipoCta.name = 'cuenta_tipo_cta[]';
        colTipoCta.appendChild(selTipoCta);

        const hidBill = document.createElement('input');
        hidBill.type = 'hidden'; hidBill.name = 'cuenta_billetera[]'; hidBill.value = '0';
        colTipoCta.appendChild(hidBill);

        const colNum = document.createElement('div'); colNum.className = 'col-md-4';
        const grpNum = document.createElement('div'); grpNum.className = 'input-group input-group-sm';
        const icon = document.createElement('span'); icon.className = 'input-group-text bg-white text-muted border-end-0';
        const inpNum = document.createElement('input'); 
        inpNum.className = 'form-control border-start-0'; inpNum.name = 'cuenta_cci[]';
        inpNum.value = data.cci || data.numero_cuenta || '';
        
        const inpSec = document.createElement('input');
        inpSec.type = 'hidden'; inpSec.name = 'cuenta_numero[]'; inpSec.value = data.numero_cuenta || '';

        grpNum.append(icon, inpNum, inpSec); colNum.appendChild(grpNum);

        const colAct = document.createElement('div'); colAct.className = 'col-md-1 d-flex gap-1';
        const selMon = document.createElement('select');
        selMon.className = 'form-select form-select-sm px-1 text-center'; selMon.name = 'cuenta_moneda[]';
        selMon.add(new Option('S/', 'PEN', false, data.moneda === 'PEN'));
        selMon.add(new Option('$', 'USD', false, data.moneda === 'USD'));
        
        const btnDel = createRemoveButton(() => { card.remove(); if(onRemove) onRemove(); });
        colAct.append(selMon, btnDel);

        const rowDet = document.createElement('div'); rowDet.className = 'row g-2 mt-1';
        const colTit = document.createElement('div'); colTit.className = 'col-md-5';
        const inpTit = document.createElement('input');
        inpTit.className = 'form-control form-control-sm'; inpTit.placeholder = 'Titular (si es diferente)';
        inpTit.name = 'cuenta_titular[]'; inpTit.value = data.titular || '';
        colTit.appendChild(inpTit);

        const colObs = document.createElement('div'); colObs.className = 'col-md-7';
        const inpObs = document.createElement('input');
        inpObs.className = 'form-control form-control-sm'; inpObs.placeholder = 'Observaciones';
        inpObs.name = 'cuenta_observaciones[]'; inpObs.value = data.observaciones || '';
        colObs.appendChild(inpObs);

        rowDet.append(colTit, colObs);
        
        const updateRow = () => {
            const tipoUI = normalizeTipoEntidad(selType.value);
            const isBill = isBilleteraTipo(tipoUI);
            
            let dbType = 'OTROS';
            if (tipoUI === 'Banco') dbType = 'BANCO';
            else if (tipoUI === 'Caja') dbType = 'CAJA';
            else if (tipoUI === 'Billetera Digital') dbType = 'BILLETERA';
            
            selEnt.innerHTML = '<option value="">Seleccione...</option>';
            const opciones = catalogosFinancieros[dbType] || [];
            
            opciones.forEach(item => {
                const opt = new Option(item.nombre, item.id);
                opt.dataset.nombre = item.nombre;
                selEnt.add(opt);
            });

            if (data.config_banco_id) {
                selEnt.value = data.config_banco_id;
            } else if (data.entidad) {
                const matchedOpt = Array.from(selEnt.options).find(o => o.dataset.nombre === data.entidad);
                if (matchedOpt) {
                    selEnt.value = matchedOpt.value;
                } else {
                    const customOpt = new Option(data.entidad, "");
                    customOpt.dataset.nombre = data.entidad;
                    customOpt.selected = true;
                    selEnt.add(customOpt);
                }
            }

            hidEntidadTexto.value = selEnt.options[selEnt.selectedIndex]?.dataset.nombre || data.entidad || '';
            const selectedOpt = selEnt.options[selEnt.selectedIndex] || null;
            inpConfigBanco.value = selectedOpt?.value || '0';

            selTipoCta.innerHTML = '';
            const optsCta = isBill ? TIPOS_CUENTA_BILLETERA : TIPOS_CUENTA_BANCO;
            optsCta.forEach(t => selTipoCta.add(new Option(t, t)));

            const tipoCuentaCatalogo = selectedOpt?.dataset?.tipoCuenta || '';
            if (tipoCuentaCatalogo && Array.from(selTipoCta.options).some(o => o.value === tipoCuentaCatalogo)) {
                selTipoCta.value = tipoCuentaCatalogo;
            }
            if(data.tipo_cuenta && Array.from(selTipoCta.options).some(o => o.value === data.tipo_cuenta)) selTipoCta.value = data.tipo_cuenta;

            const monedaCatalogo = selectedOpt?.dataset?.moneda || '';
            if (monedaCatalogo === 'PEN' || monedaCatalogo === 'USD') selMon.value = monedaCatalogo;
            if (data.moneda === 'PEN' || data.moneda === 'USD') selMon.value = data.moneda;

            if (isBill) {
                colTipoCta.classList.add('d-none');
                colNum.classList.remove('col-md-4'); colNum.classList.add('col-md-6');
                icon.innerHTML = '<i class="bi bi-phone"></i>';
                inpNum.placeholder = 'Celular (9 dígitos)'; inpNum.maxLength = 9;
                hidBill.value = '1';
            } else {
                colTipoCta.classList.remove('d-none');
                colNum.classList.remove('col-md-6'); colNum.classList.add('col-md-4');
                icon.innerHTML = '<i class="bi bi-bank"></i>';
                inpNum.placeholder = 'CCI o Cuenta'; inpNum.maxLength = 20;
                hidBill.value = '0';
            }
        };

        selType.addEventListener('change', () => {
            data.config_banco_id = '';
            data.entidad = '';
            updateRow();
        });

        selEnt.addEventListener('change', () => {
            inpConfigBanco.value = selEnt.value || '0';
            hidEntidadTexto.value = selEnt.options[selEnt.selectedIndex]?.dataset.nombre || '';
        });
        inpNum.addEventListener('input', function() {
            if(hidBill.value === '1') this.value = sanitizeDigits(this.value).slice(0,9);
            inpSec.value = this.value; 
        });

        row.append(colType, colEnt, colTipoCta, colNum, colAct);
        body.append(row, rowDet);
        card.appendChild(body);

        updateRow(); 
        return card;
    }

    // =========================================================================
    // 4. INICIALIZACIÓN DE FORMULARIOS Y MODALES
    // =========================================================================

    function initDynamicFields() {
        const setupListeners = (prefix) => {
            const form = document.getElementById(`form${prefix === 'crear' ? 'Crear' : 'Editar'}Tercero`);
            if(!form) return;

            // Checkboxes de Roles -> Activan Pestañas
            ['EsCliente', 'EsDistribuidor', 'EsProveedor', 'EsEmpleado'].forEach(role => {
                const el = document.getElementById(`${prefix}${role}`);
                if (el) {
                    el.addEventListener('change', () => syncRoleTabs(prefix));
                }
            });

            // Init Zonas Distribuidor
            if (window.TercerosClientes && window.TercerosClientes.initDistribuidorZones) {
                window.TercerosClientes.initDistribuidorZones(prefix);
            }

            // Tipo Persona -> Toggle Representante Legal
            const tipoPer = document.getElementById(`${prefix}TipoPersona`);
            if (tipoPer) {
                tipoPer.addEventListener('change', () => {
                    const isJuridica = tipoPer.value === 'JURIDICA';
                    const repSection = document.getElementById(`${prefix}RepresentanteLegalSection`);
                    const lblNombre = document.getElementById(`${prefix}NombreLabel`);
                    const inpRep = document.getElementById(`${prefix}RepresentanteLegal`);

                    if (repSection) {
                        if (isJuridica) repSection.classList.remove('d-none');
                        else repSection.classList.add('d-none');
                    }
                    if (lblNombre) lblNombre.innerHTML = isJuridica ? 'Razón Social <span class="text-danger">*</span>' : 'Nombre Completo <span class="text-danger">*</span>';
                    if (inpRep) inpRep.required = isJuridica;
                });
            }

            // Ubigeo (Departamento -> Provincia -> Distrito)
            const dep = document.getElementById(`${prefix}Departamento`);
            const prov = document.getElementById(`${prefix}Provincia`);
            const dist = document.getElementById(`${prefix}Distrito`);
            
            if (dep && prov && dist) {
                const loadUbigeo = (tipo, padreId, targetEl) => {
                    targetEl.innerHTML = '<option value="">Cargando...</option>';
                    targetEl.disabled = true;
                    
                    const fd = new FormData();
                    fd.append('accion', 'cargar_ubigeo');
                    fd.append('tipo', tipo);
                    fd.append('padre_id', padreId);

                    fetch(window.location.href, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                        .then(parseJsonResponse)
                        .then(({data}) => {
                            targetEl.innerHTML = '<option value="">Seleccionar...</option>';
                            if (data.ok && data.data) {
                                data.data.forEach(x => targetEl.add(new Option(x.nombre, x.id)));
                                targetEl.disabled = false;
                                if (targetEl.dataset.preselect) {
                                    targetEl.value = targetEl.dataset.preselect;
                                    targetEl.dataset.preselect = ''; 
                                    targetEl.dispatchEvent(new Event('change'));
                                }
                            }
                        });
                };

                dep.addEventListener('change', () => {
                    dist.innerHTML = '<option value="">Seleccionar...</option>'; dist.disabled = true;
                    if (dep.value) loadUbigeo('provincias', dep.value, prov);
                    else { prov.innerHTML = '<option value="">Seleccionar...</option>'; prov.disabled = true; }
                });

                prov.addEventListener('change', () => {
                    if (prov.value) loadUbigeo('distritos', prov.value, dist);
                    else { dist.innerHTML = '<option value="">Seleccionar...</option>'; dist.disabled = true; }
                });
            }

            // --- LÓGICA DEL SWITCH DE RECORDAR CUMPLEAÑOS ---
            const recordarCumpleanosSwitch = document.getElementById(`${prefix}RecordarCumpleanos`);
            const fechaNacimientoInput = document.getElementById(`${prefix}FechaNacimiento`);
            
            if (recordarCumpleanosSwitch && fechaNacimientoInput) {
                recordarCumpleanosSwitch.addEventListener('change', function() {
                    if (this.checked) {
                        fechaNacimientoInput.disabled = false;
                        fechaNacimientoInput.required = true;
                    } else {
                        fechaNacimientoInput.disabled = true;
                        fechaNacimientoInput.required = false;
                        fechaNacimientoInput.value = ''; // Limpiamos el valor si se apaga
                        clearInvalid(fechaNacimientoInput); // Removemos estilos de error por si los había
                    }
                });
            }
        };

        setupListeners('crear');
        setupListeners('edit');
    }

    function initModals() {
        // CREAR
        const modalCrear = document.getElementById('modalCrearTercero');
        if (modalCrear) {
            modalCrear.addEventListener('show.bs.modal', () => {
                const form = document.getElementById('formCrearTercero');
                if(form) form.reset();
                
                document.getElementById('crearTelefonosList').innerHTML = '';
                document.getElementById('crearCuentasBancariasList').innerHTML = '';
                
                if (window.TercerosClientes && window.TercerosClientes.setDistribuidorZones) {
                    window.TercerosClientes.setDistribuidorZones('crear', []);
                }

                if (window.TercerosEmpleados && window.TercerosEmpleados.setHijos) {
                    window.TercerosEmpleados.setHijos('crear', []);
                }

                syncRoleTabs('crear');
                document.getElementById('crearTipoPersona')?.dispatchEvent(new Event('change'));
                document.getElementById('crearProvincia').innerHTML = '';
                document.getElementById('crearDistrito').innerHTML = '';

                // Forzar el estado inicial del switch cumpleaños
                const recordarChkCrear = document.getElementById('crearRecordarCumpleanos');
                if(recordarChkCrear){
                    recordarChkCrear.checked = false;
                    recordarChkCrear.dispatchEvent(new Event('change'));
                }

                if (window.TercerosEmpleados && window.TercerosEmpleados.refreshState) {
                    window.TercerosEmpleados.refreshState('crear');
                }
            });
        }

        // EDITAR
        const modalEdit = document.getElementById('modalEditarTercero');
        if (modalEdit) {
            modalEdit.addEventListener('show.bs.modal', (e) => {
                const btn = e.relatedTarget;
                modalEdit.__currentTriggerButton = btn;
                const id = btn.dataset.id;
                
                document.getElementById('editId').value = id;
                document.getElementById('editNombre').value = btn.dataset.nombre;
                document.getElementById('editNumeroDoc').value = btn.dataset.numeroDoc;
                document.getElementById('editTipoDoc').value = btn.dataset.tipoDoc;
                document.getElementById('editEstado').value = btn.dataset.estado || '1';
                document.getElementById('editDireccion').value = btn.dataset.direccion;
                document.getElementById('editEmail').value = btn.dataset.email;
                document.getElementById('editRepresentanteLegal').value = btn.dataset.representanteLegal || '';

                // Datos Cliente / Proveedor
                document.getElementById('editClienteDiasCredito').value = btn.dataset.clienteDiasCredito || '';
                document.getElementById('editClienteLimiteCredito').value = btn.dataset.clienteLimiteCredito || '';
                document.getElementById('editClienteCondicionPago').value = btn.dataset.clienteCondicionPago || '';
                document.getElementById('editProvCondicion').value = btn.dataset.proveedorCondicionPago || '';
                document.getElementById('editProvDiasCredito').value = btn.dataset.proveedorDiasCredito || '';
                document.getElementById('editProvFormaPago').value = btn.dataset.proveedorFormaPago || '';
                document.getElementById('editProvCondicion').dispatchEvent(new Event('change'));
                
                document.getElementById('editEsCliente').checked = btn.dataset.esCliente == 1;
                document.getElementById('editEsProveedor').checked = btn.dataset.esProveedor == 1;
                document.getElementById('editEsEmpleado').checked = btn.dataset.esEmpleado == 1;
                document.getElementById('editEsDistribuidor').checked = btn.dataset.esDistribuidor == 1;

                // Datos Empleado
                document.getElementById('editCargo').value = btn.dataset.cargo || '';
                document.getElementById('editArea').value = btn.dataset.area || '';
                document.getElementById('editFechaIngreso').value = btn.dataset.fechaIngreso || '';
                document.getElementById('editFechaCese').value = btn.dataset.fechaCese || '';
                document.getElementById('editEstadoLaboral').value = normalizeEstadoLaboral(btn.dataset.estadoLaboral);
                document.getElementById('editTipoContrato').value = normalizeTipoContrato(btn.dataset.tipoContrato);
                document.getElementById('editTipoPago').value = normalizeTipoPago(btn.dataset.tipoPago);
                document.getElementById('editMoneda').value = normalizeMoneda(btn.dataset.moneda);
                document.getElementById('editSueldoBasico').value = btn.dataset.sueldoBasico || '';
                document.getElementById('editPagoDiario').value = btn.dataset.pagoDiario || '';
                document.getElementById('editRegimen').value = normalizeRegimenPensionario(btn.dataset.regimenPensionario);
                document.getElementById('editTipoComision').value = normalizeTipoComisionAfp(btn.dataset.tipoComisionAfp);
                document.getElementById('editCuspp').value = btn.dataset.cuspp || '';
                document.getElementById('editAsignacionFamiliar').checked = btn.dataset.asignacionFamiliar == 1;
                document.getElementById('editEssalud').checked = btn.dataset.essalud == 1;
                
                // --- Inicialización del Cumpleaños ---
                document.getElementById('editFechaNacimiento').value = normalizeDateForInput(btn.dataset.fechaNacimiento);
                const recordarChk = document.getElementById('editRecordarCumpleanos');
                if (recordarChk) {
                    recordarChk.checked = btn.dataset.recordarCumpleanos == 1;
                    recordarChk.dispatchEvent(new Event('change')); // Disparamos el evento para habilitar/deshabilitar la fecha
                }

                document.getElementById('editGenero').value = normalizeGenero(btn.dataset.genero);
                document.getElementById('editEstadoCivil').value = normalizeEstadoCivil(btn.dataset.estadoCivil);
                document.getElementById('editNivelEducativo').value = btn.dataset.nivelEducativo || '';
                document.getElementById('editContactoEmergenciaNombre').value = btn.dataset.contactoEmergenciaNombre || '';
                document.getElementById('editContactoEmergenciaTelf').value = btn.dataset.contactoEmergenciaTelf || '';
                document.getElementById('editTipoSangre').value = btn.dataset.tipoSangre || '';
                
                syncRoleTabs('edit');

                if (window.TercerosEmpleados && window.TercerosEmpleados.setHijos) {
                    const hijos = safeJsonParse(btn.dataset.hijosLista, []);
                    window.TercerosEmpleados.setHijos('edit', hijos);
                }

                if (window.TercerosEmpleados && window.TercerosEmpleados.refreshState) {
                    window.TercerosEmpleados.refreshState('edit');
                }

                const tipoP = document.getElementById('editTipoPersona');
                tipoP.value = normalizeTipoPersona(btn.dataset.tipoPersona);
                tipoP.dispatchEvent(new Event('change'));

                const dep = document.getElementById('editDepartamento');
                const prov = document.getElementById('editProvincia');
                const dist = document.getElementById('editDistrito');
                
                prov.dataset.preselect = btn.dataset.provincia;
                dist.dataset.preselect = btn.dataset.distrito;
                
                dep.value = btn.dataset.departamento;
                dep.dispatchEvent(new Event('change'));

                const tels = safeJsonParse(btn.dataset.telefonos, []);
                const ctas = safeJsonParse(btn.dataset.cuentasBancarias, []);
                
                const telList = document.getElementById('editTelefonosList');
                telList.innerHTML = '';
                tels.forEach(t => telList.appendChild(buildTelefonoRow(t)));

                const ctaList = document.getElementById('editCuentasBancariasList');
                ctaList.innerHTML = '';
                ctas.forEach(c => ctaList.appendChild(buildCuentaRow(c)));

                if (window.TercerosClientes && window.TercerosClientes.loadSavedZones && window.TercerosClientes.setDistribuidorZones) {
                    if (btn.dataset.esDistribuidor == 1) {
                        window.TercerosClientes.loadSavedZones('edit', id);
                    } else {
                        window.TercerosClientes.setDistribuidorZones('edit', []);
                    }
                }
            });
        }
    }

    function initFormSubmit() {
        const tiposSangreValidos = new Set(['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);

        const validateBusinessRules = (form) => {
            const tipoPersona = (form.querySelector('[name="tipo_persona"]')?.value || '').trim().toUpperCase();
            const tipoDoc = (form.querySelector('[name="tipo_documento"]')?.value || '').trim().toUpperCase();
            const numeroDocumento = (form.querySelector('[name="numero_documento"]')?.value || '').replace(/\D/g, '');
            const nombreCompleto = (form.querySelector('[name="nombre_completo"]')?.value || '').trim();
            const representanteLegal = (form.querySelector('[name="representante_legal"]')?.value || '').trim();
            const email = (form.querySelector('[name="email"]')?.value || '').trim();
            const recordarCumpleanos = !!form.querySelector('[name="recordar_cumpleanos"]')?.checked;
            const fechaNacimiento = (form.querySelector('[name="fecha_nacimiento"]')?.value || '').trim();
            const tipoSangre = (form.querySelector('[name="tipo_sangre"]')?.value || '').trim().toUpperCase();
            const esEmpleado = !!form.querySelector('[name="es_empleado"]')?.checked;
            const sueldoBasicoRaw = (form.querySelector('[name="sueldo_basico"]')?.value || '').trim();
            const sueldoBasico = Number.parseFloat(sueldoBasicoRaw);

            if (!tipoPersona || !tipoDoc || !nombreCompleto || !numeroDocumento) {
                return 'Tipo de persona, documento, número y nombre son obligatorios.';
            }

            if (tipoPersona === 'JURIDICA' && !representanteLegal) {
                return 'Representante legal es obligatorio para empresas.';
            }

            if (tipoDoc === 'RUC' && numeroDocumento.length !== 11) {
                return 'El RUC debe tener 11 dígitos.';
            }

            if (tipoDoc === 'DNI' && numeroDocumento.length !== 8) {
                return 'El DNI debe tener 8 dígitos.';
            }

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return 'El correo electrónico no tiene un formato válido.';
            }

            if (esEmpleado && recordarCumpleanos) {
                if (!fechaNacimiento) {
                    return 'Si activa recordar cumpleaños, debe registrar la fecha de nacimiento.';
                }
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);
                const fecha = new Date(`${fechaNacimiento}T00:00:00`);
                if (Number.isNaN(fecha.getTime())) {
                    return 'La fecha de nacimiento no tiene un formato válido.';
                }
                if (fecha > hoy) {
                    return 'La fecha de nacimiento no puede ser mayor a la fecha actual.';
                }
            }

            if (esEmpleado) {
                if (sueldoBasicoRaw === '') {
                    return 'Para el rol Empleado, el sueldo básico es obligatorio.';
                }
                if (Number.isNaN(sueldoBasico) || sueldoBasico < 0) {
                    return 'El sueldo básico del empleado debe ser un número válido mayor o igual a 0.';
                }
            }

            if (!tiposSangreValidos.has(tipoSangre)) {
                return 'El tipo de sangre seleccionado no es válido.';
            }

            return null;
        };

        const showTabForInvalidField = (field) => {
            if (!field) return;
            const tabPane = field.closest('.tab-pane');
            if (!tabPane || tabPane.classList.contains('active')) return;
            const tabButton = document.querySelector(`[data-bs-target="#${tabPane.id}"]`);
            if (!tabButton) return;
            bootstrap.Tab.getOrCreateInstance(tabButton).show();
        };

        ['formCrearTercero', 'formEditarTercero'].forEach(fid => {
            const form = document.getElementById(fid);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (!form.checkValidity()) {
                    const firstInvalid = form.querySelector(':invalid');
                    showTabForInvalidField(firstInvalid);
                    form.reportValidity();
                    return;
                }
                
                const checkRole = form.querySelector('[name="es_cliente"]').checked || 
                                  form.querySelector('[name="es_distribuidor"]').checked || 
                                  form.querySelector('[name="es_proveedor"]').checked || 
                                  form.querySelector('[name="es_empleado"]').checked;

                if (!checkRole) {
                    Swal.fire('Atención', 'Debe seleccionar al menos un Rol (Cliente, Distribuidor, Proveedor o Empleado).', 'warning');
                    return;
                }

                const validationMessage = validateBusinessRules(form);
                if (validationMessage) {
                    Swal.fire('Validación', validationMessage, 'warning');
                    return;
                }

                const fd = new FormData(form);

                // Limpieza absoluta de la fecha si el check no está activo
                const recordarCumpleanosInput = form.querySelector('[name="recordar_cumpleanos"]');
                if (!recordarCumpleanosInput?.checked) {
                    fd.delete('fecha_nacimiento');
                } else {
                    const fechaNacimientoInput = form.querySelector('[name="fecha_nacimiento"]');
                    if (fechaNacimientoInput) {
                        fd.set('fecha_nacimiento', (fechaNacimientoInput.value || '').trim());
                    }
                }

                const btn = form.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

                fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(parseJsonResponse)
                .then(({data}) => {
                    if (data.ok) {
                        Swal.fire('Éxito', data.mensaje, 'success').then(() => window.location.reload());
                    } else {
                        throw new Error(data.mensaje || 'Error desconocido');
                    }
                })
                .catch(err => Swal.fire('Error', err.message, 'error'))
                .finally(() => {
                    btn.disabled = false; btn.innerHTML = originalText;
                });
            });
        });
    }

    function initButtons() {
        ['crear', 'edit'].forEach(p => {
            const btnTel = document.getElementById(`${p}AgregarTelefono`);
            const listTel = document.getElementById(`${p}TelefonosList`);
            if (btnTel) btnTel.onclick = () => listTel.appendChild(buildTelefonoRow());

            const btnCta = document.getElementById(`${p}AgregarCuenta`);
            const listCta = document.getElementById(`${p}CuentasBancariasList`);
            if (btnCta) btnCta.onclick = () => listCta.appendChild(buildCuentaRow());
        });

        document.querySelectorAll('.js-eliminar-tercero').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const puedeEliminar = this.dataset.puedeEliminar === '1';
                const motivoNoEliminar = this.dataset.motivoNoEliminar || 'No se puede eliminar este tercero.';

                if (!puedeEliminar) {
                    Swal.fire('No se puede eliminar', motivoNoEliminar, 'info');
                    return;
                }

                Swal.fire({
                    title: '¿Eliminar tercero?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar'
                }).then((r) => {
                    if(r.isConfirmed) {
                        const fd = new FormData();
                        fd.append('accion', 'eliminar');
                        fd.append('id', id);
                        fetch(window.location.href, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                        .then(parseJsonResponse)
                        .then(({ data }) => {
                            if (!data.ok) {
                                throw new Error(data.mensaje || 'No se pudo eliminar el tercero.');
                            }
                            window.location.reload();
                        })
                        .catch((err) => Swal.fire('Error', err.message, 'error'));
                    }
                });
            });
        });
        
        document.querySelectorAll('.switch-estado-tercero').forEach(sw => {
            sw.addEventListener('change', function() {
                const id = this.dataset.id;
                const estado = this.checked ? 1 : 0;
                const fd = new FormData();
                fd.append('accion', 'toggle_estado');
                fd.append('id', id);
                fd.append('estado', estado);
                
                fetch(window.location.href, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                .then(parseJsonResponse)
                .then(({data}) => {
                    if(!data.ok) {
                        this.checked = !this.checked;
                        Swal.fire('Error', data.mensaje, 'error');
                    } else {
                        const badge = document.getElementById(`badge_status_tercero_${id}`);
                        if(badge) {
                            badge.className = `badge-status status-${estado ? 'active' : 'inactive'}`;
                            badge.innerText = estado ? 'Activo' : 'Inactivo';
                        }
                    }
                });
            });
        });
    }

    // =========================================================================
    // MAESTROS (CARGOS / ÁREAS)
    // =========================================================================

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function renderMaestroList(container, rows, type) {
        if (!container) return;
        if (!Array.isArray(rows) || rows.length === 0) {
            container.innerHTML = '<div class="text-center py-3 text-muted small">No hay registros</div>';
            return;
        }

        container.innerHTML = rows.map((row) => {
            const id = Number(row.id || 0);
            const nombre = escapeHtml(row.nombre || '');
            const estado = Number(row.estado || 0) === 1;
            return `
                <div class="list-group-item py-2 maestro-row" data-id="${id}">
                    <div class="d-flex flex-column">
                        <span class="fw-semibold">${nombre}</span>
                    </div>
                    <div class="form-check form-switch m-0 d-flex justify-content-center" title="Activar / desactivar">
                        <input class="form-check-input js-switch-${type}" type="checkbox" data-id="${id}" ${estado ? 'checked' : ''}>
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-secondary js-edit-${type}" type="button" data-id="${id}" data-nombre="${nombre}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger js-delete-${type}" type="button" data-id="${id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>`;
        }).join('');
    }

    function initMasterCatalog(config) {
        const modal = document.getElementById(config.modalId);
        const form = document.getElementById(config.formId);
        const list = document.getElementById(config.listId);
        const input = document.getElementById(config.inputId);
        const action = document.getElementById(config.actionId);
        const idField = document.getElementById(config.idFieldId);
        const cancelBtn = document.getElementById(config.cancelBtnId);

        if (!modal || !form || !list || !input || !action || !idField || !cancelBtn) return;

        const resetForm = () => {
            form.reset();
            action.value = config.saveAction;
            idField.value = '';
            cancelBtn.classList.add('d-none');
        };

        const loadList = async () => {
            list.innerHTML = '<div class="text-center py-3 text-muted small"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>';
            const fd = new FormData();
            fd.append('accion', config.listAction);
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const { data } = await parseJsonResponse(response);
                if (!data.ok) {
                    throw new Error(data.mensaje || `No se pudo listar ${config.labelPlural}.`);
                }
                renderMaestroList(list, data.data || [], config.type);
            } catch (error) {
                list.innerHTML = `<div class="text-center py-3 text-danger small">${escapeHtml(error.message)}</div>`;
            }
        };

        modal.addEventListener('shown.bs.modal', loadList);
        cancelBtn.addEventListener('click', resetForm);

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const nombre = (input.value || '').trim();
            if (!nombre) {
                Swal.fire('Atención', `Ingresa un nombre de ${config.label}.`, 'warning');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const original = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const fd = new FormData(form);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const { data } = await parseJsonResponse(response);
                if (!data.ok) throw new Error(data.mensaje || `No se pudo guardar ${config.label}.`);

                resetForm();
                await loadList();
                Swal.fire('Éxito', data.mensaje || `${config.labelCapitalized} guardada.`, 'success');
            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = original;
            }
        });

        list.addEventListener('click', async function (event) {
            const editBtn = event.target.closest(`.js-edit-${config.type}`);
            const toggleBtn = event.target.closest(`.js-toggle-${config.type}`);
            const toggleSwitch = event.target.closest(`.js-switch-${config.type}`);
            const deleteBtn = event.target.closest(`.js-delete-${config.type}`);

            if (editBtn) {
                action.value = config.editAction;
                idField.value = editBtn.dataset.id || '';
                input.value = editBtn.dataset.nombre || '';
                input.focus();
                cancelBtn.classList.remove('d-none');
                return;
            }

            if (!toggleBtn && !toggleSwitch && !deleteBtn) return;

            const fd = new FormData();
            const targetId = toggleBtn?.dataset.id || toggleSwitch?.dataset.id || deleteBtn?.dataset.id || '0';
            const nextState = toggleBtn ? (toggleBtn.dataset.estado || '0') : (toggleSwitch?.checked ? '1' : '0');
            fd.append('id', targetId);
            fd.append('accion', (toggleBtn || toggleSwitch) ? config.toggleAction : config.deleteAction);
            if (toggleBtn || toggleSwitch) fd.append('estado', nextState);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const { data } = await parseJsonResponse(response);
                if (!data.ok) throw new Error(data.mensaje || `No se pudo actualizar ${config.label}.`);
                await loadList();
            } catch (error) {
                if (toggleSwitch) toggleSwitch.checked = !toggleSwitch.checked;
                Swal.fire('Error', error.message, 'error');
            }
        });

        modal.addEventListener('hidden.bs.modal', resetForm);
    }

    function initMasterCatalogs() {
        initMasterCatalog({
            type: 'cargo', label: 'cargo', labelPlural: 'cargos', labelCapitalized: 'Cargo',
            modalId: 'modalGestionCargos', formId: 'formCrearCargo', listId: 'listaCargosConfig',
            inputId: 'cargoNombre', actionId: 'cargoAccion', idFieldId: 'cargoId',
            cancelBtnId: 'btnCancelCargo', listAction: 'listar_cargos', saveAction: 'guardar_cargo',
            editAction: 'editar_cargo', deleteAction: 'eliminar_cargo', toggleAction: 'toggle_estado_cargo'
        });

        initMasterCatalog({
            type: 'area', label: 'área', labelPlural: 'áreas', labelCapitalized: 'Área',
            modalId: 'modalGestionAreas', formId: 'formCrearArea', listId: 'listaAreasConfig',
            inputId: 'areaNombre', actionId: 'areaAccion', idFieldId: 'areaId',
            cancelBtnId: 'btnCancelArea', listAction: 'listar_areas', saveAction: 'guardar_area',
            editAction: 'editar_area', deleteAction: 'eliminar_area', toggleAction: 'toggle_estado_area'
        });
    }

    function initTercerosTableManager() {
        const table = document.getElementById('tercerosTable');
        if (!table || typeof ERPTable === 'undefined' || !ERPTable.createTableManager) return;

        ERPTable.createTableManager({
            tableSelector: '#tercerosTable',
            searchInput: '#terceroSearch',
            filters: [
                { el: '#terceroFiltroRol', attr: 'data-roles', match: 'includes' },
                { el: '#terceroFiltroEstado', attr: 'data-estado' }
            ],
            searchAttr: 'data-search',
            paginationControls: '#tercerosPaginationControls',
            paginationInfo: '#tercerosPaginationInfo',
            rowsPerPage: 20,
            infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} registros`,
            emptyText: 'Mostrando 0-0 de 0 registros',
            scrollToTopOnPageChange: false
        }).init();
    }

    // =========================================================================
    // BOOTSTRAP
    // =========================================================================
    document.addEventListener('DOMContentLoaded', async function () {
        await fetchCatalogos(); 

        initDynamicFields();
        initModals();
        initButtons();
        initMasterCatalogs();
        initFormSubmit();
        initTercerosTableManager();
        
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
})();