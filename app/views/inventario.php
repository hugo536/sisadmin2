<?php
$stockActual = $stockActual ?? [];
$almacenes = $almacenes ?? [];
// NUEVO: Asegurarnos de recibir la variable proveedores (debemos enviarla desde el controlador)
$proveedores = $proveedores ?? []; 
$idAlmacenFiltro = (int) ($id_almacen_filtro ?? 0);
$hoy = new DateTimeImmutable('today');
?>
<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Inventario de Productos
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de existencias, kardex y movimientos de almacén.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('inventario/kardex')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-journal-text me-2 text-info"></i>Kardex
            </a>
            
            <div class="btn-group shadow-sm">
                <button type="button" class="btn btn-white border text-secondary fw-semibold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-arrow-down me-2 text-info"></i>Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=csv&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=excel&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=pdf&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-file-pdf me-2"></i>PDF</a></li>
                </ul>
            </div>

            <?php if (tiene_permiso('inventario.movimiento.crear')): ?>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalMovimientoInventario">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Movimiento
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="inventarioSearch" placeholder="Buscar SKU o nombre...">
                    </div>
                </div>
                
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="inventarioFiltroTipoRegistro">
                        <option value="">Todos los Tipos</option>
                        <option value="item">Productos Base / Insumos</option>
                        <option value="pack">Presentaciones Comerciales (Packs)</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="inventarioFiltroAlmacen">
                        <option value="" <?php echo $idAlmacenFiltro === 0 ? 'selected' : ''; ?>>Todos los almacenes</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>" <?php echo $idAlmacenFiltro === (int) ($almacen['id'] ?? 0) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($almacen['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="inventarioFiltroEstado">
                        <option value="">Estado Stock</option>
                        <option value="disponible">Saludable (Verde)</option>
                        <option value="alerta">Alerta (Amarillo)</option>
                        <option value="agotado">Agotado (Rojo)</option>
                        <option value="sin_movimiento">Sin Movimientos (Gris)</option>
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

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaInventarioStock">
                    <thead>
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Producto (Nombre Completo)</th>
                            <th>Almacén</th>
                            <th>Lote</th>
                            <th class="text-end pe-4">Stock Actual</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Vencimiento</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stockActual)): ?>
                            <?php foreach ($stockActual as $stock): ?>
                                <?php
                                $sku = (string) ($stock['sku'] ?? '');
                                $itemNombreCompleto = (string) ($stock['item_nombre'] ?? '');
                                $almacenNombre = (string) ($stock['almacen_nombre'] ?? '');
                                $loteActual = trim((string) ($stock['lote_actual'] ?? ''));
                                $idAlmacen = (int) ($stock['id_almacen'] ?? 0);
                                $tipoRegistro = (string) ($stock['tipo_registro'] ?? 'item');
                                
                                $stockFormateado = (string) ($stock['stock_formateado'] ?? '0');
                                $badgeColor = (string) ($stock['badge_color'] ?? '');
                                $badgeTexto = (string) ($stock['badge_estado'] ?? '');

                                $requiereVencimiento = (int) ($stock['requiere_vencimiento'] ?? 0) === 1;
                                $diasAlerta = (int) ($stock['dias_alerta_vencimiento'] ?? 0);
                                $proximoVencimiento = (string) ($stock['proximo_vencimiento'] ?? '');
                                $textoVencimiento = '-';
                                $badgeVencimiento = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                $etiquetaVencimiento = '-';
                                $estadoVencimiento = '';

                                if ($requiereVencimiento && $proximoVencimiento !== '') {
                                    $textoVencimiento = $proximoVencimiento;
                                    $fechaVencimiento = DateTimeImmutable::createFromFormat('Y-m-d', $proximoVencimiento);
                                    $limiteAlerta = $hoy->modify('+' . $diasAlerta . ' days');

                                    if ($fechaVencimiento instanceof DateTimeImmutable) {
                                        if ($fechaVencimiento < $hoy) {
                                            $badgeVencimiento = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            $etiquetaVencimiento = 'VENCIDO';
                                            $estadoVencimiento = 'vencido';
                                        } elseif ($fechaVencimiento <= $limiteAlerta) {
                                            $badgeVencimiento = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                            $etiquetaVencimiento = 'PRÓXIMO';
                                            $estadoVencimiento = 'proximo';
                                        } else {
                                            $badgeVencimiento = 'bg-success-subtle text-success border border-success-subtle';
                                            $etiquetaVencimiento = 'OK';
                                        }
                                    }
                                }

                                $search = mb_strtolower($sku . ' ' . $itemNombreCompleto . ' ' . $almacenNombre . ' ' . $loteActual);
                                ?>
                                <tr data-search="<?php echo e($search); ?>"
                                    data-item-id="<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                    data-tipo-registro="<?php echo e($tipoRegistro); ?>"
                                    data-estado="<?php echo strtolower(str_replace(' ', '_', $badgeTexto)); ?>"
                                    data-almacen="<?php echo (int) $idAlmacen; ?>"
                                    data-vencimiento="<?php echo e($estadoVencimiento); ?>">
                                    
                                    <td class="ps-4 fw-semibold text-primary"><?php echo e($sku); ?></td>
                                    <td class="fw-semibold text-dark">
                                        <?php echo e($itemNombreCompleto); ?>
                                        <?php if($tipoRegistro === 'pack'): ?>
                                            <span class="badge bg-info text-dark ms-1" style="font-size: 0.65rem;">PACK</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo e($almacenNombre); ?></td>
                                    <td><?php echo e($loteActual !== '' ? $loteActual : '-'); ?></td>
                                    
                                    <td class="text-end pe-4 fw-bold fs-6"><?php echo $stockFormateado; ?></td>
                                    
                                    <td class="text-center">
                                        <span class="badge px-3 rounded-pill <?php echo $badgeColor; ?>">
                                            <?php echo e($badgeTexto); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-center">
                                        <span class="badge px-3 rounded-pill <?php echo e($badgeVencimiento); ?>">
                                            <?php echo e($etiquetaVencimiento); ?>
                                        </span>
                                        <?php if ($textoVencimiento !== '-'): ?>
                                            <div class="small text-muted mt-1" style="font-size: 0.75rem;"><?php echo e($textoVencimiento); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <?php $itemActivo = (int) ($stock['item_estado'] ?? 0) === 1; ?>
                                            <span class="badge rounded-pill <?php echo $itemActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>"
                                                  data-bs-toggle="tooltip" data-bs-placement="top"
                                                  title="Estado referencial del ítem (solo lectura en inventario)">
                                                <?php echo $itemActivo ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                            <div class="vr bg-secondary opacity-25" style="height:20px;"></div>
                                            <?php if (in_array($tipoRegistro, ['item', 'pack'], true)): ?>
                                                <a href="<?php echo e(route_url('inventario/kardex')); ?>&item_id=<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                                   class="btn btn-sm btn-light text-primary border-0 bg-transparent"
                                                   data-bs-toggle="tooltip" data-bs-placement="top" title="Ver Kardex">
                                                    <i class="bi bi-eye fs-5"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small" data-bs-toggle="tooltip" data-bs-placement="top" title="Kardex disponible para ítems base">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay registros de stock disponibles.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMovimientoInventario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Registrar Movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form id="formMovimientoInventario" autocomplete="off">
                    <input type="hidden" id="idItemMovimiento" name="id_item" value="0">
                    <input type="hidden" id="idPackMovimiento" name="id_pack" value="0">
                    <input type="hidden" id="tipoRegistroMovimiento" name="tipo_registro" value="item">
                    <input type="hidden" name="lote" id="loteFinalEnviar">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-muted mb-3">Datos del Movimiento</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="tipoMovimiento" class="form-label small text-muted">Tipo de Movimiento <span class="text-danger fw-bold">*</span></label>
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
                                    <label for="almacenMovimiento" class="form-label small text-muted">Almacén Origen <span class="text-danger fw-bold">*</span></label>
                                    <select id="almacenMovimiento" name="id_almacen" class="form-select" required disabled>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($almacenes as $almacen): ?>
                                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted d-block mt-1">Seleccione primero el ítem para habilitar los almacenes con stock disponible.</small>
                                </div>

                                <div class="col-md-12 mt-2 d-none" id="grupoProveedorMovimiento">
                                    <label for="proveedorMovimiento" class="form-label small text-muted">Proveedor (Opcional / Para compras)</label>
                                    <select id="proveedorMovimiento" name="id_proveedor" class="form-select">
                                        <option value="">Seleccione proveedor...</option>
                                        <?php foreach (($proveedores ?? []) as $proveedor): ?>
                                            <option value="<?php echo (int) ($proveedor['id'] ?? 0); ?>"><?php echo e((string) ($proveedor['nombre_completo'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-12 d-none mt-2" id="grupoAlmacenDestino">
                                    <label for="almacenDestinoMovimiento" class="form-label small text-muted">Almacén Destino (Solo Transferencias)</label>
                                    <select id="almacenDestinoMovimiento" name="id_almacen_destino" class="form-select">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($almacenes as $almacen): ?>
                                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-12 d-none mt-2" id="grupoMotivoMovimiento">
                                    <label for="motivoMovimiento" class="form-label small text-muted">Motivo del Movimiento</label>
                                    <select id="motivoMovimiento" name="motivo" class="form-select">
                                        <option value="">Seleccione motivo...</option>
                                        <option value="Merma recuperada">Merma recuperada</option>
                                        <option value="Conteo físico">Conteo físico</option>
                                        <option value="Error anterior">Error anterior</option>
                                        <option value="Devolución interna">Devolución interna</option>
                                        <option value="Merma">Merma</option>
                                        <option value="Robo">Robo</option>
                                        <option value="Caducado">Caducado</option>
                                        <option value="Desperdicio">Desperdicio</option>
                                        <option value="Producción">Producción</option>
                                        <option value="Muestras">Muestras</option>
                                        <option value="Pruebas laboratorio">Pruebas laboratorio</option>
                                        <option value="Consumo administrativo">Consumo administrativo</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-muted mb-3">Detalle del Producto</h6>
                            <div class="row g-2">
                                
                                <div class="col-12 mb-2">
                                    <label class="form-label small text-muted mb-1">Buscar Ítem (SKU / Nombre) <span class="text-danger fw-bold">*</span></label>
                                    <select id="itemMovimiento" class="form-select" placeholder="Escriba para buscar..." required>
                                        <option value="">Escriba para buscar...</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-light-subtle text-center">
                                        <small class="text-muted d-block">Stock Actual</small>
                                        <span class="fw-bold text-dark fs-5" id="stockActualItemSeleccionado">0.00</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-light-subtle text-center">
                                        <small class="text-muted d-block">Costo Promedio</small>
                                        <span class="fw-bold text-dark fs-5" id="costoPromedioActual">S/ 0.00</span>
                                    </div>
                                </div>

                                <div class="col-md-6 form-floating mt-2">
                                    <input type="number" step="0.0001" min="0.0001" class="form-control" id="cantidadMovimiento" name="cantidad" required>
                                    <label for="cantidadMovimiento">Cantidad a Mover <span class="text-danger fw-bold">*</span></label>
                                    <div class="form-text" id="stockDisponibleHint"></div>
                                </div>
                                <div class="col-md-6 form-floating mt-2">
                                    <input type="number" step="0.0001" min="0" class="form-control" id="costoUnitarioMovimiento" name="costo_unitario" value="0">
                                    <label for="costoUnitarioMovimiento">Costo Unitario (S/)</label>
                                </div>

                                <div class="col-md-6 d-none mt-2" id="grupoLoteInput">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="loteMovimientoInput" maxlength="100" placeholder="Lote">
                                        <label>Nuevo Lote</label>
                                    </div>
                                </div>
                                <div class="col-md-6 d-none mt-2" id="grupoLoteSelect">
                                    <div class="form-floating">
                                        <select class="form-select" id="loteMovimientoSelect">
                                            <option value="">Seleccione lote...</option>
                                        </select>
                                        <label>Lote Existente</label>
                                    </div>
                                    <div class="form-text small text-danger d-none" id="msgSinLotes"><i class="bi bi-exclamation-circle"></i> Sin lotes disponibles.</div>
                                </div>
                                <div class="col-md-6 d-none mt-2" id="grupoVencimientoMovimiento">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="vencimientoMovimiento" name="fecha_vencimiento">
                                        <label>Fecha Vencimiento</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="referenciaMovimiento" name="referencia" style="height: 80px" maxlength="255" placeholder="Ref"></textarea>
                        <label for="referenciaMovimiento">Referencia / Comentario <small class="text-muted">(obligatorio para INI, AJ+ y AJ-)</small></label>
                    </div>

                    <div class="d-flex justify-content-end pt-2">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Guardar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function(){
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>
<script src="<?php echo e(asset_url('js/inventario.js')); ?>"></script>
