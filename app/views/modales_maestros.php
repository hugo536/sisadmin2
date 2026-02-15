<div class="modal fade" id="modalGestionCargos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0">Administrar Cargos</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCrearCargo" class="mb-3">
                    <input type="hidden" name="accion" value="guardar_cargo" id="cargoAccion">
                    <input type="hidden" name="id" id="cargoId">
                    <div class="input-group">
                        <input type="text" class="form-control" name="nombre" id="cargoNombre" placeholder="Nuevo Cargo" required>
                        <button class="btn btn-primary" type="submit" id="btnSaveCargo">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button class="btn btn-outline-secondary d-none" type="button" id="btnCancelCargo">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </form>
                
                <h6 class="small text-muted fw-bold">Listado de cargos</h6>
                <div class="list-group list-group-flush border rounded" id="listaCargosConfig" style="max-height: 300px; overflow-y: auto;">
                    <div class="text-center py-3 text-muted small"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionAreas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0">Administrar Áreas</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCrearArea" class="mb-3">
                    <input type="hidden" name="accion" value="guardar_area" id="areaAccion">
                    <input type="hidden" name="id" id="areaId">
                    <div class="input-group">
                        <input type="text" class="form-control" name="nombre" id="areaNombre" placeholder="Nueva Área" required>
                        <button class="btn btn-primary" type="submit" id="btnSaveArea">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button class="btn btn-outline-secondary d-none" type="button" id="btnCancelArea">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </form>
                
                <h6 class="small text-muted fw-bold">Listado de áreas</h6>
                <div class="list-group list-group-flush border rounded" id="listaAreasConfig" style="max-height: 300px; overflow-y: auto;">
                    <div class="text-center py-3 text-muted small"><span class="spinner-border spinner-border-sm"></span> Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>