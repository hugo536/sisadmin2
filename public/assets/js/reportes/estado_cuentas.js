/**
 * LÓGICA CENTRALIZADA PARA ESTADOS DE CUENTA (CLIENTES Y PROVEEDORES)
 * Archivo: public/assets/js/reportes/estado_cuentas.js
 */

if (typeof window.inicializarModuloEstadoCuentas === 'undefined') {
    
    window.inicializarModuloEstadoCuentas = function(config) {
        const { 
            formId, 
            terceroSelectId, 
            btnPdfId, 
            pdfAction, 
            excelAction, 
            csvAction 
        } = config;

        const formEstadoCuenta = document.getElementById(formId);
        
        // Validamos que el formulario exista y que no se haya inicializado ya (vital para SPA)
        if (!formEstadoCuenta || formEstadoCuenta.getAttribute('data-js-init') === 'true') {
            return;
        }
        formEstadoCuenta.setAttribute('data-js-init', 'true');

        const filtroTerceroEstadoCuenta = document.getElementById(terceroSelectId);
        
        // --- 1. FUNCIÓN PARA ENVIAR FILTROS (SPA) ---
        const submitEstadoCuentaFiltros = () => {
            // Validación de fechas antes de enviar
            const desde = formEstadoCuenta.querySelector('input[name="fecha_desde"]');
            const hasta = formEstadoCuenta.querySelector('input[name="fecha_hasta"]');
            if (desde && hasta && desde.value && hasta.value && desde.value > hasta.value) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Rango inválido',
                        text: 'La fecha "Desde" no puede ser mayor que la fecha "Hasta".',
                        confirmButtonColor: '#0B5ED7'
                    });
                } else {
                    alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta".');
                }
                return false;
            }

            const params = new URLSearchParams(new FormData(formEstadoCuenta));
            const baseUrl = formEstadoCuenta.action.split('?')[0];
            const destino = new URL(baseUrl, window.location.origin);
            destino.search = params.toString();

            if (typeof window.navigateWithoutReload === 'function') {
                window.navigateWithoutReload(destino, true);
            } else {
                window.location.href = destino.toString();
            }
        };

        // --- 2. LÓGICA BOTÓN LIMPIAR FILTROS ---
        const btnLimpiar = formEstadoCuenta.querySelector('#btnLimpiarFiltrosEstadoCuenta');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                if (typeof TomSelect !== 'undefined' && filtroTerceroEstadoCuenta && filtroTerceroEstadoCuenta.tomselect) {
                    filtroTerceroEstadoCuenta.tomselect.clear(true); 
                } else if (filtroTerceroEstadoCuenta) {
                    filtroTerceroEstadoCuenta.value = '';
                }

                const inputDesde = formEstadoCuenta.querySelector('input[name="fecha_desde"]');
                const inputHasta = formEstadoCuenta.querySelector('input[name="fecha_hasta"]');
                if (inputDesde) inputDesde.value = '';
                if (inputHasta) inputHasta.value = '';

                const baseUrl = formEstadoCuenta.action.split('?')[0];
                const destino = new URL(baseUrl, window.location.origin);
                const inputRuta = formEstadoCuenta.querySelector('input[name="ruta"]');
                if (inputRuta) destino.searchParams.set('ruta', inputRuta.value);

                if (typeof window.navigateWithoutReload === 'function') {
                    window.navigateWithoutReload(destino, true);
                } else {
                    window.location.href = destino.toString();
                }
            });
        }

        // --- 3. TEMPORIZADOR Y EVENTOS DE FORMULARIO ---
        const autoSubmitEstadoCuenta = (() => {
            let timer = null;
            return (delay = 350) => {
                if (timer) window.clearTimeout(timer);
                timer = window.setTimeout(() => submitEstadoCuentaFiltros(), delay);
            };
        })();

        formEstadoCuenta.addEventListener('submit', (event) => {
            event.preventDefault();
            submitEstadoCuentaFiltros();
        });

        const filtrosAutoSubmit = formEstadoCuenta.querySelectorAll('[name="vista"]');
        filtrosAutoSubmit.forEach((field) => {
            field.addEventListener('change', () => autoSubmitEstadoCuenta());
        });

        // --- 4. INTEGRACIÓN DE TOMSELECT ---
        if (filtroTerceroEstadoCuenta) {
            if (typeof TomSelect !== 'undefined') {
                new TomSelect(filtroTerceroEstadoCuenta, {
                    create: false,
                    placeholder: "Buscar...",
                    onChange: function() {
                        autoSubmitEstadoCuenta();
                    }
                });
            } else {
                filtroTerceroEstadoCuenta.addEventListener('change', () => autoSubmitEstadoCuenta());
            }
        }
        
        // --- 5. LÓGICA DE EXPORTACIÓN Y VALIDACIÓN ---
        
        // Función dinámica para obtener los registros de la tabla que esté actualmente visible
        const obtenerTotalRegistros = () => {
            const tablaVisible = document.querySelector('table[data-erp-table="true"]');
            
            // 1. Si la tabla tiene el atributo explícito, lo usamos (ej. Vista Productos)
            if (tablaVisible && tablaVisible.hasAttribute('data-total-rows')) {
                const total = parseInt(tablaVisible.getAttribute('data-total-rows'), 10);
                if (!isNaN(total)) return total;
            }
            
            // 2. Si no lo tiene (ej. Vista Historial), contamos las filas físicas reales
            return document.querySelectorAll('tbody tr[data-search]').length;
        };

        const verificarDatosVacios = (e) => {
            const totalRegistros = obtenerTotalRegistros();
            if (totalRegistros === 0) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin datos para exportar',
                        text: 'No hay registros en el periodo y filtros seleccionados.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#0B5ED7'
                    });
                } else {
                    alert('No hay registros para exportar.');
                }
                return true; 
            }
            return false; 
        };

        const prepararExportacion = (accion) => {
            const params = new URLSearchParams(new FormData(formEstadoCuenta));
            params.set('accion', accion); 
            const baseUrl = formEstadoCuenta.action.split('?')[0]; 
            const urlCompleta = `${baseUrl}?${params.toString()}`;
            window.open(urlCompleta, '_blank');
        };

        // Seleccionamos todos los botones por ID usando querySelectorAll para abarcar cualquier vista
        const btnsExportarPdf = document.querySelectorAll(`#${btnPdfId}, #btnExportarPdfLimitado`);
        btnsExportarPdf.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (verificarDatosVacios(e)) return;

                const totalRegistros = obtenerTotalRegistros();
                if (totalRegistros >= 2500) {
                    alert(`⚠️ LÍMITE EXCEDIDO: El reporte contiene ${totalRegistros} registros.\nPor favor, utiliza la opción de exportar a Excel o CSV.`);
                    return; 
                }
                if (totalRegistros >= 1000) {
                    const continuar = confirm(`⚠️ ATENCIÓN: Estás intentando exportar ${totalRegistros} registros a PDF.\n¿Estás seguro?`);
                    if (!continuar) return;
                }
                prepararExportacion(pdfAction);
            });
        });

        const btnsExportarExcel = document.querySelectorAll('#btnExportarExcel');
        btnsExportarExcel.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (verificarDatosVacios(e)) return;
                prepararExportacion(excelAction);
            });
        });

        const btnsExportarCsv = document.querySelectorAll('#btnExportarCsv');
        btnsExportarCsv.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (verificarDatosVacios(e)) return;
                prepararExportacion(csvAction);
            });
        });
    };

    // --- AUTO-INICIALIZACIÓN INTELIGENTE (SOPORTA SPA Y F5) ---
    const autoIniciarEstadoCuentas = () => {
        if (document.getElementById('estadoCuentaFiltrosForm')) {
            window.inicializarModuloEstadoCuentas({
                formId: 'estadoCuentaFiltrosForm',
                terceroSelectId: 'filtroClienteEstadoCuenta',
                btnPdfId: 'btnExportarPdf',
                pdfAction: 'imprimir_estado_cuenta',
                excelAction: 'exportar_excel_estado_cuenta',
                csvAction: 'exportar_csv_estado_cuenta'
            });
        }

        if (document.getElementById('estadoCuentaProveedoresFiltrosForm')) {
            window.inicializarModuloEstadoCuentas({
                formId: 'estadoCuentaProveedoresFiltrosForm',
                terceroSelectId: 'filtroProveedorEstadoCuenta',
                btnPdfId: 'btnExportarPdfProveedores',
                pdfAction: 'imprimir_estado_cuenta_proveedores',
                excelAction: 'exportar_excel_estado_cuenta_proveedores',
                csvAction: 'exportar_csv_estado_cuenta_proveedores'
            });
        }
    };

    document.addEventListener('DOMContentLoaded', autoIniciarEstadoCuentas);
    document.addEventListener('sisadmin:route-loaded', autoIniciarEstadoCuentas);
}