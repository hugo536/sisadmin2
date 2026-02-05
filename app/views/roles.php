<?php $roles = $roles ?? []; $permisos = $permisos ?? []; ?>
<div class="container-fluid">
    <h1 class="h3 mb-3">Roles</h1>

    <div class="card mb-3"><div class="card-header">Crear rol</div><div class="card-body">
        <form method="post" class="row g-2"><input type="hidden" name="accion" value="crear">
            <div class="col-md-6"><input name="nombre" class="form-control" placeholder="Nombre de rol" required></div>
            <div class="col-md-3"><button class="btn btn-primary">Crear</button></div>
        </form>
    </div></div>

    <div class="card"><div class="card-header">Listado y permisos</div><div class="card-body table-responsive">
        <table class="table align-middle"><thead><tr><th>Rol</th><th>Estado</th><th>Editar</th><th>Permisos</th></tr></thead><tbody>
        <?php foreach ($roles as $rol): ?>
            <tr>
                <td><?php echo e((string) $rol['nombre']); ?></td>
                <td><?php echo (int) $rol['estado'] === 1 ? 'Activo' : 'Inactivo'; ?></td>
                <td>
                    <form method="post" class="d-flex gap-1">
                        <input type="hidden" name="accion" value="editar"><input type="hidden" name="id" value="<?php echo (int) $rol['id']; ?>">
                        <input name="nombre" class="form-control form-control-sm" value="<?php echo e((string) $rol['nombre']); ?>">
                        <select name="estado" class="form-select form-select-sm"><option value="1" <?php echo (int) $rol['estado']===1?'selected':''; ?>>Activo</option><option value="0" <?php echo (int) $rol['estado']===0?'selected':''; ?>>Inactivo</option></select>
                        <button class="btn btn-sm btn-outline-primary">Guardar</button>
                    </form>
                </td>
                <td>
                    <form method="post"><input type="hidden" name="accion" value="permisos"><input type="hidden" name="id_rol" value="<?php echo (int) $rol['id']; ?>">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($permisos as $permiso): ?>
                                <label class="small border rounded p-1">
                                    <input type="checkbox" name="permisos[]" value="<?php echo (int) $permiso['id']; ?>" <?php echo in_array((int)$permiso['id'], $rol['permisos_ids'], true) ? 'checked' : ''; ?>>
                                    <?php echo e((string) $permiso['slug']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-sm btn-dark mt-2">Guardar permisos</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div>
</div>
