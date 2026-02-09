<?php
$prefix = $prefix ?? 'crear';
?>
<h6 class="small text-muted fw-bold mb-2">PROVEEDOR</h6>
<div class="row g-2">
    <div class="col-12">
        <div class="form-floating">
            <select class="form-select" name="proveedor_condicion_pago" id="<?php echo $prefix; ?>ProvCondicion">
                <option value="">Seleccionar...</option>
                <option value="contado">Contado</option>
                <option value="credito">Crédito</option>
            </select>
            <label for="<?php echo $prefix; ?>ProvCondicion">Condición Pago</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-floating">
            <input type="number" class="form-control" name="proveedor_dias_credito" id="<?php echo $prefix; ?>ProvDiasCredito" min="0" placeholder="0">
            <label for="<?php echo $prefix; ?>ProvDiasCredito">Días Crédito (Proveedor)</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-floating">
            <input type="text" class="form-control" name="proveedor_forma_pago" id="<?php echo $prefix; ?>ProvFormaPago" placeholder="Forma de pago">
            <label for="<?php echo $prefix; ?>ProvFormaPago">Forma de pago</label>
        </div>
    </div>
</div>
