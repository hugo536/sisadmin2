<?php 
    $movimientos = $movimientos ?? [];
    $resumenCuentas = $resumenCuentas ?? []; 
    $metodos = $metodos ?? [];
    $filtros = $filtros ?? [];

    // ========================================================================
    // 1. OBTENCIÓN DE FILTROS ACTUALES (Adaptados para Arrays / Selección Múltiple)
    // ========================================================================
    
    // Filtro Cuentas
    $idCuentaFiltro = $filtros['id_cuenta'] ?? [];
    if (!is_array($idCuentaFiltro) && $idCuentaFiltro !== '') $idCuentaFiltro = [$idCuentaFiltro];
    elseif ($idCuentaFiltro === '') $idCuentaFiltro = [];

    // Filtro Métodos de Pago
    $metodoFiltro = $filtros['id_metodo_pago'] ?? [];
    if (!is_array($metodoFiltro) && $metodoFiltro !== '') $metodoFiltro = [$metodoFiltro];
    elseif ($metodoFiltro === '') $metodoFiltro = [];

    // Filtro Orígenes
    $origenFiltro = $filtros['origen'] ?? [];
    if (!is_array($origenFiltro) && $origenFiltro !== '') $origenFiltro = [$origenFiltro];
    elseif ($origenFiltro === '') $origenFiltro = [];

    $cuentaSeleccionada = null;
    if (!empty($idCuentaFiltro)) {
        foreach ($resumenCuentas as $cuenta) {
            if (in_array((string)($cuenta['id'] ?? ''), $idCuentaFiltro)) {
                $cuentaSeleccionada = $cuenta;
                break;
            }
        }
    }

    // ========================================================================
    // 2. FUNCIONES AUXILIARES (HELPERS) PARA LIMPIAR LA VISTA
    // ========================================================================
    $formatCurrency = function($value, $decimals = 2) {
        return 'S/ ' . number_format((float)($value ?? 0), $decimals, '.', ',');
    };

    // ========================================================================
    // 3. LÓGICA DE ALERTAS (SWEETALERT)
    // ========================================================================
    $swalIcon = null;
    $swalMessage = null;

    if (!empty($_GET['error'])) {
        $swalIcon = 'error';
        $swalMessage = htmlspecialchars((string) $_GET['error'], ENT_QUOTES, 'UTF-8');
    } elseif (!empty($_GET['ok'])) {
        $swalIcon = 'success';
        $swalMessage = 'Movimiento anulado correctamente.';
    }
?>

