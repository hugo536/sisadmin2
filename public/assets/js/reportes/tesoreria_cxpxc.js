/**
 * LÓGICA GLOBAL PARA REPORTES MACRO DE TESORERÍA (CXC Y CXP)
 * Archivo: public/assets/js/tesoreria_cxpxc.js
 */

if (typeof window.inicializarModuloTesoreriaCxPCxC === 'undefined') {
    
    window.inicializarModuloTesoreriaCxPCxC = function(config) {
        const { 
            formId, 
            pdfAction, 
            excelAction, 
            csvAction 
        } = config;

        const formReporte = document.getElementById(formId);
        
        // Validamos que el formulario exista y que no se haya inicializado ya (vital para SPA)
        if (!formReporte || formReporte.getAttribute('data-js-init') === 'true') {
            return;
        }
        formReporte.setAttribute('data-js-init', 'true');
        
        // --- 1. FUNCIÓN PARA ENVIAR FILTROS (SPA) ---
        const submitFiltros = () => {
            const desde = formReporte.querySelector('input[name="fecha_desde"]');
            const hasta = formReporte.querySelector('input[name="fecha_hasta"]');
            
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

            const params = new URLSearchParams(new FormData(formReporte));
            const baseUrl = formReporte.action.split('?')[0];
            const destino = new URL(baseUrl, window.location.origin);
            destino.search = params.toString();

            if (typeof window.navigateWithoutReload === 'function') {
                window.navigateWithoutReload(destino, true);
            } else {
                window.location.href = destino.toString();
            }
        };

        // --- 2. LÓGICA BOTÓN LIMPIAR FILTROS ---
        const btnLimpiar = formReporte.querySelector('[id^="btnLimpiarFiltros"]');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                // Resetear todos los selectores nativos a su primera opción ('todos')
                const selects = formReporte.querySelectorAll('select');
                selects.forEach(s => {
                    if (s.options.length > 0) {
                        s.value = s.options[0].value; 
                    }
                });

                // Limpiar fechas
                const inputDesde = formReporte.querySelector('input[name="fecha_desde"]');
                const inputHasta = formReporte.querySelector('input[name="fecha_hasta"]');
                if (inputDesde) inputDesde.value = '';
                if (inputHasta) inputHasta.value = '';

                // Redirigir limpiamente
                const inputRuta = formReporte.querySelector('input[name="ruta"]');
                const rutaValor = inputRuta ? inputRuta.value : '';
                const destino = new URL(window.location.origin + '/' + (window.location.pathname.includes('public') ? 'sisadmin2/public/' : ''));
                if (rutaValor) destino.searchParams.set('ruta', rutaValor);

                if (typeof window.navigateWithoutReload === 'function') {
                    window.navigateWithoutReload(destino, true);
                } else {
                    window.location.href = destino.toString();
                }
            });
        }

        // --- 3. TEMPORIZADOR Y EVENTOS DE FORMULARIO ---
        const autoSubmit = (() => {
            let timer = null;
            return (delay = 350) => {
                if (timer) window.clearTimeout(timer);
                timer = window.setTimeout(() => submitFiltros(), delay);
            };
        })();

        formReporte.addEventListener('submit', (event) => {
            event.preventDefault();
            submitFiltros();
        });

        // Escuchar cambios en CUALQUIER select del formulario (Segmentos, Estados)
        const selectoresAuto = formReporte.querySelectorAll('select');
        selectoresAuto.forEach((field) => {
            field.addEventListener('change', () => autoSubmit());
        });
        
        // --- 4. LÓGICA DE EXPORTACIÓN Y VALIDACIÓN ---
        const tablaDetalle = document.querySelector('table[data-erp-table="true"]') || document.querySelector('table');
        const filasReales = document.querySelectorAll('tbody tr[data-search]').length;
        const totalRegistros = tablaDetalle ? parseInt(tablaDetalle.getAttribute('data-total-rows') || filasReales, 10) : filasReales;

        const verificarDatosVacios = (e) => {
            if (totalRegistros === 0) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin datos para exportar',
                        text: 'No hay registros en la cartera con los filtros seleccionados.',
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
            const params = new URLSearchParams(new FormData(formReporte));
            params.set('accion', accion); 
            const baseUrl = formReporte.action.split('?')[0]; 
            const urlCompleta = `${baseUrl}?${params.toString()}`;
            window.open(urlCompleta, '_blank');
        };

        // Enlazamos botones de exportación buscando IDs que contengan la palabra clave
        document.querySelectorAll('[id*="ExportarPdf"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (verificarDatosVacios(e)) return;
                prepararExportacion(pdfAction);
            });
        });

        document.querySelectorAll('[id*="ExportarExcel"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (verificarDatosVacios(e)) return;
                prepararExportacion(excelAction);
            });
        });

        document.querySelectorAll('[id*="ExportarCsv"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (verificarDatosVacios(e)) return;
                prepararExportacion(csvAction);
            });
        });
    };

    // --- AUTO-INICIALIZACIÓN INTELIGENTE (SOPORTA SPA Y F5) ---
    const autoIniciarTesoreriaCxPCxC = () => {
        // 1. Vista de Reporte CxC (Cuentas por Cobrar)
        if (document.getElementById('cxcReporteFiltrosForm')) {
            window.inicializarModuloTesoreriaCxPCxC({
                formId: 'cxcReporteFiltrosForm',
                pdfAction: 'exportar_pdf_cxc',
                excelAction: 'exportar_excel_cxc',
                csvAction: 'exportar_csv_cxc'
            });
        }

        // 2. Vista de Reporte CxP (Cuentas por Pagar)
        if (document.getElementById('cxpReporteFiltrosForm')) {
            window.inicializarModuloTesoreriaCxPCxC({
                formId: 'cxpReporteFiltrosForm',
                pdfAction: 'exportar_pdf_cxp',
                excelAction: 'exportar_excel_cxp',
                csvAction: 'exportar_csv_cxp'
            });
        }
    };

    document.addEventListener('DOMContentLoaded', autoIniciarTesoreriaCxPCxC);
    document.addEventListener('sisadmin:route-loaded', autoIniciarTesoreriaCxPCxC);
}