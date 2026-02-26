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

<meta name="csrf-token" content="<?php echo e((string) ($csrf_token ?? '')); ?>">

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam me-2 text-primary"></i> Ítems y Servicios
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo maestro de ítems.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalUnidadesConversion">
                <i class="bi bi-arrow-left-right me-2 text-warning"></i>Unidades y Conversiones
                <?php if (count($pendientesConversion) > 0): ?>
                    <span class="badge rounded-pill bg-warning text-dark ms-2" id="ucPendientesBadge"><?php echo count($pendientesConversion); ?></span>
                <?php endif; ?>
            </button>
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionRubros">
                <i class="bi bi-diagram-3 me-2 text-info"></i>Rubros
            </button>
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
                <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive inventario-table-wrapper">
                <table class="table align-middle mb-0 table-pro" id="itemsTable">
                    <thead class="inventario-sticky-thead bg-light">
                        <tr>
                            <th class="ps-4 text-center" style="width: 50px;"><i class="bi bi-image text-muted"></i></th> <th>SKU</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Cargando ítems...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-1">
        <div class="small text-muted" id="itemsPaginationInfo">Cargando información...</div>
        <nav aria-label="Paginación de ítems">
            <ul class="pagination pagination-sm mb-0" id="itemsPaginationControls">
                </ul>
        </nav>
    </div>
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

<?php require BASE_PATH . '/app/views/items/partials/_modal_crear.php'; ?>
<?php require BASE_PATH . '/app/views/items/partials/_modal_editar.php'; ?>
<?php require BASE_PATH . '/app/views/items/partials/_modal_unidades.php'; ?>
<?php require BASE_PATH . '/app/views/items/partials/_modal_categorias.php'; ?>
