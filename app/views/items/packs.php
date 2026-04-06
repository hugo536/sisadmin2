<?php
// Variables esperadas desde el controlador
$packs = $packs ?? []; 
$titulo = $titulo ?? 'Packs y Combos Comerciales';
?>

<meta name="csrf-token" content="<?= e((string) ($csrf_token ?? '')) ?>">

<div class="container-fluid p-4" id="packsApp">
    
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-boxes me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?>
            </h1>
            <p class="text-muted small mb-0 ms-1">Crea y configura recetas comerciales, promociones y kits de venta.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(route_url('items')) ?>" class="btn btn-light shadow-sm text-secondary fw-semibold border">
                <i class="bi bi-box-seam me-2"></i>Ir a Maestro de Ítems
            </a>
        </div>
    </div>

    <div class="row g-4 fade-in">
        
        <div class="col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">Catálogo de Packs</h6>
                    <button class="btn btn-sm btn-primary fw-bold shadow-sm" id="btnNuevoPack">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                </div>
                <div class="p-2 bg-light border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-white border-start-0 ps-0 shadow-none" id="buscarPack" placeholder="Buscar combo o pack...">
                    </div>
                </div>
                
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    <div class="list-group list-group-flush" id="listaPacks">
                        <?php if(!empty($packs)): ?>
                            <?php foreach($packs as $pack): ?>
                                <button type="button" class="list-group-item list-group-item-action p-3 pack-item-btn" 
                                        data-id="<?= (int)$pack['id'] ?>" 
                                        data-nombre="<?= htmlspecialchars($pack['nombre']) ?>"
                                        data-precio="<?= (float)$pack['precio_venta'] ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($pack['nombre']) ?></div>
                                        <span class="badge bg-primary rounded-pill">S/ <?= number_format((float)$pack['precio_venta'], 2) ?></span>
                                    </div>
                                    <div class="small text-muted mt-1"><i class="bi bi-upc-scan me-1"></i><?= htmlspecialchars($pack['sku']) ?></div>
                                </button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-light"></i>
                                <small>Crea tu primer combo haciendo clic en "Nuevo".</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xl-9">
            <div class="card border-0 shadow-sm h-100 bg-white">
                
                <div id="panelVacio" class="card-body d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                    <div class="bg-light rounded-circle p-4 mb-3">
                        <i class="bi bi-diagram-3 text-secondary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Crea o Selecciona un Pack</h5>
                    <p class="mb-0">Haz clic en "Nuevo" para inventar un combo, o selecciona uno existente para editarlo.</p>
                </div>

                <div id="panelConfiguracion" class="d-none flex-column h-100">
                    
                    <form id="formPackPadre" class="card-header bg-white border-bottom p-4">
                        <input type="hidden" id="idPackSeleccionado" value="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="lblEstadoPack">Nuevo Combo</span>
                            <button type="button" class="btn-close d-lg-none" aria-label="Cerrar" id="btnCerrarPanelMobile"></button>
                        </div>

                        <div class="row g-3 align-items-start">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold mb-1">Nombre del Combo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control fw-bold shadow-none border-primary-subtle" id="inputNombrePack" placeholder="Ej. BIDÓN NUEVO LLENO 20L" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold mb-1">Precio Público (S/) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control fw-bold text-success shadow-none border-success-subtle" id="inputPrecioPack" step="0.01" min="0" placeholder="25.00" required>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-outline-danger shadow-sm d-none" id="btnEliminarPack" title="Eliminar combo">
                                    <i class="bi bi-trash3"></i>
                                </button>
                                <button type="submit" class="btn btn-primary fw-bold w-100 shadow-sm" id="btnGuardarPack">
                                    <i class="bi bi-save me-1"></i>Guardar
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="card-body p-4 bg-light" id="seccionComponentes" style="opacity: 0.5; pointer-events: none;">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-link-45deg me-1"></i>Componentes del Combo</h6>
                        
                        <form id="formAgregarComponente" class="row g-2 align-items-end mb-4 p-3 bg-white border border-secondary-subtle rounded-3 shadow-sm">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold mb-1">Buscar Componente <span class="text-danger">*</span></label>
                                <select class="form-select shadow-none" id="selectComponente" required>
                                    <option value=""></option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold mb-1">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control shadow-none" id="inputCantidad" min="0.01" step="0.01" value="1" required>
                            </div>

                            <div class="col-md-2 d-flex align-items-center h-100">
                                <div class="form-check form-switch w-100 mt-2 p-2 border border-secondary-subtle rounded text-center bg-light" data-bs-toggle="tooltip" title="Al marcarlo, sabes que es un regalo para el cliente">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="checkBonificacion">
                                    <label class="form-check-label small fw-bold text-dark" for="checkBonificacion">Regalo</label>
                                </div>
                            </div>
                            
                            <div class="col-md-2 text-end">
                                <button type="submit" class="btn btn-dark fw-bold w-100 shadow-sm">
                                    Añadir
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive bg-white border rounded-3 shadow-sm">
                            <table class="table table-hover align-middle mb-0 table-pro" id="tablaComponentes">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 text-secondary fw-semibold">Ítem Componente</th>
                                        <th class="text-center text-secondary fw-semibold">Cantidad</th>
                                        <th class="text-center text-secondary fw-semibold">Tipo</th>
                                        <th class="text-center text-secondary fw-semibold">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="filaVacia"><td colspan="4" class="text-center text-muted py-4">Guarda el Combo primero para añadir componentes.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<template id="templateFilaComponente">
    <tr class="border-bottom">
        <td class="ps-4 text-dark fw-medium td-nombre">Nombre del Producto</td>
        <td class="text-center fw-bold text-primary fs-6 td-cantidad">1.00</td>
        <td class="text-center">
            <span class="badge rounded-pill badge-tipo">Componente</span>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-light text-danger border-0 rounded-circle btn-eliminar-componente" data-bs-toggle="tooltip" title="Eliminar componente">
                <i class="bi bi-trash-fill fs-6"></i>
            </button>
        </td>
    </tr>
</template>

<script>
    document.addEventListener("DOMContentLoaded", function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="<?= e(asset_url('js/items/packs.js')) ?>?v=<?= time() ?>"></script>
