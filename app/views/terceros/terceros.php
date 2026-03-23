<?php 
    $terceros = $terceros ?? []; 
    $departamentos_list = $departamentos_list ?? []; 
    $cargos_list = $cargos_list ?? [];
    $areas_list = $areas_list ?? [];
    $centros_costo_list = $centros_costo_list ?? [];
?>
<div class="container-fluid p-4" id="tercerosApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in terceros-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-people-fill me-2 text-primary"></i> Terceros
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión unificada de clientes, proveedores y empleados.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionCargos">
                <i class="bi bi-briefcase me-2 text-warning"></i>Cargos
            </button>
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionAreas">
                <i class="bi bi-building me-2 text-info"></i>Áreas
            </button>
            <div class="vr mx-2 d-none d-sm-block"></div>
            <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearTercero" id="btnNuevoTercero">
                <i class="bi bi-person-plus-fill me-2"></i>Nuevo Tercero
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 terceros-sticky-filters">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" id="terceroSearch" placeholder="Buscar por documento, nombre...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="terceroFiltroRol">
                        <option value="">Todos los roles</option>
                        <option value="CLIENTE">Cliente</option>
                        <option value="PROVEEDOR">Proveedor</option>
                        <option value="EMPLEADO">Empleado</option>
                        <option value="DISTRIBUIDOR">Distribuidor</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="terceroFiltroEstado">
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
            <div class="table-responsive terceros-table-wrapper">
                
                <table class="table align-middle mb-0 table-pro table-hover" id="tercerosTable"
                       data-erp-table="true"
                       data-rows-selector="#tercerosTableBody tr:not(.empty-msg-row)"
                       data-search-input="#terceroSearch"
                       data-empty-text="No se encontraron terceros"
                       data-info-text-template="Mostrando {start} a {end} de {total} terceros"
                       data-erp-filters='[{"el":"#terceroFiltroRol","attr":"data-roles","match":"includes"},{"el":"#terceroFiltroEstado","attr":"data-estado","match":"equals"}]'
                       data-pagination-controls="#tercerosPaginationControls"
                       data-pagination-info="#tercerosPaginationInfo"
                       data-rows-per-page="15">
                    <thead class="terceros-sticky-thead table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Documento</th>
                            <th class="text-secondary fw-semibold">Tercero / Razón Social</th>
                            <th class="text-secondary fw-semibold">Roles Registrados</th>
                            <th class="text-secondary fw-semibold">Datos de Contacto</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tercerosTableBody">
                        <?php if (empty($terceros)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-light"></i>
                                    No hay terceros registrados en el sistema.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($terceros as $tercero): ?>
                                <?php
                                    $roles = [];
                                    if ((int) $tercero['es_cliente'] === 1) $roles[] = 'CLIENTE';
                                    if ((int) $tercero['es_proveedor'] === 1) $roles[] = 'PROVEEDOR';
                                    if ((int) $tercero['es_empleado'] === 1) $roles[] = 'EMPLEADO';
                                    if ((int) $tercero['es_distribuidor'] === 1) $roles[] = 'DISTRIBUIDOR';
                                    $rolesFiltro = implode(' ', $roles); // Con espacio o coma, ¡ahora funcionará de ambas formas!
                                    $estadoBinario = ((int) ($tercero['estado'] ?? 0) === 1) ? 1 : 0;
                                    
                                    $cuentasBancariasParaJs = array_map(function($cta) {
                                        return [
                                            'config_banco_id' => $cta['config_banco_id'] ?? null,
                                            'tipo_entidad' => $cta['tipo_entidad'] ?? null,
                                            'entidad' => $cta['entidad'] ?? '',
                                            'tipo_cuenta' => $cta['tipo_cuenta'] ?? '',
                                            'numero_cuenta' => $cta['numero_cuenta'] ?? '',
                                            'cci' => $cta['cci'] ?? '',
                                            'titular' => $cta['titular'] ?? '',
                                            'moneda' => $cta['moneda'] ?? 'PEN',
                                            'principal' => $cta['principal'] ?? 0,
                                            'billetera_digital' => $cta['billetera_digital'] ?? 0,
                                            'observaciones' => $cta['observaciones'] ?? ''
                                        ];
                                    }, $tercero['cuentas_bancarias'] ?? []);
                                ?>
                                <tr class="border-bottom" 
                                    data-estado="<?php echo $estadoBinario; ?>"
                                    data-roles="<?php echo htmlspecialchars($rolesFiltro); ?>"
                                    data-search="<?php echo htmlspecialchars(mb_strtolower((string) ($tercero['nombre_completo'] ?? '').' '.(string) ($tercero['numero_documento'] ?? ''))); ?>">
                                    
                                    <td class="ps-4 fw-semibold text-primary align-top pt-3">
                                        <span class="badge bg-light text-secondary border border-secondary-subtle mb-1"><?php echo htmlspecialchars((string) ($tercero['tipo_documento'] ?? '')); ?></span><br>
                                        <span class="text-dark fw-bold"><?php echo htmlspecialchars((string) ($tercero['numero_documento'] ?? '')); ?></span>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars((string) ($tercero['nombre_completo'] ?? '')); ?></div>
                                        <?php if (!empty($tercero['direccion'])): ?>
                                            <div class="small text-muted text-truncate mt-1" style="max-width: 250px;" title="<?php echo htmlspecialchars($tercero['direccion']); ?>">
                                                <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($tercero['direccion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($roles as $rol): ?>
                                                <?php 
                                                    $rolClass = 'bg-light text-dark';
                                                    if ($rol === 'CLIENTE') $rolClass = 'bg-success-subtle text-success';
                                                    elseif ($rol === 'PROVEEDOR') $rolClass = 'bg-warning-subtle text-warning-emphasis';
                                                    elseif ($rol === 'EMPLEADO') $rolClass = 'bg-info-subtle text-info-emphasis';
                                                    elseif ($rol === 'DISTRIBUIDOR') $rolClass = 'bg-primary-subtle text-primary';
                                                ?>
                                                <span class="badge <?php echo $rolClass; ?> border px-2 shadow-sm" style="font-size: 0.65rem;"><?php echo htmlspecialchars($rol); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <?php if (!empty($tercero['telefono'])): ?>
                                            <div class="fw-medium text-dark small mb-1"><i class="bi bi-telephone text-muted me-2"></i><?php echo htmlspecialchars($tercero['telefono']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($tercero['email'])): ?>
                                            <div class="text-muted small"><i class="bi bi-envelope text-muted me-2"></i><?php echo htmlspecialchars($tercero['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <?php if ($estadoBinario === 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4 align-top pt-3">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <div class="form-check form-switch pt-1 me-1" title="Cambiar estado" data-bs-toggle="tooltip">
                                                <input class="form-check-input switch-estado-tercero shadow-none" type="checkbox" role="switch"
                                                       style="cursor: pointer; width: 2.2em; height: 1.1em;"
                                                       data-id="<?php echo (int) $tercero['id']; ?>"
                                                       <?php echo $estadoBinario === 1 ? 'checked' : ''; ?>>
                                            </div>

                                            <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                            <a href="?ruta=terceros/perfil&id=<?php echo (int) $tercero['id']; ?>" class="btn btn-sm btn-light text-info border-0 rounded-circle shadow-sm" data-bs-toggle="tooltip" title="Ver Perfil Detallado">
                                                <i class="bi bi-person-vcard fs-6"></i>
                                            </a>

                                            <button class="btn btn-sm btn-light text-primary border-0 rounded-circle shadow-sm js-editar-tercero"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEditarTercero"
                                                    data-bs-toggle="tooltip" title="Editar Tercero"
                                                    data-id="<?php echo (int) $tercero['id']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars((string) ($tercero['nombre_completo'] ?? '')); ?>"
                                                    data-tipo-doc="<?php echo htmlspecialchars((string) ($tercero['tipo_documento'] ?? '')); ?>"
                                                    data-numero-doc="<?php echo htmlspecialchars((string) ($tercero['numero_documento'] ?? '')); ?>"
                                                    data-tipo-persona="<?php echo htmlspecialchars($tercero['tipo_persona']); ?>"
                                                    data-estado="<?php echo (int) ($tercero['estado'] ?? 1); ?>"
                                                    data-representante-legal="<?php echo htmlspecialchars($tercero['representante_legal'] ?? ''); ?>"
                                                    data-direccion="<?php echo htmlspecialchars($tercero['direccion'] ?? ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($tercero['email'] ?? ''); ?>"
                                                    data-telefonos='<?php echo e(json_encode($tercero['telefonos'] ?? [], JSON_UNESCAPED_UNICODE)); ?>'
                                                    data-departamento="<?php echo (int)($tercero['departamento_id'] ?? 0); ?>"
                                                    data-provincia="<?php echo (int)($tercero['provincia_id'] ?? 0); ?>"
                                                    data-distrito="<?php echo (int)($tercero['distrito_id'] ?? 0); ?>"
                                                    data-es-cliente="<?php echo (int)$tercero['es_cliente']; ?>"
                                                    data-es-proveedor="<?php echo (int)$tercero['es_proveedor']; ?>"
                                                    data-es-empleado="<?php echo (int)$tercero['es_empleado']; ?>"
                                                    data-empleado-registrado="<?php echo (int)$tercero['es_empleado']; ?>"
                                                    data-es-distribuidor="<?php echo (int)$tercero['es_distribuidor']; ?>"
                                                    data-cargo="<?php echo htmlspecialchars((string) ($tercero['cargo'] ?? '')); ?>"
                                                    data-area="<?php echo htmlspecialchars((string) ($tercero['area'] ?? '')); ?>"
                                                    data-id-centro-costo="<?php echo (int) ($tercero['id_centro_costo'] ?? 0); ?>"
                                                    data-codigo-biometrico="<?php echo htmlspecialchars((string) ($tercero['codigo_biometrico'] ?? '')); ?>"
                                                    data-fecha-ingreso="<?php echo htmlspecialchars((string) ($tercero['fecha_ingreso'] ?? '')); ?>"
                                                    data-fecha-cese="<?php echo htmlspecialchars((string) ($tercero['fecha_cese'] ?? '')); ?>"
                                                    data-estado-laboral="<?php echo htmlspecialchars((string) ($tercero['estado_laboral'] ?? 'activo')); ?>"
                                                    data-tipo-contrato="<?php echo htmlspecialchars((string) ($tercero['tipo_contrato'] ?? '')); ?>"
                                                    data-tipo-pago="<?php echo htmlspecialchars((string) ($tercero['tipo_pago'] ?? 'MENSUAL')); ?>"
                                                    data-moneda="<?php echo htmlspecialchars((string) ($tercero['moneda'] ?? 'PEN')); ?>"
                                                    data-sueldo-basico="<?php echo htmlspecialchars((string) ($tercero['sueldo_basico'] ?? '')); ?>"
                                                    data-regimen-pensionario="<?php echo htmlspecialchars((string) ($tercero['regimen_pensionario'] ?? '')); ?>"
                                                    data-tipo-comision-afp="<?php echo htmlspecialchars((string) ($tercero['tipo_comision_afp'] ?? '')); ?>"
                                                    data-cuspp="<?php echo htmlspecialchars((string) ($tercero['cuspp'] ?? '')); ?>"
                                                    data-asignacion-familiar="<?php echo (int) ($tercero['asignacion_familiar'] ?? 0); ?>"
                                                    data-essalud="<?php echo (int) ($tercero['essalud'] ?? 0); ?>"
                                                    data-recordar-cumpleanos="<?php echo (int) ($tercero['recordar_cumpleanos'] ?? 0); ?>"
                                                    data-fecha-nacimiento="<?php echo htmlspecialchars((string) ($tercero['fecha_nacimiento'] ?? '')); ?>"
                                                    data-genero="<?php echo htmlspecialchars((string) ($tercero['genero'] ?? '')); ?>"
                                                    data-estado-civil="<?php echo htmlspecialchars((string) ($tercero['estado_civil'] ?? '')); ?>"
                                                    data-nivel-educativo="<?php echo htmlspecialchars((string) ($tercero['nivel_educativo'] ?? '')); ?>"
                                                    data-contacto-emergencia-nombre="<?php echo htmlspecialchars((string) ($tercero['contacto_emergencia_nombre'] ?? '')); ?>"
                                                    data-contacto-emergencia-telf="<?php echo htmlspecialchars((string) ($tercero['contacto_emergencia_telf'] ?? '')); ?>"
                                                    data-tipo-sangre="<?php echo htmlspecialchars((string) ($tercero['tipo_sangre'] ?? '')); ?>"
                                                    data-cliente-dias-credito="<?php echo (int) ($tercero['cliente_dias_credito'] ?? 0); ?>"
                                                    data-cliente-limite-credito="<?php echo htmlspecialchars((string) ($tercero['cliente_limite_credito'] ?? '')); ?>"
                                                    data-cliente-condicion-pago="<?php echo htmlspecialchars((string) ($tercero['cliente_condicion_pago'] ?? '')); ?>"
                                                    data-proveedor-dias-credito="<?php echo (int) ($tercero['proveedor_dias_credito'] ?? 0); ?>"
                                                    data-proveedor-condicion-pago="<?php echo htmlspecialchars((string) ($tercero['proveedor_condicion_pago'] ?? '')); ?>"
                                                    data-proveedor-forma-pago="<?php echo htmlspecialchars((string) ($tercero['proveedor_forma_pago'] ?? '')); ?>"
                                                    data-hijos-lista='<?php echo e(json_encode($tercero['hijos_lista'] ?? [], JSON_UNESCAPED_UNICODE)); ?>'
                                                    data-cuentas-bancarias='<?php echo e(json_encode($cuentasBancariasParaJs, JSON_UNESCAPED_UNICODE)); ?>'>
                                                <i class="bi bi-pencil fs-6"></i>
                                            </button>
                                    
                                            <?php
                                                $puedeEliminar = (int) ($tercero['puede_eliminar'] ?? 1) === 1;
                                                $motivoNoEliminar = (string) ($tercero['motivo_no_eliminar'] ?? 'No se puede eliminar este tercero.');
                                            ?>
                                            <button class="btn btn-sm border-0 rounded-circle shadow-sm js-eliminar-tercero <?php echo $puedeEliminar ? 'btn-light text-danger' : 'btn-light text-muted opacity-50'; ?>" 
                                                    data-id="<?php echo (int) $tercero['id']; ?>"
                                                    data-puede-eliminar="<?php echo $puedeEliminar ? '1' : '0'; ?>"
                                                    data-motivo-no-eliminar="<?php echo e($motivoNoEliminar); ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="<?php echo $puedeEliminar ? 'Eliminar' : e($motivoNoEliminar); ?>"
                                                    <?php echo $puedeEliminar ? '' : 'disabled aria-disabled="true"'; ?>>
                                                <i class="bi bi-trash fs-6"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($terceros)): ?>
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-4">
                <small class="text-muted fw-semibold" id="tercerosPaginationInfo">Procesando...</small>
                <nav aria-label="Paginación de Terceros">
                    <ul class="pagination mb-0 shadow-sm" id="tercerosPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearTercero" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <form id="formCrearTercero" method="POST" novalidate class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Registrar Nuevo Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <input type="hidden" name="accion" value="crear">
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
               
                <ul class="nav nav-tabs px-2 pt-2 bg-white rounded-top shadow-sm border-bottom-0" id="crearTerceroTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active fw-bold text-secondary" id="crear-tab-identificacion" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-identificacion" type="button"><i class="bi bi-person-vcard me-2"></i>Identificación</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold text-secondary" id="crear-tab-contacto" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-contacto" type="button"><i class="bi bi-telephone me-2"></i>Contacto</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold text-secondary" id="crear-tab-financiero" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-financiero" type="button"><i class="bi bi-bank me-2"></i>Cuentas</button></li>
                   
                    <li class="nav-item d-none" id="crear-tab-header-cliente"><button class="nav-link text-success fw-bold bg-success-subtle border-success-subtle ms-2" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-cliente" type="button"><i class="bi bi-bag-check me-1"></i>Cliente</button></li>
                    <li class="nav-item d-none" id="crear-tab-header-distribuidor"><button class="nav-link text-primary fw-bold bg-primary-subtle border-primary-subtle ms-1" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-distribuidor" type="button"><i class="bi bi-truck me-1"></i>Distribuidor</button></li>
                    <li class="nav-item d-none" id="crear-tab-header-proveedor"><button class="nav-link text-warning-emphasis fw-bold bg-warning-subtle border-warning-subtle ms-1" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-proveedor" type="button"><i class="bi bi-box-seam me-1"></i>Proveedor</button></li>
                    <li class="nav-item d-none" id="crear-tab-header-empleado"><button class="nav-link text-info-emphasis fw-bold bg-info-subtle border-info-subtle ms-1" data-bs-toggle="tab" data-bs-target="#crear-tab-pane-empleado" type="button"><i class="bi bi-person-badge me-1"></i>Empleado</button></li>
                </ul>

                <div class="tab-content p-0 shadow-sm rounded-bottom">
                    <div class="tab-pane fade show active" id="crear-tab-pane-identificacion" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom">
                            <div class="card-body p-4">
                                <div class="bg-light p-3 rounded-3 border mb-4">
                                    <label class="form-label fw-bold text-dark mb-3"><i class="bi bi-tags me-2 text-primary"></i>¿Qué rol cumple este tercero en el sistema?</label>
                                    <div class="d-flex flex-wrap gap-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="crearEsCliente" name="es_cliente" value="1" data-target="crear-tab-header-cliente">
                                            <label class="form-check-label fw-semibold text-secondary" for="crearEsCliente">Es Cliente</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="crearEsDistribuidor" name="es_distribuidor" value="1" data-target="crear-tab-header-distribuidor">
                                            <label class="form-check-label fw-semibold text-secondary" for="crearEsDistribuidor">Es Distribuidor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="crearEsProveedor" name="es_proveedor" value="1" data-target="crear-tab-header-proveedor">
                                            <label class="form-check-label fw-semibold text-secondary" for="crearEsProveedor">Es Proveedor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="crearEsEmpleado" name="es_empleado" value="1" data-target="crear-tab-header-empleado">
                                            <label class="form-check-label fw-semibold text-secondary" for="crearEsEmpleado">Es Empleado</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="tipo_persona" id="crearTipoPersona" required>
                                                <option value="NATURAL">Persona Natural</option>
                                                <option value="JURIDICA">Persona Jurídica</option>
                                            </select>
                                            <label>Tipo Entidad <span class="text-danger">*</span></label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="tipo_documento" id="crearTipoDoc">
                                                <option value="DNI">DNI</option>
                                                <option value="RUC">RUC</option>
                                                <option value="CE">Carnet Ext. (CE)</option>
                                            </select>
                                            <label>Tipo Documento</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control shadow-none border-secondary-subtle fw-bold" name="numero_documento" id="crearNumeroDoc" placeholder="Número">
                                            <label>Número de Documento</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control shadow-none border-secondary-subtle fw-bold" name="nombre_completo" id="crearNombre" placeholder="Razón Social" required>
                                            <label id="crearNombreLabel">Razón Social / Nombres Completos <span class="text-danger">*</span></label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 d-none" id="crearRepresentanteLegalSection">
                                        <div class="form-floating">
                                            <input type="text" class="form-control shadow-none border-secondary-subtle" name="representante_legal" id="crearRepresentanteLegal" placeholder="Representante">
                                            <label>Representante Legal</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mt-4">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="estado" id="crearEstado">
                                                <option value="1">Activo (Operativo)</option>
                                                <option value="0">Inactivo</option>
                                            </select>
                                            <label>Estado en Sistema</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="crear-tab-pane-contacto" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom">
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="form-floating">
                                            <input type="text" class="form-control shadow-none border-secondary-subtle" name="direccion" id="crearDireccion" placeholder="Dirección">
                                            <label>Dirección Fiscal / Principal</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="email" class="form-control shadow-none border-secondary-subtle" name="email" id="crearEmail" placeholder="Email">
                                            <label>Correo Electrónico</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mt-4">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="departamento_id" id="crearDepartamento">
                                                <option value="">Seleccione departamento...</option>
                                                <?php foreach ($departamentos_list as $dep): ?>
                                                    <option value="<?php echo (int)$dep['id']; ?>"><?php echo htmlspecialchars($dep['nombre']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label>Departamento</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mt-4">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="provincia_id" id="crearProvincia" disabled><option value="">Seleccione provincia...</option></select>
                                            <label>Provincia</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mt-4">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="distrito_id" id="crearDistrito" disabled><option value="">Seleccione distrito...</option></select>
                                            <label>Distrito</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 mt-4 pt-3 border-top">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-telephone-plus me-2 text-primary"></i>Números de Teléfono</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 shadow-sm" id="crearAgregarTelefono"><i class="bi bi-plus-lg me-1"></i> Agregar Teléfono</button>
                                        </div>
                                        <div id="crearTelefonosList" class="d-flex flex-column gap-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="crear-tab-pane-financiero" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom">
                            <div class="card-body p-4">
                                <div class="alert alert-info py-3 small d-none shadow-sm border-0 d-flex align-items-center" id="crearAlertaBancos">
                                    <div class="spinner-border spinner-border-sm text-info me-3" role="status"></div>
                                    <div>Cargando catálogo de entidades financieras...</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-credit-card me-2 text-primary"></i>Cuentas Bancarias / Billeteras</h6>
                                        <small class="text-muted">Necesario para pagos a proveedores o planillas de empleados.</small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 shadow-sm" id="crearAgregarCuenta"><i class="bi bi-plus-lg me-1"></i> Agregar Cuenta</button>
                                </div>
                                <div id="crearCuentasBancariasList" class="d-flex flex-column gap-3"></div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="crear-tab-pane-cliente" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-success-subtle bg-opacity-10 border-top border-success">
                            <div class="card-body p-4">
                                <?php $prefix = 'crear'; require __DIR__ . '/clientes_form.php'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="crear-tab-pane-distribuidor" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-primary-subtle bg-opacity-10 border-top border-primary">
                            <div class="card-body p-4">
                                <?php $prefix = 'crear'; require __DIR__ . '/distribuidores_form.php'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="crear-tab-pane-proveedor" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-warning-subtle bg-opacity-10 border-top border-warning">
                            <div class="card-body p-4">
                                <?php $prefix = 'crear'; require __DIR__ . '/proveedores_form.php'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="crear-tab-pane-empleado" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-info-subtle bg-opacity-10 border-top border-info">
                            <div class="card-body p-4">
                                <?php $prefix = 'crear'; require __DIR__ . '/empleados_form.php'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top shadow-sm">
                <button type="button" class="btn btn-light text-secondary border px-4 fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm" id="crearGuardarBtn"><i class="bi bi-save me-2"></i>Guardar Tercero</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditarTercero" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <form id="formEditarTercero" method="POST" novalidate class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Ficha de Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="editId"> 
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
               
                <ul class="nav nav-tabs px-2 pt-2 bg-white rounded-top shadow-sm border-bottom-0" id="editTerceroTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active fw-bold text-secondary" id="edit-tab-identificacion" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-identificacion" type="button"><i class="bi bi-person-vcard me-2"></i>Identificación</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold text-secondary" id="edit-tab-contacto" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-contacto" type="button"><i class="bi bi-telephone me-2"></i>Contacto</button></li>
                    <li class="nav-item"><button class="nav-link fw-bold text-secondary" id="edit-tab-financiero" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-financiero" type="button"><i class="bi bi-bank me-2"></i>Cuentas</button></li>
                   
                    <li class="nav-item d-none" id="edit-tab-header-cliente"><button class="nav-link text-success fw-bold bg-success-subtle border-success-subtle ms-2" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-cliente" type="button"><i class="bi bi-bag-check me-1"></i>Cliente</button></li>
                    <li class="nav-item d-none" id="edit-tab-header-distribuidor"><button class="nav-link text-primary fw-bold bg-primary-subtle border-primary-subtle ms-1" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-distribuidor" type="button"><i class="bi bi-truck me-1"></i>Distribuidor</button></li>
                    <li class="nav-item d-none" id="edit-tab-header-proveedor"><button class="nav-link text-warning-emphasis fw-bold bg-warning-subtle border-warning-subtle ms-1" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-proveedor" type="button"><i class="bi bi-box-seam me-1"></i>Proveedor</button></li>
                    <li class="nav-item d-none" id="edit-tab-header-empleado"><button class="nav-link text-info-emphasis fw-bold bg-info-subtle border-info-subtle ms-1" data-bs-toggle="tab" data-bs-target="#edit-tab-pane-empleado" type="button"><i class="bi bi-person-badge me-1"></i>Empleado</button></li>
                </ul>

                <div class="tab-content p-0 shadow-sm rounded-bottom">
                    
                    <div class="tab-pane fade show active" id="edit-tab-pane-identificacion" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom">
                            <div class="card-body p-4">
                                <div class="bg-light p-3 rounded-3 border mb-4">
                                    <label class="form-label fw-bold text-dark mb-3"><i class="bi bi-tags me-2 text-primary"></i>Roles Asignados</label>
                                    <div class="d-flex flex-wrap gap-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="editEsCliente" name="es_cliente" value="1" data-target="edit-tab-header-cliente">
                                            <label class="form-check-label fw-semibold text-secondary" for="editEsCliente">Cliente</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="editEsDistribuidor" name="es_distribuidor" value="1" data-target="edit-tab-header-distribuidor">
                                            <label class="form-check-label fw-semibold text-secondary" for="editEsDistribuidor">Distribuidor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="editEsProveedor" name="es_proveedor" value="1" data-target="edit-tab-header-proveedor">
                                            <label class="form-check-label fw-semibold text-secondary" for="editEsProveedor">Proveedor</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input role-trigger shadow-none" type="checkbox" id="editEsEmpleado" name="es_empleado" value="1" data-target="edit-tab-header-empleado">
                                            <label class="form-check-label fw-semibold text-secondary" for="editEsEmpleado">Empleado</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="tipo_persona" id="editTipoPersona" required>
                                                <option value="NATURAL">Persona Natural</option>
                                                <option value="JURIDICA">Persona Jurídica</option>
                                            </select>
                                            <label>Tipo Entidad</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="tipo_documento" id="editTipoDoc">
                                                <option value="DNI">DNI</option>
                                                <option value="RUC">RUC</option>
                                                <option value="CE">Carnet Ext. (CE)</option>
                                            </select>
                                            <label>Tipo Documento</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control shadow-none border-secondary-subtle fw-bold" name="numero_documento" id="editNumeroDoc" placeholder="Número">
                                            <label>Número de Documento</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control shadow-none border-secondary-subtle fw-bold text-dark" name="nombre_completo" id="editNombre" required>
                                            <label id="editNombreLabel">Razón Social / Nombres Completos <span class="text-danger">*</span></label>
                                        </div>
                                    </div>
                                    <div class="col-md-12 d-none" id="editRepresentanteLegalSection">
                                        <div class="form-floating">
                                            <input type="text" class="form-control shadow-none border-secondary-subtle" name="representante_legal" id="editRepresentanteLegal">
                                            <label>Representante Legal</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mt-4">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="estado" id="editEstado">
                                                <option value="1">Activo</option>
                                                <option value="0">Inactivo</option>
                                            </select>
                                            <label>Estado en Sistema</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="edit-tab-pane-contacto" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom">
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-8"><div class="form-floating"><input type="text" class="form-control shadow-none border-secondary-subtle" name="direccion" id="editDireccion"><label>Dirección Fiscal</label></div></div>
                                    <div class="col-md-4"><div class="form-floating"><input type="email" class="form-control shadow-none border-secondary-subtle" name="email" id="editEmail"><label>Correo Electrónico</label></div></div>
                                    
                                    <div class="col-md-4 mt-4">
                                        <div class="form-floating">
                                            <select class="form-select shadow-none border-secondary-subtle" name="departamento_id" id="editDepartamento">
                                                <option value="">Seleccione...</option>
                                                <?php foreach ($departamentos_list as $dep): ?>
                                                    <option value="<?php echo (int)$dep['id']; ?>"><?php echo htmlspecialchars($dep['nombre']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label>Departamento</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mt-4"><div class="form-floating"><select class="form-select shadow-none border-secondary-subtle" name="provincia_id" id="editProvincia" disabled><option value="">Seleccione...</option></select><label>Provincia</label></div></div>
                                    <div class="col-md-4 mt-4"><div class="form-floating"><select class="form-select shadow-none border-secondary-subtle" name="distrito_id" id="editDistrito" disabled><option value="">Seleccione...</option></select><label>Distrito</label></div></div>

                                    <div class="col-12 mt-4 pt-3 border-top">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-telephone-plus me-2 text-primary"></i>Números de Teléfono</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 shadow-sm" id="editAgregarTelefono"><i class="bi bi-plus-lg me-1"></i> Agregar Teléfono</button>
                                        </div>
                                        <div id="editTelefonosList" class="d-flex flex-column gap-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="edit-tab-pane-financiero" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom">
                            <div class="card-body p-4">
                                <div class="alert alert-info py-3 small d-none shadow-sm border-0 d-flex align-items-center" id="editAlertaBancos">
                                    <div class="spinner-border spinner-border-sm text-info me-3" role="status"></div>
                                    <div>Cargando lista de entidades financieras...</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-credit-card me-2 text-primary"></i>Cuentas Bancarias / Billeteras</h6>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 shadow-sm" id="editAgregarCuenta"><i class="bi bi-plus-lg me-1"></i> Agregar Cuenta</button>
                                </div>
                                <div id="editCuentasBancariasList" class="d-flex flex-column gap-3"></div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="edit-tab-pane-cliente" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-success-subtle bg-opacity-10 border-top border-success">
                            <div class="card-body p-4">
                                <?php $prefix = 'edit'; require __DIR__ . '/clientes_form.php'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="edit-tab-pane-distribuidor" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-primary-subtle bg-opacity-10 border-top border-primary">
                            <div class="card-body p-4">
                                <?php $prefix = 'edit'; require __DIR__ . '/distribuidores_form.php'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="edit-tab-pane-proveedor" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-warning-subtle bg-opacity-10 border-top border-warning">
                            <div class="card-body p-4">
                                <?php $prefix = 'edit'; require __DIR__ . '/proveedores_form.php'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="edit-tab-pane-empleado" tabindex="0">
                        <div class="card border-0 rounded-0 rounded-bottom bg-info-subtle bg-opacity-10 border-top border-info">
                            <div class="card-body p-4">
                                <?php $prefix = 'edit'; require __DIR__ . '/empleados_form.php'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top shadow-sm">
                <button type="button" class="btn btn-light text-secondary border px-4 fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Actualizar Ficha</button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/modales_maestros.php'; ?>

<style>
.empleado-switch-card{min-height:58px;}
.empleado-input-disabled{opacity:.7;}
.maestro-row{display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:.75rem;}
</style>
