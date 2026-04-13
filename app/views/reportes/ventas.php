<div class="container-fluid p-4" id="reportesVentasApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bag-check-fill me-2 text-primary"></i> Reportes de Ventas
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de facturación por cliente, top de productos y control de despachos.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/ventas')); ?>" id="formFiltrosReporteVentas">
                <input type="hidden" name="ruta" value="reportes/ventas">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Cliente</label>
                    <select name="id_cliente" id="filtroVentasCliente" class="form-select bg-light" placeholder="Todos...">
                        <option value="">Todos...</option>
                        <?php foreach (($clientesFiltro ?? []) as $cli): ?>
                            <option value="<?php echo (int) ($cli['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_cliente'] ?? 0) === (int)($cli['id'] ?? 0)) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($cli['nombre_completo'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Tipo tercero</label>
                    <select name="tipo_tercero" id="filtroVentasTipoTercero" class="form-select bg-light">
                        <option value="" <?php echo ($filtros['tipo_tercero'] ?? '') === '' ? 'selected' : ''; ?>>Todos...</option>
                        <option value="cliente" <?php echo ($filtros['tipo_tercero'] ?? '') === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                        <option value="cliente_distribuidor" <?php echo ($filtros['tipo_tercero'] ?? '') === 'cliente_distribuidor' ? 'selected' : ''; ?>>Cliente-distribuidor</option>
                        <option value="distribuidor" <?php echo ($filtros['tipo_tercero'] ?? '') === 'distribuidor' ? 'selected' : ''; ?>>Distribuidor</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Producto</label>
                    <select name="id_item" id="filtroVentasProducto" class="form-select bg-light" placeholder="Todos...">
                        <option value="">Todos...</option>
                        <?php foreach (($productosFiltro ?? []) as $item): ?>
                            <option value="<?php echo (int) ($item['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_item'] ?? 0) === (int)($item['id'] ?? 0)) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($item['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Estado</label>
                    <select name="estado" class="form-select bg-light">
                        <option value="">Todos...</option>
                        <option value="1" <?php echo ($filtros['estado'] ?? '') === '1' ? 'selected' : ''; ?>>Activas</option>
                        <option value="0" <?php echo ($filtros['estado'] ?? '') === '0' ? 'selected' : ''; ?>>Anuladas</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Agrupar por</label>
                    <select name="agrupacion" class="form-select bg-light">
                        <option value="diaria" <?php echo ($filtros['agrupacion'] ?? 'diaria') === 'diaria' ? 'selected' : ''; ?>>Día</option>
                        <option value="semanal" <?php echo ($filtros['agrupacion'] ?? '') === 'semanal' ? 'selected' : ''; ?>>Semana</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Gráfico</label>
                    <select name="tipo_grafico" class="form-select bg-light">
                        <option value="barras" <?php echo ($filtros['tipo_grafico'] ?? 'barras') === 'barras' ? 'selected' : ''; ?>>Barras</option>
                        <option value="linea" <?php echo ($filtros['tipo_grafico'] ?? '') === 'linea' ? 'selected' : ''; ?>>Línea</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-graph-up-arrow me-2 text-success"></i>Ventas <?php echo (($filtros['agrupacion'] ?? 'diaria') === 'semanal') ? 'semanales' : 'diarias'; ?>
            </h5>
            <span class="badge bg-light text-secondary border">Últimos 12 periodos</span>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <div class="border rounded-3 p-3 bg-light-subtle">
                        <canvas id="ventasPeriodoChart" aria-label="Gráfico de ventas por periodo" role="img" height="180"></canvas>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="table-responsive border rounded-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Periodo</th>
                                    <th class="text-end">Documentos</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($porPeriodo)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Sin datos para el rango seleccionado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($porPeriodo as $r): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e((string)($r['etiqueta'] ?? '-')); ?></td>
                                            <td class="text-end"><?php echo e((string)($r['documentos'] ?? '0')); ?></td>
                                            <td class="text-end fw-bold">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-person-lines-fill me-2 text-info"></i>Ventas por cliente
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasCliente" placeholder="Buscar cliente...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasCliente"
                       data-erp-table="true"
                       data-search-input="#filtroRepVentasCliente"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cliente</th>
                            <th class="text-end text-secondary fw-semibold">Total Vendido</th>
                            <th class="text-end text-secondary fw-semibold">Ticket Promedio</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Docs. Emitidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($porCliente['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de ventas para este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($porCliente['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cliente'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                                    <td class="text-end text-muted">S/ <?php echo number_format((float)($r['ticket_promedio'] ?? 0), 2); ?></td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-light text-secondary border"><?php echo e((string)$r['documentos']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVentasClientePaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasClientePaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-star-fill me-2 text-warning"></i>Top productos vendidos
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasProd" placeholder="Buscar producto...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasProd"
                       data-erp-table="true"
                       data-search-input="#filtroRepVentasProd"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Producto</th>
                            <th class="text-end text-secondary fw-semibold">Cantidad</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Monto Generado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($topProductos)): ?>
                            <tr class="empty-msg-row"><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay productos vendidos en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($topProductos ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['producto'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['producto']); ?></td>
                                    <td class="text-end fw-semibold text-primary"><?php echo number_format((float)($r['total_cantidad'] ?? 0), 2); ?></td>
                                    <td class="text-end pe-4 fw-bold text-dark">S/ <?php echo number_format((float)($r['total_monto'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVentasProdPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasProdPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-truck me-2 text-danger"></i>Pendientes de despacho
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Incluye donaciones</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasPendientes" placeholder="Buscar doc o cliente...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasPendientes"
                       data-erp-table="true"
                       data-search-input="#filtroRepVentasPendientes"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Documento</th>
                            <th class="text-secondary fw-semibold">Cliente</th>
                            <th class="text-end text-secondary fw-semibold">Saldo Pendiente</th>
                            <th class="text-secondary fw-semibold">Almacén Origen</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Tiempo en espera</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($pendientes['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-check2-circle fs-1 d-block mb-2 text-success opacity-50"></i>Todo al día. No hay despachos pendientes.</td></tr>
                        <?php else: ?>
                            <?php foreach (($pendientes['rows'] ?? []) as $r): ?>
                                <?php 
                                    $dias = (int)($r['dias_desde_emision'] ?? 0);
                                    $esDonacion = ($r['tipo_operacion'] ?? '') === 'DONACION'; // <-- NUEVO: Verificamos si es donación
                                    
                                    if ($dias >= 7) {
                                        $badgeDias = 'bg-danger-subtle text-danger border-danger-subtle';
                                    } elseif ($dias >= 3) {
                                        $badgeDias = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                    } else {
                                        $badgeDias = 'bg-success-subtle text-success border-success-subtle';
                                    }
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['documento'] . ' ' . (string)$r['cliente'] . ($esDonacion ? ' donacion' : ''))); ?>">
                                    <td class="ps-4 fw-bold text-primary">
                                        <?php echo e((string)$r['documento']); ?>
                                        <?php if($esDonacion): ?>
                                            <br><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-0 mt-1" style="font-size: 0.65rem;">DONACIÓN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo number_format((float)($r['saldo_despachar'] ?? 0), 2); ?></td>
                                    <td class="text-muted small"><i class="bi bi-building me-1"></i><?php echo e((string)$r['almacen']); ?></td>
                                    <td class="text-center pe-4">
                                        <span class="badge px-3 py-1 rounded-pill border <?php echo $badgeDias; ?>"><?php echo $dias; ?> día(s)</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVentasPendientesPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasPendientesPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    async function esperarTomSelect(maxIntentos = 20, esperaMs = 120) {
        for (let i = 0; i < maxIntentos; i++) {
            if (typeof window.TomSelect !== 'undefined') return true;
            await new Promise(resolve => setTimeout(resolve, esperaMs));
        }
        return false;
    }

    async function inicializarFiltrosVentas() {
        const form = document.getElementById('formFiltrosReporteVentas');
        if (!form || form.dataset.jsInicializado === '1') return;
        form.dataset.jsInicializado = '1';

        const clienteSelect = document.getElementById('filtroVentasCliente');
        const tipoTerceroSelect = document.getElementById('filtroVentasTipoTercero');
        const productoSelect = document.getElementById('filtroVentasProducto');
        const tomListo = await esperarTomSelect();
        if (tomListo && clienteSelect && !clienteSelect.tomselect) {
            new TomSelect(clienteSelect, {
                valueField: 'id',
                labelField: 'nombre_completo',
                searchField: ['nombre_completo', 'num_doc'],
                placeholder: 'Buscar cliente...',
                maxOptions: 50,
                create: false,
                load(query, callback) {
                    const u = new URL(window.location.href);
                    u.searchParams.set('ruta', 'reportes/ventas');
                    u.searchParams.set('accion', 'buscar_clientes');
                    u.searchParams.set('q', query || '');
                    u.searchParams.set('tipo_tercero', tipoTerceroSelect?.value || '');
                    fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(r => callback(Array.isArray(r?.data) ? r.data : []))
                        .catch(() => callback());
                }
            });
        }
        if (tipoTerceroSelect && clienteSelect?.tomselect) {
            tipoTerceroSelect.addEventListener('change', () => {
                clienteSelect.tomselect.clear(true);
                clienteSelect.tomselect.clearOptions();
                clienteSelect.tomselect.load('');
            });
        }
        if (tomListo && productoSelect && !productoSelect.tomselect) {
            new TomSelect(productoSelect, {
                valueField: 'id',
                labelField: 'nombre',
                searchField: ['nombre', 'sku'],
                placeholder: 'Buscar producto...',
                maxOptions: 50,
                create: false,
                load(query, callback) {
                    const u = new URL(window.location.href);
                    u.searchParams.set('ruta', 'reportes/ventas');
                    u.searchParams.set('accion', 'buscar_productos');
                    u.searchParams.set('q', query || '');
                    fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(r => callback(Array.isArray(r?.data) ? r.data : []))
                        .catch(() => callback());
                }
            });
        }

        let autoSubmitTimer = null;
        const enviarFiltros = () => {
            if (autoSubmitTimer) clearTimeout(autoSubmitTimer);
            autoSubmitTimer = setTimeout(() => form.requestSubmit(), 150);
        };

        const filtrosAutoSubmit = form.querySelectorAll('input[name], select[name]');
        filtrosAutoSubmit.forEach((campo) => {
            const evento = campo.tagName === 'INPUT' && campo.type === 'date' ? 'change' : 'input';
            campo.addEventListener(evento, enviarFiltros);
            if (evento !== 'change') {
                campo.addEventListener('change', enviarFiltros);
            }
        });

        if (clienteSelect?.tomselect) {
            clienteSelect.tomselect.on('change', enviarFiltros);
        }
        if (productoSelect?.tomselect) {
            productoSelect.tomselect.on('change', enviarFiltros);
        }
        if (tipoTerceroSelect) {
            tipoTerceroSelect.addEventListener('change', enviarFiltros);
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(form);
            data.set('ruta', 'reportes/ventas');
            const params = new URLSearchParams(data);
            const url = `${window.location.pathname}?${params.toString()}`;

            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nuevo = doc.getElementById('reportesVentasApp');
                const actual = document.getElementById('reportesVentasApp');
                if (!nuevo || !actual) {
                    window.location.href = url;
                    return;
                }
                actual.innerHTML = nuevo.innerHTML;
                window.history.pushState({}, '', url);

                doc.querySelectorAll('script').forEach((script) => {
                    if (!script.textContent || !script.textContent.trim()) return;
                    const s = document.createElement('script');
                    s.textContent = script.textContent;
                    document.body.appendChild(s);
                    s.remove();
                });
            } catch (e) {
                window.location.href = url;
            }
        });
    }

    inicializarFiltrosVentas();
})();
</script>
<script>
(() => {
    const chartData = <?php echo json_encode($porPeriodo ?? [], JSON_UNESCAPED_UNICODE); ?>;
    const el = document.getElementById('ventasPeriodoChart');
    if (!el || !Array.isArray(chartData) || chartData.length === 0 || typeof window.Chart === 'undefined') return;

    const labels = chartData.map(r => String(r.etiqueta ?? ''));
    const data = chartData.map(r => Number(r.total_vendido ?? 0));
    const tipoGrafico = <?php echo json_encode(($filtros['tipo_grafico'] ?? 'barras') === 'linea' ? 'line' : 'bar'); ?>;

    new Chart(el, {
        type: tipoGrafico,
        data: {
            labels,
            datasets: [{
                label: 'Total vendido (S/)',
                data,
                borderColor: '#198754',
                backgroundColor: tipoGrafico === 'line' ? 'rgba(25,135,84,.15)' : 'rgba(25,135,84,.35)',
                tension: .25,
                fill: tipoGrafico === 'line',
                pointRadius: tipoGrafico === 'line' ? 3 : 0,
                borderRadius: tipoGrafico === 'bar' ? 6 : 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const val = Number(ctx.parsed.y ?? 0);
                            return `S/ ${val.toFixed(2)}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback(value) { return `S/ ${Number(value).toFixed(0)}`; }
                    }
                }
            }
        }
    });
})();
</script>
