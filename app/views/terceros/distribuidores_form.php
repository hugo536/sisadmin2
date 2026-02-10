<?php
$prefix = $prefix ?? 'crear';
?>
<h6 class="small text-muted fw-bold mb-2">DISTRIBUIDOR</h6>
<div class="mt-2">
    <div class="form-check form-switch p-2 border rounded bg-white d-flex align-items-center">
        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="<?php echo $prefix; ?>EsDistribuidor" name="es_distribuidor" value="1" style="margin-top: 0;">
        <label class="form-check-label small lh-1" for="<?php echo $prefix; ?>EsDistribuidor">Es Distribuidor Autorizado</label>
    </div>
</div>

<div class="border rounded-3 p-3 bg-light mt-3 d-none" id="<?php echo $prefix; ?>DistribuidorFields">
    <h6 class="small text-muted fw-bold mb-3">ZONAS EXCLUSIVAS</h6>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Departamento</label>
            <select class="form-select" id="<?php echo $prefix; ?>ZonaDepartamento">
                <option value="">Seleccionar...</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Provincia</label>
            <select class="form-select" id="<?php echo $prefix; ?>ZonaProvincia" disabled>
                <option value="">Seleccionar...</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Distrito</label>
            <select class="form-select" id="<?php echo $prefix; ?>ZonaDistrito" disabled>
                <option value="">Seleccionar...</option>
            </select>
        </div>
    </div>
    <div class="mt-3">
        <button type="button" class="btn btn-sm btn-outline-primary" id="<?php echo $prefix; ?>AgregarZonaBtn">
            <i class="bi bi-plus-circle me-1"></i>Agregar zona
        </button>
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Zona</th>
                    <th class="text-end" style="width:90px;">Acción</th>
                </tr>
            </thead>
            <tbody id="<?php echo $prefix; ?>ZonasList"></tbody>
        </table>
    </div>
</div>
