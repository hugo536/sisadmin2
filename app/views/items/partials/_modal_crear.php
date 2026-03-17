<div class="modal fade" id="modalCrearItem" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar ítem</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body bg-light p-3 p-md-4">
                <form method="post" class="row g-4 m-0" id="formCrearItem">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="nombre_manual_override" id="newNombreManualOverride" value="0">
                    
                    <div class="col-12 px-0">
                        <div class="card modal-pastel-card rounded-3 bg-white">
                            <div class="card-header fw-bold text-dark py-2">
                                <i class="bi bi-tag header-icon-primary me-2"></i>Identidad
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted fw-semibold mb-1">Rubro <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="newRubro" name="id_rubro" required>
                                            <option value="" selected disabled hidden>Seleccionar...</option>
                                            <?php foreach ($rubros as $rubro): ?>
                                                <option value="<?php echo (int) $rubro['id']; ?>"><?php echo e((string) $rubro['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted fw-semibold mb-1">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="newCategoria" name="id_categoria" required>
                                            <option value="" selected disabled hidden>Seleccionar...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="newTipo" class="form-label small text-muted fw-semibold mb-1">Tipo de ítem <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="newTipo" name="tipo_item" required>
                                            <option value="" selected disabled hidden>Seleccionar tipo</option>
                                            
                                            <option value="producto_terminado" class="fw-bold text-primary">📦 Producto terminado</option>
                                            <option value="semielaborado" class="fw-bold text-info">🔄 Semielaborado</option>
                                            <option value="materia_prima" class="fw-bold text-success">🌿 Materia Prima</option>
                                            <option value="material_empaque" class="fw-bold text-warning">🏷️ Material de Empaque</option>
                                            <option value="insumo" class="fw-bold text-secondary">🔧 Insumo (Consumibles de fábrica)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 d-flex align-items-end pb-1" id="newAutoIdentidadWrap">
                                        <div class="form-check form-switch mb-0 bg-pastel-light border border-secondary-subtle rounded px-3 py-2 w-100 d-flex align-items-center">
                                            <input class="form-check-input mt-0 me-3" type="checkbox" id="newAutoIdentidad" name="autogenerar_identidad" value="1" checked>
                                            <label class="form-check-label small fw-medium text-dark" for="newAutoIdentidad" style="padding-top: 1px;">Generar SKU automáticamente</label>
                                        </div>
                                    </div>

                                    <div class="col-md-9">
                                        <label for="newNombre" class="form-label small text-muted fw-semibold mb-1">Nombre del producto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control shadow-none border-secondary-subtle fw-bold" id="newNombre" name="nombre" required placeholder="Ej. Botella PET 500ml">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="newSku" class="form-label small text-muted fw-semibold mb-1">SKU</label>
                                        <input type="text" class="form-control shadow-none border-secondary-subtle sku-lockable bg-light" id="newSku" name="sku" readonly>
                                        <div class="invalid-feedback" style="font-size: 0.75rem;">Este SKU ya está en uso.</div>
                                        <div class="valid-feedback" style="font-size: 0.75rem;">SKU disponible.</div>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="newUnidad" class="form-label small text-muted fw-semibold mb-1">Unidad base <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="newUnidad" name="unidad_base" required>
                                            <option value="UND" selected>UND</option>
                                            <option value="KG">KG</option>
                                            <option value="LT">LT</option>
                                            <option value="M">M</option>
                                            <option value="CAJA">CAJA</option>
                                            <option value="PAQ">PAQ</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4" id="newSaborContainer">
                                        <label class="form-label small text-muted fw-semibold mb-1">Sabor / Variante <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="newSabor" name="id_sabor">
                                            <option value="" selected>Ninguno...</option>
                                            <?php foreach ($sabores as $sabor): ?>
                                                <option value="<?php echo (int) $sabor['id']; ?>"><?php echo e((string) $sabor['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4" id="newMarcaContainer">
                                        <label class="form-label small text-muted fw-semibold mb-1">Marca <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="newMarca" name="id_marca">
                                            <option value="" selected>Ninguna...</option>
                                            <?php foreach ($marcas as $marca): ?>
                                                <option value="<?php echo (int) ($marca['id'] ?? 0); ?>"><?php echo e((string) $marca['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-12" id="newPresentacionContainer">
                                        <label class="form-label small text-muted fw-semibold mb-1">Presentación / Envase <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="newPresentacion" name="id_presentacion">
                                            <option value="" selected>Ninguna...</option>
                                            <?php foreach ($presentaciones as $presentacion): ?>
                                                <option value="<?php echo (int) $presentacion['id']; ?>"><?php echo e((string) $presentacion['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="newPrecio" name="precio_venta" value="0.0000">
                    <input type="hidden" id="newMoneda" name="moneda" value="PEN">

                    <div class="col-12 px-0" id="newComercialCard">
                        <div class="card modal-pastel-card rounded-3 bg-white">
                            <div class="card-header fw-bold text-dark py-2">
                                <i class="bi bi-currency-dollar header-icon-success me-2"></i>Comercial
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted fw-semibold mb-1">Costo Referencial</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-pastel-light border-secondary-subtle text-muted">S/</span>
                                            <input type="number" step="0.0001" class="form-control shadow-none border-secondary-subtle" id="newCosto" name="costo_referencial" value="0.0000">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted fw-semibold mb-1">Impuesto (%)</label>
                                        <input type="number" step="0.0001" class="form-control form-control-sm shadow-none border-secondary-subtle" id="newImpuesto" name="impuesto" value="18.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted fw-semibold mb-1">Peso (kg)</label>
                                        <input type="number" step="0.001" min="0" class="form-control form-control-sm shadow-none border-secondary-subtle" id="newPesoKg" name="peso_kg" value="0.000" placeholder="0.000">
                                    </div>

                                    <div class="col-12">
                                        <input type="text" class="form-control form-control-sm shadow-none bg-pastel-light border-secondary-subtle" id="newDescripcion" name="descripcion" placeholder="Añadir descripción o especificaciones adicionales (Opcional)">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 px-0">
                        <div class="card modal-pastel-card rounded-3 bg-white">
                            <div class="card-header fw-bold text-dark py-2">
                                <i class="bi bi-sliders2 header-icon-secondary me-2"></i>Configuración Avanzada
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    
                                    <div class="col-md-6">
                                        <div class="p-3 border border-secondary-subtle rounded-3 bg-pastel-light h-100">
                                            <small class="text-uppercase text-secondary fw-bold d-block mb-3" style="font-size: 0.7rem; letter-spacing: 0.5px;">Inventario y Medidas</small>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="newControlaStock" name="controla_stock" value="1">
                                                    <label class="form-check-label fw-medium text-dark small" for="newControlaStock">Controlar Stock Mínimo</label>
                                                </div>
                                                <div id="newStockMinContainer" style="width: 80px;">
                                                    <input type="number" class="form-control form-control-sm text-end shadow-none border-secondary-subtle" id="newStockMin" name="stock_minimo" placeholder="Mín." value="0" disabled>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center" id="newPermiteDecimalesContainer">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="newPermiteDecimales" name="permite_decimales" value="1">
                                                    <label class="form-check-label fw-medium text-dark small" for="newPermiteDecimales">Permitir Decimales</label>
                                                </div>
                                                <i class="bi bi-info-circle-fill text-secondary opacity-50 ms-2" data-bs-toggle="tooltip" title="Para ventas a granel (ej. 1.5 Kg)."></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="p-3 border border-secondary-subtle rounded-3 bg-pastel-light h-100">
                                            <small class="text-uppercase text-secondary fw-bold d-block mb-3" style="font-size: 0.7rem; letter-spacing: 0.5px;">Trazabilidad y Calidad</small>
                                            
                                            <div class="d-flex align-items-center mb-3" id="newRequiereLoteContainer">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="newRequiereLote" name="requiere_lote" value="1">
                                                    <label class="form-check-label fw-medium text-dark small" for="newRequiereLote">Exigir Lote de Producción</label>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center" id="newRequiereVencimientoContainer">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="newRequiereVencimiento" name="requiere_vencimiento" value="1">
                                                    <label class="form-check-label fw-medium text-dark small" for="newRequiereVencimiento">Requiere Vencimiento</label>
                                                </div>
                                                <div class="d-flex align-items-center gap-2" id="newDiasAlertaContainer">
                                                    <span class="small text-muted" style="font-size: 0.75rem;">Alerta (días):</span>
                                                    <input type="number" min="0" class="form-control form-control-sm text-center shadow-none border-secondary-subtle" id="newDiasAlerta" name="dias_alerta_vencimiento" style="width: 60px;" value="0" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-3 border border-secondary-subtle rounded-3 bg-pastel-light">
                                            <small class="text-uppercase text-secondary fw-bold d-block mb-3" style="font-size: 0.7rem; letter-spacing: 0.5px;">Operaciones de Producción</small>
                                            
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="newRequiereFormulaBomContainer">
                                                        <input class="form-check-input" type="checkbox" id="newRequiereFormulaBom" name="requiere_formula_bom" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="newRequiereFormulaBom">Fórmula (BOM)</label>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="newRequiereFactorConversionContainer">
                                                        <input class="form-check-input" type="checkbox" id="newRequiereFactorConversion" name="requiere_factor_conversion" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="newRequiereFactorConversion">Conversión Unidad</label>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="newEsEnvaseRetornableContainer">
                                                        <input class="form-check-input" type="checkbox" id="newEsEnvaseRetornable" name="es_envase_retornable" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="newEsEnvaseRetornable">Envase Retornable</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="newEstado" name="estado" value="1">
                    
                    <div class="col-12 mt-4 pt-3 pb-0 px-0 d-flex justify-content-end border-top">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold shadow-sm" type="submit"><i class="bi bi-save me-2"></i>Guardar Ítem</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>