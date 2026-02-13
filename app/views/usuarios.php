<?php 
$usuarios = $usuarios ?? []; 
$roles = $roles ?? []; 
$currentUserId = $current_user_id ?? 0; 
?>
<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-people-fill me-2 text-primary"></i> Usuarios
            </h1>
            <p class="text-muted small mb-0 ms-1">Administración de cuentas y control de acceso.</p>
        </div>
        
        <button class="btn btn-primary shadow-sm" onclick="abrirModalCrear()">
            <i class="bi bi-person-plus-fill me-2"></i>Nuevo Usuario
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="usuarioSearch" placeholder="Buscar por nombre, usuario...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="filtroRol">
                        <option value="">Todos los Roles</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo e($rol['nombre']); ?>"><?php echo e($rol['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="filtroEstado">
                        <option value="">Todos los Estados</option>
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
                <table class="table align-middle mb-0 table-pro" id="usuariosTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Usuario</th>
                            <th>Rol</th>
                            <th class="text-center">Estado</th>
                            <th>Último Acceso</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php 
                                $esAdmin = strtolower($u['usuario']) === 'admin';
                                $esMismoUsuario = (int)$u['id'] === $currentUserId;
                                $esIntocable = $esAdmin || $esMismoUsuario;
                            ?>
                            <tr data-search="<?php echo e(mb_strtolower($u['nombre_completo'].' '.$u['usuario'].' '.$u['email'])); ?>" 
                                data-rol="<?php echo e($u['rol']); ?>" 
                                data-estado="<?php echo (int)$u['estado']; ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3 bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center border border-primary-subtle" style="width:40px; height:40px; border-radius:50%;">
                                            <?php echo strtoupper(substr($u['usuario'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center">
                                                <?php echo e($u['nombre_completo']); ?>
                                                <?php if ($esAdmin): ?>
                                                    <i class="bi bi-patch-check-fill text-warning ms-1" title="Super Admin"></i>
                                                <?php endif; ?>
                                                <?php if ($esMismoUsuario): ?>
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle ms-2 py-0 px-1">TÚ</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted"><?php echo e($u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td><span class="badge bg-light text-dark border"><?php echo e($u['rol'] ?? 'N/A'); ?></span></td>
                                
                                <td class="text-center">
                                    <?php if ((int)$u['estado'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 rounded-pill" id="badge_status_<?php echo $u['id']; ?>">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill" id="badge_status_<?php echo $u['id']; ?>">Inactivo</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-muted small">
                                    <?php if(!empty($u['ultimo_login'])): ?>
                                        <i class="bi bi-clock me-1"></i><?php echo e($u['ultimo_login']); ?>
                                    <?php else: ?>
                                        <span class="text-secondary">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        
                                        <div class="form-check form-switch pt-1" title="<?php echo $esIntocable ? 'Protegido' : 'Cambiar estado'; ?>">
                                            <input class="form-check-input switch-estado" type="checkbox" role="switch" 
                                                   style="cursor: pointer;"
                                                   data-id="<?php echo $u['id']; ?>"
                                                   <?php echo (int)$u['estado'] === 1 ? 'checked' : ''; ?>
                                                   <?php echo $esIntocable ? 'disabled' : ''; ?>>
                                        </div>

                                        <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" 
                                                onclick="editarUsuario(<?php echo (int) $u['id']; ?>,'<?php echo e($u['nombre_completo']); ?>','<?php echo e($u['usuario']); ?>','<?php echo e($u['email']); ?>',<?php echo (int) $u['id_rol']; ?>)" 
                                                title="Editar">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        
                                        <?php if (!$esIntocable): ?>
                                            <form method="post" class="delete-form d-inline m-0">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent" title="Eliminar">
                                                    <i class="bi bi-trash fs-5"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-light text-muted border-0 bg-transparent" disabled title="Protegido">
                                                <i class="bi bi-lock fs-5"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3">
                <small class="text-muted">Mostrando <?php echo count($usuarios); ?> usuarios</small>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCrearUsuario" method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body p-4 bg-light">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" name="nombre_completo" id="crearNombre" placeholder="Nombre" required>
                        <label for="crearNombre">Nombre Completo</label>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6 form-floating">
                            <input type="text" class="form-control" name="usuario" id="crearUsuario" placeholder="Usuario" required>
                            <label for="crearUsuario">Usuario (Login)</label>
                        </div>
                        <div class="col-md-6 form-floating">
                            <select class="form-select" name="id_rol" id="crearRol" required>
                                <option value="" selected disabled>Seleccione...</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo (int) $rol['id']; ?>"><?php echo e($rol['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="crearRol">Rol</label>
                        </div>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" name="email" id="crearEmail" placeholder="Email" required>
                        <label for="crearEmail">Correo Electrónico</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" name="clave" id="crearClave" placeholder="Pass" required>
                        <label for="crearClave">Contraseña</label>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" id="formEditarUsuario">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="form-floating mb-3">
                        <input class="form-control" id="editNombreCompleto" name="nombre_completo" placeholder="Nombre" required>
                        <label for="editNombreCompleto">Nombre Completo</label>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6 form-floating">
                            <input class="form-control" id="editUsuario" name="usuario" placeholder="User" required>
                            <label for="editUsuario">Usuario</label>
                        </div>
                        <div class="col-md-6 form-floating">
                            <select class="form-select" id="editRol" name="id_rol" required>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo (int) $rol['id']; ?>"><?php echo e((string) $rol['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="editRol">Rol</label>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input class="form-control" id="editEmail" name="email" type="email" placeholder="Email" required>
                        <label for="editEmail">Email</label>
                    </div>

                    <div class="form-floating">
                        <input class="form-control" name="clave" type="password" placeholder="Clave">
                        <label for="editClave" class="text-muted">Nueva contraseña (opcional)</label>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold" type="submit">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('js/usuarios.js'); ?>"></script>