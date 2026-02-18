/**
 * GESTIÓN COMERCIAL (Presentaciones, Listas, Asignaciones)
 * Estructura: "App Mode" (Igual que Compras)
 */

document.addEventListener('DOMContentLoaded', () => {

    // =========================================================================
    // 1. INICIALIZACIÓN Y REFERENCIAS (INTERRUPTOR MAESTRO)
    // =========================================================================
    // Buscamos el contenedor principal definido en la vista
    const app = document.getElementById('presentacionesApp');
    if (!app) return; // Si no existe, detenemos el script para evitar errores.

    console.log("🚀 Presentaciones App Iniciado");

    // Lectura de Rutas desde el HTML (Dataset)
    const urls = {
        obtener: app.dataset.urlObtener,
        eliminar: app.dataset.urlEliminar,
        estado: app.dataset.urlEstado
    };

    // Elementos del DOM
    const tablaPresentaciones = document.getElementById('presentacionesTable');
    const modalEl = document.getElementById('modalCrearPresentacion');
    const formPresentacion = document.getElementById('formPresentacion');
    const btnCrear = document.querySelector('.js-crear-presentacion');
    const modalTitle = document.getElementById('modalTitle');

    // Inicializar Modal Bootstrap
    const modalBootstrap = modalEl ? new bootstrap.Modal(modalEl) : null;

    // Inputs del Formulario
    const inputId = document.getElementById('presentacionId');
    const inputItem = document.getElementById('inputItem'); 
    const inputFactor = document.getElementById('inputFactor');
    const inputPrecioMenor = document.getElementById('inputPrecioMenor');
    const inputPrecioMayor = document.getElementById('inputPrecioMayor');
    const inputMinMayor = document.getElementById('inputMinMayor');

    // =========================================================================
    // 2. FUNCIONES LÓGICAS (ASYNC/AWAIT)
    // =========================================================================

    // --- A. Cargar Datos para Editar ---
    const cargarDatos = async (id) => {
        if (!modalBootstrap) return;

        // Feedback Visual
        if(modalTitle) modalTitle.innerHTML = '<i class="bi bi-arrow-clockwise me-2 fa-spin"></i>Cargando datos...';
        modalBootstrap.show();

        try {
            const response = await fetch(`${urls.obtener}&id=${id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error('Error en la respuesta del servidor');

            const res = await response.json();

            if (res.success) {
                const d = res.data;

                // Llenar ID oculto
                if(inputId) inputId.value = d.id;

                // --- Lógica de Bloqueo del Producto Base ---
                if (inputItem) {
                    if (inputItem.tomselect) {
                        inputItem.tomselect.setValue(d.id_item);
                        inputItem.tomselect.disable(); // Bloquea TomSelect
                    } else {
                        inputItem.value = d.id_item;
                        inputItem.disabled = true; // Bloquea select estándar
                    }
                }

                // Llenar el resto de campos numéricos
                if(inputFactor) inputFactor.value = parseFloat(d.factor);
                if(inputPrecioMenor) inputPrecioMenor.value = d.precio_x_menor;
                if(inputPrecioMayor) inputPrecioMayor.value = d.precio_x_mayor;
                if(inputMinMayor) inputMinMayor.value = d.cantidad_minima_mayor;

                if(modalTitle) modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Presentación';
            } else {
                throw new Error(res.message || 'No se pudo cargar la información');
            }

        } catch (error) {
            console.error(error);
            alert('Error: ' + error.message);
            modalBootstrap.hide();
        }
    };

    // --- B. Resetear Formulario (Para nueva presentación) ---
    const resetearFormulario = () => {
        if (!formPresentacion) return;
        
        formPresentacion.reset();
        if(inputId) inputId.value = ''; 

        // --- Rehabilitar el campo de Producto ---
        if (inputItem) {
            if (inputItem.tomselect) {
                inputItem.tomselect.clear();
                inputItem.tomselect.enable(); // Habilita TomSelect
            } else {
                inputItem.disabled = false; // Habilita select estándar
            }
        }

        if(modalTitle) modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nueva Presentación';
    };

    // =========================================================================
    // 3. DELEGACIÓN DE EVENTOS (TABLA)
    // =========================================================================
    if (tablaPresentaciones) {
        tablaPresentaciones.addEventListener('click', (e) => {
            
            // Detectar clicks en elementos hijos (iconos) y subir al botón
            const target = e.target.closest('button') || e.target.closest('input[type="checkbox"]');
            if (!target) return;

            const id = target.dataset.id;

            // --- CASO 1: EDITAR ---
            if (target.classList.contains('js-editar-presentacion')) {
                e.preventDefault();
                cargarDatos(id);
            }

            // --- CASO 2: ELIMINAR ---
            if (target.classList.contains('js-eliminar-presentacion')) {
                e.preventDefault();
                if (confirm('¿Estás seguro de eliminar esta presentación?\nEsta acción no se puede deshacer.')) {
                    // Usamos la URL del dataset
                    window.location.href = `${urls.eliminar}&id=${id}`;
                }
            }

            // --- CASO 3: CAMBIAR ESTADO (SWITCH) ---
            if (target.classList.contains('js-toggle-estado-presentacion')) {
                const estado = target.checked ? 1 : 0;
                // Usamos la URL del dataset
                window.location.href = `${urls.estado}&id=${id}&estado=${estado}`;
            }
        });
    }

    // =========================================================================
    // 4. OTROS EVENTOS
    // =========================================================================
    
    // Botón "Nueva Presentación"
    if (btnCrear) {
        btnCrear.addEventListener('click', resetearFormulario);
    }

    // Filtros de búsqueda (Lógica simple visual)
    const inputBuscador = document.getElementById('presentacionSearch');
    const filtroProducto = document.getElementById('presentacionFiltroProducto');
    const filtroEstado = document.getElementById('presentacionFiltroEstado');
    
    if (inputBuscador && tablaPresentaciones) {
        const filas = Array.from(tablaPresentaciones.querySelectorAll('tbody tr'));
        
        const filtrarTabla = () => {
            const termino = inputBuscador.value.toLowerCase().trim();
            const prod = filtroProducto ? filtroProducto.value : '';
            const est = filtroEstado ? filtroEstado.value : '';

            filas.forEach(fila => {
                const search = fila.getAttribute('data-search') || '';
                const fIdItem = fila.getAttribute('data-id-item') || '';
                const fEstado = fila.getAttribute('data-estado') || '';

                const matchTexto = search.includes(termino);
                const matchProd = prod === '' || fIdItem === prod;
                const matchEst = est === '' || fEstado === est;

                fila.style.display = (matchTexto && matchProd && matchEst) ? '' : 'none';
            });
        };

        [inputBuscador, filtroProducto, filtroEstado].forEach(el => {
            if(el) {
                el.addEventListener('input', filtrarTabla);
                el.addEventListener('change', filtrarTabla);
            }
        });
    }
    
    // =========================================================================
    // 5. EXTRAS (Listas de Precios y Asignaciones)
    // =========================================================================
    // Se mantiene tu lógica original para las otras tablas si existen en el DOM
    const tablaAsignacion = document.getElementById('clientesAsignacionTable');
    if (tablaAsignacion) {
        const selects = tablaAsignacion.querySelectorAll('.js-cambiar-lista');
        selects.forEach(select => {
            select.addEventListener('change', function() {
                const idCliente = this.dataset.idCliente;
                const idLista = this.value;
                this.disabled = true;
                
                fetch('?ruta=comercial/guardarAsignacionAjax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id_cliente: idCliente, id_lista: idLista })
                })
                .then(r => r.json())
                .then(data => {
                    this.disabled = false;
                    if (data.success) {
                        this.classList.add('is-valid', 'bg-success-subtle');
                        setTimeout(() => this.classList.remove('is-valid', 'bg-success-subtle'), 1500);
                    } else {
                        alert('Error: ' + (data.message || 'Desconocido'));
                        this.classList.add('is-invalid');
                    }
                })
                .catch(e => {
                    console.error(e);
                    this.disabled = false;
                    alert('Error de conexión');
                });
            });
        });
    }
});