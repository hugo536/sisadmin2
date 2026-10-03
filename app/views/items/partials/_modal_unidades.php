<div class="modal fade" id="modalUnidadesConversion" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            
            <!-- Header del Modal -->
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-arrow-left-right me-2"></i>Unidades y Conversiones
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <!-- Body del Modal -->
            <div class="modal-body bg-light p-3 p-md-4">
                
                <!-- Alerta Global -->
                <div class="alert alert-warning py-2 px-3 small mb-3 border-0 shadow-sm rounded-3 d-none d-flex align-items-center" id="ucPendientesAlert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-3 text-warning"></i>
                    <span>Los ítems con <strong>Factor de Conversión</strong> activo deben tener al menos una unidad declarada.</span>
                </div>
                
                <div class="row g-3 h-100">
                    
                    <!-- PANEL IZQUIERDO: Buscador y Lista de Ítems (Ajustado a col-lg-4 para dar más espacio al panel principal) -->
                    <div class="col-lg-4 d-flex flex-column" style="max-height: 70vh;">
                        <div class="card shadow-sm border-0 d-flex flex-column h-100 overflow-hidden">
                            
                            <!-- Buscador Fijo -->
                            <div class="p-3 border-bottom bg-white" style="z-index: 5;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" id="ucBuscarItem" placeholder="Buscar por nombre o SKU..." autocomplete="off">
                                </div>
                            </div>
                            
                            <!-- Tabla Scrollable -->
                            <div class="table-responsive flex-grow-1 bg-white" style="overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaUnidadesConversion">
                                    <thead class="bg-white position-sticky top-0 shadow-sm" style="z-index: 10;">
                                        <tr>
                                            <!-- Sin anchos fijos restrictivos, ocultando datos menos críticos en pantallas pequeñas -->
                                            <th class="ps-3 py-2 text-secondary fw-semibold">Ítem / SKU</th> 
                                            <th class="text-center py-2 text-secondary fw-semibold d-none d-xl-table-cell">Base</th>
                                            <th class="text-center py-2 text-secondary fw-semibold" title="Unidades Declaradas">Cant.</th>
                                            <th class="text-center py-2 text-secondary fw-semibold d-none d-md-table-cell">Estado</th>
                                            <th class="pe-3 py-2"></th> 
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Renderizado dinámico de filas aquí -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PANEL DERECHO: Gestión de Unidades (Ajustado a col-lg-8) -->
                    <div class="col-lg-8 d-flex flex-column" style="max-height: 70vh;">
                        <div class="card shadow-sm border-0 h-100 d-flex flex-column overflow-hidden p-3 p-md-4">
                            
                            <!-- Encabezado de Selección -->
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3 border-bottom pb-3">
                                <div class="bg-light px-3 py-2 rounded border border-secondary-subtle flex-grow-1">
                                    <!-- Eliminado el text-truncate y max-width, sustituido por text-wrap para que todo el nombre sea visible -->
                                    <h6 class="mb-0 fw-bold text-dark text-wrap lh-base" id="ucTituloSeleccion">
                                        Selecciona un ítem de la lista
                                    </h6>
                                </div>
                                <button type="button" class="btn btn-primary shadow-sm fw-medium text-nowrap" id="btnAgregarUnidadConversion" disabled>
                                    <i class="bi bi-plus-circle me-1"></i>Agregar Unidad
                                </button>
                            </div>

                            <!-- FORMULARIO DE INSERCIÓN/EDICIÓN -->
                            <div id="contenedorFormulario" class="d-none mb-4">
                                <div class="card bg-light border-secondary-subtle shadow-sm">
                                    <form id="formUnidadConversion" class="card-body row g-3" novalidate>
                                        <input type="hidden" id="ucAccion" value="crear_item_unidad_conversion">
                                        <input type="hidden" id="ucId" value="0">
                                        <input type="hidden" id="ucIdItem" value="0">
                                        
                                        <div class="col-md-6 form-floating">
                                            <input type="text" class="form-control border-secondary-subtle shadow-none" id="ucNombre" placeholder="Nombre" required>
                                            <label class="ps-3">Nombre de Unidad (Ej: Caja x 12) <span class="text-danger">*</span></label>
                                        </div>
                                        
                                        <div class="col-md-6 form-floating">
                                            <input type="text" class="form-control border-secondary-subtle bg-white shadow-none" id="ucCodigoUnidad" placeholder="Código" readonly>
                                            <label class="ps-3 text-muted">Código SKU (Auto-generado)</label>
                                        </div>
                                        
                                        <div class="col-md-4 form-floating">
                                            <input type="number" class="form-control border-secondary-subtle shadow-none" id="ucFactorConversion" step="0.0001" min="0.0001" placeholder="0.0000" required>
                                            <label class="ps-3">Factor Conversión <span class="text-danger">*</span></label>
                                        </div>
                                        
                                        <div class="col-md-4 form-floating">
                                            <input type="number" class="form-control border-secondary-subtle shadow-none" id="ucPesoKg" step="0.001" min="0" placeholder="0.000">
                                            <label class="ps-3">Peso Bruto (KG)</label>
                                        </div>
                                        
                                        <div class="col-md-4 d-flex align-items-center">
                                            <div class="form-check form-switch bg-white border border-secondary-subtle rounded px-3 w-100 d-flex align-items-center justify-content-between h-100" style="min-height: 58px;">
                                                <label class="form-check-label small fw-medium text-dark m-0" for="ucEstado">Activo</label>
                                                <input class="form-check-input m-0 fs-5" type="checkbox" id="ucEstado" checked>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="small fw-bold text-primary bg-primary-subtle border border-primary-subtle px-3 py-2 rounded text-center" id="ucResumenFormula">
                                                1 Unidad = 0.0000 UND
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 d-flex justify-content-end gap-2 mt-2 pt-3 border-top border-secondary-subtle">
                                            <button type="button" class="btn btn-outline-secondary fw-medium shadow-none" id="btnCancelarUnidadConversion">Cancelar</button>
                                            <button type="submit" class="btn btn-primary fw-semibold shadow-sm" id="btnGuardarUnidadConversion">
                                                <i class="bi bi-save me-2"></i>Guardar Unidad
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tabla Detalle de Unidades Scrollable -->
                            <div class="table-responsive flex-grow-1 border rounded" style="overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaDetalleUnidadesConversion">
                                    <thead class="bg-light position-sticky top-0 shadow-sm" style="z-index: 10;">
                                        <tr>
                                            <!-- Sin anchos fijos restrictivos, ocultando columnas en móviles -->
                                            <th class="ps-3 py-2 text-secondary fw-semibold">Nombre</th>
                                            <th class="text-end py-2 text-secondary fw-semibold">Factor</th>
                                            <th class="text-end py-2 text-secondary fw-semibold d-none d-sm-table-cell" title="Peso en KG">Peso</th>
                                            <th class="text-center py-2 text-secondary fw-semibold" title="Predeterminada para Inventario">Pref.</th>
                                            <th class="text-center py-2 text-secondary fw-semibold d-none d-md-table-cell">Est.</th>
                                            <th class="pe-3 py-2"></th> 
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                                Selecciona un ítem para ver sus unidades de conversión.
                                            </td>
                                        </tr>
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