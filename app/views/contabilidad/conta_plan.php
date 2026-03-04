<?php
$err = (string)($_GET['error'] ?? '');
$ok = (string)($_GET['ok'] ?? '');
$cuentas = $cuentas ?? [];
$cuentasMovimiento = $cuentasMovimiento ?? [];
$parametros = $parametros ?? [];
$cuentasTesoreria = $cuentasTesoreria ?? [];

$paramLabels = [];

// 1. Agregamos dinámicamente las cuentas de tesorería a los labels 
// para que se lean correctamente en la tabla de "Parámetros Vigentes"
if (!empty($cuentasTesoreria)) {
    foreach ($cuentasTesoreria as $ct) {
        $codigo = (string)($ct['codigo'] ?? '');
        if ($codigo !== '') {
            $paramLabels[$codigo] = $codigo . ' - ' . (string)($ct['nombre'] ?? '');
        }
    }
}

$swalIcon = null;
$swalMessage = null;

if ($err !== '') {
    $swalIcon = 'error';
    $swalMessage = $err;
} elseif ($ok !== '') {
    $swalIcon = 'success';
    $swalMessage = 'Operación realizada correctamente.';
}
?>
<div class="container-fluid p-4">

    <?php if ($swalMessage !== null): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal === 'undefined') {
                    return;
                }
                Swal.fire({
                    icon: <?php echo json_encode($swalIcon); ?>,
                    title: <?php echo json_encode($swalIcon === 'error' ? 'Error' : 'Éxito'); ?>,
                    text: <?php echo json_encode($swalMessage); ?>,
                    confirmButtonText: 'Entendido'
                });
            });
        </script>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-bookmark-fill me-2 text-primary"></i> Plan Contable
            </h1>
            <p class="text-muted small mb-0 ms-1">Catálogo general de cuentas y configuración de parámetros financieros.</p>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalParametrosVigentes">
                <i class="bi bi-link-45deg me-2 text-primary"></i>Parámetros Vigentes
            </button>
            <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalParametros">
                <i class="bi bi-gear-fill me-2 text-info"></i>Configurar Parámetros
            </button>
            <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevaCuenta">
                <i class="bi bi-plus-circle-fill me-2"></i>Nueva Cuenta
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-nested text-primary me-2"></i>Estructura de Cuentas</h6>
            
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="buscarCuenta" class="form-control border-start-0 shadow-none" placeholder="Buscar cuenta o código...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="planContableTable" class="table align-middle mb-0 table-hover table-pro" 
                    data-erp-table="true" 
                    data-search-input="#buscarCuenta" 
                    data-rows-per-page="50">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold" style="width: 15%;">Código</th>
                            <th class="text-secondary fw-semibold">Nombre de la Cuenta</th>
                            <th class="text-secondary fw-semibold">Clasificación (Tipo)</th>
                            <th class="text-center text-secondary fw-semibold">Nivel</th>
                            <th class="text-center text-secondary fw-semibold">Acepta Mov.</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($cuentas)): ?>
                            <?php foreach ($cuentas as $c): ?>
                                <?php 
                                    $esActivo = ((int)$c['estado'] === 1);
                                    $aceptaMov = ((int)$c['permite_movimiento'] === 1);
                                    
                                    $tipoColor = 'text-secondary';
                                    switch(strtoupper($c['tipo'])) {
                                        case 'ACTIVO': $tipoColor = 'text-success'; break;
                                        case 'PASIVO': $tipoColor = 'text-danger'; break;
                                        case 'PATRIMONIO': $tipoColor = 'text-warning text-dark'; break;
                                        case 'INGRESO': $tipoColor = 'text-info text-dark'; break;
                                        case 'GASTO': $tipoColor = 'text-primary'; break;
                                    }

                                    $nivel = (int)$c['nivel'];
                                    $paddingLeft = ($nivel > 1) ? ($nivel * 15) . 'px' : '0px';
                                ?>
                                <tr class="border-bottom <?php echo !$esActivo ? 'opacity-50 bg-light' : ''; ?>" 
                                     data-search="<?php echo e($c['codigo'] . ' ' . $c['nombre'] . ' ' . $c['tipo']); ?>">
                                    <td class="ps-4 font-monospace fw-bold <?php echo !$aceptaMov ? 'text-dark' : 'text-primary'; ?> pt-3" style="padding-left: <?php echo $paddingLeft; ?> !important;">
                                        <?php if($nivel > 1): ?> <i class="bi bi-arrow-return-right text-muted me-1" style="font-size: 0.8rem;"></i> <?php endif; ?>
                                        <?php echo e($c['codigo']); ?>
                                    </td>
                                    <td class="<?php echo !$aceptaMov ? 'fw-bold text-dark' : 'text-body'; ?> pt-3">
                                        <?php echo e($c['nombre']); ?>
                                    </td>
                                    <td class="fw-medium small <?php echo $tipoColor; ?> pt-3">
                                        <?php echo e($c['tipo']); ?>
                                    </td>
                                    <td class="text-center text-muted fw-bold pt-3">
                                        Lv. <?php echo $nivel; ?>
                                    </td>
                                    <td class="text-center pt-3">
                                        <span class="badge rounded-pill <?php echo $aceptaMov ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?>">
                                            <?php echo $aceptaMov ? 'Sí (Transaccional)' : 'No (Agrupadora)'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center pt-3">
                                        <span class="badge rounded-pill badge-estado-cuenta <?php echo $esActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>" data-estado-badge>
                                            <?php echo $esActivo ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <form method="post" action="<?php echo e(route_url('contabilidad/cambiar_estado_cuenta')); ?>" class="m-0 form-cambiar-estado-cuenta" data-codigo="<?php echo e($c['codigo']); ?>" data-nombre="<?php echo e($c['nombre']); ?>">
                                                <input type="hidden" name="id_cuenta" value="<?php echo (int)$c['id']; ?>">
                                                <input type="hidden" name="estado" value="<?php echo $esActivo ? 1 : 0; ?>">
                                                <div class="form-check form-switch pt-1 m-0" title="<?php echo $esActivo ? 'Inactivar cuenta' : 'Activar cuenta'; ?>">
                                                    <input class="form-check-input switch-estado-cuenta" type="checkbox" role="switch" data-estado-actual="<?php echo $esActivo ? 1 : 0; ?>" <?php echo $esActivo ? 'checked' : ''; ?>>
                                                </div>
                                            </form>

                                            <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                            <button type="button"
                                                class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar-cuenta"
                                                title="Editar cuenta"
                                                data-id="<?php echo (int)$c['id']; ?>"
                                                data-codigo="<?php echo e($c['codigo']); ?>"
                                                data-nombre="<?php echo e($c['nombre']); ?>"
                                                data-tipo="<?php echo e($c['tipo']); ?>"
                                                data-nivel="<?php echo (int)$c['nivel']; ?>"
                                                data-permite-movimiento="<?php echo (int)$c['permite_movimiento']; ?>"
                                                data-estado="<?php echo (int)$c['estado']; ?>">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-journal-x fs-1 d-block mb-2 text-light"></i>
                                    El plan contable está vacío. Registre su primera cuenta.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center p-3">
                <span id="planContablePaginationInfo" class="small text-muted fw-semibold"></span>
                <ul id="planContablePaginationControls" class="pagination pagination-sm mb-0"></ul>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="modalNuevaCuenta" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar Cuenta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('contabilidad/guardar_cuenta')); ?>">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Código <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none font-monospace fw-bold" name="codigo" placeholder="Ej. 101" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted fw-bold">Nombre de la Cuenta <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="nombre" placeholder="Ej. Caja General" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Clasificación <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="tipo" required>
                                        <option value="ACTIVO">ACTIVO</option>
                                        <option value="PASIVO">PASIVO</option>
                                        <option value="PATRIMONIO">PATRIMONIO</option>
                                        <option value="INGRESO">INGRESO</option>
                                        <option value="GASTO">GASTO</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Nivel Jerárquico <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control shadow-none" min="1" name="nivel" value="1" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">Cuenta Padre</label>
                                    <select class="form-select shadow-none" name="id_padre">
                                        <option value="0">-- Es una cuenta principal (Sin padre) --</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">¿Acepta Movimientos Directos? <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none border-primary" name="permite_movimiento" required>
                                        <option value="1">Sí (Cuenta transaccional para asientos)</option>
                                        <option value="0">No (Es una cuenta agrupadora/título)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Guardar Cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalParametros" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear-wide-connected me-2"></i>Configurar Parámetros</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <div class="alert alert-info small border-0 shadow-sm mb-4">
                    <i class="bi bi-info-circle-fill me-1"></i> Asigne las cuentas específicas que el sistema utilizará de forma predeterminada para operaciones automáticas y tesorería.
                </div>
                
                <form method="post" action="<?php echo e(route_url('contabilidad/guardar_parametro')); ?>">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold">Parámetro del Sistema / Tesorería <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none font-monospace text-primary fw-bold" name="clave" id="parametroClave" required>
                                        <option value="">Seleccione cuenta de tesorería...</option>
                                        <?php if (!empty($cuentasTesoreria)): ?>
                                            <?php foreach ($cuentasTesoreria as $ct): ?>
                                                <option value="<?php echo e((string)($ct['codigo'] ?? '')); ?>">
                                                    <?php echo e((string)($ct['codigo'] ?? '') . ' - ' . (string)($ct['nombre'] ?? '')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No hay cuentas de tesorería registradas</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold">Cuenta Contable a Asignar <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta" id="parametroCuenta" required>
                                        <option value="">Seleccione cuenta transaccional...</option>
                                        <?php foreach ($cuentasMovimiento as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'].' - '.$c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-secondary px-4 fw-bold"><i class="bi bi-check2-circle me-2"></i>Asignar Parámetro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalParametrosVigentes" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-link-45deg me-2"></i>Parámetros Contables Vigentes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <div class="card border-0 shadow-sm">
                    <div class="card-body pb-2">
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col-md-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                    <input type="text" id="buscarParametroVigente" class="form-control shadow-none" placeholder="Buscar por clave, código o cuenta...">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <select id="filtroClaveParametroVigente" class="form-select form-select-sm shadow-none">
                                    <option value="">Todas las claves</option>
                                    <?php foreach ($parametros as $p): ?>
                                        <option value="<?php echo e((string)($p['clave'] ?? '')); ?>"><?php echo e((string)($p['clave'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Clave</th>
                                        <th class="text-center">Cuenta vinculada</th>
                                        <th class="text-end pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaParametrosVigentesBody">
                                    <?php if (!empty($parametros)): ?>
                                        <?php foreach ($parametros as $p): ?>
                                            <?php
                                                $claveParam = strtolower((string)($p['clave'] ?? ''));
                                                // Actualizamos el filtro de búsqueda para que también busque por el nombre legible de la caja
                                                $nombreLegible = $paramLabels[(string)($p['clave'] ?? '')] ?? (string)($p['clave'] ?? '');
                                                $searchParam = strtolower(trim((string)($p['clave'] ?? '') . ' ' . $nombreLegible . ' ' . (string)($p['cuenta_codigo'] ?? '') . ' ' . (string)($p['cuenta_nombre'] ?? '')));
                                            ?>
                                            <tr data-param-row="true" data-clave="<?php echo e($claveParam); ?>" data-search="<?php echo e($searchParam); ?>">
                                                <td class="ps-3 fw-semibold text-primary">
                                                    <?php echo e((string)($paramLabels[(string)($p['clave'] ?? '')] ?? (string)($p['clave'] ?? ''))); ?>
                                                </td>
                                                <td class="text-center"><?php echo e((string)($p['cuenta_codigo'] ?? '') . ' - ' . (string)($p['cuenta_nombre'] ?? '')); ?></td>
                                                <td class="text-end pe-3">
                                                    <div class="d-inline-flex gap-2">
                                                        <button type="button"
                                                                class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar-parametro"
                                                                data-clave="<?php echo e((string)($p['clave'] ?? '')); ?>"
                                                                data-id-cuenta="<?php echo (int)($p['id_cuenta'] ?? 0); ?>"
                                                                title="Editar parámetro">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <form method="post" action="<?php echo e(route_url('contabilidad/eliminar_parametro')); ?>" class="d-inline m-0" onsubmit="return confirm('¿Eliminar este parámetro contable?');">
                                                            <input type="hidden" name="id_parametro" value="<?php echo (int)($p['id'] ?? 0); ?>">
                                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent" title="Eliminar parámetro">
                                                                <i class="bi bi-trash3"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr id="filaSinParametrosFiltrados" class="d-none">
                                            <td colspan="3" class="text-center text-muted py-4">No hay resultados con los filtros actuales.</td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">No hay parámetros contables configurados todavía.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarCuenta" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Cuenta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('contabilidad/guardar_cuenta')); ?>">
                    
                    <input type="hidden" name="id" value="">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Código <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none font-monospace fw-bold bg-light" name="codigo" readonly>
                                    <small class="text-muted" style="font-size: 0.7rem;">No modificable</small>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted fw-bold">Nombre de la Cuenta <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="nombre" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Clasificación <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="tipo" required>
                                        <option value="ACTIVO">ACTIVO</option>
                                        <option value="PASIVO">PASIVO</option>
                                        <option value="PATRIMONIO">PATRIMONIO</option>
                                        <option value="INGRESO">INGRESO</option>
                                        <option value="GASTO">GASTO</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Nivel <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control shadow-none" min="1" name="nivel" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Estado <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="estado" required>
                                        <option value="1">Activa</option>
                                        <option value="0">Inactiva</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">¿Acepta Movimientos Directos? <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none border-primary" name="permite_movimiento" required>
                                        <option value="1">Sí (Cuenta transaccional para asientos)</option>
                                        <option value="0">No (Es una cuenta agrupadora/título)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Actualizar Cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(asset_url('js/contabilidad/plan_contable.js')); ?>?v=<?php echo time(); ?>"></script>