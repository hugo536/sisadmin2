<?php
/**
 * @var array|null $totales
 * @var array|null $eventos
 * @var array|null $cumpleanosSemana
 * @var array|null $reportes_widgets
 * @var array|null $inventario_valorizado
 */

$totales = is_array($totales ?? null) ? $totales : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$cumpleanosSemana = is_array($cumpleanosSemana ?? null) ? $cumpleanosSemana : [];
$reportesWidgets = is_array($reportes_widgets ?? null) ? $reportes_widgets : [];
$inventarioValorizado = is_array($inventario_valorizado ?? null) ? $inventario_valorizado : [];
$totalInventarioValorizado = (float) ($inventarioValorizado['total_inventario'] ?? 0);
?>

<div class="container-fluid p-4 dashboard-page" id="dashboardApp">
    
    <!-- ENCABEZADO MINIMALISTA -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 fade-in gap-3">
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

    <!-- ACCESOS OPERATIVOS (Estilo Soft Bento Moderno) -->
    <div class="mb-5">
        <h2 class="h5 fw-bold text-dark mb-3"><i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Accesos Operativos</h2>
        <div class="row g-3">
            
            <!-- Botón de Gráfico de Ventas integrado como Widget -->
            <div class="col-12 col-sm-6 col-lg-3">
                <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-primary text-white" 
                   href="<?php echo e(route_url('reportes/ventas')); ?>" 
                   onclick="navegarDesdeDashboard(event, this.href)"
                   style="border-radius: 1.25rem;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Análisis</div>
                            <div class="h5 mb-0 fw-bold text-white lh-1 mt-1">Gráfico de Ventas</div>
                        </div>
                        <div class="bg-white bg-opacity-25 text-white p-2 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                            <i class="bi bi-bar-chart-line-fill fs-4"></i>
                        </div>
                    </div>
                </a>
            </div>

            <?php 
            $widgetConfig = [
                'compras_pendientes'   => ['color' => 'warning', 'icon' => 'bi-cart-check', 'url' => 'reportes/compras'],
                'ventas_por_despachar' => ['color' => 'info',    'icon' => 'bi-truck',       'url' => 'reportes/ventas'],
                'produccion_proceso'   => ['color' => 'primary', 'icon' => 'bi-gear',        'url' => 'reportes/produccion'],
                'cxc_vencida'          => ['color' => 'danger',  'icon' => 'bi-cash-stack',  'url' => 'reportes/tesoreria'],
                'cxp_vencida'          => ['color' => 'danger',  'icon' => 'bi-wallet2',     'url' => 'reportes/tesoreria']
            ]; 
            ?>
            
            <?php foreach ($reportesWidgets as $k => $v): ?>
                <?php if ($k === 'stock_critico') continue; ?>
                <?php $cfg = $widgetConfig[$k] ?? ['color' => 'secondary', 'icon' => 'bi-arrow-right', 'url' => 'reportes/dashboard']; ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-<?php echo $cfg['color']; ?>-subtle" 
                       href="<?php echo e(route_url((string) $cfg['url'])); ?>" 
                       onclick="navegarDesdeDashboard(event, this.href)"
                       style="border-radius: 1.25rem;">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-<?php echo $cfg['color']; ?>-emphasis mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?php echo e(str_replace('_', ' ', (string) $k)); ?>
                                </div>
                                <div class="h3 mb-0 fw-bold text-<?php echo $cfg['color']; ?>-emphasis lh-1"><?php echo (int) $v; ?></div>
                            </div>
                            <div class="bg-white bg-opacity-75 text-<?php echo $cfg['color']; ?>-emphasis p-2 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                                <i class="bi <?php echo $cfg['icon']; ?> fs-4"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>

            <?php if (tiene_permiso('reportes.tesoreria.ver')): ?>
            <div class="col-12 col-sm-6 col-lg-3">
                <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-success-subtle" 
                   href="<?php echo e(route_url('reportes/estado_cuenta')); ?>" 
                   onclick="navegarDesdeDashboard(event, this.href)"
                   style="border-radius: 1.25rem;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-success-emphasis mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">E. Cuenta Clientes</div>
                            <div class="fw-bold text-success-emphasis" style="font-size: 0.95rem;">Ver Reporte</div>
                        </div>
                        <div class="bg-white bg-opacity-75 text-success-emphasis p-2 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                            <i class="bi bi-file-earmark-text fs-4"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <a class="card border-0 h-100 text-decoration-none widget-bento transition-hover bg-secondary-subtle" 
                   href="<?php echo e(route_url('reportes/estado_cuenta_proveedores')); ?>" 
                   onclick="navegarDesdeDashboard(event, this.href)"
                   style="border-radius: 1.25rem;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-secondary-emphasis mb-1" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">E. Cuenta Prov.</div>
                            <div class="fw-bold text-secondary-emphasis" style="font-size: 0.95rem;">Ver Reporte</div>
                        </div>
                        <div class="bg-white bg-opacity-75 text-secondary-emphasis p-2 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                            <i class="bi bi-file-earmark-text fs-4"></i>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ANÁLISIS DE INVENTARIO -->
    <div class="mb-5">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
            <h2 class="h5 fw-bold text-dark mb-0"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Análisis de Inventario</h2>
            <div class="fw-bold text-success bg-success-subtle px-3 py-2 rounded-pill border border-success-subtle shadow-sm fs-6">
                Valor Total: S/ <?php echo number_format($totalInventarioValorizado, 2); ?>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 1rem;">
                    <div class="card-body text-center p-4">
                        <h6 class="text-muted fw-bold mb-4 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">Distribución del Valor</h6>
                        <div style="position: relative; height: 260px; width: 100%;">
                            <canvas id="chartStockDona"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 1rem;">
                    <div class="card-body p-4">
                        <h6 class="text-muted fw-bold mb-4 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">Top 5 Artículos de Mayor Valor</h6>
                        <div style="position: relative; height: 260px; width: 100%;">
                            <canvas id="chartStockBarras"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTAS INFORMATIVAS (Cumpleaños y Bitácora) -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 1rem; overflow: hidden;">
                <div class="card-header bg-white border-bottom-0 px-4 py-3 d-flex justify-content-between align-items-center pt-4">
                    <h5 class="mb-0 fw-bold text-dark fs-6 text-uppercase" style="letter-spacing: 0.5px;">
                        <i class="bi bi-cake2-fill me-2 text-danger"></i>Cumpleaños de la semana
                    </h5>
                    <span class="badge rounded-pill bg-danger text-white px-3 py-1 shadow-sm">
                        <?php echo count($cumpleanosSemana); ?> programados
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if ($cumpleanosSemana === []): ?>
                        <div class="p-5 text-center text-muted">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-calendar2-heart fs-1 text-secondary opacity-50"></i>
                            </div>
                            <p class="mb-0 fw-semibold">No hay cumpleaños en los próximos 7 días.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover table-borderless">
                                <thead class="table-light text-muted" style="font-size: 0.75rem; text-transform: uppercase;">
                                    <tr>
                                        <th class="ps-4 fw-bold">Fecha</th>
                                        <th class="fw-bold">Empleado</th>
                                        <th class="text-center pe-4 fw-bold">Faltan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cumpleanosSemana as $cumple): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td class="ps-4 py-3" data-label="Fecha">
                                            <span class="d-inline-flex align-items-center gap-2 fw-bold text-dark bg-light px-2 py-1 rounded">
                                                <i class="bi bi-calendar-event text-primary"></i>
                                                <?php echo e((string) ($cumple['fecha_cumple'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td class="py-3" data-label="Empleado">
                                            <div class="fw-bold text-dark"><?php echo e((string) ($cumple['nombre_completo'] ?? '')); ?></div>
                                            <div class="text-muted" style="font-size: 0.8rem;"><?php echo e(trim((string) (($cumple['cargo'] ?? '') . ' / ' . ($cumple['area'] ?? '')), ' /')); ?></div>
                                        </td>
                                        <td class="pe-4 text-center py-3" data-label="Faltan">
                                            <?php if ((int) ($cumple['dias_restantes'] ?? 0) === 0): ?>
                                                <span class="badge px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle shadow-sm">🎉 Hoy (<?php echo (int) ($cumple['edad_cumple'] ?? 0); ?>)</span>
                                            <?php else: ?>
                                                <span class="badge px-3 py-2 rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle"><?php echo (int) ($cumple['dias_restantes'] ?? 0); ?> día(s)</span>
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

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 1rem; overflow: hidden;">
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
    /* Quita cualquier sombra y confía en el color de fondo para la jerarquía visual */
    box-shadow: none !important;
}

.transition-hover {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.transition-hover:hover {
    transform: translateY(-4px) scale(1.02); /* Ligero rebote hacia arriba y al frente */
    box-shadow: 0 12px 20px rgba(0,0,0,0.06) !important; /* Le añade sombra solo al pasar el mouse */
}

/* Animación sutil de entrada para la página */
.fade-in {
    animation: fadeIn 0.6s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Rediseño de scrollbar invisible para tablas pequeñas */
.table-responsive::-webkit-scrollbar {
    height: 6px;
}
.table-responsive::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 10px;
}
</style>

<script>
    window.datosInventario = {
        graficoDona: <?php echo json_encode($datosGraficoDona ?? []); ?>,
        graficoBarras: <?php echo json_encode($datosGraficoBarras ?? []); ?>
    };
</script>

<script>
// Función robusta para interceptar el clic de los widgets
function navegarDesdeDashboard(event, urlString) {
    event.preventDefault(); 

    if (typeof window.navigateWithoutReload === 'function') {
        try {
            const urlObjeto = new URL(urlString, window.location.origin);
            window.navigateWithoutReload(urlObjeto, true);
            
            // --- LÓGICA ANTI-PARPADEO (Bloqueo agresivo) ---
            let intentos = 0;
            
            // Usamos setInterval para forzar la selección cada 50 milisegundos
            const candadoMenu = setInterval(() => {
                const dashboardLink = document.querySelector('.sidebar a[href*="reportes/dashboard"], aside a[href*="reportes/dashboard"]');
                
                if(dashboardLink) {
                    // Quitamos la clase 'active' de cualquier otro lado
                    document.querySelectorAll('.sidebar a.active, aside a.active').forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Aseguramos que el Dashboard esté activo
                    dashboardLink.classList.add('active'); 
                    
                    const parentCollapse = dashboardLink.closest('.collapse');
                    if(parentCollapse) parentCollapse.classList.add('show');
                }
                
                intentos++;
                // Detenemos el candado después de medio segundo (10 intentos x 50ms)
                // Para este momento la plantilla ya se rindió y el Dashboard quedará fijo.
                if(intentos >= 10) {
                    clearInterval(candadoMenu);
                }
            }, 50); 
            // ----------------------------------------------
            
        } catch (error) {
            console.error("Error al navegar con SPA:", error);
            window.location.href = urlString; 
        }
    } else {
        window.location.href = urlString;
    }
}
</script>