<?php
/**
 * 🔍 SUPER CHECKER V5 - El Veredicto Final
 * Diagnóstico de Filtros Tesorería
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

echo "<!DOCTYPE html><html><head><title>Debugger Tesorería V5</title>";
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">';
?>
<style>
    body { background: #f8f9fa; padding: 20px; font-family: 'Segoe UI', system-ui, sans-serif; }
    .terminal { background-color: #0d1117; color: #58a6ff; font-family: monospace; font-size: 13.5px; padding: 15px; border-radius: 8px; overflow-x: auto; border: 1px solid #30363d; }
    .terminal-comment { color: #8b949e; font-style: italic; }
    .terminal-keyword { color: #ff7b72; }
    .terminal-string { color: #a5d6ff; }
    .terminal-var { color: #79c0ff; }
</style>
</head><body>

<div class="container" style="max-width: 1200px;">
    <h2 class="text-primary mb-3 fw-bold"><i class="bi bi-radar"></i> DIAGNÓSTICO V5: El Veredicto Final</h2>
    <p class="lead text-muted">¿Por qué seguía sin funcionar? Aquí está el motivo exacto (Spoiler: el JS estaba enviando la petición al lugar equivocado) y la solución definitiva.</p>

    <div class="row g-4 mt-2">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 border-start border-danger border-4 mb-4">
                <div class="card-header bg-white fw-bold text-danger"><i class="bi bi-bug"></i> Los 2 Culpables Restantes</div>
                <div class="card-body p-4 bg-light">
                    <ol class="mb-0">
                        <li class="mb-3">
                            <strong>El Asesino Silencioso de Subcarpetas (JavaScript):</strong> En la versión anterior, el código <code>new URL(action, window.location.origin)</code> ignoraba tu carpeta <code>/sisadmin2/</code>. La petición de filtro se enviaba a <code>http://localhost/tesoreria...</code>, generando un Error 404 invisible. La tabla no se actualizaba porque nunca recibía datos nuevos.<br>
                            <span class="badge bg-success mt-2">Solución:</span> Usar la propiedad nativa <code>form.action</code>, que el navegador ya resuelve automáticamente con la ruta absoluta correcta.
                        </li>
                        <li class="mb-3">
                            <strong>Sintaxis MySQL Estricta (Modelo):</strong> En algunas versiones de MySQL/MariaDB, envolver subconsultas <code>UNION ALL</code> entre paréntesis <code>($sql) UNION ALL ($sql)</code> arroja un error de sintaxis silencioso al combinarlo con <code>ORDER BY</code>.<br>
                            <span class="badge bg-success mt-2">Solución:</span> Quitar los paréntesis al concatenar la consulta final.
                        </li>
                    </ol>
                </div>
            </div>

            <div class="alert alert-warning border-0 shadow-sm">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Recuerda borrar tu caché (Ctrl + F5)</strong> después de actualizar tu JavaScript.
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-tools"></i> Código de Solución Definitiva (Copia esto)</span>
                </div>
                <div class="card-body p-0">
                    <div class="accordion accordion-flush" id="accordionFixes">
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button bg-light fw-bold text-warning" type="button" data-bs-toggle="collapse" data-bs-target="#fix3">
                                    1. Actualizar JS (movimientos.js)
                                </button>
                            </h2>
                            <div id="fix3" class="accordion-collapse collapse show" data-bs-parent="#accordionFixes">
                                <div class="accordion-body">
                                    <p class="small text-muted mb-2">Reemplaza tu función <code>procesarFiltros</code> por esta versión infalible. Extrae la URL absoluta directamente del DOM:</p>
                                    <div class="terminal">
const procesarFiltros = () => {
    const formData = new FormData(formFiltros);
    
    <span class="terminal-comment">// Propiedad DOM nativa: obtiene la ruta ABSOLUTA garantizada</span>
    <span class="terminal-comment">// (ej: http://localhost/sisadmin2/index.php?ruta=...)</span>
    const urlObj = new URL(formFiltros.action);
    
    formData.forEach((value, key) => {
        if (value.trim() !== '') {
            urlObj.searchParams.set(key, value.trim());
        } else {
            urlObj.searchParams.delete(key); <span class="terminal-comment">// Limpia filtros vacíos</span>
        }
    });
    
    const finalUrlStr = urlObj.toString();
    console.log("📍 AJAX Enviado a:", finalUrlStr);
    
    cargarDatosAjax(finalUrlStr);
};
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-light fw-bold text-success" type="button" data-bs-toggle="collapse" data-bs-target="#fix2">
                                    2. Actualizar TesoreriaMovimientoModel.php
                                </button>
                            </h2>
                            <div id="fix2" class="accordion-collapse collapse" data-bs-parent="#accordionFixes">
                                <div class="accordion-body">
                                    <p class="small text-muted mb-2">Reemplaza la función <code>listarRecientes</code>. Hemos eliminado los paréntesis del UNION ALL para máxima compatibilidad:</p>
                                    <div class="terminal" style="max-height: 350px;">
<span class="terminal-keyword">public function</span> listarRecientes(array $filtros = [], int $limite = 50): array
{
    $origenFilter = strtoupper(trim((string) ($filtros['origen'] ?? '')));
    $idOrigenFilter = (int) ($filtros['id_origen'] ?? 0);
    $idTerceroFilter = (int) ($filtros['id_tercero'] ?? 0);
    $idCuentaFilter = (int) ($filtros['id_cuenta'] ?? 0);
    $fechaDesdeFilter = (string) ($filtros['fecha_desde'] ?? '');
    $fechaHastaFilter = (string) ($filtros['fecha_hasta'] ?? '');

    $paramsFinal = [];
    $whereMov = ['m.deleted_at IS NULL'];
    
    if (in_array($origenFilter, ['CXC', 'CXP'], true)) {
        $whereMov[] = 'm.origen = :origen_mov';
        $paramsFinal['origen_mov'] = $origenFilter;
    }
    if ($idOrigenFilter > 0) {
        $whereMov[] = 'm.id_origen = :id_origen_mov';
        $paramsFinal['id_origen_mov'] = $idOrigenFilter;
    }
    if ($idTerceroFilter > 0) {
        $whereMov[] = 'm.id_tercero = :id_tercero_mov';
        $paramsFinal['id_tercero_mov'] = $idTerceroFilter;
    }
    if ($idCuentaFilter > 0) {
        $whereMov[] = 'm.id_cuenta = :id_cuenta_mov';
        $paramsFinal['id_cuenta_mov'] = $idCuentaFilter;
    }
    if ($fechaDesdeFilter !== '') {
        $whereMov[] = 'm.fecha >= :fecha_desde_mov';
        $paramsFinal['fecha_desde_mov'] = $fechaDesdeFilter . ' 00:00:00';
    }
    if ($fechaHastaFilter !== '') {
        $whereMov[] = 'm.fecha <= :fecha_hasta_mov';
        $paramsFinal['fecha_hasta_mov'] = $fechaHastaFilter . ' 23:59:59';
    }

    $sqlMov = 'SELECT m.id, m.fecha, m.tipo, m.origen, m.id_origen, m.monto, m.estado, 
                      COALESCE(c.codigo, "S/C") AS cuenta_codigo, COALESCE(c.nombre, "Cuenta Eliminada") AS cuenta_nombre, 
                      COALESCE(t.nombre_completo, "Tercero Eliminado") AS tercero_nombre, m.created_at
               FROM tesoreria_movimientos m
               LEFT JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
               LEFT JOIN terceros t ON t.id = m.id_tercero
               WHERE ' . implode(' AND ', $whereMov);

    $addTransfers = true;
    if ($origenFilter !== '' && $origenFilter !== 'TRANSFERENCIA') $addTransfers = false;
    if ($idTerceroFilter > 0) $addTransfers = false;
    
    if ($addTransfers) {
        $whereTrfOut = ['trf.deleted_at IS NULL'];
        $whereTrfIn  = ['trf.deleted_at IS NULL'];

        if ($idOrigenFilter > 0) {
            $whereTrfOut[] = 'trf.id = :id_origen_out'; $paramsFinal['id_origen_out'] = $idOrigenFilter;
            $whereTrfIn[] = 'trf.id = :id_origen_in'; $paramsFinal['id_origen_in'] = $idOrigenFilter;
        }
        if ($idCuentaFilter > 0) {
            $whereTrfOut[] = 'trf.id_cuenta_origen = :id_cuenta_out'; $paramsFinal['id_cuenta_out'] = $idCuentaFilter;
            $whereTrfIn[] = 'trf.id_cuenta_destino = :id_cuenta_in'; $paramsFinal['id_cuenta_in'] = $idCuentaFilter;
        }
        if ($fechaDesdeFilter !== '') {
            $whereTrfOut[] = 'trf.fecha >= :fecha_desde_out'; $paramsFinal['fecha_desde_out'] = $fechaDesdeFilter . ' 00:00:00';
            $whereTrfIn[] = 'trf.fecha >= :fecha_desde_in'; $paramsFinal['fecha_desde_in'] = $fechaDesdeFilter . ' 00:00:00';
        }
        if ($fechaHastaFilter !== '') {
            $whereTrfOut[] = 'trf.fecha <= :fecha_hasta_out'; $paramsFinal['fecha_hasta_out'] = $fechaHastaFilter . ' 23:59:59';
            $whereTrfIn[] = 'trf.fecha <= :fecha_hasta_in'; $paramsFinal['fecha_hasta_in'] = $fechaHastaFilter . ' 23:59:59';
        }

        $sqlTrfOut = 'SELECT trf.id, trf.fecha, "PAGO" AS tipo, "TRANSFERENCIA" AS origen, trf.id AS id_origen, trf.monto, trf.estado, COALESCE(co.codigo, "S/C") AS cuenta_codigo, COALESCE(co.nombre, "Cuenta Eliminada") AS cuenta_nombre, "Cuentas Propias" AS tercero_nombre, trf.created_at FROM tesoreria_transferencias trf LEFT JOIN tesoreria_cuentas co ON co.id = trf.id_cuenta_origen WHERE ' . implode(' AND ', $whereTrfOut);
        $sqlTrfIn = 'SELECT trf.id, trf.fecha, "COBRO" AS tipo, "TRANSFERENCIA" AS origen, trf.id AS id_origen, trf.monto, trf.estado, COALESCE(cd.codigo, "S/C") AS cuenta_codigo, COALESCE(cd.nombre, "Cuenta Eliminada") AS cuenta_nombre, "Cuentas Propias" AS tercero_nombre, trf.created_at FROM tesoreria_transferencias trf LEFT JOIN tesoreria_cuentas cd ON cd.id = trf.id_cuenta_destino WHERE ' . implode(' AND ', $whereTrfIn);

        <span class="terminal-comment">// CORRECCIÓN: Quitamos los paréntesis alrededor de las subconsultas</span>
        $sqlFinal = $sqlMov . " UNION ALL " . $sqlTrfOut . " UNION ALL " . $sqlTrfIn . " ORDER BY fecha DESC, created_at DESC LIMIT :limite";
    } else {
        $sqlFinal = $sqlMov . " ORDER BY fecha DESC, created_at DESC LIMIT :limite";
    }

    $stmt = $this->db()->prepare($sqlFinal);
    foreach ($paramsFinal as $k => $v) { $stmt->bindValue(':' . $k, $v); }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>