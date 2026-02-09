<?php
$prefix = $prefix ?? 'crear';
$cargos_list = $cargos_list ?? [];
$areas_list = $areas_list ?? [];
?>
<h6 class="fw-bold mb-3 text-success">Datos Laborales</h6>
<div class="row g-3">
    <div class="col-md-4">
        <div class="form-floating">
            <select class="form-select" name="cargo" id="<?php echo $prefix; ?>Cargo">
                <option value="" disabled selected>Seleccione Cargo...</option>
                <?php if(empty($cargos_list)): ?>
                    <option value="" disabled>-- No hay cargos registrados --</option>
                <?php else: ?>
                    <?php foreach($cargos_list as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['nombre']); ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <label for="<?php echo $prefix; ?>Cargo">Cargo <span class="text-danger">*</span></label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <select class="form-select" name="area" id="<?php echo $prefix; ?>Area">
                <option value="" disabled selected>Seleccione Área...</option>
                <?php if(empty($areas_list)): ?>
                    <option value="" disabled>-- No hay áreas registradas --</option>
                <?php else: ?>
                    <?php foreach($areas_list as $a): ?>
                        <option value="<?php echo htmlspecialchars($a['nombre']); ?>"><?php echo htmlspecialchars($a['nombre']); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <label for="<?php echo $prefix; ?>Area">Área <span class="text-danger">*</span></label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <select class="form-select" name="tipo_contrato" id="<?php echo $prefix; ?>TipoContrato">
                <option value="">Seleccionar...</option>
                <option value="INDETERMINADO">Indeterminado</option>
                <option value="PLAZO_FIJO">Plazo Fijo</option>
                <option value="PART_TIME">Part-Time</option>
                <option value="LOCACION">Locación de Servicios</option>
                <option value="PRACTICANTE">Practicante</option>
            </select>
            <label for="<?php echo $prefix; ?>TipoContrato">Tipo de Contrato</label>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-floating">
            <input type="date" class="form-control" name="fecha_ingreso" id="<?php echo $prefix; ?>FechaIngreso">
            <label for="<?php echo $prefix; ?>FechaIngreso">Fecha de Ingreso</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <input type="date" class="form-control" name="fecha_cese" id="<?php echo $prefix; ?>FechaCese">
            <label for="<?php echo $prefix; ?>FechaCese">Fecha de Cese</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <select class="form-select" name="estado_laboral" id="<?php echo $prefix; ?>EstadoLaboral">
                <option value="activo">Activo</option>
                <option value="cesado">Cesado</option>
                <option value="suspendido">Suspendido</option>
            </select>
            <label for="<?php echo $prefix; ?>EstadoLaboral">Estado Laboral</label>
        </div>
    </div>

    <div class="col-md-3">
        <div class="form-floating">
            <select class="form-select" name="moneda" id="<?php echo $prefix; ?>Moneda">
                <option value="PEN">S/ (Soles)</option>
                <option value="USD">$ (Dólares)</option>
            </select>
            <label for="<?php echo $prefix; ?>Moneda">Moneda</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <input type="number" step="0.01" class="form-control" name="sueldo_basico" id="<?php echo $prefix; ?>SueldoBasico" placeholder="0.00">
            <label for="<?php echo $prefix; ?>SueldoBasico">Sueldo Básico</label>
        </div>
    </div>
    <div class="col-md-5">
        <div class="form-check form-switch p-2 border rounded bg-white h-100 d-flex align-items-center">
            <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="<?php echo $prefix; ?>AsignacionFamiliar" name="asignacion_familiar" value="1" style="margin-top: 0;">
            <label class="form-check-label small lh-1" for="<?php echo $prefix; ?>AsignacionFamiliar">Asignación Familiar (Hijos)</label>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-floating">
            <select class="form-select" name="tipo_pago" id="<?php echo $prefix; ?>TipoPago">
                <option value="">Seleccionar...</option>
                <option value="MENSUAL">Mensual</option>
                <option value="QUINCENAL">Quincenal</option>
                <option value="DIARIO">Diario</option>
            </select>
            <label for="<?php echo $prefix; ?>TipoPago">Frecuencia Pago</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-floating">
            <input type="number" step="0.01" class="form-control" name="pago_diario" id="<?php echo $prefix; ?>PagoDiario" placeholder="0.00">
            <label for="<?php echo $prefix; ?>PagoDiario">Pago Diario (si aplica)</label>
        </div>
    </div>

    <div class="col-md-4">
        <div class="form-floating">
            <select class="form-select" name="regimen_pensionario" id="<?php echo $prefix; ?>Regimen">
                <option value="">Ninguno</option>
                <option value="ONP">ONP</option>
                <option value="AFP_INTEGRA">AFP Integra</option>
                <option value="AFP_PRIMA">AFP Prima</option>
                <option value="AFP_PROFUTURO">AFP Profuturo</option>
                <option value="AFP_HABITAT">AFP Habitat</option>
            </select>
            <label for="<?php echo $prefix; ?>Regimen">Régimen Pensionario</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <select class="form-select" name="tipo_comision_afp" id="<?php echo $prefix; ?>TipoComision">
                <option value="">No Aplica</option>
                <option value="FLUJO">Comisión sobre Flujo</option>
                <option value="MIXTA">Comisión Mixta/Saldo</option>
            </select>
            <label for="<?php echo $prefix; ?>TipoComision">Tipo Comisión AFP</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <input type="text" class="form-control" name="cuspp" id="<?php echo $prefix; ?>Cuspp" placeholder="CUSPP">
            <label for="<?php echo $prefix; ?>Cuspp">CUSPP (Código AFP)</label>
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-check form-switch p-2 border rounded bg-white h-100 d-flex align-items-center">
            <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="<?php echo $prefix; ?>Essalud" name="essalud" value="1" style="margin-top: 0;">
            <label class="form-check-label small" for="<?php echo $prefix; ?>Essalud">Aportante EsSalud (9%)</label>
        </div>
    </div>
</div>
