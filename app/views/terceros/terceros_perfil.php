<?php
$tercero = $tercero ?? [];
$documentos = $documentos ?? [];
$hijosEmpleado = $hijos_empleado ?? [];
$departamentos = $departamentos_list ?? [];

$nombreCompleto = (string) ($tercero['nombre_completo'] ?? 'Tercero');
$inicial = mb_strtoupper(mb_substr(trim($nombreCompleto), 0, 1));
if ($inicial === '') {
    $inicial = 'T';
}

$rolBadges = [];
if ((int) ($tercero['es_cliente'] ?? 0) === 1) {
    $rolBadges[] = ['label' => 'Cliente', 'class' => 'bg-success-subtle text-success'];
}
if ((int) ($tercero['es_proveedor'] ?? 0) === 1) {
    $rolBadges[] = ['label' => 'Proveedor', 'class' => 'bg-warning-subtle text-warning-emphasis'];
}
if ((int) ($tercero['es_empleado'] ?? 0) === 1) {
    $rolBadges[] = ['label' => 'Empleado', 'class' => 'bg-info-subtle text-info-emphasis'];
}
if ((int) ($tercero['es_distribuidor'] ?? 0) === 1) {
    $rolBadges[] = ['label' => 'Distribuidor', 'class' => 'bg-primary-subtle text-primary'];
}

$estadoActivo = (int) ($tercero['estado'] ?? 0) === 1;

$mapaTiposDoc = [
    'CV' => 'Curriculum Vitae',
    'CONTRATO_LABORAL' => 'Contrato de Trabajo',
    'DNI' => 'Documento de Identidad',
    'ANTECEDENTES' => 'Antecedentes',
    'BOLETA' => 'Boleta de Pago',
    'TITULO' => 'Título Profesional',
    'COTIZACION' => 'Cotización',
    'FICHA_RUC' => 'Ficha RUC',
    'CATALOGO' => 'Catálogo',
    'CONTRATO_COM' => 'Contrato Comercial',
    'CONSTANCIA_BANCARIA' => 'Constancia Bancaria',
    'ORDEN_COMPRA' => 'Orden de Compra',
    'CONTRATO_SERV' => 'Contrato Servicio',
    'CONFORMIDAD' => 'Acta Conformidad',
    'EVAL_CREDITO' => 'Evaluación Crediticia',
    'OTRO' => 'Otros Documentos',
];

$basePublic = rtrim((string) base_url(), '/');
?>

