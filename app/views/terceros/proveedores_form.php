<?php $p = $prefix ?? 'crear'; ?>
<div class="row g-2">
    <div class="col-md-6">
        <div class="form-floating">
            <select class="form-select" name="proveedor_condicion_pago" id="<?php echo $p; ?>ProvCondicion">
                <option value="">Seleccione...</option>
                <option value="CONTADO">Contado</option>
                <option value="CREDITO">Crédito</option>
                <option value="CONSIGNACION">Consignación</option>
            </select>
            <label for="<?php echo $p; ?>ProvCondicion">Condición Pago</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-floating">
            <input type="number" class="form-control" name="proveedor_dias_credito" id="<?php echo $p; ?>ProvDiasCredito" min="0" placeholder="0">
            <label for="<?php echo $p; ?>ProvDiasCredito">Días Crédito</label>
        </div>
    </div>

    <div class="col-12">
        <div class="form-floating">
            <select class="form-select" name="proveedor_forma_pago" id="<?php echo $p; ?>ProvFormaPago">
                <option value="">Seleccione...</option>
                <option value="TRANSFERENCIA">Transferencia Bancaria</option>
                <option value="CHEQUE">Cheque</option>
                <option value="EFECTIVO">Efectivo</option>
                <option value="YAPE/PLIN">Billetera Digital (Yape/Plin)</option>
            </select>
            <label for="<?php echo $p; ?>ProvFormaPago">Forma de pago habitual</label>
        </div>
    </div>
</div>