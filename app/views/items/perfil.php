<?php
$i = $item ?? [];
$docs = $documentos ?? [];
// Nueva variable que vendrá del controlador
$historialCostos = $historial_costos ?? []; 

function showItemVal($val, string $fallback = '--'): string {
    $txt = trim((string) ($val ?? ''));
    return $txt !== '' ? htmlspecialchars($txt) : '<span class="text-muted fst-italic">' . htmlspecialchars($fallback) . '</span>';
}
?>

<div class="container-fluid p-4">
    
    <!-- 1. ENCABEZADO SUPERIOR (Acciones) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Expediente Digital de Ítem
            </h1>
            <p class="text-muted small mb-0 ms-1">Información centralizada, costos y documentos digitales.</p>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <a href="?ruta=items" 
               onclick="if(typeof window.navigateWithoutReload === 'function') { event.preventDefault(); window.navigateWithoutReload(new window.URL(this.href, window.location.origin), true); }"
               class="btn btn-light bg-white border border-secondary-subtle shadow-sm text-secondary fw-medium px-3 transition-hover d-flex align-items-center">
                <i class="bi bi-arrow-left me-2"></i>Regresar al catálogo
            </a>
        </div>
    </div>

    <!-- 2. NUEVA CABECERA HORIZONTAL DEL ÍTEM (Top Banner) -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-primary border-4 fade-in">
        <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center gap-4">
            <!-- Avatar -->
            <div class="avatar-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 80px; height: 80px; border-radius: 50%; font-size: 2.2rem;">
                <?php echo strtoupper(substr((string) ($i['nombre'] ?? '?'), 0, 1)); ?>
            </div>
            
            <!-- Información Principal -->
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                    <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars((string) ($i['nombre'] ?? '')); ?></h4>
                    <?php if ((int) ($i['estado'] ?? 0) === 1): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill shadow-sm">Activo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill shadow-sm">Inactivo</span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex flex-wrap align-items-center gap-4 mt-2">
                    <div class="d-flex align-items-center">
                        <small class="text-muted fw-semibold me-2">SKU:</small>
                        <span class="badge bg-light text-primary border border-secondary-subtle px-2 py-1 fs-6 shadow-sm"><?php echo htmlspecialchars((string) ($i['sku'] ?? '')); ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <small class="text-muted fw-semibold me-2">Tipo:</small>
                        <span class="text-dark fw-medium"><i class="bi bi-tag text-muted me-1"></i><?php echo htmlspecialchars((string) ($i['tipo_item'] ?? '')); ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <small class="text-muted fw-semibold me-2">Categoría:</small>
                        <span class="text-dark fw-medium"><i class="bi bi-folder2 text-muted me-1"></i><?php echo showItemVal($i['categoria_nombre'] ?? ''); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. SECCIÓN DE PESTAÑAS (Ancho completo al 100%) -->
    <div class="fade-in">
        <!-- Navegación de Pestañas -->
        <div class="bg-light pt-2 px-3 rounded-top border border-bottom-0 overflow-auto scrollbar-hide">
            <ul class="nav nav-tabs border-bottom-0 mb-0 flex-nowrap text-nowrap" id="perfilTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link fs-6 fw-semibold py-3 px-4 active text-primary border-bottom-0 bg-white" id="gral-tab" data-bs-toggle="tab" data-bs-target="#tab-gral" type="button" role="tab">
                        <i class="bi bi-info-circle me-2"></i>Información Comercial
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fs-6 fw-semibold py-3 px-4 text-muted border-0 bg-transparent transition-hover" id="docs-tab" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
                        <i class="bi bi-folder2-open me-2"></i>Documentos Digitales
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fs-6 fw-semibold py-3 px-4 text-muted border-0 bg-transparent transition-hover" id="costos-tab" data-bs-toggle="tab" data-bs-target="#tab-costos" type="button" role="tab">
                        <i class="bi bi-graph-up-arrow me-2"></i>Costos y Fluctuación
                    </button>
                </li>
            </ul>
        </div>

        <!-- Contenedor Principal de Pestañas -->
        <div class="card border-0 shadow-sm rounded-top-0 border-top border-primary border-3" style="min-height: 500px;">
            <div class="card-body p-0">
                <div class="tab-content h-100">
                    
                    <!-- TAB 1: INFORMACIÓN GENERAL -->
                    <div class="tab-pane fade show active p-4 p-lg-5" id="tab-gral" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-1">Marca</small>
                                    <div class="fw-bold text-dark fs-6"><?php echo showItemVal($i['marca'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-1">Unidad base</small>
                                    <div class="fw-medium text-dark"><?php echo showItemVal($i['unidad_base'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-1">Moneda</small>
                                    <div class="fw-medium text-dark"><?php echo showItemVal($i['moneda'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-1">Impuesto</small>
                                    <div class="fw-medium text-dark"><?php echo htmlspecialchars(number_format((float) ($i['impuesto'] ?? 0), 2)); ?>%</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-1">Precio venta</small>
                                    <div class="fw-bold text-success fs-5">S/ <?php echo htmlspecialchars(number_format((float) ($i['precio_venta'] ?? 0), 4)); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-1">Stock mínimo</small>
                                    <div class="fw-medium text-dark fs-5"><?php echo htmlspecialchars(number_format((float) ($i['stock_minimo'] ?? 0), 2)); ?></div>
                                </div>
                            </div>
                            <div class="col-md-12 col-lg-4">
                                <div class="p-3 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-1">Peso Bruto</small>
                                    <div class="fw-medium text-dark fs-5"><?php echo htmlspecialchars(number_format((float) ($i['peso_kg'] ?? 0), 3)); ?> kg</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="p-4 bg-light rounded-3 border border-secondary-subtle h-100">
                                    <small class="text-muted d-block fw-semibold mb-2"><i class="bi bi-card-text me-2"></i>Descripción Técnica</small>
                                    <div class="text-dark" style="white-space: pre-line;"><?php echo showItemVal($i['descripcion'] ?? '', 'Sin descripción registrada.'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: DOCUMENTOS -->
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

                    <!-- TAB 3: COSTOS E HISTORIAL -->
                    <div class="tab-pane fade p-4 p-lg-5" id="tab-costos" role="tabpanel">
                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <div class="card bg-light border-secondary-subtle shadow-sm h-100 border-start border-primary border-4">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary-subtle p-2 rounded me-3 text-primary">
                                                <i class="bi bi-archive-fill fs-5"></i>
                                            </div>
                                            <small class="text-muted fw-bold text-uppercase" style="letter-spacing: 0.5px;">Costo Promedio Actual</small>
                                        </div>
                                        <h3 class="fw-bold text-dark mb-0">S/ <?php echo htmlspecialchars(number_format((float) ($i['costo_promedio'] ?? 0), 4)); ?></h3>
                                        <small class="text-muted mt-2 d-block">Valor contable en stock actual</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php 
                                $costoPromedio = (float) ($i['costo_promedio'] ?? 0);
                                $ultimoCosto = (float) ($i['ultimo_costo_compra'] ?? 0);
                                $colorUltimoCosto = 'text-dark';
                                $borderIndicador = 'border-secondary';
                                $iconoTendencia = '';
                                
                                if ($ultimoCosto > $costoPromedio && $costoPromedio > 0) {
                                    $colorUltimoCosto = 'text-danger';
                                    $borderIndicador = 'border-danger';
                                    $iconoTendencia = '<i class="bi bi-arrow-up-right ms-1"></i>';
                                } elseif ($ultimoCosto < $costoPromedio && $ultimoCosto > 0) {
                                    $colorUltimoCosto = 'text-success';
                                    $borderIndicador = 'border-success';
                                    $iconoTendencia = '<i class="bi bi-arrow-down-right ms-1"></i>';
                                }
                                ?>
                                <div class="card bg-light border-secondary-subtle shadow-sm h-100 border-start <?php echo $borderIndicador; ?> border-4">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-secondary-subtle p-2 rounded me-3 text-secondary">
                                                <i class="bi bi-receipt-cutoff fs-5"></i>
                                            </div>
                                            <small class="text-muted fw-bold text-uppercase" style="letter-spacing: 0.5px;">Último Costo de Compra</small>
                                        </div>
                                        <h3 class="fw-bold <?php echo $colorUltimoCosto; ?> mb-0">
                                            S/ <?php echo htmlspecialchars(number_format($ultimoCosto, 4)) . $iconoTendencia; ?>
                                        </h3>
                                        <small class="text-muted mt-2 d-block">Precio pagado al proveedor en la última O.C.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-primary bg-opacity-10 border-primary border-opacity-25 shadow-sm h-100 border-start border-primary border-4">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-white p-2 rounded me-3 text-primary shadow-sm">
                                                <i class="bi bi-pie-chart-fill fs-5"></i>
                                            </div>
                                            <small class="text-primary fw-bold text-uppercase" style="letter-spacing: 0.5px;">Margen vs Venta</small>
                                        </div>
                                        <?php 
                                            $precioVenta = (float) ($i['precio_venta'] ?? 0);
                                            $margen = 0;
                                            if ($precioVenta > 0 && $ultimoCosto > 0) {
                                                $margen = (($precioVenta - $ultimoCosto) / $precioVenta) * 100;
                                            }
                                        ?>
                                        <h3 class="fw-bold text-primary mb-0"><?php echo number_format($margen, 2); ?>%</h3>
                                        <small class="text-primary opacity-75 mt-2 d-block">Margen comercial proyectado</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Evolución -->
                        <div class="card border-secondary-subtle shadow-sm mb-5">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-4"><i class="bi bi-graph-up text-primary me-2"></i>Curva de Evolución del Costo</h6>
                                <div style="height: 300px; width: 100%;">
                                    <canvas id="chartPerfilCosto" 
                                            data-historial='<?php echo htmlspecialchars(json_encode($historialCostos), ENT_QUOTES, 'UTF-8'); ?>'>
                                    </canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla -->
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table text-primary me-2"></i>Historial de Recepciones y Fluctuación</h6>
                        <div class="table-responsive bg-white border border-secondary-subtle rounded-3 shadow-sm d-flex flex-column">
                            <table class="table align-middle mb-0 table-hover table-pro" id="tablaPerfilCostos" data-erp-table="true" data-rows-per-page="10">
                                <thead class="table-light border-bottom border-secondary-subtle">
                                    <tr>
                                        <th class="text-secondary fw-semibold py-3 px-4">Fecha</th>
                                        <th class="text-secondary fw-semibold py-3">Movimiento</th>
                                        <th class="text-secondary fw-semibold py-3 text-end">Cant.</th>
                                        <th class="text-secondary fw-semibold py-3 text-end">Promedio Ant.</th>
                                        <th class="text-secondary fw-semibold py-3 text-end text-primary">Precio Compra</th>
                                        <th class="text-secondary fw-semibold py-3 text-end pe-4 text-success">Nuevo Costo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($historialCostos)): ?>
                                        <tr class="empty-msg-row">
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="bi bi-clock-history fs-3 d-block mb-2 opacity-50"></i>
                                                No hay registros de compras recientes que afecten el costo.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($historialCostos as $mov): ?>
                                            <tr class="border-bottom">
                                                <td class="px-4 text-muted fw-medium"><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border border-secondary-subtle shadow-sm">
                                                        <?php echo htmlspecialchars($mov['tipo_movimiento']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end fw-semibold text-dark"><?php echo number_format((float)$mov['cantidad'], 2); ?></td>
                                                <td class="text-end text-muted">S/ <?php echo number_format((float)$mov['costo_promedio_anterior'], 4); ?></td>
                                                <td class="text-end fw-bold text-primary">S/ <?php echo number_format((float)$mov['precio_compra'], 4); ?></td>
                                                <td class="text-end pe-4 fw-bold text-success">
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 shadow-sm">S/ <?php echo number_format((float)$mov['costo_promedio_resultante'], 4); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                                <small class="text-muted fw-semibold" id="tablaPerfilCostosPaginationInfo"></small>
                                <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaPerfilCostosPaginationControls"></ul></nav>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Documento -->
<div class="modal fade" id="modalEditarDoc" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
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