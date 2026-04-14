<div class="modal fade" id="modalEditarItem" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar ítem</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body bg-light p-3 p-md-4">
                <form method="post" class="row g-4 m-0" id="formEditarItem">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editId">
                    <input type="hidden" name="estado" id="editEstado" value="1">
                    <input type="hidden" name="nombre_manual_override" id="editNombreManualOverride" value="0">
                    
                    <div class="col-12 px-0">
                        <div class="card modal-pastel-card rounded-3 bg-white">
                            <div class="card-header fw-bold text-dark py-2">
                                <i class="bi bi-tag header-icon-primary me-2"></i>Identidad
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted fw-semibold mb-1">Rubro <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="editRubro" name="id_rubro" required>
                                            <option value="" disabled hidden>Seleccionar...</option>
                                            <?php foreach ($rubros as $rubro): ?>
                                                <option value="<?php echo (int) $rubro['id']; ?>"><?php echo e((string) $rubro['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted fw-semibold mb-1">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="editCategoria" name="id_categoria" required>
                                            <option value="" disabled hidden>Seleccionar...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-12">
                                        <label for="editTipo" class="form-label small text-muted fw-semibold mb-1">Tipo de ítem <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="editTipo" name="tipo_item" required>
                                            <option value="producto_terminado">Producto terminado</option>
                                            <option value="materia_prima">Materia prima</option>
                                            <option value="insumo">Insumo</option>
                                            <option value="semielaborado">Semielaborado</option>
                                            <option value="material_empaque">Material de empaque</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label for="editNombre" class="form-label small text-muted fw-semibold mb-1">Nombre del producto / ítem <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control shadow-none border-secondary-subtle fw-bold" id="editNombre" name="nombre" required>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label for="editSku" class="form-label small text-muted fw-semibold mb-0">Código SKU</label>
                                            <div class="form-check form-switch mb-0" title="Generar código aleatorio automáticamente" data-bs-toggle="tooltip">
                                                <input class="form-check-input mt-0" type="checkbox" id="editAutoIdentidad" name="autogenerar_identidad" value="0" style="cursor: pointer;">
                                                <label class="form-check-label text-primary" style="font-size: 0.7rem; margin-top: 2px;">Auto</label>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control shadow-none border-secondary-subtle sku-lockable bg-light" id="editSku" name="sku" readonly title="El SKU no puede modificarse libremente una vez creado el ítem." placeholder="Generado por sistema">
                                    </div>

                                    <div class="col-md-4">
                                        <label for="editUnidadSelect" class="form-label small text-muted fw-semibold mb-1">Unidad base <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="editUnidadSelect" name="unidad_base" required>
                                            <option value="UND">UND</option>
                                            <option value="KG">KG</option>
                                            <option value="LT">LT</option>
                                            <option value="M">M</option>
                                            <option value="CAJA">CAJA</option>
                                            <option value="PAQ">PAQ</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4" id="editSaborContainer">
                                        <label class="form-label small text-muted fw-semibold mb-1">Sabor / Variante <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="editSabor" name="id_sabor">
                                            <option value="">Ninguno...</option>
                                            <?php foreach ($sabores as $sabor): ?>
                                                <option value="<?php echo (int) $sabor['id']; ?>"><?php echo e((string) $sabor['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4" id="editMarcaContainer">
                                        <label class="form-label small text-muted fw-semibold mb-1">Marca <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="editMarca" name="id_marca">
                                            <option value="">Ninguna...</option>
                                            <?php foreach ($marcas as $marca): ?>
                                                <option value="<?php echo (int) ($marca['id'] ?? 0); ?>"><?php echo e((string) $marca['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12" id="editPresentacionContainer">
                                        <label class="form-label small text-muted fw-semibold mb-1">Presentación / Envase <span class="text-danger">*</span></label>
                                        <select class="form-select shadow-none border-secondary-subtle" id="editPresentacion" name="id_presentacion">
                                            <option value="">Ninguna...</option>
                                            <?php foreach ($presentaciones as $presentacion): ?>
                                                <option value="<?php echo (int) $presentacion['id']; ?>"><?php echo e((string) $presentacion['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="editPrecio" name="precio_venta" value="0.0000">
                    <input type="hidden" id="editMoneda" name="moneda" value="PEN">

                    <div class="col-12 px-0" id="editComercialCard">
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
                                            <input class="form-control shadow-none border-secondary-subtle" id="editCosto" name="costo_referencial" type="number" step="0.0001">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted fw-semibold mb-1">Impuesto (%)</label>
                                        <input class="form-control form-control-sm shadow-none border-secondary-subtle" id="editImpuesto" name="impuesto" type="number" step="0.0001">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted fw-semibold mb-1">Peso (kg)</label>
                                        <input class="form-control form-control-sm shadow-none border-secondary-subtle" id="editPesoKg" name="peso_kg" type="number" step="0.001" min="0" placeholder="0.000">
                                    </div>

                                    <div class="col-12">
                                        <input class="form-control form-control-sm shadow-none bg-pastel-light border-secondary-subtle" id="editDescripcion" name="descripcion" placeholder="Añadir descripción o especificaciones adicionales (Opcional)">
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
                                <div class="row g-4">
                                    
                                    <div class="col-md-6">
                                        <div class="p-3 border border-secondary-subtle rounded-3 bg-pastel-light h-100">
                                            <small class="text-uppercase text-secondary fw-bold d-block mb-3" style="font-size: 0.7rem; letter-spacing: 0.5px;">Inventario y Medidas</small>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" id="editControlaStock" name="controla_stock" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="editControlaStock">Controlar Stock Mínimo</label>
                                                    </div>
                                                    <div class="text-muted small" style="padding-left: 2.5em; font-size: 0.75rem;">Activar alertas</div>
                                                </div>
                                                <div id="editStockMinimoContainer" style="width: 80px;">
                                                    <input class="form-control form-control-sm text-end shadow-none border-secondary-subtle" id="editStockMinimo" name="stock_minimo" type="number" step="0.0001" disabled placeholder="Mín.">
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center" id="editPermiteDecimalesContainer">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="editPermiteDecimales" name="permite_decimales" value="1">
                                                    <label class="form-check-label fw-medium text-dark small" for="editPermiteDecimales">Permitir Decimales</label>
                                                </div>
                                                <i class="bi bi-info-circle-fill text-secondary opacity-50 ms-2" data-bs-toggle="tooltip" title="Para ventas a granel (ej. 1.5 Kg)."></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="p-3 border border-secondary-subtle rounded-3 bg-pastel-light h-100">
                                            <small class="text-uppercase text-secondary fw-bold d-block mb-3" style="font-size: 0.7rem; letter-spacing: 0.5px;">Trazabilidad y Calidad</small>
                                            
                                            <div class="d-flex align-items-center mb-3" id="editRequiereLoteContainer">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" id="editRequiereLote" name="requiere_lote" value="1">
                                                    <label class="form-check-label fw-medium text-dark small" for="editRequiereLote">Exigir Lote de Producción</label>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center" id="editRequiereVencimientoContainer">
                                                <div>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" id="editRequiereVencimiento" name="requiere_vencimiento" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="editRequiereVencimiento">Requiere Vencimiento</label>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2" id="editDiasAlertaContainer">
                                                    <span class="small text-muted text-nowrap" style="font-size: 0.75rem;">Alerta (días):</span>
                                                    <input type="number" min="0" class="form-control form-control-sm text-center shadow-none border-secondary-subtle" id="editDiasAlerta" name="dias_alerta_vencimiento" style="width: 60px;" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="p-3 border border-secondary-subtle rounded-3 bg-pastel-light">
                                            <small class="text-uppercase text-secondary fw-bold d-block mb-3" style="font-size: 0.7rem; letter-spacing: 0.5px;">Operaciones de Producción</small>
                                            
                                            <div class="row g-2" id="editOperacionesProduccionContainer">
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="editRequiereFormulaBomContainer">
                                                        <input class="form-check-input" type="checkbox" id="editRequiereFormulaBom" name="requiere_formula_bom" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="editRequiereFormulaBom">Fórmula (BOM)</label>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="editRequiereFactorConversionContainer">
                                                        <input class="form-check-input" type="checkbox" id="editRequiereFactorConversion" name="requiere_factor_conversion" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="editRequiereFactorConversion">Conversión Unidad</label>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="form-check form-switch mb-0" id="editEsEnvaseRetornableContainer">
                                                        <input class="form-check-input" type="checkbox" id="editEsEnvaseRetornable" name="es_envase_retornable" value="1">
                                                        <label class="form-check-label fw-medium text-dark small" for="editEsEnvaseRetornable">Envase Retornable</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4 pt-3 pb-0 px-0 d-flex justify-content-end border-top">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold shadow-sm" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
