<?php
// Variables esperadas desde el controlador
$packs = $packs ?? []; 
$titulo = $titulo ?? 'Packs y Combos Comerciales';
?>

<meta name="csrf-token" content="<?= e((string) ($csrf_token ?? '')) ?>">

<style>
    /* Micro-interacciones y ajustes visuales */
    .pack-item-btn {
        transition: all 0.2s ease-in-out;
        border-left: 4px solid transparent !important;
    }
    .pack-item-btn:hover {
        background-color: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        z-index: 1;
    }
    .pack-item-btn.active {
        border-left-color: #0d6efd !important;
        background-color: #f0f7ff;
    }
    .empty-state-icon {
        transition: transform 0.3s ease;
    }
    .empty-state-container:hover .empty-state-icon {
        transform: scale(1.1);
    }
    .card-modern {
        border-radius: 1rem;
        overflow: hidden;
    }
    .table-pro tbody tr {
        transition: background-color 0.15s ease;
    }
    .table-pro tbody tr:hover {
        background-color: #f8f9fa !important;
    }
</style>

<div class="container-fluid p-4" id="packsApp">
    
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h4 fw-bolder mb-1 text-dark d-flex align-items-center tracking-tight">
                <span class="bg-primary text-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-boxes fs-5"></i>
                </span>
                <?= htmlspecialchars($titulo) ?>
            </h1>
            <p class="text-secondary small mb-0 mt-2">Crea y configura recetas comerciales, promociones y kits de venta de forma intuitiva.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(route_url('items')) ?>" class="btn btn-white shadow-sm text-secondary fw-semibold border rounded-pill px-4 hover-lift">
                <i class="bi bi-box-seam me-2"></i>Maestro de Ítems
            </a>
        </div>
    </div>

    <div class="row g-4 fade-in">
        
        <!-- Panel Izquierdo: Lista de Packs -->
        <div class="col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100 card-modern">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">Catálogo</h6>
                    <button class="btn btn-sm btn-primary fw-bold shadow-sm rounded-pill px-3" id="btnNuevoPack">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                </div>
                <div class="p-3 bg-light border-bottom">
                    <div class="input-group input-group-sm bg-white rounded-pill border overflow-hidden focus-ring-primary">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control border-0 shadow-none ps-1" id="buscarPack" placeholder="Buscar combo o pack...">
                    </div>
                </div>
                
                <div class="card-body p-0" style="max-height: calc(100vh - 250px); overflow-y: auto;">
                    <div class="list-group list-group-flush border-top-0" id="listaPacks">
                        <?php if(!empty($packs)): ?>
                            <?php foreach($packs as $pack): ?>
                                <button type="button" class="list-group-item list-group-item-action p-3 border-bottom pack-item-btn" 
                                    data-id="<?= (int)$pack['id'] ?>" 
                                    data-nombre="<?= htmlspecialchars($pack['nombre']) ?>"
                                    data-precio="<?= (float)$pack['precio_venta'] ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold text-dark text-truncate pe-2" style="max-width: 70%;"><?= htmlspecialchars($pack['nombre']) ?></div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">S/ <?= number_format((float)$pack['precio_venta'], 2) ?></span>
                                    </div>
                                    <div class="small text-muted d-flex align-items-center">
                                        <i class="bi bi-upc-scan me-1"></i><?= htmlspecialchars($pack['sku']) ?>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-5 text-center text-muted">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-inbox fs-3 text-secondary"></i>
                                </div>
                                <small class="d-block">Aún no hay combos.<br>Crea el primero haciendo clic en "Nuevo".</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Configuración -->
        <div class="col-lg-8 col-xl-9">
            <div class="card border-0 shadow-sm h-100 bg-white card-modern">
                
                <!-- Estado Vacío Mejorado -->
                <div id="panelVacio" class="card-body d-flex flex-column align-items-center justify-content-center py-5 text-muted empty-state-container">
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center mb-4 empty-state-icon shadow-sm" style="width: 100px; height: 100px;">
                        <i class="bi bi-diagram-3 text-primary" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="fw-bolder text-dark mb-2">Diseña tus combos comerciales</h4>
                    <p class="text-secondary text-center max-w-md">Selecciona un pack de la lista lateral para editar sus componentes, o presiona <strong>Nuevo</strong> para registrar una nueva promoción o receta.</p>
                </div>

                <!-- Panel de Configuración -->
                <div id="panelConfiguracion" class="d-none flex-column h-100">
                    
                    <form id="formPackPadre" class="card-header bg-white border-bottom p-4">
                        <input type="hidden" id="idPackSeleccionado" value="0">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-6" id="lblEstadoPack">
                                <i class="bi bi-star-fill me-1"></i> Nuevo Combo
                            </span>
                            <button type="button" class="btn-close d-lg-none bg-light rounded-circle p-2" aria-label="Cerrar" id="btnCerrarPanelMobile"></button>
                        </div>

                        <div class="row g-4 align-items-start">
                            <div class="col-md-7">
                                <label class="form-label text-secondary small fw-bold mb-2">Nombre del Combo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg fw-bold text-dark shadow-sm border-secondary-subtle" id="inputNombrePack" placeholder="Ej. BIDÓN NUEVO LLENO 20L" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small fw-bold mb-2">Precio al Público (S/) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg shadow-sm border-secondary-subtle rounded">
                                    <span class="input-group-text bg-light text-success fw-bold">S/</span>
                                    <input type="number" class="form-control fw-bold text-success border-start-0 ps-0" id="inputPrecioPack" step="0.01" min="0" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-outline-danger btn-lg shadow-sm d-none p-2" id="btnEliminarPack" title="Eliminar combo">
                                    <i class="bi bi-trash3"></i>
                                </button>
                                <button type="submit" class="btn btn-primary btn-lg fw-bold w-100 shadow-sm" id="btnGuardarPack">
                                    <i class="bi bi-save me-2"></i>Guardar
                                </button>
                            </div>
                        </div>

                        <div class="col-md-12 mt-4 d-none" id="contenedorSwitchEnvase">
                            <div class="form-check form-switch bg-success-subtle border border-success-subtle rounded-4 p-3 d-flex align-items-center shadow-sm">
                                <input class="form-check-input ms-0 me-3 shadow-none" type="checkbox" id="combo_incluye_envase" style="cursor: pointer; transform: scale(1.4); margin-top: 0;">
                                <div>
                                    <label class="form-check-label fw-bold text-success-emphasis d-block fs-6" for="combo_incluye_envase" style="cursor: pointer;">
                                        <i class="bi bi-shield-check me-1"></i> Perdonar Deuda de Envase
                                    </label>
                                    <small class="text-success-emphasis opacity-75 d-block mt-1">
                                        Activa esta opción para no exigir el retorno del envase vacío al momento de la venta.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="card-body p-4 bg-light" id="seccionComponentes" style="opacity: 0.5; pointer-events: none;">
                        <h6 class="fw-bold text-dark mb-4 d-flex align-items-center">
                            <i class="bi bi-diagram-2 text-primary me-2 fs-5"></i>Componentes del Combo
                        </h6>
                        
                        <form id="formAgregarComponente" class="row g-3 align-items-end mb-4 p-4 bg-white border border-secondary-subtle rounded-4 shadow-sm">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small fw-bold mb-2">Buscar Componente <span class="text-danger">*</span></label>
                                <select class="form-select form-select-lg shadow-none" id="selectComponente" required>
                                    <option value=""></option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label text-secondary small fw-bold mb-2">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-lg shadow-sm" id="inputCantidad" min="0.01" step="0.01" value="1" required>
                            </div>

                            <div class="col-md-2 d-flex align-items-center h-100">
                                <div class="form-check form-switch w-100 mb-0 py-2 px-3 border border-secondary-subtle rounded-3 text-center bg-light shadow-sm" data-bs-toggle="tooltip" title="Marca esto si el ítem va de regalo">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="checkBonificacion">
                                    <label class="form-check-label small fw-bold text-dark mt-1" for="checkBonificacion">Regalo</label>
                                </div>
                            </div>
                            
                            <div class="col-md-2 text-end">
                                <button type="submit" class="btn btn-dark btn-lg fw-bold w-100 shadow-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Añadir
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive bg-white border border-secondary-subtle rounded-4 shadow-sm">
                            <table class="table table-borderless align-middle mb-0 table-pro" id="tablaComponentes">
                                <thead class="border-bottom bg-light">
                                    <tr>
                                        <th class="ps-4 py-3 text-secondary fw-bold text-uppercase" style="font-size: 0.8rem;">Ítem Componente</th>
                                        <th class="text-center py-3 text-secondary fw-bold text-uppercase" style="font-size: 0.8rem;">Cantidad</th>
                                        <th class="text-center py-3 text-secondary fw-bold text-uppercase" style="font-size: 0.8rem;">Tipo</th>
                                        <th class="text-center py-3 text-secondary fw-bold text-uppercase" style="font-size: 0.8rem;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="filaVacia"><td colspan="4" class="text-center text-muted py-5">Guarda el Combo primero para empezar a añadir sus componentes.</td></tr>
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
        <td class="ps-4 text-dark fw-bold td-nombre py-3">Nombre del Producto</td>
        <td class="text-center fw-bolder text-primary fs-6 td-cantidad py-3">1.00</td>
        <td class="text-center py-3">
            <span class="badge rounded-pill badge-tipo px-3 py-2 bg-secondary-subtle text-secondary">Componente</span>
        </td>
        <td class="text-center py-3">
            <button type="button" class="btn btn-sm btn-light text-danger border-0 rounded-circle btn-eliminar-componente" data-bs-toggle="tooltip" title="Eliminar componente" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>
    </tr>
</template>