<div class="container-fluid p-4 terceros-perfil-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 terceros-perfil-header">
        <div>
            <a href="?ruta=terceros" class="btn btn-outline-secondary btn-sm terceros-back-btn mb-2">
                <i class="bi bi-arrow-left me-1"></i> Volver a terceros
            </a>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-person-vcard-fill me-2 text-primary"></i> Perfil detallado
            </h1>
            <p class="text-muted small mb-0"><?php echo e($nombreCompleto); ?></p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm terceros-profile-card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="terceros-avatar-lg rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-3">
                            <?php echo e($inicial); ?>
                        </div>
                        <div>
                            <h2 class="h5 mb-1"><?php echo e($nombreCompleto); ?></h2>
                            <span class="badge <?php echo $estadoActivo ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?> border rounded-pill px-3 py-2">
                                <?php echo $estadoActivo ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($rolBadges as $rol): ?>
                            <span class="badge <?php echo e($rol['class']); ?> border"><?php echo e($rol['label']); ?></span>
                        <?php endforeach; ?>
                    </div>

                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Documento</span>
                            <strong><?php echo e(($tercero['tipo_documento'] ?? '') . ' ' . ($tercero['numero_documento'] ?? '')); ?></strong>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Teléfono</span>
                            <strong><?php echo e($tercero['telefono'] ?? '—'); ?></strong>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Email</span>
                            <strong><?php echo e($tercero['email'] ?? '—'); ?></strong>
                        </li>
                        <li class="list-group-item px-0">
                            <span class="text-muted d-block mb-1">Dirección</span>
                            <strong><?php echo e($tercero['direccion'] ?? '—'); ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm terceros-content-card">
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="perfilTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs-pane" type="button" role="tab">Documentos</button>
                        </li>
                        <?php if ((int) ($tercero['es_empleado'] ?? 0) === 1): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="hijos-tab" data-bs-toggle="tab" data-bs-target="#hijos-pane" type="button" role="tab">Hijos</button>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="docs-pane" role="tabpanel">
                            <form class="row g-2 mb-3" action="?ruta=terceros" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="accion" value="subir_documento">
                                <input type="hidden" name="tercero_id" value="<?php echo (int) ($tercero['id'] ?? 0); ?>">
                                <div class="col-12 col-md-4">
                                    <select class="form-select" name="tipo_documento" id="docTipoSelect" required>
                                        <option value="">Seleccione tipo...</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <input type="file" class="form-control" name="archivo" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <input type="text" class="form-control" name="observaciones" placeholder="Observaciones opcionales">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Subir documento</button>
                                </div>
                            </form>

                            <div class="row g-3 terceros-docs-layout">
                                <div class="col-12 col-lg-5 terceros-docs-sidebar">
                                    <input type="search" id="docSearch" class="form-control mb-2" placeholder="Buscar documento...">
                                    <div class="list-group">
                                        <?php if (empty($documentos)): ?>
                                            <div class="text-muted small p-3 border rounded">No hay documentos registrados.</div>
                                        <?php else: ?>
                                            <?php foreach ($documentos as $doc): ?>
                                                <?php
                                                $ruta = (string) ($doc['ruta_archivo'] ?? '');
                                                $url = $basePublic . '/' . ltrim($ruta, '/');
                                                $ext = strtolower((string) pathinfo($ruta, PATHINFO_EXTENSION));
                                                $tipoRaw = (string) ($doc['tipo_documento'] ?? 'OTRO');
                                                $tipoLabel = $mapaTiposDoc[$tipoRaw] ?? $tipoRaw;
                                                $obs = (string) ($doc['observaciones'] ?? '');
                                                $textoBusqueda = mb_strtolower($tipoLabel . ' ' . $obs . ' ' . basename($ruta));
                                                ?>
                                                <div class="list-group-item doc-item" data-url="<?php echo e($url); ?>" data-type="<?php echo e($ext); ?>" data-search="<?php echo e($textoBusqueda); ?>">
                                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                                        <div>
                                                            <h6 class="mb-1 small fw-bold"><?php echo e($tipoLabel); ?></h6>
                                                            <small class="text-muted d-block"><?php echo e($obs !== '' ? $obs : 'Sin observaciones'); ?></small>
                                                        </div>
                                                        <div class="d-flex gap-1">
                                                            <button type="button" class="btn btn-light btn-sm btn-edit-doc" data-id="<?php echo (int) $doc['id']; ?>" data-tipo="<?php echo e($tipoRaw); ?>" data-obs="<?php echo e($obs); ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <form class="form-eliminar-doc" action="?ruta=terceros" method="post">
                                                                <input type="hidden" name="accion" value="eliminar_documento">
                                                                <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                                                <input type="hidden" name="tercero_id" value="<?php echo (int) ($tercero['id'] ?? 0); ?>">
                                                                <button class="btn btn-light btn-sm text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-12 col-lg-7 terceros-visor-panel" id="visorContainer">
                                    <div id="visorToolbar" class="d-none mb-2 d-flex justify-content-between align-items-center">
                                        <small id="visorFileName" class="fw-semibold text-truncate"></small>
                                        <a id="visorBtnOpen" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Abrir</a>
                                    </div>
                                    <div id="visorPlaceholder" class="text-center text-muted py-5 border rounded">Selecciona un documento para previsualizar.</div>
                                    <iframe id="visorPDF" class="w-100 d-none border rounded" style="height: 420px;"></iframe>
                                    <img id="visorIMG" class="w-100 d-none rounded border terceros-visor-image" alt="Vista previa">
                                    <div id="visorExternal" class="d-none text-center py-5 border rounded">
                                        <p class="text-muted">Este archivo no se puede previsualizar aquí.</p>
                                        <a id="btnDescarga" class="btn btn-primary btn-sm" target="_blank" rel="noopener">Descargar / Abrir</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ((int) ($tercero['es_empleado'] ?? 0) === 1): ?>
                        <div class="tab-pane fade" id="hijos-pane" role="tabpanel">
                            <?php if (empty($hijosEmpleado)): ?>
                                <p class="text-muted mb-0">No hay hijos registrados.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead><tr><th>Nombre</th><th>Fecha Nacimiento</th><th>Estudia</th><th>Discapacidad</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($hijosEmpleado as $hijo): ?>
                                            <tr>
                                                <td><?php echo e($hijo['nombre_completo'] ?? ''); ?></td>
                                                <td><?php echo e($hijo['fecha_nacimiento'] ?? ''); ?></td>
                                                <td><?php echo !empty($hijo['esta_estudiando']) ? 'Sí' : 'No'; ?></td>
                                                <td><?php echo !empty($hijo['discapacidad']) ? 'Sí' : 'No'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarDoc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="?ruta=terceros" method="post">
            <input type="hidden" name="accion" value="editar_documento">
            <input type="hidden" name="id" id="editDocId" value="0">
            <input type="hidden" name="tercero_id" value="<?php echo (int) ($tercero['id'] ?? 0); ?>">
            <div class="modal-header">
                <h5 class="modal-title">Editar documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo_documento" id="editDocTipo" required>
                        <option value="">Seleccione tipo...</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Observaciones</label>
                    <textarea class="form-control" name="observaciones" id="editDocObs" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
window.perfilRoles = {
    empleado: <?php echo (int) ($tercero['es_empleado'] ?? 0); ?>,
    proveedor: <?php echo (int) ($tercero['es_proveedor'] ?? 0); ?>,
    cliente: <?php echo (int) ($tercero['es_cliente'] ?? 0); ?>
};
</script>
