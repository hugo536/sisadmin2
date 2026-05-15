<?php
/**
 * 🔍 SUPER CHECKER V2 - Diagnóstico de Nombres Ocultos en Tabla Inventario
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

echo "<!DOCTYPE html><html><head><title>Debugger Inventario Móvil</title>";
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">';
?>
<style>
    /* Inyectamos el CSS que te di para verificar que funciona perfectamente a nivel de código */
    @media (max-width: 767.98px) {
        table#tablaInventarioStock.erp-mobile-cards,
        table#tablaInventarioStock.erp-mobile-cards tbody,
        table#tablaInventarioStock.erp-mobile-cards tr,
        table#tablaInventarioStock.erp-mobile-cards td { display: block !important; width: 100% !important; }
        table#tablaInventarioStock.erp-mobile-cards thead { display: none !important; }
        table#tablaInventarioStock.erp-mobile-cards tr {
            margin-bottom: 1rem !important; border: 1px solid #e0e0e0 !important;
            border-radius: 10px !important; padding: 16px !important; background-color: #ffffff !important;
        }
        table#tablaInventarioStock.erp-mobile-cards td {
            border: none !important; border-bottom: 1px dashed #e9ecef !important; padding: 10px 0 !important; text-align: left !important;
        }
        table#tablaInventarioStock.erp-mobile-cards td::before {
            content: attr(data-label) !important; display: block !important;
            font-size: 0.75rem !important; text-transform: uppercase !important; color: #6c757d !important; font-weight: 700 !important; margin-bottom: 6px !important;
        }
        table#tablaInventarioStock.erp-mobile-cards td:nth-child(1)::before { display: none !important; }
        table#tablaInventarioStock.erp-mobile-cards td > div,
        table#tablaInventarioStock.erp-mobile-cards td > span {
            visibility: visible !important; opacity: 1 !important; display: flex !important;
        }
    }
</style>
</head><body style='background: #f8f9fa; padding: 20px;'>

<div class="container" style="max-width: 1000px;">
    <h2 class="text-primary mb-3"><i class="bi bi-bug-fill"></i> DIAGNÓSTICO: ¿Dónde está el Producto?</h2>
    <p class="lead">Este script evalúa el DOM en tiempo real para atrapar al JavaScript culpable que borra tu código.</p>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold">1. Fila Simulada (Como debería verse)</div>
                <div class="card-body p-3 bg-light">
                    
                    <table class="table align-middle mb-0 erp-mobile-cards" id="tablaInventarioStock">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Almacén</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-label="Producto" id="tdProducto">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-wrap" id="spanNombre">BOTELLA PET 18 GR - AGUA BELÉN 625ML</span>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold ms-1" style="font-size: 0.65rem;">PACK</span>
                                    </div>
                                </td>
                                <td data-label="Almacén" id="tdAlmacen">Múltiples Almacenes</td>
                                <td data-label="Acciones">
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">Activo</span>
                                        <button class="btn btn-sm btn-light"><i class="bi bi-eye fs-5"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-terminal"></i> Consola de DOM</span>
                    <button id="btnEscanear" class="btn btn-sm btn-light text-danger fw-bold shadow-sm">ESCANEAR AHORA</button>
                </div>
                <div class="card-body bg-dark text-light p-3" style="font-family: monospace; font-size: 14px; overflow-y: auto; height: 350px;" id="consolaLog">
                    > Esperando instrucción... <br>
                    > Redimensiona tu pantalla simulando un móvil y luego presiona "ESCANEAR AHORA".<br><br>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const consola = document.getElementById('consolaLog');
    const btnEscanear = document.getElementById('btnEscanear');
    
    function log(msg, color = "#fff") {
        consola.innerHTML += `<span style="color: ${color}">> ${msg}</span><br>`;
        consola.scrollTop = consola.scrollHeight;
    }

    btnEscanear.addEventListener('click', function() {
        log("--- INICIANDO DIAGNÓSTICO MÓVIL ---", "#00ffff");
        
        const tdProducto = document.getElementById('tdProducto');
        const spanNombre = document.getElementById('spanNombre');
        const tdAlmacen = document.getElementById('tdAlmacen');
        
        // 1. Verificación de Borrado de Nodos (El mayor sospechoso)
        if (!tdProducto) {
            log("❌ ERROR CRÍTICO: El contenedor <td> del Producto HA DESAPARECIDO.", "#ff5555");
            log("💡 CAUSA: Un JS externo (ERPTable) está redibujando la tabla y destruyó el elemento.", "#ffff55");
            return;
        }
        
        if (!spanNombre) {
            log("❌ ERROR CRÍTICO: El <span> del nombre existe, pero fue limpiado/borrado por JS.", "#ff5555");
            return;
        }

        log("✅ DOM Intacto: Las etiquetas HTML siguen en su lugar.", "#55ff55");

        // 2. Verificación de CSS Computado
        const estilosTd = window.getComputedStyle(tdProducto);
        const estilosSpan = window.getComputedStyle(spanNombre);
        
        log(`[INFO] <td> Producto -> display: ${estilosTd.display}, visibility: ${estilosTd.visibility}`);
        log(`[INFO] <span> Nombre -> display: ${estilosSpan.display}, visibility: ${estilosSpan.visibility}`);

        if (estilosTd.display === 'none' || estilosSpan.display === 'none') {
            log("❌ ERROR CSS: Un código externo está inyectando un 'display: none' muy agresivo.", "#ff5555");
        } else if (parseFloat(estilosTd.width) < 5 || parseFloat(estilosSpan.width) < 5) {
            log("❌ ERROR DE DIMENSIÓN: El elemento colapsó (Ancho de 0px).", "#ff5555");
        } else {
            log("✅ CSS PERFECTO: Si estuvieras viendo esto en tu sistema real, el nombre SE VERÍA.", "#55ff55");
        }
        
        log("--- DIAGNÓSTICO FINALIZADO ---", "#00ffff");
        log("💡 CONCLUSIÓN: Si en esta prueba aislada el diseño se ve perfecto, significa que el archivo base de tu sistema llamado 'ERPTable' está reestructurando la tabla usando Javascript cuando detecta pantallas pequeñas.", "#ffaa00");
    });
});
</script>
</body></html>