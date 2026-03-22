<?php
$items = $items ?? [];
$rubros = $rubros ?? [];
$rubrosGestion = $rubros_gestion ?? [];
$categorias = $categorias ?? [];
$categoriasGestion = $categorias_gestion ?? [];
$marcas = $marcas ?? [];
$marcasGestion = $marcas_gestion ?? [];
$sabores = $sabores ?? [];
$saboresGestion = $sabores_gestion ?? [];
$presentaciones = $presentaciones ?? [];
$presentacionesGestion = $presentaciones_gestion ?? [];
$unidadesConversion = $unidades_conversion ?? [];
$pendientesConversion = array_values(array_filter($unidadesConversion, static fn(array $row): bool => (int) ($row['total_unidades'] ?? 0) <= 0));
$categoriasPorId = [];

foreach ($categoriasGestion as $categoriaGestion) {
    $categoriaId = (int) ($categoriaGestion['id'] ?? 0);
    if ($categoriaId <= 0) {
        continue;
    }
    $categoriasPorId[$categoriaId] = (string) ($categoriaGestion['nombre'] ?? '');
}

// Helpers para UI
$tipoItemValueForUi = static function (string $tipo): string {
    if ($tipo === 'producto') {
        return 'producto_terminado';
    }

    return $tipo;
};
$tipoItemLabel = static function (string $tipo): string {
    if ($tipo === 'producto' || $tipo === 'producto_terminado') {
        return 'Producto terminado';
    }

    if ($tipo === 'materia_prima') {
        return 'Materia prima';
    }

    if ($tipo === 'material_empaque') {
        return 'Material de empaque';
    }

    if ($tipo === 'servicio') {
        return 'Servicio';
    }

    if ($tipo === 'semielaborado') {
        return 'Semielaborado';
    }

    if ($tipo === 'insumo') {
        return 'Insumo';
    }

    return $tipo;
};
?>

<meta name="csrf-token" content="<?php echo e((string) ($csrf_token ?? '')); ?>">

