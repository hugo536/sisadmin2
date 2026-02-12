<?php
$stockActual = $stockActual ?? [];
$almacenes = $almacenes ?? [];
$items = $items ?? [];
$kpis = $kpis ?? ['total_items' => 0, 'sin_stock' => 0, 'critico' => 0, 'por_vencer' => 0];
$hoy = new DateTimeImmutable('today');
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 fade-in gap-2">
        <div class="flex-grow-1">
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary fs-5"></i>
                <span>Inventario de Productos</span>
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de existencias por producto y almacén.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('inventario/kardex')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-journal-text me-2 text-info"></i>Kardex
            </a>
            <div class="btn-group shadow-sm">
                <button type="button" class="btn btn-white border text-secondary fw-semibold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-arrow-down me-2 text-info"></i>Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=csv">CSV</a></li>
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=excel">Excel</a></li>
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=pdf">PDF (texto)</a></li>
                </ul>
            </div>
            <?php if (tiene_permiso('inventario.movimiento.crear')): ?>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMovimientoInventario">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Movimiento
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-3 fade-in">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Productos</div><div class="h4 mb-0 fw-bold"><?php echo (int) ($kpis['total_items'] ?? 0); ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Registros sin stock</div><div class="h4 mb-0 fw-bold text-danger"><?php echo (int) ($kpis['sin_stock'] ?? 0); ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Stock crítico</div><div class="h4 mb-0 fw-bold text-warning"><?php echo (int) ($kpis['critico'] ?? 0); ?></div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="text-muted small">Por vencer</div><div class="h4 mb-0 fw-bold text-dark"><?php echo (int) ($kpis['por_vencer'] ?? 0); ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3 fade-in">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="inventarioSearch" placeholder="Buscar SKU o nombre...">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="inventarioFiltroEstado">
                        <option value="">Estado</option>
                        <option value="disponible">Con stock</option>
                        <option value="agotado">Sin stock</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="inventarioFiltroCriticidad">
                        <option value="">Criticidad</option>
                        <option value="normal">Normal</option>
                        <option value="bajo">Bajo</option>
                        <option value="critico">Crítico</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="inventarioFiltroAlmacen">
                        <option value="">Todos los almacenes</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="inventarioFiltroVencimiento">
                        <option value="">Vencimiento</option>
                        <option value="vencido">Vencido</option>
                        <option value="proximo">Próximo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm fade-in">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaInventarioStock">
                    <thead>
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Producto</th>
                            <th>Almacén</th>
                            <th class="text-end pe-4">Stock Actual</th>
                            <th class="text-center">Estado Stock</th>
                            <th class="text-center">Vencimiento</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stockActual)): ?>
                            <?php foreach ($stockActual as $stock): ?>
                                <?php
                                $stockActualItem = (float) ($stock['stock_actual'] ?? 0);
                                $stockMinimo = (float) ($stock['stock_minimo'] ?? 0);
                                $sku = (string) ($stock['sku'] ?? '');
                                $itemNombre = (string) ($stock['item_nombre'] ?? '');
                                $almacenNombre = (string) ($stock['almacen_nombre'] ?? '');
                                $idAlmacen = (int) ($stock['id_almacen'] ?? 0);
                                $estadoStock = $stockActualItem <= 0 ? 'agotado' : 'disponible';
                                $criticidad = 'normal';
                                $estadoCantidadClase = 'bg-success';
                                $estadoCantidadTexto = 'Normal';

                                if ($stockActualItem <= $stockMinimo) {
                                    $criticidad = 'critico';
                                    $estadoCantidadClase = 'bg-danger';
                                    $estadoCantidadTexto = 'Crítico';
                                } elseif ($stockActualItem <= ($stockMinimo * 1.5)) {
                                    $criticidad = 'bajo';
                                    $estadoCantidadClase = 'bg-warning text-dark';
                                    $estadoCantidadTexto = 'Bajo';
                                }

                                $requiereVencimiento = (int) ($stock['requiere_vencimiento'] ?? 0) === 1;
                                $diasAlerta = (int) ($stock['dias_alerta_vencimiento'] ?? 0);
                                $proximoVencimiento = (string) ($stock['proximo_vencimiento'] ?? '');
                                $textoVencimiento = '-';
                                $badgeVencimiento = 'bg-secondary';
                                $etiquetaVencimiento = '-';
                                $estadoVencimiento = '';

                                if ($requiereVencimiento && $proximoVencimiento !== '') {
                                    $textoVencimiento = $proximoVencimiento;
                                    $fechaVencimiento = DateTimeImmutable::createFromFormat('Y-m-d', $proximoVencimiento);
                                    $limiteAlerta = $hoy->modify('+' . $diasAlerta . ' days');

                                    if ($fechaVencimiento instanceof DateTimeImmutable) {
                                        if ($fechaVencimiento < $hoy) {
                                            $badgeVencimiento = 'bg-dark';
                                            $etiquetaVencimiento = 'VENCIDO';
                                            $estadoVencimiento = 'vencido';
                                        } elseif ($fechaVencimiento <= $limiteAlerta) {
                                            $badgeVencimiento = 'bg-warning text-dark';
                                            $etiquetaVencimiento = 'PRÓXIMO';
                                            $estadoVencimiento = 'proximo';
                                        } else {
                                            $badgeVencimiento = 'bg-success';
                                            $etiquetaVencimiento = 'OK';
                                        }
                                    }
                                }

                                $search = $sku . ' ' . $itemNombre . ' ' . $almacenNombre;
                                ?>
                                <tr
                                    data-search="<?php echo e($search); ?>"
                                    data-item-id="<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                    data-estado="<?php echo e($estadoStock); ?>"
                                    data-criticidad="<?php echo e($criticidad); ?>"
                                    data-almacen="<?php echo (int) $idAlmacen; ?>"
                                    data-vencimiento="<?php echo e($estadoVencimiento); ?>"
                                >
                                    <td class="ps-4 fw-semibold"><?php echo e($sku); ?></td>
                                    <td><?php echo e($itemNombre); ?></td>
                                    <td><?php echo e($almacenNombre); ?></td>
                                    <td class="text-end pe-4 fw-bold"><?php echo number_format($stockActualItem, 4, '.', ''); ?></td>
                                    <td class="text-center"><span class="badge <?php echo e($estadoCantidadClase); ?>"><?php echo e($estadoCantidadTexto); ?></span></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo e($badgeVencimiento); ?>"><?php echo e($etiquetaVencimiento); ?></span>
                                        <?php if ($textoVencimiento !== '-'): ?><div class="small text-muted"><?php echo e($textoVencimiento); ?></div><?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <div class="form-check form-switch pt-1" title="Cambiar estado del producto">
                                                <input class="form-check-input switch-estado-item-inventario" type="checkbox" role="switch"
                                                       style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                       data-id="<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                                       <?php echo ((int) ($stock['item_estado'] ?? 0) === 1) ? 'checked' : ''; ?>
                                                       <?php echo tiene_permiso('items.editar') ? '' : 'disabled'; ?>>
                                            </div>
                                            <div class="vr bg-secondary opacity-25" style="height:20px;"></div>
                                            <?php if (tiene_permiso('items.editar')): ?>
                                            <a href="?ruta=items/perfil&id=<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                               class="btn btn-sm btn-light text-primary border-0 bg-transparent"
                                               title="Editar">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if (tiene_permiso('items.eliminar')): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-light text-danger border-0 bg-transparent btn-eliminar-item-inventario"
                                                    data-id="<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                                    data-item="<?php echo e($itemNombre); ?>"
                                                    title="Eliminar">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay registros de stock disponibles.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMovimientoInventario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Registrar movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formMovimientoInventario" class="row g-3" autocomplete="off">
                    <div class="col-md-6">
                        <label for="tipoMovimiento" class="form-label">Tipo</label>
                        <select id="tipoMovimiento" name="tipo_movimiento" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="INI">INI - Inicial</option>
                            <option value="AJ+">AJ+ - Ajuste positivo</option>
                            <option value="AJ-">AJ- - Ajuste negativo</option>
                            <option value="TRF">TRF - Transferencia</option>
                            <option value="CON">CON - Consumo</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="almacenMovimiento" class="form-label">Almacén</label>
                        <select id="almacenMovimiento" name="id_almacen" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="grupoAlmacenDestino">
                        <label for="almacenDestinoMovimiento" class="form-label">Almacén destino</label>
                        <select id="almacenDestinoMovimiento" name="id_almacen_destino" class="form-select">
                            <option value="">Seleccione...</option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 position-relative">
                        <label for="itemMovimiento" class="form-label">Ítem (SKU / Nombre)</label>
                        <input type="text" id="itemMovimiento" class="form-control" list="listaItemsInventario" placeholder="Buscar por SKU o nombre" required>
                        <input type="hidden" id="idItemMovimiento" name="id_item" required>
                        <datalist id="listaItemsInventario">
                            <?php foreach ($items as $item): ?>
                                <option data-id="<?php echo (int) ($item['id'] ?? 0); ?>" data-requiere-lote="<?php echo (int) ($item['requiere_lote'] ?? 0); ?>" data-requiere-vencimiento="<?php echo (int) ($item['requiere_vencimiento'] ?? 0); ?>" value="<?php echo e((string) ($item['sku'] ?? '')); ?> - <?php echo e((string) ($item['nombre'] ?? '')); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div id="sugerenciasItemsInventario" class="list-group position-absolute w-100 mt-1 d-none" style="z-index: 1060;"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="cantidadMovimiento" class="form-label">Cantidad</label>
                        <input type="number" step="0.0001" min="0.0001" class="form-control" id="cantidadMovimiento" name="cantidad" required>
                        <div id="stockDisponibleHint" class="form-text text-muted"></div>
                    </div>
                    <div class="col-md-6 d-none" id="grupoLoteMovimiento">
                        <label for="loteMovimiento" class="form-label">Lote</label>
                        <input type="text" class="form-control" id="loteMovimiento" name="lote" maxlength="100" placeholder="Código de lote">
                    </div>
                    <div class="col-md-6 d-none" id="grupoVencimientoMovimiento">
                        <label for="vencimientoMovimiento" class="form-label">Fecha de vencimiento</label>
                        <input type="date" class="form-control" id="vencimientoMovimiento" name="fecha_vencimiento">
                    </div>
                    <div class="col-md-6">
                        <label for="costoUnitarioMovimiento" class="form-label">Costo unitario</label>
                        <input type="number" step="0.0001" min="0" class="form-control" id="costoUnitarioMovimiento" name="costo_unitario" value="0">
                    </div>
                    <div class="col-12">
                        <label for="referenciaMovimiento" class="form-label">Referencia</label>
                        <input type="text" class="form-control" id="referenciaMovimiento" name="referencia" maxlength="255" placeholder="N° documento / comentario">
                        <div class="small text-muted mt-1">Salidas sin lote específico usan FEFO: primero vence, primero sale.</div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(asset_url('js/inventario.js')); ?>"></script>
