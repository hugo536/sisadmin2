<?php $terceros = $terceros ?? []; ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-people-fill me-2 text-primary"></i> Terceros
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión unificada de clientes, proveedores y empleados.</p>
        </div>
        <button class="btn btn-primary shadow-sm" type="button" onclick="abrirModalCrearTercero()">
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
                            $rolesTexto = implode(', ', $roles);
                            $rolesFiltro = implode('|', $roles);
                        ?>
                        <tr data-estado="<?php echo (int) $tercero['estado']; ?>"
                            data-roles="<?php echo e($rolesFiltro); ?>"
                            data-search="<?php echo e(mb_strtolower($tercero['tipo_documento'].' '.$tercero['numero_documento'].' '.$tercero['nombre_completo'].' '.($tercero['direccion'] ?? '').' '.($tercero['telefono'] ?? '').' '.($tercero['email'] ?? ''))); ?>">
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
                                <div><?php echo e($tercero['telefono'] ?? ''); ?></div>
                                <div class="small text-muted"><?php echo e($tercero['email'] ?? ''); ?></div>
                            </td>
                            <td class="text-center" data-label="Estado">
                                <?php if ((int) $tercero['estado'] === 1): ?>
                                    <span class="badge-status status-active" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Activo</span>
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
                                            data-telefono="<?php echo e($tercero['telefono'] ?? ''); ?>"
                                            data-email="<?php echo e($tercero['email'] ?? ''); ?>"
                                            data-condicion-pago="<?php echo e($tercero['condicion_pago'] ?? ''); ?>"
                                            data-dias-credito="<?php echo e((string) ($tercero['dias_credito'] ?? '')); ?>"
                                            data-limite-credito="<?php echo e((string) ($tercero['limite_credito'] ?? '')); ?>"
                                            data-cargo="<?php echo e($tercero['cargo'] ?? ''); ?>"
                                            data-area="<?php echo e($tercero['area'] ?? ''); ?>"
                                            data-fecha-ingreso="<?php echo e($tercero['fecha_ingreso'] ?? ''); ?>"
                                            data-estado-laboral="<?php echo e($tercero['estado_laboral'] ?? ''); ?>"
                                            data-estado="<?php echo (int) $tercero['estado']; ?>"
                                            data-es-cliente="<?php echo (int) $tercero['es_cliente']; ?>"
                                            data-es-proveedor="<?php echo (int) $tercero['es_proveedor']; ?>"
                                            data-es-empleado="<?php echo (int) $tercero['es_empleado']; ?>"
                                            title="Editar">
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>
                                    <form method="post" class="delete-form d-inline m-0">
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
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-plus me-2"></i>Nuevo Tercero
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="formCrearTercero" method="POST">
                <input type="hidden" name="accion" value="crear">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_persona" id="crearTipoPersona" required>
                                    <option value="NATURAL">Natural</option>
                                    <option value="JURIDICA">Jurídica</option>
                                </select>
                                <label for="crearTipoPersona">Tipo de persona</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" name="tipo_documento" id="crearTipoDoc" required>
                                    <option value="" selected>Seleccionar...</option>
                                    <option value="DNI">DNI</option>
                                    <option value="RUC">RUC</option>
                                    <option value="PASAPORTE">Pasaporte</option>
                                </select>
                                <label for="crearTipoDoc">Tipo documento</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="numero_documento" id="crearNumeroDoc" placeholder="Número" required>
                                <label for="crearNumeroDoc">Número</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="nombre_completo" id="crearNombre" placeholder="Nombre" required>
                                <label for="crearNombre">Nombre completo</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" name="estado" id="crearEstado">
                                    <option value="1" selected>Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                                <label for="crearEstado">Estado</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="direccion" id="crearDireccion" placeholder="Dirección">
                                <label for="crearDireccion">Dirección</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="telefono" id="crearTelefono" placeholder="Teléfono">
                                <label for="crearTelefono">Teléfono</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="email" class="form-control" name="email" id="crearEmail" placeholder="Correo">
                                <label for="crearEmail">Email</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Roles</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="crearEsCliente" name="es_cliente" value="1">
                                    <label class="form-check-label" for="crearEsCliente">Cliente</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="crearEsProveedor" name="es_proveedor" value="1">
                                    <label class="form-check-label" for="crearEsProveedor">Proveedor</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="crearEsEmpleado" name="es_empleado" value="1">
                                    <label class="form-check-label" for="crearEsEmpleado">Empleado</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr class="my-2">
                            <h6 class="fw-bold">Datos Comerciales</h6>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="condicion_pago" id="crearCondicionPago" placeholder="Condición de pago">
                                <label for="crearCondicionPago">Condición de pago</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="number" class="form-control" name="dias_credito" id="crearDiasCredito" placeholder="Días de crédito" value="0">
                                <label for="crearDiasCredito">Días de crédito</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="number" step="0.0001" class="form-control" name="limite_credito" id="crearLimiteCredito" placeholder="Límite de crédito" value="0.0000">
                                <label for="crearLimiteCredito">Límite de crédito</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr class="my-2">
                            <h6 class="fw-bold">Datos Laborales</h6>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="cargo" id="crearCargo" placeholder="Cargo">
                                <label for="crearCargo">Cargo</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="area" id="crearArea" placeholder="Área">
                                <label for="crearArea">Área</label>
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
                                <input type="text" class="form-control" name="estado_laboral" id="crearEstadoLaboral" placeholder="Estado laboral">
                                <label for="crearEstadoLaboral">Estado laboral</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-save me-2"></i>Guardar Tercero
                    </button>
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
                <form method="post" id="formEditarTercero" class="row g-3">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editTerceroId">
                    <div class="col-md-4 form-floating">
                        <select class="form-select" name="tipo_persona" id="editTipoPersona" required>
                            <option value="NATURAL">Natural</option>
                            <option value="JURIDICA">Jurídica</option>
                        </select>
                        <label for="editTipoPersona">Tipo de persona</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editTipoDoc" name="tipo_documento" required>
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                            <option value="PASAPORTE">Pasaporte</option>
                        </select>
                        <label for="editTipoDoc">Tipo documento</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editNumeroDoc" name="numero_documento" required>
                        <label for="editNumeroDoc">Número</label>
                    </div>
                    <div class="col-md-8 form-floating">
                        <input class="form-control" id="editNombre" name="nombre_completo" required>
                        <label for="editNombre">Nombre completo</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editEstado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <label for="editEstado">Estado</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input class="form-control" id="editDireccion" name="direccion">
                        <label for="editDireccion">Dirección</label>
                    </div>
                    <div class="col-md-3 form-floating">
                        <input class="form-control" id="editTelefono" name="telefono">
                        <label for="editTelefono">Teléfono</label>
                    </div>
                    <div class="col-md-3 form-floating">
                        <input class="form-control" id="editEmail" name="email" type="email">
                        <label for="editEmail">Email</label>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Roles</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editEsCliente" name="es_cliente" value="1">
                                <label class="form-check-label" for="editEsCliente">Cliente</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editEsProveedor" name="es_proveedor" value="1">
                                <label class="form-check-label" for="editEsProveedor">Proveedor</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="editEsEmpleado" name="es_empleado" value="1">
                                <label class="form-check-label" for="editEsEmpleado">Empleado</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr class="my-2">
                        <h6 class="fw-bold">Datos Comerciales</h6>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editCondicionPago" name="condicion_pago">
                        <label for="editCondicionPago">Condición de pago</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editDiasCredito" name="dias_credito" type="number">
                        <label for="editDiasCredito">Días de crédito</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editLimiteCredito" name="limite_credito" type="number" step="0.0001">
                        <label for="editLimiteCredito">Límite de crédito</label>
                    </div>
                    <div class="col-12">
                        <hr class="my-2">
                        <h6 class="fw-bold">Datos Laborales</h6>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editCargo" name="cargo">
                        <label for="editCargo">Cargo</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editArea" name="area">
                        <label for="editArea">Área</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editFechaIngreso" name="fecha_ingreso" type="date">
                        <label for="editFechaIngreso">Fecha de ingreso</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editEstadoLaboral" name="estado_laboral">
                        <label for="editEstadoLaboral">Estado laboral</label>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-2"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function abrirModalCrearTercero() {
        const modal = new bootstrap.Modal(document.getElementById('modalCrearTercero'));
        modal.show();
    }

    document.getElementById('modalEditarTercero').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) {
            return;
        }
        document.getElementById('editTerceroId').value = button.getAttribute('data-id') || '';
        document.getElementById('editTipoPersona').value = button.getAttribute('data-tipo-persona') || 'NATURAL';
        document.getElementById('editTipoDoc').value = button.getAttribute('data-tipo-doc') || 'DNI';
        document.getElementById('editNumeroDoc').value = button.getAttribute('data-numero-doc') || '';
        document.getElementById('editNombre').value = button.getAttribute('data-nombre') || '';
        document.getElementById('editDireccion').value = button.getAttribute('data-direccion') || '';
        document.getElementById('editTelefono').value = button.getAttribute('data-telefono') || '';
        document.getElementById('editEmail').value = button.getAttribute('data-email') || '';
        document.getElementById('editCondicionPago').value = button.getAttribute('data-condicion-pago') || '';
        document.getElementById('editDiasCredito').value = button.getAttribute('data-dias-credito') || '0';
        document.getElementById('editLimiteCredito').value = button.getAttribute('data-limite-credito') || '0.0000';
        document.getElementById('editCargo').value = button.getAttribute('data-cargo') || '';
        document.getElementById('editArea').value = button.getAttribute('data-area') || '';
        document.getElementById('editFechaIngreso').value = button.getAttribute('data-fecha-ingreso') || '';
        document.getElementById('editEstadoLaboral').value = button.getAttribute('data-estado-laboral') || '';
        document.getElementById('editEstado').value = button.getAttribute('data-estado') || '1';
        document.getElementById('editEsCliente').checked = (button.getAttribute('data-es-cliente') || '0') === '1';
        document.getElementById('editEsProveedor').checked = (button.getAttribute('data-es-proveedor') || '0') === '1';
        document.getElementById('editEsEmpleado').checked = (button.getAttribute('data-es-empleado') || '0') === '1';
    });
</script>
