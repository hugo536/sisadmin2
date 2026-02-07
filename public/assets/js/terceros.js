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

    function toggleLaboralFields(checkboxEl, containerEl) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.style.display = show ? '' : 'none';
        if (!show) {
            resetFields(containerEl);
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
        const telefono = form.querySelector('[name="telefono"]');
        const email = form.querySelector('[name="email"]');
        const condicionPago = form.querySelector('[name="condicion_pago"]');
        const diasCredito = form.querySelector('[name="dias_credito"]');
        const limiteCredito = form.querySelector('[name="limite_credito"]');
        const cargo = form.querySelector('[name="cargo"]');
        const area = form.querySelector('[name="area"]');
        const fechaIngreso = form.querySelector('[name="fecha_ingreso"]');
        const estadoLaboral = form.querySelector('[name="estado_laboral"]');

        [tipoPersona, tipoDoc, numeroDoc, nombre, telefono, email, condicionPago, diasCredito, limiteCredito, cargo, area, fechaIngreso, estadoLaboral].forEach(clearInvalid);

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

        if (!isPeruPhone(telefono?.value || '')) {
            if (showErrors) setInvalid(telefono, 'Ingrese un teléfono peruano válido.');
            valid = false;
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

        const esCliente = form.querySelector('[name="es_cliente"]')?.checked;
        const esProveedor = form.querySelector('[name="es_proveedor"]')?.checked;
        const esEmpleado = form.querySelector('[name="es_empleado"]')?.checked;

        if (esCliente || esProveedor) {
            if (!((condicionPago?.value || '').trim())) {
                if (showErrors) setInvalid(condicionPago, 'Ingrese la condición de pago.');
                valid = false;
            }
            if (!((diasCredito?.value ?? '').toString().trim())) {
                if (showErrors) setInvalid(diasCredito, 'Ingrese los días de crédito.');
                valid = false;
            }
            if (!((limiteCredito?.value ?? '').toString().trim())) {
                if (showErrors) setInvalid(limiteCredito, 'Ingrese el límite de crédito.');
                valid = false;
            }
        }

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
        }

        return valid;
    }

    function updateSubmitState(form, submitButton, rolesFeedbackId, showErrors = true) {
        if (!form) return;
        const valid = validateForm(form, rolesFeedbackId, showErrors);
        if (submitButton) submitButton.disabled = !valid;
    }

    function submitForm(form, submitButton, rolesFeedbackId) {
        if (!form) return;
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            if (!validateForm(form, rolesFeedbackId, true)) {
                updateSubmitState(form, submitButton, rolesFeedbackId, true);
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
            updateSubmitState(form, document.getElementById('crearGuardarBtn'), 'crearRolesFeedback', false);
        });
    }

    function initEditModal() {
        const modalEdit = document.getElementById('modalEditarTercero');
        if (!modalEdit) return;

        modalEdit.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            document.getElementById('formEditarTercero')?.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            const fields = {
                'editTerceroId': 'data-id',
                'editTipoPersona': 'data-tipo-persona',
                'editTipoDoc': 'data-tipo-doc',
                'editNumeroDoc': 'data-numero-doc',
                'editNombre': 'data-nombre',
                'editDireccion': 'data-direccion',
                'editTelefono': 'data-telefono',
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
                'editEstado': 'data-estado'
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
            updateSubmitState(document.getElementById('formEditarTercero'), document.getElementById('editGuardarBtn'), 'editRolesFeedback', false);
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

        createTipoPersona?.addEventListener('change', () => {
            updateNombreLabel(createTipoPersona, document.getElementById('crearNombreLabel'));
            updateSubmitState(document.getElementById('formCrearTercero'), document.getElementById('crearGuardarBtn'), 'crearRolesFeedback');
        });
        editTipoPersona?.addEventListener('change', () => {
            updateNombreLabel(editTipoPersona, document.getElementById('editNombreLabel'));
            updateSubmitState(document.getElementById('formEditarTercero'), document.getElementById('editGuardarBtn'), 'editRolesFeedback');
        });

        crearEsEmpleado?.addEventListener('change', () => {
            toggleLaboralFields(crearEsEmpleado, document.getElementById('crearLaboralFields'));
            updateSubmitState(document.getElementById('formCrearTercero'), document.getElementById('crearGuardarBtn'), 'crearRolesFeedback');
        });
        editEsEmpleado?.addEventListener('change', () => {
            toggleLaboralFields(editEsEmpleado, document.getElementById('editLaboralFields'));
            updateSubmitState(document.getElementById('formEditarTercero'), document.getElementById('editGuardarBtn'), 'editRolesFeedback');
        });

        const handleComercialToggle = (clienteEl, proveedorEl, formId, submitId, rolesFeedbackId, containerId) => {
            toggleComercialFields(clienteEl, proveedorEl, document.getElementById(containerId));
            updateSubmitState(document.getElementById(formId), document.getElementById(submitId), rolesFeedbackId);
        };

        crearEsCliente?.addEventListener('change', () => handleComercialToggle(crearEsCliente, crearEsProveedor, 'formCrearTercero', 'crearGuardarBtn', 'crearRolesFeedback', 'crearComercialFields'));
        crearEsProveedor?.addEventListener('change', () => handleComercialToggle(crearEsCliente, crearEsProveedor, 'formCrearTercero', 'crearGuardarBtn', 'crearRolesFeedback', 'crearComercialFields'));
        editEsCliente?.addEventListener('change', () => handleComercialToggle(editEsCliente, editEsProveedor, 'formEditarTercero', 'editGuardarBtn', 'editRolesFeedback', 'editComercialFields'));
        editEsProveedor?.addEventListener('change', () => handleComercialToggle(editEsCliente, editEsProveedor, 'formEditarTercero', 'editGuardarBtn', 'editRolesFeedback', 'editComercialFields'));
    }

    function bindFormRealtimeValidation(form, submitButton, rolesFeedbackId) {
        if (!form) return;
        const fields = Array.from(form.querySelectorAll('input, select, textarea'));
        fields.forEach(field => {
            const eventType = field.tagName === 'SELECT' || field.type === 'checkbox' || field.type === 'radio' ? 'change' : 'input';
            field.addEventListener(eventType, () => updateSubmitState(form, submitButton, rolesFeedbackId, true));
            if (eventType !== 'change') {
                field.addEventListener('change', () => updateSubmitState(form, submitButton, rolesFeedbackId, true));
            }
        });
        updateSubmitState(form, submitButton, rolesFeedbackId, false);
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
                numeroEl.classList.add('is-invalid');
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
                        numeroEl.classList.add('is-invalid');
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
                        nEl.classList.add('is-invalid');
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