<div class="container-fluid p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam me-2 text-primary"></i> Ítems y Servicios
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo maestro de ítems.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-md-end">
            <button class="btn btn-light border border-secondary-subtle shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalUnidadesConversion">
                <i class="bi bi-arrow-left-right me-2 text-warning"></i>Unidades y Conversiones
                <?php if (count($pendientesConversion) > 0): ?>
                    <span class="badge rounded-pill bg-warning text-dark ms-2" id="ucPendientesBadge"><?php echo count($pendientesConversion); ?></span>
                <?php endif; ?>
            </button>
            <button class="btn btn-light border border-secondary-subtle shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionRubros">
                <i class="bi bi-diagram-3 me-2 text-info"></i>Rubros
            </button>
            <button class="btn btn-light border border-secondary-subtle shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionCategorias">
                <i class="bi bi-tags me-2 text-info"></i>Categorías
            </button>
            <button class="btn btn-light border border-secondary-subtle shadow-sm text-secondary fw-semibold js-open-gestion-items" type="button" id="btnGestionItemsHeader" data-tab="sabores">
                <i class="bi bi-sliders me-2 text-primary"></i>Configuración
            </button>
            <button class="btn btn-primary shadow-sm fw-bold px-3" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearItem">
                <i class="bi bi-plus-circle-fill me-2"></i>Nuevo ítem
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 inventario-sticky-filters">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" id="itemSearch" placeholder="Buscar por código, nombre o SKU...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="itemFiltroCategoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categoriasGestion as $categoria): ?>
                            <option value="<?php echo (int) ($categoria['id'] ?? 0); ?>">
                                <?php echo e((string) ($categoria['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="itemFiltroTipo">
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
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="itemFiltroEstado">
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
                <!-- Se agregó el ID "itemsTable" para que renderizadores.js lo encuentre -->
                <table class="table align-middle mb-0 table-pro table-hover" id="itemsTable"
                       data-erp-table="true"
                       data-rows-selector="#itemsTableBody tr:not(.empty-msg-row)"
                       data-search-input="#itemSearch"
                       data-empty-text="No se encontraron ítems"
                       data-info-text-template="Mostrando {start} a {end} de {total} ítems"
                       data-erp-filters='[{"el":"#itemFiltroCategoria","attr":"data-categoria","match":"equals"},{"el":"#itemFiltroTipo","attr":"data-tipo","match":"equals"},{"el":"#itemFiltroEstado","attr":"data-estado","match":"equals"}]'
                       data-pagination-controls="#itemsPaginationControls"
                        data-pagination-info="#itemsPaginationInfo">
                    <thead class="inventario-sticky-thead bg-light border-bottom">
                        <tr>
                            <th class="text-secondary fw-semibold">SKU</th>
                            <th class="text-secondary fw-semibold">Nombre</th>
                            <th class="text-secondary fw-semibold">Tipo</th>
                            <th class="text-secondary fw-semibold">Categoría</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <?php if (empty($items)): ?>
                            <tr class="empty-msg-row">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay ítems registrados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $id = (int) ($item['id'] ?? 0);
                                $sku = (string) ($item['sku'] ?? '');
                                $nombre = (string) ($item['nombre'] ?? '');
                                $descripcion = (string) ($item['descripcion'] ?? '');
                                $tipo = (string) ($item['tipo_item'] ?? '');
                                $idCategoria = (int) ($item['id_categoria'] ?? 0);
                                $estado = (int) ($item['estado'] ?? 0);
                                $isActivo = $estado === 1;
                                $catNombre = $categoriasPorId[$idCategoria] ?? 'Sin categoría';
                                $tipoLabelUi = $tipoItemLabel($tipo);
                                
                                // Construir el texto de búsqueda (esto es VITAL para el buscador estático)
                                $searchStr = strtolower(trim($sku . ' ' . $nombre . ' ' . $descripcion));
                                
                                // Lógica del icono BOM
                                $bomIcon = ((int) ($item['bom_pendiente'] ?? 0) === 1) 
                                    ? '<i class="bi bi-exclamation-triangle-fill text-warning ms-1" data-bs-toggle="tooltip" title="Falta definir una receta BOM"></i>' 
                                    : '';
                                    
                                // Lógica del botón eliminar
                                $puedeEliminar = (int) ($item['puede_eliminar'] ?? 0) === 1;
                                $motivoNoEliminar = (string) ($item['motivo_no_eliminar'] ?? '');
                                ?>
                                <tr data-search="<?php echo e($searchStr); ?>" 
                                    data-categoria="<?php echo $idCategoria; ?>" 
                                    data-tipo="<?php echo e($tipo); ?>" 
                                    data-estado="<?php echo $estado; ?>"
                                    data-id="<?php echo $id; ?>">
                                    
                                    <td class="fw-semibold text-secondary"><?php echo e($sku); ?></td>
                                    <td>
                                        <div class="fw-bold text-dark d-inline-flex align-items-center gap-1">
                                            <span><?php echo e($nombre); ?></span>
                                            <?php echo $bomIcon; ?>
                                        </div>
                                        <div class="small text-muted"><?php echo e($descripcion); ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo e($tipoLabelUi); ?></span></td>
                                    <td><?php echo e($catNombre); ?></td>
                                    <td class="text-center">
                                        <span id="badge_status_item_<?php echo $id; ?>" class="badge rounded-pill <?php echo $isActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>">
                                            <?php echo $isActivo ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <div class="form-check form-switch pt-1 m-0" data-bs-toggle="tooltip" title="Cambiar estado">
                                                <input class="form-check-input switch-estado-item-dynamic" type="checkbox" role="switch" style="cursor: pointer; width: 2.5em; height: 1.25em;" data-id="<?php echo $id; ?>" <?php echo $isActivo ? 'checked' : ''; ?>>
                                            </div>
                                            <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>
                                            
                                            <div class="d-inline-flex gap-1">
                                                <a href="?ruta=items/perfil&id=<?php echo $id; ?>" class="btn-icon text-info" data-bs-toggle="tooltip" title="Ver perfil y documentos">
                                                    <i class="bi bi-person-badge"></i>
                                                </a>

                                                <button class="btn-icon btn-icon-primary" data-bs-toggle="modal" data-bs-target="#modalEditarItem"
                                                    data-id="<?php echo $id; ?>"
                                                    data-sku="<?php echo e($sku); ?>"
                                                    data-nombre="<?php echo e($nombre); ?>"
                                                    data-descripcion="<?php echo e($descripcion); ?>"
                                                    data-tipo="<?php echo e($tipo); ?>"
                                                    data-marca="<?php echo (int) ($item['id_marca'] ?? 0); ?>"
                                                    data-unidad="<?php echo e((string) ($item['unidad_base'] ?? '')); ?>"
                                                    data-moneda="<?php echo e((string) ($item['moneda'] ?? '')); ?>"
                                                    data-impuesto="<?php echo (float) ($item['impuesto'] ?? 0); ?>"
                                                    data-precio="<?php echo (float) ($item['precio_venta'] ?? 0); ?>"
                                                    data-stock-minimo="<?php echo (float) ($item['stock_minimo'] ?? 0); ?>"
                                                    data-costo="<?php echo (float) ($item['costo_referencial'] ?? 0); ?>"
                                                    data-peso-kg="<?php echo (float) ($item['peso_kg'] ?? 0); ?>"
                                                    data-controla-stock="<?php echo (int) ($item['controla_stock'] ?? 0); ?>"
                                                    data-permite-decimales="<?php echo (int) ($item['permite_decimales'] ?? 0); ?>"
                                                    data-requiere-lote="<?php echo (int) ($item['requiere_lote'] ?? 0); ?>"
                                                    data-requiere-vencimiento="<?php echo (int) ($item['requiere_vencimiento'] ?? 0); ?>"
                                                    data-requiere-formula-bom="<?php echo (int) ($item['requiere_formula_bom'] ?? 0); ?>"
                                                    data-requiere-factor-conversion="<?php echo (int) ($item['requiere_factor_conversion'] ?? 0); ?>"
                                                    data-es-envase-retornable="<?php echo (int) ($item['es_envase_retornable'] ?? 0); ?>"
                                                    data-dias-alerta-vencimiento="<?php echo (int) ($item['dias_alerta_vencimiento'] ?? 0); ?>"
                                                    data-rubro="<?php echo (int) ($item['id_rubro'] ?? 0); ?>"
                                                    data-categoria="<?php echo $idCategoria; ?>"
                                                    data-sabor="<?php echo (int) ($item['id_sabor'] ?? 0); ?>"
                                                    data-presentacion="<?php echo (int) ($item['id_presentacion'] ?? 0); ?>"
                                                    data-estado="<?php echo $estado; ?>" data-bs-toggle="tooltip" title="Editar">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>

                                                <form method="post" class="d-inline m-0 p-0 form-eliminar-dinamico">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo e((string) ($csrf_token ?? '')); ?>">
                                                    <?php if ($puedeEliminar): ?>
                                                        <button type="submit" class="btn-icon btn-icon-danger" data-bs-toggle="tooltip" title="Eliminar"><i class="bi bi-trash3"></i></button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn-icon text-muted opacity-50" data-bs-toggle="tooltip" title="<?php echo e($motivoNoEliminar); ?>" disabled><i class="bi bi-trash3"></i></button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center px-4">
                <small class="text-muted fw-semibold" id="itemsPaginationInfo">Cargando...</small>
                <nav aria-label="Paginación de ítems">
                    <ul class="pagination mb-0 justify-content-end" id="itemsPaginationControls"></ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<?php require BASE_PATH . '/app/views/items/partials/_modal_crear.php'; ?>
<?php require BASE_PATH . '/app/views/items/partials/_modal_editar.php'; ?>
<?php require BASE_PATH . '/app/views/items/partials/_modal_unidades.php'; ?>
<?php require BASE_PATH . '/app/views/items/partials/_modal_categorias.php'; ?>

<script src="<?php echo e(asset_url('js/items/main.js')); ?>?v=<?php echo time(); ?>"></script>
