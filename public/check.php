<?php
/**
 * 🔍 SUPER CHECKER V3 - Diagnóstico de Nombres Ocultos (Con MutationObserver)
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

echo "<!DOCTYPE html><html><head><title>Debugger Inventario Móvil V3</title>";
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">';
?>
<style>
    /* CSS Base de prueba */
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

<div class="container" style="max-width: 1200px;">
    <h2 class="text-primary mb-3"><i class="bi bi-radar"></i> DIAGNÓSTICO V3: El Vigilante del DOM</h2>
    <p class="lead">Si algún script externo (como ERPTable) toca esta tabla, la consola te avisará en tiempo real.</p>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-dark text-white fw-bold">1. Fila Simulada</div>
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
                            <tr id="filaTest">
                                <td data-label="Producto" id="tdProducto">
                                    <div class="d-flex align-items-center gap-2" id="divContenedorNombre">
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

            <div class="card shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-danger"><i class="bi bi-bug"></i> Simulador de ataques (Prueba el radar)</h6>
                    <div class="d-flex gap-2 mt-2">
                        <button id="btnSimularOcultar" class="btn btn-sm btn-outline-danger">Inyectar 'display:none'</button>
                        <button id="btnSimularDestruir" class="btn btn-sm btn-outline-danger">Destruir DOM interno</button>
                        <button id="btnRestaurar" class="btn btn-sm btn-success">Restaurar Fila</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-terminal"></i> Consola del Radar</span>
                    <button id="btnEscanear" class="btn btn-sm btn-primary fw-bold shadow-sm">ESCANEAR AHORA</button>
                </div>
                <div class="card-body bg-black text-light p-3" style="font-family: monospace; font-size: 13px; overflow-y: auto; height: 450px;" id="consolaLog">
                    <span style="color: #00ff00">> Sistema de monitoreo (MutationObserver) INICIADO.</span><br>
                    <span style="color: #aaaaaa">> Achica la pantalla o usa los botones de simulación para ver la actividad.</span><br><br>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const consola = document.getElementById('consolaLog');
    const btnEscanear = document.getElementById('btnEscanear');
    const tdProducto = document.getElementById('tdProducto');
    
    // Función para escribir en la consola virtual
    function log(msg, color = "#fff") {
        const time = new Date().toLocaleTimeString();
        consola.innerHTML += `<span style="color: #666">[${time}]</span> <span style="color: ${color}">${msg}</span><br>`;
        consola.scrollTop = consola.scrollHeight;
    }

    // ====================================================================
    // 1. EL VIGILANTE: MutationObserver (Atrapa cambios en tiempo real)
    // ====================================================================
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                const displayActual = window.getComputedStyle(mutation.target).display;
                if (displayActual === 'none') {
                    log(`🚨 ALERTA ROJA: Un script acaba de inyectar 'display: none' en un elemento!`, "#ff5555");
                } else {
                    log(`⚠️ PRECAUCIÓN: Un script modificó el atributo style del elemento.`, "#ffaa00");
                }
            }
            if (mutation.type === 'childList') {
                if (mutation.removedNodes.length > 0) {
                    log(`🚨 ALERTA ROJA: Un script acaba de BORRAR contenido (Nodos HTML) dentro del TD!`, "#ff5555");
                }
            }
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                log(`⚠️ PRECAUCIÓN: Un script modificó las clases de la fila.`, "#ffaa00");
            }
        });
    });

    // Empezamos a vigilar toda la fila y sus hijos
    const filaTest = document.getElementById('filaTest');
    if(filaTest) {
        observer.observe(filaTest, { 
            attributes: true, 
            childList: true, 
            subtree: true 
        });
    }

    // ====================================================================
    // 2. ESCÁNER MANUAL DE ESTADO
    // ====================================================================
    btnEscanear.addEventListener('click', function() {
        log("--- EJECUTANDO ESCÁNER MANUAL ---", "#00ffff");
        const spanNombre = document.getElementById('spanNombre');
        
        if (!document.body.contains(tdProducto)) {
            log("❌ RESULTADO: El contenedor <td> FUE ELIMINADO DEL DOM.", "#ff5555");
            return;
        }
        
        if (!spanNombre) {
            log("❌ RESULTADO: El texto del producto NO EXISTE. Fue limpiado por JS (.innerHTML = '').", "#ff5555");
            return;
        }

        const estilosTd = window.getComputedStyle(tdProducto);
        const estilosSpan = window.getComputedStyle(spanNombre);
        
        log(`Estado actual -> <td> display: ${estilosTd.display}, <span> display: ${estilosSpan.display}`);

        if (estilosTd.display === 'none' || estilosSpan.display === 'none') {
            log("❌ RESULTADO: El producto está ahí, pero oculto mediante CSS agresivo.", "#ff5555");
        } else {
            log("✅ RESULTADO: El DOM y el CSS están intactos. El producto es visible.", "#55ff55");
        }
    });

    // ====================================================================
    // 3. SIMULADORES DE ATAQUE (Para probar la herramienta)
    // ====================================================================
    document.getElementById('btnSimularOcultar').addEventListener('click', () => {
        log("Simulando acción de ERPTable: Ocultando vía inline CSS...", "#aaaaaa");
        document.getElementById('divContenedorNombre').style.display = 'none';
    });

    document.getElementById('btnSimularDestruir').addEventListener('click', () => {
        log("Simulando acción de ERPTable: Vaciando el innerHTML...", "#aaaaaa");
        tdProducto.innerHTML = '<span class="text-muted">Responsive view...</span>';
    });

    document.getElementById('btnRestaurar').addEventListener('click', () => {
        log("Restaurando DOM a su estado original...", "#55ff55");
        tdProducto.innerHTML = `
            <div class="d-flex align-items-center gap-2" id="divContenedorNombre">
                <span class="fw-bold text-wrap" id="spanNombre">BOTELLA PET 18 GR - AGUA BELÉN 625ML</span>
                <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold ms-1" style="font-size: 0.65rem;">PACK</span>
            </div>`;
    });

    // ====================================================================
    // 4. DETECTOR DE REDIMENSIÓN
    // ====================================================================
    let lastWidth = window.innerWidth;
    window.addEventListener('resize', () => {
        const currentWidth = window.innerWidth;
        if ((lastWidth > 767 && currentWidth <= 767) || (lastWidth <= 767 && currentWidth > 767)) {
            log(`[EVENTO] Breakpoint cruzado. Ancho actual: ${currentWidth}px`, "#00ffff");
        }
        lastWidth = currentWidth;
    });
});
</script>
<script src="ruta/a/tu/js/main.js"></script>
<script src="ruta/a/tu/js/tablas/renderizadores.js"></script>
<script src="ruta/a/tu/js/tablas/cards_acordeon.js"></script>
</body></html>