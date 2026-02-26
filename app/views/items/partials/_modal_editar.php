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
                    <input type="hidden" name="estado" id="editEstado" value="1">
                    <input type="hidden" name="nombre_manual_override" id="editNombreManualOverride" value="0">
                    
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold text-primary py-2"><i class="bi bi-tag me-2"></i>Identidad</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Rubro <span class="text-danger">*</span></label>
                                        <select class="form-select" id="editRubro" name="id_rubro" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($rubros as $rubro): ?>
                                                <option value="<?php echo (int) $rubro['id']; ?>"><?php echo e((string) $rubro['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-select" id="editCategoria" name="id_categoria" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-12">
                                        <label for="editTipo" class="form-label small text-muted mb-1">Tipo de ítem <span class="text-danger">*</span></label>
                                        <select class="form-select" id="editTipo" name="tipo_item" required>
                                            <option value="producto_terminado">Producto terminado</option>
                                            <option value="materia_prima">Materia prima</option>
                                            <option value="insumo">Insumo</option>
                                            <option value="semielaborado">Semielaborado</option>
                                            <option value="material_empaque">Material de empaque</option>
                                            <option value="servicio">Servicio</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 d-none" id="editAutoIdentidadWrap">
                                        <label class="form-label fw-semibold mb-1">Automatización</label>
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" id="editAutoIdentidad" name="autogenerar_identidad" value="0">
                                            <label class="form-check-label small" for="editAutoIdentidad">Generar nombre y SKU automáticamente</label>
                                        </div>
                                    </div>

                                    <div class="col-md-9">
                                        <label for="editNombre" class="form-label small text-muted mb-1">Nombre del producto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control fw-bold" id="editNombre" name="nombre" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="editSku" class="form-label small text-muted mb-1">SKU</label>
                                        <input type="text" class="form-control sku-lockable bg-light" id="editSku" name="sku" readonly title="El SKU no puede modificarse una vez creado el ítem.">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="editUnidadSelect" class="form-label small text-muted mb-1">Unidad base <span class="text-danger">*</span></label>
                                        <select class="form-select bg-light" id="editUnidadSelect" disabled title="La unidad base no puede cambiarse para evitar descuadres de stock.">
                                            <option value="UND">UND</option>
                                            <option value="KG">KG</option>
                                            <option value="LT">LT</option>
                                            <option value="M">M</option>
                                            <option value="CAJA">CAJA</option>
                                            <option value="PAQ">PAQ</option>
                                        </select>
                                        <input type="hidden" id="editUnidad" name="unidad_base">
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
                                                        <input class="form-check-input" type="checkbox" id="editControlaStock" name="controla_stock" value="1">
                                                        <label class="form-check-label fw-bold text-dark" for="editControlaStock">Controlar Stock</label>
                                                    </div>
                                                    <div class="text-muted small" style="padding-left: 2.5em; font-size: 0.8rem;">Activar alertas de inventario</div>
                                                </div>
                                                <div id="editStockMinimoContainer" style="width: 100px;">
                                                    <input class="form-control form-control-sm text-end" id="editStockMinimo" name="stock_minimo" type="number" step="0.0001" disabled placeholder="Mín.">
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center" id="editPermiteDecimalesContainer">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="editPermiteDecimales" name="permite_decimales" value="1">
                                                    <label class="form-check-label fw-bold text-dark" for="editPermiteDecimales">Permite Decimales</label>
                                                </div>
                                                <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Ideal para ventas a granel (ej. Litros o Kg)."></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 bg-light-subtle h-100">
                                            <small class="text-uppercase text-muted fw-bold d-block mb-3" style="font-size: 0.7rem;">Trazabilidad y Calidad</small>
                                            
                                            <div class="d-flex align-items-center mb-3" id="editRequiereLoteContainer">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="editRequiereLote" name="requiere_lote" value="1">
                                                    <label class="form-check-label fw-bold text-dark" for="editRequiereLote">Exigir Lote</label>
                                                </div>
                                                <i class="bi bi-info-circle-fill text-primary opacity-50 ms-2" data-bs-toggle="tooltip" data-bs-placement="right" title="Obligatorio al registrar ingresos/salidas."></i>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-start" id="editRequiereVencimientoContainer">
                                                <div>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" id="editRequiereVencimiento" name="requiere_vencimiento" value="1">
                                                        <label class="form-check-label fw-bold text-dark" for="editRequiereVencimiento">Requiere Venc.</label>
                                                    </div>
                                                    <div class="text-muted small" style="padding-left: 2.5em; font-size: 0.8rem;">Notificar antes de caducar</div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2" id="editDiasAlertaContainer">
                                                    <span class="small text-muted text-nowrap" style="font-size: 0.8rem;">Días alerta:</span>
                                                    <input type="number" min="0" class="form-control form-control-sm text-center" id="editDiasAlerta" name="dias_alerta_vencimiento" style="width: 70px;" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-3 border rounded-3 bg-light-subtle">
                                            <small class="text-uppercase text-muted fw-bold d-block mb-3" style="font-size: 0.7rem;">Operaciones de Producción</small>
                                            
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="editRequiereFormulaBomContainer">
                                                        <input class="form-check-input" type="checkbox" id="editRequiereFormulaBom" name="requiere_formula_bom" value="1">
                                                        <label class="form-check-label fw-semibold text-dark" for="editRequiereFormulaBom">Requiere Fórmula (BOM)</label>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="editRequiereFactorConversionContainer">
                                                        <input class="form-check-input" type="checkbox" id="editRequiereFactorConversion" name="requiere_factor_conversion" value="1">
                                                        <label class="form-check-label fw-semibold text-dark" for="editRequiereFactorConversion">Factor Conversión</label>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="editEsEnvaseRetornableContainer">
                                                        <input class="form-check-input" type="checkbox" id="editEsEnvaseRetornable" name="es_envase_retornable" value="1">
                                                        <label class="form-check-label fw-semibold text-dark" for="editEsEnvaseRetornable">Envase Retornable</label>
                                                    </div>
                                                </div>
                                            </div>
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

