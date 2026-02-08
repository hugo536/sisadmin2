(function () {
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    const UBIGEO_DATA = {
        LIMA: {
            LIMA: ['Miraflores', 'San Isidro', 'Surco'],
            HUAROCHIRI: ['Matucana', 'San Pedro de Casta'],
            CAÑETE: ['San Vicente', 'Imperial']
        },
        AREQUIPA: {
            AREQUIPA: ['Cayma', 'Yanahuara', 'Cerro Colorado'],
            CAMANA: ['Camaná', 'Ocoña']
        },
        LA_LIBERTAD: {
            TRUJILLO: ['Trujillo', 'La Esperanza', 'Víctor Larco'],
            ASCOPE: ['Chocope', 'Rázuri']
        }
    };

    const ENTIDADES_FINANCIERAS = {
        BANCO: ['BCP', 'BBVA', 'Interbank', 'Scotiabank', 'Banco de la Nación', 'Pichincha'],
        BILLETERA: ['Yape', 'Plin', 'Tunki', 'Agora Pay', 'BIM'],
        CAJA: ['Caja Huancayo', 'Caja Piura', 'Caja Arequipa', 'Caja Sullana'],
        COOPERATIVA: ['Coopac Pacífico', 'Coopac San Cristóbal'],
        OTRO: []
    };

    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

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
        if (!valor) {
            return 'Ingrese el número de documento.';
        }
        if (tipo === 'RUC') {
            if (!/^\d+$/.test(valor)) {
                return 'El RUC debe contener solo números.';
            }
            const digits = sanitizeDigits(valor);
            if (digits.length !== 11) {
                return 'El RUC debe tener 11 dígitos.';
            }
            return null;
        }
        if (tipo === 'DNI') {
            if (!/^\d+$/.test(valor)) {
                return 'El DNI debe contener solo números.';
            }
            const digits = sanitizeDigits(valor);
            if (digits.length !== 8) {
                return 'El DNI debe tener 8 dígitos.';
            }
        }
        return null;
    }

    function updateNombreLabel(tipoPersonaEl, labelEl) {
        if (!tipoPersonaEl || !labelEl) return;
        const tipo = tipoPersonaEl.value;
        labelEl.innerHTML = (tipo === 'JURIDICA' ? 'Razón social' : 'Nombre completo') + ' <span class="text-danger">*</span>';
    }

    function resetFields(containerEl) {
        if (!containerEl) return;
        containerEl.querySelectorAll('input, select, textarea').forEach(el => {
            if (el.type === 'checkbox') {
                el.checked = false;
            } else {
                el.value = '';
            }
            el.classList.remove('is-invalid');
        });
    }

    function buildTelefonoRow({ telefono = '', tipo = '' } = {}, onRemove) {
        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex gap-2 align-items-start';

        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'text';
        telefonoInput.name = 'telefonos[]';
        telefonoInput.className = 'form-control';
        telefonoInput.placeholder = 'Número de teléfono';
        telefonoInput.value = telefono;

        const tipoSelect = document.createElement('select');
        tipoSelect.name = 'telefono_tipos[]';
        tipoSelect.className = 'form-select';
        const tipos = ['', 'Móvil', 'Fijo', 'WhatsApp', 'Trabajo'];
        tipos.forEach(optionValue => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionValue === '' ? 'Tipo' : optionValue;
            if (optionValue === tipo) option.selected = true;
            tipoSelect.appendChild(option);
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-outline-danger btn-sm';
        removeButton.innerHTML = '<i class="bi bi-x-lg"></i>';
        removeButton.addEventListener('click', () => {
            wrapper.remove();
            if (typeof onRemove === 'function') onRemove();
        });

        wrapper.appendChild(telefonoInput);
        wrapper.appendChild(tipoSelect);
        wrapper.appendChild(removeButton);
        return wrapper;
    }

    function buildCuentaRow({ tipo_entidad = '', entidad = '', tipo_cuenta = '', numero_cuenta = '' } = {}, onRemove) {
        const wrapper = document.createElement('div');
        wrapper.className = 'border rounded-3 p-3 bg-light';

        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center';

        const tipoEntCol = document.createElement('div');
        tipoEntCol.className = 'col-md-3';
        const tipoEntSelect = document.createElement('select');
        tipoEntSelect.name = 'cuenta_tipo_entidad[]';
        tipoEntSelect.className = 'form-select';
        const tipoEntOptions = ['', 'BANCO', 'BILLETERA', 'CAJA', 'COOPERATIVA', 'OTRO'];
        tipoEntOptions.forEach(optionValue => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionValue === '' ? 'Tipo' : optionValue;
            if (optionValue === tipo_entidad) option.selected = true;
            tipoEntSelect.appendChild(option);
        });
        tipoEntCol.appendChild(tipoEntSelect);

        const entidadCol = document.createElement('div');
        entidadCol.className = 'col-md-3';
        const entidadInput = document.createElement('input');
        entidadInput.name = 'cuenta_entidad[]';
        entidadInput.className = 'form-control';
        entidadInput.placeholder = 'Entidad (BBVA, Yape...)';
        entidadInput.value = entidad;
        const datalist = document.createElement('datalist');
        const datalistId = `entidad-list-${Math.random().toString(36).slice(2)}`;
        datalist.id = datalistId;
        entidadInput.setAttribute('list', datalistId);
        entidadCol.appendChild(entidadInput);
        entidadCol.appendChild(datalist);

        const tipoCuentaCol = document.createElement('div');
        tipoCuentaCol.className = 'col-md-3';
        const tipoCuentaSelect = document.createElement('select');
        tipoCuentaSelect.name = 'cuenta_tipo_cuenta[]';
        tipoCuentaSelect.className = 'form-select';
        const tipoCuentaOptions = ['', 'AHORROS', 'CORRIENTE'];
        tipoCuentaOptions.forEach(optionValue => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionValue === '' ? 'Tipo de cuenta' : optionValue;
            if (optionValue === tipo_cuenta) option.selected = true;
            tipoCuentaSelect.appendChild(option);
        });
        tipoCuentaCol.appendChild(tipoCuentaSelect);

        const numeroCol = document.createElement('div');
        numeroCol.className = 'col-md-3';
        const numeroInput = document.createElement('input');
        numeroInput.name = 'cuenta_numero[]';
        numeroInput.className = 'form-control';
        numeroInput.placeholder = 'Número / celular';
        numeroInput.value = numero_cuenta;
        numeroCol.appendChild(numeroInput);

        const removeCol = document.createElement('div');
        removeCol.className = 'col-12 text-end mt-2';
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-outline-danger btn-sm';
        removeButton.innerHTML = '<i class="bi bi-trash me-1"></i>Quitar';
        removeButton.addEventListener('click', () => {
            wrapper.remove();
            if (typeof onRemove === 'function') onRemove();
        });
        removeCol.appendChild(removeButton);

        row.appendChild(tipoEntCol);
        row.appendChild(entidadCol);
        row.appendChild(tipoCuentaCol);
        row.appendChild(numeroCol);
        wrapper.appendChild(row);
        wrapper.appendChild(removeCol);

        const updateEntidadList = () => {
            const opciones = ENTIDADES_FINANCIERAS[tipoEntSelect.value] || [];
            datalist.innerHTML = '';
            opciones.forEach(nombre => {
                const option = document.createElement('option');
                option.value = nombre;
                datalist.appendChild(option);
            });
            const isBanco = tipoEntSelect.value === 'BANCO';
            tipoCuentaSelect.disabled = !isBanco;
            if (!isBanco) {
                tipoCuentaSelect.value = '';
            }
        };

        tipoEntSelect.addEventListener('change', updateEntidadList);
        updateEntidadList();

        return wrapper;
    }

    function toggleLaboralFields(checkboxEl, containerEl) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.style.display = show ? '' : 'none';
        if (!show) {
            resetFields(containerEl);
        }
    }

    function togglePagoFields(tipoPagoEl, sueldoWrapper, pagoDiarioWrapper) {
        if (!tipoPagoEl || !sueldoWrapper || !pagoDiarioWrapper) return;
        const tipo = tipoPagoEl.value;
        const showSueldo = tipo === 'SUELDO';
        const showDiario = tipo === 'DIARIO';
        sueldoWrapper.style.display = showSueldo ? '' : 'none';
        pagoDiarioWrapper.style.display = showDiario ? '' : 'none';
        if (!showSueldo) {
            sueldoWrapper.querySelector('input')?.classList.remove('is-invalid');
            sueldoWrapper.querySelector('input')?.value = '';
        }
        if (!showDiario) {
            pagoDiarioWrapper.querySelector('input')?.classList.remove('is-invalid');
            pagoDiarioWrapper.querySelector('input')?.value = '';
        }
    }

    function toggleComercialFields(clienteEl, proveedorEl, containerEl) {
        if (!containerEl) return;
        const show = Boolean(clienteEl?.checked || proveedorEl?.checked);
        containerEl.style.display = show ? '' : 'none';
        if (!show) {
            resetFields(containerEl);
        }
    }

    function setUbigeoOptions(departamentoEl, provinciaEl, distritoEl, selected = {}) {
        if (!departamentoEl || !provinciaEl || !distritoEl) return;
        const departamentos = Object.keys(UBIGEO_DATA);

        const fillSelect = (select, options, placeholder) => {
            select.innerHTML = '';
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = placeholder;
            select.appendChild(emptyOption);
            options.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option;
                opt.textContent = option.replace('_', ' ');
                select.appendChild(opt);
            });
        };

        fillSelect(departamentoEl, departamentos, 'Seleccionar...');
        departamentoEl.value = selected.departamento || '';

        const provincias = departamentoEl.value ? Object.keys(UBIGEO_DATA[departamentoEl.value] || {}) : [];
        fillSelect(provinciaEl, provincias, 'Seleccionar...');
        provinciaEl.value = selected.provincia || '';

        const distritos = departamentoEl.value && provinciaEl.value
            ? (UBIGEO_DATA[departamentoEl.value]?.[provinciaEl.value] || [])
            : [];
        fillSelect(distritoEl, distritos, 'Seleccionar...');
        distritoEl.value = selected.distrito || '';

        if (!departamentoEl.dataset.bound) {
            departamentoEl.addEventListener('change', () => {
                const newProvincias = departamentoEl.value ? Object.keys(UBIGEO_DATA[departamentoEl.value] || {}) : [];
                fillSelect(provinciaEl, newProvincias, 'Seleccionar...');
                fillSelect(distritoEl, [], 'Seleccionar...');
            });
            departamentoEl.dataset.bound = '1';
        }

        if (!provinciaEl.dataset.bound) {
            provinciaEl.addEventListener('change', () => {
                const newDistritos = departamentoEl.value && provinciaEl.value
                    ? (UBIGEO_DATA[departamentoEl.value]?.[provinciaEl.value] || [])
                    : [];
                fillSelect(distritoEl, newDistritos, 'Seleccionar...');
            });
            provinciaEl.dataset.bound = '1';
        }
    }

    function validateForm(form, rolesFeedbackId, showErrors = true) {
        let valid = true;
        const tipoPersona = form.querySelector('[name="tipo_persona"]');
        const tipoDoc = form.querySelector('[name="tipo_documento"]');
        const numeroDoc = form.querySelector('[name="numero_documento"]');
        const nombre = form.querySelector('[name="nombre_completo"]');
        const email = form.querySelector('[name="email"]');
        const cargo = form.querySelector('[name="cargo"]');
        const area = form.querySelector('[name="area"]');
        const fechaIngreso = form.querySelector('[name="fecha_ingreso"]');
        const estadoLaboral = form.querySelector('[name="estado_laboral"]');
        const tipoPago = form.querySelector('[name="tipo_pago"]');
        const sueldoBasico = form.querySelector('[name="sueldo_basico"]');
        const pagoDiario = form.querySelector('[name="pago_diario"]');

        [tipoPersona, tipoDoc, numeroDoc, nombre, email, cargo, area, fechaIngreso, estadoLaboral, tipoPago, sueldoBasico, pagoDiario].forEach(clearInvalid);
        form.querySelectorAll('input[name="telefonos[]"], select[name="telefono_tipos[]"]').forEach(clearInvalid);
        form.querySelectorAll('select[name="cuenta_tipo_entidad[]"], input[name="cuenta_entidad[]"], select[name="cuenta_tipo_cuenta[]"], input[name="cuenta_numero[]"]').forEach(clearInvalid);

        if (!tipoPersona?.value) {
            if (showErrors) setInvalid(tipoPersona, 'Seleccione el tipo de persona.');
            valid = false;
        }

        if (!tipoDoc?.value) {
            if (showErrors) setInvalid(tipoDoc, 'Seleccione el tipo de documento.');
            valid = false;
        }

        const numero = (numeroDoc?.value || '').trim();
        const documentoError = getDocumentoError(tipoDoc?.value || '', numero);
        if (documentoError) {
            if (numeroDoc) numeroDoc.setCustomValidity(documentoError);
            if (showErrors) setInvalid(numeroDoc, documentoError);
            valid = false;
        } else if (numeroDoc && !numeroDoc.validity?.customError) {
            numeroDoc.setCustomValidity('');
        }
        if (numeroDoc?.validity?.customError) {
            if (showErrors) setInvalid(numeroDoc, numeroDoc.validationMessage);
            valid = false;
        }

        if (!nombre?.value.trim()) {
            if (showErrors) setInvalid(nombre, 'Ingrese el nombre o razón social.');
            valid = false;
        }

        if (!isValidEmail(email?.value || '')) {
            if (showErrors) setInvalid(email, 'Ingrese un email válido.');
            valid = false;
        }

        const telefonos = Array.from(form.querySelectorAll('input[name="telefonos[]"]'));
        let telefonosInvalidos = false;
        telefonos.forEach(telefonoInput => {
            if (!isPeruPhone(telefonoInput.value || '')) {
                if (showErrors) setInvalid(telefonoInput, 'Ingrese un teléfono peruano válido.');
                valid = false;
                telefonosInvalidos = true;
            }
        });
        const telefonosFeedback = form.querySelector('#crearTelefonosFeedback, #editTelefonosFeedback');
        if (telefonosFeedback) {
            if (telefonosInvalidos && showErrors) {
                telefonosFeedback.classList.remove('d-none');
                telefonosFeedback.classList.add('d-block');
            } else {
                telefonosFeedback.classList.add('d-none');
                telefonosFeedback.classList.remove('d-block');
            }
        }

        const roles = form.querySelectorAll('input[name="es_cliente"], input[name="es_proveedor"], input[name="es_empleado"]');
        const hasRole = Array.from(roles).some(el => el.checked);
        const rolesFeedback = rolesFeedbackId ? document.getElementById(rolesFeedbackId) : null;
        if (!hasRole) {
            if (showErrors) {
                rolesFeedback?.classList.remove('d-none');
                rolesFeedback?.classList.add('d-block');
            }
            valid = false;
        } else {
            rolesFeedback?.classList.add('d-none');
            rolesFeedback?.classList.remove('d-block');
        }

        const esEmpleado = form.querySelector('[name="es_empleado"]')?.checked;

        if (esEmpleado) {
            if (!((cargo?.value || '').trim())) {
                if (showErrors) setInvalid(cargo, 'Ingrese el cargo.');
                valid = false;
            }
            if (!((area?.value || '').trim())) {
                if (showErrors) setInvalid(area, 'Ingrese el área.');
                valid = false;
            }
            if (!((fechaIngreso?.value || '').trim())) {
                if (showErrors) setInvalid(fechaIngreso, 'Seleccione la fecha de ingreso.');
                valid = false;
            }
            if (!((estadoLaboral?.value || '').trim())) {
                if (showErrors) setInvalid(estadoLaboral, 'Seleccione el estado laboral.');
                valid = false;
            }
            if (!((tipoPago?.value || '').trim())) {
                if (showErrors) setInvalid(tipoPago, 'Seleccione el tipo de pago.');
                valid = false;
            }
            if (tipoPago?.value === 'DIARIO') {
                if (!((pagoDiario?.value || '').toString().trim())) {
                    if (showErrors) setInvalid(pagoDiario, 'Ingrese el pago diario.');
                    valid = false;
                }
            }
            if (tipoPago?.value === 'SUELDO') {
                if (!((sueldoBasico?.value || '').toString().trim())) {
                    if (showErrors) setInvalid(sueldoBasico, 'Ingrese el sueldo básico.');
                    valid = false;
                }
            }
        }

        const cuentaTipos = Array.from(form.querySelectorAll('select[name="cuenta_tipo_entidad[]"]'));
        const cuentaEntidades = Array.from(form.querySelectorAll('input[name="cuenta_entidad[]"]'));
        const cuentaTiposCuenta = Array.from(form.querySelectorAll('select[name="cuenta_tipo_cuenta[]"]'));
        const cuentaNumeros = Array.from(form.querySelectorAll('input[name="cuenta_numero[]"]'));
        let cuentasInvalidas = false;

        cuentaTipos.forEach((tipoEl, index) => {
            const entidadEl = cuentaEntidades[index];
            const tipoCuentaEl = cuentaTiposCuenta[index];
            const numeroEl = cuentaNumeros[index];

            const tipoVal = tipoEl?.value || '';
            const entidadVal = entidadEl?.value || '';
            const tipoCuentaVal = tipoCuentaEl?.value || '';
            const numeroVal = numeroEl?.value || '';
            const hasData = [tipoVal, entidadVal, tipoCuentaVal, numeroVal].some(val => (val || '').toString().trim() !== '');
            if (!hasData) return;

            if (!tipoVal) {
                if (showErrors) setInvalid(tipoEl, 'Seleccione el tipo.');
                valid = false;
                cuentasInvalidas = true;
            }
            if (!entidadVal.trim()) {
                if (showErrors) setInvalid(entidadEl, 'Ingrese la entidad.');
                valid = false;
                cuentasInvalidas = true;
            }
            if (!numeroVal.trim()) {
                if (showErrors) setInvalid(numeroEl, 'Ingrese el número.');
                valid = false;
                cuentasInvalidas = true;
            }
            if (tipoVal === 'BANCO' && !tipoCuentaVal) {
                if (showErrors) setInvalid(tipoCuentaEl, 'Seleccione el tipo de cuenta.');
                valid = false;
                cuentasInvalidas = true;
            }
        });

        const cuentasFeedback = form.querySelector('#crearCuentasFeedback, #editCuentasFeedback');
        if (cuentasFeedback) {
            if (cuentasInvalidas && showErrors) {
                cuentasFeedback.classList.remove('d-none');
                cuentasFeedback.classList.add('d-block');
            } else {
                cuentasFeedback.classList.add('d-none');
                cuentasFeedback.classList.remove('d-block');
            }
        }

        return valid;
    }

    function refreshValidationOnChange(form, rolesFeedbackId) {
        if (!form) return;
        if (form.dataset.submitted === '1') {
            validateForm(form, rolesFeedbackId, true);
        }
    }

    function initTelefonosSection(listEl, addButton, form, rolesFeedbackId, telefonos = []) {
        if (!listEl || !addButton) return;
        listEl.innerHTML = '';
        const addRow = (data = {}) => {
            const row = buildTelefonoRow(data, () => refreshValidationOnChange(form, rolesFeedbackId));
            listEl.appendChild(row);
        };
        (telefonos.length ? telefonos : [{}]).forEach(item => addRow(item));
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
        (cuentas.length ? cuentas : [{}]).forEach(item => addRow(item));
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

            if (!validateForm(form, rolesFeedbackId, true)) {
                return;
            }

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
                    .then(response => response.json().then(data => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok || !data.ok) {
                            throw new Error(data.mensaje || 'No se pudo guardar.');
                        }
                        Swal.fire('Guardado', data.mensaje || 'Registro guardado.', 'success').then(() => {
                            const modalEl = form.closest('.modal');
                            if (modalEl) {
                                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                            }
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
            updateNombreLabel(document.getElementById('crearTipoPersona'), document.getElementById('crearNombreLabel'));
            toggleComercialFields(
                document.getElementById('crearEsCliente'),
                document.getElementById('crearEsProveedor'),
                document.getElementById('crearComercialFields')
            );
            toggleLaboralFields(document.getElementById('crearEsEmpleado'), document.getElementById('crearLaboralFields'));
            togglePagoFields(
                document.getElementById('crearTipoPago'),
                document.getElementById('crearSueldoBasicoWrapper'),
                document.getElementById('crearPagoDiarioWrapper')
            );
            initTelefonosSection(
                document.getElementById('crearTelefonosList'),
                document.getElementById('crearAgregarTelefono'),
                form,
                'crearRolesFeedback',
                []
            );
            initCuentasSection(
                document.getElementById('crearCuentasBancariasList'),
                document.getElementById('crearAgregarCuenta'),
                form,
                'crearRolesFeedback',
                []
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
            form?.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            if (form) form.dataset.submitted = '0';

            const fields = {
                'editTerceroId': 'data-id',
                'editTipoPersona': 'data-tipo-persona',
                'editTipoDoc': 'data-tipo-doc',
                'editNumeroDoc': 'data-numero-doc',
                'editNombre': 'data-nombre',
                'editDireccion': 'data-direccion',
                'editEmail': 'data-email',
                'editObservaciones': 'data-observaciones',
                'editCondicionPago': 'data-condicion-pago',
                'editDiasCredito': 'data-dias-credito',
                'editLimiteCredito': 'data-limite-credito',
                'editCargo': 'data-cargo',
                'editArea': 'data-area',
                'editFechaIngreso': 'data-fecha-ingreso',
                'editEstadoLaboral': 'data-estado-laboral',
                'editSueldoBasico': 'data-sueldo-basico',
                'editRegimenPensionario': 'data-regimen-pensionario',
                'editEstado': 'data-estado',
                'editTipoPago': 'data-tipo-pago',
                'editPagoDiario': 'data-pago-diario'
            };

            for (let id in fields) {
                const el = document.getElementById(id);
                if (el) el.value = button.getAttribute(fields[id]) || '';
            }

            const estadoLaboral = document.getElementById('editEstadoLaboral');
            if (estadoLaboral && estadoLaboral.value) {
                const normalized = estadoLaboral.value.toLowerCase();
                if (estadoLaboral.querySelector(`option[value="${normalized}"]`)) {
                    estadoLaboral.value = normalized;
                }
            }

            const essalud = document.getElementById('editEssalud');
            if (essalud) essalud.checked = button.getAttribute('data-essalud') === '1';

            const checks = {
                'editEsCliente': 'data-es-cliente',
                'editEsProveedor': 'data-es-proveedor',
                'editEsEmpleado': 'data-es-empleado'
            };

            for (let id in checks) {
                const el = document.getElementById(id);
                if (el) el.checked = button.getAttribute(checks[id]) === '1';
            }

            setUbigeoOptions(
                document.getElementById('editDepartamento'),
                document.getElementById('editProvincia'),
                document.getElementById('editDistrito'),
                {
                    departamento: button.getAttribute('data-departamento') || '',
                    provincia: button.getAttribute('data-provincia') || '',
                    distrito: button.getAttribute('data-distrito') || ''
                }
            );

            document.getElementById('editRolesFeedback')?.classList.add('d-none');
            updateNombreLabel(document.getElementById('editTipoPersona'), document.getElementById('editNombreLabel'));
            toggleComercialFields(
                document.getElementById('editEsCliente'),
                document.getElementById('editEsProveedor'),
                document.getElementById('editComercialFields')
            );
            toggleLaboralFields(document.getElementById('editEsEmpleado'), document.getElementById('editLaboralFields'));
            togglePagoFields(
                document.getElementById('editTipoPago'),
                document.getElementById('editSueldoBasicoWrapper'),
                document.getElementById('editPagoDiarioWrapper')
            );

            let telefonos = [];
            let cuentas = [];
            try {
                const rawTelefonos = button.getAttribute('data-telefonos');
                telefonos = rawTelefonos ? JSON.parse(rawTelefonos) : [];
            } catch (e) {
                telefonos = [];
            }
            try {
                const rawCuentas = button.getAttribute('data-cuentas-bancarias');
                cuentas = rawCuentas ? JSON.parse(rawCuentas) : [];
            } catch (e) {
                cuentas = [];
            }
            initTelefonosSection(
                document.getElementById('editTelefonosList'),
                document.getElementById('editAgregarTelefono'),
                form,
                'editRolesFeedback',
                telefonos
            );
            initCuentasSection(
                document.getElementById('editCuentasBancariasList'),
                document.getElementById('editAgregarCuenta'),
                form,
                'editRolesFeedback',
                cuentas
            );
        });
    }

    function initUbigeo() {
        setUbigeoOptions(
            document.getElementById('crearDepartamento'),
            document.getElementById('crearProvincia'),
            document.getElementById('crearDistrito'),
            {}
        );
    }

    function initDynamicFields() {
        const createTipoPersona = document.getElementById('crearTipoPersona');
        const editTipoPersona = document.getElementById('editTipoPersona');
        const crearEsEmpleado = document.getElementById('crearEsEmpleado');
        const editEsEmpleado = document.getElementById('editEsEmpleado');
        const crearEsCliente = document.getElementById('crearEsCliente');
        const crearEsProveedor = document.getElementById('crearEsProveedor');
        const editEsCliente = document.getElementById('editEsCliente');
        const editEsProveedor = document.getElementById('editEsProveedor');
        const crearTipoPago = document.getElementById('crearTipoPago');
        const editTipoPago = document.getElementById('editTipoPago');

        createTipoPersona?.addEventListener('change', () => {
            updateNombreLabel(createTipoPersona, document.getElementById('crearNombreLabel'));
            refreshValidationOnChange(document.getElementById('formCrearTercero'), 'crearRolesFeedback');
        });
        editTipoPersona?.addEventListener('change', () => {
            updateNombreLabel(editTipoPersona, document.getElementById('editNombreLabel'));
            refreshValidationOnChange(document.getElementById('formEditarTercero'), 'editRolesFeedback');
        });

        crearEsEmpleado?.addEventListener('change', () => {
            toggleLaboralFields(crearEsEmpleado, document.getElementById('crearLaboralFields'));
            refreshValidationOnChange(document.getElementById('formCrearTercero'), 'crearRolesFeedback');
        });
        editEsEmpleado?.addEventListener('change', () => {
            toggleLaboralFields(editEsEmpleado, document.getElementById('editLaboralFields'));
            refreshValidationOnChange(document.getElementById('formEditarTercero'), 'editRolesFeedback');
        });

        const handleComercialToggle = (clienteEl, proveedorEl, formId, submitId, rolesFeedbackId, containerId) => {
            toggleComercialFields(clienteEl, proveedorEl, document.getElementById(containerId));
            refreshValidationOnChange(document.getElementById(formId), rolesFeedbackId);
        };

        crearEsCliente?.addEventListener('change', () => handleComercialToggle(crearEsCliente, crearEsProveedor, 'formCrearTercero', 'crearGuardarBtn', 'crearRolesFeedback', 'crearComercialFields'));
        crearEsProveedor?.addEventListener('change', () => handleComercialToggle(crearEsCliente, crearEsProveedor, 'formCrearTercero', 'crearGuardarBtn', 'crearRolesFeedback', 'crearComercialFields'));
        editEsCliente?.addEventListener('change', () => handleComercialToggle(editEsCliente, editEsProveedor, 'formEditarTercero', 'editGuardarBtn', 'editRolesFeedback', 'editComercialFields'));
        editEsProveedor?.addEventListener('change', () => handleComercialToggle(editEsCliente, editEsProveedor, 'formEditarTercero', 'editGuardarBtn', 'editRolesFeedback', 'editComercialFields'));

        crearTipoPago?.addEventListener('change', () => {
            togglePagoFields(
                crearTipoPago,
                document.getElementById('crearSueldoBasicoWrapper'),
                document.getElementById('crearPagoDiarioWrapper')
            );
            refreshValidationOnChange(document.getElementById('formCrearTercero'), 'crearRolesFeedback');
        });
        editTipoPago?.addEventListener('change', () => {
            togglePagoFields(
                editTipoPago,
                document.getElementById('editSueldoBasicoWrapper'),
                document.getElementById('editPagoDiarioWrapper')
            );
            refreshValidationOnChange(document.getElementById('formEditarTercero'), 'editRolesFeedback');
        });
    }

    function bindFormRealtimeValidation(form, submitButton, rolesFeedbackId) {
        if (!form) return;
        const fields = Array.from(form.querySelectorAll('input, select, textarea'));
        fields.forEach(field => {
            const eventType = field.tagName === 'SELECT' || field.type === 'checkbox' || field.type === 'radio' ? 'change' : 'input';
            field.addEventListener(eventType, () => refreshValidationOnChange(form, rolesFeedbackId));
            if (eventType !== 'change') {
                field.addEventListener('change', () => refreshValidationOnChange(form, rolesFeedbackId));
            }
        });
    }

    function initTableManager() {
        const searchInput = document.getElementById('terceroSearch');
        const filtroRol = document.getElementById('terceroFiltroRol');
        const filtroEstado = document.getElementById('terceroFiltroEstado');
        const paginationControls = document.getElementById('tercerosPaginationControls');
        const paginationInfo = document.getElementById('tercerosPaginationInfo');

        const table = document.getElementById('tercerosTable');
        if (!table) return;

        const allRows = Array.from(table.querySelectorAll('tbody tr'));

        const updateTable = function () {
            const texto = (searchInput?.value || '').toLowerCase().trim();
            const rolSeleccionado = (filtroRol ? filtroRol.value : '');
            const estadoSeleccionado = (filtroEstado ? filtroEstado.value : '');

            const visibleRows = allRows.filter(row => {
                const dataSearch = row.getAttribute('data-search') || '';
                const dataRoles = row.getAttribute('data-roles') || '';
                const dataEstado = row.getAttribute('data-estado') || '';

                const coincideTexto = dataSearch.includes(texto);
                const coincideRol = rolSeleccionado === '' || dataRoles.includes(rolSeleccionado);
                const coincideEstado = estadoSeleccionado === '' || dataEstado === estadoSeleccionado;

                return coincideTexto && coincideRol && coincideEstado;
            });

            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE) || 1;

            if (currentPage > totalPages) currentPage = 1;
            if (currentPage < 1) currentPage = 1;

            allRows.forEach(row => row.style.display = 'none');

            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;
            visibleRows.slice(start, end).forEach(row => row.style.display = '');

            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = 'Sin resultados';
                } else {
                    const realEnd = Math.min(end, totalRows);
                    paginationInfo.textContent = `Mostrando ${start + 1}-${realEnd} de ${totalRows} terceros`;
                }
            }
            renderPagination(totalPages);
        };

        function renderPagination(totalPages) {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';
            if (totalPages <= 1) return;

            const createItem = (text, page, isActive = false, isDisabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="return false;">${text}</a>`;
                li.onclick = (e) => {
                    e.preventDefault();
                    if (!isActive && !isDisabled) {
                        currentPage = page;
                        updateTable();
                    }
                };
                return li;
            };

            paginationControls.appendChild(createItem('Anterior', currentPage - 1, false, currentPage === 1));
            for (let i = 1; i <= totalPages; i++) {
                paginationControls.appendChild(createItem(i, i, i === currentPage));
            }
            paginationControls.appendChild(createItem('Siguiente', currentPage + 1, false, currentPage === totalPages));
        }

        if (searchInput) searchInput.addEventListener('input', () => { currentPage = 1; updateTable(); });
        if (filtroRol) filtroRol.addEventListener('change', () => { currentPage = 1; updateTable(); });
        if (filtroEstado) filtroEstado.addEventListener('change', () => { currentPage = 1; updateTable(); });

        window.updateTercerosTable = updateTable;
        updateTable();
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
                    .then(response => response.json())
                    .then(data => {
                        if (!data.ok) throw new Error(data.mensaje);
                        if (fila) fila.setAttribute('data-estado', nuevoEstado);
                        if (badge) {
                            badge.textContent = nuevoEstado === 1 ? 'Activo' : nuevoEstado === 2 ? 'Bloqueado' : 'Inactivo';
                            badge.className = nuevoEstado === 1 ? 'badge-status status-active' : 'badge-status status-inactive';
                        }
                        if (window.updateTercerosTable) window.updateTercerosTable();
                    })
                    .catch(err => {
                        console.error(err);
                        this.checked = !this.checked;
                        Swal.fire('Error', 'No se pudo actualizar el estado.', 'error');
                    });
            });
        });
    }

    function initDocumentoValidation() {
        const campos = [
            { tipo: 'crearTipoDoc', numero: 'crearNumeroDoc', excludeId: null },
            { tipo: 'editTipoDoc', numero: 'editNumeroDoc', excludeId: () => document.getElementById('editTerceroId')?.value || null }
        ];

        const validar = (tipoEl, numeroEl, excludeIdVal) => {
            if (!tipoEl || !numeroEl) return;
            const tipo = tipoEl.value;
            const numero = numeroEl.value.trim();
            if (tipo === '' || numero === '') return;

            const errorMensaje = getDocumentoError(tipo, numero);
            if (errorMensaje) {
                numeroEl.setCustomValidity(errorMensaje);
                if (numeroEl.closest('form')?.dataset.submitted === '1') {
                    numeroEl.classList.add('is-invalid');
                }
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'validar_documento');
            formData.append('tipo_documento', tipo);
            formData.append('numero_documento', numero);
            if (excludeIdVal) formData.append('exclude_id', excludeIdVal);

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.existe) {
                        numeroEl.setCustomValidity('El documento ya se encuentra registrado.');
                        if (numeroEl.closest('form')?.dataset.submitted === '1') {
                            numeroEl.classList.add('is-invalid');
                        }
                    } else {
                        numeroEl.setCustomValidity('');
                        numeroEl.classList.remove('is-invalid');
                    }
                })
                .catch(console.error);
        };

        campos.forEach(c => {
            const tEl = document.getElementById(c.tipo);
            const nEl = document.getElementById(c.numero);
            if (tEl && nEl) {
                const handler = () => validar(tEl, nEl, typeof c.excludeId === 'function' ? c.excludeId() : c.excludeId);
                const localHandler = () => {
                    const errorMensaje = getDocumentoError(tEl.value, nEl.value);
                    if (errorMensaje) {
                        nEl.setCustomValidity(errorMensaje);
                        if (nEl.closest('form')?.dataset.submitted === '1') {
                            nEl.classList.add('is-invalid');
                        }
                    } else {
                        nEl.setCustomValidity('');
                        nEl.classList.remove('is-invalid');
                    }
                };
                tEl.addEventListener('change', () => {
                    localHandler();
                    handler();
                });
                nEl.addEventListener('input', localHandler);
                nEl.addEventListener('blur', handler);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        initCreateModal();
        initEditModal();
        initUbigeo();
        initDynamicFields();
        initTableManager();
        initStatusSwitch();
        initDocumentoValidation();

        const crearGuardarBtn = document.getElementById('crearGuardarBtn');
        if (crearGuardarBtn) crearGuardarBtn.setAttribute('data-original-text', crearGuardarBtn.innerHTML);
        bindFormRealtimeValidation(document.getElementById('formCrearTercero'), crearGuardarBtn, 'crearRolesFeedback');
        submitForm(document.getElementById('formCrearTercero'), crearGuardarBtn, 'crearRolesFeedback');

        const editGuardarBtn = document.getElementById('editGuardarBtn');
        if (editGuardarBtn) editGuardarBtn.setAttribute('data-original-text', editGuardarBtn.innerHTML);
        bindFormRealtimeValidation(document.getElementById('formEditarTercero'), editGuardarBtn, 'editRolesFeedback');
        submitForm(document.getElementById('formEditarTercero'), editGuardarBtn, 'editRolesFeedback');
    });
})();
