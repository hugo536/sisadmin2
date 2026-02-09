<?php
$prefix = $prefix ?? 'crear';
?>
<h6 class="small text-muted fw-bold mb-2">CLIENTE</h6>
<div class="row g-2">
    <div class="col-12">
        <div class="form-floating">
            <input type="number" class="form-control" name="cliente_dias_credito" id="<?php echo $prefix; ?>ClienteDiasCredito" min="0" placeholder="0">
            <label for="<?php echo $prefix; ?>ClienteDiasCredito">Días Crédito (Cliente)</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-floating">
            <input type="number" step="0.01" class="form-control" name="cliente_limite_credito" id="<?php echo $prefix; ?>ClienteLimiteCredito" min="0" placeholder="0.00">
            <label for="<?php echo $prefix; ?>ClienteLimiteCredito">Límite Crédito (S/)</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-floating">
            <input type="text" class="form-control" name="cliente_condicion_pago" id="<?php echo $prefix; ?>ClienteCondicionPago" placeholder="Condición de pago">
            <label for="<?php echo $prefix; ?>ClienteCondicionPago">Condición de pago</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-floating">
            <input type="text" class="form-control" name="cliente_ruta_reparto" id="<?php echo $prefix; ?>ClienteRutaReparto" placeholder="Ruta de reparto">
            <label for="<?php echo $prefix; ?>ClienteRutaReparto">Ruta de reparto</label>
        </div>
    </div>
</div>

<div class="mt-3">
    <div class="form-check form-switch p-2 border rounded bg-white d-flex align-items-center">
        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="<?php echo $prefix; ?>EsDistribuidor" name="es_distribuidor" value="1" style="margin-top: 0;">
        <label class="form-check-label small lh-1" for="<?php echo $prefix; ?>EsDistribuidor">Es distribuidor</label>
    </div>
</div>

<?php require __DIR__ . '/distribuidores_form.php'; ?>
