<?php 
    $seccionActiva = $_GET['seccion_activa'] ?? ($filtros['seccion_activa'] ?? 'stock'); 
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

    <ul class="nav nav-tabs border-bottom-1 mb-0 px-2" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'stock' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="stock">
                <i class="bi bi-layers-half me-2"></i>Stock Actual
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'historico' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="historico">
                <i class="bi bi-clock-history me-2"></i>Stock a Fecha
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'kardex' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="kardex">
                <i class="bi bi-journal-check me-2"></i>Kardex Valorizado
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'vencimientos' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="vencimientos">
                <i class="bi bi-calendar2-x me-2"></i>Lotes y Vencimientos
            </button>
        </li>
    </ul>

    <div class="card border-0 shadow-sm mb-4 rounded-top-0 border-top border-primary border-3">
        <div class="card-body p-4 bg-white">
            <form id="formFiltrosInventario" class="row g-3" method="get" action="<?php echo e(route_url('reportes/inventario')); ?>">
                <input type="hidden" name="ruta" value="reportes/inventario">
                <input type="hidden" name="seccion_activa" id="input_seccion_activa" value="<?php echo e($seccionActiva); ?>">
                
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Categoría</label>
                    <?php 
                        $catSeleccionadas = is_array($filtros['id_categoria'] ?? []) ? $filtros['id_categoria'] : (!empty($filtros['id_categoria']) ? [$filtros['id_categoria']] : []);
                        $catCount = count($catSeleccionadas);
                        $txtCat = $catCount === 0 ? 'Todas las categorías' : ($catCount === 1 ? '1 seleccionada' : $catCount . ' seleccionadas');
                    ?>
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
                    <?php 
                        $tiposSeleccionados = is_array($filtros['tipo_item'] ?? []) ? $filtros['tipo_item'] : (is_string($filtros['tipo_item'] ?? '') && $filtros['tipo_item'] !== '' ? [$filtros['tipo_item']] : []);
                        $opcionesTipos = ['producto_terminado' => 'Producto terminado', 'materia_prima' => 'Materia prima', 'insumo' => 'Insumo', 'semielaborado' => 'Semielaborado', 'material_empaque' => 'Material de empaque', 'servicio' => 'Servicio'];
                        $tipoCount = count($tiposSeleccionados);
                        $txtTipo = $tipoCount === 0 ? 'Todos los tipos' : ($tipoCount === 1 ? $opcionesTipos[$tiposSeleccionados[0]] ?? '1 seleccionado' : $tipoCount . ' seleccionados');
                    ?>
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
                    <?php 
                        $almSeleccionados = is_array($filtros['id_almacen'] ?? []) ? $filtros['id_almacen'] : (!empty($filtros['id_almacen']) ? [$filtros['id_almacen']] : []);
                        $almCount = count($almSeleccionados);
                        $txtAlm = $almCount === 0 ? 'Todos los almacenes' : ($almCount === 1 ? '1 seleccionado' : $almCount . ' seleccionados');
                    ?>
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

                <div class="col-12 col-md-3 d-flex flex-column justify-content-end">
                    
                    <?php if ($seccionActiva === 'stock'): ?>
                        <div class="form-check form-switch p-2 bg-light rounded border d-flex align-items-center w-100" style="height: 38px; margin-bottom: 0;">
                            <input class="form-check-input mt-0 me-2 auto-submit" type="checkbox" role="switch" id="filtroBajoMinimo" name="solo_bajo_minimo" value="1" <?php echo !empty($filtros['solo_bajo_minimo']) ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-bold text-danger cursor-pointer mb-0" for="filtroBajoMinimo">Solo bajo mínimo</label>
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

                <div class="col-12 d-flex justify-content-end mt-4 pt-3 border-top">
                    <button type="submit" name="exportar_pdf" value="1" class="btn btn-danger shadow-sm fw-bold px-4" formtarget="_blank">
                        <i class="bi bi-file-pdf-fill me-2"></i>Exportar <?php echo ucfirst($seccionActiva); ?> a PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($seccionActiva === 'stock'): ?>
        <div class="row mb-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted fw-bold mb-3">Distribución del Valor</h6>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartStockDona"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted fw-bold mb-3">Top 5 Artículos de Mayor Valor</h6>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartStockBarras"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-layers-half me-2 text-info"></i>Detalle de Inventario</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepStock" placeholder="Buscar ítem o almacén...">
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
                                <?php foreach (($stock['rows'] ?? []) as $r): ?>
                                    <?php 
                                        $alertaTexto = (string)$r['alerta'];
                                        $esCritico = stripos($alertaTexto, 'bajo') !== false || stripos($alertaTexto, 'crítico') !== false || stripos($alertaTexto, 'critico') !== false;
                                        $alertaClase = $esCritico ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-light text-secondary border-secondary-subtle';
                                        $usaDecimales = (int)($r['permite_decimales'] ?? 0) === 1;
                                        $stockFormateado = number_format((float)($r['stock_actual'] ?? 0), $usaDecimales ? 3 : 0, '.', ',');
                                        $stockMinimoFormateado = number_format((float)($r['stock_minimo'] ?? 0), $usaDecimales ? 3 : 0, '.', ',');
                                    ?>
                                    <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['item'] . ' ' . (string)$r['almacen'])); ?>">
                                        <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['item']); ?></td>
                                        <td class="text-muted"><?php echo e((string)$r['almacen']); ?></td>
                                        <td class="text-end fw-bold <?php echo $esCritico ? 'text-danger' : 'text-success'; ?>"><?php echo e($stockFormateado); ?></td>
                                        <td class="text-end text-muted">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 4); ?></td>
                                        <td class="text-end fw-semibold text-dark">S/ <?php echo number_format((float)($r['valor_total'] ?? 0), 2); ?></td>
                                        <td class="text-end text-muted"><?php echo e($stockMinimoFormateado); ?></td>
                                        <td class="text-center"><span class="badge bg-light text-secondary border"><?php echo e((string)$r['unidad']); ?></span></td>
                                        <td class="text-center pe-4">
                                            <span class="badge px-2 py-1 rounded border <?php echo $alertaClase; ?>"><?php echo e($alertaTexto !== '' ? $alertaTexto : 'OK'); ?></span>
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
                                Valor total: S/ <?php echo number_format((float)($stock['valor_total'] ?? 0), 2); ?>
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
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Stock a la Fecha Seleccionada</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepHistorico" placeholder="Buscar ítem...">
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
                                        <td class="text-end text-muted">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 4); ?></td>
                                        <td class="text-end fw-semibold text-dark">S/ <?php echo number_format((float)($r['valor_total'] ?? 0), 2); ?></td>
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
                                Valor total a la fecha: S/ <?php echo number_format((float)($historico['valor_total'] ?? 0), 2); ?>
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
            <div class="card-body">
                <h6 class="text-muted fw-bold mb-3">Entradas vs Salidas (Periodo Seleccionado)</h6>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="chartKardexLineas"></canvas>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-journal-check me-2 text-primary"></i>Movimientos Detallados</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepKardex" placeholder="Buscar ref o tipo...">
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
                                <?php foreach (($kardex['rows'] ?? []) as $r): ?>
                                    <?php 
                                        $tipo = mb_strtolower((string)$r['tipo']);
                                        $esIngreso = stripos($tipo, 'ingreso') !== false || stripos($tipo, 'entrada') !== false;
                                        $badgeTipo = $esIngreso ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle';
                                        $iconoTipo = $esIngreso ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
                                    ?>
                                    <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['referencia'] . ' ' . (string)$r['tipo'])); ?>">
                                        <td class="ps-4 text-muted small"><?php echo e((string)$r['fecha']); ?></td>
                                        <td class="text-center">
                                            <span class="badge px-2 py-1 rounded-pill border <?php echo $badgeTipo; ?>">
                                                <i class="bi <?php echo $iconoTipo; ?> me-1"></i><?php echo e((string)$r['tipo']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold"><?php echo e((string)$r['cantidad']); ?></td>
                                        <td class="text-end text-muted">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 2); ?></td>
                                        <td class="text-end fw-semibold text-dark">S/ <?php echo number_format((float)($r['costo_total'] ?? 0), 2); ?></td>
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
        <div class="row mb-4 justify-content-center">
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted fw-bold mb-3">Estado de Salud de Lotes</h6>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartLotesPie"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar2-x me-2 text-warning"></i>Detalle de Lotes</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVencimientos" placeholder="Buscar ítem o lote...">
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
                                <?php foreach (($vencimientos['rows'] ?? []) as $r): ?>
                                    <?php 
                                        $alertaVenc = (string)$r['alerta'];
                                        $esVencido = stripos($alertaVenc, 'vencido') !== false;
                                        $badgeVenc = $esVencido ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                    ?>
                                    <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['item'] . ' ' . (string)$r['lote'])); ?>">
                                        <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['item']); ?></td>
                                        <td class="text-muted"><?php echo e((string)$r['almacen']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><i class="bi bi-upc-scan me-1"></i><?php echo e((string)$r['lote']); ?></span></td>
                                        <td class="text-muted"><i class="bi bi-calendar-event me-1"></i><?php echo e((string)$r['fecha_vencimiento']); ?></td>
                                        <td class="text-end fw-semibold text-dark"><?php echo e((string)$r['stock_lote']); ?></td>
                                        <td class="text-center pe-4">
                                            <span class="badge px-2 py-1 rounded border <?php echo $badgeVenc; ?>"><?php echo e($alertaVenc !== '' ? $alertaVenc : 'Normal'); ?></span>
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
        graficoDona: <?php echo json_encode($datosGraficoDona ?? []); ?>,
        graficoBarras: <?php echo json_encode($datosGraficoBarras ?? []); ?>
    };
</script>