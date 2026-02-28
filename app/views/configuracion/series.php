<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$resumen = $resumen ?? ['activos' => 0, 'inactivos' => 0, 'predeterminadas' => 0];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Configuración de Series</h1>
            <small class="text-muted">Define numeración por módulo y tipo de documento.</small>
        </div>
        <?php if (tiene_permiso('config.editar')): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSerie" data-mode="create">
                <i class="bi bi-plus-circle me-1"></i> Nueva serie
            </button>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Activas</div><div class="h4 mb-0"><?php echo (int) $resumen['activos']; ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Inactivas</div><div class="h4 mb-0"><?php echo (int) $resumen['inactivos']; ?></div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Predeterminadas</div><div class="h4 mb-0"><?php echo (int) $resumen['predeterminadas']; ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2" method="GET">
                <input type="hidden" name="ruta" value="series/index">
                <div class="col-md-5"><input type="text" class="form-control" name="q" placeholder="Buscar por serie/prefijo/documento" value="<?php echo e((string) ($filtros['q'] ?? '')); ?>"></div>
                <div class="col-md-3">
                    <select class="form-select" name="modulo">
                        <option value="">Módulo (todos)</option>
                        <?php foreach (['VENTAS', 'COMPRAS'] as $mod): ?>
                            <option value="<?php echo $mod; ?>" <?php echo (($filtros['modulo'] ?? '') === $mod) ? 'selected' : ''; ?>><?php echo $mod; ?></option>
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
            <table class="table align-middle mb-0 table-pro" id="seriesTable"
                   data-erp-table="true"
                   data-pagination-controls="#seriesPaginationControls"
                   data-pagination-info="#seriesPaginationInfo">
                <thead><tr><th>Módulo</th><th>Documento</th><th>Serie</th><th>Prefijo</th><th class="text-end">Correlativo</th><th>Estado</th><th>Pred.</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php if (!$registros): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Sin registros</td></tr>
                <?php else: ?>
                    <?php foreach ($registros as $row): ?>
                    <tr>
                        <td><span class="badge text-bg-light"><?php echo e((string) $row['modulo']); ?></span></td>
                        <td><?php echo e((string) $row['tipo_documento']); ?></td>
                        <td><?php echo e((string) $row['codigo_serie']); ?></td>
                        <td><?php echo e((string) $row['prefijo']); ?></td>
                        <td class="text-end"><?php echo e(str_pad((string) ((int) $row['correlativo_actual']), (int) $row['longitud_correlativo'], '0', STR_PAD_LEFT)); ?></td>
                        <td><?php echo ((int) $row['estado'] === 1) ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>'; ?></td>
                        <td><?php echo ((int) $row['predeterminada'] === 1) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>'; ?></td>
                        <td class="text-end">
                            <?php if (tiene_permiso('config.editar')): ?>
                                <button class="btn btn-sm btn-light border js-edit-serie" data-bs-toggle="modal" data-bs-target="#modalSerie"
                                        data-id="<?php echo (int) $row['id']; ?>"
                                        data-modulo="<?php echo e((string) $row['modulo']); ?>"
                                        data-tipo-documento="<?php echo e((string) $row['tipo_documento']); ?>"
                                        data-codigo-serie="<?php echo e((string) $row['codigo_serie']); ?>"
                                        data-prefijo="<?php echo e((string) $row['prefijo']); ?>"
                                        data-correlativo-actual="<?php echo (int) $row['correlativo_actual']; ?>"
                                        data-longitud-correlativo="<?php echo (int) $row['longitud_correlativo']; ?>"
                                        data-predeterminada="<?php echo (int) $row['predeterminada']; ?>"
                                        data-estado="<?php echo (int) $row['estado']; ?>"
                                        data-observaciones="<?php echo e((string) ($row['observaciones'] ?? '')); ?>">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form class="d-inline" method="post" action="<?php echo e(route_url('series/eliminar')); ?>" onsubmit="return confirm('¿Eliminar serie?');">
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
<div class="modal fade" id="modalSerie" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?php echo e(route_url('series/guardar')); ?>" id="formSerie">
                <div class="modal-header"><h5 class="modal-title">Serie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="ser_id" value="0">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Módulo</label><select class="form-select" name="modulo" id="ser_modulo"><option>VENTAS</option><option>COMPRAS</option></select></div>
                        <div class="col-md-3"><label class="form-label">Tipo documento</label><input class="form-control" name="tipo_documento" id="ser_tipo_documento" required></div>
                        <div class="col-md-3"><label class="form-label">Serie</label><input class="form-control" name="codigo_serie" id="ser_codigo_serie" required></div>
                        <div class="col-md-3"><label class="form-label">Prefijo</label><input class="form-control" name="prefijo" id="ser_prefijo" required></div>
                        <div class="col-md-4"><label class="form-label">Correlativo actual</label><input type="number" min="0" class="form-control" name="correlativo_actual" id="ser_correlativo_actual" required></div>
                        <div class="col-md-4"><label class="form-label">Longitud</label><input type="number" min="3" max="12" class="form-control" name="longitud_correlativo" id="ser_longitud_correlativo" value="6" required></div>
                        <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado" id="ser_estado"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
                        <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="predeterminada" id="ser_predeterminada"><label class="form-check-label" for="ser_predeterminada">Marcar como predeterminada</label></div></div>
                        <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" rows="2" name="observaciones" id="ser_observaciones"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
