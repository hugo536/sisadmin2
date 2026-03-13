<?php
$i = $item ?? [];
$docs = $documentos ?? [];

function showItemVal($val, string $fallback = '--'): string {
    $txt = trim((string) ($val ?? ''));
    return $txt !== '' ? htmlspecialchars($txt) : '<span class="text-muted fst-italic">' . htmlspecialchars($fallback) . '</span>';
}
?>

<div class="container-fluid p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 fade-in">
        <div class="d-flex align-items-center">
            <a href="?ruta=items" class="btn btn-outline-secondary me-3 bg-white shadow-sm border-secondary-subtle" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%;" title="Volver al listado">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0 text-dark">Expediente Digital de Ítem</h1>
                <p class="text-muted small mb-0">Información centralizada y documentos digitales.</p>
            </div>
        </div>
        <div>
            <?php if ((int) ($i['estado'] ?? 0) === 1): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-6 shadow-sm">Activo</span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill fs-6 shadow-sm">Inactivo</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card border-secondary-subtle shadow-sm sticky-top" style="top: 20px; z-index: 1;">
                <div class="card-body text-center p-4">
                    <div class="avatar-circle mx-auto mb-3 bg-primary text-white fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px; border-radius: 50%; font-size: 2.5rem;">
                        <?php echo strtoupper(substr((string) ($i['nombre'] ?? '?'), 0, 1)); ?>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark"><?php echo htmlspecialchars((string) ($i['nombre'] ?? '')); ?></h5>
                    <div class="bg-light border border-secondary-subtle rounded-3 p-2 mb-2">
                        <small class="text-muted d-block fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">SKU</small>
                        <span class="fw-bold text-primary"><?php echo htmlspecialchars((string) ($i['sku'] ?? '')); ?></span>
                    </div>
                    <p class="text-muted small mb-0 fw-medium">Tipo: <?php echo htmlspecialchars((string) ($i['tipo_item'] ?? '')); ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card border-secondary-subtle shadow-sm overflow-hidden" style="min-height: 600px;">
                <div class="card-header bg-white border-bottom border-secondary-subtle pt-3 px-4 pb-2">
                    <ul class="nav nav-pills card-header-pills gap-2" id="perfilTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill fw-semibold shadow-sm" id="gral-tab" data-bs-toggle="tab" data-bs-target="#tab-gral" type="button" role="tab">Información General</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill text-secondary border border-secondary-subtle fw-semibold hover-primary bg-light" id="docs-tab" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
                                <i class="bi bi-folder2-open me-2"></i>Documentos Digitales
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content h-100">
                        
                        <div class="tab-pane fade show active p-4" id="tab-gral" role="tabpanel">
                            <h6 class="fw-bold text-dark mb-4 pb-2 border-bottom border-secondary-subtle">
                                <i class="bi bi-info-circle text-primary me-2"></i>Resumen del Ítem
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Categoría</small>
                                        <div class="fw-bold text-dark fs-6"><?php echo showItemVal($i['categoria_nombre'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Marca</small>
                                        <div class="fw-bold text-dark fs-6"><?php echo showItemVal($i['marca'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Unidad base</small>
                                        <div class="fw-medium text-dark"><?php echo showItemVal($i['unidad_base'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Moneda</small>
                                        <div class="fw-medium text-dark"><?php echo showItemVal($i['moneda'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Impuesto</small>
                                        <div class="fw-medium text-dark"><?php echo htmlspecialchars(number_format((float) ($i['impuesto'] ?? 0), 2)); ?>%</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Peso Bruto</small>
                                        <div class="fw-medium text-dark"><?php echo htmlspecialchars(number_format((float) ($i['peso_kg'] ?? 0), 3)); ?> kg</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Precio venta</small>
                                        <div class="fw-medium text-success fs-6">S/ <?php echo htmlspecialchars(number_format((float) ($i['precio_venta'] ?? 0), 4)); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Stock mínimo</small>
                                        <div class="fw-medium text-dark"><?php echo htmlspecialchars(number_format((float) ($i['stock_minimo'] ?? 0), 4)); ?></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                        <small class="text-muted d-block fw-semibold mb-1">Descripción Técnica</small>
                                        <div class="text-dark"><?php echo showItemVal($i['descripcion'] ?? '', 'Sin descripción registrada.'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade h-100" id="tab-docs" role="tabpanel">
                            <div class="row g-0 h-100" style="min-height: 600px;">
                                
                                <div class="col-md-5 col-lg-4 border-end border-secondary-subtle bg-light d-flex flex-column h-100">
                                    
                                    <div class="p-3 border-bottom border-secondary-subtle bg-white">
                                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Subir Documento</h6>
                                        <form action="?ruta=items/perfil&id=<?php echo (int) ($i['id'] ?? 0); ?>" method="POST" enctype="multipart/form-data" class="bg-light p-2 rounded-3 border border-secondary-subtle">
                                            <input type="hidden" name="accion" value="subir_documento_item">
                                            <div class="mb-2">
                                                <select class="form-select form-select-sm shadow-none border-secondary-subtle" name="tipo_documento" id="docTipoSelect" required>
                                                    <option value="">Tipo de documento...</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <input type="file" class="form-control form-control-sm shadow-none border-secondary-subtle" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                            </div>
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-primary btn-sm fw-semibold shadow-sm"><i class="bi bi-upload me-2"></i>Subir Archivo</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="p-2 bg-white border-bottom border-secondary-subtle shadow-sm z-index-1">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                                            <input type="text" class="form-control border-secondary-subtle border-start-0 shadow-none bg-light" id="docSearch" placeholder="Buscar documento...">
                                        </div>
                                    </div>

                                    <div class="flex-grow-1 overflow-auto p-0 scrollable-list bg-white">
                                        <div class="list-group list-group-flush" id="listaDocumentos">
                                            <?php if (empty($docs)): ?>
                                                <div class="text-center p-5 text-muted">
                                                    <i class="bi bi-folder-x display-4 opacity-25"></i>
                                                    <p class="small mt-3 fw-medium">No hay documentos registrados.</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($docs as $doc):
                                                    $ext = strtolower((string) ($doc['extension'] ?? ''));
                                                    $icon = 'bi-file-earmark text-secondary';
                                                    if ($ext === 'pdf') $icon = 'bi-file-earmark-pdf-fill text-danger';
                                                    elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)) $icon = 'bi-file-earmark-image-fill text-primary';
                                                    elseif (in_array($ext, ['doc', 'docx'], true)) $icon = 'bi-file-earmark-word-fill text-info';
                                                    elseif (in_array($ext, ['xls', 'xlsx'], true)) $icon = 'bi-file-earmark-excel-fill text-success';
                                                    
                                                    $searchText = strtolower((string) (($doc['nombre_archivo'] ?? '') . ' ' . ($doc['tipo_documento'] ?? '')));
                                                ?>
                                                    <div class="list-group-item list-group-item-action doc-item p-3 border-bottom border-secondary-subtle"
                                                        data-url="<?php echo htmlspecialchars((string) ($doc['ruta_archivo'] ?? '')); ?>"
                                                        data-type="<?php echo htmlspecialchars($ext); ?>"
                                                        data-search="<?php echo htmlspecialchars($searchText); ?>"
                                                        style="cursor:pointer; transition: background-color 0.2s;">
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="text-truncate flex-grow-1 pe-2">
                                                                <h6 class="mb-1 text-dark fw-bold small text-truncate d-flex align-items-center">
                                                                    <i class="bi <?php echo $icon; ?> me-2 fs-5"></i>
                                                                    <?php echo htmlspecialchars((string) ($doc['nombre_archivo'] ?? 'Documento')); ?>
                                                                </h6>
                                                                <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                                                    <span class="badge bg-light text-secondary border border-secondary-subtle fw-medium"><?php echo htmlspecialchars((string) ($doc['tipo_documento'] ?? 'OTRO')); ?></span>
                                                                    <small class="text-muted fw-medium" style="font-size:0.7rem;"><?php echo !empty($doc['created_at']) ? date('d/m/Y', strtotime((string) $doc['created_at'])) : ''; ?></small>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex gap-1 flex-shrink-0">
                                                                <button type="button" class="btn-icon btn-icon-primary btn-edit-doc" title="Editar Categoría" data-id="<?php echo (int) ($doc['id'] ?? 0); ?>" data-tipo="<?php echo htmlspecialchars((string) ($doc['tipo_documento'] ?? '')); ?>">
                                                                    <i class="bi bi-pencil-square"></i>
                                                                </button>
                                                                <form action="?ruta=items/perfil&id=<?php echo (int) ($i['id'] ?? 0); ?>" method="POST" class="form-eliminar-doc d-inline m-0 p-0">
                                                                    <input type="hidden" name="accion" value="eliminar_documento_item">
                                                                    <input type="hidden" name="id_documento" value="<?php echo (int) ($doc['id'] ?? 0); ?>">
                                                                    <button type="submit" class="btn-icon btn-icon-danger" title="Eliminar Archivo">
                                                                        <i class="bi bi-trash3"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-7 col-lg-8 bg-light d-flex flex-column h-100 position-relative" id="visorContainer">
                                    <div id="visorToolbar" class="bg-white border-bottom border-secondary-subtle p-2 px-3 d-flex justify-content-between align-items-center d-none shadow-sm z-index-1">
                                        <span class="small fw-bold text-dark text-truncate pe-3" id="visorFileName">Documento</span>
                                        <a href="#" id="visorBtnOpen" target="_blank" class="btn btn-sm btn-outline-primary fw-medium text-nowrap">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>Abrir Externamente
                                        </a>
                                    </div>
                                    
                                    <div class="flex-grow-1 position-relative d-flex align-items-center justify-content-center overflow-hidden bg-secondary bg-opacity-10">
                                        <div class="text-center text-muted p-5" id="visorPlaceholder">
                                            <i class="bi bi-file-earmark-text display-1 opacity-25 mb-3 d-block"></i>
                                            <h5 class="fw-bold text-secondary">Visor de Documentos</h5>
                                            <p class="small text-muted">Selecciona un archivo de la lista para visualizarlo aquí.</p>
                                        </div>
                                        
                                        <iframe id="visorPDF" src="" class="d-none w-100 h-100 border-0 shadow-sm bg-white"></iframe>
                                        <img id="visorIMG" src="" class="d-none img-fluid shadow rounded" style="max-height: 90%; max-width: 90%; object-fit: contain;">
                                        
                                        <div id="visorExternal" class="d-none text-center bg-white p-5 rounded-4 shadow-sm border border-secondary-subtle">
                                            <i class="bi bi-cloud-download display-1 text-primary mb-3 d-block"></i>
                                            <h5 class="fw-bold text-dark">Archivo no visualizable</h5>
                                            <p class="text-muted small mb-4">El formato de este archivo no soporta previsualización en el navegador.</p>
                                            <a href="#" id="btnDescarga" target="_blank" class="btn btn-primary fw-bold shadow-sm px-4 py-2">
                                                <i class="bi bi-download me-2"></i>Descargar Archivo Seguro
                                            </a>
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
            <div class="modal-header bg-light py-3 border-bottom border-secondary-subtle">
                <h6 class="modal-title fw-bold text-dark mb-0"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Documento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form action="?ruta=items/perfil&id=<?php echo (int) ($i['id'] ?? 0); ?>" method="POST" class="m-0">
                <input type="hidden" name="accion" value="editar_documento_item">
                <input type="hidden" name="id_documento" id="editDocId">
                <div class="modal-body p-4 bg-white">
                    <div class="form-floating">
                        <select class="form-select shadow-none border-secondary-subtle" name="tipo_documento" id="editDocTipo" required></select>
                        <label class="text-muted fw-semibold">Tipo de documento <span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light border-top border-secondary-subtle d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border border-secondary-subtle text-secondary fw-medium shadow-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>