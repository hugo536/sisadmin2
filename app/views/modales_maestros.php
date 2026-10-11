<!-- MODAL: GESTIÓN DE CARGOS -->
<div class="modal fade" id="modalGestionCargos" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-white shadow-lg border-0 rounded-3">
            
            <!-- Header limpio y fijo -->
            <div class="modal-header bg-white border-bottom pb-3 pt-3">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center mb-0">
                    <i class="bi bi-briefcase-fill text-primary me-2"></i> Administrar Cargos
                </h6>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <!-- Body con scroll nativo -->
            <div class="modal-body p-3 p-md-4 bg-light">
                
                <!-- Formulario de creación -->
                <div class="card border-0 shadow-sm mb-4 rounded-3">
                    <div class="card-body p-3">
                        <form id="formCrearCargo" class="mb-0">
                            <input type="hidden" name="accion" value="guardar_cargo" id="cargoAccion">
                            <input type="hidden" name="id" id="cargoId">
                            
                            <label class="form-label small fw-bold text-muted mb-2">Crear o Editar Cargo</label>
                            <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                                <input type="text" class="form-control border-0 bg-light-subtle fs-6" name="nombre" id="cargoNombre" placeholder="Ej. Administrador..." required autocomplete="off">
                                <button class="btn btn-primary px-3 px-md-4" type="submit" id="btnSaveCargo">
                                    <i class="bi bi-plus-lg fw-bold"></i>
                                </button>
                                <button class="btn btn-danger px-3 d-none" type="button" id="btnCancelCargo" title="Cancelar edición">
                                    <i class="bi bi-x-lg fw-bold"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Listado -->
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <h6 class="small text-muted fw-bold text-uppercase mb-0" style="letter-spacing: 0.5px;">Listado de Cargos</h6>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="contadorCargos">0</span>
                </div>
                
                <!-- Aquí ya NO usamos max-height inline. El modal-dialog-scrollable hace la magia -->
                <div class="list-group list-group-flush border border-secondary-subtle rounded-3 shadow-sm bg-white overflow-hidden" id="listaCargosConfig">
                    <div class="text-center py-4 text-muted small">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div class="fw-medium">Cargando cargos...</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL: GESTIÓN DE ÁREAS -->
<div class="modal fade" id="modalGestionAreas" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-white shadow-lg border-0 rounded-3">
            
            <div class="modal-header bg-white border-bottom pb-3 pt-3">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center mb-0">
                    <i class="bi bi-diagram-3-fill text-primary me-2"></i> Administrar Áreas
                </h6>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body p-3 p-md-4 bg-light">
                
                <div class="card border-0 shadow-sm mb-4 rounded-3">
                    <div class="card-body p-3">
                        <form id="formCrearArea" class="mb-0">
                            <input type="hidden" name="accion" value="guardar_area" id="areaAccion">
                            <input type="hidden" name="id" id="areaId">
                            
                            <label class="form-label small fw-bold text-muted mb-2">Crear o Editar Área</label>
                            <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                                <input type="text" class="form-control border-0 bg-light-subtle fs-6" name="nombre" id="areaNombre" placeholder="Ej. Recursos Humanos..." required autocomplete="off">
                                <button class="btn btn-primary px-3 px-md-4" type="submit" id="btnSaveArea">
                                    <i class="bi bi-plus-lg fw-bold"></i>
                                </button>
                                <button class="btn btn-danger px-3 d-none" type="button" id="btnCancelArea" title="Cancelar edición">
                                    <i class="bi bi-x-lg fw-bold"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <h6 class="small text-muted fw-bold text-uppercase mb-0" style="letter-spacing: 0.5px;">Listado de Áreas</h6>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="contadorAreas">0</span>
                </div>
                
                <div class="list-group list-group-flush border border-secondary-subtle rounded-3 shadow-sm bg-white overflow-hidden" id="listaAreasConfig">
                    <div class="text-center py-4 text-muted small">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div class="fw-medium">Cargando áreas...</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>