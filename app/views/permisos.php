<?php $permisosAgrupados = $permisosAgrupados ?? []; ?>

<div class="container-fluid p-4">

  <div class="mb-4 fade-in">
    <h1 class="h4 fw-bold mb-1 text-dark d-flex align-items-center">
      <i class="bi bi-key me-2 text-primary fs-5"></i>
      <span>Permisos</span>
    </h1>
    <p class="text-muted small mb-0 ms-1">Listado de permisos base del sistema agrupados por módulo.</p>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input
          type="search"
          id="permisoSearch"
          class="form-control bg-light border-start-0 ps-0"
          placeholder="Buscar por slug, nombre o módulo..."
        >
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0 table-pro" id="permisosTable">
          <thead>
            <tr>
              <th class="ps-4">Módulo</th>
              <th>Slug</th>
              <th>Nombre</th>
              <th class="text-center">Estado</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($permisosAgrupados as $modulo => $permisos): ?>
              <?php foreach ($permisos as $permiso): ?>
                <?php
                  $mod = (string)$modulo;
                  $slug = (string)($permiso['slug'] ?? '');
                  $nom  = (string)($permiso['nombre'] ?? '');
                  $est  = (int)($permiso['estado'] ?? 0);

                  // Para búsqueda (mismo patrón que Usuarios/Roles)
                  $search = mb_strtolower(trim($mod . ' ' . $slug . ' ' . $nom));
                ?>
                <tr data-search="<?php echo e($search); ?>">
                  <td class="ps-4" data-label="Módulo">
                    <span class="badge rounded-pill text-bg-light border">
                      <?php echo e($mod); ?>
                    </span>
                  </td>

                  <td data-label="Slug">
                    <code><?php echo e($slug); ?></code>
                  </td>

                  <td data-label="Nombre">
                    <?php echo e($nom); ?>
                  </td>

                  <td class="text-center" data-label="Estado">
                    <span class="badge-status <?php echo $est === 1 ? 'status-active' : 'status-inactive'; ?>">
                      <?php echo $est === 1 ? 'Activo' : 'Inactivo'; ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- OPCIONAL: si quieres paginación como Usuarios/Roles -->
      <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
        <small class="text-muted" id="permisosPaginationInfo">Cargando...</small>
        <nav aria-label="Page navigation">
          <ul class="pagination pagination-sm mb-0 justify-content-end" id="permisosPaginationControls"></ul>
        </nav>
      </div>

    </div>
  </div>

</div>
