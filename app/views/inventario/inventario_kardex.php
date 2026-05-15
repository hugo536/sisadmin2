<?php
$movimientos = $movimientos ?? [];
$items = $items ?? [];
$filtros = $filtros ?? [];

$tiposEntrada = ['INI', 'AJ+', 'COM', 'PROD'];
$tiposSalida = ['AJ-', 'CON', 'VEN'];

// =========================================================
// 🚀 MAGIA: CÁLCULO DINÁMICO DE SALDO
// Como la BD no tiene el saldo histórico, lo calculamos 
// matemáticamente (de más antiguo a más nuevo).
// =========================================================
$movsReversos = array_reverse($movimientos);
$saldoAcumulado = 0.0;

foreach ($movsReversos as &$m) {
    $tMov = strtoupper(trim((string) ($m['tipo_movimiento'] ?? '')));
    $cant = (float) ($m['cantidad'] ?? 0);
    
    if (in_array($tMov, $tiposEntrada, true)) {
        $saldoAcumulado += $cant;
    } elseif (in_array($tMov, $tiposSalida, true)) {
        $saldoAcumulado -= $cant;
    }
    // Nota: 'TRF' (Transferencias) no cambian el saldo global del ítem, 
    // por lo que no suman ni restan al total consolidado.

    // Guardamos el cálculo matemático en una variable temporal
    $m['saldo_calculado'] = $saldoAcumulado;
}
unset($m); // Limpiamos la referencia
// Volvemos a ordenar para mostrar el más reciente arriba
$movimientos = array_reverse($movsReversos);
// =========================================================
?>
<style>
    /* Ocultar las etiquetas de TomSelect dentro del input para usar "Filter Chips" externos */
    .ts-control > .item {
        display: none !important; 
    }
