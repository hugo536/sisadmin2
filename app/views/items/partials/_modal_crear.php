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
                    <input type="hidden" name="nombre_manual_override" id="newNombreManualOverride" value="0">
                    
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-primary py-2"><i class="bi bi-tag me-2"></i>Identidad</div>
                            <div class="card-body">
                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Rubro <span class="text-danger">*</span></label>
                                        <select class="form-select" id="newRubro" name="id_rubro" required>
                                            <option value="" selected>Seleccionar...</option>
                                            <?php foreach ($rubros as $rubro): ?>
                                                <option value="<?php echo (int) $rubro['id']; ?>"><?php echo e((string) $rubro['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-select" id="newCategoria" name="id_categoria" required>
                                            <option value="" selected>Seleccionar...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>


                                    <div class="col-md-6">
                                        <label for="newTipo" class="form-label small text-muted mb-1">Tipo de ítem <span class="text-danger">*</span></label>
                                        <select class="form-select" id="newTipo" name="tipo_item" required>
                                            <option value="" selected>Seleccionar tipo</option>
                                            <option value="producto_terminado">Producto terminado</option>
                                            <option value="materia_prima">Materia Prima</option>
                                            <option value="insumo">Insumo</option>
                                            <option value="semielaborado">Semielaborado</option>
                                            <option value="material_empaque">Material de Empaque</option>
                                            <option value="servicio">Servicios / Otros</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4" id="newAutoIdentidadWrap">
                                        <label class="form-label fw-semibold mb-1">Automatización</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" id="newAutoIdentidad" name="autogenerar_identidad" value="1" checked>
                                            <label class="form-check-label small" for="newAutoIdentidad">Generar nombre y SKU automáticamente</label>
                                        </div>
                                    </div>


                                    <div class="col-md-9">
                                        <label for="newNombre" class="form-label small text-muted mb-1">Nombre del producto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control fw-bold" id="newNombre" name="nombre" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="newSku" class="form-label small text-muted mb-1">SKU</label>
                                        <input type="text" class="form-control sku-lockable" id="newSku" name="sku" readonly>
                                        <div class="invalid-feedback" style="font-size: 0.75rem;">Este SKU ya está en uso.</div>
                                        <div class="valid-feedback" style="font-size: 0.75rem;">SKU disponible.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="newUnidad" class="form-label small text-muted mb-1">Unidad base <span class="text-danger">*</span></label>
                                        <select class="form-select" id="newUnidad" name="unidad_base" required>
                                            <option value="UND" selected>UND</option>
                                            <option value="KG">KG</option>
                                            <option value="LT">LT</option>
                                            <option value="M">M</option>
                                            <option value="CAJA">CAJA</option>
                                            <option value="PAQ">PAQ</option>
                                        </select>
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
                        <div class="card-header bg-white fw-bold text-secondary py-2">
                            <i class="bi bi-sliders me-2"></i>Configuración Avanzada
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light-subtle h-100">
                                        <small class="text-uppercase text-muted fw-bold d-block mb-3" style="font-size: 0.7rem;">Inventario y Medidas</small>
                                        
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="newControlaStock" name="controla_stock" value="1">
                                                    <label class="form-check-label fw-bold text-dark" for="newControlaStock">Controlar Stock</label>
                                                </div>
                                                <div class="text-muted small" style="padding-left: 2.5em; font-size: 0.8rem;">Activar alertas de inventario</div>
                                            </div>
                                            <div id="newStockMinContainer" style="width: 100px;">
                                                <input type="number" class="form-control form-control-sm text-end" id="newStockMin" name="stock_minimo" placeholder="Mín." value="0.0000" disabled>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center" id="newPermiteDecimalesContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="newPermiteDecimales" name="permite_decimales" value="1">
                                                <label class="form-check-label fw-bold text-dark" for="newPermiteDecimales">Permite Decimales</label>
                                            </div>
                                            <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Ideal para ventas a granel (ej. Litros o Kg)."></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light-subtle h-100">
                                        <small class="text-uppercase text-muted fw-bold d-block mb-3" style="font-size: 0.7rem;">Trazabilidad y Calidad</small>
                                        
                                        <div class="d-flex align-items-center mb-3" id="newRequiereLoteContainer">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="newRequiereLote" name="requiere_lote" value="1">
                                                <label class="form-check-label fw-bold text-dark" for="newRequiereLote">Exigir Lote</label>
                                            </div>
                                            <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Obligatorio al registrar ingresos/salidas."></i>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-start" id="newRequiereVencimientoContainer">
                                            <div>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="newRequiereVencimiento" name="requiere_vencimiento" value="1">
                                                    <label class="form-check-label fw-bold text-dark" for="newRequiereVencimiento">Requiere Venc.</label>
                                                </div>
                                                <div class="text-muted small" style="padding-left: 2.5em; font-size: 0.8rem;">Notificar antes de caducar</div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2" id="newDiasAlertaContainer">
                                                <span class="small text-muted text-nowrap" style="font-size: 0.8rem;">Días alerta:</span>
                                                <input type="number" min="0" class="form-control form-control-sm text-center" id="newDiasAlerta" name="dias_alerta_vencimiento" style="width: 70px;" value="0" disabled>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="p-3 border rounded-3 bg-light-subtle">
                                        <small class="text-uppercase text-muted fw-bold d-block mb-3" style="font-size: 0.7rem;">Operaciones de Producción</small>
                                        
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="form-check form-switch mb-0" id="newRequiereFormulaBomContainer">
                                                    <input class="form-check-input" type="checkbox" id="newRequiereFormulaBom" name="requiere_formula_bom" value="1">
                                                    <label class="form-check-label fw-semibold text-dark" for="newRequiereFormulaBom">Requiere Fórmula (BOM)</label>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <div class="form-check form-switch mb-0" id="newRequiereFactorConversionContainer">
                                                    <input class="form-check-input" type="checkbox" id="newRequiereFactorConversion" name="requiere_factor_conversion" value="1">
                                                    <label class="form-check-label fw-semibold text-dark" for="newRequiereFactorConversion">Factor Conversión</label>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-check form-switch mb-0" id="newEsEnvaseRetornableContainer">
                                                    <input class="form-check-input" type="checkbox" id="newEsEnvaseRetornable" name="es_envase_retornable" value="1">
                                                    <label class="form-check-label fw-semibold text-dark" for="newEsEnvaseRetornable">Envase Retornable</label>
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
                    
                    <div class="col-12 modal-footer border-top-0 pb-0 px-0">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold" type="submit"><i class="bi bi-save me-2"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

