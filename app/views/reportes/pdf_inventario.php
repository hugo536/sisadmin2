<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario</title>
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
        
        /* Footer fijo para la paginación */
        footer { position: fixed; bottom: -1cm; left: 0px; right: 0px; height: 1cm; text-align: right; font-size: 9px; color: #555; }
        .page-number:after { content: "Página " counter(page); }

        .titulo-doc { clear: both; text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; padding: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; letter-spacing: 1px; }

        .info-filtros { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-filtros td { padding: 5px 8px; border: 1px solid #000; font-size: 10px;}
        .info-filtros .label { font-weight: bold; background-color: #eee !important; text-align: right; width: 15%; }

        .seccion-titulo { font-size: 12px; font-weight: bold; margin-top: 20px; margin-bottom: 5px; background-color: #f1f1f1; padding: 5px; border: 1px solid #000; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        .detalle-tabla th { background-color: #eee !important; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 9.5px;}
        
        /* Evita que las filas se corten a la mitad en el salto de página */
        .detalle-tabla tr { page-break-inside: avoid; }
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .critico { color: #d32f2f !important; font-weight: bold; }
        .normal { color: #2e7d32 !important; }
    </style>
</head>
<body>

    <footer>
        <span class="page-number"></span>
    </footer>

    <?php 
        $seccionActiva = $filtros['seccion_activa'] ?? 'stock';
        $tituloReporte = '';
        if ($seccionActiva === 'stock') $tituloReporte = 'REPORTE DE STOCK ACTUAL VALORIZADO';
        if ($seccionActiva === 'historico') $tituloReporte = 'REPORTE DE STOCK A FECHA DE CORTE (HISTÓRICO)';
        if ($seccionActiva === 'kardex') $tituloReporte = 'REPORTE DE KARDEX (MOVIMIENTOS)';
        if ($seccionActiva === 'vencimientos') $tituloReporte = 'REPORTE DE LOTES Y VENCIMIENTOS';
    ?>

    <div class="titulo-doc"><?php echo $tituloReporte; ?></div>

    <table class="info-filtros">
        <tr>
            <td class="label">PERIODO:</td>
            <td style="width: 35%;">
                <?php if($seccionActiva === 'kardex'): ?>
                    <?php echo date('d/m/Y', strtotime($filtros['fecha_desde'] ?? date('Y-m-d'))); ?> AL <?php echo date('d/m/Y', strtotime($filtros['fecha_hasta'] ?? date('Y-m-d'))); ?>
                <?php elseif($seccionActiva === 'historico'): ?>
                    CORTE EXACTO AL: <?php echo date('d/m/Y - h:i A', strtotime($filtros['fecha_corte'] ?? date('Y-m-d H:i:s'))); ?>
                <?php else: ?>
                    AL DÍA DE HOY (<?php echo date('d/m/Y - H:i:s'); ?>)
                <?php endif; ?>
            </td>
            <td class="label">TIPO / CATEGORÍA:</td>
            <td style="width: 35%; text-transform: uppercase;">
                <?php echo htmlspecialchars((string) (($filtros['tipo_item'] ?? 'TODOS LOS TIPOS') . ' / ' . ($filtros['id_categoria'] ? ('CAT. #' . (int) $filtros['id_categoria']) : 'TODAS LAS CATEGORÍAS'))); ?>
            </td>
        </tr>
        <tr>
            <td class="label">ALMACÉN:</td>
            <td>
                <?php echo htmlspecialchars((string)($almacenNombre ?? 'TODOS LOS ALMACENES')); ?>
            </td>
            <td class="label">SITUACIÓN:</td>
            <td>
                <?php 
                    if (!empty($filtros['solo_bajo_minimo'])) {
                        echo 'SOLO BAJO MÍNIMO';
                    } else {
                        echo htmlspecialchars((string) ($filtros['situacion_alerta'] ?: 'TODAS')); 
                    }
                ?>
            </td>
        </tr>
    </table>

    <?php if($seccionActiva === 'stock'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 30%;">ÍTEM</th>
                    <th style="width: 15%;">ALMACÉN</th>
                    <th style="width: 10%;" class="text-right">STOCK</th>
                    <th style="width: 10%;" class="text-right">C/U</th>
                    <th style="width: 15%;" class="text-right">VALOR TOTAL</th>
                    <th style="width: 20%;" class="text-center">ESTADO</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($stock['rows'])): ?>
                    <tr><td colspan="6" class="text-center">No hay registros de stock.</td></tr>
                <?php else: ?>
                    <?php foreach (($stock['rows'] ?? []) as $r): 
                        $alertaTexto = (string)($r['alerta'] ?? '');
                        $esCritico = stripos($alertaTexto, 'bajo') !== false || stripos($alertaTexto, 'crítico') !== false || stripos($alertaTexto, 'critico') !== false;
                        $usaDecimales = (int)($r['permite_decimales'] ?? 0) === 1;
                        $stockFormateado = number_format((float)($r['stock_actual'] ?? 0), $usaDecimales ? 3 : 0, '.', ',');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$r['item']); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['almacen']); ?></td>
                        <td class="text-right fw-bold <?php echo $esCritico ? 'critico' : 'normal'; ?>"><?php echo htmlspecialchars($stockFormateado); ?> <?php echo htmlspecialchars((string)($r['unidad'] ?? '')); ?></td>
                        <td class="text-right">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 2); ?></td>
                        <td class="text-right fw-bold">S/ <?php echo number_format((float)($r['valor_total'] ?? 0), 2); ?></td>
                        <td class="text-center <?php echo $esCritico ? 'critico' : 'normal'; ?>">
                            <?php echo htmlspecialchars($alertaTexto !== '' ? $alertaTexto : 'OK'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4" class="text-right fw-bold">VALOR TOTAL DE INVENTARIO:</td>
                        <td class="text-right fw-bold" style="background-color: #eee;">
                            S/ <?php echo number_format((float)($stock['valor_total'] ?? 0), 2); ?>
                        </td>
                        <td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if($seccionActiva === 'historico'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 35%;">ÍTEM</th>
                    <th style="width: 20%;">ALMACÉN</th>
                    <th style="width: 10%;" class="text-right">STOCK</th>
                    <th style="width: 10%;" class="text-center">UNIDAD</th>
                    <th style="width: 10%;" class="text-right">C/U</th>
                    <th style="width: 15%;" class="text-right">VALOR TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($historico['rows'])): ?>
                    <tr><td colspan="6" class="text-center">No hay registros para la fecha solicitada.</td></tr>
                <?php else: ?>
                    <?php foreach (($historico['rows'] ?? []) as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$r['item']); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['almacen']); ?></td>
                        <td class="text-right fw-bold" style="color: #0056b3;"><?php echo number_format((float)($r['stock_actual'] ?? 0), 2, '.', ','); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars((string)($r['unidad'] ?? '')); ?></td>
                        <td class="text-right">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 4); ?></td>
                        <td class="text-right fw-bold">S/ <?php echo number_format((float)($r['valor_total'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4" class="text-right fw-bold">VALOR TOTAL A LA FECHA DE CORTE:</td>
                        <td colspan="2" class="text-right fw-bold" style="background-color: #eee; color: #0056b3;">
                            S/ <?php echo number_format((float)($historico['valor_total'] ?? 0), 2); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if($seccionActiva === 'kardex'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 12%;" class="text-center">FECHA</th>
                    <th style="width: 13%;" class="text-center">TIPO</th>
                    <th style="width: 25%;">REFERENCIA</th>
                    <th style="width: 10%;" class="text-right">CANTIDAD</th>
                    <th style="width: 15%;" class="text-right">C/U</th>
                    <th style="width: 15%;" class="text-right">TOTAL</th>
                    <th style="width: 10%;" class="text-center">USUARIO</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($kardex['rows'])): ?>
                    <tr><td colspan="7" class="text-center">No hay movimientos en este periodo.</td></tr>
                <?php else: ?>
                    <?php foreach (($kardex['rows'] ?? []) as $r): 
                        $tipoStr = (string)($r['tipo'] ?? '');
                        $esIngreso = stripos($tipoStr, 'ingreso') !== false || stripos($tipoStr, 'entrada') !== false;
                    ?>
                    <tr>
                        <td class="text-center"><?php echo htmlspecialchars((string)$r['fecha']); ?></td>
                        <td class="text-center <?php echo $esIngreso ? 'normal' : 'critico'; ?>"><?php echo htmlspecialchars($tipoStr); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['referencia']); ?></td>
                        <td class="text-right fw-bold">
                            <?php echo $esIngreso ? '+' : '-'; ?> <?php echo htmlspecialchars((string)$r['cantidad']); ?>
                        </td>
                        <td class="text-right">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 2); ?></td>
                        <td class="text-right">S/ <?php echo number_format((float)($r['costo_total'] ?? 0), 2); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars((string)($r['usuario'] ?? '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if($seccionActiva === 'vencimientos'): ?>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 35%;">ÍTEM</th>
                    <th style="width: 15%;">ALMACÉN</th>
                    <th style="width: 15%;" class="text-center">LOTE</th>
                    <th style="width: 15%;" class="text-center">VENCIMIENTO</th>
                    <th style="width: 10%;" class="text-right">STOCK</th>
                    <th style="width: 10%;" class="text-center">ALERTA</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($vencimientos['rows'])): ?>
                    <tr><td colspan="6" class="text-center">No hay registros de lotes.</td></tr>
                <?php else: ?>
                    <?php foreach (($vencimientos['rows'] ?? []) as $r): 
                        $alertaVenc = (string)($r['alerta'] ?? '');
                        $esVencido = stripos($alertaVenc, 'vencido') !== false;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$r['item']); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['almacen']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars((string)$r['lote']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars((string)$r['fecha_vencimiento']); ?></td>
                        <td class="text-right fw-bold"><?php echo htmlspecialchars((string)$r['stock_lote']); ?></td>
                        <td class="text-center <?php echo $esVencido ? 'critico' : ''; ?>">
                            <?php echo htmlspecialchars($alertaVenc !== '' ? $alertaVenc : 'Normal'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>