<?php
$centros = $centros ?? [];
// Generamos un token CSRF si no existe en la sesión (Buena práctica de seguridad)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-diagram-3-fill me-2 text-primary"></i> Centros de Costo
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de áreas, departamentos o proyectos para distribución contable.</p>
        </div>

        <div class="d-flex gap-2">
            <?php if (tiene_permiso('conta.centros_costo.gestionar')): ?>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCentroCosto" id="btnNuevoCentroCosto">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Centro de Costo
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="searchCentrosCosto" placeholder="Buscar por código o nombre...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro" id="tablaCentrosCosto">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold" style="width: 15%;">Código</th>
                            <th class="text-secondary fw-semibold">Nombre del Centro de Costo</th>
                            <th class="text-center text-secondary fw-semibold" style="width: 15%;">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold" style="width: 10%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($centros)): ?>
                            <?php foreach ($centros as $c): ?>
                                <?php 
                                    $esActivo = ((int)$c['estado'] === 1); 
                                ?>
                                <tr class="border-bottom fila-datos">
                                    <td class="ps-4 fw-semibold text-primary pt-3"><?php echo e($c['codigo']); ?></td>
                                    <td class="fw-semibold text-dark pt-3"><?php echo e($c['nombre']); ?></td>
                                    <td class="text-center pt-3">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo $esActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>">
                                            <?php echo $esActivo ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <button type="button" class="btn btn-sm btn-light text-primary border-0 bg-transparent rounded-circle btn-editar-cc" 
                                                data-bs-toggle="tooltip" data-bs-placement="top" title="Editar"
                                                data-id="<?php echo (int)$c['id']; ?>" 
                                                data-codigo="<?php echo e($c['codigo']); ?>" 
                                                data-nombre="<?php echo e($c['nombre']); ?>" 
                                                data-estado="<?php echo (int)$c['estado']; ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="filaVaciaInicial">
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-diagram-2 fs-1 d-block mb-2 text-light"></i>
                                    No hay centros de costo registrados.
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr id="filaSinResultados" style="display: none;">
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-search fs-2 d-block mb-2 text-light"></i>
                                No se encontraron coincidencias para tu búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="modalCentroCosto" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold" id="tituloModalCC"><i class="bi bi-diagram-3 me-2"></i>Registrar Centro de Costo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <form id="formCentroCosto" autocomplete="off" data-url="<?php echo e(route_url('contabilidad/guardar_centro_costo')); ?>">
                    
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <input type="hidden" name="id" id="cc_id" value="0">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small text-muted fw-bold">Código <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="codigo" id="cc_codigo" placeholder="Ej. ADM-01" required>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small text-muted fw-bold">Estado</label>
                                    <select class="form-select shadow-none" name="estado" id="cc_estado">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">Nombre del Centro de Costo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="nombre" id="cc_nombre" placeholder="Ej. Administración General" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        
                        <button type="submit" class="btn btn-primary px-4 fw-bold" id="btnGuardarCC">
                            <i class="bi bi-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/contabilidad/centros_costo.js"></script>