<?php 
    $terceros = $terceros ?? []; 
    $departamentos_list = $departamentos_list ?? []; 
    $cargos_list = $cargos_list ?? [];
    $areas_list = $areas_list ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-people-fill me-2 text-primary"></i> Terceros
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión unificada de clientes, proveedores y empleados.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionCargos">
                <i class="bi bi-briefcase me-2 text-warning"></i>Cargos
            </button>
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionAreas">
                <i class="bi bi-building me-2 text-info"></i>Áreas
            </button>
            <div class="vr mx-2"></div>
            <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearTercero">
                <i class="bi bi-person-plus-fill me-2"></i>Nuevo Tercero
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="terceroSearch" placeholder="Buscar por documento, nombre...">
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
                                if ((int) $tercero['es_distribuidor'] === 1) $roles[] = 'DISTRIBUIDOR';
                                $rolesFiltro = implode('|', $roles);
                                $estadoBinario = ((int) ($tercero['estado'] ?? 0) === 1) ? 1 : 0;
                            ?>
                            <tr data-estado="<?php echo $estadoBinario; ?>"
                                data-roles="<?php echo htmlspecialchars($rolesFiltro); ?>"
                                data-search="<?php echo htmlspecialchars(mb_strtolower($tercero['nombre_completo'].' '.$tercero['numero_documento'])); ?>">
                                
                                <td class="ps-4 fw-semibold text-primary">
                                    <?php echo htmlspecialchars($tercero['tipo_documento']); ?> <br>
                                    <span class="text-dark small"><?php echo htmlspecialchars($tercero['numero_documento']); ?></span>
                                </td>
                                
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($tercero['nombre_completo']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($tercero['direccion'] ?? ''); ?></div>
                                </td>
                                
                                <td>
                                    <?php foreach ($roles as $rol): ?>
                                        <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($rol); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                
                                <td>
                                    <div><?php echo htmlspecialchars($tercero['telefono'] ?? ''); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($tercero['email'] ?? ''); ?></div>
                                </td>
                                
                                <td class="text-center">
                                    <?php if ($estadoBinario === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 rounded-pill">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light text-primary border-0 bg-transparent js-editar-tercero" 
                                            data-id="<?php echo (int) $tercero['id']; ?>"
                                            
                                            /* Identificación */
                                            data-nombre="<?php echo htmlspecialchars($tercero['nombre_completo']); ?>"
                                            data-tipo-doc="<?php echo htmlspecialchars($tercero['tipo_documento']); ?>"
                                            data-numero-doc="<?php echo htmlspecialchars($tercero['numero_documento']); ?>"
                                            data-tipo-persona="<?php echo htmlspecialchars($tercero['tipo_persona']); ?>"
                                            data-representante-legal="<?php echo htmlspecialchars($tercero['representante_legal'] ?? ''); ?>"

                                            /* Contacto */
                                            data-direccion="<?php echo htmlspecialchars($tercero['direccion'] ?? ''); ?>"
                                            data-email="<?php echo htmlspecialchars($tercero['email'] ?? ''); ?>"
                                            data-telefonos='<?php echo htmlspecialchars(json_encode($tercero['telefonos'] ?? [], JSON_UNESCAPED_UNICODE)); ?>'
                                            
                                            /* Ubigeo */
                                            data-departamento="<?php echo (int)($tercero['departamento_id'] ?? 0); ?>"
                                            data-provincia="<?php echo (int)($tercero['provincia_id'] ?? 0); ?>"
                                            data-distrito="<?php echo (int)($tercero['distrito_id'] ?? 0); ?>"

                                            /* Roles */
                                            data-es-cliente="<?php echo (int)$tercero['es_cliente']; ?>"
                                            data-es-proveedor="<?php echo (int)$tercero['es_proveedor']; ?>"
                                            data-es-empleado="<?php echo (int)$tercero['es_empleado']; ?>"
                                            data-es-distribuidor="<?php echo (int)$tercero['es_distribuidor']; ?>"
                                            
                                            /* Financiero */
                                            data-cuentas-bancarias='<?php echo htmlspecialchars(json_encode($tercero['cuentas_bancarias'] ?? [], JSON_UNESCAPED_UNICODE)); ?>'

                                            title="Editar"><i class="bi bi-pencil-square fs-5"></i></button>
                                    
                                    <button class="btn btn-sm btn-light text-danger border-0 bg-transparent js-eliminar-tercero" 
                                            data-id="<?php echo (int) $tercero['id']; ?>"
                                            title="Eliminar"><i class="bi bi-trash fs-5"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="tercerosPaginationInfo">Mostrando registros</small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end" id="tercerosPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearTercero" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Nuevo Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCrearTercero" method="POST" novalidate>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body p-4 bg-light">
                    
                    <ul class="nav nav-tabs mb-3" id="crearTerceroTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="crear-tab-identificacion" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-identificacion" type="button">Identificación</button></li>
                        <li class="nav-item"><button class="nav-link" id="crear-tab-contacto" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-contacto" type="button">Contacto</button></li>
                        <li class="nav-item"><button class="nav-link" id="crear-tab-financiero" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-financiero" type="button">Financiero</button></li>
                        
                        <li class="nav-item d-none" id="crear-tab-header-cliente"><button class="nav-link text-success fw-semibold" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-cliente" type="button">Datos Cliente</button></li>
                        <li class="nav-item d-none" id="crear-tab-header-distribuidor"><button class="nav-link text-primary fw-semibold" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-distribuidor" type="button">Zonas Distribuidor</button></li>
                        <li class="nav-item d-none" id="crear-tab-header-proveedor"><button class="nav-link text-warning fw-semibold" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-proveedor" type="button">Datos Proveedor</button></li>
                        <li class="nav-item d-none" id="crear-tab-header-empleado"><button class="nav-link text-info fw-semibold" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-empleado" type="button">Datos Empleado</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="crear-tab-pane-identificacion" tabindex="0">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <label class="form-label fw-bold text-primary mb-3">Roles (Seleccione para habilitar pestañas)</label>
                                    <div class="d-flex flex-wrap gap-4 mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="crearEsCliente" name="es_cliente" value="1" data-target="crear-tab-header-cliente">
                                            <label class="form-check-label fw-semibold" for="crearEsCliente">Cliente</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="crearEsDistribuidor" name="es_distribuidor" value="1" data-target="crear-tab-header-distribuidor">
                                            <label class="form-check-label fw-semibold" for="crearEsDistribuidor">Distribuidor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="crearEsProveedor" name="es_proveedor" value="1" data-target="crear-tab-header-proveedor">
                                            <label class="form-check-label fw-semibold" for="crearEsProveedor">Proveedor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="crearEsEmpleado" name="es_empleado" value="1" data-target="crear-tab-header-empleado">
                                            <label class="form-check-label fw-semibold" for="crearEsEmpleado">Empleado</label>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="form-floating">
                                                <select class="form-select" name="tipo_persona" id="crearTipoPersona" required>
                                                    <option value="NATURAL">Natural</option>
                                                    <option value="JURIDICA">Jurídica</option>
                                                </select>
                                                <label>Tipo Persona <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating">
                                                <select class="form-select" name="tipo_documento" id="crearTipoDoc" required>
                                                    <option value="DNI">DNI</option>
                                                    <option value="RUC">RUC</option>
                                                    <option value="CE">CE</option>
                                                </select>
                                                <label>Tipo Doc. <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="numero_documento" id="crearNumeroDoc" placeholder="Número" required>
                                                <label>Número Documento <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="nombre_completo" id="crearNombre" placeholder="Razón Social" required>
                                                <label id="crearNombreLabel">Razón Social / Nombre Completo <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-12 d-none" id="crearRepresentanteLegalSection">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="representante_legal" id="crearRepresentanteLegal" placeholder="Representante">
                                                <label>Representante Legal</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <select class="form-select" name="estado" id="crearEstado">
                                                    <option value="1">Activo</option>
                                                    <option value="0">Inactivo</option>
                                                </select>
                                                <label>Estado</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="crear-tab-pane-contacto" tabindex="0">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="direccion" id="crearDireccion" placeholder="Dirección">
                                                <label>Dirección Fiscal</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <input type="email" class="form-control" name="email" id="crearEmail" placeholder="Email">
                                                <label>Correo Electrónico</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <select class="form-select" name="departamento_id" id="crearDepartamento">
                                                    <option value="">Seleccione...</option>
                                                    <?php foreach ($departamentos_list as $dep): ?>
                                                        <option value="<?php echo (int)$dep['id']; ?>"><?php echo htmlspecialchars($dep['nombre']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Departamento</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <select class="form-select" name="provincia_id" id="crearProvincia" disabled><option value="">Seleccione...</option></select>
                                                <label>Provincia</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <select class="form-select" name="distrito_id" id="crearDistrito" disabled><option value="">Seleccione...</option></select>
                                                <label>Distrito</label>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <h6 class="fw-bold text-muted border-bottom pb-2">Teléfonos</h6>
                                            <div id="crearTelefonosList"></div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="crearAgregarTelefono"><i class="bi bi-plus-lg"></i> Agregar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="crear-tab-pane-financiero" tabindex="0">
                            <div class="card border-0 shadow-sm"><div class="card-body">
                                <div id="crearCuentasBancariasList"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-3" id="crearAgregarCuenta">Agregar Cuenta Bancaria</button>
                            </div></div>
                        </div>

                        <div class="tab-pane fade" id="crear-tab-pane-cliente" tabindex="0">
                            <div class="card border-0 shadow-sm bg-success-subtle"><div class="card-body">
                                <?php $prefix = 'crear'; require __DIR__ . '/terceros/clientes_form.php'; ?>
                            </div></div>
                        </div>
                        <div class="tab-pane fade" id="crear-tab-pane-distribuidor" tabindex="0">
                            <div class="card border-0 shadow-sm bg-light-subtle"><div class="card-body">
                                <?php $prefix = 'crear'; require __DIR__ . '/terceros/distribuidores_form.php'; ?>
                            </div></div>
                        </div>
                        <div class="tab-pane fade" id="crear-tab-pane-proveedor" tabindex="0">
                            <div class="card border-0 shadow-sm bg-warning-subtle"><div class="card-body">
                                <?php $prefix = 'crear'; require __DIR__ . '/terceros/proveedores_form.php'; ?>
                            </div></div>
                        </div>
                        <div class="tab-pane fade" id="crear-tab-pane-empleado" tabindex="0">
                            <div class="card border-0 shadow-sm bg-info-subtle"><div class="card-body">
                                <?php $prefix = 'crear'; require __DIR__ . '/terceros/empleados_form.php'; ?>
                            </div></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="crearGuardarBtn">Guardar Tercero</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarTercero" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTercero" method="POST" novalidate>
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body p-4 bg-light">
                    
                    <ul class="nav nav-tabs mb-3" id="editTerceroTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="edit-tab-identificacion" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-identificacion" type="button">Identificación</button></li>
                        <li class="nav-item"><button class="nav-link" id="edit-tab-contacto" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-contacto" type="button">Contacto</button></li>
                        <li class="nav-item"><button class="nav-link" id="edit-tab-financiero" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-financiero" type="button">Financiero</button></li>
                        
                        <li class="nav-item d-none" id="edit-tab-header-cliente"><button class="nav-link text-success fw-semibold" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-cliente" type="button">Datos Cliente</button></li>
                        <li class="nav-item d-none" id="edit-tab-header-distribuidor"><button class="nav-link text-primary fw-semibold" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-distribuidor" type="button">Zonas Distribuidor</button></li>
                        <li class="nav-item d-none" id="edit-tab-header-proveedor"><button class="nav-link text-warning fw-semibold" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-proveedor" type="button">Datos Proveedor</button></li>
                        <li class="nav-item d-none" id="edit-tab-header-empleado"><button class="nav-link text-info fw-semibold" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-empleado" type="button">Datos Empleado</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="edit-tab-pane-identificacion" tabindex="0">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <label class="form-label fw-bold text-primary mb-3">Roles</label>
                                    <div class="d-flex flex-wrap gap-4 mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="editEsCliente" name="es_cliente" value="1" data-target="edit-tab-header-cliente">
                                            <label class="form-check-label fw-semibold" for="editEsCliente">Cliente</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="editEsDistribuidor" name="es_distribuidor" value="1" data-target="edit-tab-header-distribuidor">
                                            <label class="form-check-label fw-semibold" for="editEsDistribuidor">Distribuidor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="editEsProveedor" name="es_proveedor" value="1" data-target="edit-tab-header-proveedor">
                                            <label class="form-check-label fw-semibold" for="editEsProveedor">Proveedor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger" type="checkbox" id="editEsEmpleado" name="es_empleado" value="1" data-target="edit-tab-header-empleado">
                                            <label class="form-check-label fw-semibold" for="editEsEmpleado">Empleado</label>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="form-floating">
                                                <select class="form-select" name="tipo_persona" id="editTipoPersona" required>
                                                    <option value="NATURAL">Natural</option>
                                                    <option value="JURIDICA">Jurídica</option>
                                                </select>
                                                <label>Tipo Persona</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating">
                                                <select class="form-select" name="tipo_documento" id="editTipoDoc" required>
                                                    <option value="DNI">DNI</option>
                                                    <option value="RUC">RUC</option>
                                                    <option value="CE">CE</option>
                                                </select>
                                                <label>Tipo Doc.</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="numero_documento" id="editNumeroDoc" required>
                                                <label>Número Documento</label>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="nombre_completo" id="editNombre" required>
                                                <label id="editNombreLabel">Razón Social / Nombre</label>
                                            </div>
                                        </div>
                                        <div class="col-md-12 d-none" id="editRepresentanteLegalSection">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="representante_legal" id="editRepresentanteLegal">
                                                <label>Representante Legal</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <select class="form-select" name="estado" id="editEstado">
                                                    <option value="1">Activo</option>
                                                    <option value="0">Inactivo</option>
                                                </select>
                                                <label>Estado</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="edit-tab-pane-contacto" tabindex="0">
                            <div class="card border-0 shadow-sm"><div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8"><div class="form-floating"><input type="text" class="form-control" name="direccion" id="editDireccion"><label>Dirección</label></div></div>
                                    <div class="col-md-4"><div class="form-floating"><input type="email" class="form-control" name="email" id="editEmail"><label>Email</label></div></div>
                                    
                                    <div class="col-md-4"><div class="form-floating"><select class="form-select" name="departamento_id" id="editDepartamento"><option value="">Seleccione...</option><?php foreach ($departamentos_list as $dep): ?><option value="<?php echo (int)$dep['id']; ?>"><?php echo htmlspecialchars($dep['nombre']); ?></option><?php endforeach; ?></select><label>Departamento</label></div></div>
                                    <div class="col-md-4"><div class="form-floating"><select class="form-select" name="provincia_id" id="editProvincia" disabled><option value="">Seleccione...</option></select><label>Provincia</label></div></div>
                                    <div class="col-md-4"><div class="form-floating"><select class="form-select" name="distrito_id" id="editDistrito" disabled><option value="">Seleccione...</option></select><label>Distrito</label></div></div>

                                    <div class="col-12 mt-3"><h6 class="fw-bold text-muted">Teléfonos</h6><div id="editTelefonosList"></div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="editAgregarTelefono">Agregar</button></div>
                                </div>
                            </div></div>
                        </div>

                        <div class="tab-pane fade" id="edit-tab-pane-financiero" tabindex="0">
                            <div class="card border-0 shadow-sm"><div class="card-body">
                                <div id="editCuentasBancariasList"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-3" id="editAgregarCuenta">Agregar Cuenta</button>
                            </div></div>
                        </div>

                        <div class="tab-pane fade" id="edit-tab-pane-cliente" tabindex="0">
                            <div class="card border-0 shadow-sm bg-success-subtle"><div class="card-body">
                                <?php $prefix = 'edit'; require __DIR__ . '/terceros/clientes_form.php'; ?>
                            </div></div>
                        </div>
                        <div class="tab-pane fade" id="edit-tab-pane-distribuidor" tabindex="0">
                            <div class="card border-0 shadow-sm bg-light-subtle"><div class="card-body">
                                <?php $prefix = 'edit'; require __DIR__ . '/terceros/distribuidores_form.php'; ?>
                            </div></div>
                        </div>
                        <div class="tab-pane fade" id="edit-tab-pane-proveedor" tabindex="0">
                            <div class="card border-0 shadow-sm bg-warning-subtle"><div class="card-body">
                                <?php $prefix = 'edit'; require __DIR__ . '/terceros/proveedores_form.php'; ?>
                            </div></div>
                        </div>
                        <div class="tab-pane fade" id="edit-tab-pane-empleado" tabindex="0">
                            <div class="card border-0 shadow-sm bg-info-subtle"><div class="card-body">
                                <?php $prefix = 'edit'; require __DIR__ . '/terceros/empleados_form.php'; ?>
                            </div></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Actualizar Tercero</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modales_maestros.php'; ?>

<script src="<?php echo asset_url('js/terceros/clientes.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros/proveedores.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros/empleados.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros/distribuidores.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros.js'); ?>"></script>