</style>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 fade-in kardex-sticky-header">
        <h1 class="h4 fw-bold mb-0 text-dark d-flex align-items-center">
            <i class="bi bi-journal-text me-2 text-primary"></i>Kardex de inventario
        </h1>
        <a class="btn btn-white border shadow-sm text-secondary fw-semibold" href="<?php echo e(route_url('inventario')); ?>">
            <i class="bi bi-arrow-left me-1 text-info"></i>Volver al Inventario
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="" id="kardexFiltrosForm">
                <input type="hidden" name="ruta" value="inventario/kardex">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold mb-1">Ítem</label>
                        <select class="form-select bg-light border-secondary-subtle shadow-sm" name="id_item[]" id="kardexItemSelect" multiple>
                            <option value="">Todos</option>
                            <?php 
                                // Aseguramos que los filtros sean un array para poder buscar en ellos
                                $itemsSeleccionados = (array) ($filtros['id_item'] ?? []);
                                
                                foreach ($items as $item): 
                                    $itemId = (int) ($item['id'] ?? 0);
                                    // Verificamos si el ID actual está dentro de la lista de seleccionados
                                    $isSelected = in_array($itemId, $itemsSeleccionados, true) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $itemId; ?>" <?php echo $isSelected; ?>>
                                    <?php echo e((string) ($item['nombre'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted fw-bold mb-1">Desde</label>
                        <input class="form-control bg-light border-secondary-subtle shadow-sm kardex-auto-submit" type="date" name="fecha_desde" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted fw-bold mb-1">Hasta</label>
                        <input class="form-control bg-light border-secondary-subtle shadow-sm kardex-auto-submit" type="date" name="fecha_hasta" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>">
                    </div>
                </div>
                
                <div id="kardexChipsContainer" class="mt-3 d-flex flex-wrap gap-2"></div>
                
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="contenedorResultadosKardex">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Movimientos Registrados</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?php echo count($movimientos); ?> Resultados</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchKardex" placeholder="Buscar en resultados...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-pro align-middle mb-0" id="tablaKardex"
                       data-erp-table="true"
                       data-rows-selector="#kardexTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchKardex"
                       data-empty-text="No se encontraron movimientos"
                       data-info-text-template="Mostrando {start} a {end} de {total} movimientos"
                       data-pagination-controls="#kardexPaginationControls"
                       data-pagination-info="#kardexPaginationInfo">
                    <thead class="border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold" style="width: 12%;">Fecha</th>
                            <th class="text-secondary fw-semibold" style="width: 8%;">Tipo</th>
                            <th class="text-secondary fw-semibold">Ítem</th>
                            <th class="text-secondary fw-semibold" style="width: 15%;">Ubicación</th>
                            <th class="text-end text-secondary fw-semibold" style="width: 11%;">Cantidad</th>
                            <th class="text-end text-secondary fw-semibold" style="width: 11%;">Saldo</th>
                            <th class="text-secondary fw-semibold ps-3" style="width: 12%;">Usuario</th>
                            <th class="pe-4 text-secondary fw-semibold" style="width: 15%;">Referencia</th>
                        </tr>
                    </thead>
                    <tbody id="kardexTableBody">
                    <?php if (empty($movimientos)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 text-light"></i>
                                Sin movimientos para los filtros seleccionados.
                            </td>
                        </tr>
                    <?php else: foreach ($movimientos as $mov): ?>
                        <?php 
                            $tipoMov = strtoupper(trim((string) ($mov['tipo_movimiento'] ?? ''))); 
                            
                            $fechaDocumento = trim((string) ($mov['fecha_documento'] ?? ''));
                            $fechaCreacion = trim((string) ($mov['created_at'] ?? ''));
                            $fechaMostrar = !empty($fechaDocumento) ? $fechaDocumento : $fechaCreacion;
                            
                            $referenciaBruta = (string) ($mov['referencia'] ?? '-');
                            $terceroNombre = trim((string) ($mov['tercero_nombre'] ?? ''));
                            $terceroTipo = strtoupper(trim((string) ($mov['tercero_tipo'] ?? 'CLIENTE')));
                            
                            if ($tipoMov === 'VEN' && $terceroNombre !== '') {
                                $yaIncluyeDestino = stripos($referenciaBruta, 'Cliente:') !== false || stripos($referenciaBruta, 'Distribuidor:') !== false;
                                if (!$yaIncluyeDestino) {
                                    $etiqueta = $terceroTipo === 'DISTRIBUIDOR' ? 'Distribuidor' : 'Cliente';
                                    $referenciaBruta .= ' | ' . $etiqueta . ': ' . $terceroNombre;
                                }
                            }

                            $refParts = array_map('trim', explode('|', $referenciaBruta));
                            $mainRef = array_shift($refParts) ?: '-'; 
                            
                            $refParts = array_filter($refParts, function($part) {
                                return !preg_match('/(?:Ingreso|Egreso):\s*[\d.]+\s*[A-Z]+/i', $part);
                            });
                            
                            $subRef = !empty($refParts) ? implode(' <span class="mx-1 text-light">|</span> ', $refParts) : '';

                            $origen = trim((string) ($mov['almacen_origen'] ?? ''));
                            $destino = trim((string) ($mov['almacen_destino'] ?? ''));
                            $ubicacionHtml = '<span class="text-muted opacity-50">-</span>';
                            
                            if ($origen !== '' && $destino !== '') {
                                $ubicacionHtml = "<div class='text-nowrap'><i class='bi bi-shop small text-muted me-1'></i>{$origen}<br><i class='bi bi-arrow-return-right small text-primary me-1'></i>{$destino}</div>";
                            } elseif ($origen !== '') {
                                $ubicacionHtml = "<i class='bi bi-box-arrow-up text-danger me-1' title='Origen'></i>{$origen}";
                            } elseif ($destino !== '') {
                                $ubicacionHtml = "<i class='bi bi-box-arrow-in-down text-success me-1' title='Destino'></i>{$destino}";
                            }

                            $searchStr = strtolower($fechaMostrar . ' ' . $tipoMov . ' ' . ($mov['item_nombre'] ?? '') . ' ' . $origen . ' ' . $destino . ' ' . ($mov['usuario'] ?? '') . ' ' . $referenciaBruta . ' ' . $terceroNombre);
                        ?>
                        <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                            <td data-label="Fecha" class="ps-4 text-muted align-top pt-3">
                                <i class="bi bi-clock small me-1 opacity-50"></i><?php echo e($fechaMostrar); ?>
                            </td>
                            <td data-label="Tipo" class="align-top pt-3 text-center">
                                <?php if (in_array($tipoMov, $tiposEntrada, true)): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">ENT - <?php echo e($tipoMov); ?></span>
                                <?php elseif (in_array($tipoMov, $tiposSalida, true)): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">SAL - <?php echo e($tipoMov); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><?php echo e($tipoMov); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Ítem" class="fw-bold text-dark align-top pt-3">
                                <?php echo e((string) ($mov['item_nombre'] ?? '')); ?>
                            </td>
                            <td data-label="Ubicación" class="align-top pt-3">
                                <?php echo $ubicacionHtml; ?>
                            </td>
                            
                            <?php
                                // --- LÓGICA DE LIMPIEZA DE DECIMALES Y COLORES ---
                                $cantidadBase = (float) ($mov['cantidad'] ?? 0);
                                $saldoBase = (float) ($mov['saldo_calculado'] ?? 0); // <-- Llamamos al saldo dinámico de PHP

                                // Función anónima para formatear: Quita ".0000" pero mantiene decimales reales si existen.
                                $formatQty = function($val) {
                                    return (floor($val) == $val) ? number_format($val, 0, '.', ',') : rtrim(rtrim(number_format($val, 4, '.', ','), '0'), '.');
                                };

                                $cantidadPrincipal = $formatQty($cantidadBase);
                                $saldoPrincipal = $formatQty($saldoBase);

                                // Asignar color y signo a la cantidad
                                $colorCantidad = 'text-dark';
                                $signoCantidad = '';
                                if (in_array($tipoMov, $tiposEntrada, true)) {
                                    $colorCantidad = 'text-success';
                                    $signoCantidad = '+ ';
                                } elseif (in_array($tipoMov, $tiposSalida, true)) {
                                    $colorCantidad = 'text-danger';
                                    $signoCantidad = '- ';
                                }

                                $detalleConversion = '';
                                if (preg_match('/Conv:\s*([^|]+)/i', $referenciaBruta, $matchConv)) {
                                    $detalleConversion = trim((string) ($matchConv[1] ?? ''));
                                }
                            ?>
                            
                            <td data-label="Cantidad" class="text-end fw-bold align-top pt-3 <?php echo $colorCantidad; ?>" style="font-size: 1.05rem;">
                                <?php if ($detalleConversion !== ''): ?>
                                    <div class="text-dark fw-medium" style="font-size: 0.8rem;"><?php echo e($detalleConversion); ?></div>
                                    <div><?php echo $signoCantidad . e($cantidadPrincipal); ?></div>
                                <?php else: ?>
                                    <?php echo $signoCantidad . e($cantidadPrincipal); ?>
                                <?php endif; ?>
                            </td>
                            
                            <td data-label="Saldo" class="text-end fw-bold text-dark align-top pt-3 bg-light bg-opacity-50" style="font-size: 1.05rem;">
                                <?php echo e($saldoPrincipal); ?>
                            </td>

                            <td data-label="Usuario" class="align-top pt-3 ps-3 text-secondary small">
                                <i class="bi bi-person-circle me-1 opacity-50"></i><?php echo e((string) ($mov['usuario'] ?? '-')); ?>
                            </td>
                            <td data-label="Referencia" class="pe-4 align-top pt-3">
                                <div class="fw-medium text-dark small"><?php echo e($mainRef); ?></div>
                                <?php if ($subRef !== ''): ?>
                                    <div class="small text-muted" style="font-size: 0.8em;"><?php echo $subRef; ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($movimientos)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="kardexPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación de kardex">
                    <ul class="pagination mb-0 shadow-sm" id="kardexPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<script src="<?php echo e(asset_url('js/inventario/inventario_kardex.js')); ?>"></script>