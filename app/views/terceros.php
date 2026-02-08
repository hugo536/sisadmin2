<?php 
    $terceros = $terceros ?? []; 
    $departamentos_list = $departamentos_list ?? []; 
?>
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
                            if (empty($telefonos) && !empty($tercero['telefono'])) {
                                $telefonos = [['telefono' => $tercero['telefono'], 'tipo' => 'Principal']];
                            }
                            
                            $telefonoPrincipal = $telefonos[0]['telefono'] ?? ($tercero['telefono'] ?? '');
                            $telefonosTexto = implode(' ', array_column($telefonos, 'telefono'));
                            $telefonosExtra = max(count($telefonos) - 1, 0);
                            $cuentasBancarias = $tercero['cuentas_bancarias'] ?? [];
                            
                            $depId = $tercero['departamento_id'] ?? 0;
                            if ($depId == 0 && !empty($tercero['departamento'])) {
                                foreach ($departamentos_list as $dep) {
                                    if (strcasecmp($dep['nombre'], $tercero['departamento']) === 0) {
                                        $depId = $dep['id'];
                                        break;
                                    }
                                }
                            }
                        ?>
                        <tr data-estado="<?php echo (int) $tercero['estado']; ?>"
                            data-roles="<?php echo htmlspecialchars($rolesFiltro); ?>"
                            data-search="<?php echo htmlspecialchars(mb_strtolower($tercero['tipo_documento'].' '.$tercero['numero_documento'].' '.$tercero['nombre_completo'].' '.($tercero['direccion'] ?? '').' '.$telefonosTexto.' '.($tercero['email'] ?? ''))); ?>">
                            <td class="ps-4 fw-semibold" data-label="Documento">
                                <?php echo htmlspecialchars($tercero['tipo_documento']); ?> - <?php echo htmlspecialchars($tercero['numero_documento']); ?>
                            </td>
                            <td data-label="Tercero">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:40px; height:40px; border-radius:50%;">
                                        <?php echo strtoupper(substr((string) $tercero['nombre_completo'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($tercero['nombre_completo']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($tercero['direccion'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Roles">
                                <?php if (empty($roles)): ?>
                                    <span class="badge bg-light text-dark border">Sin rol</span>
                                <?php else: ?>
                                    <?php foreach ($roles as $rol): ?>
                                        <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($rol); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="Contacto">
                                <div><?php echo htmlspecialchars($telefonoPrincipal); ?></div>
                                <?php if ($telefonosExtra > 0): ?>
                                    <div class="small text-muted">+<?php echo $telefonosExtra; ?> teléfono(s)</div>
                                <?php endif; ?>
                                <div class="small text-muted"><?php echo htmlspecialchars($tercero['email'] ?? ''); ?></div>
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

                                    <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" 
                                            data-bs-toggle="modal" data-bs-target="#modalEditarTercero"
                                            data-id="<?php echo (int) $tercero['id']; ?>"
                                            data-tipo-persona="<?php echo htmlspecialchars($tercero['tipo_persona'] ?? 'NATURAL'); ?>"
                                            data-tipo-doc="<?php echo htmlspecialchars($tercero['tipo_documento']); ?>"
                                            data-numero-doc="<?php echo htmlspecialchars($tercero['numero_documento']); ?>"
                                            data-nombre="<?php echo htmlspecialchars($tercero['nombre_completo']); ?>"
                                            data-direccion="<?php echo htmlspecialchars($tercero['direccion'] ?? ''); ?>"
                                            data-email="<?php echo htmlspecialchars($tercero['email'] ?? ''); ?>"
                                            data-departamento="<?php echo (int) $depId; ?>"
                                            data-departamento-nombre="<?php echo htmlspecialchars($tercero['departamento'] ?? ''); ?>"
                                            data-provincia="<?php echo (int) ($tercero['provincia_id'] ?? 0); ?>"
                                            data-provincia-nombre="<?php echo htmlspecialchars($tercero['provincia'] ?? ''); ?>"
                                            data-distrito="<?php echo (int) ($tercero['distrito_id'] ?? 0); ?>"
                                            data-distrito-nombre="<?php echo htmlspecialchars($tercero['distrito'] ?? ''); ?>"
                                            
                                            data-observaciones="<?php echo htmlspecialchars($tercero['observaciones'] ?? ''); ?>"
                                            
                                            data-condicion-pago="<?php echo htmlspecialchars($tercero['condicion_pago'] ?? ''); ?>"
                                            data-dias-credito="<?php echo (int) ($tercero['dias_credito'] ?? 0); ?>"
                                            data-limite-credito="<?php echo (float) ($tercero['limite_credito'] ?? 0); ?>"
                                            
                                            data-cliente-dias-credito="<?php echo (int) ($tercero['cliente_dias_credito'] ?? 0); ?>"
                                            data-cliente-limite-credito="<?php echo (float) ($tercero['cliente_limite_credito'] ?? 0); ?>"
                                            data-proveedor-condicion-pago="<?php echo htmlspecialchars($tercero['proveedor_condicion_pago'] ?? ''); ?>"
                                            data-proveedor-dias-credito="<?php echo (int) ($tercero['proveedor_dias_credito'] ?? 0); ?>"
                                            
                                            data-cargo="<?php echo htmlspecialchars($tercero['cargo'] ?? ''); ?>"
                                            data-area="<?php echo htmlspecialchars($tercero['area'] ?? ''); ?>"
                                            data-fecha-ingreso="<?php echo htmlspecialchars($tercero['fecha_ingreso'] ?? ''); ?>"
                                            data-estado-laboral="<?php echo htmlspecialchars($tercero['estado_laboral'] ?? ''); ?>"
                                            data-sueldo-basico="<?php echo (float) ($tercero['sueldo_basico'] ?? 0); ?>"
                                            data-tipo-pago="<?php echo htmlspecialchars($tercero['tipo_pago'] ?? ''); ?>"
                                            data-pago-diario="<?php echo (float) ($tercero['pago_diario'] ?? 0); ?>"
                                            data-regimen-pensionario="<?php echo htmlspecialchars($tercero['regimen_pensionario'] ?? ''); ?>"
                                            data-essalud="<?php echo (int) ($tercero['essalud'] ?? 0); ?>"
                                            
                                            data-estado="<?php echo (int) $tercero['estado']; ?>"
                                            data-es-cliente="<?php echo (int) $tercero['es_cliente']; ?>"
                                            data-es-proveedor="<?php echo (int) $tercero['es_proveedor']; ?>"
                                            data-es-empleado="<?php echo (int) $tercero['es_empleado']; ?>"
                                            
                                            data-telefonos="<?php echo htmlspecialchars(json_encode($telefonos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-cuentas-bancarias="<?php echo htmlspecialchars(json_encode($cuentasBancarias, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
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
                <small class="text-muted" id="tercerosPaginationInfo">Mostrando todos los registros</small>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0 justify-content-end" id="tercerosPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Tercero -->
<div class="modal fade" id="modalCrearTercero" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Nuevo Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCrearTercero" method="POST" novalidate>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Roles -->
                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light">
                                <label class="form-label fw-semibold mb-2">Roles <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input role-checkbox" type="checkbox" id="crearEsCliente" name="es_cliente" value="1">
                                        <label class="form-check-label" for="crearEsCliente">Cliente</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-checkbox" type="checkbox" id="crearEsProveedor" name="es_proveedor" value="1">
                                        <label class="form-check-label" for="crearEsProveedor">Proveedor</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-checkbox" type="checkbox" id="crearEsEmpleado" name="es_empleado" value="1">
                                        <label class="form-check-label" for="crearEsEmpleado">Empleado</label>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-none" id="crearRolesFeedback">Seleccione al menos un rol.</div>
                            </div>
                        </div>

                        <!-- Tipo Persona / Documento -->
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_persona" id="crearTipoPersona" required>
                                    <option value="" selected>Seleccionar...</option>
                                    <option value="NATURAL">Natural</option>
                                    <option value="JURIDICA">Jurídica</option>
                                </select>
                                <label for="crearTipoPersona">Tipo de persona <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_documento" id="crearTipoDoc" required>
                                    <option value="" selected>Seleccionar...</option>
                                    <option value="DNI">DNI</option>
                                    <option value="RUC">RUC</option>
                                    <option value="CE">CE</option>
                                    <option value="PASAPORTE">Pasaporte</option>
                                </select>
                                <label for="crearTipoDoc">Tipo documento <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="numero_documento" id="crearNumeroDoc" placeholder="Número" required>
                                <label for="crearNumeroDoc">Número <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Ingrese un número válido.</div>
                            </div>
                        </div>

                        <!-- Nombre / Dirección / Email -->
                        <div class="col-md-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="nombre_completo" id="crearNombre" placeholder="Nombre completo" required>
                                <label for="crearNombre">Nombre completo / Razón social <span class="text-danger">*</span></label>
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
                                <input type="email" class="form-control" name="email" id="crearEmail" placeholder="Correo electrónico">
                                <label for="crearEmail">Email</label>
                            </div>
                        </div>

                        <!-- Teléfonos -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Teléfonos</label>
                            <div id="crearTelefonosList" class="d-flex flex-column gap-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="crearAgregarTelefono">
                                <i class="bi bi-plus-circle me-1"></i>Agregar teléfono
                            </button>
                        </div>

                        <!-- Ubigeo -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Ubicación</label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="departamento" id="crearDepartamento">
                                            <option value="">Seleccionar departamento...</option>
                                            <?php foreach ($departamentos_list as $dep): ?>
                                                <option value="<?php echo (int) $dep['id']; ?>">
                                                    <?php echo htmlspecialchars($dep['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="crearDepartamento">Departamento</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="provincia" id="crearProvincia" disabled>
                                            <option value="">Primero seleccione departamento</option>
                                        </select>
                                        <label for="crearProvincia">Provincia</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="distrito" id="crearDistrito" disabled>
                                            <option value="">Primero seleccione provincia</option>
                                        </select>
                                        <label for="crearDistrito">Distrito</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" name="observaciones" id="crearObservaciones" style="height: 80px;"></textarea>
                                <label for="crearObservaciones">Observaciones</label>
                            </div>
                        </div>

                        <!-- Estado -->
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

                        <!-- Cuentas Bancarias -->
                        <div class="col-12" id="crearCuentasBancariasSection">
                            <hr class="my-3">
                            <h6 class="fw-bold mb-3">Cuentas Bancarias / Billeteras Digitales</h6>
                            <div id="crearCuentasBancariasList" class="d-flex flex-column gap-3"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="crearAgregarCuenta">
                                <i class="bi bi-plus-circle me-1"></i>Agregar cuenta / billetera
                            </button>
                        </div>

                        <!-- Campos Comerciales (condicionados por roles cliente/proveedor) -->
                        <div class="col-12 comercial-fields d-none" id="crearComercialFields">
                            <hr class="my-3">
                            <h6 class="fw-bold mb-3 text-primary">Datos Comerciales</h6>
                            <div class="row g-3">
                                <div class="col-md-6 border-end">
                                    <h6 class="small text-muted fw-bold mb-2">CLIENTE</h6>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="cliente_dias_credito" id="crearClienteDiasCredito" min="0" placeholder="0">
                                                <label for="crearClienteDiasCredito">Días Crédito (Cliente)</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="number" step="0.01" class="form-control" name="cliente_limite_credito" id="crearClienteLimiteCredito" min="0" placeholder="0.00">
                                                <label for="crearClienteLimiteCredito">Límite Crédito (S/)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="small text-muted fw-bold mb-2">PROVEEDOR</h6>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <select class="form-select" name="proveedor_condicion_pago" id="crearProvCondicion">
                                                    <option value="">Seleccionar...</option>
                                                    <option value="contado">Contado</option>
                                                    <option value="credito">Crédito</option>
                                                </select>
                                                <label for="crearProvCondicion">Condición Pago</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="proveedor_dias_credito" id="crearProvDiasCredito" min="0" placeholder="0">
                                                <label for="crearProvDiasCredito">Días Crédito (Proveedor)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Campo Legacy/Generico -->
                                <div class="col-12 mt-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="condicion_pago" id="crearCondicionPago" placeholder="Condición general">
                                        <label for="crearCondicionPago">Condición de pago (General)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos Laborales (condicionados por rol empleado) -->
                        <div class="col-12 laboral-fields d-none" id="crearLaboralFields">
                            <hr class="my-3">
                            <h6 class="fw-bold mb-3 text-success">Datos Laborales</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="cargo" id="crearCargo" placeholder="Ej: Operario">
                                        <label for="crearCargo">Cargo <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="area" id="crearArea" placeholder="Ej: Producción">
                                        <label for="crearArea">Área <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" name="fecha_ingreso" id="crearFechaIngreso">
                                        <label for="crearFechaIngreso">Fecha de ingreso</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="estado_laboral" id="crearEstadoLaboral">
                                            <option value="activo">Activo</option>
                                            <option value="cesado">Cesado</option>
                                            <option value="suspendido">Suspendido</option>
                                        </select>
                                        <label for="crearEstadoLaboral">Estado Laboral</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="tipo_pago" id="crearTipoPago">
                                            <option value="">Seleccionar...</option>
                                            <option value="MENSUAL">Mensual</option>
                                            <option value="QUINCENAL">Quincenal</option>
                                            <option value="DIARIO">Diario</option>
                                        </select>
                                        <label for="crearTipoPago">Frecuencia Pago</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" class="form-control" name="sueldo_basico" id="crearSueldoBasico" placeholder="0.00">
                                        <label for="crearSueldoBasico">Sueldo Básico</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" class="form-control" name="pago_diario" id="crearPagoDiario" placeholder="0.00">
                                        <label for="crearPagoDiario">Pago Diario (si aplica)</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="regimen_pensionario" id="crearRegimen">
                                            <option value="">Ninguno</option>
                                            <option value="ONP">ONP</option>
                                            <option value="AFP_INTEGRA">AFP Integra</option>
                                            <option value="AFP_PRIMA">AFP Prima</option>
                                            <option value="AFP_PROFUTURO">AFP Profuturo</option>
                                            <option value="AFP_HABITAT">AFP Habitat</option>
                                        </select>
                                        <label for="crearRegimen">Régimen Pensionario</label>
                                    </div>
                                </div>
                                <div class="col-md-4 pt-2">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crearEssalud" name="essalud" value="1">
                                        <label class="form-check-label" for="crearEssalud">Aportante EsSalud</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="crearGuardarBtn">
                        <i class="bi bi-save me-2"></i>Guardar Tercero
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Tercero -->
<div class="modal fade" id="modalEditarTercero" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditarTercero" method="POST" novalidate>
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Roles -->
                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light">
                                <label class="form-label fw-semibold mb-2">Roles <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input role-checkbox" type="checkbox" id="editEsCliente" name="es_cliente" value="1">
                                        <label class="form-check-label" for="editEsCliente">Cliente</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-checkbox" type="checkbox" id="editEsProveedor" name="es_proveedor" value="1">
                                        <label class="form-check-label" for="editEsProveedor">Proveedor</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-checkbox" type="checkbox" id="editEsEmpleado" name="es_empleado" value="1">
                                        <label class="form-check-label" for="editEsEmpleado">Empleado</label>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-none" id="editRolesFeedback">Seleccione al menos un rol.</div>
                            </div>
                        </div>

                        <!-- Tipo Persona / Documento -->
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_persona" id="editTipoPersona" required>
                                    <option value="" selected>Seleccionar...</option>
                                    <option value="NATURAL">Natural</option>
                                    <option value="JURIDICA">Jurídica</option>
                                </select>
                                <label for="editTipoPersona">Tipo de persona <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_documento" id="editTipoDoc" required>
                                    <option value="" selected>Seleccionar...</option>
                                    <option value="DNI">DNI</option>
                                    <option value="RUC">RUC</option>
                                    <option value="CE">CE</option>
                                    <option value="PASAPORTE">Pasaporte</option>
                                </select>
                                <label for="editTipoDoc">Tipo documento <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="numero_documento" id="editNumeroDoc" placeholder="Número" required>
                                <label for="editNumeroDoc">Número <span class="text-danger">*</span></label>
                                <div class="invalid-feedback">Ingrese un número válido.</div>
                            </div>
                        </div>

                        <!-- Nombre / Dirección / Email -->
                        <div class="col-md-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="nombre_completo" id="editNombre" placeholder="Nombre completo" required>
                                <label for="editNombre">Nombre completo / Razón social <span class="text-danger">*</span></label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="direccion" id="editDireccion" placeholder="Dirección">
                                <label for="editDireccion">Dirección</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" name="email" id="editEmail" placeholder="Correo electrónico">
                                <label for="editEmail">Email</label>
                            </div>
                        </div>

                        <!-- Teléfonos -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Teléfonos</label>
                            <div id="editTelefonosList" class="d-flex flex-column gap-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="editAgregarTelefono">
                                <i class="bi bi-plus-circle me-1"></i>Agregar teléfono
                            </button>
                        </div>

                        <!-- Ubigeo -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Ubicación</label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="departamento" id="editDepartamento">
                                            <option value="">Seleccionar departamento...</option>
                                            <?php foreach ($departamentos_list as $dep): ?>
                                                <option value="<?php echo (int) $dep['id']; ?>">
                                                    <?php echo htmlspecialchars($dep['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="editDepartamento">Departamento</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="provincia" id="editProvincia" disabled>
                                            <option value="">Primero seleccione departamento</option>
                                        </select>
                                        <label for="editProvincia">Provincia</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="distrito" id="editDistrito" disabled>
                                            <option value="">Primero seleccione provincia</option>
                                        </select>
                                        <label for="editDistrito">Distrito</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="col-12">
                            <div class="form-floating">
                                <textarea class="form-control" name="observaciones" id="editObservaciones" style="height: 80px;"></textarea>
                                <label for="editObservaciones">Observaciones</label>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" name="estado" id="editEstado">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                    <option value="2">Bloqueado</option>
                                </select>
                                <label for="editEstado">Estado</label>
                            </div>
                        </div>

                        <!-- Cuentas Bancarias -->
                        <div class="col-12" id="editCuentasBancariasSection">
                            <hr class="my-3">
                            <h6 class="fw-bold mb-3">Cuentas Bancarias / Billeteras Digitales</h6>
                            <div id="editCuentasBancariasList" class="d-flex flex-column gap-3"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="editAgregarCuenta">
                                <i class="bi bi-plus-circle me-1"></i>Agregar cuenta / billetera
                            </button>
                        </div>

                        <!-- Campos Comerciales (condicionados por roles cliente/proveedor) -->
                        <div class="col-12 comercial-fields d-none" id="editComercialFields">
                            <hr class="my-3">
                            <h6 class="fw-bold mb-3 text-primary">Datos Comerciales</h6>
                            <div class="row g-3">
                                <div class="col-md-6 border-end">
                                    <h6 class="small text-muted fw-bold mb-2">CLIENTE</h6>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="cliente_dias_credito" id="editClienteDiasCredito" min="0" placeholder="0">
                                                <label for="editClienteDiasCredito">Días Crédito (Cliente)</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="number" step="0.01" class="form-control" name="cliente_limite_credito" id="editClienteLimiteCredito" min="0" placeholder="0.00">
                                                <label for="editClienteLimiteCredito">Límite Crédito (S/)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="small text-muted fw-bold mb-2">PROVEEDOR</h6>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <select class="form-select" name="proveedor_condicion_pago" id="editProvCondicion">
                                                    <option value="">Seleccionar...</option>
                                                    <option value="contado">Contado</option>
                                                    <option value="credito">Crédito</option>
                                                </select>
                                                <label for="editProvCondicion">Condición Pago</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="proveedor_dias_credito" id="editProvDiasCredito" min="0" placeholder="0">
                                                <label for="editProvDiasCredito">Días Crédito (Proveedor)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Campo Legacy -->
                                <div class="col-12 mt-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="condicion_pago" id="editCondicionPago" placeholder="Condición general">
                                        <label for="editCondicionPago">Condición de pago (General)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos Laborales (condicionados por rol empleado) -->
                        <div class="col-12 laboral-fields d-none" id="editLaboralFields">
                            <hr class="my-3">
                            <h6 class="fw-bold mb-3 text-success">Datos Laborales</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="cargo" id="editCargo" placeholder="Ej: Operario">
                                        <label for="editCargo">Cargo <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" name="area" id="editArea" placeholder="Ej: Producción">
                                        <label for="editArea">Área <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" name="fecha_ingreso" id="editFechaIngreso">
                                        <label for="editFechaIngreso">Fecha de ingreso</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="estado_laboral" id="editEstadoLaboral">
                                            <option value="activo">Activo</option>
                                            <option value="cesado">Cesado</option>
                                            <option value="suspendido">Suspendido</option>
                                        </select>
                                        <label for="editEstadoLaboral">Estado Laboral</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="tipo_pago" id="editTipoPago">
                                            <option value="">Seleccionar...</option>
                                            <option value="MENSUAL">Mensual</option>
                                            <option value="QUINCENAL">Quincenal</option>
                                            <option value="DIARIO">Diario</option>
                                        </select>
                                        <label for="editTipoPago">Frecuencia Pago</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" class="form-control" name="sueldo_basico" id="editSueldoBasico" placeholder="0.00">
                                        <label for="editSueldoBasico">Sueldo Básico</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" class="form-control" name="pago_diario" id="editPagoDiario" placeholder="0.00">
                                        <label for="editPagoDiario">Pago Diario (si aplica)</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" name="regimen_pensionario" id="editRegimen">
                                            <option value="">Ninguno</option>
                                            <option value="ONP">ONP</option>
                                            <option value="AFP_INTEGRA">AFP Integra</option>
                                            <option value="AFP_PRIMA">AFP Prima</option>
                                            <option value="AFP_PROFUTURO">AFP Profuturo</option>
                                            <option value="AFP_HABITAT">AFP Habitat</option>
                                        </select>
                                        <label for="editRegimen">Régimen Pensionario</label>
                                    </div>
                                </div>
                                <div class="col-md-4 pt-2">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="editEssalud" name="essalud" value="1">
                                        <label class="form-check-label" for="editEssalud">Aportante EsSalud</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="editGuardarBtn">
                        <i class="bi bi-save me-2"></i>Actualizar Tercero
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('js/terceros.js'); ?>"></script>