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
            
            <div class="btn-group shadow-sm">
                <button type="button" class="btn btn-primary js-crear-presentacion" data-bs-toggle="modal" data-bs-target="#modalCrearPresentacion">
                    <i class="bi bi-plus-lg me-2"></i>Nueva Presentación
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                    <li>
                        <a class="dropdown-item d-flex align-items-center py-2 js-crear-presentacion-mixta" href="#" data-bs-toggle="modal" data-bs-target="#modalCrearPresentacion">
                            <i class="bi bi-diagram-3 me-2 text-primary"></i> Crear Pack Mixto / Especial
                        </a>
                    </li>
                </ul>
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
                                <th class="ps-4" style="width: 14%;">Código (SKU)</th>
                                <th style="width: 27%;">Nombre Presentación</th>
                                <th class="text-center" style="width: 6%;">Factor</th>
                                <th class="text-center" style="width: 10%;">PESO (KG)</th>
                                <th class="text-end" style="width: 11%;">Precio Menor</th>
                                <th class="text-end">Precio Mayor</th>
                                <th class="text-center">Min. Mayorista</th>
                                <th class="text-center">Stock Min</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($presentaciones as $p): ?>
                                <?php
                                $factorLimpio = (float) ($p['factor'] ?? 0);
                                $nombreFusion = trim(($p['item_nombre_full'] ?? ''));
                                $esMixto = (int) ($p['es_mixto'] ?? 0) === 1;
                                $composicionMixta = trim((string) ($p['composicion_mixta'] ?? ''));
                                $estado = (int) ($p['estado'] ?? 1);
                                $codigo = $p['codigo_presentacion'] ?? '---';
                                $pesoBruto = isset($p['peso_bruto']) ? (float) $p['peso_bruto'] : 0;
                                $cantidadItemsDistintos = (int)($p['cantidad_items_distintos'] ?? 0);
                                $notaPack = trim($p['nota_pack'] ?? '');
                                ?>
                                <tr data-search="<?php echo htmlspecialchars(mb_strtolower($nombreFusion . ' ' . $codigo . ' ' . $notaPack)); ?>"
                                    data-id-item="<?php echo (int) ($p['id_item'] ?? 0); ?>"
                                    data-estado="<?php echo $estado; ?>">
                                    
                                    <td class="ps-4 fw-bold text-primary font-monospace">
                                        <?php echo htmlspecialchars($codigo); ?>
                                    </td>

                                    <td class="fw-semibold text-dark">
                                        <div class="d-flex align-items-center flex-wrap">
                                            <span class="me-2"><?php echo htmlspecialchars($nombreFusion); ?></span>
                                            
                                            <?php if ($esMixto && $cantidadItemsDistintos > 1): ?>
                                                <i class="bi bi-diagram-3-fill text-info fs-6" 
                                                   data-bs-toggle="tooltip" 
                                                   title="<?php echo htmlspecialchars($composicionMixta ?: 'Pack Variado'); ?>"
                                                   style="cursor: help;"></i>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($notaPack)): ?>
                                            <div class="small text-primary fst-italic mt-1" style="font-size: 0.85em;">
                                                <i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($notaPack); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">x<?php echo $factorLimpio; ?></span>
                                    </td>
                                    <td class="text-center text-muted"><?php echo number_format($pesoBruto, 3); ?> kg</td>
                                    <td class="text-end text-success fw-bold">S/ <?php echo number_format((float) $p['precio_x_menor'], 2); ?></td>
                                    <td class="text-end text-secondary">
                                        <?php echo ($p['precio_x_mayor'] > 0) ? 'S/ ' . number_format((float) $p['precio_x_mayor'], 2) : '-'; ?>
                                    </td>
                                    <td class="text-center small text-muted"><?php echo !empty($p['cantidad_minima_mayor']) ? '> ' . (int) $p['cantidad_minima_mayor'] : '-'; ?></td>
                                    <td class="text-center small"><?php echo (float) $p['stock_minimo']; ?></td>
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
                                            <button type="button" class="btn btn-sm text-primary js-editar-presentacion" data-id="<?php echo (int) $p['id']; ?>"><i class="bi bi-pencil-square fs-5"></i></button>
                                            <button type="button" class="btn btn-sm text-danger js-eliminar-presentacion" data-id="<?php echo (int) $p['id']; ?>"><i class="bi bi-trash fs-5"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCrearPresentacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"> 
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle">
                        <i class="bi bi-plus-circle me-2"></i>Nueva Presentación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <form action="<?php echo route_url('comercial/guardarPresentacion'); ?>" method="POST" id="formPresentacion">
                    <input type="hidden" name="id" id="presentacionId" value="">
                    <input type="hidden" id="es_mixto" name="es_mixto" value="0">

                    <div class="modal-body p-4 bg-light">
                        
                        <div class="row g-3 mb-3 js-modo-simple">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Producto Principal</label>
                                <select class="form-select" name="id_item" id="inputItem">
                                    <option value="">Seleccione el producto padre...</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['nombre_completo'] ?? $item['nombre']); ?> (<?php echo $item['sku']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">El nombre y SKU se generarán automáticamente.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3 d-none js-modo-mixto">
                            <div class="col-md-8">
                                <label class="form-label fw-bold text-primary">Nombre Personalizado / Pack</label>
                                <input type="text" class="form-control" name="nombre_manual" id="inputNombreManual" placeholder="Ej: Pack Surtido Verano / Guaraná Especial">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-primary">SKU / Código</label>
                                <input type="text" class="form-control fw-bold" name="codigo_presentacion" id="inputCodigoManual" placeholder="Ej: MIX-001">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1"><i class="bi bi-info-circle me-1"></i>Observaciones / Variantes (Opcional)</label>
                            <input type="text" class="form-control form-control-sm bg-white" name="nota_pack" id="inputNotaPack" placeholder="Ej: Solo botellas verdes / No incluye sabor Fresa">
                        </div>

                        <div class="row g-3 mb-3 js-modo-simple">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Factor (Unidades)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control text-center fw-bold" name="factor" id="inputFactor" placeholder="Ej: 15">
                                    <span class="input-group-text bg-white">unid.</span>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3 d-none js-modo-mixto" id="seccionComposicionMixta">
                            <div class="card-header bg-white border-bottom-0 pt-3">
                                <label class="fw-bold text-dark mb-1"><i class="bi bi-search me-1"></i> Contenido del pack (Buscar productos):</label>
                                <select class="form-select" id="inputBusquedaComponente">
                                    <option value="">Buscar producto...</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" 
                                                data-nombre="<?php echo htmlspecialchars($item['nombre_completo'] ?? $item['nombre']); ?>"
                                                data-unidad="<?php echo htmlspecialchars((string) ($item['unidad_base'] ?? 'UND')); ?>">
                                            <?php echo htmlspecialchars($item['nombre_completo'] ?? $item['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 table-striped" id="tablaComposicionMixta">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Producto / Sabor</th>
                                                <th style="width: 20%;" class="text-center">Cantidad</th>
                                                <th style="width: 10%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot class="table-light border-top">
                                            <tr>
                                                <td class="text-end pe-3 fw-bold text-muted">TOTAL UNIDADES:</td>
                                                <td class="text-center fw-bold text-primary fs-5" id="totalUnidadesPack">0</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="row g-3 mb-3">
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
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Aplicar mayorista desde (Unidades):</label>
                                <input type="number" class="form-control form-control-sm" name="cantidad_minima_mayor" id="inputMinMayor" placeholder="Ej: 5">
                            </div>
                             <div class="col-md-6">
                                <label class="form-label small text-muted">Peso Bruto Total (Kg):</label>
                                <input type="number" class="form-control form-control-sm" name="peso_bruto" id="peso_bruto" step="0.001" value="0.000">
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-secondary mb-3">
                                <i class="bi bi-sliders me-2"></i>Configuración Avanzada
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="checkControlStock">
                                            <label class="form-check-label fw-semibold text-dark" for="checkControlStock">Controlar Stock</label>
                                        </div>
                                        <div class="w-50">
                                            <input type="number" class="form-control form-control-sm text-end" name="stock_minimo" id="stock_minimo" step="0.01" value="0" disabled placeholder="Mín.">
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="exigir_lote" name="exigir_lote" value="1">
                                            <label class="form-check-label fw-semibold text-dark" for="exigir_lote">Exigir Lote</label>
                                        </div>
                                        <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Será obligatorio indicar de qué lote sale este pack al momento de despachar."></i>
                                    </div>

                                </div>

                                <div class="col-md-6">
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="requiere_vencimiento" name="requiere_vencimiento" value="1">
                                            <label class="form-check-label fw-semibold text-dark" for="requiere_vencimiento">Requiere Venc.</label>
                                        </div>
                                        <div class="input-group input-group-sm w-50">
                                            <input type="number" class="form-control text-center" name="dias_vencimiento_alerta" id="dias_vencimiento_alerta" value="0" min="0" disabled title="Días previos para alertar">
                                            <span class="input-group-text bg-light text-muted">días</span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold" id="btnGuardar">Guardar Datos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>