<?php $usuarios = $usuarios ?? []; $roles = $roles ?? []; ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-people text-primary"></i>
                <span>Usuarios</span>
            </h1>
            <p class="text-muted mb-0">Gestión de usuarios del sistema, roles y accesos.</p>
        </div>
        <button
            class="btn btn-primary"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#crearUsuarioCollapse"
            aria-expanded="false"
            aria-controls="crearUsuarioCollapse"
            id="toggleCrearUsuarioBtn"
        >
            Nuevo usuario
        </button>
    </div>

    <hr class="mt-0 mb-4">

    <div class="collapse mb-4" id="crearUsuarioCollapse">
        <div class="card">
            <div class="card-header">Crear usuario</div>
            <div class="card-body">
                <form method="post" class="row g-2" id="formCrearUsuario">
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
                    <div class="col-md-3"><button class="btn btn-primary" type="submit">Crear</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Listado</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12 col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input
                            type="search"
                            class="form-control"
                            id="usuarioSearch"
                            placeholder="Buscar por nombre, usuario, correo o rol…"
                            aria-label="Buscar usuario"
                        >
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" id="usuariosTable">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th>Último login</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr
                            data-search="<?php echo e(mb_strtolower(trim((string) ($u['nombre_completo'] ?? '') . ' ' . (string) ($u['usuario'] ?? '') . ' ' . (string) ($u['email'] ?? '') . ' ' . (string) ($u['rol'] ?? '')))); ?>"
                        >
                            <td><?php echo (int) $u['id']; ?></td>
                            <td><?php echo e((string) ($u['nombre_completo'] ?? '')); ?></td>
                            <td><?php echo e((string) $u['usuario']); ?></td>
                            <td><?php echo e((string) ($u['email'] ?? '')); ?></td>
                            <td><?php echo e((string) ($u['rol'] ?? '-')); ?></td>
                            <td><span class="badge-status <?php echo (int) $u['estado'] === 1 ? 'bg-active' : 'bg-inactive'; ?>"><?php echo (int) $u['estado'] === 1 ? 'Activo' : 'Inactivo'; ?></span></td>
                            <td><?php echo e((string) ($u['ultimo_login'] ?? '-')); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <button
                                        class="btn btn-sm btn-link text-primary p-0"
                                        type="button"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Editar"
                                        onclick="editarUsuario(<?php echo (int) $u['id']; ?>,'<?php echo e((string) ($u['nombre_completo'] ?? '')); ?>','<?php echo e((string) $u['usuario']); ?>','<?php echo e((string) ($u['email'] ?? '')); ?>',<?php echo (int) $u['id_rol']; ?>)"
                                    >
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>

                                    <form method="post" class="estado-form d-inline">
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                        <input type="hidden" name="estado" value="<?php echo (int) $u['estado'] === 1 ? 0 : 1; ?>">
                                        <button
                                            class="btn btn-sm btn-link p-0 <?php echo (int) $u['estado'] === 1 ? 'text-success' : 'text-secondary'; ?>"
                                            type="submit"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="<?php echo (int) $u['estado'] === 1 ? 'Desactivar' : 'Activar'; ?>"
                                        >
                                            <i class="bi <?php echo (int) $u['estado'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off'; ?> fs-4"></i>
                                        </button>
                                    </form>

                                    <form method="post" class="delete-form d-inline">
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                        <input type="hidden" name="estado" value="0">
                                        <button
                                            class="btn btn-sm btn-link text-danger p-0"
                                            type="submit"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Eliminar"
                                        >
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
