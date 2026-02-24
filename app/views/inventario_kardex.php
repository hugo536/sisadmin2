<?php
$movimientos = $movimientos ?? [];
$items = $items ?? [];
$filtros = $filtros ?? [];

$tiposEntrada = ['INI', 'AJ+', 'COM', 'PROD'];
$tiposSalida = ['AJ-', 'CON', 'VEN'];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Kardex de inventario</h1>
        <a class="btn btn-outline-secondary" href="<?php echo e(route_url('inventario')); ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="">
                <input type="hidden" name="ruta" value="inventario/kardex">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Ítem</label>
                        <select class="form-select" name="id_item">
                            <option value="0">Todos</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo (int) ($item['id'] ?? 0); ?>" <?php echo ((int) ($filtros['id_item'] ?? 0) === (int) ($item['id'] ?? 0)) ? 'selected' : ''; ?>>
                                    <?php echo e((string) ($item['sku'] ?? '') . ' - ' . (string) ($item['nombre'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Lote</label><input class="form-control" type="text" name="lote" value="<?php echo e((string) ($filtros['lote'] ?? '')); ?>" placeholder="Ej: LOTE-001"></div>
                    <div class="col-md-2"><label class="form-label">Desde</label><input class="form-control" type="date" name="fecha_desde" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>"></div>
                    <div class="col-md-2"><label class="form-label">Hasta</label><input class="form-control" type="date" name="fecha_hasta" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>"></div>
                    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Filtrar</button></div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-pro align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Ítem</th><th>Origen</th><th>Destino</th><th class="text-end">Cantidad</th><th>Usuario</th><th>Referencia</th></tr></thead>
                <tbody>
                <?php if (empty($movimientos)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Sin movimientos para los filtros seleccionados.</td></tr>
                <?php else: foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?php echo e((string) ($mov['created_at'] ?? '')); ?></td>
                        <?php $tipoMov = strtoupper(trim((string) ($mov['tipo_movimiento'] ?? ''))); ?>
                        <td>
                            <?php if (in_array($tipoMov, $tiposEntrada, true)): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">ENT - <?php echo e($tipoMov); ?></span>
                            <?php elseif (in_array($tipoMov, $tiposSalida, true)): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">SAL - <?php echo e($tipoMov); ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo e($tipoMov); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e((string) ($mov['sku'] ?? '') . ' - ' . (string) ($mov['item_nombre'] ?? '')); ?></td>
                        <td><?php echo e((string) ($mov['almacen_origen'] ?? '-')); ?></td>
                        <td><?php echo e((string) ($mov['almacen_destino'] ?? '-')); ?></td>
                        <?php
                            $cantidadBase = (float) ($mov['cantidad'] ?? 0);
                            $cantidadPrincipal = number_format($cantidadBase, 4, '.', '');
                            $referencia = (string) ($mov['referencia'] ?? '');
                            $detalleConversion = '';
                            if (preg_match('/Conv:\s*([^|]+)/i', $referencia, $matchConv)) {
                                $detalleConversion = trim((string) ($matchConv[1] ?? ''));
                            }
                        ?>
                        <td class="text-end fw-semibold">
                            <?php if ($detalleConversion !== ''): ?>
                                <div><?php echo e($detalleConversion); ?></div>
                                <div class="small text-muted"><?php echo e($cantidadPrincipal); ?></div>
                            <?php else: ?>
                                <?php echo e($cantidadPrincipal); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e((string) ($mov['usuario'] ?? '-')); ?></td>
                        <td><?php echo e((string) ($mov['referencia'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
