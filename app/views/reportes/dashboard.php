<?php
/**
 * @var array|null $totales
 * @var array|null $eventos
 * @var array|null $cumpleanosMes
 * @var array|null $reportes_widgets
 * @var array|null $productosCriticos
 */

$totales = is_array($totales ?? null) ? $totales : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$cumpleanosMes = is_array($cumpleanosMes ?? null) ? $cumpleanosMes : [];
$reportesWidgets = is_array($reportes_widgets ?? null) ? $reportes_widgets : [];
$productosCriticos = is_array($productosCriticos ?? null) ? $productosCriticos : [];
?>

<div class="container-fluid p-4 dashboard-page" id="dashboardApp">
    
    <!-- ENCABEZADO MINIMALISTA -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 fade-in gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard Principal
            </h1>
            <p class="text-muted small mb-0 ms-1">Resumen operativo, indicadores clave y actividad reciente.</p>
        </div>
        <div class="text-end">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill shadow-sm d-inline-flex align-items-center">
                <span class="spinner-grow spinner-grow-sm text-success me-2" role="status" style="width: 0.5rem; height: 0.5rem;"></span>
                Panel en tiempo real
            </span>
        </div>
    </div>

    <!-- SECCIÓN 1: REPORTES PRINCIPALES -->
    <div class="mb-4 fade-in" style="animation-delay: 0.05s;">
        <h2 class="h6 fw-bold text-secondary text-uppercase tracking-wider mb-3" style="letter-spacing: 1px;"><i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Reportes Principales</h2>
        <div class="row g-3">
            
            <!-- Widget: Reportes de Ventas -->
            <div class="col-12 col-md-6 col-xl-3">
                <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-primary text-white shadow-sm" 
                   href="<?php echo e(route_url('reportes/ventas')); ?>" 
                   onclick="navegarDesdeDashboard(event, this.href)"
                   style="border-radius: 1.25rem;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Análisis Comercial</div>
                            <div class="h5 mb-0 fw-bold text-white lh-1 mt-1">Reportes de Ventas</div>
                        </div>
                        <div class="bg-white bg-opacity-25 text-white p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-graph-up-arrow fs-4"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Widget: Reportes de Compras -->
            <div class="col-12 col-md-6 col-xl-3">
                <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover text-white shadow-sm" 
                   href="<?php echo e(route_url('reportes/compras')); ?>" 
                   onclick="navegarDesdeDashboard(event, this.href)"
                   style="border-radius: 1.25rem; background-color: #0dcaf0;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Abastecimiento</div>
                            <div class="h5 mb-0 fw-bold text-white lh-1 mt-1">Reportes de Compras</div>
                        </div>
                        <div class="bg-white bg-opacity-25 text-white p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-cart-check-fill fs-4"></i>
                        </div>
                    </div>
                </a>
            </div>

            <?php if (tiene_permiso('reportes.tesoreria.ver')): ?>
            <!-- Widget: Estado de Cuenta Clientes -->
            <div class="col-12 col-md-6 col-xl-3">
                <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-success-subtle shadow-sm" 
                   href="<?php echo e(route_url('reportes/estado_cuenta')); ?>" 
                   onclick="navegarDesdeDashboard(event, this.href)"
                   style="border-radius: 1.25rem;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-success-emphasis mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Tesorería Clientes</div>
                            <div class="h6 mb-0 fw-bold text-success-emphasis lh-1 mt-1">Estado de Cuenta</div>
                        </div>
                        <div class="bg-white bg-opacity-75 text-success-emphasis p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-person-lines-fill fs-4"></i>
                        </div>
                    </div>
                </a>
            </div>
            
            <!-- Widget: Estado de Cuenta Proveedores -->
            <div class="col-12 col-md-6 col-xl-3">
                <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-secondary-subtle shadow-sm" 
                   href="<?php echo e(route_url('reportes/estado_cuenta_proveedores')); ?>" 
                   onclick="navegarDesdeDashboard(event, this.href)"
                   style="border-radius: 1.25rem;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary-emphasis mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Tesorería Prov.</div>
                            <div class="h6 mb-0 fw-bold text-secondary-emphasis lh-1 mt-1">Estado de Cuenta</div>
                        </div>
                        <div class="bg-white bg-opacity-75 text-secondary-emphasis p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-buildings-fill fs-4"></i>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- SECCIÓN 2: INDICADORES OPERATIVOS (KPIs) -->
    <div class="mb-5 fade-in" style="animation-delay: 0.1s;">
        <div class="row g-3">
            <?php 
            $widgetConfig = [
                'compras_pendientes'   => ['color' => 'warning', 'icon' => 'bi-cart-dash',    'url' => 'reportes/compras'],
                'ventas_por_despachar' => ['color' => 'info',    'icon' => 'bi-truck',        'url' => 'reportes/ventas'],
                'produccion_proceso'   => ['color' => 'primary', 'icon' => 'bi-gear-wide',    'url' => 'reportes/produccion'],
                'cxc_vencida'          => ['color' => 'danger',  'icon' => 'bi-cash-stack',   'url' => 'reportes/cxc'], 
                'cxp_vencida'          => ['color' => 'danger',  'icon' => 'bi-wallet2',      'url' => 'reportes/cxp']  
            ]; 
            ?>
            
            <?php foreach ($reportesWidgets as $k => $v): ?>
                <?php if ($k === 'stock_critico') continue; ?>
                <?php $cfg = $widgetConfig[$k] ?? ['color' => 'secondary', 'icon' => 'bi-arrow-right', 'url' => 'reportes/dashboard']; ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl">
                    <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-<?php echo $cfg['color']; ?>-subtle" 
                       href="<?php echo e(route_url((string) $cfg['url'])); ?>" 
                       onclick="navegarDesdeDashboard(event, this.href)"
                       style="border-radius: 1rem;">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-<?php echo $cfg['color']; ?>-emphasis mb-1" style="font-size: 0.70rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?php echo e(str_replace('_', ' ', (string) $k)); ?>
                                </div>
                                <div class="h4 mb-0 fw-bold text-<?php echo $cfg['color']; ?>-emphasis lh-1"><?php echo (int) $v; ?></div>
                            </div>
                            <div class="text-<?php echo $cfg['color']; ?>-emphasis opacity-75">
                                <i class="bi <?php echo $cfg['icon']; ?> fs-2"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECCIÓN 3: TRES COLUMNAS EQUILIBRADAS -->
    <div class="row g-4 mb-4 fade-in" style="animation-delay: 0.15s;">
        
        <!-- 1. ALERTAS DE INVENTARIO (Columna de 4/12) -->
        <div class="col-12 col-xl-4 d-flex flex-column">
            
            <!-- Título simplificado sin el badge del monto -->
            <div class="d-flex mb-3 align-items-center">
                <h2 class="h5 fw-bold text-dark mb-0 text-truncate">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Stock Crítico
                </h2>
            </div>
            
            <div class="card border-0 shadow-sm flex-grow-1" style="border-radius: 1rem; overflow: hidden;">
                <!-- ... (el resto del código de la tabla se mantiene igual) ... -->
                <div class="card-body p-0 d-flex flex-column">
                    <?php if (empty($productosCriticos)): ?>
                        <div class="p-4 text-center text-muted my-auto">
                            <div class="bg-success-subtle rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi bi-check2-circle fs-2 text-success"></i>
                            </div>
                            <h6 class="fw-bold text-dark">¡Inventario Saludable!</h6>
                            <p class="mb-0 small fw-semibold">No hay artículos bajo stock mínimo.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover table-borderless" style="table-layout: fixed;">
                                <thead class="table-light text-muted" style="font-size: 0.75rem; text-transform: uppercase;">
                                    <tr>
                                        <th class="ps-4 fw-bold py-2" style="width: 75%;">Producto</th>
                                        <th class="pe-4 fw-bold py-2 text-end" style="width: 25%;">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($productosCriticos, 0, 5) as $prod): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td class="ps-4 py-2 text-truncate">
                                                <div class="fw-bold text-dark text-truncate lh-sm mb-1" title="<?php echo e((string) ($prod['nombre'] ?? 'Sin Nombre')); ?>">
                                                    <?php echo e((string) ($prod['nombre'] ?? 'Sin Nombre')); ?>
                                                </div>
                                                <div class="text-muted lh-1" style="font-size: 0.75rem;">
                                                    <?php echo e((string) ($prod['codigo'] ?? 'S/C')); ?>
                                                </div>
                                            </td>
                                            <td class="pe-4 text-end py-2">
                                                <?php if ((float)($prod['stock_actual'] ?? 0) <= 0): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" style="font-size: 0.7rem; font-weight: 600;">Agotado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1" style="font-size: 0.7rem; font-weight: 600;">Crítico</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if(count($productosCriticos) > 5): ?>
                            <div class="card-footer bg-light border-top text-center py-2 mt-auto">
                                <a href="<?php echo e(route_url('reportes/inventario')); ?>" class="text-decoration-none fw-bold text-primary small sb-link">
                                    Ver los <?php echo count($productosCriticos); ?> críticos <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. CUMPLEAÑOS (Columna de 4/12) -->
        <div class="col-12 col-xl-4 d-flex flex-column">
            <!-- Título invisible para alinear con las demás tarjetas -->
            <div class="d-flex mb-3 d-none d-xl-flex" style="visibility: hidden;">
                <h2 class="h5 mb-0">Espacio</h2>
            </div>

            <div class="card border-0 shadow-sm flex-grow-1" style="border-radius: 1rem; overflow: hidden;">
                <div class="card-header bg-white border-bottom-0 px-4 py-3 d-flex justify-content-between align-items-center pt-4">
                    <h5 class="mb-0 fw-bold text-dark fs-6 text-uppercase text-truncate" style="letter-spacing: 0.5px;">
                        <i class="bi bi-cake2-fill me-2 text-danger"></i>Cumpleaños del Mes
                    </h5>
                    <span class="badge rounded-pill bg-danger text-white px-2 py-1 shadow-sm">
                        <?php echo count($cumpleanosMes); ?>
                    </span>
                </div>
                <div class="card-body p-0 d-flex flex-column">
                    <?php if ($cumpleanosMes === []): ?>
                        <div class="p-4 text-center text-muted my-auto">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi bi-calendar2-heart fs-2 text-secondary opacity-50"></i>
                            </div>
                            <p class="mb-0 small fw-semibold">No hay cumpleaños este mes.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover table-borderless" style="table-layout: fixed;">
                                <thead class="table-light text-muted" style="font-size: 0.75rem; text-transform: uppercase;">
                                    <tr>
                                        <th class="ps-4 fw-bold py-2" style="width: 70%;">Empleado</th>
                                        <th class="pe-4 fw-bold py-2 text-end" style="width: 30%;">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cumpleanosMes as $cumple): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td class="ps-4 py-2 text-truncate" data-label="Empleado">
                                            <div class="fw-bold text-dark text-truncate lh-sm mb-1" title="<?php echo e((string) ($cumple['nombre_completo'] ?? '')); ?>">
                                                <?php echo e((string) ($cumple['nombre_completo'] ?? '')); ?>
                                            </div>
                                            <div class="text-muted lh-1" style="font-size: 0.75rem;">
                                                <?php echo e((string) ($cumple['fecha_cumple'] ?? '')); ?>
                                            </div>
                                        </td>
                                        <td class="pe-4 text-end py-2" data-label="Fecha">
                                            <?php if ((int) ($cumple['dias_restantes'] ?? 0) === 0): ?>
                                                <span class="badge px-2 py-1 rounded-pill bg-success-subtle text-success border border-success-subtle shadow-sm" style="font-size: 0.7rem; font-weight: 600;">🎉 Hoy</span>
                                            <?php else: ?>
                                                <span class="badge px-2 py-1 rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.7rem; font-weight: 600;"><?php echo (int) ($cumple['dias_restantes'] ?? 0); ?> día(s)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. ACCESOS RÁPIDOS (Columna de 4/12) -->
        <div class="col-12 col-xl-4 d-flex flex-column">
            <!-- Título invisible para alineación perfecta -->
            <div class="d-flex mb-3 d-none d-xl-flex" style="visibility: hidden;">
                <h2 class="h5 mb-0">Espacio</h2>
            </div>

            <div class="card border-0 shadow-sm flex-grow-1" style="border-radius: 1rem; overflow: hidden;">
                <div class="card-header bg-white border-bottom-0 px-4 py-3 pt-4">
                    <h5 class="mb-0 fw-bold text-dark fs-6 text-uppercase text-truncate" style="letter-spacing: 0.5px;">
                        <i class="bi bi-star-fill me-2 text-warning"></i>Accesos Rápidos
                    </h5>
                </div>
                <div class="card-body p-3 d-flex flex-column justify-content-center gap-2">
                    <a href="<?php echo e(route_url('ventas')); ?>" class="btn btn-light border text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 transition-hover sb-link">
                        <span class="d-flex align-items-center fw-semibold text-secondary">
                            <i class="bi bi-bag-plus text-primary fs-5 me-2"></i> Nueva Venta / Factura
                        </span>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    </a>
                    <a href="<?php echo e(route_url('compras')); ?>" class="btn btn-light border text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 transition-hover sb-link">
                        <span class="d-flex align-items-center fw-semibold text-secondary">
                            <i class="bi bi-cart-plus text-info fs-5 me-2"></i> Registrar Compra
                        </span>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    </a>
                    <a href="<?php echo e(route_url('inventario')); ?>" class="btn btn-light border text-start d-flex align-items-center justify-content-between p-2.5 rounded-3 transition-hover sb-link">
                        <span class="d-flex align-items-center fw-semibold text-secondary">
                            <i class="bi bi-box-seam text-success fs-5 me-2"></i> Consultar Inventario
                        </span>
                        <i class="bi bi-chevron-right text-muted small"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- SECCIÓN 4: BITÁCORA -->
    <div class="row fade-in" style="animation-delay: 0.2s;">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 1rem; overflow: hidden;">
                <div class="card-header bg-white border-bottom-0 px-4 py-3 pt-4">
                    <h5 class="mb-0 fw-bold text-dark fs-6 text-uppercase" style="letter-spacing: 0.5px;">
                        <i class="bi bi-journal-text me-2 text-primary"></i>Últimos registros en Bitácora
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($eventos === []): ?>
                        <div class="p-5 text-center text-muted">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-inboxes fs-1 text-secondary opacity-50"></i>
                            </div>
                            <p class="mb-0 fw-semibold">No hay registros recientes.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover table-borderless">
                            <thead class="table-light text-muted" style="font-size: 0.75rem; text-transform: uppercase;">
                                <tr>
                                    <th class="ps-4 fw-bold">Fecha / Hora</th>
                                    <th class="fw-bold">Evento Registrado</th>
                                    <th class="pe-4 fw-bold">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($eventos as $row): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td class="ps-4 py-3 text-muted" style="font-size: 0.85rem;" data-label="Fecha">
                                        <i class="bi bi-clock me-1 opacity-50"></i> <?php echo e((string) ($row['created_at'] ?? '')); ?>
                                    </td>
                                    <td class="fw-semibold text-dark py-3" style="font-size: 0.9rem;" data-label="Evento">
                                        <?php echo e((string) ($row['evento'] ?? '')); ?>
                                    </td>
                                    <td class="pe-4 py-3" data-label="Usuario">
                                        <span class="badge bg-light text-dark border fw-normal px-2 py-1">
                                            <i class="bi bi-person-fill me-1 text-secondary"></i><?php echo e((string) ($row['usuario'] ?? '')); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
/* Estilos modernos Soft Bento */
.widget-bento {
    box-shadow: none !important;
}

.transition-hover {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.transition-hover:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 12px 20px rgba(0,0,0,0.06) !important;
}

.fade-in {
    animation: fadeIn 0.6s ease-in-out;
    animation-fill-mode: both;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.table-responsive::-webkit-scrollbar {
    height: 6px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 10px;
}
</style>

<script>
function navegarDesdeDashboard(event, urlString) {
    event.preventDefault(); 

    if (typeof window.navigateWithoutReload === 'function') {
        try {
            const urlObjeto = new URL(urlString, window.location.origin);
            window.navigateWithoutReload(urlObjeto, true);
        } catch (error) {
            console.error("Error al navegar con SPA:", error);
            window.location.href = urlString; 
        }
    } else {
        window.location.href = urlString;
    }
}
</script>