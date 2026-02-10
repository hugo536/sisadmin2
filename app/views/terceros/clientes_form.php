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
</div>
