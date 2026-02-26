<div class="modal fade" id="modalUnidadesConversion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning-subtle py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Unidades y Conversiones</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small mb-3" id="ucPendientesAlert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Los ítems con <strong>Factor Conversión</strong> activo deben tener al menos una unidad declarada.
                </div>
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm align-middle mb-0" id="tablaUnidadesConversion">
                                <thead>
                                    <tr>
                                        <th>Ítem</th>
                                        <th>Base</th>
                                        <th class="text-center">Declaradas</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0" id="ucTituloSeleccion">Selecciona un ítem para gestionar sus conversiones</h6>
                                <button type="button" class="btn btn-sm btn-primary" id="btnAgregarUnidadConversion" disabled>
                                    <i class="bi bi-plus-circle me-1"></i>Agregar Nueva Unidad
                                </button>
                            </div>

                            <form id="formUnidadConversion" class="row g-2 d-none" novalidate>
                                <input type="hidden" id="ucAccion" value="crear_item_unidad_conversion">
                                <input type="hidden" id="ucId" value="0">
                                <input type="hidden" id="ucIdItem" value="0">
                                <div class="col-md-6">
                                    <label class="form-label mb-1">Nombre de la Unidad</label>
                                    <input type="text" class="form-control" id="ucNombre" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1">Código / SKU Empaque</label>
                                    <input type="text" class="form-control" id="ucCodigoUnidad">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1">Factor de Conversión</label>
                                    <input type="number" class="form-control" id="ucFactorConversion" step="0.0001" min="0.0001" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1">Peso Bruto (KG)</label>
                                    <input type="number" class="form-control" id="ucPesoKg" step="0.001" min="0">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="ucEstado" checked>
                                        <label class="form-check-label" for="ucEstado">Activo</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="small text-muted" id="ucResumenFormula">1 Unidad = 0.0000 UND</div>
                                </div>
                                <div class="col-12 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary" id="btnCancelarUnidadConversion">Cancelar</button>
                                    <button type="submit" class="btn btn-success" id="btnGuardarUnidadConversion">Guardar</button>
                                </div>
                            </form>

                            <div class="table-responsive mt-3 border rounded-3 bg-white">
                                <table class="table table-sm align-middle mb-0" id="tablaDetalleUnidadesConversion">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Código</th>
                                            <th class="text-end">Factor</th>
                                            <th class="text-end">Peso KG</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="6" class="text-center text-muted py-4">Selecciona un ítem para ver sus unidades de conversión.</td></tr>
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

