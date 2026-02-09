<?php
$prefix = $prefix ?? 'crear';
?>
<div class="border rounded-3 p-3 bg-light mt-3 d-none" id="<?php echo $prefix; ?>DistribuidorFields">
    <h6 class="small text-muted fw-bold mb-2">DISTRIBUIDOR</h6>
    <div class="row g-2">
        <div class="col-12">
            <div class="form-floating">
                <textarea class="form-control" name="distribuidor_zona_exclusiva" id="<?php echo $prefix; ?>DistribuidorZona" style="height: 80px;" placeholder="Zona exclusiva"></textarea>
                <label for="<?php echo $prefix; ?>DistribuidorZona">Zona exclusiva</label>
            </div>
        </div>
        <div class="col-12">
            <div class="form-floating">
                <input type="number" step="0.01" class="form-control" name="distribuidor_meta_volumen" id="<?php echo $prefix; ?>DistribuidorMeta" min="0" placeholder="0.00">
                <label for="<?php echo $prefix; ?>DistribuidorMeta">Meta de volumen</label>
            </div>
        </div>
    </div>
</div>
