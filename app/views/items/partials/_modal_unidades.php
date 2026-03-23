<div class="modal fade" id="modalUnidadesConversion" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Unidades y Conversiones</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body bg-light p-3 p-md-4">
                
                <div class="alert alert-warning py-2 small mb-3 border-0 shadow-sm rounded-3 d-none" id="ucPendientesAlert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Los ítems con <strong>Factor Conversión</strong> activo deben tener al menos una unidad declarada.
                </div>
                
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="modal-pastel-card bg-white overflow-hidden h-100">
                            <div class="p-3 border-bottom bg-light-subtle">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-secondary-subtle border-end-0 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input
                                        type="search"
                                        class="form-control bg-white border-secondary-subtle border-start-0 ps-0 shadow-none"
                                        id="ucBuscarItem"
                                        placeholder="Buscar por nombre o SKU..."
                                        autocomplete="off">
                                </div>
                            </div>
                            <div class="table-responsive h-100">
                                <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaUnidadesConversion">
                                    <thead>
                                        <tr>
                                            <th class="ps-4 py-3 w-50">Ítem</th> 
                                            <th class="text-center py-3">Base</th>
                                            <th class="text-center py-3" title="Unidades Declaradas">Cant.</th>
                                            <th class="text-center py-3">Estado</th>
                                            <th class="text-end pe-4 py-3"></th> 
                                        </tr>
                                    </thead>
                                    <tbody>
                                        </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-7">
                        <div class="modal-pastel-card bg-white p-3 p-md-4 h-100">
                            
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 border-bottom pb-3">
                                <h6 class="mb-0 fw-bold text-dark" id="ucTituloSeleccion">Selecciona un ítem para gestionar sus conversiones</h6>
                                <button type="button" class="btn btn-sm btn-primary shadow-sm fw-medium" id="btnAgregarUnidadConversion" disabled>
                                    <i class="bi bi-plus-circle me-1"></i>Agregar Unidad
                                </button>
                            </div>

                            <form id="formUnidadConversion" class="row g-2 d-none mb-4 p-3 bg-pastel-light border border-secondary-subtle rounded-3" novalidate>
                                <input type="hidden" id="ucAccion" value="crear_item_unidad_conversion">
                                <input type="hidden" id="ucId" value="0">
                                <input type="hidden" id="ucIdItem" value="0">
                                
                                <div class="col-md-6 form-floating">
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="ucNombre" placeholder="Nombre" required>
                                    <label>Nombre de la Unidad (Ej: Caja x 12) <span class="text-danger">*</span></label>
                                </div>
                                
                                <div class="col-md-6 form-floating">
                                    <input type="text" class="form-control shadow-none border-secondary-subtle bg-light text-muted" id="ucCodigoUnidad" placeholder="Código" readonly>
                                    <label>Código / SKU Empaque (Opcional)</label>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">El código SKU empaque se genera automáticamente.</small>
                                </div>
                                
                                <div class="col-md-4 form-floating mt-2">
                                    <input type="number" class="form-control shadow-none border-secondary-subtle" id="ucFactorConversion" step="0.0001" min="0.0001" placeholder="0.0000" required>
                                    <label>Factor Conversión <span class="text-danger">*</span></label>
                                </div>
                                
                                <div class="col-md-4 form-floating mt-2">
                                    <input type="number" class="form-control shadow-none border-secondary-subtle" id="ucPesoKg" step="0.001" min="0" placeholder="0.000">
                                    <label>Peso Bruto (KG)</label>
                                </div>
                                
                                <div class="col-md-4 d-flex align-items-center mt-2">
                                    <div class="form-check form-switch mb-0 bg-white border border-secondary-subtle rounded px-3 py-2 w-100 d-flex align-items-center justify-content-between h-100" style="min-height: 58px;">
                                        <label class="form-check-label small fw-medium text-dark m-0" for="ucEstado">Estado Activo</label>
                                        <input class="form-check-input m-0 fs-5" type="checkbox" id="ucEstado" checked>
                                    </div>
                                </div>

                                <div class="col-12 mt-3">
                                    <div class="small fw-bold text-primary bg-primary-subtle border border-primary-subtle px-3 py-2 rounded-2 text-center" id="ucResumenFormula">
                                        1 Unidad = 0.0000 UND
                                    </div>
                                </div>
                                
                                <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                                    <button type="button" class="btn btn-light text-secondary border border-secondary-subtle fw-medium shadow-none" id="btnCancelarUnidadConversion">Cancelar</button>
                                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm" id="btnGuardarUnidadConversion"><i class="bi bi-save me-2"></i>Guardar Unidad</button>
                                </div>
                            </form>

                            <div class="modal-pastel-card overflow-hidden bg-white">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleUnidadesConversion">
                                        <thead>
                                            <tr>
                                                <th class="ps-4 py-3">Nombre</th>
                                                <th class="py-3">Código</th>
                                                <th class="text-end py-3">Factor</th>
                                                <th class="text-end py-3" title="Peso en KG">Peso</th>
                                                <th class="text-center py-3">Est.</th>
                                                <th class="text-end pe-4 py-3"></th> 
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="6" class="text-center text-muted py-5">Selecciona un ítem para ver sus unidades de conversión.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
