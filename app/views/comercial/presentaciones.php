<?php
$items = $items ?? [];
$presentaciones = $presentaciones ?? [];
?>

<div id="presentacionesApp" 
     data-url-obtener="?ruta=comercial/obtenerPresentacion"
     data-url-eliminar="?ruta=comercial/eliminarPresentacion"
     data-url-estado="?ruta=comercial/toggleEstadoPresentacion">

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
                            <input type="search" class="form-control bg-light border-start-0 ps-0" id="presentacionSearch" placeholder="Buscar por código, presentación o producto...">
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
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 15%;">Código (SKU)</th>
                                <th style="width: 30%;">Nombre Presentación</th>
                                <th class="text-center">Factor</th>
                                <th class="text-center">PESO (KG)</th>
                                <th class="text-end">Precio Menor</th>
                                <th class="text-end">Precio Mayor</th>
                                <th class="text-center">Min. Mayorista</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($presentaciones as $p): ?>
                                <?php
                                // Corrección: Convertir a float elimina .00 sin borrar los ceros de números como 10, 20 o 30
                                $factorLimpio = (float) ($p['factor'] ?? 0);
                                
                                $nombreFusion = trim(($p['item_nombre_full'] ?? '')); 
                                $estado = (int) ($p['estado'] ?? 1);
                                $codigo = $p['codigo_presentacion'] ?? '---';
                                $pesoBruto = isset($p['peso_bruto']) ? (float) $p['peso_bruto'] : 0;
                                ?>
                                <tr data-search="<?php echo htmlspecialchars(mb_strtolower($nombreFusion . ' ' . $codigo)); ?>"
                                    data-id-item="<?php echo (int) ($p['id_item'] ?? 0); ?>"
                                    data-estado="<?php echo $estado; ?>">
                                    
                                    <td class="ps-4 fw-bold text-primary font-monospace">
                                        <?php echo htmlspecialchars($codigo); ?>
                                    </td>

                                    <td class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($nombreFusion); ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">x<?php echo $factorLimpio; ?></span>
                                    </td>

                                    <td class="text-center text-muted">
                                        <?php echo number_format($pesoBruto, 3); ?> kg
                                    </td>

                                    <td class="text-end text-success fw-bold">
                                        S/ <?php echo number_format((float) $p['precio_x_menor'], 2); ?>
                                    </td>
                                    <td class="text-end text-secondary">
                                        <?php if ($p['precio_x_mayor'] !== null && $p['precio_x_mayor'] > 0): ?>
                                            S/ <?php echo number_format((float) $p['precio_x_mayor'], 2); ?>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted small">
                                        <?php echo !empty($p['cantidad_minima_mayor']) ? '> ' . (int) $p['cantidad_minima_mayor'] : '-'; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $estado === 1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'; ?>">
                                            <?php echo $estado === 1 ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <div class="form-check form-switch pt-1">
                                                <input class="form-check-input js-toggle-estado-presentacion" type="checkbox" role="switch"
                                                    data-id="<?php echo (int) $p['id']; ?>"
                                                    data-estado="<?php echo $estado; ?>"
                                                    <?php echo $estado === 1 ? 'checked' : ''; ?>>
                                            </div>
                                            <div class="vr bg-secondary opacity-25"></div>
                                            
                                            <button type="button" class="btn btn-sm text-primary js-editar-presentacion"
                                                    data-id="<?php echo (int) $p['id']; ?>"
                                                    title="Editar" style="background: transparent; border: none;">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>
                                            
                                            <button type="button" class="btn btn-sm text-danger js-eliminar-presentacion"
                                                    data-id="<?php echo (int) $p['id']; ?>"
                                                    title="Eliminar" style="background: transparent; border: none;">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($presentaciones)): ?>
                                <tr><td colspan="9" class="text-center py-5 text-muted">No hay presentaciones registradas.</td></tr>
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
                    <h5 class="modal-title fw-bold" id="modalTitle">
                        <i class="bi bi-plus-circle me-2"></i>Nueva Presentación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?php echo route_url('comercial/guardarPresentacion'); ?>" method="POST" id="formPresentacion">
                    <input type="hidden" name="id" id="presentacionId" value="">
                    
                    <div class="modal-body p-4 bg-light">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Producto Base</label>
                                <select class="form-select" name="id_item" id="inputItem" required>
                                    <option value="">Seleccione un producto...</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['nombre_completo'] ?? $item['nombre']); ?>
                                            (<?php echo $item['sku'] ?? 'S/C'; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">El nombre se generará automáticamente.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Factor (Unidades)</label>
                                <input type="number" step="0.01" class="form-control text-center fw-bold" name="factor" id="inputFactor" placeholder="Ej: 15" required>
                                <div class="form-text small text-center">Unidades por pack</div>
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-success fw-bold">Precio Público (Menor)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-success-subtle text-success">S/</span>
                                    <input type="number" step="0.01" class="form-control" name="precio_x_menor" id="inputPrecioMenor" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-primary fw-bold">Precio Mayorista</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary-subtle text-primary">S/</span>
                                    <input type="number" step="0.01" class="form-control" name="precio_x_mayor" id="inputPrecioMayor">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">Aplicar precio mayorista a partir de:</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" name="cantidad_minima_mayor" id="inputMinMayor" placeholder="Ej: 5">
                                    <span class="input-group-text">unidades (packs)</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">Peso del Pack (Kg)</label>
                                <input type="number" class="form-control" name="peso_bruto" id="peso_bruto" step="0.001" min="0" placeholder="Ej: 1.250" value="0.000">
                                <div class="form-text small">Si no se ingresa un valor se guardará 0.000 kg.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold">Guardar Datos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div> ```
