<?php 
    $listas = $listas ?? [];
    $listas_detalle = $listas_detalle ?? []; // Datos precargados si se selecciona una lista
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-currency-exchange me-2 text-success"></i> Listas de Precios
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestiona tarifas diferenciadas por tipo de cliente o zona.</p>
        </div>
        <button class="btn btn-success shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevaLista">
            <i class="bi bi-file-earmark-plus me-2"></i>Nueva Lista
        </button>
    </div>

    <div class="row g-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold border-bottom">Listas Activas</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($listas as $lista): ?>
                        <a href="?ruta=comercial/listas&id=<?php echo $lista['id']; ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo (isset($_GET['id']) && $_GET['id'] == $lista['id']) ? 'active' : ''; ?>">
                            <span><?php echo htmlspecialchars($lista['nombre']); ?></span>
                            <i class="bi bi-chevron-right small opacity-50"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <?php if (isset($_GET['id'])): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-primary">Editando: <?php echo htmlspecialchars($lista_seleccionada['nombre'] ?? 'Lista'); ?></h6>
                        <button class="btn btn-sm btn-outline-primary" form="formPrecios">
                            <i class="bi bi-save me-1"></i> Guardar Cambios
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <form id="formPrecios" method="POST" action="<?php echo route_url('comercial/actualizarPreciosLista'); ?>">
                            <input type="hidden" name="id_lista" value="<?php echo $_GET['id']; ?>">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Producto / Presentación</th>
                                            <th class="text-end">Precio Base</th>
                                            <th class="text-end pe-4" style="width: 200px;">Precio Especial</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($precios_matriz as $p): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['nombre_presentacion']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($p['item_nombre']); ?></small>
                                                </td>
                                                <td class="text-end text-muted">
                                                    S/ <?php echo number_format($p['precio_base'], 2); ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-white border-end-0">S/</span>
                                                        <input type="number" step="0.01" 
                                                               name="precios[<?php echo $p['id_presentacion']; ?>]" 
                                                               value="<?php echo $p['precio_especial']; ?>" 
                                                               class="form-control border-start-0 text-end fw-bold text-primary" 
                                                               placeholder="Base">
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-center p-5 shadow-sm">
                    <i class="bi bi-arrow-left-circle display-4 text-muted mb-3"></i>
                    <p class="h5 text-muted">Selecciona una lista del menú izquierdo para editar sus precios.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevaLista" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Crear Lista de Precios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo route_url('comercial/crearLista'); ?>" method="POST">
                <div class="modal-body bg-light p-4">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Lista</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Distribuidores Zona Sur" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" class="btn btn-success fw-bold w-100">Crear Lista</button>
                </div>
            </form>
        </div>
    </div>
</div>