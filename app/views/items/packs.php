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
        background-color: #e9f2ff !important;
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
    /* Ajustes Mobile First */
    @media (max-width: 991.98px) {
        .catalog-container {
            max-height: 400px !important;
        }
        .btn-mobile-full {
            width: 100% !important;
            margin-top: 0.5rem;
        }
    }
</style>

<div class="container-fluid p-3 p-md-4" id="packsApp">
    
    <!-- ENCABEZADO -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h4 fw-bolder mb-1 text-dark d-flex align-items-center tracking-tight">
                <span class="bg-primary text-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-boxes fs-5"></i>
                </span>
                <?= htmlspecialchars($titulo) ?>
            </h1>
            <p class="text-secondary small mb-0 mt-2">Crea y configura recetas comerciales, promociones y kits de venta de forma intuitiva.</p>
        </div>
        <div class="w-100 w-md-auto d-flex justify-content-end">
            <a href="<?= e(route_url('items')) ?>" class="btn btn-white shadow-sm text-secondary fw-semibold border rounded-pill px-4 hover-lift btn-mobile-full d-flex align-items-center justify-content-center">
                <i class="bi bi-box-seam me-2"></i>Maestro de Ítems
            </a>
        </div>
    </div>

    <div class="row g-4 fade-in">
        
        <!-- PANEL IZQUIERDO: CATÁLOGO -->
        <div class="col-12 col-lg-4 col-xl-4">
            <div class="card border-0 shadow-sm h-100 card-modern">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">Catálogo</h6>
                    <button class="btn btn-sm btn-primary fw-bold shadow-sm rounded-pill px-3" id="btnNuevoPack">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                </div>
                <div class="p-3 bg-light border-bottom">
                    <div class="input-group input-group-sm bg-white rounded-pill border overflow-hidden focus-ring-primary shadow-sm">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control border-0 shadow-none ps-1 py-2" id="buscarPack" placeholder="Buscar combo o pack...">
                    </div>
                </div>
                
                <div class="card-body p-0 catalog-container" style="max-height: calc(100vh - 280px); overflow-y: auto;">
                    <div class="list-group list-group-flush border-top-0" id="listaPacks">
                        <?php if(!empty($packs)): ?>
                            <?php foreach($packs as $pack): ?>
                                <button type="button" class="list-group-item list-group-item-action p-3 border-bottom pack-item-btn" 
                                    data-id="<?= (int)$pack['id'] ?>" 
                                    data-nombre="<?= htmlspecialchars($pack['nombre']) ?>"
                                    data-precio="<?= (float)$pack['precio_venta'] ?>"
                                    data-tipo-combo="<?= htmlspecialchars($pack['tipo_combo'] ?? 'consolidado') ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold text-dark text-truncate pe-2" style="max-width: 70%;"><?= htmlspecialchars($pack['nombre']) ?></div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">S/ <?= number_format((float)$pack['precio_venta'], 2) ?></span>
                                    </div>
                                    <div class="small text-muted d-flex align-items-center justify-content-between">
                                        <span><i class="bi bi-upc-scan me-1"></i><?= htmlspecialchars($pack['sku']) ?></span>
                                        <?php if(($pack['tipo_combo'] ?? 'consolidado') === 'desglosable'): ?>
                                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="bi bi-arrows-expand me-1"></i>Desglosable</span>
                                        <?php endif; ?>
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

        <!-- PANEL DERECHO: CONFIGURACIÓN -->
        <div class="col-12 col-lg-8 col-xl-8">
            <div class="card border-0 shadow-sm h-100 bg-white card-modern d-flex flex-column">
                
                <!-- Estado Vacío -->
                <div id="panelVacio" class="card-body d-flex flex-column align-items-center justify-content-center py-5 text-muted empty-state-container flex-grow-1">
                    <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center mb-4 empty-state-icon shadow-sm" style="width: 100px; height: 100px;">
                        <i class="bi bi-diagram-3 text-primary" style="font-size: 3.5rem;"></i>
                    </div>
                    <h4 class="fw-bolder text-dark mb-2 text-center">Diseña tus combos comerciales</h4>
                    <p class="text-secondary text-center px-3" style="max-width: 450px;">Selecciona un pack de la lista lateral para editar sus componentes, o presiona <strong>Nuevo</strong> para registrar una nueva promoción o receta.</p>
                </div>

                <!-- Panel de Configuración -->
                <div id="panelConfiguracion" class="d-none flex-column h-100">
                    
                    <form id="formPackPadre" class="card-header bg-white border-bottom p-4">
                        <input type="hidden" id="idPackSeleccionado" value="0">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-6 shadow-sm" id="lblEstadoPack">
                                <i class="bi bi-star-fill me-1"></i> Nuevo Combo
                            </span>
                            <button type="button" class="btn-close d-lg-none bg-light rounded-circle p-2" aria-label="Cerrar" id="btnCerrarPanelMobile"></button>
                        </div>

                        <!-- Fila de Inputs Mejorada (Tamaños Estándar) -->
                        <div class="row g-3">
                            <div class="col-12 col-xl-8">
                                <label class="form-label text-secondary small fw-bold mb-2">Nombre del Combo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control fw-bold text-dark shadow-sm border-secondary-subtle" id="inputNombrePack" placeholder="Ej. BIDÓN NUEVO LLENO 20L" required>
                            </div>
                            <div class="col-12 col-xl-4">
                                <label class="form-label text-secondary small fw-bold mb-2">Precio al Público (S/) <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm border-secondary-subtle rounded">
                                    <span class="input-group-text bg-light text-success fw-bold">S/</span>
                                    <input type="number" class="form-control fw-bold text-success border-start-0 ps-0" id="inputPrecioPack" step="0.01" min="0" placeholder="0.00" required>
                                </div>
                            </div>
                            
                            <!-- Nueva configuración de comportamiento -->
                            <div class="col-12 col-xl-8">
                                <label class="form-label text-secondary small fw-bold mb-2">Comportamiento en Ventas <span class="text-danger">*</span></label>
                                <select id="selectTipoCombo" class="form-select shadow-sm border-secondary-subtle text-dark fw-medium">
                                    <option value="consolidado">Paquete Cerrado (Se reporta como 1 solo producto)</option>
                                    <option value="desglosable">Combo Promocional (Se divide en sus componentes al facturar)</option>
                                </select>
                            </div>
                            
                            <div class="col-12 col-xl-4 d-flex align-items-end gap-2 mt-3 mt-xl-0 pt-xl-4">
                                <button type="button" class="btn btn-outline-danger shadow-sm d-none flex-shrink-0" id="btnEliminarPack" title="Eliminar combo" style="width: 42px;">
                                    <i class="bi bi-trash3"></i>
                                </button>
                                <button type="submit" class="btn btn-primary fw-bold shadow-sm flex-fill d-flex align-items-center justify-content-center" id="btnGuardarPack">
                                    <i class="bi bi-save me-2"></i>Guardar Combo
                                </button>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3 d-none" id="contenedorSwitchEnvase">
                            <div class="form-check form-switch bg-success-subtle border border-success-subtle rounded-3 p-3 d-flex align-items-center shadow-sm">
                                <input class="form-check-input ms-0 me-3 shadow-none" type="checkbox" id="combo_incluye_envase" style="cursor: pointer; transform: scale(1.3); margin-top: 0;">
                                <div>
                                    <label class="form-check-label fw-bold text-success-emphasis d-block fs-6" for="combo_incluye_envase" style="cursor: pointer;">
                                        <i class="bi bi-shield-check me-1"></i> Perdonar Deuda de Envase
                                    </label>
                                    <small class="text-success-emphasis opacity-75 d-block mt-1" style="font-size: 0.8rem;">
                                        Activa esta opción para no exigir el retorno del envase vacío al momento de la venta.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="card-body p-3 p-md-4 bg-light flex-grow-1" id="seccionComponentes" style="opacity: 0.5; pointer-events: none;">
                        <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                            <i class="bi bi-diagram-2 text-primary me-2 fs-5"></i>Componentes del Combo
                        </h6>
                        
                        <!-- Formulario de Agregar Componentes -->
                        <form id="formAgregarComponente" class="row g-2 g-md-3 align-items-end mb-4 p-3 bg-white border border-secondary-subtle rounded-4 shadow-sm mx-0">
                            <div class="col-12 col-md-5">
                                <label class="form-label text-secondary small fw-bold mb-1">Buscar Componente <span class="text-danger">*</span></label>
                                <select class="form-select shadow-none" id="selectComponente" required>
                                    <option value=""></option>
                                </select>
                            </div>
                            
                            <div class="col-6 col-md-2">
                                <label class="form-label text-secondary small fw-bold mb-1">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control shadow-sm" id="inputCantidad" min="0.01" step="0.01" value="1" required>
                            </div>

                            <div class="col-6 col-md-3 d-flex align-items-center">
                                <div class="form-check form-switch w-100 mb-0 py-2 px-2 border border-secondary-subtle rounded-2 text-center bg-light shadow-sm d-flex justify-content-center align-items-center gap-2" data-bs-toggle="tooltip" title="Marca esto si el ítem va de regalo">
                                    <input class="form-check-input ms-0 mt-0" type="checkbox" id="checkBonificacion">
                                    <label class="form-check-label small fw-bold text-dark" for="checkBonificacion" style="line-height: 1;">Regalo</label>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-2">
                                <button type="submit" class="btn btn-dark fw-bold w-100 shadow-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Añadir
                                </button>
                            </div>
                        </form>

                        <div class="table-responsive bg-white border border-secondary-subtle rounded-4 shadow-sm">
                            <table class="table align-middle mb-0 table-pro" id="tablaComponentes">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="ps-4 py-3 fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Ítem Componente</th>
                                        <th class="text-center py-3 fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Cantidad</th>
                                        <th class="text-center py-3 fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Tipo</th>
                                        <th class="text-center py-3 fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Acción</th>
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
            <span class="badge rounded-pill badge-tipo px-3 py-2 bg-info-subtle text-info border border-info-subtle shadow-sm">Componente</span>
        </td>
        <td class="text-center py-3">
            <button type="button" class="btn btn-sm btn-light text-danger border border-danger-subtle rounded-circle btn-eliminar-componente shadow-sm transition-hover" data-bs-toggle="tooltip" title="Eliminar componente" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    </tr>
</template>