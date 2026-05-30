<?php 

    $filtros = $filtros ?? [];
    // 1. OBTENCIÓN DE SECCIÓN ACTIVA
    $seccionActiva = $_GET['seccion_activa'] ?? ($filtros['seccion_activa'] ?? 'stock'); 

    // 2. FUNCIONES AUXILIARES (HELPERS) PARA LIMPIAR LA VISTA
    $formatCurrency = function($value, $decimals = 2) {
        return 'S/ ' . number_format((float)($value ?? 0), $decimals, '.', ',');
    };

    $formatStock = function($value, $permiteDecimales = false) {
        $decimals = $permiteDecimales ? 3 : 0;
        return number_format((float)($value ?? 0), $decimals, '.', ',');
    };

    $getAlertClassStock = function($alertaTexto, &$esCritico) {
        $alerta = mb_strtolower((string)$alertaTexto);
        $esCritico = stripos($alerta, 'bajo') !== false || stripos($alerta, 'crític') !== false || stripos($alerta, 'critico') !== false || stripos($alerta, 'agotado') !== false || stripos($alerta, 'vencido') !== false;
        return $esCritico ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-light text-secondary border-secondary-subtle';
    };

    // 3. PRE-PROCESAMIENTO DE FILTROS PARA DROPDOWNS
    // Categorías
    $catSeleccionadas = is_array($filtros['id_categoria'] ?? []) ? $filtros['id_categoria'] : (!empty($filtros['id_categoria']) ? [$filtros['id_categoria']] : []);
    $catCount = count($catSeleccionadas);
    $txtCat = $catCount === 0 ? 'Todas las categorías' : ($catCount === 1 ? '1 seleccionada' : $catCount . ' seleccionadas');

    // Tipos de Ítem
    $tiposSeleccionados = is_array($filtros['tipo_item'] ?? []) ? $filtros['tipo_item'] : (is_string($filtros['tipo_item'] ?? '') && $filtros['tipo_item'] !== '' ? [$filtros['tipo_item']] : []);
    $opcionesTipos = ['producto_terminado' => 'Producto terminado', 'materia_prima' => 'Materia prima', 'insumo' => 'Insumo', 'semielaborado' => 'Semielaborado', 'material_empaque' => 'Material de empaque', 'servicio' => 'Servicio'];
    $tipoCount = count($tiposSeleccionados);
    $txtTipo = $tipoCount === 0 ? 'Todos los tipos' : ($tipoCount === 1 ? $opcionesTipos[$tiposSeleccionados[0]] ?? '1 seleccionado' : $tipoCount . ' seleccionados');

    // Almacenes
    $almSeleccionados = is_array($filtros['id_almacen'] ?? []) ? $filtros['id_almacen'] : (!empty($filtros['id_almacen']) ? [$filtros['id_almacen']] : []);
    $almCount = count($almSeleccionados);
    $txtAlm = $almCount === 0 ? 'Todos los almacenes' : ($almCount === 1 ? '1 seleccionado' : $almCount . ' seleccionados');

    // Alertas (Filtro múltiple)
    $rawAlertas = $filtros['alertas'] ?? [];
    $alertasSeleccionadas = is_array($rawAlertas) ? $rawAlertas : (trim((string)$rawAlertas) !== '' ? [$rawAlertas] : []);
    $opcionesAlertas = [
        'disponible' => 'Disponible (Verde)',
        'próximo_a_vencer' => 'Próximo a Vencer (Amarillo)',
        'bajo_mínimo' => 'Bajo Mínimo (Amarillo)',
        'agotado' => 'Agotado (Rojo)',
        'vencido' => 'Vencido (Rojo)',
        'sin_movimientos' => 'Sin Movimientos (Gris)'
    ];
    $alertasCount = count($alertasSeleccionadas);
    $txtAlerta = $alertasCount === 0 ? 'Todas las alertas' : ($alertasCount === 1 ? $opcionesAlertas[$alertasSeleccionadas[0]] ?? '1 seleccionada' : $alertasCount . ' seleccionadas');

    // 4. FILTRADO INTERNO DE ALERTAS EN LA VISTA
    if ($seccionActiva === 'stock' && !empty($alertasSeleccionadas) && !empty($stock['rows'])) {
        $filtroResult = [];
        $nuevoValorTotal = 0;
        foreach ($stock['rows'] as $r) {
            $alertaRaw = mb_strtolower((string)($r['alerta'] ?? ''));
            $estado = 'disponible'; 
            
            if (stripos($alertaRaw, 'bajo') !== false || stripos($alertaRaw, 'crític') !== false) {
                $estado = 'bajo_mínimo';
            } elseif (stripos($alertaRaw, 'vencido') !== false) {
                $estado = 'vencido';
            } elseif (stripos($alertaRaw, 'agotado') !== false) {
                $estado = 'agotado';
            } elseif (stripos($alertaRaw, 'próximo') !== false || stripos($alertaRaw, 'proximo') !== false) {
                $estado = 'próximo_a_vencer';
            } elseif (stripos($alertaRaw, 'sin mov') !== false) {
                $estado = 'sin_movimientos';
            }
            
            if (in_array($estado, $alertasSeleccionadas)) {
                $filtroResult[] = $r;
                $nuevoValorTotal += (float)($r['valor_total'] ?? 0);
            }
        }
        $stock['rows'] = $filtroResult;
        $stock['valor_total'] = $nuevoValorTotal;
    }
