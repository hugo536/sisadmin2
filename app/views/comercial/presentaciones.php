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
            <button class="btn btn-primary shadow-sm js-crear-presentacion" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearPresentacion">
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
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="presentacionSearch" placeholder="Buscar por presentación o producto...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="presentacionFiltroProducto">
                        <option value="">Todos los productos base</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo (int) $item['id']; ?>"><?php echo htmlspecialchars($item['nombre_completo'] ?? $item['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="presentacionFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="presentacionesTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Nombre Presentación</th>
                            <th class="text-end">Precio Menor</th>
                            <th class="text-end">Precio Mayor</th>
                            <th class="text-center">Cant. Min Mayor</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4" style="width: 170px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presentaciones as $p): ?>
                            <?php
                            $factorLimpio = rtrim(rtrim((string) (float) ($p['factor'] ?? 0), '0'), '.');
                            if ($factorLimpio === '') {
                                $factorLimpio = '0';
                            }
                            $nombreFusion = trim(($p['item_nombre_full'] ?? $p['item_nombre'] ?? '') . ' x ' . $factorLimpio);
                            $estado = (int) ($p['estado'] ?? 1);
                            ?>
                            <tr data-search="<?php echo htmlspecialchars(mb_strtolower($nombreFusion . ' ' . ($p['nombre'] ?? ''))); ?>"
                                data-id-item="<?php echo (int) ($p['id_item'] ?? 0); ?>"
                                data-estado="<?php echo $estado; ?>">
                                <td class="ps-4 fw-bold text-dark" data-label="Nombre Presentación">
                                    <?php echo htmlspecialchars($nombreFusion); ?>
                                </td>
                                <td class="text-end text-success fw-semibold" data-label="Precio Menor">
                                    S/ <?php echo number_format((float) $p['precio_x_menor'], 2); ?>
                                </td>
                                <td class="text-end text-primary fw-semibold" data-label="Precio Mayor">
                                    <?php if ($p['precio_x_mayor'] !== null && $p['precio_x_mayor'] !== ''): ?>
                                        S/ <?php echo number_format((float) $p['precio_x_mayor'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-muted small" data-label="Cant. Min Mayor">
                                    <?php echo !empty($p['cantidad_minima_mayor']) ? '> ' . (int) $p['cantidad_minima_mayor'] . ' unds' : '-'; ?>
                                </td>
                                <td class="text-center" data-label="Estado">
                                    <span class="badge <?php echo $estado === 1 ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle'; ?>">
                                        <?php echo $estado === 1 ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4" data-label="Acciones">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="form-check form-switch pt-1" title="Cambiar estado">
                                            <input class="form-check-input js-toggle-estado-presentacion" type="checkbox" role="switch"
                                                   style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                   data-id="<?php echo (int) $p['id']; ?>"
                                                   data-estado="<?php echo $estado; ?>"
                                                   <?php echo $estado === 1 ? 'checked' : ''; ?>>
                                        </div>
                                        <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>
                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent js-editar-presentacion"
                                                data-id="<?php echo (int) $p['id']; ?>"
                                                data-id-item="<?php echo (int) $p['id_item']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($p['nombre'] ?? ''); ?>"
                                                data-factor="<?php echo htmlspecialchars((string) $p['factor']); ?>"
                                                data-precio-menor="<?php echo htmlspecialchars((string) $p['precio_x_menor']); ?>"
                                                data-precio-mayor="<?php echo htmlspecialchars((string) ($p['precio_x_mayor'] ?? '')); ?>"
                                                data-cantidad-minima="<?php echo htmlspecialchars((string) ($p['cantidad_minima_mayor'] ?? '')); ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalCrearPresentacion"
                                                title="Editar presentación">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light text-danger border-0 bg-transparent js-eliminar-presentacion"
                                                data-id="<?php echo (int) $p['id']; ?>"
                                                title="Eliminar presentación">
                                            <i class="bi bi-trash fs-5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($presentaciones)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No hay presentaciones registradas.</td></tr>
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
            <form action="<?php echo route_url('comercial/guardarPresentacion'); ?>" method="POST" id="formPresentacion">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="id" value="">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Producto Base</label>
                        <select class="form-select" name="id_item" required>
                            <option value="">Seleccione un producto...</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['nombre_completo'] ?? $item['nombre']); ?>
                                </option>
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
