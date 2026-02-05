<?php $usuarios = $usuarios ?? []; $roles = $roles ?? []; ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3">Usuarios</h1>
    </div>

    <div class="card mb-4">
        <div class="card-header">Crear usuario</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <input type="hidden" name="accion" value="crear">
                <div class="col-md-3"><input class="form-control" name="nombre_completo" placeholder="Nombre completo" required></div>
                <div class="col-md-2"><input class="form-control" name="usuario" placeholder="Usuario" required></div>
                <div class="col-md-3"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
                <div class="col-md-2"><input class="form-control" name="clave" type="password" placeholder="Clave" required></div>
                <div class="col-md-2">
                    <select class="form-select" name="id_rol" required>
                        <option value="">Rol</option>
                        <?php foreach ($roles as $rol): ?><option value="<?php echo (int) $rol['id']; ?>"><?php echo e((string) $rol['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-primary">Crear</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Listado</div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th>Último login</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?php echo (int) $u['id']; ?></td>
                        <td><?php echo e((string) ($u['nombre_completo'] ?? '')); ?></td>
                        <td><?php echo e((string) $u['usuario']); ?></td>
                        <td><?php echo e((string) ($u['email'] ?? '')); ?></td>
                        <td><?php echo e((string) ($u['rol'] ?? '-')); ?></td>
                        <td><span class="badge-status <?php echo (int) $u['estado'] === 1 ? 'bg-active' : 'bg-inactive'; ?>"><?php echo (int) $u['estado'] === 1 ? 'Activo' : 'Inactivo'; ?></span></td>
                        <td><?php echo e((string) ($u['ultimo_login'] ?? '-')); ?></td>
                        <td class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?php echo (int) $u['id']; ?>,'<?php echo e((string) ($u['nombre_completo'] ?? '')); ?>','<?php echo e((string) $u['usuario']); ?>','<?php echo e((string) ($u['email'] ?? '')); ?>',<?php echo (int) $u['id_rol']; ?>)">Editar</button>
                            <form method="post" class="estado-form">
                                <input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                <input type="hidden" name="estado" value="<?php echo (int) $u['estado'] === 1 ? 0 : 1; ?>">
                                <button class="btn btn-sm btn-outline-warning"><?php echo (int) $u['estado'] === 1 ? 'Desactivar' : 'Activar'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarUsuario" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar usuario</h5></div><div class="modal-body">
<form method="post" id="formEditarUsuario">
    <input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="editId">
    <label>Nombre completo</label><input class="form-control mb-2" id="editNombreCompleto" name="nombre_completo" required>
    <label>Usuario</label><input class="form-control mb-2" id="editUsuario" name="usuario" required>
    <label>Email</label><input class="form-control mb-2" id="editEmail" name="email" type="email" required>
    <label>Rol</label><select class="form-select mb-2" id="editRol" name="id_rol" required><?php foreach ($roles as $rol): ?><option value="<?php echo (int) $rol['id']; ?>"><?php echo e((string) $rol['nombre']); ?></option><?php endforeach; ?></select>
    <label>Nueva clave (opcional)</label><input class="form-control mb-3" name="clave" type="password">
    <button class="btn btn-primary">Guardar</button>
</form>
</div></div></div></div>
<script>
function editarUsuario(id, nombreCompleto, usuario, email, idRol){document.getElementById('editId').value=id;document.getElementById('editNombreCompleto').value=nombreCompleto;document.getElementById('editUsuario').value=usuario;document.getElementById('editEmail').value=email;document.getElementById('editRol').value=idRol;new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();}
Array.from(document.querySelectorAll('.estado-form')).forEach(function(form){form.addEventListener('submit',function(e){e.preventDefault();Swal.fire({title:'Confirmar',text:'¿Aplicar cambio de estado?',icon:'warning',showCancelButton:true}).then(function(r){if(r.isConfirmed){form.submit();}});});});
</script>
