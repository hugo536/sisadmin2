<?php
$totales = is_array($totales ?? null) ? $totales : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
?>
<div class="container-fluid">
    <h1 class="h3 mb-4">Dashboard</h1>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body"><h6>Total ítems activos</h6><h2><?php echo (int) ($totales['items'] ?? 0); ?></h2></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h6>Total terceros activos</h6><h2><?php echo (int) ($totales['terceros'] ?? 0); ?></h2></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h6>Total usuarios activos</h6><h2><?php echo (int) ($totales['usuarios'] ?? 0); ?></h2></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">Últimos 10 registros de bitácora</div>
        <div class="card-body">
            <?php if ($eventos === []): ?>
                <div class="alert alert-light border">No hay registros en bitácora.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th></tr></thead>
                    <tbody>
                    <?php foreach ($eventos as $row): ?>
                        <tr>
                            <td><?php echo e((string) ($row['created_at'] ?? '')); ?></td>
                            <td><?php echo e((string) ($row['evento'] ?? '')); ?></td>
                            <td><?php echo e((string) ($row['usuario'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
