<?php
$i = $item ?? [];
$docs = $documentos ?? [];

function showItemVal($val, string $fallback = '--'): string {
    $txt = trim((string) ($val ?? ''));
    return $txt !== '' ? htmlspecialchars($txt) : '<span class="text-muted fst-italic">' . htmlspecialchars($fallback) . '</span>';
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div class="d-flex align-items-center">
            <a href="?ruta=items" class="btn btn-outline-secondary me-3 shadow-sm rounded-circle" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Volver al listado">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0 text-dark">Expediente Digital de Ítem</h1>
                <p class="text-muted small mb-0">Información centralizada y documentos digitales.</p>
            </div>
        </div>
        <?php if ((int) ($i['estado'] ?? 0) === 1): ?>
            <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill">Activo</span>
        <?php else: ?>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-3 py-2 rounded-pill">Inactivo</span>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm sticky-top" style="top: 20px; z-index: 1;">
                <div class="card-body text-center p-4">
                    <div class="avatar-circle mx-auto mb-3 bg-primary text-white fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px; border-radius: 50%; font-size: 2.5rem;">
                        <?php echo strtoupper(substr((string) ($i['nombre'] ?? '?'), 0, 1)); ?>
                    </div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars((string) ($i['nombre'] ?? '')); ?></h5>
                    <p class="text-muted small mb-1">SKU: <?php echo htmlspecialchars((string) ($i['sku'] ?? '')); ?></p>
                    <p class="text-muted small mb-0">Tipo: <?php echo htmlspecialchars((string) ($i['tipo_item'] ?? '')); ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card border-0 shadow-sm" style="min-height: 600px;">
                <div class="card-header bg-white border-bottom pt-3 px-4">
                    <ul class="nav nav-pills card-header-pills" id="perfilTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill" id="gral-tab" data-bs-toggle="tab" data-bs-target="#tab-gral" type="button" role="tab">Información General</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill bg-primary bg-opacity-10 text-primary" id="docs-tab" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab"><i class="bi bi-folder2-open me-2"></i>Documentos Digitales</button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content h-100">
                        <div class="tab-pane fade show active p-4" id="tab-gral" role="tabpanel">
                            <h6 class="fw-bold text-primary mb-4 pb-2 border-bottom">Resumen del Ítem</h6>
                            <div class="row g-4">
                                <div class="col-md-6"><small class="text-muted d-block">Categoría</small><div class="fw-semibold"><?php echo showItemVal($i['categoria_nombre'] ?? ''); ?></div></div>
                                <div class="col-md-6"><small class="text-muted d-block">Marca</small><div class="fw-semibold"><?php echo showItemVal($i['marca'] ?? ''); ?></div></div>
                                <div class="col-md-4"><small class="text-muted d-block">Unidad base</small><div><?php echo showItemVal($i['unidad_base'] ?? ''); ?></div></div>
                                <div class="col-md-4"><small class="text-muted d-block">Moneda</small><div><?php echo showItemVal($i['moneda'] ?? ''); ?></div></div>
                                <div class="col-md-4"><small class="text-muted d-block">Impuesto</small><div><?php echo htmlspecialchars(number_format((float) ($i['impuesto'] ?? 0), 2)); ?>%</div></div>
                                <div class="col-md-6"><small class="text-muted d-block">Precio venta</small><div>S/ <?php echo htmlspecialchars(number_format((float) ($i['precio_venta'] ?? 0), 4)); ?></div></div>
                                <div class="col-md-6"><small class="text-muted d-block">Stock mínimo</small><div><?php echo htmlspecialchars(number_format((float) ($i['stock_minimo'] ?? 0), 4)); ?></div></div>
                                <div class="col-12"><small class="text-muted d-block">Descripción</small><div><?php echo showItemVal($i['descripcion'] ?? '', 'Sin descripción'); ?></div></div>
                            </div>
                        </div>

                        <div class="tab-pane fade h-100" id="tab-docs" role="tabpanel">
                            <div class="row g-0 h-100" style="min-height: 600px;">
                                <div class="col-md-4 col-12 border-end bg-light d-flex flex-column" style="height: 600px;">
                                    <div class="p-3 border-bottom bg-white">
                                        <h6 class="fw-bold mb-2"><i class="bi bi-cloud-upload me-2"></i>Subir Documento</h6>
                                        <form action="?ruta=items/perfil&id=<?php echo (int) ($i['id'] ?? 0); ?>" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="accion" value="subir_documento_item">
                                            <div class="mb-2">
                                                <select class="form-select form-select-sm" name="tipo_documento" id="docTipoSelect" required>
                                                    <option value="">Tipo...</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <input type="file" class="form-control form-control-sm" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                            </div>
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Subir</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="p-2 bg-light border-bottom">
                                        <input type="text" class="form-control form-control-sm" id="docSearch" placeholder="🔍 Buscar documento...">
                                    </div>

                                    <div class="flex-grow-1 overflow-auto p-0 scrollable-list">
                                        <div class="list-group list-group-flush" id="listaDocumentos">
                                            <?php if (empty($docs)): ?>
                                                <div class="text-center p-4 text-muted">
                                                    <i class="bi bi-folder2-open display-4 opacity-50"></i>
                                                    <p class="small mt-2">Sin documentos.</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($docs as $doc):
                                                    $ext = strtolower((string) ($doc['extension'] ?? ''));
                                                    $icon = 'bi-file-earmark';
                                                    if ($ext === 'pdf') $icon = 'bi-file-earmark-pdf text-danger';
                                                    elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)) $icon = 'bi-file-earmark-image text-primary';
                                                    elseif (in_array($ext, ['doc', 'docx'], true)) $icon = 'bi-file-earmark-word text-primary';
                                                    elseif (in_array($ext, ['xls', 'xlsx'], true)) $icon = 'bi-file-earmark-excel text-success';
                                                    $searchText = strtolower((string) (($doc['nombre_archivo'] ?? '') . ' ' . ($doc['tipo_documento'] ?? '')));
                                                ?>
                                                    <div class="list-group-item list-group-item-action doc-item py-3"
                                                        data-url="<?php echo htmlspecialchars((string) ($doc['ruta_archivo'] ?? '')); ?>"
                                                        data-type="<?php echo htmlspecialchars($ext); ?>"
                                                        data-search="<?php echo htmlspecialchars($searchText); ?>"
                                                        style="cursor:pointer;">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="text-truncate flex-grow-1 pe-2">
                                                                <h6 class="mb-1 text-dark fw-semibold small text-truncate"><i class="bi <?php echo $icon; ?> me-1"></i><?php echo htmlspecialchars((string) ($doc['nombre_archivo'] ?? 'Documento')); ?></h6>
                                                                <div class="d-flex align-items-center flex-wrap gap-1">
                                                                    <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars((string) ($doc['tipo_documento'] ?? 'OTRO')); ?></span>
                                                                    <small class="text-muted" style="font-size:0.7rem;"><?php echo !empty($doc['created_at']) ? date('d/m/Y', strtotime((string) $doc['created_at'])) : ''; ?></small>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex gap-1">
                                                                <button type="button" class="btn btn-sm btn-outline-primary btn-icon-sm btn-edit-doc" title="Editar" data-id="<?php echo (int) ($doc['id'] ?? 0); ?>" data-tipo="<?php echo htmlspecialchars((string) ($doc['tipo_documento'] ?? '')); ?>">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <form action="?ruta=items/perfil&id=<?php echo (int) ($i['id'] ?? 0); ?>" method="POST" class="form-eliminar-doc d-inline">
                                                                    <input type="hidden" name="accion" value="eliminar_documento_item">
                                                                    <input type="hidden" name="id_documento" value="<?php echo (int) ($doc['id'] ?? 0); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-8 col-12 bg-secondary bg-opacity-10 d-flex flex-column" id="visorContainer" style="height: 600px;">
                                    <div id="visorToolbar" class="bg-white border-bottom p-2 d-flex justify-content-between align-items-center d-none">
                                        <span class="small fw-bold text-muted" id="visorFileName">Documento</span>
                                        <a href="#" id="visorBtnOpen" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir Externamente</a>
                                    </div>
                                    <div class="flex-grow-1 position-relative d-flex align-items-center justify-content-center overflow-hidden">
                                        <div class="text-center text-muted p-4" id="visorPlaceholder"><i class="bi bi-eye display-1 opacity-25"></i><p class="mt-3 fs-5">Selecciona un documento para visualizarlo</p></div>
                                        <iframe id="visorPDF" src="" class="d-none w-100 h-100 border-0"></iframe>
                                        <img id="visorIMG" src="" class="d-none img-fluid shadow-sm rounded" style="max-height: 95%; max-width: 95%;">
                                        <div id="visorExternal" class="d-none text-center">
                                            <i class="bi bi-file-earmark-arrow-down display-1 text-primary"></i>
                                            <p class="mt-3">Este archivo no se puede previsualizar aquí.</p>
                                            <a href="#" id="btnDescarga" target="_blank" class="btn btn-primary"><i class="bi bi-download me-2"></i>Descargar Archivo</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarDoc" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold">Editar Documento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="?ruta=items/perfil&id=<?php echo (int) ($i['id'] ?? 0); ?>" method="POST">
                <input type="hidden" name="accion" value="editar_documento_item">
                <input type="hidden" name="id_documento" id="editDocId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Tipo</label>
                        <select class="form-select form-select-sm" name="tipo_documento" id="editDocTipo" required></select>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-link text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
