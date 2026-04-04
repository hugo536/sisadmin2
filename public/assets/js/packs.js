/**
 * Script para la gestión de Packs y Combos Comerciales (BOM Comercial)
 * Archivo: public/assets/js/items/packs.js
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // 1. Referencias a los elementos del DOM
    const listaPacks = document.querySelectorAll('.pack-item-btn');
    const inputBuscar = document.getElementById('buscarPack');
    const panelVacio = document.getElementById('panelVacio');
    const panelConfiguracion = document.getElementById('panelConfiguracion');
    
    const lblNombrePack = document.getElementById('lblNombrePack');
    const lblPrecioPack = document.getElementById('lblPrecioPack');
    const idPackSeleccionadoInput = document.getElementById('idPackSeleccionado');
    
    const formAgregar = document.getElementById('formAgregarComponente');
    const selectComponente = document.getElementById('selectComponente');
    const inputCantidad = document.getElementById('inputCantidad');
    const checkBonificacion = document.getElementById('checkBonificacion');
    
    const tbodyComponentes = document.querySelector('#tablaComponentes tbody');
    const filaVacia = document.getElementById('filaVacia');
    const templateFila = document.getElementById('templateFilaComponente');

    // =========================================================
    // A. BUSCADOR LATERAL (Filtro en tiempo real)
    // =========================================================
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            const termino = this.value.trim().toLowerCase();
            listaPacks.forEach(btn => {
                const textoBtn = btn.textContent.toLowerCase();
                btn.style.display = textoBtn.includes(termino) ? '' : 'none';
            });
        });
    }

    // =========================================================
    // B. SELECCIÓN DE UN PACK
    // =========================================================
    listaPacks.forEach(btn => {
        btn.addEventListener('click', function() {
            // 1. Quitar la clase 'active' de todos los botones
            listaPacks.forEach(b => {
                b.classList.remove('active', 'bg-primary-subtle', 'border-primary');
            });
            
            // 2. Resaltar el botón clickeado
            this.classList.add('active', 'bg-primary-subtle', 'border-primary');

            // 3. Obtener los datos almacenados en los atributos 'data-' del botón
            const idPack = this.dataset.id;
            const nombrePack = this.dataset.nombre;
            const precioPack = parseFloat(this.dataset.precio).toFixed(2);

            // 4. Actualizar el panel derecho con la información
            idPackSeleccionadoInput.value = idPack;
            lblNombrePack.textContent = nombrePack;
            lblPrecioPack.textContent = `S/ ${precioPack}`;

            // 5. Ocultar estado vacío y mostrar panel de configuración
            panelVacio.classList.add('d-none');
            panelConfiguracion.classList.remove('d-none');

            // 6. Llamar a la base de datos para traer los componentes
            cargarComponentesDelPack(idPack);
        });
    });

    // =========================================================
    // C. INICIALIZAR SELECT2 (Buscador de ítems para armar receta)
    // =========================================================
    // Aseguramos que jQuery y Select2 estén cargados en el proyecto
    if (typeof jQuery !== 'undefined' && $.fn.select2) {
        $(selectComponente).select2({
            placeholder: "Buscar producto, envase o insumo...",
            allowClear: true,
            width: '100%',
            // 💡 NOTA: Aquí conectarás la URL de tu buscador de ítems general
            /*
            ajax: {
                url: '/items/buscar_ajax', // Cambia esto por tu ruta real
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term }; // Término de búsqueda
                },
                processResults: function (data) {
                    // Tu endpoint debe devolver { id: 1, text: 'Nombre Item' }
                    return { results: data }; 
                },
                cache: true
            }
            */
        });
    }

    // =========================================================
    // D. AGREGAR COMPONENTE A LA RECETA (Submit)
    // =========================================================
    formAgregar.addEventListener('submit', function(e) {
        e.preventDefault();

        // Recolectar datos
        const idPack = idPackSeleccionadoInput.value;
        const idItem = selectComponente.value;
        // Obtenemos el texto del select. Si usas Select2, esto captura el nombre escrito/seleccionado
        const nombreItem = selectComponente.options[selectComponente.selectedIndex]?.text || 'Item Demo';
        const cantidad = inputCantidad.value;
        const esBonificacion = checkBonificacion.checked ? 1 : 0;

        if (!idPack || idPack === '0') {
            alert('Error: No se ha seleccionado un Pack válido.');
            return;
        }

        if (!idItem || idItem === '') {
            alert('Por favor selecciona un componente para agregar.');
            return;
        }

        // 💡 NOTA PARA EL BACKEND: Aquí enviarás la petición POST para guardar en DB
        /*
        const formData = new FormData();
        formData.append('id_pack', idPack);
        formData.append('id_item_componente', idItem);
        formData.append('cantidad', cantidad);
        formData.append('es_bonificacion', esBonificacion);

        fetch('/items/packs/agregar_componente', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Si guardó bien, recargamos la tabla
                cargarComponentesDelPack(idPack);
                limpiarFormulario();
            } else {
                alert(data.mensaje || 'Error al guardar el componente');
            }
        })
        .catch(error => console.error('Error:', error));
        */

        // ---> 🛠️ SIMULACIÓN VISUAL (Borrar cuando conectes el Fetch real de arriba) <---
        const idDetalleFalso = Math.floor(Math.random() * 10000); // ID temporal
        dibujarFilaEnTabla({
            id_detalle: idDetalleFalso,
            nombre_item: nombreItem,
            cantidad: cantidad,
            es_bonificacion: esBonificacion
        });
        limpiarFormulario();
        // ---> FIN DE SIMULACIÓN <---
    });

    // =========================================================
    // E. FUNCIONES AUXILIARES
    // =========================================================

    /**
     * Limpia los inputs del formulario después de agregar
     */
    function limpiarFormulario() {
        inputCantidad.value = 1;
        checkBonificacion.checked = false;
        
        // Limpiar Select2
        if (typeof jQuery !== 'undefined' && $.fn.select2) {
            $(selectComponente).val(null).trigger('change');
        } else {
            selectComponente.value = '';
        }
    }

    /**
     * Trae los componentes de la DB y llena la tabla
     */
    function cargarComponentesDelPack(idPack) {
        // Mostrar estado de carga
        tbodyComponentes.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm text-primary me-2"></div> 
                    Cargando componentes...
                </td>
            </tr>`;
        
        // 💡 NOTA PARA EL BACKEND: Aquí enviarás la petición GET a tu DB
        /*
        fetch(`/items/packs/obtener_componentes?id_pack=${idPack}`)
        .then(res => res.json())
        .then(data => {
            tbodyComponentes.innerHTML = ''; // Limpiar loader
            if(data.length === 0) {
                tbodyComponentes.appendChild(filaVacia);
            } else {
                data.forEach(item => dibujarFilaEnTabla(item));
            }
        });
        */

        // ---> 🛠️ SIMULACIÓN VISUAL <---
        setTimeout(() => {
            tbodyComponentes.innerHTML = '';
            tbodyComponentes.appendChild(filaVacia);
        }, 400); // Simula 400ms de red
    }

    /**
     * Clona el template HTML y lo inserta en el tbody
     */
    function dibujarFilaEnTabla(data) {
        // Si la tabla solo tiene el mensaje "Fila Vacia", lo quitamos
        if (tbodyComponentes.contains(filaVacia)) {
            tbodyComponentes.removeChild(filaVacia);
        }

        // Clonar el HTML del <template>
        const clon = templateFila.content.cloneNode(true);
        const tr = clon.querySelector('tr');

        // Asignar el ID del registro de DB al <tr> para poder eliminarlo después
        tr.dataset.idDetalle = data.id_detalle;

        // Rellenar textos
        clon.querySelector('.td-nombre').textContent = data.nombre_item;
        clon.querySelector('.td-cantidad').textContent = parseFloat(data.cantidad).toFixed(2);
        
        // Estilizar el badge según el tipo
        const badgeTipo = clon.querySelector('.badge-tipo');
        if (data.es_bonificacion == 1) {
            badgeTipo.textContent = 'Bonificación / Regalo';
            badgeTipo.classList.add('bg-warning-subtle', 'text-warning-emphasis', 'border', 'border-warning-subtle');
        } else {
            badgeTipo.textContent = 'Componente Base';
            badgeTipo.classList.add('bg-info-subtle', 'text-info', 'border', 'border-info-subtle');
        }

        // Asignar evento al botón de eliminar (Tachito de basura)
        const btnEliminar = clon.querySelector('.btn-eliminar-componente');
        btnEliminar.addEventListener('click', function() {
            if(confirm('¿Estás seguro de quitar este componente del Pack?')) {
                
                // 💡 NOTA PARA EL BACKEND: Petición para borrar en DB
                /*
                fetch(`/items/packs/eliminar_componente`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_detalle: data.id_detalle })
                })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.success) {
                        tr.remove();
                        verificarTablaVacia();
                    }
                });
                */
               
               // Simulación visual
               tr.remove();
               verificarTablaVacia();
            }
        });

        // Inyectar a la tabla
        tbodyComponentes.appendChild(clon);

        // Reinicializar los tooltips de Bootstrap para esta nueva fila
        var tooltipList = [].slice.call(tr.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipList.map(function (el) { return new bootstrap.Tooltip(el); });
    }

    /**
     * Vuelve a mostrar el mensaje de vacío si nos quedamos sin filas
     */
    function verificarTablaVacia() {
        if (tbodyComponentes.children.length === 0) {
            tbodyComponentes.appendChild(filaVacia);
        }
    }
});