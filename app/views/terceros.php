<?php $terceros = $terceros ?? []; ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-people-fill me-2 text-primary"></i> Terceros
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión unificada de clientes, proveedores y empleados.</p>
        </div>
        <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearTercero">
            <i class="bi bi-person-plus-fill me-2"></i>Nuevo Tercero
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="terceroSearch" placeholder="Buscar tercero...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="terceroFiltroRol">
                        <option value="">Todos los roles</option>
                        <option value="CLIENTE">Cliente</option>
                        <option value="PROVEEDOR">Proveedor</option>
                        <option value="EMPLEADO">Empleado</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="terceroFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activo</option>
                        <option value="2">Bloqueado</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tercerosTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Documento</th>
                            <th>Tercero</th>
                            <th>Roles</th>
                            <th>Contacto</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($terceros as $tercero): ?>
                        <?php
                            $roles = [];
                            if ((int) $tercero['es_cliente'] === 1) $roles[] = 'CLIENTE';
                            if ((int) $tercero['es_proveedor'] === 1) $roles[] = 'PROVEEDOR';
                            if ((int) $tercero['es_empleado'] === 1) $roles[] = 'EMPLEADO';
                            $rolesFiltro = implode('|', $roles);
                            $telefonos = $tercero['telefonos'] ?? [];
                            if ($telefonos === [] && !empty($tercero['telefono'])) {
                                $telefonos = [['telefono' => $tercero['telefono'], 'tipo' => null]];
                            }
                            $telefonoPrincipal = $telefonos[0]['telefono'] ?? ($tercero['telefono'] ?? '');
                            $telefonosTexto = implode(' ', array_map(static fn($tel) => (string) ($tel['telefono'] ?? ''), $telefonos));
                            $telefonosExtra = max(count($telefonos) - 1, 0);
                            $cuentasBancarias = $tercero['cuentas_bancarias'] ?? [];
                        ?>
                        <tr data-estado="<?php echo (int) $tercero['estado']; ?>"
                            data-roles="<?php echo e($rolesFiltro); ?>"
                            data-search="<?php echo e(mb_strtolower($tercero['tipo_documento'].' '.$tercero['numero_documento'].' '.$tercero['nombre_completo'].' '.($tercero['direccion'] ?? '').' '.$telefonosTexto.' '.($tercero['email'] ?? ''))); ?>">
                            <td class="ps-4 fw-semibold" data-label="Documento"><?php echo e($tercero['tipo_documento']); ?> - <?php echo e($tercero['numero_documento']); ?></td>
                            <td data-label="Tercero">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:40px; height:40px; border-radius:50%;">
                                        <?php echo strtoupper(substr((string) $tercero['nombre_completo'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo e($tercero['nombre_completo']); ?></div>
                                        <div class="small text-muted"><?php echo e($tercero['direccion'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Roles">
                                <?php if ($roles === []): ?>
                                    <span class="badge bg-light text-dark border">Sin rol</span>
                                <?php else: ?>
                                    <?php foreach ($roles as $rol): ?>
                                        <span class="badge bg-light text-dark border me-1"><?php echo e($rol); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="Contacto">
                                <div><?php echo e($telefonoPrincipal); ?></div>
                                <?php if ($telefonosExtra > 0): ?>
                                    <div class="small text-muted">+<?php echo (int) $telefonosExtra; ?> teléfono(s)</div>
                                <?php endif; ?>
                                <div class="small text-muted"><?php echo e($tercero['email'] ?? ''); ?></div>
                            </td>
                            <td class="text-center" data-label="Estado">
                                <?php if ((int) $tercero['estado'] === 1): ?>
                                    <span class="badge-status status-active" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Activo</span>
                                <?php elseif ((int) $tercero['estado'] === 2): ?>
                                    <span class="badge-status status-inactive" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Bloqueado</span>
                                <?php else: ?>
                                    <span class="badge-status status-inactive" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4" data-label="Acciones">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <div class="form-check form-switch pt-1" title="Cambiar estado">
                                        <input class="form-check-input switch-estado-tercero" type="checkbox" role="switch"
                                               style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                               data-id="<?php echo (int) $tercero['id']; ?>"
                                               <?php echo (int) $tercero['estado'] === 1 ? 'checked' : ''; ?>>
                                    </div>

                                    <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                    <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalEditarTercero"
                                            data-id="<?php echo (int) $tercero['id']; ?>"
                                            data-tipo-persona="<?php echo e($tercero['tipo_persona'] ?? 'NATURAL'); ?>"
                                            data-tipo-doc="<?php echo e($tercero['tipo_documento']); ?>"
                                            data-numero-doc="<?php echo e($tercero['numero_documento']); ?>"
                                            data-nombre="<?php echo e($tercero['nombre_completo']); ?>"
                                            data-direccion="<?php echo e($tercero['direccion'] ?? ''); ?>"
                                            data-telefonos="<?php echo e(htmlspecialchars(json_encode($telefonos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')); ?>"
                                            data-email="<?php echo e($tercero['email'] ?? ''); ?>"
                                            data-departamento="<?php echo e($tercero['departamento'] ?? ''); ?>"
                                            data-provincia="<?php echo e($tercero['provincia'] ?? ''); ?>"
                                            data-distrito="<?php echo e($tercero['distrito'] ?? ''); ?>"
                                            data-observaciones="<?php echo e($tercero['observaciones'] ?? ''); ?>"
                                            data-condicion-pago="<?php echo e($tercero['condicion_pago'] ?? ''); ?>"
                                            data-dias-credito="<?php echo e((string) ($tercero['dias_credito'] ?? '')); ?>"
                                            data-limite-credito="<?php echo e((string) ($tercero['limite_credito'] ?? '')); ?>"
                                            data-cargo="<?php echo e($tercero['cargo'] ?? ''); ?>"
                                            data-area="<?php echo e($tercero['area'] ?? ''); ?>"
                                            data-fecha-ingreso="<?php echo e($tercero['fecha_ingreso'] ?? ''); ?>"
                                            data-estado-laboral="<?php echo e($tercero['estado_laboral'] ?? ''); ?>"
                                            data-sueldo-basico="<?php echo e((string) ($tercero['sueldo_basico'] ?? '')); ?>"
                                            data-tipo-pago="<?php echo e($tercero['tipo_pago'] ?? ''); ?>"
                                            data-pago-diario="<?php echo e((string) ($tercero['pago_diario'] ?? '')); ?>"
                                            data-regimen-pensionario="<?php echo e($tercero['regimen_pensionario'] ?? ''); ?>"
                                            data-essalud="<?php echo (int) ($tercero['essalud'] ?? 0); ?>"
                                            data-estado="<?php echo (int) $tercero['estado']; ?>"
                                            data-es-cliente="<?php echo (int) $tercero['es_cliente']; ?>"
                                            data-es-proveedor="<?php echo (int) $tercero['es_proveedor']; ?>"
                                            data-es-empleado="<?php echo (int) $tercero['es_empleado']; ?>"
                                            data-cuentas-bancarias="<?php echo e(htmlspecialchars(json_encode($cuentasBancarias, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')); ?>"
                                            title="Editar">
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>
                                    <form method="post" class="delete-form d-inline m-0" onsubmit="return confirm('¿Eliminar este tercero?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int) $tercero['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent" title="Eliminar">
                                            <i class="bi bi-trash fs-5"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="tercerosPaginationInfo">Cargando...</small>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0 justify-content-end" id="tercerosPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearTercero" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Nuevo Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCrearTercero" method="POST" novalidate>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light">
                                <label class="form-label fw-semibold mb-2">Roles <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="crearEsCliente" name="es_cliente" value="1">
                                        <label class="form-check-label" for="crearEsCliente">Cliente</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="crearEsProveedor" name="es_proveedor" value="1">
                                        <label class="form-check-label" for="crearEsProveedor">Proveedor</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="crearEsEmpleado" name="es_empleado" value="1">
                                        <label class="form-check-label" for="crearEsEmpleado">Empleado</label>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-none" id="crearRolesFeedback">Seleccione al menos un rol.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_persona" id="crearTipoPersona" required>
                                    <option value="" selected>Seleccionar...</option>
                                    <option value="NATURAL">Natural</option>
                                    <option value="JURIDICA">Jurídica</option>
                                </select>
                                <label for="crearTipoPersona">Tipo de persona <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Seleccione el tipo de persona.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_documento" id="crearTipoDoc" required>
                                    <option value="" selected>Seleccionar...</option>
                                    <option value="DNI">DNI</option>
                                    <option value="RUC">RUC</option>
                                    <option value="CE">CE</option>
                                    <option value="PASAPORTE">Pasaporte</option>
                                </select>
                                <label for="crearTipoDoc">Tipo documento <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Seleccione el tipo de documento.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <div class="form-floating flex-grow-1">
                                    <input type="text" class="form-control" name="numero_documento" id="crearNumeroDoc" placeholder="Número" required>
                                    <label for="crearNumeroDoc">Número <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="invalid-feedback">Ingrese un número válido.</div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="nombre_completo" id="crearNombre" placeholder="Nombre" required>
                                <label for="crearNombre" id="crearNombreLabel">Nombre completo <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Ingrese el nombre o razón social.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="direccion" id="crearDireccion" placeholder="Dirección">
                                <label for="crearDireccion">Dirección</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" name="email" id="crearEmail" placeholder="Correo">
                                <label for="crearEmail">Email</label>
                                <div class="invalid-feedback">Ingrese un email válido.</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Teléfonos</label>
                            <div id="crearTelefonosList" class="d-flex flex-column gap-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="crearAgregarTelefono">
                                <i class="bi bi-plus-circle me-1"></i>Agregar teléfono
                            </button>
                            <div class="invalid-feedback d-none" id="crearTelefonosFeedback">Ingrese teléfonos válidos.</div>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="departamento" id="crearDepartamento"></select>
                                        <label for="crearDepartamento">Departamento</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="provincia" id="crearProvincia"></select>
                                        <label for="crearProvincia">Provincia</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="distrito" id="crearDistrito"></select>
                                        <label for="crearDistrito">Distrito</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" name="observaciones" id="crearObservaciones" style="height: 90px" placeholder="Observaciones"></textarea>
                                <label for="crearObservaciones">Observaciones</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" name="estado" id="crearEstado">
                                    <option value="1" selected>Activo</option>
                                    <option value="0">Inactivo</option>
                                    <option value="2">Bloqueado</option>
                                </select>
                                <label for="crearEstado">Estado</label>
                            </div>
                        </div>

                        <div class="col-12 comercial-fields" id="crearComercialFields">
                            <hr class="my-2">
                            <h6 class="fw-bold">Datos Comerciales</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="condicion_pago" id="crearCondicionPago" placeholder="Condición de pago">
                                        <label for="crearCondicionPago">Condición de pago</label>
                                        <div class="invalid-feedback">Ingrese la condición de pago.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" name="dias_credito" id="crearDiasCredito" placeholder="Días de crédito">
                                        <label for="crearDiasCredito">Días de crédito</label>
                                        <div class="invalid-feedback">Ingrese los días de crédito.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" class="form-control" name="limite_credito" id="crearLimiteCredito" placeholder="Límite de crédito">
                                        <label for="crearLimiteCredito">Límite de crédito</label>
                                        <div class="invalid-feedback">Ingrese el límite de crédito.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="crearCuentasBancariasSection">
                            <hr class="my-2">
                            <h6 class="fw-bold">Cuentas bancarias</h6>
                            <div id="crearCuentasBancariasList" class="d-flex flex-column gap-3"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="crearAgregarCuenta">
                                <i class="bi bi-plus-circle me-1"></i>Agregar cuenta
                            </button>
                            <div class="invalid-feedback d-none" id="crearCuentasFeedback">Complete los datos de la cuenta.</div>
                        </div>

                        <div class="col-12 laboral-fields" id="crearLaboralFields">
                            <hr class="my-2">
                            <h6 class="fw-bold">Datos Laborales</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="cargo" id="crearCargo" placeholder="Cargo">
                                        <label for="crearCargo">Cargo <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">Ingrese el cargo.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="area" id="crearArea" placeholder="Área">
                                        <label for="crearArea">Área <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">Ingrese el área.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" name="fecha_ingreso" id="crearFechaIngreso">
                                        <label for="crearFechaIngreso">Fecha de ingreso <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">Seleccione la fecha de ingreso.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="estado_laboral" id="crearEstadoLaboral">
                                            <option value="">Seleccionar...</option>
                                            <option value="activo">Activo</option>
                                            <option value="suspendido">Suspendido</option>
                                            <option value="cesado">Cesado</option>
                                        </select>
                                        <label for="crearEstadoLaboral">Estado laboral <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">Seleccione el estado laboral.</div>
                                    </div>
                                </div>
                                <div class="col-md-4" id="crearSueldoBasicoWrapper">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" class="form-control" name="sueldo_basico" id="crearSueldoBasico" placeholder="Sueldo básico">
                                        <label for="crearSueldoBasico">Sueldo básico</label>
                                        <div class="invalid-feedback">Ingrese el sueldo básico.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="tipo_pago" id="crearTipoPago">
                                            <option value="">Seleccionar...</option>
                                            <option value="DIARIO">Pago diario (8 horas)</option>
                                            <option value="SUELDO">Sueldo mensual</option>
                                        </select>
                                        <label for="crearTipoPago">Tipo de pago <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">Seleccione el tipo de pago.</div>
                                    </div>
                                </div>
                                <div class="col-md-4" id="crearPagoDiarioWrapper">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" class="form-control" name="pago_diario" id="crearPagoDiario" placeholder="Pago diario">
                                        <label for="crearPagoDiario">Pago diario (8 horas)</label>
                                        <div class="invalid-feedback">Ingrese el pago diario.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="regimen_pensionario" id="crearRegimenPensionario">
                                            <option value="">Seleccionar...</option>
                                            <option value="AFP">AFP</option>
                                            <option value="ONP">ONP</option>
                                        </select>
                                        <label for="crearRegimenPensionario">Régimen pensionario</label>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-center">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="crearEssalud" name="essalud" value="1">
                                        <label class="form-check-label" for="crearEssalud">Afiliado a ESSALUD</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="crearGuardarBtn"><i class="bi bi-save me-2"></i>Guardar Tercero</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarTercero" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">Editar Tercero</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" id="formEditarTercero" class="row g-3" novalidate>
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editTerceroId">
                    <div class="col-md-4 form-floating">
                        <select class="form-select" name="tipo_persona" id="editTipoPersona" required>
                            <option value="" selected>Seleccionar...</option>
                            <option value="NATURAL">Natural</option>
                            <option value="JURIDICA">Jurídica</option>
                        </select>
                        <label for="editTipoPersona">Tipo de persona <span class="text-danger">*</span></label>
                        <div class="invalid-feedback">Seleccione el tipo de persona.</div>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editTipoDoc" name="tipo_documento" required>
                            <option value="" selected>Seleccionar...</option>
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                            <option value="CE">CE</option>
                            <option value="PASAPORTE">Pasaporte</option>
                        </select>
                        <label for="editTipoDoc">Tipo documento <span class="text-danger">*</span></label>
                        <div class="invalid-feedback">Seleccione el tipo de documento.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input class="form-control" id="editNumeroDoc" name="numero_documento" required>
                            <label for="editNumeroDoc">Número <span class="text-danger">*</span></label>
                            <div class="invalid-feedback">Ingrese un número válido.</div>
                        </div>
                    </div>
                    <div class="col-md-8 form-floating">
                        <input class="form-control" id="editNombre" name="nombre_completo" required>
                        <label for="editNombre" id="editNombreLabel">Nombre completo <span class="text-danger">*</span></label>
                        <div class="invalid-feedback">Ingrese el nombre o razón social.</div>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editEstado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                            <option value="2">Bloqueado</option>
                        </select>
                        <label for="editEstado">Estado</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input class="form-control" id="editDireccion" name="direccion">
                        <label for="editDireccion">Dirección</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input class="form-control" id="editEmail" name="email" type="email">
                        <label for="editEmail">Email</label>
                        <div class="invalid-feedback">Ingrese un email válido.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Teléfonos</label>
                        <div id="editTelefonosList" class="d-flex flex-column gap-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="editAgregarTelefono">
                            <i class="bi bi-plus-circle me-1"></i>Agregar teléfono
                        </button>
                        <div class="invalid-feedback d-none" id="editTelefonosFeedback">Ingrese teléfonos válidos.</div>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" name="departamento" id="editDepartamento"></select>
                                    <label for="editDepartamento">Departamento</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" name="provincia" id="editProvincia"></select>
                                    <label for="editProvincia">Provincia</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" name="distrito" id="editDistrito"></select>
                                    <label for="editDistrito">Distrito</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Roles <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="editEsCliente" name="es_cliente" value="1"><label class="form-check-label" for="editEsCliente">Cliente</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="editEsProveedor" name="es_proveedor" value="1"><label class="form-check-label" for="editEsProveedor">Proveedor</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="editEsEmpleado" name="es_empleado" value="1"><label class="form-check-label" for="editEsEmpleado">Empleado</label></div>
                        </div>
                        <div class="invalid-feedback d-none" id="editRolesFeedback">Seleccione al menos un rol.</div>
                    </div>
                    <div class="col-12 comercial-fields" id="editComercialFields">
                        <hr class="my-2">
                        <h6 class="fw-bold">Datos Comerciales</h6>
                        <div class="row g-3">
                            <div class="col-md-4 form-floating">
                                <input class="form-control" id="editCondicionPago" name="condicion_pago">
                                <label for="editCondicionPago">Condición de pago</label>
                                <div class="invalid-feedback">Ingrese la condición de pago.</div>
                            </div>
                            <div class="col-md-4 form-floating">
                                <input class="form-control" id="editDiasCredito" name="dias_credito" type="number">
                                <label for="editDiasCredito">Días de crédito</label>
                                <div class="invalid-feedback">Ingrese los días de crédito.</div>
                            </div>
                            <div class="col-md-4 form-floating">
                                <input class="form-control" id="editLimiteCredito" name="limite_credito" type="number" step="0.01">
                                <label for="editLimiteCredito">Límite de crédito</label>
                                <div class="invalid-feedback">Ingrese el límite de crédito.</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12" id="editCuentasBancariasSection">
                        <hr class="my-2">
                        <h6 class="fw-bold">Cuentas bancarias</h6>
                        <div id="editCuentasBancariasList" class="d-flex flex-column gap-3"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="editAgregarCuenta">
                            <i class="bi bi-plus-circle me-1"></i>Agregar cuenta
                        </button>
                        <div class="invalid-feedback d-none" id="editCuentasFeedback">Complete los datos de la cuenta.</div>
                    </div>

                    <div class="col-12"><hr class="my-2"><h6 class="fw-bold">Observaciones</h6></div>
                    <div class="col-12">
                        <div class="form-floating">
                            <textarea class="form-control" name="observaciones" id="editObservaciones" style="height: 90px"></textarea>
                            <label for="editObservaciones">Observaciones</label>
                        </div>
                    </div>

                    <div class="col-12 laboral-fields" id="editLaboralFields">
                        <hr class="my-2">
                        <h6 class="fw-bold">Datos Laborales</h6>
                        <div class="row g-3">
                            <div class="col-md-4 form-floating">
                                <input class="form-control" id="editCargo" name="cargo">
                                <label for="editCargo">Cargo <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Ingrese el cargo.</div>
                            </div>
                            <div class="col-md-4 form-floating">
                                <input class="form-control" id="editArea" name="area">
                                <label for="editArea">Área <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Ingrese el área.</div>
                            </div>
                            <div class="col-md-4 form-floating">
                                <input class="form-control" id="editFechaIngreso" name="fecha_ingreso" type="date">
                                <label for="editFechaIngreso">Fecha de ingreso <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Seleccione la fecha de ingreso.</div>
                            </div>
                            <div class="col-md-4 form-floating">
                                <select class="form-select" name="estado_laboral" id="editEstadoLaboral">
                                    <option value="">Seleccionar...</option>
                                    <option value="activo">Activo</option>
                                    <option value="suspendido">Suspendido</option>
                                    <option value="cesado">Cesado</option>
                                </select>
                                <label for="editEstadoLaboral">Estado laboral <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Seleccione el estado laboral.</div>
                            </div>
                            <div class="col-md-4" id="editSueldoBasicoWrapper">
                                <div class="form-floating">
                                    <input class="form-control" id="editSueldoBasico" name="sueldo_basico" type="number" step="0.01">
                                    <label for="editSueldoBasico">Sueldo básico</label>
                                    <div class="invalid-feedback">Ingrese el sueldo básico.</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" name="tipo_pago" id="editTipoPago">
                                        <option value="">Seleccionar...</option>
                                        <option value="DIARIO">Pago diario (8 horas)</option>
                                        <option value="SUELDO">Sueldo mensual</option>
                                    </select>
                                    <label for="editTipoPago">Tipo de pago <span class="text-danger">*</span></label>
                                    <div class="invalid-feedback">Seleccione el tipo de pago.</div>
                                </div>
                            </div>
                            <div class="col-md-4" id="editPagoDiarioWrapper">
                                <div class="form-floating">
                                    <input class="form-control" id="editPagoDiario" name="pago_diario" type="number" step="0.01">
                                    <label for="editPagoDiario">Pago diario (8 horas)</label>
                                    <div class="invalid-feedback">Ingrese el pago diario.</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" name="regimen_pensionario" id="editRegimenPensionario">
                                        <option value="">Seleccionar...</option>
                                        <option value="AFP">AFP</option>
                                        <option value="ONP">ONP</option>
                                    </select>
                                    <label for="editRegimenPensionario">Régimen pensionario</label>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="editEssalud" name="essalud" value="1">
                                    <label class="form-check-label" for="editEssalud">Afiliado a ESSALUD</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit" id="editGuardarBtn"><i class="bi bi-save me-2"></i>Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('js/terceros.js'); ?>"></script>
