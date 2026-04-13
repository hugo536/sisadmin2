<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        @page { margin: 1cm; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10px; 
            color: #000; 
            margin: 0; 
            padding: 0; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        
        .titulo-doc { clear: both; text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; padding: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; letter-spacing: 1px; }

        .info-filtros { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-filtros td { padding: 5px 8px; border: 1px solid #000; font-size: 10px;}
        .info-filtros .label { font-weight: bold; background-color: #eee !important; text-align: right; width: 15%; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        .detalle-tabla th { background-color: #eee !important; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 9.5px;}
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .text-success { color: #198754 !important; }
        .text-primary { color: #0d6efd !important; }
        .text-danger { color: #dc3545 !important; }
        .fw-bold { font-weight: bold; }
        .bg-light { background-color: #eee !important; }
    </style>
</head>
<body>

    <?php 
        $seccionActiva = $filtros['seccion_activa'] ?? 'tendencias';
        $tituloReporte = '';
        if ($seccionActiva === 'tendencias') $tituloReporte = 'REPORTE DE VENTAS POR PERIODO';
        if ($seccionActiva === 'clientes') $tituloReporte = 'REPORTE DE VENTAS POR CLIENTE';
        if ($seccionActiva === 'productos') $tituloReporte = 'REPORTE TOP PRODUCTOS VENDIDOS';
        if ($seccionActiva === 'pendientes') $tituloReporte = 'REPORTE DE DESPACHOS PENDIENTES';
    ?>

    <div class="titulo-doc"><?php echo $tituloReporte; ?></div>

    <table class="info-filtros">
        <tr>
            <td class="label">PERIODO:</td>
            <td style="width: 35%;">
                <?php echo date('d/m/Y', strtotime($filtros['fecha_desde'] ?? date('Y-m-d'))); ?> AL <?php echo date('d/m/Y', strtotime($filtros['fecha_hasta'] ?? date('Y-m-d'))); ?>
            </td>
            <td class="label">ESTADO DOC.:</td>
            <td style="width: 35%;">
                <?php 
                    if (($filtros['estado'] ?? '') === '1') echo 'ACTIVAS';
                    elseif (($filtros['estado'] ?? '') === '0') echo 'ANULADAS';
                    else echo 'TODAS';
                ?>
            </td>
        </tr>
        <tr>
            <td class="label">TIPO TERCERO:</td>
            <td>
                <?php 
                    $tipoTercero = $filtros['tipo_tercero'] ?? '';
                    if ($tipoTercero === 'cliente') echo 'CLIENTES';
                    elseif ($tipoTercero === 'cliente_distribuidor') echo 'CLIENTE-DISTRIBUIDOR';
                    elseif ($tipoTercero === 'distribuidor') echo 'DISTRIBUIDOR';
                    else echo 'TODOS';
                ?>
            </td>
            <td class="label">FILTRO ESPECÍFICO:</td>
            <td>
                <?php 
                    if (!empty($filtros['id_cliente'])) echo 'CLIENTE ID: ' . (int)$filtros['id_cliente'];
                    elseif (!empty($filtros['id_item'])) echo 'PRODUCTO ID: ' . (int)$filtros['id_item'];
                    else echo 'NINGUNO (TODOS)';
                ?>
            </td>
        </tr>
    </table>

    <?php if($seccionActiva === 'tendencias'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 50%;">PERIODO (<?php echo strtoupper($filtros['agrupacion'] ?? 'DIARIA'); ?>)</th>
                    <th style="width: 20%;">DOCUMENTOS</th>
                    <th style="width: 30%;">TOTAL VENDIDO</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($porPeriodo)): ?>
                    <tr><td colspan="3" class="text-center">No hay registros para este periodo.</td></tr>
                <?php else: ?>
                    <?php 
                        $totalDocs = 0;
                        $totalVendido = 0;
                        foreach ($porPeriodo as $r): 
                            $totalDocs += (int)($r['documentos'] ?? 0);
                            $totalVendido += (float)($r['total_vendido'] ?? 0);
                    ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars((string)($r['etiqueta'] ?? '-')); ?></td>
                        <td class="text-right"><?php echo htmlspecialchars((string)($r['documentos'] ?? '0')); ?></td>
                        <td class="text-right fw-bold text-success">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td class="text-right fw-bold bg-light">TOTAL GENERAL:</td>
                        <td class="text-right fw-bold bg-light"><?php echo number_format($totalDocs); ?></td>
                        <td class="text-right fw-bold text-success bg-light">S/ <?php echo number_format($totalVendido, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if($seccionActiva === 'clientes'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 45%;">CLIENTE</th>
                    <th style="width: 15%;">DOCS. EMITIDOS</th>
                    <th style="width: 20%;">TICKET PROMEDIO</th>
                    <th style="width: 20%;">TOTAL VENDIDO</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($porCliente['rows'])): ?>
                    <tr><td colspan="4" class="text-center">No hay registros de ventas.</td></tr>
                <?php else: ?>
                    <?php 
                        $sumDocs = 0;
                        $sumTotal = 0;
                        foreach (($porCliente['rows'] ?? []) as $r): 
                            $sumDocs += (int)($r['documentos'] ?? 0);
                            $sumTotal += (float)($r['total_vendido'] ?? 0);
                    ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars((string)$r['cliente']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars((string)$r['documentos']); ?></td>
                        <td class="text-right">S/ <?php echo number_format((float)($r['ticket_promedio'] ?? 0), 2); ?></td>
                        <td class="text-right fw-bold text-success">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="2" class="text-right fw-bold bg-light">TOTAL CLIENTES LISTADOS: <?php echo count($porCliente['rows']); ?></td>
                        <td class="text-right fw-bold bg-light">TOTAL GENERAL:</td>
                        <td class="text-right fw-bold text-success bg-light">S/ <?php echo number_format($sumTotal, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if($seccionActiva === 'productos'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 60%;">PRODUCTO</th>
                    <th style="width: 20%;">CANTIDAD VENDIDA</th>
                    <th style="width: 20%;">MONTO GENERADO</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($topProductos)): ?>
                    <tr><td colspan="3" class="text-center">No hay productos vendidos.</td></tr>
                <?php else: ?>
                    <?php 
                        $sumCant = 0;
                        $sumMonto = 0;
                        foreach (($topProductos ?? []) as $r): 
                            $sumCant += (float)($r['total_cantidad'] ?? 0);
                            $sumMonto += (float)($r['total_monto'] ?? 0);
                    ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars((string)$r['producto']); ?></td>
                        <td class="text-right fw-bold text-primary"><?php echo number_format((float)($r['total_cantidad'] ?? 0), 2); ?></td>
                        <td class="text-right fw-bold">S/ <?php echo number_format((float)($r['total_monto'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td class="text-right fw-bold bg-light">TOTALES TOP PRODUCTOS:</td>
                        <td class="text-right fw-bold text-primary bg-light"><?php echo number_format($sumCant, 2); ?></td>
                        <td class="text-right fw-bold bg-light">S/ <?php echo number_format($sumMonto, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if($seccionActiva === 'pendientes'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 15%;">DOCUMENTO</th>
                    <th style="width: 35%;">CLIENTE</th>
                    <th style="width: 20%;">ALMACÉN ORIGEN</th>
                    <th style="width: 15%;">TIEMPO ESPERA</th>
                    <th style="width: 15%;">SALDO PENDIENTE</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pendientes['rows'])): ?>
                    <tr><td colspan="5" class="text-center">Todo al día. No hay despachos pendientes.</td></tr>
                <?php else: ?>
                    <?php 
                        $sumPendiente = 0;
                        foreach (($pendientes['rows'] ?? []) as $r): 
                            $dias = (int)($r['dias_desde_emision'] ?? 0);
                            $esDonacion = ($r['tipo_operacion'] ?? '') === 'DONACION';
                            $claseDias = $dias >= 7 ? 'text-danger fw-bold' : '';
                            $sumPendiente += (float)($r['saldo_despachar'] ?? 0);
                    ?>
                    <tr>
                        <td class="fw-bold text-primary">
                            <?php echo htmlspecialchars((string)$r['documento']); ?>
                            <?php if($esDonacion): ?><br><span style="font-size: 8px; color: #0dcaf0;">(DONACIÓN)</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$r['cliente']); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['almacen']); ?></td>
                        <td class="text-center <?php echo $claseDias; ?>"><?php echo $dias; ?> día(s)</td>
                        <td class="text-right fw-bold text-danger"><?php echo number_format((float)($r['saldo_despachar'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4" class="text-right fw-bold bg-light">MERCADERÍA TOTAL PENDIENTE DE DESPACHO:</td>
                        <td class="text-right fw-bold text-danger bg-light"><?php echo number_format($sumPendiente, 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>