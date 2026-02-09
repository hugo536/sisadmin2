(function () {
    'use strict';

    // ENTIDADES FINANCIERAS (Estáticas para el ejemplo)
    const ENTIDADES_FINANCIERAS = {
        BANCO: ['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Banco de la Nación', 'Pichincha'],
        BILLETERA: ['Yape', 'Plin', 'Tunki', 'Agora Pay', 'BIM'],
        CAJA: ['Caja Huancayo', 'Caja Piura', 'Caja Arequipa', 'Caja Sullana'],
        COOPERATIVA: ['Coopac Pacífico', 'Coopac San Cristóbal'],
        OTRO: []
    };

    // =========================================================================
    // UTILIDADES GENERALES
    // =========================================================================

    function getFeedbackElement(input) {
        if (!input) return null;
        const floatingFeedback = input.closest('.form-floating')?.querySelector('.invalid-feedback');
        if (floatingFeedback) return floatingFeedback;
        const inputGroup = input.closest('.input-group');
        if (inputGroup && inputGroup.nextElementSibling?.classList.contains('invalid-feedback')) {
            return inputGroup.nextElementSibling;
        }
        const parentFeedback = input.parentElement?.querySelector('.invalid-feedback');
        if (parentFeedback) return parentFeedback;
        if (input.nextElementSibling?.classList.contains('invalid-feedback')) return input.nextElementSibling;
        return null;
    }

    function setInvalid(input, message) {
        if (!input) return;
        input.classList.add('is-invalid');
        const feedback = getFeedbackElement(input);
        if (message && feedback) {
            feedback.textContent = message;
        }
    }

    function clearInvalid(input) {
        if (!input) return;
        input.classList.remove('is-invalid');
    }

    function sanitizeDigits(value) {
        return (value || '').replace(/\D/g, '');
    }

    function isPeruPhone(value) {
        if (!value) return true;
        const digits = sanitizeDigits(value);
        if (digits.startsWith('51')) {
            return /^51\d{9}$/.test(digits);
        }
        return /^9\d{8}$/.test(digits);
    }

    function isValidEmail(value) {
        if (!value) return true;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function getDocumentoError(tipo, numero) {
        const valor = (numero || '').trim();
        if (!valor) return 'Ingrese el número de documento.';
        
        if (tipo === 'RUC') {
            if (!/^\d+$/.test(valor)) return 'El RUC debe contener solo números.';
            if (sanitizeDigits(valor).length !== 11) return 'El RUC debe tener 11 dígitos.';
            return null;
        }
        if (tipo === 'DNI') {
            if (!/^\d+$/.test(valor)) return 'El DNI debe contener solo números.';
            if (sanitizeDigits(valor).length !== 8) return 'El DNI debe tener 8 dígitos.';
        }
        return null;
    }

    function resetFields(containerEl) {
        if (!containerEl) return;
        containerEl.querySelectorAll('input, select, textarea').forEach(el => {
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = false;
            } else {
                el.value = '';
            }
            el.classList.remove('is-invalid');
            el.disabled = false; // Reset disabled state
        });
    }

    // =========================================================================
    // GENERADORES DE FILAS (Teléfonos y Cuentas)
    // =========================================================================

    function createRemoveButton(onClick) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-danger btn-sm';
        btn.innerHTML = '<i class="bi bi-trash"></i>';
        btn.title = 'Eliminar';
        btn.addEventListener('click', onClick);
        return btn;
    }

    function buildTelefonoRow({ telefono = '', tipo = '' } = {}, onRemove) {
        const wrapper = document.createElement('div');
        wrapper.className = 'input-group mb-2';

        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'text';
        telefonoInput.name = 'telefonos[]';
        telefonoInput.className = 'form-control';
        telefonoInput.placeholder = 'Número';
        telefonoInput.value = telefono;

        const tipoSelect = document.createElement('select');
        tipoSelect.name = 'telefono_tipos[]';
        tipoSelect.className = 'form-select';
        ['', 'Móvil', 'Fijo', 'WhatsApp', 'Trabajo'].forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt === '' ? 'Tipo' : opt;
            if (opt === tipo) option.selected = true;
            tipoSelect.appendChild(option);
        });

        const removeBtn = createRemoveButton(() => {
            wrapper.remove();
            if (typeof onRemove === 'function') onRemove();
        });

        wrapper.appendChild(telefonoInput);
        wrapper.appendChild(tipoSelect);
        wrapper.appendChild(removeBtn);
        return wrapper;
    }

    function buildCuentaRow({ tipo = '', entidad = '', tipo_cta = '', numero_cuenta = '', cci = '', alias = '', moneda = 'PEN', principal = 0, billetera_digital = 0, observaciones = '' } = {}, onRemove) {
        const wrapper = document.createElement('div');
        wrapper.className = 'card mb-2 bg-light border';

        const tipoEntidadVal = tipo || ''; 

        const cardBody = document.createElement('div');
        cardBody.className = 'card-body p-2';

        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center';

        // Col 1: Tipo Entidad
        const col1 = document.createElement('div');
        col1.className = 'col-md-3';
        const tipoEntSelect = document.createElement('select');
        tipoEntSelect.name = 'cuenta_tipo[]';
        tipoEntSelect.className = 'form-select form-select-sm';
        ['', 'BANCO', 'BILLETERA', 'CAJA', 'COOPERATIVA', 'OTRO'].forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt === '' ? 'Tipo' : opt;
            if (opt === tipoEntidadVal) option.selected = true;
            tipoEntSelect.appendChild(option);
        });
        col1.appendChild(tipoEntSelect);

        // Col 2: Entidad (Input con Datalist)
        const col2 = document.createElement('div');
        col2.className = 'col-md-3';
        const entidadInput = document.createElement('input');
        entidadInput.name = 'cuenta_entidad[]';
        entidadInput.className = 'form-control form-control-sm';
        entidadInput.placeholder = 'Entidad';
        entidadInput.value = entidad;
        
        const datalist = document.createElement('datalist');
        const datalistId = `entidad-list-${Math.random().toString(36).slice(2)}`;
        datalist.id = datalistId;
        entidadInput.setAttribute('list', datalistId);
        
        col2.appendChild(entidadInput);
        col2.appendChild(datalist);

        // Col 3: Tipo Cuenta
        const col3 = document.createElement('div');
        col3.className = 'col-md-3';
        const tipoCuentaSelect = document.createElement('select');
        tipoCuentaSelect.name = 'cuenta_tipo_cta[]';
        tipoCuentaSelect.className = 'form-select form-select-sm';
        ['', 'AHORROS', 'CORRIENTE', 'MAESTRA'].forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt === '' ? 'Tipo Cta.' : opt;
            if (opt === tipo_cta) option.selected = true;
            tipoCuentaSelect.appendChild(option);
        });
        col3.appendChild(tipoCuentaSelect);

        // Col 4: Número y Botón
        const col4 = document.createElement('div');
        col4.className = 'col-md-3';
        const inputGroup = document.createElement('div');
        inputGroup.className = 'input-group input-group-sm';
        
        const numeroInput = document.createElement('input');
        numeroInput.name = 'cuenta_numero[]';
        numeroInput.className = 'form-control';
        numeroInput.placeholder = 'N° Cuenta';
        numeroInput.value = numero_cuenta;

        const removeBtn = createRemoveButton(() => {
            wrapper.remove();
            if (typeof onRemove === 'function') onRemove();
        });

        inputGroup.appendChild(numeroInput);
        inputGroup.appendChild(removeBtn);
        col4.appendChild(inputGroup);

        // Fila 2: CCI y Moneda
        const row2 = document.createElement('div');
        row2.className = 'row g-2 mt-1';
        
        // CCI
        const colCci = document.createElement('div');
        colCci.className = 'col-md-4';
        const cciInput = document.createElement('input');
        cciInput.type = 'text';
        cciInput.name = 'cuenta_cci[]';
        cciInput.className = 'form-control form-control-sm';
        cciInput.placeholder = 'CCI / Celular Yape';
        cciInput.value = cci;
        colCci.appendChild(cciInput);

        // Moneda
        const colMoneda = document.createElement('div');
        colMoneda.className = 'col-md-3';
        const monedaSelect = document.createElement('select');
        monedaSelect.name = 'cuenta_moneda[]';
        monedaSelect.className = 'form-select form-select-sm';
        ['PEN', 'USD'].forEach(m => {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = m;
            if(m === moneda) opt.selected = true;
            monedaSelect.appendChild(opt);
        });
        colMoneda.appendChild(monedaSelect);

        row2.appendChild(colCci);
        row2.appendChild(colMoneda);

        row.appendChild(col1);
        row.appendChild(col2);
        row.appendChild(col3);
        row.appendChild(col4);
        
        cardBody.appendChild(row);
        cardBody.appendChild(row2);
        wrapper.appendChild(cardBody);

        const updateEntidadList = () => {
            const opciones = ENTIDADES_FINANCIERAS[tipoEntSelect.value] || [];
            datalist.innerHTML = '';
            opciones.forEach(nombre => {
                const option = document.createElement('option');
                option.value = nombre;
                datalist.appendChild(option);
            });
        };

        tipoEntSelect.addEventListener('change', updateEntidadList);
        updateEntidadList();

        return wrapper;
    }

    // =========================================================================
    // LÓGICA DE UI Y TOGGLES
    // =========================================================================

    function toggleLaboralFields(checkboxEl, containerEl) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.classList.toggle('d-none', !show);
        if (!show) resetFields(containerEl);
    }

    function togglePagoFields(tipoPagoEl) {
        if (!tipoPagoEl) return;
        
        const form = tipoPagoEl.closest('form');
        const sueldoInput = form.querySelector('[name="sueldo_basico"]');
        const pagoDiarioInput = form.querySelector('[name="pago_diario"]');
        
        const sueldoCol = sueldoInput?.closest('.col-md-6') || sueldoInput?.closest('.col-md-4'); // Support both layouts
        const diarioCol = pagoDiarioInput?.closest('.col-md-6') || pagoDiarioInput?.closest('.col-md-4');

        if (!sueldoCol || !diarioCol) return;

        const tipo = tipoPagoEl.value;
        const showSueldo = tipo === 'SUELDO' || tipo === 'MENSUAL' || tipo === 'QUINCENAL'; 
        const showDiario = tipo === 'DIARIO';

        // Resetear visualización
        sueldoCol.style.display = 'none';
        diarioCol.style.display = 'none';

        if (showSueldo) {
            sueldoCol.style.display = '';
            pagoDiarioInput.value = '';
        } else if (showDiario) {
            diarioCol.style.display = '';
            sueldoInput.value = '';
        }
    }

    function toggleComercialFields(clienteEl, proveedorEl, containerEl) {
        if (!containerEl) return;
        const show = Boolean(clienteEl?.checked || proveedorEl?.checked);
        containerEl.classList.toggle('d-none', !show); 
        if (!show) resetFields(containerEl);
    }

    function toggleRegimenFields(regimenEl, form) {
        if(!regimenEl || !form) return;
        const comisionSelect = form.querySelector('[name="tipo_comision_afp"]');
        const cusppInput = form.querySelector('[name="cuspp"]');
        
        if(comisionSelect && cusppInput) {
            const val = regimenEl.value;
            // Si es ONP o Ninguno, deshabilitar AFP fields
            const isAfp = val && val !== 'Ninguno' && val !== 'ONP';
            
            comisionSelect.disabled = !isAfp;
            cusppInput.disabled = !isAfp;
            
            if(!isAfp) {
                comisionSelect.value = '';
                cusppInput.value = '';
            }
        }
    }

    // =========================================================================
    // LÓGICA DE UBIGEO CON AJAX
    // =========================================================================

    function setUbigeoOptions(departamentoEl, provinciaEl, distritoEl, selected = {}) {
        if (!departamentoEl || !provinciaEl || !distritoEl) return;

        const loadUbigeoData = (tipo, padreId, selectEl, preSelectedValue, preSelectedName) => {
            selectEl.innerHTML = '<option value="">Seleccionar...</option>';
            selectEl.disabled = true;

            const fd = new FormData();
            fd.append('accion', 'cargar_ubigeo');
            fd.append('tipo', tipo);
            fd.append('padre_id', padreId);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                selectEl.innerHTML = '<option value="">Seleccionar...</option>';
                let matched = false;
                if(res.ok && res.data) {
                    res.data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = item.nombre;
                        
                        if(preSelectedValue && String(preSelectedValue) !== '0' && String(item.id) === String(preSelectedValue)) {
                            opt.selected = true;
                            matched = true;
                        }
                        else if(!matched && preSelectedName && item.nombre && String(item.nombre).toUpperCase() === String(preSelectedName).toUpperCase()) {
                            opt.selected = true;
                            matched = true;
                        }
                        
                        selectEl.appendChild(opt);
                    });
                }
                selectEl.disabled = false;
                
                if (matched) {
                    selectEl.dispatchEvent(new Event('change'));
                }
            })
            .catch(err => {
                console.error("Error cargando ubigeo:", err);
                selectEl.innerHTML = '<option value="">Error</option>';
                selectEl.disabled = false;
            });
        };

        departamentoEl.addEventListener('change', function() {
            const depId = this.value;
            distritoEl.innerHTML = '<option value="">Seleccionar...</option>'; 
            distritoEl.disabled = true; 
            
            if(depId) {
                const nextVal = this.dataset.nextSelectVal || null;
                const nextName = this.dataset.nextSelectName || null; 
                loadUbigeoData('provincias', depId, provinciaEl, nextVal, nextName);
                this.dataset.nextSelectVal = '';
                this.dataset.nextSelectName = '';
            } else {
                provinciaEl.innerHTML = '<option value="">Seleccionar...</option>';
                provinciaEl.disabled = true; 
            }
        });

        provinciaEl.addEventListener('change', function() {
            const provId = this.value;
            if(provId) {
                const nextVal = this.dataset.nextSelectVal || null;
                const nextName = this.dataset.nextSelectName || null; 
                loadUbigeoData('distritos', provId, distritoEl, nextVal, nextName);
                this.dataset.nextSelectVal = '';
                this.dataset.nextSelectName = '';
            } else {
                distritoEl.innerHTML = '<option value="">Seleccionar...</option>';
                distritoEl.disabled = true; 
            }
        });

        if (selected.departamento) {
            departamentoEl.value = selected.departamento;
            departamentoEl.dataset.nextSelectVal = selected.provincia || '';
            departamentoEl.dataset.nextSelectName = selected.provinciaNombre || '';
            provinciaEl.dataset.nextSelectVal = selected.distrito || '';
            provinciaEl.dataset.nextSelectName = selected.distritoNombre || '';
            departamentoEl.dispatchEvent(new Event('change'));
        } else {
            provinciaEl.disabled = true;
            distritoEl.disabled = true;
        }
    }

    // =========================================================================
    // VALIDACIONES
    // =========================================================================

    function validateForm(form, rolesFeedbackId, showErrors = true) {
        let valid = true;
        const tipoPersona = form.querySelector('[name="tipo_persona"]');
        const tipoDoc = form.querySelector('[name="tipo_documento"]');
        const numeroDoc = form.querySelector('[name="numero_documento"]');
        const nombre = form.querySelector('[name="nombre_completo"]');
        const email = form.querySelector('[name="email"]');
        
        // Laborales
        const cargo = form.querySelector('[name="cargo"]');
        const area = form.querySelector('[name="area"]');
        const fechaIngreso = form.querySelector('[name="fecha_ingreso"]');
        const tipoPago = form.querySelector('[name="tipo_pago"]');
        const sueldoBasico = form.querySelector('[name="sueldo_basico"]');
        const pagoDiario = form.querySelector('[name="pago_diario"]');

        [tipoPersona, tipoDoc, numeroDoc, nombre, email, cargo, area, fechaIngreso, tipoPago, sueldoBasico, pagoDiario].forEach(clearInvalid);
        form.querySelectorAll('input[name="telefonos[]"], select[name="telefono_tipos[]"]').forEach(clearInvalid);
        form.querySelectorAll('select[name="cuenta_tipo[]"], input[name="cuenta_entidad[]"], select[name="cuenta_tipo_cta[]"], input[name="cuenta_numero[]"]').forEach(clearInvalid);

        if (!tipoPersona?.value) { if (showErrors) setInvalid(tipoPersona, 'Seleccione el tipo de persona.'); valid = false; }
        if (!tipoDoc?.value) { if (showErrors) setInvalid(tipoDoc, 'Seleccione el tipo de documento.'); valid = false; }

        const numero = (numeroDoc?.value || '').trim();
        const docError = getDocumentoError(tipoDoc?.value || '', numero);
        if (docError) {
            if (numeroDoc) numeroDoc.setCustomValidity(docError);
            if (showErrors) setInvalid(numeroDoc, docError);
            valid = false;
        } else if (numeroDoc?.validity?.customError) {
            if (showErrors) setInvalid(numeroDoc, numeroDoc.validationMessage);
            valid = false;
        } else if (numeroDoc) {
            numeroDoc.setCustomValidity('');
        }

        if (!nombre?.value.trim()) { if (showErrors) setInvalid(nombre, 'Ingrese el nombre o razón social.'); valid = false; }
        if (!isValidEmail(email?.value || '')) { if (showErrors) setInvalid(email, 'Ingrese un email válido.'); valid = false; }

        form.querySelectorAll('input[name="telefonos[]"]').forEach(input => {
            if (input.value.trim() === '') {
                if (showErrors) setInvalid(input, 'Ingrese el número o elimine la fila.');
                valid = false;
            } else if (!isPeruPhone(input.value)) {
                if (showErrors) setInvalid(input, 'Teléfono inválido.');
                valid = false;
            }
        });
        
        const hasRole = Array.from(form.querySelectorAll('input[name="es_cliente"], input[name="es_proveedor"], input[name="es_empleado"]')).some(el => el.checked);
        const rolesFeedback = document.getElementById(rolesFeedbackId);
        if (!hasRole) {
            if (showErrors && rolesFeedback) {
                rolesFeedback.classList.remove('d-none');
                rolesFeedback.classList.add('d-block');
            }
            valid = false;
        } else if (rolesFeedback) {
            rolesFeedback.classList.add('d-none');
            rolesFeedback.classList.remove('d-block');
        }

        if (form.querySelector('[name="es_empleado"]')?.checked) {
            if (!cargo?.value.trim()) { if(showErrors) setInvalid(cargo, 'Requerido'); valid = false; }
            if (!area?.value.trim()) { if(showErrors) setInvalid(area, 'Requerido'); valid = false; }
            if (!fechaIngreso?.value) { if(showErrors) setInvalid(fechaIngreso, 'Requerido'); valid = false; }
            
            const tp = tipoPago?.value;
            if ((tp === 'MENSUAL' || tp === 'QUINCENAL') && !sueldoBasico?.value) {
                 if(showErrors) setInvalid(sueldoBasico, 'Requerido'); valid = false;
            }
            if (tp === 'DIARIO' && !pagoDiario?.value) {
                 if(showErrors) setInvalid(pagoDiario, 'Requerido'); valid = false;
            }
        }

        const cTipos = form.querySelectorAll('select[name="cuenta_tipo[]"]');
        const cEntidades = form.querySelectorAll('input[name="cuenta_entidad[]"]');
        const cNumeros = form.querySelectorAll('input[name="cuenta_numero[]"]');

        cTipos.forEach((tipoEl, i) => {
            const entidadEl = cEntidades[i];
            const numEl = cNumeros[i];
            
            if (!tipoEl.value) { if(showErrors) setInvalid(tipoEl, 'Requerido'); valid = false; }
            if (!entidadEl.value.trim()) { if(showErrors) setInvalid(entidadEl, 'Requerido'); valid = false; }
            if (!numEl.value.trim()) { if(showErrors) setInvalid(numEl, 'Requerido'); valid = false; }
        });

        return valid;
    }

    function refreshValidationOnChange(form, rolesFeedbackId) {
        if (!form) return;
        if (form.dataset.submitted === '1') {
            validateForm(form, rolesFeedbackId, true);
        }
    }

    // =========================================================================
    // INICIALIZADORES
    // =========================================================================

    function initTelefonosSection(listEl, addButton, form, rolesFeedbackId, telefonos = []) {
        if (!listEl || !addButton) return;
        listEl.innerHTML = '';
        const addRow = (data = {}) => {
            const row = buildTelefonoRow(data, () => refreshValidationOnChange(form, rolesFeedbackId));
            listEl.appendChild(row);
        };
        (telefonos || []).forEach(item => addRow(item));
        
        addButton.onclick = () => {
            addRow();
            refreshValidationOnChange(form, rolesFeedbackId);
        };
    }

    function initCuentasSection(listEl, addButton, form, rolesFeedbackId, cuentas = []) {
        if (!listEl || !addButton) return;
        listEl.innerHTML = '';
        const addRow = (data = {}) => {
            const row = buildCuentaRow(data, () => refreshValidationOnChange(form, rolesFeedbackId));
            listEl.appendChild(row);
        };
        (cuentas || []).forEach(item => addRow(item));
        
        addButton.onclick = () => {
            addRow();
            refreshValidationOnChange(form, rolesFeedbackId);
        };
    }

    function submitForm(form, submitButton, rolesFeedbackId) {
        if (!form) return;
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            form.dataset.submitted = '1';

            if (!validateForm(form, rolesFeedbackId, true)) return;

            Swal.fire({
                title: '¿Confirmar guardado?',
                text: 'Verifica la información antes de continuar.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (!result.isConfirmed) return;

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
                }

                const formData = new FormData(form);
                const targetUrl = form.getAttribute('action') || window.location.href;

                fetch(targetUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok || !data.ok) throw new Error(data.mensaje || 'Error al guardar.');
                    
                    Swal.fire('Guardado', data.mensaje || 'Registro guardado.', 'success').then(() => {
                        const modalEl = form.closest('.modal');
                        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        window.location.reload();
                    });
                })
                .catch(err => {
                    Swal.fire('Error', err.message, 'error');
                })
                .finally(() => {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = submitButton.getAttribute('data-original-text') || submitButton.innerHTML;
                    }
                });
            });
        });
    }

    function initCreateModal() {
        const modalCreate = document.getElementById('modalCrearTercero');
        if (!modalCreate) return;

        modalCreate.addEventListener('show.bs.modal', function () {
            const form = document.getElementById('formCrearTercero');
            if (form) {
                form.reset();
                form.dataset.submitted = '0';
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            }
            
            document.getElementById('crearRolesFeedback')?.classList.add('d-none');
            
            const provSelect = document.getElementById('crearProvincia');
            const distSelect = document.getElementById('crearDistrito');
            if (provSelect) {
                provSelect.innerHTML = '<option value="">Seleccionar...</option>';
                provSelect.disabled = true;
            }
            if (distSelect) {
                distSelect.innerHTML = '<option value="">Seleccionar...</option>';
                distSelect.disabled = true;
            }

            toggleComercialFields(
                document.getElementById('crearEsCliente'),
                document.getElementById('crearEsProveedor'),
                document.getElementById('crearComercialFields')
            );
            toggleLaboralFields(document.getElementById('crearEsEmpleado'), document.getElementById('crearLaboralFields'));
            togglePagoFields(document.getElementById('crearTipoPago'));
            // Inicializar estado de campos AFP
            toggleRegimenFields(document.getElementById('crearRegimen'), form);

            initTelefonosSection(
                document.getElementById('crearTelefonosList'),
                document.getElementById('crearAgregarTelefono'),
                form, 'crearRolesFeedback', []
            );
            initCuentasSection(
                document.getElementById('crearCuentasBancariasList'),
                document.getElementById('crearAgregarCuenta'),
                form, 'crearRolesFeedback', []
            );
        });
    }

    function initEditModal() {
        const modalEdit = document.getElementById('modalEditarTercero');
        if (!modalEdit) return;

        modalEdit.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const form = document.getElementById('formEditarTercero');
            if(form) {
                form.dataset.submitted = '0';
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            }

            const fields = {
                'editId': 'data-id',
                'editTipoPersona': 'data-tipo-persona',
                'editTipoDoc': 'data-tipo-doc',
                'editNumeroDoc': 'data-numero-doc',
                'editNombre': 'data-nombre',
                'editDireccion': 'data-direccion',
                'editEmail': 'data-email',
                'editObservaciones': 'data-observaciones',
                
                // Comercial
                'editCondicionPago': 'data-condicion-pago',
                'editClienteDiasCredito': 'data-cliente-dias-credito',
                'editClienteLimiteCredito': 'data-cliente-limite-credito',
                'editProvCondicion': 'data-proveedor-condicion-pago',
                'editProvDiasCredito': 'data-proveedor-dias-credito',

                // Laboral
                'editCargo': 'data-cargo',
                'editArea': 'data-area',
                'editTipoContrato': 'data-tipo-contrato',
                'editFechaIngreso': 'data-fecha-ingreso',
                'editFechaCese': 'data-fecha-cese',
                'editEstadoLaboral': 'data-estado-laboral',
                'editMoneda': 'data-moneda',
                'editSueldoBasico': 'data-sueldo-basico',
                'editRegimen': 'data-regimen-pensionario',
                'editTipoComision': 'data-tipo-comision-afp',
                'editCuspp': 'data-cuspp',
                'editTipoPago': 'data-tipo-pago',
                'editPagoDiario': 'data-pago-diario'
            };

            for (let id in fields) {
                const el = document.getElementById(id);
                if (el) el.value = button.getAttribute(fields[id]) || '';
            }

            const essalud = document.getElementById('editEssalud');
            if (essalud) essalud.checked = button.getAttribute('data-essalud') === '1';
            
            const asigFam = document.getElementById('editAsignacionFamiliar');
            if (asigFam) asigFam.checked = button.getAttribute('data-asignacion-familiar') === '1';

            ['editEsCliente', 'editEsProveedor', 'editEsEmpleado'].forEach(id => {
                const el = document.getElementById(id);
                if(el) el.checked = button.getAttribute('data-' + id.replace('edit','').replace(/([A-Z])/g, '-$1').toLowerCase().slice(1)) === '1';
            });

            setUbigeoOptions(
                document.getElementById('editDepartamento'),
                document.getElementById('editProvincia'),
                document.getElementById('editDistrito'),
                {
                    departamento: button.getAttribute('data-departamento'),
                    provincia: button.getAttribute('data-provincia'),
                    distrito: button.getAttribute('data-distrito'),
                    provinciaNombre: button.getAttribute('data-provincia-nombre'),
                    distritoNombre: button.getAttribute('data-distrito-nombre')
                }
            );

            document.getElementById('editRolesFeedback')?.classList.add('d-none');
            
            toggleComercialFields(
                document.getElementById('editEsCliente'),
                document.getElementById('editEsProveedor'),
                document.getElementById('editComercialFields')
            );
            toggleLaboralFields(document.getElementById('editEsEmpleado'), document.getElementById('editLaboralFields'));
            togglePagoFields(document.getElementById('editTipoPago'));
            // Actualizar estado de campos AFP al cargar
            toggleRegimenFields(document.getElementById('editRegimen'), form);

            let telefonos = [], cuentas = [];
            try { telefonos = JSON.parse(button.getAttribute('data-telefonos') || '[]'); } catch(e){}
            try { cuentas = JSON.parse(button.getAttribute('data-cuentas-bancarias') || '[]'); } catch(e){}

            initTelefonosSection(
                document.getElementById('editTelefonosList'),
                document.getElementById('editAgregarTelefono'),
                form, 'editRolesFeedback', telefonos
            );
            initCuentasSection(
                document.getElementById('editCuentasBancariasList'),
                document.getElementById('editAgregarCuenta'),
                form, 'editRolesFeedback', cuentas
            );
        });
    }

    function initDynamicFields() {
        const setup = (prefix) => {
            const formId = `form${prefix === 'crear' ? 'Crear' : 'Editar'}Tercero`;
            const form = document.getElementById(formId);
            const fbId = `${prefix}RolesFeedback`;
            
            const esEmpleado = document.getElementById(`${prefix}EsEmpleado`);
            if(esEmpleado) esEmpleado.addEventListener('change', () => {
                toggleLaboralFields(esEmpleado, document.getElementById(`${prefix}LaboralFields`));
                refreshValidationOnChange(form, fbId);
            });

            const esCliente = document.getElementById(`${prefix}EsCliente`);
            const esProv = document.getElementById(`${prefix}EsProveedor`);
            const updateCom = () => {
                toggleComercialFields(esCliente, esProv, document.getElementById(`${prefix}ComercialFields`));
                refreshValidationOnChange(form, fbId);
            };
            if(esCliente) esCliente.addEventListener('change', updateCom);
            if(esProv) esProv.addEventListener('change', updateCom);

            const tipoPago = document.getElementById(`${prefix}TipoPago`);
            if(tipoPago) tipoPago.addEventListener('change', () => {
                togglePagoFields(tipoPago);
                refreshValidationOnChange(form, fbId);
            });
            
            // NUEVO: Toggle para Regimen AFP/ONP
            const regimen = document.getElementById(`${prefix}Regimen`);
            if(regimen) regimen.addEventListener('change', () => {
                toggleRegimenFields(regimen, form);
            });
        };

        setup('crear');
        setup('edit');
    }

    // =========================================================================
    // LÓGICA DE GESTIÓN DE MAESTROS (CARGOS Y ÁREAS)
    // =========================================================================

    function initMaestrosManagement() {
        // Función genérica para configurar Cargo y Área
        const setupMaestro = (tipo) => { // tipo = 'cargo' o 'area'
            const Cap = tipo.charAt(0).toUpperCase() + tipo.slice(1); // 'Cargo' o 'Area'
            const form = document.getElementById(`formCrear${Cap}`);
            const inputAccion = document.getElementById(`${tipo}Accion`);
            const inputId = document.getElementById(`${tipo}Id`);
            const inputNombre = document.getElementById(`${tipo}Nombre`);
            const btnSave = document.getElementById(`btnSave${Cap}`);
            const btnCancel = document.getElementById(`btnCancel${Cap}`);
            const lista = document.getElementById(`lista${Cap}sConfig`); // listaCargosConfig
            const modal = document.getElementById(`modalGestion${Cap}s`);

            // 1. Resetear al abrir modal
            if(modal){
                modal.addEventListener('show.bs.modal', () => {
                    resetForm();
                });
            }

            // 2. Función Reset
            const resetForm = () => {
                if(form) {
                    form.reset();
                    if(inputAccion) inputAccion.value = `guardar_${tipo}`;
                    if(inputId) inputId.value = '';
                    if(btnSave) {
                        btnSave.innerHTML = '<i class="bi bi-plus-lg"></i>';
                        btnSave.classList.remove('btn-warning');
                        btnSave.classList.add('btn-primary');
                    }
                    if(btnCancel) btnCancel.classList.add('d-none');
                }
            };

            // 3. Manejar Submit (Crear o Editar)
            if(form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const fd = new FormData(form);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) {
                            Swal.fire({
                                title: 'Éxito', 
                                text: res.mensaje, 
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000
                            });
                            
                            // Recargar la página para actualizar todas las listas y selects
                            // (Es lo más seguro para sincronizar todo sin complicar el JS)
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            Swal.fire('Error', res.mensaje, 'error');
                        }
                    });
                });
            }

            // 4. Manejar Clics en la lista (Editar / Eliminar)
            if(lista) {
                lista.addEventListener('click', (e) => {
                    const btnEdit = e.target.closest('.btn-edit-maestro');
                    const btnDel = e.target.closest('.btn-del-maestro');

                    // A) MODO EDICIÓN
                    if (btnEdit) {
                        const id = btnEdit.dataset.id;
                        const nombre = btnEdit.dataset.nombre;
                        
                        inputAccion.value = `editar_${tipo}`;
                        inputId.value = id;
                        inputNombre.value = nombre;
                        inputNombre.focus();
                        
                        btnSave.innerHTML = '<i class="bi bi-check-lg"></i>';
                        btnSave.classList.remove('btn-primary');
                        btnSave.classList.add('btn-warning');
                        btnCancel.classList.remove('d-none');
                    }

                    // B) MODO ELIMINAR (Soft Delete)
                    if (btnDel) {
                        Swal.fire({
                            title: '¿Desactivar?',
                            text: "Ya no aparecerá en los listados nuevos.",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            confirmButtonText: 'Sí, desactivar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const fd = new FormData();
                                fd.append('accion', `eliminar_${tipo}`);
                                fd.append('id', btnDel.dataset.id);

                                fetch(window.location.href, {
                                    method: 'POST',
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                    body: fd
                                })
                                .then(r => r.json())
                                .then(res => {
                                    if(res.ok) {
                                        btnDel.closest('.list-group-item').remove();
                                        Swal.fire('Eliminado', res.mensaje, 'success');
                                    }
                                });
                            }
                        });
                    }
                });
            }

            // 5. Botón Cancelar Edición
            if(btnCancel) {
                btnCancel.addEventListener('click', resetForm);
            }
        };

        setupMaestro('cargo');
        setupMaestro('area');
    }

    function initStatusSwitch() {
        document.querySelectorAll('.switch-estado-tercero').forEach(switchInput => {
            switchInput.addEventListener('change', function () {
                const terceroId = this.getAttribute('data-id');
                const nuevoEstado = this.checked ? 1 : 0;
                const fila = this.closest('tr');
                const badge = document.getElementById(`badge_status_tercero_${terceroId}`);

                const formData = new FormData();
                formData.append('accion', 'toggle_estado');
                formData.append('id', terceroId);
                formData.append('estado', nuevoEstado);

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) throw new Error(data.mensaje);
                    if (fila) fila.setAttribute('data-estado', nuevoEstado);
                    if (badge) {
                        badge.textContent = nuevoEstado === 1 ? 'Activo' : 'Inactivo';
                        badge.className = `badge-status status-${nuevoEstado === 1 ? 'active' : 'inactive'}`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    this.checked = !this.checked;
                    Swal.fire('Error', 'No se pudo actualizar el estado.', 'error');
                });
            });
        });
    }

    function bindFormRealtimeValidation(form, submitButton, rolesFeedbackId) {
        if (!form) return;
        form.querySelectorAll('input, select, textarea').forEach(field => {
            const evt = (field.tagName === 'SELECT' || field.type === 'checkbox' || field.type === 'radio') ? 'change' : 'input';
            field.addEventListener(evt, () => refreshValidationOnChange(form, rolesFeedbackId));
        });
    }

    function initDocumentoValidation() {
        const validar = (tipoEl, numeroEl, excludeIdVal) => {
            if (!tipoEl || !numeroEl) return;
            const tipo = tipoEl.value;
            const numero = numeroEl.value.trim();
            if (!tipo || !numero) return;

            const error = getDocumentoError(tipo, numero);
            if (error) {
                numeroEl.setCustomValidity(error);
                if (numeroEl.closest('form')?.dataset.submitted === '1') numeroEl.classList.add('is-invalid');
                return;
            }

            const fd = new FormData();
            fd.append('accion', 'validar_documento');
            fd.append('tipo_documento', tipo);
            fd.append('numero_documento', numero);
            if (excludeIdVal) fd.append('exclude_id', excludeIdVal);

            fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(r => r.json())
                .then(d => {
                    const msg = d.existe ? 'Documento ya registrado.' : '';
                    numeroEl.setCustomValidity(msg);
                    if(d.existe && numeroEl.closest('form')?.dataset.submitted === '1') numeroEl.classList.add('is-invalid');
                    else if(!d.existe) numeroEl.classList.remove('is-invalid');
                })
                .catch(console.error);
        };

        const setup = (tipoId, numId, excludeFn) => {
            const t = document.getElementById(tipoId);
            const n = document.getElementById(numId);
            if(t && n) {
                const run = () => validar(t, n, excludeFn ? excludeFn() : null);
                t.addEventListener('change', run);
                n.addEventListener('blur', run);
            }
        };

        setup('crearTipoDoc', 'crearNumeroDoc', null);
        setup('editTipoDoc', 'editNumeroDoc', () => document.getElementById('editId')?.value);
    }

    // =========================================================================
    // BOOTSTRAP
    // =========================================================================

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof ERPTable !== 'undefined' && ERPTable.initTooltips) {
            ERPTable.initTooltips();
        } else {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        }

        initCreateModal();
        initEditModal();
        initDynamicFields();
        initStatusSwitch();
        initDocumentoValidation();
        initMaestrosManagement(); // Nuevo iniciador para cargos/areas

        const crearBtn = document.getElementById('crearGuardarBtn');
        if (crearBtn) {
            crearBtn.setAttribute('data-original-text', crearBtn.innerHTML);
            bindFormRealtimeValidation(document.getElementById('formCrearTercero'), crearBtn, 'crearRolesFeedback');
            submitForm(document.getElementById('formCrearTercero'), crearBtn, 'crearRolesFeedback');
        }

        const editBtn = document.getElementById('editGuardarBtn');
        if (editBtn) {
            editBtn.setAttribute('data-original-text', editBtn.innerHTML);
            bindFormRealtimeValidation(document.getElementById('formEditarTercero'), editBtn, 'editRolesFeedback');
            submitForm(document.getElementById('formEditarTercero'), editBtn, 'editRolesFeedback');
        }

        if (typeof ERPTable !== 'undefined' && ERPTable.createTableManager) {
            window.tercerosTableManager = ERPTable.createTableManager({
                tableSelector: '#tercerosTable',
                searchInput: '#terceroSearch',
                filters: [
                    { el: '#terceroFiltroRol', attr: 'data-roles' },
                    { el: '#terceroFiltroEstado', attr: 'data-estado' }
                ],
                paginationControls: '#tercerosPaginationControls',
                paginationInfo: '#tercerosPaginationInfo',
                rowsPerPage: 10,
                emptyText: 'No se encontraron terceros registrados.',
                infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} terceros`
            }).init();
        } else {
            console.warn('ERPTable no está cargado. La tabla no tendrá paginación/filtros JS.');
        }
    });

})();