<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$resumen = $resumen ?? ['activos' => 0, 'inactivos' => 0, 'default' => 0];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Configuración de Impuestos</h1>
            <small class="text-muted">Catálogo de tasas para ventas y compras.</small>
        </div>
        <?php if (tiene_permiso('config.editar')): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalImpuesto" data-mode="create">
                <i class="bi bi-plus-circle me-1"></i> Nuevo impuesto
            </button>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Activos</div><div class="h4 mb-0"><?php echo (int) $resumen['activos']; ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Inactivos</div><div class="h4 mb-0"><?php echo (int) $resumen['inactivos']; ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Por defecto</div><div class="h4 mb-0"><?php echo (int) $resumen['default']; ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2" method="GET">
                <input type="hidden" name="ruta" value="impuestos/index">
                <div class="col-md-5"><input type="text" class="form-control" name="q" placeholder="Buscar por código o nombre" value="<?php echo e((string) ($filtros['q'] ?? '')); ?>"></div>
                <div class="col-md-3">
                    <select class="form-select" name="tipo">
                        <option value="">Tipo (todos)</option>
                        <?php foreach (['VENTA', 'COMPRA', 'AMBOS'] as $tipo): ?>
                            <option value="<?php echo $tipo; ?>" <?php echo (($filtros['tipo'] ?? '') === $tipo) ? 'selected' : ''; ?>><?php echo $tipo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="estado_filtro">
                        <option value="activos" <?php echo (($filtros['estado_filtro'] ?? '') === 'activos') ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivos" <?php echo (($filtros['estado_filtro'] ?? '') === 'inactivos') ? 'selected' : ''; ?>>Inactivos</option>
                        <option value="todos" <?php echo (($filtros['estado_filtro'] ?? '') === 'todos') ? 'selected' : ''; ?>>Todos</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Filtrar</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-pro">
                <thead><tr><th>Código</th><th>Nombre</th><th class="text-end">%</th><th>Tipo</th><th>Estado</th><th>Default</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php if (!$registros): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Sin registros</td></tr>
                <?php else: ?>
                    <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><?php echo e((string) $row['codigo']); ?></td>
                        <td><?php echo e((string) $row['nombre']); ?></td>
                        <td class="text-end"><?php echo number_format((float) $row['porcentaje'], 2); ?></td>
                        <td><span class="badge text-bg-light"><?php echo e((string) $row['tipo']); ?></span></td>
                        <td><?php echo ((int) $row['estado'] === 1) ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>'; ?></td>
                        <td><?php echo ((int) $row['es_default'] === 1) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>'; ?></td>
                        <td class="text-end">
                            <?php if (tiene_permiso('config.editar')): ?>
                                <button class="btn btn-sm btn-light border js-edit-impuesto" data-bs-toggle="modal" data-bs-target="#modalImpuesto"
                                        data-id="<?php echo (int) $row['id']; ?>"
                                        data-codigo="<?php echo e((string) $row['codigo']); ?>"
                                        data-nombre="<?php echo e((string) $row['nombre']); ?>"
                                        data-porcentaje="<?php echo e((string) $row['porcentaje']); ?>"
                                        data-tipo="<?php echo e((string) $row['tipo']); ?>"
                                        data-es-default="<?php echo (int) $row['es_default']; ?>"
                                        data-estado="<?php echo (int) $row['estado']; ?>"
                                        data-observaciones="<?php echo e((string) ($row['observaciones'] ?? '')); ?>">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form class="d-inline" method="post" action="<?php echo e(route_url('impuestos/eliminar')); ?>" onsubmit="return confirm('¿Eliminar impuesto?');">
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <button class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (tiene_permiso('config.editar')): ?>
<div class="modal fade" id="modalImpuesto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?php echo e(route_url('impuestos/guardar')); ?>" id="formImpuesto">
                <div class="modal-header"><h5 class="modal-title">Impuesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="imp_id" value="0">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Código</label><input class="form-control" name="codigo" id="imp_codigo" required></div>
                        <div class="col-md-5"><label class="form-label">Nombre</label><input class="form-control" name="nombre" id="imp_nombre" required></div>
                        <div class="col-md-4"><label class="form-label">Porcentaje</label><input type="number" step="0.0001" min="0" max="100" class="form-control" name="porcentaje" id="imp_porcentaje" required></div>
                        <div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" name="tipo" id="imp_tipo"><option>VENTA</option><option>COMPRA</option><option>AMBOS</option></select></div>
                        <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado" id="imp_estado"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
                        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="es_default" id="imp_default"><label class="form-check-label" for="imp_default">Marcar como default</label></div></div>
                        <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" rows="2" name="observaciones" id="imp_observaciones"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
