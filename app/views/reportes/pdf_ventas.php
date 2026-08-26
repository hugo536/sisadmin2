<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        /* Márgenes de página ajustados para dar espacio al pie de página */
        @page { margin: 1.5cm 1cm 1.5cm 1cm; }
        
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10px; 
            color: #333; /* Gris muy oscuro, más elegante que el negro puro */
            margin: 0; 
            padding: 0; 
        }
        
        /* Pie de página fijo para todas las hojas */
        footer {
            position: fixed; 
            bottom: -1cm; 
            left: 0px; 
            right: 0px;
            height: 1cm; 
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 8px;
            display: table;
            width: 100%;
        }

        .titulo-doc { 
            clear: both; 
            text-align: center; 
            font-size: 16px; 
            font-weight: bold; 
            color: #2c3e50; 
            text-transform: uppercase; 
            margin-bottom: 15px; 
            padding-bottom: 8px; 
            border-bottom: 2px solid #2c3e50; 
            letter-spacing: 1px; 
        }

        /* Estilo elegante para la caja de filtros */
        .info-filtros { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .info-filtros td { 
            padding: 6px 10px; 
            border-bottom: 1px solid #e9ecef; 
            font-size: 9.5px;
        }
        .info-filtros .label { 
            font-weight: bold; 
            color: #495057; 
            text-align: right; 
            width: 15%; 
            text-transform: uppercase;
            border-right: 1px solid #e9ecef;
        }

        /* Tablas de datos modernas */
        .detalle-tabla { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #dee2e6; padding: 6px 8px; text-align: left; }
        .detalle-tabla th { 
            background-color: #343a40 !important; /* Cabecera oscura */
            color: #ffffff !important; 
            font-weight: bold; 
            text-align: center; 
            text-transform: uppercase; 
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        
        /* Filas cebra para legibilidad */
        .detalle-tabla tbody tr:nth-child(even) { background-color: #f8f9fa !important; }
        
        /* Totales destacados */
        .detalle-tabla tfoot td {
            background-color: #e9ecef !important;
            font-weight: bold;
            font-size: 10px;
            border-top: 2px solid #6c757d;
        }
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .text-success { color: #198754 !important; }
        .text-primary { color: #0d6efd !important; }
        .text-danger { color: #dc3545 !important; }
        .text-warning { color: #ffc107 !important; }
        .fw-bold { font-weight: bold; }
    </style>
</head>
<body>

    <footer>
        <table style="width: 100%; border: none;">
            <tr>
                <td style="text-align: left; width: 50%; border: none;">Generado el: <?php echo date('d/m/Y H:i:s'); ?></td>
                <td style="text-align: right; width: 50%; border: none;">Documento Interno - Confidencial</td>
            </tr>
        </table>
    </footer>

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
                <strong><?php echo date('d/m/Y', strtotime($filtros['fecha_desde'] ?? date('Y-m-d'))); ?></strong> AL <strong><?php echo date('d/m/Y', strtotime($filtros['fecha_hasta'] ?? date('Y-m-d'))); ?></strong>
            </td>
            <td class="label">ESTADO DOC.:</td>
            <td style="width: 35%;">
                <?php 
                    $estadoFiltro = $filtros['estado'] ?? 'validas';
                    if ($estadoFiltro === 'validas') echo 'VENTAS VÁLIDAS';
                    elseif ($estadoFiltro === '1') echo 'ACTIVAS';
                    elseif ($estadoFiltro === '2') echo 'APROBADO (POR DESPACHAR)';
                    elseif ($estadoFiltro === '3') echo 'CERRADO / ENTREGADO';
                    elseif ($estadoFiltro === '0') echo 'BORRADORES';
                    elseif ($estadoFiltro === '9') echo 'ANULADAS';
                    else echo 'TODAS (SIN FILTRO)';
                ?>
            </td>
        </tr>
        
        <?php if ($seccionActiva === 'tendencias'): ?>
            <tr>
                <td class="label">DETALLES:</td>
                <td colspan="3">AGRUPADO POR: <strong><?php echo strtoupper($filtros['agrupacion'] ?? 'DIARIA'); ?></strong></td>
            </tr>
        <?php elseif ($seccionActiva === 'clientes' || $seccionActiva === 'pendientes'): ?>
            <tr>
                <td class="label">DETALLES:</td>
                <td colspan="3">
                    TIPO TERCERO: <strong>
                    <?php 
                        $tipoTercero = $filtros['tipo_tercero'] ?? '';
                        if ($tipoTercero === 'cliente') echo 'CLIENTES';
                        elseif ($tipoTercero === 'cliente_distribuidor') echo 'CLIENTE-DISTRIBUIDOR';
                        elseif ($tipoTercero === 'distribuidor') echo 'DISTRIBUIDOR';
                        else echo 'TODOS';
                    ?></strong> 
                    &nbsp;|&nbsp; CLIENTE FILTRADO: <strong><?php echo !empty($filtros['id_cliente']) ? (int)$filtros['id_cliente'] : 'TODOS'; ?></strong>
                </td>
            </tr>
        <?php elseif ($seccionActiva === 'productos'): ?>
            <tr>
                <td class="label">DETALLES:</td>
                <td colspan="3">
                    PRODUCTO FILTRADO: <strong><?php echo !empty($filtros['id_item']) ? (int)$filtros['id_item'] : 'TODOS LOS PRODUCTOS'; ?></strong>
                </td>
            </tr>
        <?php endif; ?>
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
                        <td class="fw-bold">
                            <?php 
                                $etiqueta = (string)($r['etiqueta'] ?? '-');
                                $agrupacion = $filtros['agrupacion'] ?? 'diaria';
                                // CORRECCIÓN FECHA: Validamos y formateamos a d/m/Y si es diaria
                                if ($agrupacion === 'diaria' && strtotime($etiqueta)) {
                                    echo date('d/m/Y', strtotime($etiqueta));
                                } else {
                                    echo htmlspecialchars($etiqueta);
                                }
                            ?>
                        </td>
                        <td class="text-right"><?php echo htmlspecialchars((string)($r['documentos'] ?? '0')); ?></td>
                        <td class="text-right fw-bold text-success">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if(!empty($porPeriodo)): ?>
            <tfoot>
                <tr>
                    <td class="text-right">TOTAL GENERAL:</td>
                    <td class="text-right"><?php echo number_format($totalDocs); ?></td>
                    <td class="text-right text-success">S/ <?php echo number_format($totalVendido, 2); ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
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
                <?php endif; ?>
            </tbody>
            <?php if(!empty($porCliente['rows'])): ?>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right">TOTAL CLIENTES LISTADOS: <?php echo count($porCliente['rows']); ?></td>
                    <td class="text-right">TOTAL GENERAL:</td>
                    <td class="text-right text-success">S/ <?php echo number_format($sumTotal, 2); ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
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
                <?php endif; ?>
            </tbody>
            <?php if(!empty($topProductos)): ?>
            <tfoot>
                <tr>
                    <td class="text-right">TOTALES TOP PRODUCTOS:</td>
                    <td class="text-right text-primary"><?php echo number_format($sumCant, 2); ?></td>
                    <td class="text-right">S/ <?php echo number_format($sumMonto, 2); ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
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
                            <?php if($esDonacion): ?><br><span style="font-size: 8px; color: #17a2b8;">(DONACIÓN)</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$r['cliente']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars((string)$r['almacen']); ?></td>
                        <td class="text-center <?php echo $claseDias; ?>"><?php echo $dias; ?> día(s)</td>
                        <td class="text-right fw-bold text-danger"><?php echo number_format((float)($r['saldo_despachar'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if(!empty($pendientes['rows'])): ?>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">MERCADERÍA TOTAL PENDIENTE DE DESPACHO:</td>
                    <td class="text-right text-danger">S/ <?php echo number_format($sumPendiente, 2); ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    <?php endif; ?>

</body>
</html>