<?php
$almacenes = $almacenes ?? [];
$filtros = $filtros ?? [];
$resumen = $resumen ?? ['activos' => 0, 'inactivos' => 0, 'ultimos' => [], 'sin_actividad' => []];
$flash = $flash ?? ['tipo' => '', 'texto' => ''];
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-boxes me-2 text-primary"></i> Catálogo de Almacenes
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra códigos, estados y disponibilidad de almacenes.</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAlmacen" data-modo="nuevo">
            <i class="bi bi-plus-circle me-2"></i>Nuevo almacén
        </button>
    </div>

    <?php if (!empty($flash['texto'])): ?>
        <div class="alert alert-<?php echo ($flash['tipo'] ?? '') === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo e((string) $flash['texto']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>


    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Almacenes activos</div>
                <div class="fs-3 fw-bold text-success"><?php echo (int) ($resumen['activos'] ?? 0); ?></div>
            </div></div>
        </div>
    </div>


    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center" id="filtrosAlmacenesForm">
                <input type="hidden" name="ruta" value="almacenes/index">

                <div class="col-12 col-lg-8">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control bg-light border-start-0 ps-0" id="filtroBusquedaAlmacen" placeholder="Buscar código o nombre" value="<?php echo e((string) ($filtros['q'] ?? '')); ?>" autocomplete="off">
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <select name="estado_filtro" class="form-select bg-light" id="filtroEstadoAlmacen">
                        <?php $ef = (string) ($filtros['estado_filtro'] ?? 'activos'); ?>
                        <option value="activos" <?php echo $ef === 'activos' ? 'selected' : ''; ?>>Solo activos</option>
                        <option value="inactivos" <?php echo $ef === 'inactivos' ? 'selected' : ''; ?>>Solo inactivos</option>
                        <option value="eliminados" <?php echo $ef === 'eliminados' ? 'selected' : ''; ?>>Eliminados</option>
                        <option value="todos" <?php echo $ef === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th class="text-center">Estado</th>
                            <th>Creación</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($almacenes)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay almacenes para los filtros aplicados.</td></tr>
                    <?php else: foreach ($almacenes as $a): ?>
                        <?php $eliminado = !empty($a['deleted_at']); ?>
                        <tr>
                            <td class="ps-4 fw-semibold text-secondary"><?php echo e((string) $a['codigo']); ?></td>
                            <td class="fw-semibold"><?php echo e((string) $a['nombre']); ?></td>
                            <td class="text-muted"><?php echo e((string) ($a['descripcion'] ?? '')); ?></td>
                            <td class="text-center">
                                <?php if ($eliminado): ?>
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border">Eliminado</span>
                                <?php elseif ((int) $a['estado'] === 1): ?>
                                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis border">Activo</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?php echo e((string) $a['created_at']); ?></td>
                            <td class="text-end pe-4">
                                <?php if (!$eliminado): ?>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <form class="d-inline m-0" method="post" action="<?php echo e(route_url('almacenes/cambiarEstado')); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                            <input type="hidden" name="estado" class="js-estado-destino" value="<?php echo (int) $a['estado']; ?>">
                                            <div class="form-check form-switch pt-1" title="Cambiar estado">
                                                <input class="form-check-input js-switch-estado-almacen" type="checkbox" role="switch"
                                                       style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                       <?php echo (int) $a['estado'] === 1 ? 'checked' : ''; ?>>
                                            </div>
                                        </form>
                                        <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar" title="Editar" data-bs-toggle="modal" data-bs-target="#modalAlmacen"
                                            data-id="<?php echo (int) $a['id']; ?>"
                                            data-codigo="<?php echo e((string) $a['codigo']); ?>"
                                            data-nombre="<?php echo e((string) $a['nombre']); ?>"
                                            data-descripcion="<?php echo e((string) ($a['descripcion'] ?? '')); ?>"
                                            data-estado="<?php echo (int) $a['estado']; ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>

                                        <form class="d-inline m-0" method="post" action="<?php echo e(route_url('almacenes/eliminar')); ?>" onsubmit="return confirm('¿Eliminar lógicamente este almacén?');">
                                            <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                            <button class="btn btn-sm btn-light text-danger border-0 bg-transparent" title="Eliminar">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form class="d-inline m-0" method="post" action="<?php echo e(route_url('almacenes/restaurar')); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                        <button class="btn btn-sm btn-light text-success border-0 bg-transparent" title="Restaurar">
                                            <i class="bi bi-arrow-counterclockwise fs-5"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="modalAlmacen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="post" action="<?php echo e(route_url('almacenes/guardar')); ?>">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-building me-2"></i>Guardar almacén</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <input type="hidden" name="id" id="almacenId" value="0">
                <div class="form-floating mb-2">
                    <input type="text" class="form-control" name="codigo" id="almacenCodigo" maxlength="30" placeholder="Código" required>
                    <label for="almacenCodigo">Código</label>
                </div>
                <div class="form-floating mb-2">
                    <input type="text" class="form-control" name="nombre" id="almacenNombre" maxlength="120" placeholder="Nombre" required>
                    <label for="almacenNombre">Nombre</label>
                </div>
                <div class="form-floating mb-2">
                    <textarea class="form-control" name="descripcion" id="almacenDescripcion" maxlength="255" placeholder="Descripción" style="height: 90px"></textarea>
                    <label for="almacenDescripcion">Descripción</label>
                </div>
                <div class="form-floating">
                    <select class="form-select" name="estado" id="almacenEstado">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    <label for="almacenEstado">Estado</label>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filtrosAlmacenesForm');
    const buscador = document.getElementById('filtroBusquedaAlmacen');
    const estado = document.getElementById('filtroEstadoAlmacen');
    if (!form || !buscador || !estado) return;

    let debounceId = null;
    buscador.addEventListener('input', function () {
        clearTimeout(debounceId);
        debounceId = setTimeout(function () {
            form.submit();
        }, 350);
    });

    estado.addEventListener('change', function () {
        form.submit();
    });


    document.querySelectorAll('.js-switch-estado-almacen').forEach(function (switchInput) {
        switchInput.addEventListener('change', function () {
            const formEstado = this.closest('form');
            if (!formEstado) return;

            const estadoDestino = formEstado.querySelector('.js-estado-destino');
            if (!estadoDestino) return;

            estadoDestino.value = this.checked ? '1' : '0';
            formEstado.submit();
        });
    });


});
</script>
