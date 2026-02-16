(function () {
    'use strict';

    // Configuraciones y Constantes
    const TIPOS_ENTIDAD_CUENTA = ['Banco', 'Caja', 'Billetera Digital', 'Otros'];
    const ENTIDADES_FINANCIERAS = {
        Banco: ['BCP', 'Interbank', 'BBVA', 'Scotiabank', 'Banco de la Nación', 'BanBif', 'Pichincha'],
        Caja: ['Caja Arequipa', 'Caja Huancayo', 'Caja Piura', 'Caja Trujillo', 'Caja Sullana', 'Caja Tacna', 'Caja Ica'],
        'Billetera Digital': ['Yape', 'Plin', 'Tunki', 'Bim', 'Lukita', 'Mercado Pago'],
        Otros: []
    };
    const TIPOS_CUENTA_BANCO = ['Ahorros', 'Corriente', 'CTS', 'Detracción', 'Sueldo'];
    const TIPOS_CUENTA_BILLETERA = ['Personal', 'Empresarial'];

    // =========================================================================
    // 1. UTILIDADES
    // =========================================================================

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
        // Mapeo: ID del Checkbox -> ID de la Pestaña
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
                
                // Mostrar u ocultar el LI del nav-tab
                if (show) {
                    tabItem.classList.remove('d-none');
                } else {
                    tabItem.classList.add('d-none');
                }

                // Si ocultamos una pestaña que estaba activa, marcamos flag para redirigir
                const link = tabItem.querySelector('.nav-link');
                if (!show && link && link.classList.contains('active')) {
                    activeTabHidden = true;
                }
            }
        });

        // Si el usuario estaba viendo una pestaña que se acaba de ocultar, lo mandamos a Identificación
        if (activeTabHidden) {
            const firstTabBtn = document.getElementById(`${prefix}-tab-identificacion`);
            if (firstTabBtn) {
                const tabInstance = bootstrap.Tab.getOrCreateInstance(firstTabBtn);
                tabInstance.show();
            }
        }
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
        
        const body = document.createElement('div');
        body.className = 'card-body p-2';
        
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center';

        // 1. Tipo Entidad
        const colType = document.createElement('div'); colType.className = 'col-md-2';
        const selType = document.createElement('select'); 
        selType.className = 'form-select form-select-sm fw-bold bg-light';
        selType.name = 'cuenta_tipo[]';
        TIPOS_ENTIDAD_CUENTA.forEach(t => selType.add(new Option(t, t, false, t === tipoEntidadVal)));
        colType.appendChild(selType);

        // 2. Entidad
        const colEnt = document.createElement('div'); colEnt.className = 'col-md-3';
        const selEnt = document.createElement('select');
        selEnt.className = 'form-select form-select-sm';
        selEnt.name = 'cuenta_entidad[]';
        colEnt.appendChild(selEnt);

        // 3. Tipo Cuenta
        const colTipoCta = document.createElement('div'); colTipoCta.className = 'col-md-2';
        const selTipoCta = document.createElement('select');
        selTipoCta.className = 'form-select form-select-sm';
        selTipoCta.name = 'cuenta_tipo_cta[]';
        colTipoCta.appendChild(selTipoCta);

        const hidBill = document.createElement('input');
        hidBill.type = 'hidden';
        hidBill.name = 'cuenta_billetera[]';
        hidBill.value = '0';
        colTipoCta.appendChild(hidBill);

        // 4. Número / CCI
        const colNum = document.createElement('div'); colNum.className = 'col-md-4';
        const grpNum = document.createElement('div'); grpNum.className = 'input-group input-group-sm';
        const icon = document.createElement('span'); icon.className = 'input-group-text bg-white text-muted border-end-0';
        const inpNum = document.createElement('input'); 
        inpNum.className = 'form-control border-start-0'; 
        inpNum.name = 'cuenta_cci[]';
        inpNum.value = data.cci || data.numero_cuenta || '';
        
        const inpSec = document.createElement('input');
        inpSec.type = 'hidden';
        inpSec.name = 'cuenta_numero[]';
        inpSec.value = data.numero_cuenta || '';

        grpNum.append(icon, inpNum, inpSec);
        colNum.appendChild(grpNum);

        // 5. Moneda y Acciones
        const colAct = document.createElement('div'); colAct.className = 'col-md-1 d-flex gap-1';
        const selMon = document.createElement('select');
        selMon.className = 'form-select form-select-sm px-1 text-center';
        selMon.name = 'cuenta_moneda[]';
        selMon.add(new Option('S/', 'PEN', false, data.moneda === 'PEN'));
        selMon.add(new Option('$', 'USD', false, data.moneda === 'USD'));
        
        const btnDel = createRemoveButton(() => { card.remove(); if(onRemove) onRemove(); });
        colAct.append(selMon, btnDel);

        const rowDet = document.createElement('div'); rowDet.className = 'row g-2 mt-1';
        
        const colTit = document.createElement('div'); colTit.className = 'col-md-5';
        const inpTit = document.createElement('input');
        inpTit.className = 'form-control form-control-sm';
        inpTit.placeholder = 'Titular (si es diferente)';
        inpTit.name = 'cuenta_titular[]';
        inpTit.value = data.titular || '';
        colTit.appendChild(inpTit);

        const colObs = document.createElement('div'); colObs.className = 'col-md-7';
        const inpObs = document.createElement('input');
        inpObs.className = 'form-control form-control-sm';
        inpObs.placeholder = 'Observaciones';
        inpObs.name = 'cuenta_observaciones[]';
        inpObs.value = data.observaciones || '';
        colObs.appendChild(inpObs);

        rowDet.append(colTit, colObs);
        
        const updateRow = () => {
            const tipo = normalizeTipoEntidad(selType.value);
            const isBill = isBilleteraTipo(tipo);
            
            selEnt.innerHTML = '';
            (ENTIDADES_FINANCIERAS[tipo] || []).forEach(e => selEnt.add(new Option(e, e)));
            if(data.entidad && !Array.from(selEnt.options).some(o=>o.value===data.entidad)){
                selEnt.add(new Option(data.entidad, data.entidad, false, true));
            } else if(data.entidad) {
                selEnt.value = data.entidad;
            }

            selTipoCta.innerHTML = '';
            const optsCta = isBill ? TIPOS_CUENTA_BILLETERA : TIPOS_CUENTA_BANCO;
            optsCta.forEach(t => selTipoCta.add(new Option(t, t)));
            if(data.tipo_cuenta) selTipoCta.value = data.tipo_cuenta;

            if (isBill) {
                colTipoCta.classList.add('d-none');
                colNum.classList.remove('col-md-4'); colNum.classList.add('col-md-6');
                icon.innerHTML = '<i class="bi bi-phone"></i>';
                inpNum.placeholder = 'Celular (9 dígitos)';
                inpNum.maxLength = 9;
                hidBill.value = '1';
            } else {
                colTipoCta.classList.remove('d-none');
                colNum.classList.remove('col-md-6'); colNum.classList.add('col-md-4');
                icon.innerHTML = '<i class="bi bi-bank"></i>';
                inpNum.placeholder = 'CCI o Cuenta';
                inpNum.maxLength = 20;
                hidBill.value = '0';
            }
        };

        selType.addEventListener('change', updateRow);
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

            // Init Zonas Distribuidor (Llamada al módulo de clientes.js)
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
                        if (isJuridica) {
                            repSection.classList.remove('d-none');
                        } else {
                            repSection.classList.add('d-none');
                        }
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
                
                // Limpiar zonas de distribuidor visualmente (usando helper de clientes.js)
                if (window.TercerosClientes && window.TercerosClientes.setDistribuidorZones) {
                    window.TercerosClientes.setDistribuidorZones('crear', []);
                }

                syncRoleTabs('crear');
                
                document.getElementById('crearTipoPersona')?.dispatchEvent(new Event('change'));
                
                document.getElementById('crearProvincia').innerHTML = '';
                document.getElementById('crearDistrito').innerHTML = '';

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
                document.getElementById('editDireccion').value = btn.dataset.direccion;
                document.getElementById('editEmail').value = btn.dataset.email;
                document.getElementById('editRepresentanteLegal').value = btn.dataset.representanteLegal || '';
                
                document.getElementById('editEsCliente').checked = btn.dataset.esCliente == 1;
                document.getElementById('editEsProveedor').checked = btn.dataset.esProveedor == 1;
                document.getElementById('editEsEmpleado').checked = btn.dataset.esEmpleado == 1;
                document.getElementById('editEsDistribuidor').checked = btn.dataset.esDistribuidor == 1;

                document.getElementById('editCargo').value = btn.dataset.cargo || '';
                document.getElementById('editArea').value = btn.dataset.area || '';
                document.getElementById('editFechaIngreso').value = btn.dataset.fechaIngreso || '';
                document.getElementById('editFechaCese').value = btn.dataset.fechaCese || '';
                document.getElementById('editEstadoLaboral').value = btn.dataset.estadoLaboral || 'activo';
                document.getElementById('editTipoContrato').value = btn.dataset.tipoContrato || '';
                document.getElementById('editTipoPago').value = btn.dataset.tipoPago || 'MENSUAL';
                document.getElementById('editMoneda').value = btn.dataset.moneda || 'PEN';
                document.getElementById('editSueldoBasico').value = btn.dataset.sueldoBasico || '';
                document.getElementById('editPagoDiario').value = btn.dataset.pagoDiario || '';
                document.getElementById('editRegimen').value = btn.dataset.regimenPensionario || '';
                document.getElementById('editTipoComision').value = btn.dataset.tipoComisionAfp || '';
                document.getElementById('editCuspp').value = btn.dataset.cuspp || '';
                document.getElementById('editAsignacionFamiliar').checked = btn.dataset.asignacionFamiliar == 1;
                document.getElementById('editEssalud').checked = btn.dataset.essalud == 1;
                document.getElementById('editRecordarCumpleanos').checked = btn.dataset.recordarCumpleanos == 1;
                document.getElementById('editFechaNacimiento').value = btn.dataset.fechaNacimiento || '';
                document.getElementById('editGenero').value = btn.dataset.genero || '';
                document.getElementById('editEstadoCivil').value = btn.dataset.estadoCivil || '';
                document.getElementById('editNivelEducativo').value = btn.dataset.nivelEducativo || '';
                document.getElementById('editContactoEmergenciaNombre').value = btn.dataset.contactoEmergenciaNombre || '';
                document.getElementById('editContactoEmergenciaTelf').value = btn.dataset.contactoEmergenciaTelf || '';
                document.getElementById('editTipoSangre').value = btn.dataset.tipoSangre || '';
                
                syncRoleTabs('edit');

                if (window.TercerosEmpleados && window.TercerosEmpleados.refreshState) {
                    window.TercerosEmpleados.refreshState('edit');
                }

                const tipoP = document.getElementById('editTipoPersona');
                tipoP.value = btn.dataset.tipoPersona;
                tipoP.dispatchEvent(new Event('change'));

                const dep = document.getElementById('editDepartamento');
                const prov = document.getElementById('editProvincia');
                const dist = document.getElementById('editDistrito');
                
                prov.dataset.preselect = btn.dataset.provincia;
                dist.dataset.preselect = btn.dataset.distrito;
                
                dep.value = btn.dataset.departamento;
                dep.dispatchEvent(new Event('change'));

                const tels = JSON.parse(btn.dataset.telefonos || '[]');
                const ctas = JSON.parse(btn.dataset.cuentasBancarias || '[]');
                
                const telList = document.getElementById('editTelefonosList');
                telList.innerHTML = '';
                tels.forEach(t => telList.appendChild(buildTelefonoRow(t)));

                const ctaList = document.getElementById('editCuentasBancariasList');
                ctaList.innerHTML = '';
                ctas.forEach(c => ctaList.appendChild(buildCuentaRow(c)));

                // Cargar zonas distribuidor
                if (btn.dataset.esDistribuidor == 1 && window.TercerosClientes && window.TercerosClientes.loadSavedZones) {
                    window.TercerosClientes.loadSavedZones('edit', id);
                }
            });
        }
    }

    function initFormSubmit() {
        ['formCrearTercero', 'formEditarTercero'].forEach(fid => {
            const form = document.getElementById(fid);
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const checkRole = form.querySelector('[name="es_cliente"]').checked || 
                                  form.querySelector('[name="es_distribuidor"]').checked || 
                                  form.querySelector('[name="es_proveedor"]').checked || 
                                  form.querySelector('[name="es_empleado"]').checked;

                if (!checkRole) {
                    Swal.fire('Atención', 'Debe seleccionar al menos un Rol (Cliente, Distribuidor, Proveedor o Empleado).', 'warning');
                    return;
                }

                const fd = new FormData(form);
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
            type: 'cargo',
            label: 'cargo',
            labelPlural: 'cargos',
            labelCapitalized: 'Cargo',
            modalId: 'modalGestionCargos',
            formId: 'formCrearCargo',
            listId: 'listaCargosConfig',
            inputId: 'cargoNombre',
            actionId: 'cargoAccion',
            idFieldId: 'cargoId',
            cancelBtnId: 'btnCancelCargo',
            listAction: 'listar_cargos',
            saveAction: 'guardar_cargo',
            editAction: 'editar_cargo',
            deleteAction: 'eliminar_cargo',
            toggleAction: 'toggle_estado_cargo'
        });

        initMasterCatalog({
            type: 'area',
            label: 'área',
            labelPlural: 'áreas',
            labelCapitalized: 'Área',
            modalId: 'modalGestionAreas',
            formId: 'formCrearArea',
            listId: 'listaAreasConfig',
            inputId: 'areaNombre',
            actionId: 'areaAccion',
            idFieldId: 'areaId',
            cancelBtnId: 'btnCancelArea',
            listAction: 'listar_areas',
            saveAction: 'guardar_area',
            editAction: 'editar_area',
            deleteAction: 'eliminar_area',
            toggleAction: 'toggle_estado_area'
        });
    }

    // =========================================================================
    // BOOTSTRAP
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        initDynamicFields();
        initModals();
        initButtons();
        initMasterCatalogs();
        initFormSubmit();
        
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

})();
