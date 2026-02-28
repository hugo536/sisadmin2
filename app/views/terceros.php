<?php 
    $terceros = $terceros ?? []; 
    $departamentos_list = $departamentos_list ?? []; 
    $cargos_list = $cargos_list ?? [];
    $areas_list = $areas_list ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in terceros-sticky-header">
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
            <button class="btn btn-primary shadow-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearTercero" id="btnNuevoTercero">
                <i class="bi bi-person-plus-fill me-2"></i>Nuevo Tercero
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 terceros-sticky-filters">
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
            <div class="table-responsive terceros-table-wrapper">
                
                <table class="table align-middle mb-0 table-pro" id="tercerosTable"
                       data-erp-table="true"
                       data-search-input="#terceroSearch"
                       data-pagination-controls="#tercerosPaginationControls"
                       data-pagination-info="#tercerosPaginationInfo">
                    <thead class="terceros-sticky-thead bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold border-end">Documento</th>
                            <th class="text-secondary fw-semibold border-end">Tercero</th>
                            <th class="text-secondary fw-semibold border-end">Roles</th>
                            <th class="text-secondary fw-semibold border-end">Contacto</th>
                            <th class="text-center text-secondary fw-semibold border-end">Estado</th>
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
                                    $rolesFiltro = implode('|', $roles);
                                    $estadoBinario = ((int) ($tercero['estado'] ?? 0) === 1) ? 1 : 0;
                                    
                                    // Nos aseguramos de que las cuentas bancarias tengan el formato correcto para el JSON
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
                                    data-search="<?php echo htmlspecialchars(mb_strtolower($tercero['nombre_completo'].' '.$tercero['numero_documento'])); ?>">
                                    
                                    <td class="ps-4 fw-semibold text-primary align-top pt-3 border-end">
                                        <?php echo htmlspecialchars($tercero['tipo_documento']); ?> <br>
                                        <span class="text-dark small"><?php echo htmlspecialchars($tercero['numero_documento']); ?></span>
                                    </td>
                                    
                                    <td class="align-top pt-3 border-end">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($tercero['nombre_completo']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($tercero['direccion'] ?? ''); ?></div>
                                    </td>
                                    
                                    <td class="align-top pt-3 border-end">
                                        <?php foreach ($roles as $rol): ?>
                                            <span class="badge bg-light text-dark border me-1 mb-1 shadow-sm" style="font-size: 0.7rem;"><?php echo htmlspecialchars($rol); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    
                                    <td class="align-top pt-3 border-end">
                                        <div class="fw-medium text-dark"><i class="bi bi-telephone small text-muted me-1"></i><?php echo htmlspecialchars($tercero['telefono'] ?? ''); ?></div>
                                        <div class="small text-muted mt-1"><i class="bi bi-envelope small text-muted me-1"></i><?php echo htmlspecialchars($tercero['email'] ?? ''); ?></div>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3 border-end">
                                        <?php if ($estadoBinario === 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill" id="badge_status_tercero_<?php echo (int) $tercero['id']; ?>">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4 align-top pt-3">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <div class="form-check form-switch pt-1" title="Cambiar estado" data-bs-toggle="tooltip">
                                                <input class="form-check-input switch-estado-tercero" type="checkbox" role="switch"
                                                       style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                       data-id="<?php echo (int) $tercero['id']; ?>"
                                                       <?php echo $estadoBinario === 1 ? 'checked' : ''; ?>>
                                            </div>

                                            <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                            <a href="?ruta=terceros/perfil&id=<?php echo (int) $tercero['id']; ?>" class="btn btn-sm btn-light text-info border-0 rounded-circle" data-bs-toggle="tooltip" title="Ver Perfil">
                                                <i class="bi bi-person-badge fs-5"></i>
                                            </a>

                                            <button class="btn btn-sm btn-light text-primary border-0 rounded-circle js-editar-tercero"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarTercero"
                                                data-bs-toggle="tooltip" title="Editar"
                                                data-id="<?php echo (int) $tercero['id']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($tercero['nombre_completo']); ?>"
                                                data-tipo-doc="<?php echo htmlspecialchars($tercero['tipo_documento']); ?>"
                                                data-numero-doc="<?php echo htmlspecialchars($tercero['numero_documento']); ?>"
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
                                                data-codigo-biometrico="<?php echo htmlspecialchars((string) ($tercero['codigo_biometrico'] ?? '')); ?>"
                                                data-fecha-ingreso="<?php echo htmlspecialchars((string) ($tercero['fecha_ingreso'] ?? '')); ?>"
                                                data-fecha-cese="<?php echo htmlspecialchars((string) ($tercero['fecha_cese'] ?? '')); ?>"
                                                data-estado-laboral="<?php echo htmlspecialchars((string) ($tercero['estado_laboral'] ?? 'activo')); ?>"
                                                data-tipo-contrato="<?php echo htmlspecialchars((string) ($tercero['tipo_contrato'] ?? '')); ?>"
                                                data-tipo-pago="<?php echo htmlspecialchars((string) ($tercero['tipo_pago'] ?? 'MENSUAL')); ?>"
                                                data-moneda="<?php echo htmlspecialchars((string) ($tercero['moneda'] ?? 'PEN')); ?>"
                                                data-sueldo-basico="<?php echo htmlspecialchars((string) ($tercero['sueldo_basico'] ?? '')); ?>"
                                                data-pago-diario="<?php echo htmlspecialchars((string) ($tercero['pago_diario'] ?? '')); ?>"
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
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>
                                    
                                            <?php
                                                $puedeEliminar = (int) ($tercero['puede_eliminar'] ?? 1) === 1;
                                                $motivoNoEliminar = (string) ($tercero['motivo_no_eliminar'] ?? 'No se puede eliminar este tercero.');
                                            ?>
                                            <button class="btn btn-sm border-0 rounded-circle js-eliminar-tercero <?php echo $puedeEliminar ? 'btn-light text-danger' : 'btn-light text-muted opacity-50'; ?>" 
                                                data-id="<?php echo (int) $tercero['id']; ?>"
                                                data-puede-eliminar="<?php echo $puedeEliminar ? '1' : '0'; ?>"
                                                data-motivo-no-eliminar="<?php echo e($motivoNoEliminar); ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?php echo $puedeEliminar ? 'Eliminar' : e($motivoNoEliminar); ?>"
                                                <?php echo $puedeEliminar ? '' : 'disabled aria-disabled="true"'; ?>><i class="bi bi-trash fs-5"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($terceros)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="tercerosPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación de Terceros">
                    <ul class="pagination mb-0 shadow-sm" id="tercerosPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearTercero" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Nuevo Tercero</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCrearTercero" method="POST" novalidate>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body p-4 bg-light">
                    <p class="text-muted fst-italic">El contenido del modal permanece igual en tu archivo.</p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="crearGuardarBtn">Guardar Tercero</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarTercero" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    </div>

<?php include __DIR__ . '/modales_maestros.php'; ?>

<style>
.empleado-switch-card{min-height:58px;}
.empleado-input-disabled{opacity:.7;}
.maestro-row{display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:.75rem;}
</style>

<script src="<?php echo asset_url('js/terceros/clientes.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros/proveedores.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros/empleados.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros/distribuidores.js'); ?>"></script>
<script src="<?php echo asset_url('js/terceros.js'); ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.ERPTable !== 'undefined') {
        
        // Inicializamos los Tooltips nativos
        ERPTable.initTooltips();
        
        // Inicializamos la tabla especificándole dónde están los "Filtros"
        ERPTable.createTableManager({
            tableSelector: '#tercerosTable',
            rowsSelector: '#tercerosTableBody tr:not(.empty-msg-row)', // Excluir la fila vacía
            searchInput: '#terceroSearch',
            searchAttr: 'data-search',
            rowsPerPage: 25, 
            paginationControls: '#tercerosPaginationControls',
            paginationInfo: '#tercerosPaginationInfo',
            emptyText: 'No se encontraron terceros',
            infoText: ({ start, end, total }) => `Mostrando ${start} a ${end} de ${total} terceros`,
            
            // LA CLAVE: Aquí le decimos a ERPTable que escuche los <select> de filtro
            filters: [
                { el: '#terceroFiltroRol', attr: 'data-roles', match: 'includes' },
                { el: '#terceroFiltroEstado', attr: 'data-estado', match: 'equals' }
            ]
        }).init();
        
    }
});
</script>