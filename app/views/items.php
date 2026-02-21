<?php
$items = $items ?? [];
$categorias = $categorias ?? [];
$categoriasGestion = $categorias_gestion ?? [];
$marcas = $marcas ?? [];
$marcasGestion = $marcas_gestion ?? [];
$sabores = $sabores ?? [];
$saboresGestion = $sabores_gestion ?? [];
$presentaciones = $presentaciones ?? [];
$presentacionesGestion = $presentaciones_gestion ?? [];

// Helpers para UI
$tipoItemValueForUi = static function (string $tipo): string {
    return match ($tipo) {
        'producto' => 'producto_terminado',
        default => $tipo,
    };
};
$tipoItemLabel = static function (string $tipo): string {
    return match ($tipo) {
        'producto', 'producto_terminado' => 'Producto terminado',
        'materia_prima' => 'Materia prima',
        'material_empaque' => 'Material de empaque',
        'servicio' => 'Servicio',
        'semielaborado' => 'Semielaborado',
        'insumo' => 'Insumo',
        default => $tipo,
    };
};
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam me-2 text-primary"></i> Ítems y Servicios
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo maestro de ítems.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionCategorias">
                <i class="bi bi-tags me-2 text-info"></i>Categorías
            </button>
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold js-open-gestion-items" type="button" id="btnGestionItemsHeader" data-tab="sabores">
                <i class="bi bi-sliders me-2 text-info"></i>Configuración de ítems
            </button>
            <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearItem">
                <i class="bi bi-plus-circle me-2"></i>Nuevo ítem
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 inventario-sticky-filters">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="itemSearch" placeholder="Buscar ítem...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="itemFiltroCategoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categoriasGestion as $categoria): ?>
                            <option value="<?php echo (int) ($categoria['id'] ?? 0); ?>">
                                <?php echo e((string) ($categoria['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="itemFiltroTipo">
                        <option value="">Todos los tipos</option>
                        <option value="producto_terminado">Producto terminado</option>
                        <option value="materia_prima">Materia prima</option>
                        <option value="insumo">Insumo</option>
                        <option value="semielaborado">Semielaborado</option>
                        <option value="material_empaque">Material de empaque</option>
                        <option value="servicio">Servicio</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="itemFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive inventario-table-wrapper">
                <table class="table align-middle mb-0 table-pro" id="itemsTable">
                    <thead class="inventario-sticky-thead">
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Stock mín.</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $tipoItemUi = $tipoItemValueForUi((string) ($item['tipo_item'] ?? '')); ?>
                            <tr data-estado="<?php echo (int) $item['estado']; ?>"
                                data-tipo="<?php echo e($tipoItemUi); ?>"
                                data-categoria="<?php echo (int) ($item['id_categoria'] ?? 0); ?>"
                                data-search="<?php echo e(mb_strtolower($item['sku'].' '.($item['nombre'] ?? '').' '.($item['descripcion'] ?? '').' '.($item['marca'] ?? ''))); ?>">
                                <td class="ps-4 fw-semibold text-secondary"><?php echo e($item['sku']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e($item['nombre']); ?></div>
                                    <div class="small text-muted"><?php echo e($item['descripcion'] ?? ''); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($tipoItemLabel((string) ($item['tipo_item'] ?? ''))); ?></span></td>
                                <?php
                                    $stockMinimo = (string) ($item['stock_minimo'] ?? '');
                                    $stockMinimoNumero = is_numeric($stockMinimo) ? (float) $stockMinimo : 0.0;
                                    $mostrarStockSinDefinir = $stockMinimo === '' || $stockMinimoNumero <= 0.0;
                                ?>
                                <td><?php echo $mostrarStockSinDefinir ? 'Sin definir' : e(number_format($stockMinimoNumero, 4)); ?></td>
                                <td class="text-center">
                                    <?php if ((int) $item['estado'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" id="badge_status_item_<?php echo (int) $item['id']; ?>">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill" id="badge_status_item_<?php echo (int) $item['id']; ?>">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="form-check form-switch pt-1" title="Cambiar estado">
                                            <input class="form-check-input switch-estado-item" type="checkbox" role="switch"
                                                   style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                   data-id="<?php echo (int) $item['id']; ?>"
                                                   <?php echo ((int) $item['estado'] === 1) ? 'checked' : ''; ?>>
                                        </div>
                                        <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>
                                        
                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalEditarItem"
                                            data-id="<?php echo (int) $item['id']; ?>"
                                            data-sku="<?php echo e($item['sku']); ?>"
                                            data-nombre="<?php echo e($item['nombre']); ?>"
                                            data-descripcion="<?php echo e($item['descripcion'] ?? ''); ?>"
                                            data-tipo="<?php echo e($tipoItemUi); ?>"
                                            data-marca="<?php echo e((string) ($item['id_marca'] ?? '')); ?>"
                                            data-unidad="<?php echo e($item['unidad_base'] ?? ''); ?>"
                                            data-moneda="<?php echo e($item['moneda'] ?? ''); ?>"
                                            data-impuesto="<?php echo e((string) ($item['impuesto'] ?? '18.00')); ?>"
                                            data-precio="<?php echo e((string) $item['precio_venta']); ?>"
                                            data-stock-minimo="<?php echo e((string) $item['stock_minimo']); ?>"
                                            data-costo="<?php echo e((string) $item['costo_referencial']); ?>"
                                            data-controla-stock="<?php echo (int) $item['controla_stock']; ?>"
                                            data-permite-decimales="<?php echo (int) ($item['permite_decimales'] ?? 0); ?>"
                                            data-requiere-lote="<?php echo (int) ($item['requiere_lote'] ?? 0); ?>"
                                            data-requiere-vencimiento="<?php echo (int) ($item['requiere_vencimiento'] ?? 0); ?>"
                                            data-dias-alerta-vencimiento="<?php echo e((string) ($item['dias_alerta_vencimiento'] ?? '')); ?>"
                                            data-categoria="<?php echo e((string) ($item['id_categoria'] ?? '')); ?>"
                                            data-sabor="<?php echo e((string) ($item['id_sabor'] ?? '')); ?>"
                                            data-presentacion="<?php echo e((string) ($item['id_presentacion'] ?? '')); ?>"
                                            data-estado="<?php echo (int) $item['estado']; ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>

                                        <?php
                                            $puedeEliminarItem = (int) ($item['puede_eliminar'] ?? 1) === 1;
                                            $motivoNoEliminarItem = (string) ($item['motivo_no_eliminar'] ?? 'No se puede eliminar este ítem.');
                                        ?>
                                        <form method="post" class="d-inline m-0 js-swal-confirm" data-confirm-title="¿Eliminar ítem?" data-confirm-text="Esta acción no se puede deshacer.">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm border-0 bg-transparent <?php echo $puedeEliminarItem ? 'btn-light text-danger' : 'btn-light text-muted opacity-50'; ?>"
                                                title="<?php echo $puedeEliminarItem ? 'Eliminar' : e($motivoNoEliminarItem); ?>"
                                                <?php echo $puedeEliminarItem ? '' : 'disabled aria-disabled="true"'; ?>>
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-1">
        <div class="small text-muted" id="itemsPaginationInfo">Cargando...</div>
        <nav aria-label="Paginación de ítems">
            <ul class="pagination pagination-sm mb-0" id="itemsPaginationControls"></ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="modalCrearItem" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar ítem</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <form method="post" class="row g-3" id="formCrearItem">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-primary py-2"><i class="bi bi-tag me-2"></i>Identidad</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select" id="newTipo" name="tipo_item" required>
                                                <option value="" selected>Seleccionar tipo</option>
                                                <option value="producto_terminado">Producto terminado</option>
                                                <option value="materia_prima">Materia Prima</option>
                                                <option value="insumo">Insumo</option>
                                                <option value="semielaborado">Semielaborado</option>
                                                <option value="material_empaque">Material de Empaque</option>
                                                <option value="servicio">Servicios / Otros</option>
                                            </select>
                                            <label for="newTipo">Tipo de ítem <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="form-text">
                                            Producto terminado y semielaborado requieren marca, sabor y presentación.
                                        </div>
                                    </div>
                                    <div class="col-md-4" id="newAutoIdentidadWrap">
                                        <label class="form-label fw-semibold mb-1">Automatización</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" id="newAutoIdentidad" name="autogenerar_identidad" value="1" checked>
                                            <label class="form-check-label small" for="newAutoIdentidad">Generar nombre y SKU automáticamente</label>
                                        </div>
                                        <div class="form-text" id="newAutoIdentidadHelp">Disponible para producto terminado o semielaborado.</div>
                                    </div>

                                    <div class="col-12 d-none" id="newAutoIdentityHint">
                                        <div class="item-autoidentity-hint">
                                            <i class="bi bi-magic me-2"></i>
                                            Se construye con <strong>Marca - Sabor - Presentación</strong>. Puedes desactivar el switch para edición manual.
                                        </div>
                                    </div>

                                    <div class="col-md-9">
                                        <div class="form-floating">
                                            <input type="text" class="form-control fw-bold" id="newNombre" name="nombre" placeholder="Nombre" required>
                                            <label for="newNombre">Nombre del producto <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="form-text mt-2 d-flex align-items-center gap-2">
                                            <span class="badge text-bg-primary-subtle text-primary-emphasis border" id="newNombreAutoBadge">Autogenerado</span>
                                            Se genera automáticamente en ítems detallados.
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" class="form-control sku-lockable" id="newSku" name="sku" placeholder="SKU" readonly>
                                            <label for="newSku">SKU</label>
                                        </div>
                                        <div class="form-text mt-2"><span class="badge text-bg-primary-subtle text-primary-emphasis border" id="newSkuAutoBadge">Autogenerado</span></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select" id="newUnidad" name="unidad_base">
                                                <option value="UND" selected>UND</option>
                                                <option value="KG">KG</option>
                                                <option value="LT">LT</option>
                                                <option value="M">M</option>
                                                <option value="CAJA">CAJA</option>
                                                <option value="PAQ">PAQ</option>
                                            </select>
                                            <label for="newUnidad">Unidad base</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6" id="newSaborContainer">
                                        <label class="form-label small text-muted mb-1">Sabor / Variante <span class="text-danger">*</span></label>
                                        <select class="form-select" id="newSabor" name="id_sabor">
                                            <option value="" selected>Seleccionar sabor...</option>
                                            <?php foreach ($sabores as $sabor): ?>
                                                <option value="<?php echo (int) $sabor['id']; ?>"><?php echo e((string) $sabor['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6" id="newPresentacionContainer">
                                        <label class="form-label small text-muted mb-1">Presentación / Envase <span class="text-danger">*</span></label>
                                        <select class="form-select" id="newPresentacion" name="id_presentacion">
                                            <option value="" selected>Seleccionar presentación...</option>
                                            <?php foreach ($presentaciones as $presentacion): ?>
                                                <option value="<?php echo (int) $presentacion['id']; ?>"><?php echo e((string) $presentacion['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Categoría</label>
                                        <select class="form-select" id="newCategoria" name="id_categoria">
                                            <option value="" selected>Seleccionar...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6" id="newMarcaContainer">
                                        <label class="form-label small text-muted mb-1">Marca <span class="text-danger">*</span></label>
                                        <select class="form-select" id="newMarca" name="id_marca">
                                            <option value="" selected>Seleccionar...</option>
                                            <?php foreach ($marcas as $marca): ?>
                                                <option value="<?php echo (int) ($marca['id'] ?? 0); ?>"><?php echo e((string) $marca['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="newPrecio" name="precio_venta" value="0.0000">
                    <input type="hidden" id="newMoneda" name="moneda" value="PEN">

                    <div class="col-12" id="newComercialCard">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-success py-2"><i class="bi bi-currency-dollar me-2"></i>Comercial</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-0">Costo Ref.</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white">S/</span>
                                            <input type="number" step="0.0001" class="form-control" id="newCosto" name="costo_referencial" value="0.0000">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-0">Impuesto (%)</label>
                                        <input type="number" step="0.0001" class="form-control" id="newImpuesto" name="impuesto" value="18.00">
                                    </div>
                                    
                                    <div class="col-12">
                                        <input type="text" class="form-control form-control-sm" id="newDescripcion" name="descripcion" placeholder="Descripción adicional (opcional)">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-secondary py-2"><i class="bi bi-sliders me-2"></i>Configuración Avanzada</div>
                            <div class="card-body py-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="newControlaStock" name="controla_stock" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="newControlaStock">Controlar Stock</label>
                                            </div>
                                            <div class="w-50" id="newStockMinContainer">
                                                <input type="number" class="form-control form-control-sm text-end" id="newStockMin" name="stock_minimo" placeholder="Mín." value="0.0000" disabled>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center" id="newRequiereLoteContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="newRequiereLote" name="requiere_lote" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="newRequiereLote">Exigir Lote</label>
                                            </div>
                                            <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Obligatorio al registrar ingresos/salidas."></i>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-3" id="newRequiereVencimientoContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="newRequiereVencimiento" name="requiere_vencimiento" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="newRequiereVencimiento">Requiere Venc.</label>
                                            </div>
                                            <div class="d-flex align-items-center gap-2" id="newDiasAlertaContainer">
                                                <span class="small text-muted" style="font-size: 0.8rem;">Días alerta:</span>
                                                <input type="number" min="0" class="form-control form-control-sm text-center" id="newDiasAlerta" name="dias_alerta_vencimiento" style="width: 70px;" value="0" disabled>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center" id="newPermiteDecimalesContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="newPermiteDecimales" name="permite_decimales" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="newPermiteDecimales">Permite Decimales</label>
                                            </div>
                                            <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="left" title="Ideal para ventas a granel (ej. Litros o Kg)."></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="newEstado" name="estado" value="1">
                    
                    <div class="col-12 modal-footer border-top-0 pb-0 px-0">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold" type="submit"><i class="bi bi-save me-2"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarItem" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar ítem</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <form method="post" class="row g-3" id="formEditarItem">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-primary py-2"><i class="bi bi-tag me-2"></i>Identidad</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="form-floating">
                                            <select class="form-select" id="editTipo" name="tipo_item" required>
                                                <option value="producto_terminado">Producto terminado</option>
                                                <option value="materia_prima">Materia prima</option>
                                                <option value="insumo">Insumo</option>
                                                <option value="semielaborado">Semielaborado</option>
                                                <option value="material_empaque">Material de empaque</option>
                                                <option value="servicio">Servicio</option>
                                            </select>
                                            <label for="editTipo">Tipo de ítem <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="form-text">
                                            Producto terminado y semielaborado requieren marca, sabor y presentación.
                                        </div>
                                    </div>
                                    <div class="col-md-4" id="editAutoIdentidadWrap">
                                        <label class="form-label fw-semibold mb-1">Automatización</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" id="editAutoIdentidad" name="autogenerar_identidad" value="1" checked>
                                            <label class="form-check-label small" for="editAutoIdentidad">Generar nombre y SKU automáticamente</label>
                                        </div>
                                        <div class="form-text" id="editAutoIdentidadHelp">Disponible para producto terminado o semielaborado.</div>
                                    </div>

                                    <div class="col-12 d-none" id="editAutoIdentityHint">
                                        <div class="item-autoidentity-hint">
                                            <i class="bi bi-magic me-2"></i>
                                            Se construye con <strong>Marca - Sabor - Presentación</strong>. Puedes desactivar el switch para edición manual.
                                        </div>
                                    </div>

                                    <div class="col-md-9">
                                        <div class="form-floating">
                                            <input type="text" class="form-control fw-bold" id="editNombre" name="nombre" required>
                                            <label for="editNombre">Nombre del producto <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="form-text mt-2 d-flex align-items-center gap-2">
                                            <span class="badge text-bg-primary-subtle text-primary-emphasis border" id="editNombreAutoBadge">Autogenerado</span>
                                            Se genera automáticamente en ítems detallados.
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" class="form-control sku-lockable" id="editSku" name="sku" placeholder="SKU" readonly>
                                            <label for="editSku">SKU</label>
                                        </div>
                                        <div class="form-text mt-2"><span class="badge text-bg-primary-subtle text-primary-emphasis border" id="editSkuAutoBadge">Autogenerado</span></div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select" id="editUnidad" name="unidad_base">
                                                <option value="UND">UND</option>
                                                <option value="KG">KG</option>
                                                <option value="LT">LT</option>
                                                <option value="M">M</option>
                                                <option value="CAJA">CAJA</option>
                                                <option value="PAQ">PAQ</option>
                                            </select>
                                            <label for="editUnidad">Unidad base</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6" id="editSaborContainer">
                                        <label class="form-label small text-muted mb-1">Sabor / Variante <span class="text-danger">*</span></label>
                                        <select class="form-select" id="editSabor" name="id_sabor">
                                            <option value="">Seleccionar sabor...</option>
                                            <?php foreach ($sabores as $sabor): ?>
                                                <option value="<?php echo (int) $sabor['id']; ?>"><?php echo e((string) $sabor['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="editPresentacionContainer">
                                        <label class="form-label small text-muted mb-1">Presentación / Envase <span class="text-danger">*</span></label>
                                        <select class="form-select" id="editPresentacion" name="id_presentacion">
                                            <option value="">Seleccionar presentación...</option>
                                            <?php foreach ($presentaciones as $presentacion): ?>
                                                <option value="<?php echo (int) $presentacion['id']; ?>"><?php echo e((string) $presentacion['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Categoría</label>
                                        <select class="form-select" id="editCategoria" name="id_categoria">
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="editMarcaContainer">
                                        <label class="form-label small text-muted mb-1">Marca <span class="text-danger">*</span></label>
                                        <select class="form-select" id="editMarca" name="id_marca">
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($marcas as $marca): ?>
                                                <option value="<?php echo (int) ($marca['id'] ?? 0); ?>"><?php echo e((string) $marca['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="editPrecio" name="precio_venta" value="0.0000">
                    <input type="hidden" id="editMoneda" name="moneda" value="PEN">

                    <div class="col-12" id="editComercialCard">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-success py-2"><i class="bi bi-currency-dollar me-2"></i>Comercial</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-0">Costo Ref.</label>
                                        <input class="form-control" id="editCosto" name="costo_referencial" type="number" step="0.0001">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-0">Impuesto (%)</label>
                                        <input class="form-control" id="editImpuesto" name="impuesto" type="number" step="0.0001">
                                    </div>

                                    <div class="col-12">
                                        <input class="form-control form-control-sm" id="editDescripcion" name="descripcion" placeholder="Descripción adicional">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-secondary py-2"><i class="bi bi-sliders me-2"></i>Configuración Avanzada</div>
                            <div class="card-body py-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="editControlaStock" name="controla_stock" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="editControlaStock">Controlar Stock</label>
                                            </div>
                                            <div class="w-50" id="editStockMinimoContainer">
                                                <input class="form-control form-control-sm text-end" id="editStockMinimo" name="stock_minimo" type="number" step="0.0001" disabled placeholder="Mín.">
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center" id="editRequiereLoteContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="editRequiereLote" name="requiere_lote" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="editRequiereLote">Exigir Lote</label>
                                            </div>
                                            <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Obligatorio al registrar ingresos/salidas."></i>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-3" id="editRequiereVencimientoContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="editRequiereVencimiento" name="requiere_vencimiento" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="editRequiereVencimiento">Requiere Venc.</label>
                                            </div>
                                            <div class="d-flex align-items-center gap-2" id="editDiasAlertaContainer">
                                                <span class="small text-muted" style="font-size: 0.8rem;">Días alerta:</span>
                                                <input type="number" min="0" class="form-control form-control-sm text-center" id="editDiasAlerta" name="dias_alerta_vencimiento" style="width: 70px;" disabled>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center" id="editPermiteDecimalesContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="editPermiteDecimales" name="permite_decimales" value="1">
                                                <label class="form-check-label fw-semibold text-dark" for="editPermiteDecimales">Permite Decimales</label>
                                            </div>
                                            <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="left" title="Ideal para ventas a granel (ej. Litros o Kg)."></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 modal-footer border-top-0 pb-0 px-0">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold" type="submit">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionItems" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-sliders me-2"></i>Configuración de ítems</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" id="sabores-tab" data-bs-toggle="tab" data-bs-target="#tabSabores" type="button">Sabores</button></li>
                    <li class="nav-item"><button class="nav-link" id="presentaciones-tab" data-bs-toggle="tab" data-bs-target="#tabPresentaciones" type="button">Presentaciones</button></li>
                    <li class="nav-item"><button class="nav-link" id="marcas-tab" data-bs-toggle="tab" data-bs-target="#tabMarcas" type="button">Marcas</button></li>
                </ul>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tabSabores" role="tabpanel">
                        <form method="post" id="formAgregarSabor" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_sabor">
                            <div class="col-md-7 form-floating"><input type="text" class="form-control" id="nuevoSaborNombre" name="nombre" required><label>Nombre del sabor</label></div>
                            <div class="col-md-3 form-check form-switch pt-4"><input class="form-check-input" type="checkbox" id="nuevoSaborEstado" name="estado" value="1" checked><label class="ms-2">Activo</label></div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Agregar</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaSaboresGestion">
                                <thead><tr><th>Nombre</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($saboresGestion as $sabor): ?>
                                        <?php $nombreSabor = (string)($sabor['nombre'] ?? ''); $esSistema = ($nombreSabor === 'Ninguno'); ?>
                                        <tr class="<?php echo $esSistema ? 'bg-light' : ''; ?>">
                                            <td class="fw-semibold"><?php echo e($nombreSabor); ?><?php if($esSistema): ?><i class="bi bi-shield-lock-fill text-muted ms-2"></i><?php endif; ?></td>
                                            <td><div class="form-check form-switch m-0"><input class="form-check-input <?php echo $esSistema?'':'js-toggle-atributo'; ?>" type="checkbox" <?php if(!$esSistema): ?>data-accion="editar_sabor" data-id="<?php echo (int)($sabor['id']??0); ?>" data-nombre="<?php echo e($nombreSabor); ?>"<?php endif; ?> <?php echo (int)($sabor['estado']??0)===1?'checked':''; ?> <?php echo $esSistema?'disabled':''; ?>></div></td>
                                            <td class="text-end">
                                                <?php if($esSistema): ?><span class="badge bg-secondary">Protegido</span><?php else: ?>
                                                <button class="btn btn-sm btn-outline-primary js-editar-atributo" data-target="sabor" data-id="<?php echo (int)($sabor['id']??0); ?>" data-nombre="<?php echo e($nombreSabor); ?>" data-estado="<?php echo (int)($sabor['estado']??1); ?>">Editar</button>
                                                <button class="btn btn-sm btn-outline-danger js-eliminar-atributo" data-accion="eliminar_sabor" data-id="<?php echo (int)($sabor['id']??0); ?>">Eliminar</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tabPresentaciones" role="tabpanel">
                        <form method="post" id="formAgregarPresentacion" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_presentacion">
                            <div class="col-md-7 form-floating"><input type="text" class="form-control" id="nuevaPresentacionNombre" name="nombre" required><label>Nombre</label></div>
                            <div class="col-md-3 form-check form-switch pt-4"><input class="form-check-input" type="checkbox" id="nuevaPresentacionEstado" name="estado" value="1" checked><label class="ms-2">Activo</label></div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Agregar</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaPresentacionesGestion">
                                <thead><tr><th>Nombre</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($presentacionesGestion as $presentacion): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e((string)($presentacion['nombre'] ?? '')); ?></td>
                                            <td><div class="form-check form-switch m-0"><input class="form-check-input js-toggle-atributo" type="checkbox" data-accion="editar_presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>" data-nombre="<?php echo e((string)($presentacion['nombre']??'')); ?>" <?php echo (int)($presentacion['estado']??0)===1?'checked':''; ?>></div></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary js-editar-atributo" data-target="presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>" data-nombre="<?php echo e((string)($presentacion['nombre']??'')); ?>" data-estado="<?php echo (int)($presentacion['estado']??1); ?>">Editar</button>
                                                <button class="btn btn-sm btn-outline-danger js-eliminar-atributo" data-accion="eliminar_presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>">Eliminar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tabMarcas" role="tabpanel">
                        <form method="post" id="formAgregarMarca" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_marca">
                            <div class="col-md-7 form-floating"><input type="text" class="form-control" id="nuevaMarcaNombre" name="nombre" required><label>Nombre</label></div>
                            <div class="col-md-3 form-check form-switch pt-4"><input class="form-check-input" type="checkbox" id="nuevaMarcaEstado" name="estado" value="1" checked><label class="ms-2">Activo</label></div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Agregar</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaMarcasGestion">
                                <thead><tr><th>Nombre</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($marcasGestion as $marca): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e((string)($marca['nombre'] ?? '')); ?></td>
                                            <td><div class="form-check form-switch m-0"><input class="form-check-input js-toggle-atributo" type="checkbox" data-accion="editar_marca" data-id="<?php echo (int)($marca['id']??0); ?>" data-nombre="<?php echo e((string)($marca['nombre']??'')); ?>" <?php echo (int)($marca['estado']??0)===1?'checked':''; ?>></div></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary js-editar-atributo" data-target="marca" data-id="<?php echo (int)($marca['id']??0); ?>" data-nombre="<?php echo e((string)($marca['nombre']??'')); ?>" data-estado="<?php echo (int)($marca['estado']??1); ?>">Editar</button>
                                                <button class="btn btn-sm btn-outline-danger js-eliminar-atributo" data-accion="eliminar_marca" data-id="<?php echo (int)($marca['id']??0); ?>">Eliminar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionCategorias" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-tags me-2"></i>Administrar categorías</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="formGestionCategoria" class="row g-2 mb-3 border rounded-3 p-3 bg-light">
                    <input type="hidden" name="accion" id="categoriaAccion" value="crear_categoria">
                    <input type="hidden" name="id" id="categoriaId" value="">
                    <div class="col-md-4 form-floating"><input type="text" class="form-control" id="categoriaNombre" name="nombre" required><label>Nombre</label></div>
                    <div class="col-md-5 form-floating"><input type="text" class="form-control" id="categoriaDescripcion" name="descripcion"><label>Descripción</label></div>
                    <div class="col-md-3 form-floating"><select class="form-select" id="categoriaEstado" name="estado"><option value="1">Activo</option><option value="0">Inactivo</option></select><label>Estado</label></div>
                    <div class="col-12 d-flex justify-content-end gap-2"><button type="button" class="btn btn-light" id="btnResetCategoria">Limpiar</button><button type="submit" class="btn btn-primary" id="btnGuardarCategoria">Guardar</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($categoriasGestion as $categoria): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e((string)$categoria['nombre']); ?></td>
                                    <td><?php echo e((string)($categoria['descripcion'] ?? '')); ?></td>
                                    <td><?php if((int)($categoria['estado']??0)===1): ?><span class="badge bg-success-subtle text-success">Activo</span><?php else: ?><span class="badge bg-secondary-subtle text-secondary">Inactivo</span><?php endif; ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary btn-editar-categoria" data-id="<?php echo (int)$categoria['id']; ?>" data-nombre="<?php echo e((string)$categoria['nombre']); ?>" data-descripcion="<?php echo e((string)($categoria['descripcion']??'')); ?>" data-estado="<?php echo (int)($categoria['estado']??1); ?>">Editar</button>
                                        <form method="post" class="d-inline js-swal-confirm"><input type="hidden" name="accion" value="eliminar_categoria"><input type="hidden" name="id" value="<?php echo (int)$categoria['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarAtributo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h6 class="modal-title mb-0" id="tituloEditarAtributo">Editar</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="formEditarAtributo" class="modal-body">
                <input type="hidden" name="accion" id="editarAtributoAccion" value="">
                <input type="hidden" name="id" id="editarAtributoId" value="">
                <div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" id="editarAtributoNombre" name="nombre" required></div>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="editarAtributoEstado" name="estado" value="1" checked><label class="form-check-label">Activo</label></div>
                <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
            </form>
        </div>
    </div>
</div>
