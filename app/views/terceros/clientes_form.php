<?php $p = $prefix ?? 'crear'; ?>
<div class="row g-2">
    <div class="col-md-6">
        <div class="form-floating">
            <input type="number" class="form-control" name="cliente_dias_credito" id="<?php echo $p; ?>ClienteDiasCredito" min="0" placeholder="0">
            <label for="<?php echo $p; ?>ClienteDiasCredito">Días Crédito</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-floating">
            <input type="number" step="0.01" class="form-control" name="cliente_limite_credito" id="<?php echo $p; ?>ClienteLimiteCredito" min="0" placeholder="0.00">
            <label for="<?php echo $p; ?>ClienteLimiteCredito">Límite Crédito (S/)</label>
        </div>
    </div>
    
    <div class="col-12">
        <div class="form-floating">
            <select class="form-select" name="cliente_condicion_pago" id="<?php echo $p; ?>ClienteCondicionPago">
                <option value="">Seleccione...</option>
                <option value="CONTADO">Contado</option>
                <option value="CREDITO">Crédito</option>
                <option value="CONSIGNACION">Consignación</option>
                <option value="ANTICIPADO">Anticipado</option>
            </select>
            <label for="<?php echo $p; ?>ClienteCondicionPago">Condición de pago</label>
        </div>
    </div>
</div>