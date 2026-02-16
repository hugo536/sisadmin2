<?php
$prefix = $prefix ?? 'crear';
$cargos_list = $cargos_list ?? [];
$areas_list = $areas_list ?? [];
$today = date('Y-m-d');
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0 fw-bold text-muted small text-uppercase"><i class="bi bi-person-badge me-1"></i> Perfil Personal</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-check form-switch p-2 border rounded bg-white h-100 d-flex align-items-center empleado-switch-card">
                    <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="<?php echo $prefix; ?>RecordarCumpleanos" name="recordar_cumpleanos" value="1" style="margin-top: 0;">
                    <label class="form-check-label small lh-1 fw-semibold" for="<?php echo $prefix; ?>RecordarCumpleanos">Registrar Cumpleaños</label>
                </div>
            </div>
            <div class="col-md-4" id="<?php echo $prefix; ?>FechaNacimientoWrapper">
                <div class="form-floating">
                    <input type="date" class="form-control" name="fecha_nacimiento" id="<?php echo $prefix; ?>FechaNacimiento" max="<?php echo $today; ?>" disabled>
                    <label for="<?php echo $prefix; ?>FechaNacimiento">Fecha de Nacimiento</label>
                </div>
            </div>
            
            <div class="w-100 d-none d-md-block"></div> 
            
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="genero" id="<?php echo $prefix; ?>Genero">
                        <option value="">Seleccionar...</option>
                        <option value="MASCULINO">Masculino</option>
                        <option value="FEMENINO">Femenino</option>
                        <option value="OTRO">Otro</option>
                    </select>
                    <label for="<?php echo $prefix; ?>Genero">Género</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="estado_civil" id="<?php echo $prefix; ?>EstadoCivil">
                        <option value="">Seleccionar...</option>
                        <option value="SOLTERO">Soltero(a)</option>
                        <option value="CASADO">Casado(a)</option>
                        <option value="DIVORCIADO">Divorciado(a)</option>
                        <option value="VIUDO">Viudo(a)</option>
                        <option value="CONVIVIENTE">Conviviente</option>
                    </select>
                    <label for="<?php echo $prefix; ?>EstadoCivil">Estado Civil</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="nivel_educativo" id="<?php echo $prefix; ?>NivelEducativo">
                        <option value="">Seleccionar...</option>
                        <option value="PRIMARIA">Primaria</option>
                        <option value="SECUNDARIA">Secundaria</option>
                        <option value="TECNICO">Técnico</option>
                        <option value="UNIVERSITARIO">Universitario</option>
                        <option value="POSGRADO">Posgrado</option>
                    </select>
                    <label for="<?php echo $prefix; ?>NivelEducativo">Nivel Educativo</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0 fw-bold text-muted small text-uppercase"><i class="bi bi-briefcase me-1"></i> Contrato y Cargo</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="cargo" id="<?php echo $prefix; ?>Cargo" data-required-empleado="1">
                        <option value="" disabled selected>Seleccione Cargo...</option>
                        <?php foreach ($cargos_list as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['nombre']); ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="<?php echo $prefix; ?>Cargo">Cargo <span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="area" id="<?php echo $prefix; ?>Area" data-required-empleado="1">
                        <option value="" disabled selected>Seleccione Área...</option>
                        <?php foreach ($areas_list as $a): ?>
                            <option value="<?php echo htmlspecialchars($a['nombre']); ?>"><?php echo htmlspecialchars($a['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="<?php echo $prefix; ?>Area">Área <span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="estado_laboral" id="<?php echo $prefix; ?>EstadoLaboral">
                        <option value="activo" selected>Activo</option>
                        <option value="cesado">Cesado</option>
                        <option value="suspendido">Suspendido</option>
                    </select>
                    <label for="<?php echo $prefix; ?>EstadoLaboral">Estado Laboral</label>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="tipo_contrato" id="<?php echo $prefix; ?>TipoContrato">
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
                    <input type="date" class="form-control" name="fecha_ingreso" id="<?php echo $prefix; ?>FechaIngreso" data-required-empleado="1">
                    <label for="<?php echo $prefix; ?>FechaIngreso">Fecha de Ingreso <span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <input type="date" class="form-control" name="fecha_cese" id="<?php echo $prefix; ?>FechaCese" disabled>
                    <label for="<?php echo $prefix; ?>FechaCese">Fecha de Cese <span class="text-danger d-none" id="<?php echo $prefix; ?>FechaCeseRequired">*</span></label>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="tipo_pago" id="<?php echo $prefix; ?>TipoPago">
                        <option value="MENSUAL" selected>Mensual</option>
                        <option value="QUINCENAL">Quincenal</option>
                        <option value="DIARIO">Diario (Jornal)</option>
                    </select>
                    <label for="<?php echo $prefix; ?>TipoPago">Frecuencia Pago</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="moneda" id="<?php echo $prefix; ?>Moneda">
                        <option value="PEN" selected>S/ (Soles)</option>
                        <option value="USD">$ (Dólares)</option>
                    </select>
                    <label for="<?php echo $prefix; ?>Moneda">Moneda Pago</label>
                </div>
            </div>
            <div class="col-md-4" id="<?php echo $prefix; ?>SueldoGroup">
                <div class="form-floating">
                    <input type="number" step="0.01" min="0" class="form-control" name="sueldo_basico" id="<?php echo $prefix; ?>SueldoBasico" placeholder="0.00" data-required-empleado="1">
                    <label for="<?php echo $prefix; ?>SueldoBasico">Sueldo Básico <span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="col-md-4 d-none" id="<?php echo $prefix; ?>PagoDiarioGroup">
                <div class="form-floating">
                    <input type="number" step="0.01" class="form-control" name="pago_diario" id="<?php echo $prefix; ?>PagoDiario" placeholder="0.00">
                    <label for="<?php echo $prefix; ?>PagoDiario">Pago Diario</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0 fw-bold text-muted small text-uppercase"><i class="bi bi-shield-check me-1"></i> Régimen y Seguridad Social</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-floating">
                    <select class="form-select" name="regimen_pensionario" id="<?php echo $prefix; ?>Regimen">
                        <option value="">Ninguno</option>
                        <option value="ONP">ONP (Nacional)</option>
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
                    <select class="form-select" name="tipo_comision_afp" id="<?php echo $prefix; ?>TipoComision" disabled>
                        <option value="">No Aplica</option>
                        <option value="FLUJO">Comisión sobre Flujo</option>
                        <option value="MIXTA">Comisión Mixta</option>
                    </select>
                    <label for="<?php echo $prefix; ?>TipoComision">Tipo Comisión AFP</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <input type="text" class="form-control" name="cuspp" id="<?php echo $prefix; ?>Cuspp" placeholder="CUSPP" disabled>
                    <label for="<?php echo $prefix; ?>Cuspp">CUSPP</label>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-check form-switch p-2 border rounded bg-white h-100 d-flex align-items-center">
                    <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="<?php echo $prefix; ?>AsignacionFamiliar" name="asignacion_familiar" value="1" style="margin-top: 0;">
                    <label class="form-check-label small lh-1 fw-bold text-primary" for="<?php echo $prefix; ?>AsignacionFamiliar">Asignación Familiar (+10%)</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch p-2 border rounded bg-white h-100 d-flex align-items-center">
                    <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="<?php echo $prefix; ?>Essalud" name="essalud" value="1" style="margin-top: 0;">
                    <label class="form-check-label small" for="<?php echo $prefix; ?>Essalud">Aportante EsSalud (9%)</label>
                </div>
            </div>

            <div class="col-12 mt-3 d-none" id="<?php echo $prefix; ?>WrapperHijos">
                <div class="card bg-light border">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="card-title m-0 text-primary small fw-bold text-uppercase"><i class="bi bi-people-fill me-1"></i> Hijos / Dependientes</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary bg-white" id="<?php echo $prefix; ?>BtnAgregarHijo">
                                <i class="bi bi-plus-lg"></i> Agregar Hijo
                            </button>
                        </div>
                        
                        <div class="row g-2 small text-muted mb-2 px-1 d-none d-md-flex fw-bold">
                            <div class="col-md-4">Nombre Completo</div>
                            <div class="col-md-3">F. Nacimiento</div>
                            <div class="col-md-2 text-center">¿Estudia? (>18)</div>
                            <div class="col-md-2 text-center">Discapacidad</div>
                            <div class="col-md-1"></div>
                        </div>

                        <div id="<?php echo $prefix; ?>ListaHijos">
                            <div class="alert alert-info py-2 small mb-0 text-center" id="<?php echo $prefix; ?>HijosEmptyState">
                                <i class="bi bi-info-circle me-1"></i> Agrega los hijos para sustentar la asignación familiar.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0 fw-bold text-muted small text-uppercase"><i class="bi bi-heart-pulse me-1"></i> Emergencia (SST)</h6>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-5">
                <div class="form-floating">
                    <input type="text" class="form-control" name="contacto_emergencia_nombre" id="<?php echo $prefix; ?>ContactoEmergenciaNombre" placeholder="Nombre">
                    <label for="<?php echo $prefix; ?>ContactoEmergenciaNombre">Nombre Contacto</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating">
                    <input type="text" class="form-control" name="contacto_emergencia_telf" id="<?php echo $prefix; ?>ContactoEmergenciaTelf" placeholder="Teléfono">
                    <label for="<?php echo $prefix; ?>ContactoEmergenciaTelf">Teléfono Contacto</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-floating">
                    <select class="form-select" name="tipo_sangre" id="<?php echo $prefix; ?>TipoSangre">
                        <option value="">Seleccionar...</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                    <label for="<?php echo $prefix; ?>TipoSangre">Tipo Sangre</label>
                </div>
            </div>
        </div>
    </div>
</div>
