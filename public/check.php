<?php
/**
 * 🔍 SUPER CHECKER - Diagnóstico de Lógica de Devoluciones (Switch Reemplazo)
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

echo "<!DOCTYPE html><html><head><title>Debugger Devoluciones</title>";
// Cargamos Bootstrap y Bootstrap Icons tal cual los usas en tu sistema
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">';
echo "</head><body style='background: #f8f9fa; padding: 30px;'>";

echo "<div class='container' style='max-width: 800px;'>";
echo "<h1 class='text-primary mb-4'>🕵️‍♂️ DIAGNÓSTICO: Lógica del Switch de Reemplazo</h1>";
echo "<p class='lead'>Este es un clon exacto de la parte del modal de devoluciones que nos está dando problemas.</p>";

// ==========================================
// SIMULACIÓN DEL HTML DE TU MODAL
// ==========================================
?>
<div class="card shadow">
    <div class="card-header bg-warning text-dark fw-bold">
        Simulación: Registrar Devolución de Venta
    </div>
    <div class="card-body">
        
        <div class="row mb-4 g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">Motivo de Devolución <span class="text-danger">*</span></label>
                <select id="devolucionVentaMotivo" class="form-select border-warning-subtle" required>
                    <option value="">Seleccione un motivo...</option>
                    <optgroup label="📦 Restaura al Inventario Vendible">
                        <option value="producto_incorrecto">Producto incorrecto entregado</option>
                        <option value="error_despacho">Error de despacho / cantidad excedente</option>
                        <option value="cliente_rechaza">Cliente rechaza pedido (Packs sellados e intactos)</option>
                    </optgroup>
                    <optgroup label="⚠️ Descuenta o Va a Cuarentena / Mermas">
                        <option value="producto_defectuoso">Producto defectuoso, roto o dañado</option>
                    </optgroup>
                </select>
                <small id="devolucionVentaMotivoHint" class="text-muted d-block mt-1">Selecciona un motivo...</small>
            </div>
            
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">Resolución Comercial</label>
                <select id="devolucionVentaResolucion" class="form-select border-warning-subtle">
                    <option value="descuento_cxc">Reducción / Anulación de Deuda</option>
                </select>
            </div>
        </div>

        <div class="row mb-4" id="filaSwitchReemplazo">
            <div class="col-12">
                <div class="form-check form-switch bg-white border rounded-3 p-3 d-flex align-items-center shadow-sm">
                    <input class="form-check-input ms-0 me-3" type="checkbox" id="devolucionEnviarReemplazo" checked style="cursor: pointer; transform: scale(1.3); margin-top: 0;">
                    <div>
                        <label class="form-check-label fw-bold text-dark d-block" for="devolucionEnviarReemplazo" style="cursor: pointer;">
                            Enviar mercadería de reemplazo (Cambio / Garantía)
                        </label>
                        <small class="text-muted">
                            El pedido volverá a estado "Pendiente" para despachar los productos de reemplazo.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-dark mt-4">
            <h6 class="fw-bold"><i class="bi bi-terminal"></i> Consola de Diagnóstico JS</h6>
            <pre id="consolaLog" class="mb-0 text-success" style="font-size: 12px;"></pre>
        </div>

    </div>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Diccionario de configuración
    const DEVOLUCION_VENTA_MOTIVOS = {
        producto_incorrecto: { hint: 'La mercadería regresa al stock vendible.' },
        error_despacho: { hint: 'La devolución corrige la salida y repone stock vendible.' },
        cliente_rechaza: { hint: 'La mercadería vuelve al stock vendible si está sellada e intacta.' },
        producto_defectuoso: { hint: 'No reingresa a stock vendible (cuarentena/merma).' },
    };

    // Variables del DOM
    const devolucionVentaMotivo = document.getElementById('devolucionVentaMotivo');
    const devolucionVentaMotivoHint = document.getElementById('devolucionVentaMotivoHint');
    const checkReemplazo = document.getElementById('devolucionEnviarReemplazo');
    const filaSwitchReemplazo = document.getElementById('filaSwitchReemplazo');
    const consolaLog = document.getElementById('consolaLog');

    function log(msg) {
        consolaLog.textContent += msg + '\n';
        console.log(msg);
    }

    log("Sistema iniciado. Esperando interacciones del usuario...");

    // La función estrella
    function actualizarHintDevolucionVenta() {
        const motivoActual = devolucionVentaMotivo.value;
        log(`\n▶️ Evento 'change' detectado. Motivo seleccionado: "${motivoActual}"`);
        
        // 1. Hint
        const motivoCfg = DEVOLUCION_VENTA_MOTIVOS[motivoActual];
        if (devolucionVentaMotivoHint) {
            devolucionVentaMotivoHint.textContent = motivoCfg ? motivoCfg.hint : 'Selecciona un motivo...';
        }

        // 2. Lógica del Switch
        if (!filaSwitchReemplazo || !checkReemplazo) {
            log("❌ ERROR: No se encontraron en el DOM los elementos del Switch.");
            return;
        }

        if (motivoActual === 'producto_incorrecto' || motivoActual === 'producto_defectuoso') {
            log("✅ Acción: Mostrando el switch de reemplazo (Aplica garantía/cambio)");
            
            // LA CORRECCIÓN SUPREMA DE BOOTSTRAP:
            // Removemos 'd-none' para devolverle el control a Bootstrap
            filaSwitchReemplazo.classList.remove('d-none');
            
        } else {
            log("🚫 Acción: Ocultando el switch de reemplazo y desmarcándolo (No aplica reemplazo)");
            
            // LA CORRECCIÓN SUPREMA DE BOOTSTRAP:
            // Añadimos 'd-none' (clase nativa de BS) que oculta el elemento con !important
            filaSwitchReemplazo.classList.add('d-none');
            checkReemplazo.checked = false;
        }
    }

    // Escuchador de eventos
    devolucionVentaMotivo.addEventListener('change', actualizarHintDevolucionVenta);
    
    // Llamada inicial para limpiar el estado al cargar
    actualizarHintDevolucionVenta();
});
</script>
</body></html>