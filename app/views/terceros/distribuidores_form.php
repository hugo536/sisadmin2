<?php $p = $prefix ?? 'crear'; ?>

<div id="<?php echo $p; ?>DistribuidorFields">
    <div class="alert alert-light border shadow-sm mb-3">
        <i class="bi bi-geo-alt-fill text-danger me-2"></i>
        <strong>Zonas Exclusivas:</strong> Defina las ubicaciones geográficas donde este distribuidor tiene exclusividad operativa.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body bg-light-subtle">
            <div class="row g-2 mb-3">
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="<?php echo $p; ?>ZonaDepartamento">
                            <option value="">Seleccionar...</option>
                        </select>
                        <label for="<?php echo $p; ?>ZonaDepartamento">Departamento</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="<?php echo $p; ?>ZonaProvincia" disabled>
                            <option value="">Seleccionar...</option>
                        </select>
                        <label for="<?php echo $p; ?>ZonaProvincia">Provincia</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="<?php echo $p; ?>ZonaDistrito" disabled>
                            <option value="">Seleccionar...</option>
                        </select>
                        <label for="<?php echo $p; ?>ZonaDistrito">Distrito (Opcional)</label>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-outline-primary" id="<?php echo $p; ?>AgregarZonaBtn">
                    <i class="bi bi-plus-lg me-2"></i>Agregar Zona
                </button>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro">
                    <thead>
                        <tr>
                            <th class="ps-3">Zona Geográfica</th>
                            <th class="col-w-120">Estado</th>
                            <th class="text-end pe-3 col-w-80">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="<?php echo $p; ?>ZonasList">
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3 small">
                                No hay zonas asignadas. Seleccione ubicación arriba y pulse "Agregar".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>