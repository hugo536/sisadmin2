<?php
$almacenes = $almacenes ?? [];
$filtros = $filtros ?? [];
$resumen = $resumen ?? ['activos' => 0, 'inactivos' => 0, 'ultimos' => [], 'sin_actividad' => []];
$flash = $flash ?? ['tipo' => '', 'texto' => ''];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h2 class="mb-1">Catálogo de Almacenes</h2>
        <p class="text-muted mb-0">Administra códigos, estados y disponibilidad de almacenes.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAlmacen" data-modo="nuevo">
        <i class="bi bi-plus-lg me-1"></i>Nuevo almacén
    </button>
</div>

<?php if (!empty($flash['texto'])): ?>
    <div class="alert alert-<?php echo ($flash['tipo'] ?? '') === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
        <?php echo e((string) $flash['texto']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Activos</div>
            <div class="fs-4 fw-semibold"><?php echo (int) ($resumen['activos'] ?? 0); ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Inactivos</div>
            <div class="fs-4 fw-semibold"><?php echo (int) ($resumen['inactivos'] ?? 0); ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Últimos creados</div>
            <div class="small">
                <?php foreach (($resumen['ultimos'] ?? []) as $u): ?>
                    <div><?php echo e((string) $u['codigo']); ?> · <?php echo e((string) $u['nombre']); ?></div>
                <?php endforeach; ?>
            </div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <input type="hidden" name="ruta" value="almacenes/index">
            <div class="col-lg-3">
                <input type="text" name="q" class="form-control" placeholder="Buscar código o nombre" value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
            </div>
            <div class="col-lg-2">
                <select name="estado_filtro" class="form-select">
                    <?php $ef = (string) ($filtros['estado_filtro'] ?? 'activos'); ?>
                    <option value="activos" <?php echo $ef === 'activos' ? 'selected' : ''; ?>>Solo activos</option>
                    <option value="inactivos" <?php echo $ef === 'inactivos' ? 'selected' : ''; ?>>Solo inactivos</option>
                    <option value="eliminados" <?php echo $ef === 'eliminados' ? 'selected' : ''; ?>>Eliminados</option>
                    <option value="todos" <?php echo $ef === 'todos' ? 'selected' : ''; ?>>Todos</option>
                </select>
            </div>
            <div class="col-lg-2"><input type="date" name="fecha_desde" class="form-control" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>"></div>
            <div class="col-lg-2"><input type="date" name="fecha_hasta" class="form-control" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>"></div>
            <div class="col-lg-2">
                <select name="orden" class="form-select">
                    <?php $ord = (string) ($filtros['orden'] ?? 'nombre_asc'); ?>
                    <option value="nombre_asc" <?php echo $ord === 'nombre_asc' ? 'selected' : ''; ?>>Nombre A-Z</option>
                    <option value="nombre_desc" <?php echo $ord === 'nombre_desc' ? 'selected' : ''; ?>>Nombre Z-A</option>
                    <option value="codigo_asc" <?php echo $ord === 'codigo_asc' ? 'selected' : ''; ?>>Código A-Z</option>
                    <option value="codigo_desc" <?php echo $ord === 'codigo_desc' ? 'selected' : ''; ?>>Código Z-A</option>
                    <option value="fecha_desc" <?php echo $ord === 'fecha_desc' ? 'selected' : ''; ?>>Más recientes</option>
                    <option value="fecha_asc" <?php echo $ord === 'fecha_asc' ? 'selected' : ''; ?>>Más antiguos</option>
                </select>
            </div>
            <div class="col-lg-1 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Creación</th><th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($almacenes)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay almacenes para los filtros aplicados.</td></tr>
            <?php else: foreach ($almacenes as $a): ?>
                <?php $eliminado = !empty($a['deleted_at']); ?>
                <tr>
                    <td class="fw-semibold"><?php echo e((string) $a['codigo']); ?></td>
                    <td><?php echo e((string) $a['nombre']); ?></td>
                    <td><?php echo e((string) ($a['descripcion'] ?? '')); ?></td>
                    <td>
                        <?php if ($eliminado): ?>
                            <span class="badge text-bg-secondary">Eliminado</span>
                        <?php elseif ((int) $a['estado'] === 1): ?>
                            <span class="badge text-bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge text-bg-warning">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo e((string) $a['created_at']); ?></td>
                    <td class="text-end">
                        <?php if (!$eliminado): ?>
                            <button class="btn btn-sm btn-outline-primary btn-editar" data-bs-toggle="modal" data-bs-target="#modalAlmacen"
                                data-id="<?php echo (int) $a['id']; ?>"
                                data-codigo="<?php echo e((string) $a['codigo']); ?>"
                                data-nombre="<?php echo e((string) $a['nombre']); ?>"
                                data-descripcion="<?php echo e((string) ($a['descripcion'] ?? '')); ?>"
                                data-estado="<?php echo (int) $a['estado']; ?>">Editar</button>

                            <form class="d-inline" method="post" action="<?php echo e(route_url('almacenes/cambiarEstado')); ?>">
                                <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                <input type="hidden" name="estado" value="<?php echo (int) $a['estado'] === 1 ? 0 : 1; ?>">
                                <button class="btn btn-sm btn-outline-secondary"><?php echo (int) $a['estado'] === 1 ? 'Desactivar' : 'Activar'; ?></button>
                            </form>

                            <form class="d-inline" method="post" action="<?php echo e(route_url('almacenes/eliminar')); ?>" onsubmit="return confirm('¿Eliminar lógicamente este almacén?');">
                                <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        <?php else: ?>
                            <form class="d-inline" method="post" action="<?php echo e(route_url('almacenes/restaurar')); ?>">
                                <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                <button class="btn btn-sm btn-outline-success">Restaurar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h6 class="mb-2">Almacenes sin actividad reciente (30+ días)</h6>
        <?php if (empty($resumen['sin_actividad'])): ?>
            <div class="text-muted small">Todos los almacenes tienen actividad reciente.</div>
        <?php else: ?>
            <ul class="mb-0 small">
                <?php foreach ($resumen['sin_actividad'] as $s): ?>
                    <li><?php echo e((string) $s['codigo']); ?> · <?php echo e((string) $s['nombre']); ?>
                        (<?php echo empty($s['ultima_actividad']) ? 'Sin movimientos' : 'Última: ' . e((string) $s['ultima_actividad']); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalAlmacen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?php echo e(route_url('almacenes/guardar')); ?>">
            <div class="modal-header">
                <h5 class="modal-title">Guardar almacén</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="almacenId" value="0">
                <div class="mb-2">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-control" name="codigo" id="almacenCodigo" maxlength="30" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" class="form-control" name="nombre" id="almacenNombre" maxlength="120" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" id="almacenDescripcion" maxlength="255"></textarea>
                </div>
                <div>
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado" id="almacenEstado">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
