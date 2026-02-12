(function () {
    'use strict';

    const TIPOS_ENTIDAD_CUENTA = ['Banco', 'Caja', 'Billetera Digital', 'Otros'];

    const ENTIDADES_FINANCIERAS = {
        Banco: ['BCP', 'Interbank', 'BBVA Continental', 'Scotiabank', 'Banco de la Nación', 'BanBif', 'Pichincha'],
        Caja: ['Caja Arequipa', 'Caja Huancayo', 'Caja Piura', 'Caja Trujillo', 'Caja Sullana', 'Caja Tacna', 'Caja Ica'],
        'Billetera Digital': ['Yape', 'Plin', 'Tunki', 'Bim', 'Lukita', 'Mercado Pago', 'Otros'],
        Otros: []
    };

    const TIPOS_CUENTA_BANCO = ['Ahorros', 'Corriente', 'CTS', 'Detracción', 'Sueldo', 'Otros'];
    const TIPOS_CUENTA_BILLETERA = ['N/A', 'Personal', 'Empresarial'];

    // =========================================================================
    // UTILIDADES GENERALES
    // =========================================================================


    async function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        const bodyText = await response.text();

        if (!bodyText) {
            return { ok: response.ok, data: {} };
        }

        if (contentType.includes('application/json')) {
            return { ok: response.ok, data: JSON.parse(bodyText) };
        }

        try {
            return { ok: response.ok, data: JSON.parse(bodyText) };
        } catch (error) {
            const plainText = bodyText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            const details = plainText ? `Respuesta del servidor: ${plainText.slice(0, 180)}` : 'El servidor devolvió una respuesta no válida.';
            throw new Error(`No se pudo procesar la respuesta del servidor. ${details}`);
        }
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

    function normalizeTipoEntidad(value) {
        const normalized = (value || '').toString().trim().toLowerCase();
        if (normalized === 'banco') return 'Banco';
        if (normalized === 'caja') return 'Caja';
        if (normalized === 'billetera' || normalized === 'billetera digital') return 'Billetera Digital';
        if (normalized === 'cooperativa' || normalized === 'otro' || normalized === 'otros') return 'Otros';
        return 'Banco';
    }

    function isBilleteraTipo(tipoEntidad) {
        return normalizeTipoEntidad(tipoEntidad) === 'Billetera Digital';
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


    function initSweetConfirmForms() {
        document.querySelectorAll('.js-swal-confirm').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === '1') {
                    form.dataset.confirmed = '0';
                    return;
                }

                event.preventDefault();

                if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') {
                    console.warn('SweetAlert2 no está disponible para confirmar la acción.');
                    return;
                }

                const result = await Swal.fire({
                    icon: 'warning',
                    title: form.dataset.confirmTitle || '¿Confirmar acción?',
                    text: form.dataset.confirmText || 'Esta acción no se puede deshacer.',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                });

                if (!result.isConfirmed) return;
                form.dataset.confirmed = '1';
                form.requestSubmit();
            });
        });
    }

    // =========================================================================
    // LÓGICA DE ESTADO LABORAL (Reglas de Negocio)
    // =========================================================================

    function updateLaboralState(form) {
        if (!form) return;
        
        const estadoEl = form.querySelector('[name="estado_laboral"]');
        if (!estadoEl) return; // Si no es empleado, no existe este campo

        const estado = estadoEl.value; // 'activo', 'cesado', 'suspendido'
        const fechaCese = form.querySelector('[name="fecha_cese"]');
        
        // Campos que se bloquean si no está activo
        // Nota: Incluimos los selects y inputs de la sección económica/pensionaria
        const fieldsToToggle = form.querySelectorAll(
            '[name="tipo_pago"], [name="moneda"], [name="sueldo_basico"], ' +
            '[name="asignacion_familiar"], [name="regimen_pensionario"], ' +
            '[name="tipo_comision_afp"], [name="cuspp"], [name="essalud"], [name="pago_diario"]'
        );

        if (estado === 'activo') {
            // Caso Activo: Limpiar y bloquear fecha cese
            if (fechaCese) {
                fechaCese.value = '';
                fechaCese.disabled = true;
                fechaCese.required = false;
                clearInvalid(fechaCese);
            }
            
            // Habilitar campos económicos
            fieldsToToggle.forEach(el => el.disabled = false);
            
            // Re-ejecutar lógicas internas (toggle de pagos y afp) para estado correcto
            const tipoPago = form.querySelector('[name="tipo_pago"]');
            if (tipoPago) tipoPago.dispatchEvent(new Event('change'));
            
            const regimen = form.querySelector('[name="regimen_pensionario"]');
            if (regimen) regimen.dispatchEvent(new Event('change'));

        } else {
            // Caso Cesado o Suspendido
            if (fechaCese) {
                fechaCese.disabled = false;
                if (estado === 'cesado') {
                    fechaCese.required = true;
                } else {
                    fechaCese.required = false; // Suspendido puede ser indefinido
                }
            }

            // Deshabilitar campos económicos (Histórico congelado)
            fieldsToToggle.forEach(el => el.disabled = true);
        }
    }

    function toggleCumpleanosFields(recordarEl, form) {
        if (!form || !recordarEl) return;
        const fechaNacimiento = form.querySelector('[name="fecha_nacimiento"]');
        if (!fechaNacimiento) return;

        const wrapper = fechaNacimiento.closest('[id$="FechaNacimientoWrapper"]') || fechaNacimiento.closest('.col-md-4');
        const mostrar = recordarEl.checked;

        if (wrapper) wrapper.classList.toggle('d-none', !mostrar);
        fechaNacimiento.disabled = !mostrar;
        fechaNacimiento.required = mostrar;

        if (!mostrar) {
            fechaNacimiento.value = '';
            clearInvalid(fechaNacimiento);
        }
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

    function buildCuentaRow({ 
        tipo_entidad = '', 
        entidad = '', 
        tipo_cuenta = '', 
        numero_cuenta = '', 
        cci = '', 
        titular = '', 
        alias = '',
        moneda = 'PEN', 
        principal = 0, 
        billetera_digital = 0, 
        observaciones = '' 
    } = {}, onRemove) {
        titular = titular || alias;
        
        const wrapper = document.createElement('div');
        wrapper.className = 'card mb-2 border shadow-sm';

        // Determinar tipo inicial
        const tipoEntidadVal = normalizeTipoEntidad(tipo_entidad || (Number(billetera_digital) === 1 ? 'Billetera Digital' : 'Banco'));

        const cardBody = document.createElement('div');
        cardBody.className = 'card-body p-2';

        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center';

        // --- COLUMNA 1: TIPO DE ENTIDAD (Banco, Billetera...) ---
        const colType = document.createElement('div');
        colType.className = 'col-md-2'; // Más angosto
        const tipoEntSelect = document.createElement('select');
        tipoEntSelect.name = 'cuenta_tipo[]';
        tipoEntSelect.className = 'form-select form-select-sm fw-bold bg-light';
        TIPOS_ENTIDAD_CUENTA.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt;
            if (opt === tipoEntidadVal) option.selected = true;
            tipoEntSelect.appendChild(option);
        });
        colType.appendChild(tipoEntSelect);

        // --- COLUMNA 2: ENTIDAD (BCP, Yape...) ---
        const colEntidad = document.createElement('div');
        colEntidad.className = 'col-md-3';
        const entidadSelect = document.createElement('select');
        entidadSelect.name = 'cuenta_entidad[]';
        entidadSelect.className = 'form-select form-select-sm';
        colEntidad.appendChild(entidadSelect);

        // --- COLUMNA 3: TIPO DE CUENTA (Ahorros, Corriente...) ---
        // Este contenedor se ocultará si es Billetera
        const colTipoCta = document.createElement('div');
        colTipoCta.className = 'col-md-2';
        const tipoCuentaSelect = document.createElement('select');
        tipoCuentaSelect.name = 'cuenta_tipo_cta[]';
        tipoCuentaSelect.className = 'form-select form-select-sm';
        colTipoCta.appendChild(tipoCuentaSelect);

        // Input oculto para identificar billetera en backend
        const billeteraHidden = document.createElement('input');
        billeteraHidden.type = 'hidden';
        billeteraHidden.name = 'cuenta_billetera[]';
        billeteraHidden.value = '0';
        colTipoCta.appendChild(billeteraHidden);

        // --- COLUMNA 4: NÚMERO / CCI / TELÉFONO (El campo principal) ---
        const colInput = document.createElement('div');
        colInput.className = 'col-md-4'; // Ocupa el espacio restante
        const inputGroup = document.createElement('div');
        inputGroup.className = 'input-group input-group-sm';

        // Icono dinámico
        const iconSpan = document.createElement('span');
        iconSpan.className = 'input-group-text bg-white text-muted border-end-0';
        iconSpan.innerHTML = '<i class="bi bi-hash"></i>';
        
        // Input principal (CCI o Teléfono)
        const mainInput = document.createElement('input');
        mainInput.type = 'text';
        mainInput.name = 'cuenta_cci[]'; // Usamos CCI como campo principal de valor
        mainInput.className = 'form-control border-start-0';
        mainInput.placeholder = 'Número de cuenta';
        mainInput.value = cci || numero_cuenta; // Preferimos CCI/Teléfono

        // Input secundario oculto (para compatibilidad si el backend pide ambos)
        const secondaryInput = document.createElement('input');
        secondaryInput.type = 'hidden';
        secondaryInput.name = 'cuenta_numero[]';
        secondaryInput.value = numero_cuenta;

        inputGroup.appendChild(iconSpan);
        inputGroup.appendChild(mainInput);
        inputGroup.appendChild(secondaryInput);
        colInput.appendChild(inputGroup);

        // --- COLUMNA 5: TITULAR (OBLIGATORIO SI EXISTE CUENTA) ---
        const colTitular = document.createElement('div');
        colTitular.className = 'col-md-4';
        const titularLabel = document.createElement('label');
        titularLabel.className = 'form-label small fw-semibold mb-1';
        titularLabel.textContent = 'Titular de la cuenta *';
        const titularInput = document.createElement('input');
        titularInput.type = 'text';
        titularInput.name = 'cuenta_titular[]';
        titularInput.className = 'form-control form-control-sm';
        titularInput.placeholder = 'Titular de la cuenta *';
        titularInput.value = titular;
        titularInput.required = true;
        colTitular.appendChild(titularLabel);
        colTitular.appendChild(titularInput);

        // --- COLUMNA 6: OBSERVACIONES ---
        const colObservaciones = document.createElement('div');
        colObservaciones.className = 'col-md-8';
        const observacionesLabel = document.createElement('label');
        observacionesLabel.className = 'form-label small fw-semibold mb-1';
        observacionesLabel.textContent = 'Observaciones';
        const observacionesInput = document.createElement('textarea');
        observacionesInput.name = 'cuenta_observaciones[]';
        observacionesInput.className = 'form-control form-control-sm';
        observacionesInput.placeholder = 'Observaciones (ej: cobra comisión, solo dólares)';
        observacionesInput.rows = 2;
        observacionesInput.value = observaciones;
        colObservaciones.appendChild(observacionesLabel);
        observacionesInput.rows = 1;
        observacionesInput.value = observaciones;
        colObservaciones.appendChild(observacionesInput);

        // --- COLUMNA 7: MONEDA Y ACCIONES ---
        const colActions = document.createElement('div');
        colActions.className = 'col-md-1 d-flex gap-1';
        
        const monedaSelect = document.createElement('select');
        monedaSelect.name = 'cuenta_moneda[]';
        monedaSelect.className = 'form-select form-select-sm px-1 text-center';
        ['PEN', 'USD'].forEach(m => {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = m === 'PEN' ? 'S/' : '$';
            if(m === moneda) opt.selected = true;
            monedaSelect.appendChild(opt);
        });

        const removeBtn = createRemoveButton(() => {
            wrapper.remove();
            if (typeof onRemove === 'function') onRemove();
        });

        colActions.appendChild(monedaSelect);
        colActions.appendChild(removeBtn);

        // --- ARMADO DE FILA ---
        row.appendChild(colType);
        row.appendChild(colEntidad);
        row.appendChild(colTipoCta);
        row.appendChild(colInput);
        row.appendChild(colActions);

        const detailsRow = document.createElement('div');
        detailsRow.className = 'row g-2 align-items-start mt-1';
        detailsRow.appendChild(colTitular);
        detailsRow.appendChild(colObservaciones);

        cardBody.appendChild(row);
        cardBody.appendChild(detailsRow);
        wrapper.appendChild(cardBody);

        // ==========================================
        // LÓGICA DINÁMICA (Aquí ocurre la magia)
        // ==========================================

        const renderEntidadOptions = (tipoEntidad, currentValue) => {
            const opciones = ENTIDADES_FINANCIERAS[tipoEntidad] || [];
            entidadSelect.innerHTML = '';
            
            // Opcion vacia o por defecto
            if (!currentValue && opciones.length > 0) {
                 // Si es billetera y no hay valor, pre-seleccionar Yape
                 if(tipoEntidad === 'Billetera Digital') currentValue = 'Yape';
                 // Si es Banco y no hay valor, pre-seleccionar BCP
                 if(tipoEntidad === 'Banco') currentValue = 'BCP';
            }

            opciones.forEach(nombre => {
                const option = document.createElement('option');
                option.value = nombre;
                option.textContent = nombre;
                entidadSelect.appendChild(option);
            });

            // Mantener valor custom si existía
            if (currentValue && !opciones.includes(currentValue)) {
                const custom = document.createElement('option');
                custom.value = currentValue;
                custom.textContent = currentValue;
                entidadSelect.appendChild(custom);
            }
            entidadSelect.value = currentValue || opciones[0] || '';
        };

        const renderTipoCuenta = (tipoEntidad, currentValue) => {
            tipoCuentaSelect.innerHTML = '';
            const isBilletera = isBilleteraTipo(tipoEntidad);
            const opciones = isBilletera ? TIPOS_CUENTA_BILLETERA : TIPOS_CUENTA_BANCO;
            
            opciones.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                tipoCuentaSelect.appendChild(option);
            });

            // Si es billetera, forzamos "N/A" o "Personal" y ocultamos visualmente
            if (isBilletera) {
                colTipoCta.classList.add('d-none'); // OCULTAR SELECTOR
                colInput.classList.remove('col-md-4'); 
                colInput.classList.add('col-md-6'); // EXPANDIR INPUT
            } else {
                colTipoCta.classList.remove('d-none'); // MOSTRAR SELECTOR
                colInput.classList.remove('col-md-6');
                colInput.classList.add('col-md-4'); // CONTRAER INPUT
            }

            let valueToUse = currentValue;
            if (!opciones.includes(valueToUse)) {
                valueToUse = isBilletera ? 'N/A' : 'Ahorros';
            }
            tipoCuentaSelect.value = valueToUse;
        };

        const applyCuentaMode = () => {
            const tipoEntidad = normalizeTipoEntidad(tipoEntSelect.value);
            const isBilletera = isBilleteraTipo(tipoEntidad);
            
            // 1. Renderizar Selects
            renderEntidadOptions(tipoEntidad, entidadSelect.value || entidad);
            renderTipoCuenta(tipoEntidad, tipoCuentaSelect.value || tipo_cuenta);

            // 2. Configurar Input Principal
            if (isBilletera) {
                iconSpan.innerHTML = '<i class="bi bi-phone"></i>';
                mainInput.placeholder = 'Número de celular (9 dígitos)';
                mainInput.maxLength = 9;
                billeteraHidden.value = '1';
                
                // Limpieza de caracteres no numéricos para billeteras
                if (mainInput.value) {
                    mainInput.value = sanitizeDigits(mainInput.value).slice(0, 9);
                }
            } else {
                iconSpan.innerHTML = '<i class="bi bi-bank"></i>';
                mainInput.placeholder = 'CCI (20 dígitos) o Cuenta';
                mainInput.maxLength = 20;
                billeteraHidden.value = '0';
            }
            
            // Sincronizar input oculto
            secondaryInput.value = mainInput.value;
        };

        // Listeners
        tipoEntSelect.addEventListener('change', () => {
            entidadSelect.value = ''; // Resetear entidad al cambiar tipo
            applyCuentaMode();
        });
        
        mainInput.addEventListener('input', function() {
            const tipoEntidad = normalizeTipoEntidad(tipoEntSelect.value);
            if (isBilleteraTipo(tipoEntidad)) {
                this.value = sanitizeDigits(this.value).slice(0, 9);
            }
            secondaryInput.value = this.value; // Sincronizar siempre
        });

        // Inicializar
        applyCuentaMode();
        
        // Si venía un valor de entidad, intentamos setearlo de nuevo tras el render
        if (entidad) entidadSelect.value = entidad;

        return wrapper;
    }

    // =========================================================================
    // LÓGICA DE UI Y TOGGLES
    // =========================================================================

    function toggleLaboralFields(checkboxEl, containerEl, form) {
        if (!checkboxEl || !containerEl) return;
        const show = checkboxEl.checked;
        containerEl.classList.toggle('d-none', !show);
        
        if (show) {
            // Si se activa, revisar estado para aplicar reglas de bloqueo
            updateLaboralState(form);
        } else {
            resetFields(containerEl);
        }
    }

    function togglePagoFields(tipoPagoEl) {
        if (!tipoPagoEl) return;
        
        const form = tipoPagoEl.closest('form');
        const sueldoInput = form.querySelector('[name="sueldo_basico"]');
        const pagoDiarioInput = form.querySelector('[name="pago_diario"]');
        
        const sueldoCol = sueldoInput?.closest('.col-md-6') || sueldoInput?.closest('.col-md-4'); 
        const diarioCol = pagoDiarioInput?.closest('.col-md-6') || pagoDiarioInput?.closest('.col-md-4');

        if (!sueldoCol || !diarioCol) return;

        const tipo = tipoPagoEl.value;
        const showSueldo = tipo === 'MENSUAL' || tipo === 'QUINCENAL'; 
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
            .then(parseJsonResponse)
            .then(({ data: res }) => {
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

        if (!departamentoEl._ubigeoChangeHandler) {
            departamentoEl._ubigeoChangeHandler = function() {
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
            };
            departamentoEl.addEventListener('change', departamentoEl._ubigeoChangeHandler);
        }

        if (!provinciaEl._ubigeoChangeHandler) {
            provinciaEl._ubigeoChangeHandler = function() {
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
            };
            provinciaEl.addEventListener('change', provinciaEl._ubigeoChangeHandler);
        }

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

    function isTipoPersonaJuridica(tipoPersona = '') {
        return String(tipoPersona).trim().toUpperCase() === 'JURIDICA';
    }

    function toggleRepresentanteLegal(form, prefix) {
        if (!form) return;

        const tipoPersona = form.querySelector('[name="tipo_persona"]');
        const representante = form.querySelector('[name="representante_legal"]');
        const section = form.querySelector('.representante-legal-section') || document.getElementById(`${prefix}RepresentanteLegalSection`);
        const nombreLabel = document.getElementById(`${prefix}NombreLabel`);
        const esJuridica = isTipoPersonaJuridica(tipoPersona?.value || '');

        if (nombreLabel) {
            nombreLabel.innerHTML = esJuridica
                ? 'Razón Social <span class="text-danger">*</span>'
                : 'Nombre completo <span class="text-danger">*</span>';
        }

        if (section) {
            section.classList.toggle('d-none', !esJuridica);
        }

        if (representante) {
            representante.required = esJuridica;
            if (!esJuridica) {
                representante.value = '';
                representante.setCustomValidity('');
                clearInvalid(representante);
            }
        }
    }

    function validateForm(form, rolesFeedbackId, showErrors = true) {
        let valid = true;
        const tipoPersona = form.querySelector('[name="tipo_persona"]');
        const tipoDoc = form.querySelector('[name="tipo_documento"]');
        const numeroDoc = form.querySelector('[name="numero_documento"]');
        const nombre = form.querySelector('[name="nombre_completo"]');
        const representanteLegal = form.querySelector('[name="representante_legal"]');
        const email = form.querySelector('[name="email"]');
        
        // Laborales
        const cargo = form.querySelector('[name="cargo"]');
        const area = form.querySelector('[name="area"]');
        const fechaIngreso = form.querySelector('[name="fecha_ingreso"]');
        const fechaCese = form.querySelector('[name="fecha_cese"]');
        const estadoLaboral = form.querySelector('[name="estado_laboral"]');
        const tipoPago = form.querySelector('[name="tipo_pago"]');
        const sueldoBasico = form.querySelector('[name="sueldo_basico"]');
        const pagoDiario = form.querySelector('[name="pago_diario"]');
        const recordarCumpleanos = form.querySelector('[name="recordar_cumpleanos"]');
        const fechaNacimiento = form.querySelector('[name="fecha_nacimiento"]');

        [tipoPersona, tipoDoc, numeroDoc, nombre, representanteLegal, email, cargo, area, fechaIngreso, fechaCese, tipoPago, sueldoBasico, pagoDiario, fechaNacimiento].forEach(clearInvalid);
        form.querySelectorAll('input[name="telefonos[]"], select[name="telefono_tipos[]"]').forEach(clearInvalid);
        form.querySelectorAll('select[name="cuenta_tipo[]"], select[name="cuenta_entidad[]"], select[name="cuenta_tipo_cta[]"], input[name="cuenta_numero[]"], input[name="cuenta_cci[]"]').forEach(clearInvalid);

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
        if (isTipoPersonaJuridica(tipoPersona?.value || '') && !representanteLegal?.value.trim()) {
            if (showErrors) setInvalid(representanteLegal, 'Representante legal es obligatorio para empresas.');
            valid = false;
        }
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
        
        const hasRole = Array.from(form.querySelectorAll('input[name="es_cliente"], input[name="es_proveedor"], input[name="es_empleado"], input[name="es_distribuidor"]')).some(el => el.checked);
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
            
            // Validación de Fechas Lógica
            const est = estadoLaboral?.value;
            if (est === 'cesado') {
                if(!fechaCese?.value) {
                    if(showErrors) setInvalid(fechaCese, 'Fecha de cese obligatoria.'); valid = false;
                } else if(fechaIngreso?.value && fechaCese.value < fechaIngreso.value) {
                    if(showErrors) setInvalid(fechaCese, 'El cese no puede ser antes del ingreso.'); valid = false;
                }
            }

            // Solo validar pagos si está activo (si está cesado, los campos están disabled y no importan)
            if (est === 'activo') {
                const tp = tipoPago?.value;
                if ((tp === 'MENSUAL' || tp === 'QUINCENAL') && !sueldoBasico?.value) {
                     if(showErrors) setInvalid(sueldoBasico, 'Requerido'); valid = false;
                }
                if (tp === 'DIARIO' && !pagoDiario?.value) {
                     if(showErrors) setInvalid(pagoDiario, 'Requerido'); valid = false;
                }
            }

            if (recordarCumpleanos?.checked) {
                const fechaNacimientoVal = (fechaNacimiento?.value || '').trim();
                const hoy = new Date();
                hoy.setHours(0, 0, 0, 0);

                if (!fechaNacimientoVal) {
                    if (showErrors) setInvalid(fechaNacimiento, 'Ingrese la fecha de nacimiento.');
                    valid = false;
                } else {
                    const fechaNac = new Date(`${fechaNacimientoVal}T00:00:00`);
                    if (Number.isNaN(fechaNac.getTime())) {
                        if (showErrors) setInvalid(fechaNacimiento, 'Ingrese una fecha válida.');
                        valid = false;
                    } else if (fechaNac > hoy) {
                        if (showErrors) setInvalid(fechaNacimiento, 'La fecha de nacimiento no puede ser mayor a hoy.');
                        valid = false;
                    }
                }
            }
        }

        const cTipos = form.querySelectorAll('select[name="cuenta_tipo[]"]');
        const cEntidades = form.querySelectorAll('select[name="cuenta_entidad[]"]');
        const cTipoCta = form.querySelectorAll('select[name="cuenta_tipo_cta[]"]');
        const cNumeros = form.querySelectorAll('input[name="cuenta_numero[]"]');
        const cCci = form.querySelectorAll('input[name="cuenta_cci[]"]');
        const cTitulares = form.querySelectorAll('input[name="cuenta_titular[]"]');

        cTipos.forEach((tipoEl, i) => {
            const entidadEl = cEntidades[i];
            const tipoCtaEl = cTipoCta[i];
            const numEl = cNumeros[i];
            const cciEl = cCci[i];
            const titularEl = cTitulares[i];
            const tipoEntidad = normalizeTipoEntidad(tipoEl.value);
            const isBilletera = isBilleteraTipo(tipoEntidad);
            const cciDigits = sanitizeDigits(cciEl?.value || '');
            const numeroDigits = sanitizeDigits(numEl?.value || '');
            
            if (!tipoEl.value) { if(showErrors) setInvalid(tipoEl, 'Requerido'); valid = false; }
            if (!entidadEl.value.trim()) { if(showErrors) setInvalid(entidadEl, 'Requerido'); valid = false; }

            if (isBilletera) {
                if (!/^\d{9}$/.test(cciDigits)) {
                    if (showErrors) setInvalid(cciEl, 'Para billetera ingrese un celular de 9 dígitos.');
                    valid = false;
                }
            } else {
                if (!cciDigits && !numeroDigits) {
                    if (showErrors) {
                        setInvalid(cciEl, 'Ingrese CCI o número de cuenta.');
                        setInvalid(numEl, 'Ingrese CCI o número de cuenta.');
                    }
                    valid = false;
                }
                if (cciDigits && cciDigits.length !== 20 && cciDigits.length < 6) {
                    if (showErrors) setInvalid(cciEl, 'El CCI debe tener 20 dígitos (o use número de cuenta).');
                    valid = false;
                }
            }

            if (!tipoCtaEl.value.trim()) {
                if (showErrors) setInvalid(tipoCtaEl, 'Requerido');
                valid = false;
            }

            if (!titularEl?.value.trim()) {
                if (showErrors) setInvalid(titularEl, 'Ingrese titular de la cuenta.');
                valid = false;
            }
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

            // Antes de enviar, reactivamos campos disabled temporalmente si es necesario para el backend
            // Pero en este caso, la lógica de negocio dice que si está cesado, no importan los datos de pago
            
            if (!validateForm(form, rolesFeedbackId, true)) {
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus?.({ preventScroll: true });
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'Faltan datos por completar',
                    text: 'Revisa los campos marcados en rojo antes de guardar.'
                });
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
                .then(parseJsonResponse)
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
                // Asegurar que campos se reseteen al estado inicial (activo)
                updateLaboralState(form);
            }
            
            document.getElementById('crearRolesFeedback')?.classList.add('d-none');
            
            const provSelect = document.getElementById('crearProvincia');
            const distSelect = document.getElementById('crearDistrito');
            const depSelect = document.getElementById('crearDepartamento');
            if (provSelect) {
                provSelect.innerHTML = '<option value="">Seleccionar...</option>';
                provSelect.disabled = true;
            }
            if (distSelect) {
                distSelect.innerHTML = '<option value="">Seleccionar...</option>';
                distSelect.disabled = true;
            }

            setUbigeoOptions(depSelect, provSelect, distSelect);

            window.TercerosClientes?.toggleComercialFields(
                document.getElementById('crearEsCliente'),
                document.getElementById('crearEsProveedor'),
                document.getElementById('crearComercialFields'),
                document.getElementById('crearComercialClienteSection'),
                document.getElementById('crearComercialProveedorSection'),
                document.getElementById('crearComercialDistribuidorSection')
            );
            window.TercerosClientes?.toggleDistribuidorFields(
                document.getElementById('crearEsDistribuidor'),
                document.getElementById('crearDistribuidorFields')
            );
            window.TercerosClientes?.setDistribuidorZones('crear', []);
            toggleLaboralFields(document.getElementById('crearEsEmpleado'), document.getElementById('crearLaboralFields'), form);
            togglePagoFields(document.getElementById('crearTipoPago'));
            toggleRegimenFields(document.getElementById('crearRegimen'), form);
            toggleRepresentanteLegal(form, 'crear');

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
                'editRepresentanteLegal': 'data-representante-legal',
                'editEstado': 'data-estado',
                
                // Comercial
                'editClienteDiasCredito': 'data-cliente-dias-credito',
                'editClienteLimiteCredito': 'data-cliente-limite-credito',
                'editClienteCondicionPago': 'data-cliente-condicion-pago',
                'editProvCondicion': 'data-proveedor-condicion-pago',
                'editProvDiasCredito': 'data-proveedor-dias-credito',
                'editProvFormaPago': 'data-proveedor-forma-pago',

                // Laboral
                'editCargo': 'data-cargo',
                'editArea': 'data-area',
                'editTipoContrato': 'data-tipo-contrato',
                'editFechaIngreso': 'data-fecha-ingreso',
                'editFechaCese': 'data-fecha-cese',
                'editFechaNacimiento': 'data-fecha-nacimiento',
                'editEstadoLaboral': 'data-estado-laboral',
                'editMoneda': 'data-moneda',
                'editSueldoBasico': 'data-sueldo-basico',
                'editRegimen': 'data-regimen-pensionario',
                'editTipoComision': 'data-tipo-comision-afp',
                'editCuspp': 'data-cuspp',
                'editTipoPago': 'data-tipo-pago',
                'editPagoDiario': 'data-pago-diario',

            };

            for (let id in fields) {
                const el = document.getElementById(id);
                if (el) {
                    const rawValue = button.getAttribute(fields[id]);
                    if (id === 'editEstadoLaboral') {
                        el.value = rawValue || 'activo';
                    } else if (id === 'editMoneda') {
                        el.value = rawValue || 'PEN';
                    } else {
                        el.value = rawValue || '';
                    }
                }
            }

            const essalud = document.getElementById('editEssalud');
            if (essalud) essalud.checked = button.getAttribute('data-essalud') === '1';
            
            const asigFam = document.getElementById('editAsignacionFamiliar');
            if (asigFam) asigFam.checked = button.getAttribute('data-asignacion-familiar') === '1';

            const recordarCumpleanos = document.getElementById('editRecordarCumpleanos');
            if (recordarCumpleanos) recordarCumpleanos.checked = button.getAttribute('data-recordar-cumpleanos') === '1';

            ['editEsCliente', 'editEsProveedor', 'editEsEmpleado', 'editEsDistribuidor'].forEach(id => {
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
            
            window.TercerosClientes?.toggleComercialFields(
                document.getElementById('editEsCliente'),
                document.getElementById('editEsProveedor'),
                document.getElementById('editComercialFields'),
                document.getElementById('editComercialClienteSection'),
                document.getElementById('editComercialProveedorSection'),
                document.getElementById('editComercialDistribuidorSection')
            );
            window.TercerosClientes?.toggleDistribuidorFields(
                document.getElementById('editEsDistribuidor'),
                document.getElementById('editDistribuidorFields')
            );
            const editId = button.getAttribute('data-id') || '';
            window.TercerosClientes?.loadSavedZones('edit', editId).catch(() => {
                let zonas = [];
                try { zonas = JSON.parse(button.getAttribute('data-zonas-exclusivas') || '[]'); } catch(e){}
                window.TercerosClientes?.setDistribuidorZones('edit', zonas);
            });
            toggleLaboralFields(document.getElementById('editEsEmpleado'), document.getElementById('editLaboralFields'), form);
            toggleCumpleanosFields(document.getElementById('editRecordarCumpleanos'), form);
            togglePagoFields(document.getElementById('editTipoPago'));
            toggleRegimenFields(document.getElementById('editRegimen'), form);
            toggleRepresentanteLegal(form, 'edit');
            
            // Forzar actualización de estado laboral después de cargar valores
            updateLaboralState(form);

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
                toggleLaboralFields(esEmpleado, document.getElementById(`${prefix}LaboralFields`), form);
                refreshValidationOnChange(form, fbId);
            });

            const esCliente = document.getElementById(`${prefix}EsCliente`);
            const esProv = document.getElementById(`${prefix}EsProveedor`);
            const updateCom = () => {
                window.TercerosClientes?.toggleComercialFields(
                    esCliente,
                    esProv,
                    document.getElementById(`${prefix}ComercialFields`),
                    document.getElementById(`${prefix}ComercialClienteSection`),
                    document.getElementById(`${prefix}ComercialProveedorSection`),
                    document.getElementById(`${prefix}ComercialDistribuidorSection`)
                );
                refreshValidationOnChange(form, fbId);
            };
            if(esCliente) esCliente.addEventListener('change', updateCom);
            if(esProv) esProv.addEventListener('change', updateCom);
            window.TercerosClientes?.bindDistribuidorToggle(prefix);
            window.TercerosClientes?.initDistribuidorZones(prefix);

            const tipoPago = document.getElementById(`${prefix}TipoPago`);
            if(tipoPago) tipoPago.addEventListener('change', () => {
                togglePagoFields(tipoPago);
                refreshValidationOnChange(form, fbId);
            });
            
            const regimen = document.getElementById(`${prefix}Regimen`);
            if(regimen) regimen.addEventListener('change', () => {
                toggleRegimenFields(regimen, form);
            });

            const tipoPersona = document.getElementById(`${prefix}TipoPersona`);
            if (tipoPersona) {
                tipoPersona.addEventListener('change', () => {
                    toggleRepresentanteLegal(form, prefix);
                    refreshValidationOnChange(form, fbId);
                });
                toggleRepresentanteLegal(form, prefix);
            }

            // NUEVO: Listener para Estado Laboral
            const estadoLaboral = document.getElementById(`${prefix}EstadoLaboral`);
            if(estadoLaboral) estadoLaboral.addEventListener('change', () => {
                updateLaboralState(form);
            });

            const recordarCumpleanos = document.getElementById(`${prefix}RecordarCumpleanos`);
            if (recordarCumpleanos) {
                recordarCumpleanos.addEventListener('change', () => {
                    toggleCumpleanosFields(recordarCumpleanos, form);
                    refreshValidationOnChange(form, fbId);
                });
                toggleCumpleanosFields(recordarCumpleanos, form);
            }
        };

        setup('crear');
        setup('edit');
    }

    function initMaestrosManagement() {
        const handleMaestroSubmit = (formId, listId, endpoint, selectsToUpdate) => {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const input = form.querySelector('input[type="text"]');
                const val = input.value.trim();
                if(!val) return;

                const fd = new FormData(form);
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(parseJsonResponse)
                .then(({ data: res }) => {
                    if (res.ok) {
                        const list = document.getElementById(listId);
                        if(list) {
                            const item = document.createElement('div');
                            item.className = 'list-group-item d-flex justify-content-between align-items-center';
                            item.innerHTML = `<span>${res.nombre}</span>`;
                            list.appendChild(item);
                        }
                        selectsToUpdate.forEach(selId => {
                            const sel = document.getElementById(selId);
                            if(sel) {
                                const opt = document.createElement('option');
                                opt.value = res.nombre;
                                opt.textContent = res.nombre;
                                sel.appendChild(opt);
                            }
                        });
                        input.value = '';
                        Swal.fire('Guardado', res.mensaje, 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        Swal.fire('Error', res.mensaje || 'Error desconocido', 'error');
                    }
                })
                .catch(err => console.error(err));
            });
        };

        handleMaestroSubmit('formCrearCargo', 'listaCargosConfig', 'guardar_cargo', ['crearCargo', 'editCargo']);
        handleMaestroSubmit('formCrearArea', 'listaAreasConfig', 'guardar_area', ['crearArea', 'editArea']);
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
                .then(parseJsonResponse)
                .then(({ data }) => {
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
                .then(parseJsonResponse)
                .then(({ data: d }) => {
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
        initMaestrosManagement(); 
        initSweetConfirmForms();

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
