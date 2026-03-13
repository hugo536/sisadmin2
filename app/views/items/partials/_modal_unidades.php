<div class="modal fade" id="modalUnidadesConversion" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white pb-3 border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Unidades y Conversiones</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-white p-3 p-md-4">
                <div class="alert alert-warning py-2 small mb-4 border-0 shadow-sm rounded-3" id="ucPendientesAlert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Los ítems con <strong>Factor Conversión</strong> activo deben tener al menos una unidad declarada.
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="border border-light-subtle rounded-3 overflow-hidden shadow-sm h-100">
                            <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaUnidadesConversion">
                                <thead>
                                    <tr>
                                        <th class="ps-3 py-2 w-50">Ítem</th> 
                                        <th class="py-2 text-center">Base</th>
                                        <th class="text-center py-2" title="Unidades Declaradas">Cant.</th>
                                        <th class="text-center py-2">Estado</th>
                                        <th class="text-end pe-3 py-2"></th> 
                                    </tr>
                                </thead>
                                <tbody>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="col-lg-7">
                        <div class="border border-light-subtle rounded-3 p-3 p-md-4 bg-pastel-light h-100 shadow-sm" style="background-color: #f8f9fa;">
                            
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 border-bottom pb-3">
                                <h6 class="mb-0 fw-bold text-dark" id="ucTituloSeleccion">Selecciona un ítem para gestionar sus conversiones</h6>
                                <button type="button" class="btn btn-sm btn-primary shadow-sm fw-medium" id="btnAgregarUnidadConversion" disabled>
                                    <i class="bi bi-plus-circle me-1"></i>Agregar Unidad
                                </button>
                            </div>

                            <form id="formUnidadConversion" class="row g-2 d-none mb-4 p-3 bg-white border border-light-subtle rounded-3 shadow-sm" novalidate>
                                <input type="hidden" id="ucAccion" value="crear_item_unidad_conversion">
                                <input type="hidden" id="ucId" value="0">
                                <input type="hidden" id="ucIdItem" value="0">
                                
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-semibold mb-1">Nombre de la Unidad</label>
                                    <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle" id="ucNombre" placeholder="Ej: Caja x 12" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-semibold mb-1">Código / SKU Empaque</label>
                                    <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle" id="ucCodigoUnidad" placeholder="Opcional">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-semibold mb-1">Factor Conversión</label>
                                    <input type="number" class="form-control form-control-sm shadow-none border-secondary-subtle" id="ucFactorConversion" step="0.0001" min="0.0001" placeholder="0.0000" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-semibold mb-1">Peso Bruto (KG)</label>
                                    <input type="number" class="form-control form-control-sm shadow-none border-secondary-subtle" id="ucPesoKg" step="0.001" min="0" placeholder="0.000">
                                </div>
                                
                                <div class="col-md-4 d-flex align-items-end pb-1">
                                    <div class="form-check form-switch mb-0 bg-light border rounded px-3 py-1 w-100 d-flex align-items-center">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" id="ucEstado" checked>
                                        <label class="form-check-label small fw-medium" for="ucEstado">Activo</label>
                                    </div>
                                </div>

                                <div class="col-12 mt-2">
                                    <div class="small fw-bold text-primary bg-primary-subtle px-3 py-2 rounded-2" id="ucResumenFormula">1 Unidad = 0.0000 UND</div>
                                </div>
                                
                                <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                                    <button type="button" class="btn btn-light border text-secondary fw-semibold shadow-none" id="btnCancelarUnidadConversion">Cancelar</button>
                                    <button type="submit" class="btn btn-success fw-bold shadow-sm" id="btnGuardarUnidadConversion"><i class="bi bi-save me-2"></i>Guardar</button>
                                </div>
                            </form>

                            <div class="border border-light-subtle rounded-3 overflow-hidden bg-white shadow-sm">
                                <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleUnidadesConversion">
                                    <thead>
                                        <tr>
                                            <th class="ps-3 py-2">Nombre</th>
                                            <th class="py-2">Código</th>
                                            <th class="text-end py-2">Factor</th>
                                            <th class="text-end py-2" title="Peso en KG">Peso</th>
                                            <th class="text-center py-2">Est.</th>
                                            <th class="text-end pe-3 py-2"></th> 
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