?>

<div class="container-fluid p-4" id="reportesInventarioApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Reportes de Inventario
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de stock, movimientos valorizados y control de lotes.</p>
        </div>
        <a href="javascript:history.back()" class="btn btn-outline-secondary shadow-sm fw-semibold">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4 rounded">
        <div class="card-body p-4 bg-white">
            <form id="formFiltrosInventario" class="row g-3 align-items-end" method="get" action="<?php echo e(route_url('reportes/inventario')); ?>">
                <input type="hidden" name="ruta" value="reportes/inventario">
                <input type="hidden" name="seccion_activa" id="input_seccion_activa" value="<?php echo e($seccionActiva); ?>">
                
                <input type="hidden" name="busqueda" id="hidden_busqueda" value="<?php echo e($_GET['busqueda'] ?? ''); ?>">

                <?php if (in_array('bajo_mínimo', $alertasSeleccionadas)): ?>
                    <input type="hidden" name="solo_bajo_minimo" value="1">
                <?php endif; ?>

                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Categoría</label>
                    <div class="dropdown dropdown-multi">
                        <button class="btn bg-light border border-secondary-subtle w-100 text-start d-flex justify-content-between align-items-center shadow-none" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height: 38px;">
                            <span class="text-truncate text-dark" style="font-size: 0.95rem;"><?php echo $txtCat; ?></span>
                            <i class="bi bi-chevron-down text-muted small"></i>
                        </button>
                        <ul class="dropdown-menu w-100 shadow p-2" style="max-height: 300px; overflow-y: auto;">
                            <li>
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input cursor-pointer shadow-none border-primary chk-todos" type="checkbox" id="chk_todos_cat">
                                    <label class="form-check-label w-100 cursor-pointer text-primary fw-bold" for="chk_todos_cat" style="font-size: 0.9rem;">Seleccionar Todas</label>
                                </div>
                            </li>
                            <?php foreach (($categorias ?? []) as $cat): ?>
                            <li>
                                <div class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer shadow-none border-secondary-subtle chk-item" type="checkbox" name="id_categoria[]" value="<?php echo (int)$cat['id']; ?>" id="chk_cat_<?php echo $cat['id']; ?>" <?php echo in_array($cat['id'], $catSeleccionadas) ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100 cursor-pointer text-dark" for="chk_cat_<?php echo $cat['id']; ?>" style="font-size: 0.9rem;">
                                        <?php echo e((string)$cat['nombre']); ?>
                                    </label>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider mt-1 mb-2"></li>
                            <li><button type="button" class="btn btn-primary btn-sm w-100 fw-bold" onclick="document.getElementById('formFiltrosInventario').submit();">Aplicar Filtro</button></li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Tipo de Ítem</label>
                    <div class="dropdown dropdown-multi">
                        <button class="btn bg-light border border-secondary-subtle w-100 text-start d-flex justify-content-between align-items-center shadow-none" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height: 38px;">
                            <span class="text-truncate text-dark" style="font-size: 0.95rem;"><?php echo $txtTipo; ?></span>
                            <i class="bi bi-chevron-down text-muted small"></i>
                        </button>
                        <ul class="dropdown-menu w-100 shadow p-2" style="max-height: 300px; overflow-y: auto;">
                            <li>
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input cursor-pointer shadow-none border-primary chk-todos" type="checkbox" id="chk_todos_tipos">
                                    <label class="form-check-label w-100 cursor-pointer text-primary fw-bold" for="chk_todos_tipos" style="font-size: 0.9rem;">Seleccionar Todos</label>
                                </div>
                            </li>
                            <?php foreach($opcionesTipos as $valor => $etiqueta): ?>
                            <li>
                                <div class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer shadow-none border-secondary-subtle chk-item" type="checkbox" name="tipo_item[]" value="<?php echo $valor; ?>" id="chk_tipo_<?php echo $valor; ?>" <?php echo in_array($valor, $tiposSeleccionados) ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100 cursor-pointer text-dark" for="chk_tipo_<?php echo $valor; ?>" style="font-size: 0.9rem;">
                                        <?php echo $etiqueta; ?>
                                    </label>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider mt-1 mb-2"></li>
                            <li><button type="button" class="btn btn-primary btn-sm w-100 fw-bold" onclick="document.getElementById('formFiltrosInventario').submit();">Aplicar Filtro</button></li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Almacén</label>
                    <div class="dropdown dropdown-multi">
                        <button class="btn bg-light border border-secondary-subtle w-100 text-start d-flex justify-content-between align-items-center shadow-none" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height: 38px;">
                            <span class="text-truncate text-dark" style="font-size: 0.95rem;"><?php echo $txtAlm; ?></span>
                            <i class="bi bi-chevron-down text-muted small"></i>
                        </button>
                        <ul class="dropdown-menu w-100 shadow p-2" style="max-height: 300px; overflow-y: auto;">
                            <li>
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input cursor-pointer shadow-none border-primary chk-todos" type="checkbox" id="chk_todos_alm">
                                    <label class="form-check-label w-100 cursor-pointer text-primary fw-bold" for="chk_todos_alm" style="font-size: 0.9rem;">Seleccionar Todos</label>
                                </div>
                            </li>
                            <?php foreach (($almacenes ?? []) as $almacen): ?>
                            <li>
                                <div class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer shadow-none border-secondary-subtle chk-item" type="checkbox" name="id_almacen[]" value="<?php echo (int)$almacen['id']; ?>" id="chk_alm_<?php echo $almacen['id']; ?>" <?php echo in_array($almacen['id'], $almSeleccionados) ? 'checked' : ''; ?>>
                                    <label class="form-check-label w-100 cursor-pointer text-dark" for="chk_alm_<?php echo $almacen['id']; ?>" style="font-size: 0.9rem;">
                                        <?php echo e((string)$almacen['nombre']); ?>
                                    </label>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider mt-1 mb-2"></li>
                            <li><button type="button" class="btn btn-primary btn-sm w-100 fw-bold" onclick="document.getElementById('formFiltrosInventario').submit();">Aplicar Filtro</button></li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <?php if ($seccionActiva === 'stock'): ?>
                        <label class="form-label text-muted small fw-bold mb-1">Estado / Alerta</label>
                        <div class="dropdown dropdown-multi">
                            <button class="btn bg-light border border-secondary-subtle w-100 text-start d-flex justify-content-between align-items-center shadow-none" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height: 38px;">
                                <span class="text-truncate text-dark" style="font-size: 0.95rem;"><?php echo $txtAlerta; ?></span>
                                <i class="bi bi-chevron-down text-muted small"></i>
                            </button>
                            <ul class="dropdown-menu w-100 shadow p-2" style="max-height: 300px; overflow-y: auto;">
                                <li>
                                    <div class="form-check mb-2 pb-2 border-bottom">
                                        <input class="form-check-input cursor-pointer shadow-none border-primary chk-todos" type="checkbox" id="chk_todos_alertas">
                                        <label class="form-check-label w-100 cursor-pointer text-primary fw-bold" for="chk_todos_alertas" style="font-size: 0.9rem;">Seleccionar Todas</label>
                                    </div>
                                </li>
                                <?php foreach($opcionesAlertas as $valor => $etiqueta): ?>
                                <li>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input cursor-pointer shadow-none border-secondary-subtle chk-item" type="checkbox" name="alertas[]" value="<?php echo $valor; ?>" id="chk_alerta_<?php echo $valor; ?>" <?php echo in_array($valor, $alertasSeleccionadas) ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100 cursor-pointer text-dark" for="chk_alerta_<?php echo $valor; ?>" style="font-size: 0.9rem;">
                                            <?php echo $etiqueta; ?>
                                        </label>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider mt-1 mb-2"></li>
                                <li><button type="button" class="btn btn-primary btn-sm w-100 fw-bold" onclick="document.getElementById('formFiltrosInventario').submit();">Aplicar Filtro</button></li>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'historico'): ?>
                        <div class="w-100">
                            <label class="form-label text-muted small fw-bold mb-1">Fecha de Corte</label>
                            <input type="datetime-local" id="fecha_corte" name="fecha_corte" class="form-control bg-light border-primary-subtle auto-submit" value="<?php echo e($filtros['fecha_corte'] ?? date('Y-m-d\TH:i')); ?>" required style="height: 38px;">
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'kardex'): ?>
                        <div class="d-flex gap-2 w-100">
                            <div class="w-50">
                                <label class="form-label text-muted small fw-bold mb-1">Desde</label>
                                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required style="height: 38px;">
                            </div>
                            <div class="w-50">
                                <label class="form-label text-muted small fw-bold mb-1">Hasta</label>
                                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required style="height: 38px;">
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'vencimientos'): ?>
                        <div class="w-100">
                            <label class="form-label text-muted small fw-bold mb-1">Filtro de Alerta</label>
                            <select name="situacion_alerta" class="form-select bg-light border-warning-subtle auto-submit" style="height: 38px;">
                                <option value="">Todas las alertas</option>
                                <option value="disponible" <?php echo (($filtros['situacion_alerta'] ?? '') === 'disponible') ? 'selected' : ''; ?>>Disponible</option>
                                <option value="proximo_a_vencer" <?php echo (($filtros['situacion_alerta'] ?? '') === 'proximo_a_vencer') ? 'selected' : ''; ?>>Próximo a vencer</option>
                                <option value="vencido" <?php echo (($filtros['situacion_alerta'] ?? '') === 'vencido') ? 'selected' : ''; ?>>Vencido</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs border-bottom-1 mb-4 px-2" role="tablist">
        <?php 
            $tabs = [
                'stock' => ['icono' => 'bi-layers-half', 'texto' => 'Stock Actual'],
                'historico' => ['icono' => 'bi-clock-history', 'texto' => 'Stock a Fecha'],
                'kardex' => ['icono' => 'bi-journal-check', 'texto' => 'Kardex Valorizado'],
                'vencimientos' => ['icono' => 'bi-calendar2-x', 'texto' => 'Lotes y Vencimientos']
            ];
            foreach($tabs as $key => $tab):
                $activeClass = $seccionActiva === $key ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0';
        ?>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $activeClass; ?>" data-seccion="<?php echo $key; ?>">
                    <i class="bi <?php echo $tab['icono']; ?> me-2"></i><?php echo $tab['texto']; ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($seccionActiva === 'stock'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <h5 class="mb-0 fw-bold text-dark text-nowrap"><i class="bi bi-layers-half me-2 text-info"></i>Detalle de Inventario</h5>
                <div class="d-flex gap-2 align-items-center w-100" style="max-width: 450px;">
                    <button type="submit" form="formFiltrosInventario" name="exportar_pdf" value="1" class="btn btn-sm btn-danger shadow-sm fw-bold px-3 d-flex align-items-center text-nowrap" formtarget="_blank" title="Exportar a PDF" onclick="document.getElementById('hidden_busqueda').value = document.getElementById('filtroRepStock').value;">
                        <i class="bi bi-file-pdf-fill"></i><span class="ms-1 d-none d-sm-inline">PDF</span>
                    </button>
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepStock" placeholder="Buscar ítem o almacén..." value="<?php echo e($_GET['busqueda'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaRepStock" data-erp-table="true" data-search-input="#filtroRepStock" data-rows-per-page="10">
                        <thead>
                            <tr>
                                <th class="ps-4 text-secondary fw-semibold">Ítem</th>
                                <th class="text-secondary fw-semibold">Almacén</th>
                                <th class="text-end text-secondary fw-semibold">Stock</th>
                                <th class="text-end text-secondary fw-semibold">C/U</th>
                                <th class="text-end text-secondary fw-semibold">Valor</th>
                                <th class="text-end text-secondary fw-semibold">Mínimo</th>
                                <th class="text-center text-secondary fw-semibold">Unidad</th>
                                <th class="text-center pe-4 text-secondary fw-semibold">Alerta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($stock['rows'])): ?>
                                <tr class="empty-msg-row"><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de stock.</td></tr>
                            <?php else: ?>
                                <?php foreach (($stock['rows'] ?? []) as $r): 
                                    $usaDecimales = (int)($r['permite_decimales'] ?? 0) === 1;
                                    $esCritico = false;
                                    $alertaClase = $getAlertClassStock($r['alerta'] ?? '', $esCritico);
                                ?>
                                    <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['item'] . ' ' . (string)$r['almacen'])); ?>">
                                        <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['item']); ?></td>
                                        <td class="text-muted"><?php echo e((string)$r['almacen']); ?></td>
                                        <td class="text-end fw-bold <?php echo $esCritico ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo $formatStock($r['stock_actual'], $usaDecimales); ?>
                                        </td>
                                        <td class="text-end text-muted"><?php echo $formatCurrency($r['costo_unitario'], 4); ?></td>
                                        <td class="text-end fw-semibold text-dark"><?php echo $formatCurrency($r['valor_total'], 2); ?></td>
                                        <td class="text-end text-muted"><?php echo $formatStock($r['stock_minimo'], $usaDecimales); ?></td>
                                        <td class="text-center"><span class="badge bg-light text-secondary border"><?php echo e((string)$r['unidad']); ?></span></td>
                                        <td class="text-center pe-4">
                                            <span class="badge px-2 py-1 rounded border <?php echo $alertaClase; ?>">
                                                <?php echo e(!empty($r['alerta']) ? (string)$r['alerta'] : 'OK'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaRepStockPaginationInfo">
                        Cargando...
                        <?php if (!empty($stock['valor_total'])): ?>
                            <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle px-2">
                                Valor total: <?php echo $formatCurrency($stock['valor_total']); ?>
                            </span>
                        <?php endif; ?>
                    </small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepStockPaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'historico'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <h5 class="mb-0 fw-bold text-dark text-nowrap"><i class="bi bi-clock-history me-2 text-primary"></i>Stock a la Fecha Seleccionada</h5>
                <div class="d-flex gap-2 align-items-center w-100" style="max-width: 450px;">
                    <button type="submit" form="formFiltrosInventario" name="exportar_pdf" value="1" class="btn btn-sm btn-danger shadow-sm fw-bold px-3 d-flex align-items-center text-nowrap" formtarget="_blank" title="Exportar a PDF" onclick="document.getElementById('hidden_busqueda').value = document.getElementById('filtroRepHistorico').value;">
                        <i class="bi bi-file-pdf-fill"></i><span class="ms-1 d-none d-sm-inline">PDF</span>
                    </button>
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepHistorico" placeholder="Buscar ítem..." value="<?php echo e($_GET['busqueda'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaRepHistorico" data-erp-table="true" data-search-input="#filtroRepHistorico" data-rows-per-page="10">
                        <thead>
                            <tr>
                                <th class="ps-4 text-secondary fw-semibold">Ítem</th>
                                <th class="text-secondary fw-semibold">Almacén</th>
                                <th class="text-end text-secondary fw-semibold">Stock Calculado</th>
                                <th class="text-end text-secondary fw-semibold">C/U</th>
                                <th class="text-end text-secondary fw-semibold">Valor Total</th>
                                <th class="text-center pe-4 text-secondary fw-semibold">Unidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($historico['rows'])): ?>
                                <tr class="empty-msg-row"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros para la fecha solicitada.</td></tr>
                            <?php else: ?>
                                <?php foreach (($historico['rows'] ?? []) as $r): ?>
                                    <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['item'] . ' ' . (string)$r['almacen'])); ?>">
                                        <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['item']); ?></td>
                                        <td class="text-muted"><?php echo e((string)$r['almacen']); ?></td>
                                        <td class="text-end fw-bold text-primary"><?php echo number_format((float)($r['stock_actual'] ?? 0), 2, '.', ','); ?></td>
                                        <td class="text-end text-muted"><?php echo $formatCurrency($r['costo_unitario'], 4); ?></td>
                                        <td class="text-end fw-semibold text-dark"><?php echo $formatCurrency($r['valor_total'], 2); ?></td>
                                        <td class="text-center pe-4"><span class="badge bg-light text-secondary border"><?php echo e((string)$r['unidad']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaRepHistoricoPaginationInfo">
                        Cargando...
                        <?php if (!empty($historico['valor_total'])): ?>
                            <span class="ms-2 badge bg-primary-subtle text-primary border border-primary-subtle px-2">
                                Valor total a la fecha: <?php echo $formatCurrency($historico['valor_total']); ?>
                            </span>
                        <?php endif; ?>
                    </small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepHistoricoPaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'kardex'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <h5 class="mb-0 fw-bold text-dark text-nowrap"><i class="bi bi-journal-check me-2 text-primary"></i>Movimientos Detallados</h5>
                <div class="d-flex gap-2 align-items-center w-100" style="max-width: 450px;">
                    <button type="submit" form="formFiltrosInventario" name="exportar_pdf" value="1" class="btn btn-sm btn-danger shadow-sm fw-bold px-3 d-flex align-items-center text-nowrap" formtarget="_blank" title="Exportar a PDF" onclick="document.getElementById('hidden_busqueda').value = document.getElementById('filtroRepKardex').value;">
                        <i class="bi bi-file-pdf-fill"></i><span class="ms-1 d-none d-sm-inline">PDF</span>
                    </button>
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepKardex" placeholder="Buscar ref o tipo..." value="<?php echo e($_GET['busqueda'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaRepKardex" data-erp-table="true" data-search-input="#filtroRepKardex" data-rows-per-page="10">
                        <thead>
                            <tr>
                                <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                                <th class="text-center text-secondary fw-semibold">Tipo</th>
                                <th class="text-end text-secondary fw-semibold">Cantidad</th>
                                <th class="text-end text-secondary fw-semibold">C/U</th>
                                <th class="text-end text-secondary fw-semibold">Total</th>
                                <th class="text-secondary fw-semibold">Referencia</th>
                                <th class="pe-4 text-secondary fw-semibold">Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($kardex['rows'])): ?>
                                <tr class="empty-msg-row"><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay movimientos en este periodo.</td></tr>
                            <?php else: ?>
                                <?php foreach (($kardex['rows'] ?? []) as $r): 
                                    $tipo = mb_strtolower((string)$r['tipo']);
                                    $esIngreso = stripos($tipo, 'ingreso') !== false || stripos($tipo, 'entrada') !== false;
                                ?>
                                    <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['referencia'] . ' ' . (string)$r['tipo'])); ?>">
                                        <td class="ps-4 text-muted small"><?php echo e((string)$r['fecha']); ?></td>
                                        <td class="text-center">
                                            <span class="badge px-2 py-1 rounded-pill border <?php echo $esIngreso ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle'; ?>">
                                                <i class="bi <?php echo $esIngreso ? 'bi-arrow-down-left' : 'bi-arrow-up-right'; ?> me-1"></i><?php echo e((string)$r['tipo']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold"><?php echo e((string)$r['cantidad']); ?></td>
                                        <td class="text-end text-muted"><?php echo $formatCurrency($r['costo_unitario'], 2); ?></td>
                                        <td class="text-end fw-semibold text-dark"><?php echo $formatCurrency($r['costo_total'], 2); ?></td>
                                        <td class="text-muted small"><?php echo e((string)$r['referencia']); ?></td>
                                        <td class="pe-4 text-muted small"><i class="bi bi-person me-1"></i><?php echo e((string)$r['usuario']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaRepKardexPaginationInfo">Cargando...</small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepKardexPaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'vencimientos'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <h5 class="mb-0 fw-bold text-dark text-nowrap"><i class="bi bi-calendar2-x me-2 text-warning"></i>Detalle de Lotes</h5>
                <div class="d-flex gap-2 align-items-center w-100" style="max-width: 450px;">
                    <button type="submit" form="formFiltrosInventario" name="exportar_pdf" value="1" class="btn btn-sm btn-danger shadow-sm fw-bold px-3 d-flex align-items-center text-nowrap" formtarget="_blank" title="Exportar a PDF" onclick="document.getElementById('hidden_busqueda').value = document.getElementById('filtroRepVencimientos').value;">
                        <i class="bi bi-file-pdf-fill"></i><span class="ms-1 d-none d-sm-inline">PDF</span>
                    </button>
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVencimientos" placeholder="Buscar ítem o lote..." value="<?php echo e($_GET['busqueda'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaRepVencimientos" data-erp-table="true" data-search-input="#filtroRepVencimientos" data-rows-per-page="10">
                        <thead>
                            <tr>
                                <th class="ps-4 text-secondary fw-semibold">Ítem</th>
                                <th class="text-secondary fw-semibold">Almacén</th>
                                <th class="text-secondary fw-semibold">Lote</th>
                                <th class="text-secondary fw-semibold">Vencimiento</th>
                                <th class="text-end text-secondary fw-semibold">Stock</th>
                                <th class="text-center pe-4 text-secondary fw-semibold">Alerta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($vencimientos['rows'])): ?>
                                <tr class="empty-msg-row"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de lotes.</td></tr>
                            <?php else: ?>
                                <?php foreach (($vencimientos['rows'] ?? []) as $r): 
                                    $alertaVenc = (string)$r['alerta'];
                                    $esVencido = stripos($alertaVenc, 'vencido') !== false;
                                ?>
                                    <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['item'] . ' ' . (string)$r['lote'])); ?>">
                                        <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['item']); ?></td>
                                        <td class="text-muted"><?php echo e((string)$r['almacen']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><i class="bi bi-upc-scan me-1"></i><?php echo e((string)$r['lote']); ?></span></td>
                                        <td class="text-muted"><i class="bi bi-calendar-event me-1"></i><?php echo e((string)$r['fecha_vencimiento']); ?></td>
                                        <td class="text-end fw-semibold text-dark"><?php echo e((string)$r['stock_lote']); ?></td>
                                        <td class="text-center pe-4">
                                            <span class="badge px-2 py-1 rounded border <?php echo $esVencido ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-warning-subtle text-warning-emphasis border-warning-subtle'; ?>">
                                                <?php echo e($alertaVenc !== '' ? $alertaVenc : 'Normal'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaRepVencimientosPaginationInfo">Cargando...</small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVencimientosPaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    window.datosInventario = {
        graficoKardex: <?php echo json_encode($datosGraficoKardex ?? []); ?>,
        graficoLotes: <?php echo json_encode($datosGraficoLotes ?? []); ?> 
    };
</script>