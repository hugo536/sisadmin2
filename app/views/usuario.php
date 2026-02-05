<?php 
$usuarios = $usuarios ?? [];
$roles = $roles ?? [];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 text-gray-800">Gestión de Usuarios</h2>
        <button type="button" class="btn btn-primary" onclick="abrirModal()">
            <i class="fas fa-plus me-2"></i> Nuevo Usuario
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Listado de Personal</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tablaUsuarios" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo e($u['nombre_completo']); ?></div>
                            </td>
                            <td><?php echo e($u['usuario']); ?></td>
                            <td><?php echo e($u['email']); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo e($u['rol']); ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$u['estado'] === 1): ?>
                                    <span class="badge-status bg-active">Activo</span>
                                <?php else: ?>
                                    <span class="badge-status bg-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-info text-white" 
                                        onclick='editarUsuario(<?php echo json_encode($u); ?>)'
                                        title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-users fa-3x mb-3 d-block"></i>
                                No hay usuarios registrados aún.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitulo">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?php echo route_url('usuarios'); ?>">
                <div class="modal-body">
                    <input type="hidden" name="id" id="user_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_completo" id="nombre_completo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario (Login) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="usuario" id="usuario" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_rol" id="id_rol" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach($roles as $rol): ?>
                                    <option value="<?php echo $rol['id']; ?>"><?php echo e($rol['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="clave" id="clave" placeholder="Dejar vacío si no cambia">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado" id="estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Datos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Inicializar Modal de Bootstrap
    let modalUsuario;
    document.addEventListener('DOMContentLoaded', function() {
        modalUsuario = new bootstrap.Modal(document.getElementById('modalUsuario'));
    });

    function abrirModal() {
        // Limpiar formulario
        document.getElementById('user_id').value = '';
        document.getElementById('nombre_completo').value = '';
        document.getElementById('usuario').value = '';
        document.getElementById('email').value = '';
        document.getElementById('id_rol').value = '';
        document.getElementById('clave').value = '';
        document.getElementById('estado').value = '1';
        
        document.getElementById('modalTitulo').innerText = 'Nuevo Usuario';
        modalUsuario.show();
    }

    function editarUsuario(u) {
        // Llenar datos
        document.getElementById('user_id').value = u.id;
        document.getElementById('nombre_completo').value = u.nombre_completo;
        document.getElementById('usuario').value = u.usuario;
        document.getElementById('email').value = u.email;
        document.getElementById('id_rol').value = u.id_rol;
        document.getElementById('estado').value = u.estado;
        
        // Reset password field
        document.getElementById('clave').value = '';
        document.getElementById('clave').placeholder = "Dejar vacío para mantener actual";
        
        document.getElementById('modalTitulo').innerText = 'Editar Usuario';
        modalUsuario.show();
    }
</script>