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
        
        .titulo-doc { clear: both; text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; padding: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; letter-spacing: 1px; }

        .info-filtros { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-filtros td { padding: 5px 8px; border: 1px solid #000; font-size: 10px;}
        .info-filtros .label { font-weight: bold; background-color: #eee !important; text-align: right; width: 15%; }

        .seccion-titulo { font-size: 12px; font-weight: bold; margin-top: 20px; margin-bottom: 5px; background-color: #f1f1f1; padding: 5px; border: 1px solid #000; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        .detalle-tabla th { background-color: #eee !important; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 9.5px;}
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .critico { color: #d32f2f !important; font-weight: bold; }
        .normal { color: #2e7d32 !important; }
        
        /* Clase para separar las tablas en distintas páginas del PDF */
        .salto-pagina { page-break-before: always; }
    </style>
</head>
<body>

    <div class="titulo-doc">REPORTE CONSOLIDADO DE INVENTARIO</div>

    <table class="info-filtros">
        <tr>
            <td class="label">PERIODO:</td>
            <td style="width: 35%;">
                <?php echo date('d/m/Y', strtotime($filtros['fecha_desde'] ?? date('Y-m-d'))); ?> AL <?php echo date('d/m/Y', strtotime($filtros['fecha_hasta'] ?? date('Y-m-d'))); ?>
            </td>
            <td class="label">TIPO PRODUCTO:</td>
            <td style="width: 35%; text-transform: uppercase;">
                <?php echo htmlspecialchars($filtros['tipo_producto'] ?? 'TODOS LOS TIPOS'); ?>
            </td>
        </tr>
        <tr>
            <td class="label">ALMACÉN:</td>
            <td colspan="3">
                <?php echo htmlspecialchars($filtros['nombre_almacen'] ?? 'TODOS LOS ALMACENES'); ?>
            </td>
        </tr>
    </table>

    <?php 
    // Variable auxiliar para saber si ya imprimimos una sección y poner el salto de página
    $imprimio_seccion_previa = false; 
    ?>

    <?php if(in_array('stock', $filtros['secciones'] ?? [])): ?>
        <div class="seccion-titulo">1. STOCK ACTUAL (Valorizado)</div>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 30%;">ÍTEM</th>
                    <th style="width: 15%;">ALMACÉN</th>
                    <th style="width: 10%;">STOCK</th>
                    <th style="width: 10%;">C/U</th>
                    <th style="width: 15%;">VALOR TOTAL</th>
                    <th style="width: 20%;">ESTADO</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($stock['rows'])): ?>
                    <tr><td colspan="6" class="text-center">No hay registros de stock.</td></tr>
                <?php else: ?>
                    <?php foreach (($stock['rows'] ?? []) as $r): 
                        $alertaTexto = (string)($r['alerta'] ?? '');
                        $esCritico = stripos($alertaTexto, 'bajo') !== false || stripos($alertaTexto, 'crítico') !== false;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$r['item']); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['almacen']); ?></td>
                        <td class="text-right fw-bold <?php echo $esCritico ? 'critico' : 'normal'; ?>"><?php echo htmlspecialchars((string)$r['stock_actual']); ?> <?php echo htmlspecialchars((string)($r['unidad'] ?? '')); ?></td>
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
        <?php $imprimio_seccion_previa = true; ?>
    <?php endif; ?>

    <?php if(in_array('kardex', $filtros['secciones'] ?? [])): ?>
        <?php if($imprimio_seccion_previa): ?>
            <div class="salto-pagina"></div>
        <?php endif; ?>
        
        <div class="seccion-titulo">2. MOVIMIENTOS KARDEX</div>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 12%;">FECHA</th>
                    <th style="width: 13%;">TIPO</th>
                    <th style="width: 25%;">REFERENCIA</th>
                    <th style="width: 10%;">CANTIDAD</th>
                    <th style="width: 15%;">C/U</th>
                    <th style="width: 15%;">TOTAL</th>
                    <th style="width: 10%;">USUARIO</th>
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
        <?php $imprimio_seccion_previa = true; ?>
    <?php endif; ?>

    <?php if(in_array('vencimientos', $filtros['secciones'] ?? [])): ?>
        <?php if($imprimio_seccion_previa): ?>
            <div class="salto-pagina"></div>
        <?php endif; ?>

        <div class="seccion-titulo">3. CONTROL DE LOTES Y VENCIMIENTOS</div>
        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 35%;">ÍTEM</th>
                    <th style="width: 15%;">ALMACÉN</th>
                    <th style="width: 15%;">LOTE</th>
                    <th style="width: 15%;">VENCIMIENTO</th>
                    <th style="width: 10%;">STOCK</th>
                    <th style="width: 10%;">ALERTA</th>
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