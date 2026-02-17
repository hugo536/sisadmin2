<?php 
    $items = $items ?? []; 
    $presentaciones = $presentaciones ?? []; 
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box2-fill me-2 text-primary"></i> Presentaciones y Packs
            </h1>
            <p class="text-muted small mb-0 ms-1">Define formatos de venta (Packs, Cajas) y sus factores de conversión.</p>
        </div>
        <div>
            <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearPresentacion">
                <i class="bi bi-plus-lg me-2"></i>Nueva Presentación
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="presentacionSearch" placeholder="Buscar por nombre de pack o producto...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover" id="presentacionesTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Nombre Presentación</th>
                            <th>Producto Base</th>
                            <th class="text-center">Factor</th>
                            <th class="text-end">Precio Menor</th>
                            <th class="text-end">Precio Mayor</th>
                            <th class="text-center">Cant. Min Mayor</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presentaciones as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark">
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                </td>
                                <td class="text-muted">
                                    <i class="bi bi-arrow-return-right me-1"></i> <?php echo htmlspecialchars($p['item_nombre']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-3">
                                        x <?php echo (float)$p['factor']; ?>
                                    </span>
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    S/ <?php echo number_format((float)$p['precio_x_menor'], 2); ?>
                                </td>
                                <td class="text-end text-primary fw-semibold">
                                    S/ <?php echo number_format((float)$p['precio_x_mayor'], 2); ?>
                                </td>
                                <td class="text-center text-muted small">
                                    > <?php echo (int)$p['cantidad_minima_mayor']; ?> unds
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light text-primary border-0 bg-transparent js-editar-presentacion"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-json='<?php echo json_encode($p, JSON_HEX_APOS); ?>'
                                            data-bs-toggle="modal" data-bs-target="#modalEditarPresentacion">
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light text-danger border-0 bg-transparent js-eliminar-presentacion"
                                            data-id="<?php echo $p['id']; ?>">
                                        <i class="bi bi-trash fs-5"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($presentaciones)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No hay presentaciones registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearPresentacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nueva Presentación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo route_url('comercial/presentaciones/guardar'); ?>" method="POST">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="id" value="">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Producto Base</label>
                        <select class="form-select" name="id_item" required>
                            <option value="">Seleccione un producto...</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre Presentación</label>
                            <input type="text" class="form-control" name="nombre" placeholder="Ej: Pack x6 Plástico" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Factor</label>
                            <input type="number" step="0.01" class="form-control" name="factor" placeholder="6" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-success">Precio Público (Menor)</label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input type="number" step="0.01" class="form-control" name="precio_x_menor" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-primary">Precio Mayorista</label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input type="number" step="0.01" class="form-control" name="precio_x_mayor">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">Aplicar mayorista a partir de:</label>
                            <input type="number" class="form-control" name="cantidad_minima_mayor" placeholder="Ej: 12 unidades">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>