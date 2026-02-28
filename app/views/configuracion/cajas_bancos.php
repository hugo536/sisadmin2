<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$flash = $flash ?? ['tipo' => '', 'texto' => ''];

$badgeTipo = static function (string $tipo): string {
    return match (strtoupper($tipo)) {
        'CAJA' => '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Caja</span>',
        'BILLETERA' => '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Billetera</span>',
        'OTROS' => '<span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Otros</span>',
        default => '<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">Banco</span>',
    };
};
?>

<div class="container-fluid p-4 cajas-bancos-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark"><i class="bi bi-bank2 me-2 text-primary"></i>Cajas y Bancos</h1>
            <p class="text-muted small mb-0">Catálogo central de cuentas operativas para cobros y pagos.</p>
        </div>
        <?php if (tiene_permiso('config.editar')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCajaBanco">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Registro
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($flash['texto'])): ?>
        <div class="alert <?php echo $flash['tipo'] === 'error' ? 'alert-danger' : 'alert-success'; ?> border-0 shadow-sm">
            <?php echo e((string) $flash['texto']); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form class="row g-2 mb-3" method="get" action="<?php echo e(route_url('cajas_bancos/index')); ?>" id="cbFiltersForm">
                <input type="hidden" name="ruta" value="cajas_bancos/index">
                <div class="col-12 col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control" name="q" id="cbFiltroBusqueda" placeholder="Buscar por código, nombre, entidad o titular" value="<?php echo e((string) ($filtros['q'] ?? '')); ?>" autocomplete="off">
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <select class="form-select" name="tipo" id="cbFiltroTipo">
                        <option value="">Tipo (todos)</option>
                        <?php foreach (['CAJA' => 'Caja', 'BANCO' => 'Banco', 'BILLETERA' => 'Billetera', 'OTROS' => 'Otros'] as $k => $v): ?>
                            <option value="<?php echo e($k); ?>" <?php echo (($filtros['tipo'] ?? '') === $k) ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <select class="form-select" name="estado_filtro" id="cbFiltroEstado">
                        <option value="activos" <?php echo (($filtros['estado_filtro'] ?? '') === 'activos') ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivos" <?php echo (($filtros['estado_filtro'] ?? '') === 'inactivos') ? 'selected' : ''; ?>>Inactivos</option>
                        <option value="todos" <?php echo (($filtros['estado_filtro'] ?? '') === 'todos') ? 'selected' : ''; ?>>Todos</option>
                        <option value="eliminados" <?php echo (($filtros['estado_filtro'] ?? '') === 'eliminados') ? 'selected' : ''; ?>>Eliminados</option>
                    </select>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-pro align-middle mb-0" id="cajasBancosTable"
                   data-erp-table="true"
                   data-search-input="#cbFiltroBusqueda"
                   data-pagination-controls="#cajasBancosPaginationControls"
                   data-pagination-info="#cajasBancosPaginationInfo">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Entidad / Cuenta</th>
                            <th class="text-center">Uso</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($registros)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Sin registros para los filtros seleccionados.</td></tr>
                    <?php else: foreach ($registros as $r): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?php echo e((string) ($r['codigo'] ?? '')); ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo e((string) ($r['nombre'] ?? '')); ?></div>
                                <?php if (!empty($r['observaciones'])): ?><small class="text-muted"><?php echo e((string) $r['observaciones']); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo $badgeTipo((string) ($r['tipo'] ?? 'BANCO')); ?></td>
                            <td>
                                <div><?php echo e((string) ($r['entidad'] ?? '-')); ?></div>
                                <small class="text-muted"><?php echo e((string) ($r['tipo_cuenta'] ?? '-')); ?> · <?php echo e((string) ($r['moneda'] ?? 'PEN')); ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ((int)($r['permite_cobros'] ?? 0) === 1): ?><span class="badge bg-success-subtle text-success-emphasis border me-1">Cobros</span><?php endif; ?>
                                <?php if ((int)($r['permite_pagos'] ?? 0) === 1): ?><span class="badge bg-warning-subtle text-warning-emphasis border">Pagos</span><?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($r['deleted_at'])): ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis border">Eliminado</span>
                                <?php else: ?>
                                    <span class="badge <?php echo ((int) ($r['estado'] ?? 0) === 1) ? 'bg-success-subtle text-success-emphasis border' : 'bg-secondary-subtle text-secondary-emphasis border'; ?>" id="badge_status_cb_<?php echo (int) $r['id']; ?>">
                                        <?php echo ((int) ($r['estado'] ?? 0) === 1) ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (empty($r['deleted_at'])): ?>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="form-check form-switch pt-1" title="Cambiar estado">
                                            <input class="form-check-input switch-estado-cb" type="checkbox" role="switch"
                                                   style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                   data-id="<?php echo (int) $r['id']; ?>"
                                                   <?php echo ((int) ($r['estado'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                        </div>

                                        <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar-cb"
                                                title="Editar"
                                                data-bs-toggle="modal" data-bs-target="#modalCajaBanco"
                                                data-id="<?php echo (int) $r['id']; ?>"
                                                data-codigo="<?php echo e((string) ($r['codigo'] ?? '')); ?>"
                                                data-nombre="<?php echo e((string) ($r['nombre'] ?? '')); ?>"
                                                data-tipo="<?php echo e((string) ($r['tipo'] ?? 'BANCO')); ?>"
                                                data-entidad="<?php echo e((string) ($r['entidad'] ?? '')); ?>"
                                                data-tipo-cuenta="<?php echo e((string) ($r['tipo_cuenta'] ?? '')); ?>"
                                                data-moneda="<?php echo e((string) ($r['moneda'] ?? 'PEN')); ?>"
                                                data-titular="<?php echo e((string) ($r['titular'] ?? '')); ?>"
                                                data-numero-cuenta="<?php echo e((string) ($r['numero_cuenta'] ?? '')); ?>"
                                                data-permite-cobros="<?php echo (int) ($r['permite_cobros'] ?? 0); ?>"
                                                data-permite-pagos="<?php echo (int) ($r['permite_pagos'] ?? 0); ?>"
                                                data-estado="<?php echo (int) ($r['estado'] ?? 1); ?>"
                                                data-observaciones="<?php echo e((string) ($r['observaciones'] ?? '')); ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>

                                        <form class="d-inline m-0" method="post" action="<?php echo e(route_url('cajas_bancos/eliminar')); ?>" onsubmit="return confirm('¿Eliminar este registro?');">
                                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                            <button class="btn btn-sm btn-light text-danger border-0 bg-transparent" title="Eliminar">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form class="d-inline" method="post" action="<?php echo e(route_url('cajas_bancos/restaurar')); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                        <button class="btn btn-sm btn-light border text-success" title="Restaurar">
                                            <i class="bi bi-arrow-clockwise"></i>
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

<div class="modal fade" id="modalCajaBanco" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="post" action="<?php echo e(route_url('cajas_bancos/guardar')); ?>">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Nuevo registro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <input type="hidden" name="id" id="cbId" value="0">
                <div class="row g-2">
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" id="cbCodigo" name="codigo" maxlength="30" required placeholder="Código"><label for="cbCodigo">Código</label></div></div>
                    <div class="col-md-8"><div class="form-floating"><input class="form-control" id="cbNombre" name="nombre" maxlength="120" required placeholder="Nombre"><label for="cbNombre">Nombre</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><select class="form-select" id="cbTipo" name="tipo"><option value="CAJA">Caja</option><option value="BANCO">Banco</option><option value="BILLETERA">Billetera</option><option value="OTROS">Otros</option></select><label for="cbTipo">Tipo</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" id="cbEntidad" name="entidad" placeholder="Entidad"><label for="cbEntidad">Entidad</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><select class="form-select" id="cbMoneda" name="moneda"><option value="PEN">PEN</option><option value="USD">USD</option></select><label for="cbMoneda">Moneda</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" id="cbTipoCuenta" name="tipo_cuenta" placeholder="Tipo de cuenta"><label for="cbTipoCuenta">Tipo de Cuenta</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" id="cbTitular" name="titular" placeholder="Titular"><label for="cbTitular">Titular</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><input class="form-control" id="cbNumeroCuenta" name="numero_cuenta" placeholder="Número / CCI"><label for="cbNumeroCuenta">Número / CCI</label></div></div>
                    <div class="col-md-12"><div class="form-floating"><textarea class="form-control" id="cbObservaciones" name="observaciones" style="height: 85px" placeholder="Observaciones"></textarea><label for="cbObservaciones">Observaciones</label></div></div>
                    <div class="col-md-4"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" id="cbPermiteCobros" name="permite_cobros" value="1"><label class="form-check-label" for="cbPermiteCobros">Permite cobros</label></div></div>
                    <div class="col-md-4"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" id="cbPermitePagos" name="permite_pagos" value="1"><label class="form-check-label" for="cbPermitePagos">Permite pagos</label></div></div>
                    <div class="col-md-4"><div class="form-floating"><select class="form-select" id="cbEstado" name="estado"><option value="1">Activo</option><option value="0">Inactivo</option></select><label for="cbEstado">Estado</label></div></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
