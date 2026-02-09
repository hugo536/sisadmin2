<?php
// Datos pasados por el controlador
$t = $tercero ?? [];
$docs = $documentos ?? [];

// Helpers de roles
$esEmpleado = (int)($t['es_empleado'] ?? 0);
$esCliente = (int)($t['es_cliente'] ?? 0);
$esProveedor = (int)($t['es_proveedor'] ?? 0);
$esDistribuidor = (int)($t['es_distribuidor'] ?? 0);

// Helper para mostrar texto vacío
function showVal($val, $suffix = '') {
    return !empty($val) ? htmlspecialchars($val) . $suffix : '<span class="text-muted fst-italic">--</span>';
}
?>

<div class="container-fluid p-4">
    <!-- CABECERA -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div class="d-flex align-items-center">
            <a href="?ruta=terceros" class="btn btn-outline-secondary me-3 shadow-sm rounded-circle" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Volver al listado">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0 text-dark">Expediente Digital</h1>
                <p class="text-muted small mb-0">Visualización centralizada de información y documentos.</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <small class="text-muted d-block">Última actualización</small>
                <small class="fw-bold text-dark"><?php echo isset($t['updated_at']) ? date('d/m/Y H:i', strtotime($t['updated_at'])) : date('d/m/Y'); ?></small>
            </div>
            <?php if ((int)$t['estado'] === 1): ?>
                <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill">Activo</span>
            <?php else: ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-3 py-2 rounded-pill">Inactivo</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- COLUMNA IZQUIERDA: TARJETA PERFIL (Sticky) -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm sticky-top" style="top: 20px; z-index: 1;">
                <div class="card-body text-center p-4">
                    <div class="avatar-circle mx-auto mb-3 bg-primary text-white fw-bold d-flex align-items-center justify-content-center shadow-sm" 
                         style="width: 100px; height: 100px; border-radius: 50%; font-size: 2.5rem;">
                        <?php echo strtoupper(substr((string) ($t['nombre_completo'] ?? '?'), 0, 1)); ?>
                    </div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($t['nombre_completo'] ?? ''); ?></h5>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($t['tipo_documento'] ?? ''); ?>: <?php echo htmlspecialchars($t['numero_documento'] ?? ''); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4 flex-wrap">
                        <?php if ($esCliente): ?><span class="badge bg-info text-dark">Cliente</span><?php endif; ?>
                        <?php if ($esDistribuidor): ?><span class="badge bg-primary">Distribuidor</span><?php endif; ?>
                        <?php if ($esProveedor): ?><span class="badge bg-warning text-dark">Proveedor</span><?php endif; ?>
                        <?php if ($esEmpleado): ?><span class="badge bg-success">Empleado</span><?php endif; ?>
                    </div>

                    <div class="d-grid gap-2">
                        <?php if(!empty($t['email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($t['email']); ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-envelope me-2"></i>Enviar Email
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light p-3 border-top-0">
                    <h6 class="small fw-bold text-muted text-uppercase mb-3">Información de Contacto</h6>
                    <ul class="list-unstyled small mb-0 text-start">
                        <li class="mb-2 d-flex">
                            <i class="bi bi-geo-alt text-primary me-2"></i>
                            <span class="text-muted text-break"><?php echo showVal($t['direccion']); ?></span>
                        </li>
                        <li class="mb-2 d-flex">
                            <i class="bi bi-map text-primary me-2"></i>
                            <span class="text-muted">
                                <?php 
                                    $ubigeo = array_filter([$t['distrito'], $t['provincia'], $t['departamento']]);
                                    echo !empty($ubigeo) ? implode(', ', $ubigeo) : '--'; 
                                ?>
                            </span>
                        </li>
                        <?php if(!empty($t['telefono'])): ?>
                        <li class="d-flex">
                            <i class="bi bi-phone text-primary me-2"></i>
                            <span class="text-muted"><?php echo htmlspecialchars($t['telefono']); ?></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CONTENIDO DETALLADO -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm" style="min-height: 600px;">
                <div class="card-header bg-white border-bottom pt-3 px-4">
                    <ul class="nav nav-pills card-header-pills" id="perfilTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill" id="gral-tab" data-bs-toggle="tab" data-bs-target="#tab-gral" type="button" role="tab">Información General</button>
                        </li>
                        <?php if ($esEmpleado): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill" id="lab-tab" data-bs-toggle="tab" data-bs-target="#tab-lab" type="button" role="tab">Datos Laborales</button>
                        </li>
                        <?php endif; ?>
                        <?php if ($esCliente || $esProveedor): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill" id="com-tab" data-bs-toggle="tab" data-bs-target="#tab-com" type="button" role="tab">Datos Comerciales</button>
                        </li>
                        <?php endif; ?>
                        <?php if ($esDistribuidor): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill" id="dist-tab" data-bs-toggle="tab" data-bs-target="#tab-dist" type="button" role="tab">Distribuidor</button>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill bg-primary bg-opacity-10 text-primary" id="docs-tab" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
                                <i class="bi bi-folder2-open me-2"></i>Documentos Digitales
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content h-100" id="perfilTabsContent">
                        
                        <!-- TAB 1: INFORMACIÓN GENERAL -->
                        <div class="tab-pane fade show active p-4" id="tab-gral" role="tabpanel">
                            <h6 class="fw-bold text-primary mb-4 pb-2 border-bottom">Resumen General</h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label class="text-uppercase text-muted small fw-bold mb-1">Tipo de Persona</label>
                                        <div class="fs-6"><?php echo showVal($t['tipo_persona']); ?></div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="text-uppercase text-muted small fw-bold mb-1">Identificación</label>
                                        <div class="fs-6 fw-semibold text-dark"><?php echo showVal($t['tipo_documento']); ?>: <?php echo showVal($t['numero_documento']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label class="text-uppercase text-muted small fw-bold mb-1">Rubro / Sector</label>
                                        <div class="fs-6"><?php echo showVal($t['rubro_sector']); ?></div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="text-uppercase text-muted small fw-bold mb-1">Email Principal</label>
                                        <div class="fs-6 text-primary"><?php echo showVal($t['email']); ?></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="bg-light p-3 rounded border">
                                        <label class="text-uppercase text-muted small fw-bold mb-2">Notas</label>
                                        <p class="mb-0 text-muted fst-italic"><?php echo !empty($t['observaciones']) ? nl2br(htmlspecialchars($t['observaciones'])) : 'Sin observaciones.'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: DATOS LABORALES -->
                        <?php if ($esEmpleado): ?>
                        <div class="tab-pane fade p-4" id="tab-lab" role="tabpanel">
                            <h6 class="fw-bold text-success mb-4 pb-2 border-bottom">Ficha del Empleado</h6>
                            <div class="row g-4">
                                <div class="col-md-4"><div class="p-3 border rounded bg-light h-100"><small class="text-muted d-block mb-1">Cargo</small><div class="fw-bold"><?php echo showVal($t['cargo']); ?></div></div></div>
                                <div class="col-md-4"><div class="p-3 border rounded bg-light h-100"><small class="text-muted d-block mb-1">Área</small><div class="fw-bold"><?php echo showVal($t['area']); ?></div></div></div>
                                <div class="col-md-4"><div class="p-3 border rounded bg-light h-100"><small class="text-muted d-block mb-1">Estado</small><?php echo ($t['estado_laboral']??'')==='activo'?'<span class="badge bg-success">Activo</span>':'<span class="badge bg-danger">'.ucfirst($t['estado_laboral']??'').'</span>'; ?></div></div>
                                <div class="col-md-4"><label class="text-muted small fw-bold">Fecha Ingreso</label><div><?php echo showVal($t['fecha_ingreso']); ?></div></div>
                                <div class="col-md-4"><label class="text-muted small fw-bold">Sueldo</label><div class="fw-bold"><?php echo ($t['moneda']??'PEN')==='USD'?'$':'S/'; ?> <?php echo number_format((float)($t['sueldo_basico']??0),2); ?></div></div>
                                <div class="col-md-4"><label class="text-muted small fw-bold">AFP/ONP</label><div><?php echo showVal(str_replace('_',' ',$t['regimen_pensionario']??'')); ?></div></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($esDistribuidor): ?>
                        <div class="tab-pane fade p-4" id="tab-dist" role="tabpanel">
                            <h6 class="fw-bold text-primary mb-4 pb-2 border-bottom">Información de Distribuidor</h6>
                            <div class="row g-4">
                                <div class="col-md-8">
                                    <div class="p-3 border rounded bg-light h-100">
                                        <small class="text-muted d-block mb-1">Zona exclusiva</small>
                                        <div class="fw-bold"><?php echo showVal($t['distribuidor_zona_exclusiva'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded bg-light h-100">
                                        <small class="text-muted d-block mb-1">Meta de volumen</small>
                                        <div class="fw-bold"><?php echo number_format((float)($t['distribuidor_meta_volumen'] ?? 0), 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- TAB 3: DATOS COMERCIALES -->
                        <?php if ($esCliente || $esProveedor): ?>
                        <div class="tab-pane fade p-4" id="tab-com" role="tabpanel">
                            <h6 class="fw-bold text-dark mb-4 pb-2 border-bottom">Información Comercial</h6>
                            <div class="row g-4">
                                <?php if($esCliente): ?>
                                <div class="col-md-6"><div class="badge bg-primary mb-2">Cliente</div><dl class="row mb-0"><dt class="col-sm-6 fw-normal text-muted">Crédito:</dt><dd class="col-sm-6 fw-bold">S/ <?php echo number_format((float)($t['cliente_limite_credito']??0),2); ?></dd></dl></div>
                                <?php endif; ?>
                                <?php if($esProveedor): ?>
                                <div class="col-md-6"><div class="badge bg-warning text-dark mb-2">Proveedor</div><dl class="row mb-0"><dt class="col-sm-6 fw-normal text-muted">Condición:</dt><dd class="col-sm-6 fw-bold"><?php echo showVal($t['proveedor_condicion_pago']); ?></dd></dl></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- TAB 4: GESTIÓN DOCUMENTAL -->
                        <div class="tab-pane fade h-100" id="tab-docs" role="tabpanel">
                            <div class="row g-0 h-100" style="min-height: 600px;">
                                
                                <!-- LISTA Y CARGA (Izquierda) -->
                                <div class="col-md-4 col-12 border-end bg-light d-flex flex-column" style="height: 600px;">
                                    
                                    <!-- Formulario -->
                                    <div class="p-3 border-bottom bg-white">
                                        <h6 class="fw-bold mb-2"><i class="bi bi-cloud-upload me-2"></i>Subir Documento</h6>
                                        <form action="?ruta=terceros" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="accion" value="subir_documento">
                                            <input type="hidden" name="id_tercero" value="<?php echo (int)$t['id']; ?>">
                                            <div class="mb-2">
                                                <select class="form-select form-select-sm" name="tipo_documento" id="docTipoSelect" required>
                                                    <option value="">Tipo...</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <input type="file" class="form-control form-control-sm" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                            </div>
                                            <div class="input-group input-group-sm mb-2">
                                                <input type="text" class="form-control" name="observaciones" placeholder="Observación (opcional)">
                                                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i></button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Buscador -->
                                    <div class="p-2 bg-light border-bottom">
                                        <input type="text" class="form-control form-control-sm" id="docSearch" placeholder="🔍 Buscar documento...">
                                    </div>

                                    <!-- Lista con Scroll -->
                                    <div class="flex-grow-1 overflow-auto p-0 scrollable-list">
                                        <div class="list-group list-group-flush" id="listaDocumentos">
                                            <?php if(empty($docs)): ?>
                                                <div class="text-center p-4 text-muted">
                                                    <i class="bi bi-folder2-open display-4 opacity-50"></i>
                                                    <p class="small mt-2">Sin documentos.</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach($docs as $doc): 
                                                    $ext = strtolower($doc['extension'] ?? '');
                                                    $icon = 'bi-file-earmark';
                                                    if($ext === 'pdf') $icon = 'bi-file-earmark-pdf text-danger';
                                                    elseif(in_array($ext, ['jpg','jpeg','png'])) $icon = 'bi-file-earmark-image text-primary';
                                                    elseif(in_array($ext, ['doc','docx'])) $icon = 'bi-file-earmark-word text-primary';
                                                    elseif(in_array($ext, ['xls','xlsx'])) $icon = 'bi-file-earmark-excel text-success';
                                                    
                                                    $searchText = strtolower($doc['nombre_archivo'] . ' ' . $doc['tipo_documento'] . ' ' . ($doc['observaciones'] ?? ''));
                                                ?>
                                                <!-- NOTA: Usamos DIV en lugar de A para evitar problemas de anidamiento con el form -->
                                                <div class="list-group-item list-group-item-action doc-item py-3" 
                                                     data-url="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                                     data-type="<?php echo $ext; ?>"
                                                     data-search="<?php echo htmlspecialchars($searchText); ?>"
                                                     style="cursor:pointer;">
                                                    
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="text-truncate flex-grow-1 pe-2">
                                                            <h6 class="mb-1 text-dark fw-semibold small text-truncate">
                                                                <i class="bi <?php echo $icon; ?> me-1"></i>
                                                                <?php echo htmlspecialchars(!empty($doc['observaciones']) ? $doc['observaciones'] : $doc['nombre_archivo']); ?>
                                                            </h6>
                                                            <div class="d-flex align-items-center flex-wrap gap-1">
                                                                <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($doc['tipo_documento']); ?></span>
                                                                <small class="text-muted" style="font-size:0.7rem;"><?php echo date('d/m/Y', strtotime($doc['fecha_subida'])); ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex gap-1">
                                                            <!-- Botón Editar -->
                                                            <button type="button" class="btn btn-sm btn-outline-primary btn-icon-sm btn-edit-doc" 
                                                                    title="Editar"
                                                                    data-id="<?php echo (int)$doc['id']; ?>"
                                                                    data-tipo="<?php echo htmlspecialchars($doc['tipo_documento']); ?>"
                                                                    data-obs="<?php echo htmlspecialchars($doc['observaciones'] ?? ''); ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <!-- Botón Eliminar -->
                                                            <form action="?ruta=terceros" method="POST" class="form-eliminar-doc d-inline">
                                                                <input type="hidden" name="accion" value="eliminar_documento">
                                                                <input type="hidden" name="id_tercero" value="<?php echo (int)$t['id']; ?>">
                                                                <input type="hidden" name="id_documento" value="<?php echo (int)$doc['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger btn-icon-sm" title="Eliminar">
                                                                    <i class="bi bi-trash"></i>
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

                                <!-- VISOR (Derecha/Abajo) -->
                                <div class="col-md-8 col-12 bg-secondary bg-opacity-10 d-flex flex-column" id="visorContainer" style="height: 600px;">
                                    
                                    <!-- BARRA DE HERRAMIENTAS -->
                                    <div id="visorToolbar" class="bg-white border-bottom p-2 d-flex justify-content-between align-items-center d-none">
                                        <span class="small fw-bold text-muted" id="visorFileName">Documento</span>
                                        <a href="#" id="visorBtnOpen" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>Abrir Externamente
                                        </a>
                                    </div>

                                    <div class="flex-grow-1 position-relative d-flex align-items-center justify-content-center overflow-hidden">
                                        <!-- Placeholder -->
                                        <div class="text-center text-muted p-4" id="visorPlaceholder">
                                            <i class="bi bi-eye display-1 opacity-25"></i>
                                            <p class="mt-3 fs-5">Selecciona un documento para visualizarlo</p>
                                            <p class="small">Las imágenes se verán aquí. Los PDFs se pueden abrir en otra pestaña.</p>
                                        </div>

                                        <!-- Iframe PDF -->
                                        <iframe id="visorPDF" src="" class="d-none w-100 h-100 border-0"></iframe>

                                        <!-- Imagen -->
                                        <img id="visorIMG" src="" class="d-none img-fluid shadow-sm rounded" style="max-height: 95%; max-width: 95%;">
                                        
                                        <!-- Mensaje Descarga -->
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

<!-- MODAL EDITAR DOC -->
<div class="modal fade" id="modalEditarDoc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold">Editar Documento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="?ruta=terceros" method="POST">
                <input type="hidden" name="accion" value="editar_documento">
                <input type="hidden" name="id_tercero" value="<?php echo (int)$t['id']; ?>">
                <input type="hidden" name="id_documento" id="editDocId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Tipo</label>
                        <select class="form-select form-select-sm" name="tipo_documento" id="editDocTipo" required></select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">Nombre / Observación</label>
                        <input type="text" class="form-control form-control-sm" name="observaciones" id="editDocObs">
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

<script>
    window.perfilRoles = {
        empleado: <?php echo $esEmpleado; ?>,
        proveedor: <?php echo $esProveedor; ?>,
        cliente: <?php echo $esCliente; ?>
    };
</script>
<script src="<?php echo asset_url('js/terceros_perfil.js'); ?>"></script>
