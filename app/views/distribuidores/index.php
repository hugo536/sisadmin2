<?php
$distribuidores = $distribuidores ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-diagram-3 me-2 text-primary"></i> Distribuidores
            </h1>
            <p class="text-muted small mb-0 ms-1">Listado específico de distribuidores registrados.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-primary shadow-sm" href="<?php echo route_url('terceros'); ?>">
                <i class="bi bi-person-plus-fill me-2"></i>Nuevo Distribuidor
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="distribuidorSearch" placeholder="Buscar distribuidor...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="distribuidoresTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Documento</th>
                            <th>Distribuidor</th>
                            <th>Zonas exclusivas</th>
                            <th class="text-end pe-4">Ventas actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distribuidores as $distribuidor): ?>
                            <tr data-search="<?php echo htmlspecialchars(mb_strtolower($distribuidor['tipo_documento'] . ' ' . $distribuidor['numero_documento'] . ' ' . $distribuidor['nombre_completo'] . ' ' . ($distribuidor['zonas_exclusivas_resumen'] ?? ''))); ?>">
                                <td class="ps-4 fw-semibold" data-label="Documento">
                                    <?php echo htmlspecialchars($distribuidor['tipo_documento']); ?> - <?php echo htmlspecialchars($distribuidor['numero_documento']); ?>
                                </td>
                                <td data-label="Distribuidor">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($distribuidor['nombre_completo']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($distribuidor['telefono'] ?? ''); ?></div>
                                </td>
                                <td data-label="Zonas exclusivas">
                                    <?php if (!empty($distribuidor['zonas_exclusivas'])): ?>
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($distribuidor['zonas_exclusivas'] as $zona): ?>
                                                <li><?php echo htmlspecialchars($zona['label'] ?? ''); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="text-muted">Sin zonas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4" data-label="Ventas actual">
                                    <span class="text-muted">--</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="distribuidoresPaginationInfo">Mostrando todos los registros</small>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0 justify-content-end" id="distribuidoresPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('js/terceros/distribuidores.js'); ?>"></script>