<div class="container-fluid p-4" id="reporteTesoreriaMovApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Reporte de Movimientos
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis detallado de ingresos, egresos y transferencias.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?= e(route_url('tesoreria/cuentas')) ?>" class="btn btn-outline-secondary shadow-sm fw-semibold">
                <i class="bi bi-bank me-2"></i>Ir a Cuentas
            </a>
            <a href="<?= e(route_url('tesoreria/cxc')) ?>" class="btn btn-outline-secondary shadow-sm fw-semibold">
                <i class="bi bi-cash-stack me-2"></i>Ir a CxC
            </a>
            <a href="<?= e(route_url('tesoreria/cxp')) ?>" class="btn btn-outline-secondary shadow-sm fw-semibold">
                <i class="bi bi-shop me-2"></i>Ir a CxP
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 rounded">
        <div class="card-body p-4 bg-white">
            <form id="formFiltrosMovimientos" class="row g-3 align-items-end" method="get" action="<?= e(route_url('reportes/tesoreria_movimientos')) ?>">
                <input type="hidden" name="ruta" value="reportes/tesoreria_movimientos">
                <input type="hidden" name="busqueda" id="hidden_busqueda" value="<?= e($_GET['busqueda'] ?? '') ?>">

                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Cuenta Bancaria / Caja</label>
                    <div class="dropdown dropdown-multi">
                        <button class="btn btn-light border-secondary-subtle dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height: 38px;">
                            <span class="text-truncate">Todas las cuentas</span>
                        </button>
                        <ul class="dropdown-menu w-100 p-0 shadow-sm" style="max-height: 350px; overflow-y: auto;">
                            <div class="p-2">
                                <li>
                                    <div class="form-check border-bottom pb-2 mb-2">
                                        <input class="form-check-input chk-todos" type="checkbox" id="chkCuentaTodas" <?= empty($idCuentaFiltro) || count($idCuentaFiltro) === count($resumenCuentas) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-primary" for="chkCuentaTodas" style="cursor: pointer;">Seleccionar Todas</label>
                                    </div>
                                </li>
                                <?php foreach ($resumenCuentas as $c): ?>
                                <?php 
                                    $idCuentaId = (string)($c['id'] ?? 0);
                                    $isChecked = (empty($idCuentaFiltro) || in_array($idCuentaId, $idCuentaFiltro)) ? 'checked' : '';
                                ?>
                                <li>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input chk-item" type="checkbox" name="id_cuenta[]" value="<?= $idCuentaId ?>" id="chkCuenta<?= $idCuentaId ?>" <?= $isChecked ?>>
                                        <label class="form-check-label text-truncate w-100" for="chkCuenta<?= $idCuentaId ?>" style="cursor: pointer;" title="<?= e((string) ($c['nombre'] ?? '')) ?>">
                                            <?= e((string) ($c['nombre'] ?? '')) ?>
                                        </label>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </div>
                            <div class="p-2 border-top bg-light position-sticky bottom-0 z-1">
                                <button type="button" class="btn btn-primary btn-sm w-100 fw-bold btn-aplicar-filtro">Aplicar Filtro</button>
                            </div>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Método de Pago</label>
                    <div class="dropdown dropdown-multi">
                        <button class="btn btn-light border-secondary-subtle dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height: 38px;">
                            <span class="text-truncate">Todos los métodos</span>
                        </button>
                        <ul class="dropdown-menu w-100 p-0 shadow-sm" style="max-height: 350px; overflow-y: auto;">
                            <div class="p-2">
                                <li>
                                    <div class="form-check border-bottom pb-2 mb-2">
                                        <input class="form-check-input chk-todos" type="checkbox" id="chkMetodoTodos" <?= empty($metodoFiltro) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-primary" for="chkMetodoTodos" style="cursor: pointer;">Seleccionar Todos</label>
                                    </div>
                                </li>
                                <?php 
                                // Si no hay métodos de la BD, usamos los fijos
                                $listaMetodos = !empty($metodos) ? $metodos : [
                                    ['id' => 'Efectivo', 'nombre' => 'Efectivo'],
                                    ['id' => 'Transferencia', 'nombre' => 'Transferencia'],
                                    ['id' => 'Yape/Plin', 'nombre' => 'Yape/Plin'],
                                    ['id' => 'Tarjeta', 'nombre' => 'Tarjeta'],
                                    ['id' => 'Cheque', 'nombre' => 'Cheque']
                                ];
                                foreach ($listaMetodos as $m): 
                                    $idMetodo = (string) $m['id'];
                                    $isChecked = (empty($metodoFiltro) || in_array($idMetodo, $metodoFiltro)) ? 'checked' : '';
                                ?>
                                <li>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input chk-item" type="checkbox" name="id_metodo_pago[]" value="<?= $idMetodo ?>" id="chkMetodo<?= htmlspecialchars(str_replace('/', '', $idMetodo)) ?>" <?= $isChecked ?>>
                                        <label class="form-check-label text-truncate w-100" for="chkMetodo<?= htmlspecialchars(str_replace('/', '', $idMetodo)) ?>" style="cursor: pointer;">
                                            <?= e((string) $m['nombre']) ?>
                                        </label>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </div>
                            <div class="p-2 border-top bg-light position-sticky bottom-0 z-1">
                                <button type="button" class="btn btn-primary btn-sm w-100 fw-bold btn-aplicar-filtro">Aplicar Filtro</button>
                            </div>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Origen / Tipo</label>
                    <div class="dropdown dropdown-multi">
                        <button class="btn btn-light border-secondary-subtle dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height: 38px;">
                            <span class="text-truncate">Todos los orígenes</span>
                        </button>
                        <ul class="dropdown-menu w-100 p-0 shadow-sm" style="max-height: 350px; overflow-y: auto;">
                            <div class="p-2">
                                <li>
                                    <div class="form-check border-bottom pb-2 mb-2">
                                        <input class="form-check-input chk-todos" type="checkbox" id="chkOrigenTodos" <?= empty($origenFiltro) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-primary" for="chkOrigenTodos" style="cursor: pointer;">Seleccionar Todos</label>
                                    </div>
                                </li>
                                <?php 
                                $listaOrigenes = [
                                    'CXC' => 'Cobros (CXC)',
                                    'CXP' => 'Pagos (CXP)',
                                    'TRANSFERENCIA' => 'Transferencias'
                                ];
                                foreach ($listaOrigenes as $val => $label): 
                                    $isChecked = (empty($origenFiltro) || in_array($val, $origenFiltro)) ? 'checked' : '';
                                ?>
                                <li>
                                    <div class="form-check mb-1">
                                        <input class="form-check-input chk-item" type="checkbox" name="origen[]" value="<?= $val ?>" id="chkOrigen<?= $val ?>" <?= $isChecked ?>>
                                        <label class="form-check-label text-truncate w-100" for="chkOrigen<?= $val ?>" style="cursor: pointer;"><?= $label ?></label>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </div>
                            <div class="p-2 border-top bg-light position-sticky bottom-0 z-1">
                                <button type="button" class="btn btn-primary btn-sm w-100 fw-bold btn-aplicar-filtro">Aplicar Filtro</button>
                            </div>
                        </ul>
                    </div>
                </div>
                
                <div class="col-12 col-md-4">
                    <div class="d-flex gap-2 w-100">
                        <div class="w-50">
                            <label class="form-label text-muted small fw-bold mb-1">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control bg-light border-secondary-subtle auto-submit" value="<?= e($filtros['fecha_desde'] ?? date('Y-m-01')) ?>" style="height: 38px;">
                        </div>
                        <div class="w-50">
                            <label class="form-label text-muted small fw-bold mb-1">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control bg-light border-secondary-subtle auto-submit" value="<?= e($filtros['fecha_hasta'] ?? date('Y-m-t')) ?>" style="height: 38px;">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" id="contenedorTablaMovimientos">
        
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <div class="d-flex align-items-center">
                <h5 class="mb-0 fw-bold text-dark text-nowrap"><i class="bi bi-list-columns-reverse me-2 text-info"></i>Historial Detallado</h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?= count($movimientos) ?> Transacciones</span>
            </div>
            
            <div class="d-flex gap-2 align-items-center w-100" style="max-width: 450px;">
                <button type="submit" form="formFiltrosMovimientos" name="exportar_pdf" value="1" class="btn btn-sm btn-danger shadow-sm fw-bold px-3 d-flex align-items-center text-nowrap" formtarget="_blank" title="Exportar a PDF" onclick="document.getElementById('hidden_busqueda').value = document.getElementById('filtroRepMovimientos').value;">
                    <i class="bi bi-file-pdf-fill"></i><span class="ms-1 d-none d-sm-inline">PDF</span>
                </button>
                <div class="input-group input-group-sm flex-grow-1">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepMovimientos" placeholder="Buscar transacción..." value="<?= e($_GET['busqueda'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="movimientosTable" 
                       data-erp-table="true" 
                       data-search-input="#filtroRepMovimientos" 
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold text-nowrap" style="width: 10%;">Fecha</th>
                            <th class="text-secondary fw-semibold text-nowrap" style="width: 10%;">Tipo</th>
                            <th class="text-secondary fw-semibold" style="width: 30%; min-width: 250px;">Tercero</th>
                            <th class="text-center text-secondary fw-semibold text-nowrap" style="width: 10%;">Origen</th>
                            <th class="text-secondary fw-semibold" style="width: 15%; min-width: 150px;">Cuenta / Método</th>
                            <th class="text-end text-dark fw-bold text-nowrap" style="width: 10%;">Monto</th>
                            <th class="text-center text-secondary fw-semibold text-nowrap" style="width: 10%;">Estado</th>
                            <th class="text-center pe-4 text-secondary fw-semibold text-nowrap" style="width: 5%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="movimientosTableBody">
                    <?php if (empty($movimientos)): ?>
                        <tr class="empty-msg-row">
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                No hay transacciones para mostrar en este periodo.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $m): ?>
                            <?php 
                                $estado = (string) ($m['estado'] ?? '');
                                $tipo = (string) ($m['tipo'] ?? '');
                                $origen = strtoupper((string) ($m['origen'] ?? ''));
                                
                                if ($origen === 'TRANSFERENCIA') {
                                    $tercero = 'Cuentas Propias (Interno)';
                                } else {
                                    $tercero = (string) ($m['tercero_nombre'] ?? ('#' . (int) ($m['id_tercero'] ?? 0)));
                                }
                                
                                $nombreCuenta = (string) ($m['cuenta_nombre'] ?? '');
                                $metodoPago = (string) ($m['metodo'] ?? $m['metodo_pago'] ?? '');
                                
                                if ($metodoPago !== '' && $metodoPago !== 'N/D') {
                                    $cuenta = $nombreCuenta . ' - ' . $metodoPago;
                                } else {
                                    $cuenta = $nombreCuenta;
                                }

                                $notasConsolidadas = [];
                                if (!empty($m['observacion_origen'])) $notasConsolidadas[] = trim((string) $m['observacion_origen']);
                                if (!empty($m['observaciones']))      $notasConsolidadas[] = trim((string) $m['observaciones']);
                                
                                $searchStr = mb_strtolower($tipo . ' ' . $tercero . ' ' . $origen . ' ' . $cuenta . ' ' . $estado . ' ' . implode(' ', $notasConsolidadas));
                                
                                $montoColor = $tipo === 'COBRO' ? 'text-success' : 'text-danger';
                                $montoSigno = $tipo === 'COBRO' ? '+' : '-';
                                
                                $fechaFormateada = !empty($m['fecha']) ? date('d/m/Y', strtotime($m['fecha'])) : '';
                            ?>
                            <tr class="border-bottom" data-search="<?= htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8') ?>">
                                <td class="ps-4 text-muted small fw-medium text-nowrap"><?= e($fechaFormateada) ?></td>
                                <td class="fw-semibold text-nowrap">
                                    <?php if($tipo === 'COBRO'): ?>
                                        <span class="text-success"><i class="bi bi-arrow-down-left-circle me-1"></i>COBRO</span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-arrow-up-right-circle me-1"></i>PAGO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?= e($tercero) ?></span>
                                    <?php if (!empty($notasConsolidadas)): 
                                        $textoNotaMov = implode(' - ', $notasConsolidadas);
                                    ?>
                                        <small class="text-muted fw-normal d-block mt-1 text-truncate" style="font-size: 0.75rem; max-width: 250px;" title="<?= htmlspecialchars($textoNotaMov, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= e($textoNotaMov) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <?php if($origen === 'TRANSFERENCIA'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle" title="ID Transferencia: <?= (int) ($m['id_origen'] ?? 0) ?>">
                                            <i class="bi bi-arrow-left-right me-1"></i> TRF #<?= (int) ($m['id_origen'] ?? 0) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border border-secondary-subtle">
                                            <?= e($origen) ?> #<?= (int) ($m['id_origen'] ?? 0) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e($cuenta) ?></td>
                                <td class="text-end fw-bold <?= $montoColor ?> text-nowrap">
                                    <?= $montoSigno ?> <?= $formatCurrency((float) ($m['monto'] ?? 0)) ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <?php if($estado === 'CONFIRMADO'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">CONFIRMADO</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><?= e($estado) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4 text-nowrap">
                                    <?php if ($estado === 'CONFIRMADO' && tiene_permiso('tesoreria.movimientos.anular')): ?>
                                        <form method="post" action="<?= e(route_url('tesoreria/anular_movimiento')) ?>" class="d-inline js-form-confirm">
                                            <input type="hidden" name="id_movimiento" value="<?= (int) $m['id'] ?>">
                                            <input type="hidden" name="id_origen" value="<?= (int) $m['id_origen'] ?>">
                                            <input type="hidden" name="origen" value="<?= e($origen) ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 rounded-circle shadow-sm" data-bs-toggle="tooltip" title="Anular Transacción">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted opacity-25"><i class="bi bi-dash-circle"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="movimientosPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="movimientosPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>

<?php if ($swalMessage !== null): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Swal === 'undefined') return;

            const isSuccess = <?= json_encode($swalIcon === 'success') ?>;

            Swal.fire({
                icon: <?= json_encode($swalIcon) ?>,
                title: isSuccess ? '¡Éxito!' : 'Error',
                text: <?= json_encode($swalMessage) ?>,
                confirmButtonText: isSuccess ? 'Aceptar' : 'Entendido',
                confirmButtonColor: isSuccess ? '#198754' : '#dc3545',
                customClass: { popup: 'rounded-4 shadow-lg' }
            }).then(() => {
                if (isSuccess && window.history.replaceState) {
                    const url = new URL(window.location);
                    url.searchParams.delete('ok');
                    window.history.replaceState({path: url.href}, '', url.href);
                }
            });
        });
    </script>
<?php endif; ?>