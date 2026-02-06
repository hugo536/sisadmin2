
<?php $clientes = $clientes ?? []; ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-person-badge me-2 text-primary"></i> Clientes
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestiona el maestro de clientes registrados.</p>
        </div>
        <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#crearClienteCollapse" aria-expanded="false" aria-controls="crearClienteCollapse">
            <i class="bi bi-person-plus me-2"></i>Nuevo cliente
        </button>
    </div>

    <div class="collapse mb-4" id="crearClienteCollapse">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>Registrar cliente</h6>
            </div>
            <div class="card-body p-4 bg-light">
                <form method="post" class="row g-3" id="formCrearCliente">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-3">
                        <div class="form-floating">
                            <select class="form-select" id="newTipoDoc" name="tipo_documento" required>
                                <option value="" selected>Seleccionar...</option>
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="PASAPORTE">Pasaporte</option>
                            </select>
                            <label for="newTipoDoc">Tipo documento</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newNumero" name="numero_documento" placeholder="Número" required>
                            <label for="newNumero">Número</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newNombre" name="nombre_completo" placeholder="Nombre" required>
                            <label for="newNombre">Nombre completo</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newDireccion" name="direccion" placeholder="Dirección">
                            <label for="newDireccion">Dirección</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newTelefono" name="telefono" placeholder="Teléfono">
                            <label for="newTelefono">Teléfono</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="email" class="form-control" id="newEmail" name="email" placeholder="Correo">
                            <label for="newEmail">Email</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <select class="form-select" id="newEstado" name="estado">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                            <label for="newEstado">Estado</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-circle me-2"></i>Guardar cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="clientesTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Documento</th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr data-estado="<?php echo (int) $cliente['estado']; ?>">
                                <td class="ps-4 fw-semibold"><?php echo e($cliente['tipo_documento']); ?> - <?php echo e($cliente['numero_documento']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e($cliente['nombre_completo']); ?></div>
                                    <div class="small text-muted"><?php echo e($cliente['direccion'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <div><?php echo e($cliente['telefono'] ?? ''); ?></div>
                                    <div class="small text-muted"><?php echo e($cliente['email'] ?? ''); ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ((int) $cliente['estado'] === 1): ?>
                                        <span class="badge-status status-active">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-status status-inactive">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalEditarCliente"
                                            data-id="<?php echo (int) $cliente['id']; ?>"
                                            data-tipo="<?php echo e($cliente['tipo_documento']); ?>"
                                            data-numero="<?php echo e($cliente['numero_documento']); ?>"
                                            data-nombre="<?php echo e($cliente['nombre_completo']); ?>"
                                            data-direccion="<?php echo e($cliente['direccion'] ?? ''); ?>"
                                            data-telefono="<?php echo e($cliente['telefono'] ?? ''); ?>"
                                            data-email="<?php echo e($cliente['email'] ?? ''); ?>"
                                            data-estado="<?php echo (int) $cliente['estado']; ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        <form method="post" class="d-inline m-0">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int) $cliente['id']; ?>">
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
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">Editar cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" id="formEditarCliente" class="row g-3">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editClienteId">
                    <div class="col-12 form-floating">
                        <select class="form-select" id="editTipoDoc" name="tipo_documento" required>
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                            <option value="PASAPORTE">Pasaporte</option>
                        </select>
                        <label for="editTipoDoc">Tipo documento</label>
                    </div>
                    <div class="col-12 form-floating">
                        <input class="form-control" id="editNumero" name="numero_documento" required>
                        <label for="editNumero">Número documento</label>
                    </div>
                    <div class="col-12 form-floating">
                        <input class="form-control" id="editNombre" name="nombre_completo" required>
                        <label for="editNombre">Nombre completo</label>
                    </div>
                    <div class="col-12 form-floating">
                        <input class="form-control" id="editDireccion" name="direccion">
                        <label for="editDireccion">Dirección</label>
                    </div>
                    <div class="col-12 form-floating">
                        <input class="form-control" id="editTelefono" name="telefono">
                        <label for="editTelefono">Teléfono</label>
                    </div>
                    <div class="col-12 form-floating">
                        <input class="form-control" id="editEmail" name="email" type="email">
                        <label for="editEmail">Email</label>
                    </div>
                    <div class="col-12 form-floating">
                        <select class="form-select" id="editEstado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <label for="editEstado">Estado</label>
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
    document.getElementById('modalEditarCliente').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) {
            return;
        }
        document.getElementById('editClienteId').value = button.getAttribute('data-id') || '';
        document.getElementById('editTipoDoc').value = button.getAttribute('data-tipo') || 'DNI';
        document.getElementById('editNumero').value = button.getAttribute('data-numero') || '';
        document.getElementById('editNombre').value = button.getAttribute('data-nombre') || '';
        document.getElementById('editDireccion').value = button.getAttribute('data-direccion') || '';
        document.getElementById('editTelefono').value = button.getAttribute('data-telefono') || '';
        document.getElementById('editEmail').value = button.getAttribute('data-email') || '';
        document.getElementById('editEstado').value = button.getAttribute('data-estado') || '1';
    });
</script>