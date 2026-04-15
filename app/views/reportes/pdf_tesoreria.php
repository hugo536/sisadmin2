<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Tesorería</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .titulo-reporte {
            font-size: 18pt;
            font-weight: bold;
            color: #0d6efd;
            margin: 0 0 5px 0;
        }
        .filtros-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 9pt;
        }
        .filtros-box strong {
            color: #495057;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #dee2e6;
            padding: 8px 10px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f1f3f5;
            font-weight: bold;
            color: #495057;
            font-size: 9pt;
            text-transform: uppercase;
        }
        table.data-table td {
            font-size: 9pt;
        }
        .text-center { text-align: center !important; }
        .text-end { text-align: right !important; }
        .text-success { color: #198754 !important; }
        .text-danger { color: #dc3545 !important; }
        .fw-bold { font-weight: bold !important; }
        .badge {
            background-color: #e9ecef;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 8pt;
            color: #495057;
        }
        .total-box {
            width: 100%;
            text-align: right;
            margin-top: 15px;
            font-size: 12pt;
        }
        .footer {
            position: fixed;
            bottom: -15px;
            left: 0;
            right: 0;
            width: 100%;
            font-size: 8pt;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 5px;
        }
        .footer table {
            width: 100%;
        }
        .footer td {
            border: none;
        }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>

<?php
    $seccionActiva = $filtros['seccion_activa'] ?? 'cxc';
    
    $tituloReporte = 'Reporte de Tesorería';
    if ($seccionActiva === 'cxc') $tituloReporte = 'Aging Cuentas por Cobrar (CxC)';
    if ($seccionActiva === 'cxp') $tituloReporte = 'Aging Cuentas por Pagar (CxP)';
    if ($seccionActiva === 'flujo') $tituloReporte = 'Flujo de Caja por Cuenta';
    if ($seccionActiva === 'depositos') $tituloReporte = 'Reporte de Ingresos y Depósitos';

    $fechaDesdeFmt = date('d/m/Y', strtotime((string)($filtros['fecha_desde'] ?? date('Y-m-d'))));
    $fechaHastaFmt = date('d/m/Y', strtotime((string)($filtros['fecha_hasta'] ?? date('Y-m-d'))));
    $fechaImpresion = date('d/m/Y H:i');
?>

<div class="header">
    <table>
        <tr>
            <td>
                <h1 class="titulo-reporte"><?php echo $tituloReporte; ?></h1>
                <span style="color: #6c757d; font-size: 10pt;">Generado el: <?php echo $fechaImpresion; ?></span>
            </td>
            <td class="text-end" style="width: 200px;">
                <h2 style="margin:0; color:#333; font-size: 14pt;">SISADMIN ERP</h2>
            </td>
        </tr>
    </table>
</div>

<div class="filtros-box">
    <table style="width: 100%; border: none;">
        <tr>
            <td style="border: none; width: 33%;">
                <strong>Fecha Desde:</strong> <?php echo $fechaDesdeFmt; ?>
            </td>
            <td style="border: none; width: 33%;">
                <strong>Fecha Hasta:</strong> <?php echo $fechaHastaFmt; ?>
            </td>
            <td style="border: none; width: 33%;">
                <strong>Filtro Cuenta ID:</strong> <?php echo !empty($filtros['id_cuenta']) ? $filtros['id_cuenta'] : 'Todas las cuentas'; ?>
            </td>
        </tr>
    </table>
</div>

<?php if ($seccionActiva === 'cxc'): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th class="text-end">Saldo Pendiente</th>
                <th class="text-center">Vencimiento</th>
                <th class="text-center">Días Atraso</th>
                <th class="text-center">Bucket</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($agingCxc['rows'])): ?>
                <tr><td colspan="5" class="text-center">No hay registros para mostrar en este periodo.</td></tr>
            <?php else: ?>
                <?php 
                    $sumaCxc = 0;
                    foreach ($agingCxc['rows'] as $r): 
                    $sumaCxc += (float)$r['saldo'];
                ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars((string)$r['cliente']); ?></td>
                        <td class="text-end text-success fw-bold">S/ <?php echo number_format((float)$r['saldo'], 2); ?></td>
                        <td class="text-center"><?php echo date('d/m/Y', strtotime((string)$r['fecha_vencimiento'])); ?></td>
                        <td class="text-center"><?php echo (int)$r['dias_atraso']; ?> días</td>
                        <td class="text-center"><span class="badge"><?php echo htmlspecialchars((string)$r['bucket']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if (!empty($agingCxc['rows'])): ?>
    <div class="total-box">
        <strong>Total Cuentas por Cobrar: <span class="text-success">S/ <?php echo number_format($sumaCxc, 2); ?></span></strong>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($seccionActiva === 'cxp'): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Proveedor</th>
                <th class="text-end">Saldo Pendiente</th>
                <th class="text-center">Vencimiento</th>
                <th class="text-center">Días Atraso</th>
                <th class="text-center">Bucket</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($agingCxp['rows'])): ?>
                <tr><td colspan="5" class="text-center">No hay registros para mostrar en este periodo.</td></tr>
            <?php else: ?>
                <?php 
                    $sumaCxp = 0;
                    foreach ($agingCxp['rows'] as $r): 
                    $sumaCxp += (float)$r['saldo'];
                ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars((string)$r['proveedor']); ?></td>
                        <td class="text-end text-danger fw-bold">S/ <?php echo number_format((float)$r['saldo'], 2); ?></td>
                        <td class="text-center"><?php echo date('d/m/Y', strtotime((string)$r['fecha_vencimiento'])); ?></td>
                        <td class="text-center"><?php echo (int)$r['dias_atraso']; ?> días</td>
                        <td class="text-center"><span class="badge"><?php echo htmlspecialchars((string)$r['bucket']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if (!empty($agingCxp['rows'])): ?>
    <div class="total-box">
        <strong>Total Cuentas por Pagar: <span class="text-danger">S/ <?php echo number_format($sumaCxp, 2); ?></span></strong>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($seccionActiva === 'flujo'): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Cuenta Bancaria / Caja</th>
                <th class="text-end">Total Ingresos</th>
                <th class="text-end">Total Egresos</th>
                <th class="text-end">Saldo Neto</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($flujo['rows'])): ?>
                <tr><td colspan="4" class="text-center">No hay movimientos registrados en este periodo.</td></tr>
            <?php else: ?>
                <?php 
                    $sumIngresos = 0; $sumEgresos = 0; $sumNeto = 0;
                    foreach ($flujo['rows'] as $r): 
                        $sumIngresos += (float)$r['total_ingresos'];
                        $sumEgresos += (float)$r['total_egresos'];
                        $sumNeto += (float)$r['saldo_neto'];
                ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars((string)$r['cuenta']); ?></td>
                        <td class="text-end text-success">S/ <?php echo number_format((float)$r['total_ingresos'], 2); ?></td>
                        <td class="text-end text-danger">S/ <?php echo number_format((float)$r['total_egresos'], 2); ?></td>
                        <td class="text-end fw-bold">S/ <?php echo number_format((float)$r['saldo_neto'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if (!empty($flujo['rows'])): ?>
    <table style="width: 100%; margin-top: 15px; font-size: 11pt;">
        <tr>
            <td class="text-end" style="width: 70%;"><strong>Total Ingresos:</strong></td>
            <td class="text-end text-success fw-bold">S/ <?php echo number_format($sumIngresos, 2); ?></td>
        </tr>
        <tr>
            <td class="text-end"><strong>Total Egresos:</strong></td>
            <td class="text-end text-danger fw-bold">S/ <?php echo number_format($sumEgresos, 2); ?></td>
        </tr>
        <tr>
            <td class="text-end"><strong>Flujo Neto del Periodo:</strong></td>
            <td class="text-end fw-bold" style="border-top: 1px solid #333;">S/ <?php echo number_format($sumNeto, 2); ?></td>
        </tr>
    </table>
    <?php endif; ?>
<?php endif; ?>

<?php if ($seccionActiva === 'depositos'): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center">Fecha</th>
                <th>Cliente / Origen</th>
                <th>Referencia</th>
                <th>Cuenta Destino</th>
                <th class="text-end">Monto Depositado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($depositos['rows'])): ?>
                <tr><td colspan="5" class="text-center">No hay depósitos registrados en este periodo.</td></tr>
            <?php else: ?>
                <?php foreach ($depositos['rows'] as $r): ?>
                    <tr>
                        <td class="text-center"><?php echo date('d/m/Y', strtotime((string)$r['fecha'])); ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars((string)$r['cliente_origen']); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['referencia'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['cuenta']); ?></td>
                        <td class="text-end text-success fw-bold">S/ <?php echo number_format((float)$r['monto'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if (!empty($depositos['rows'])): ?>
    <div class="total-box">
        <strong>Total de Depósitos / Ingresos: <span class="text-success">S/ <?php echo number_format((float)($depositos['suma_total'] ?? 0), 2); ?></span></strong>
    </div>
    <?php endif; ?>
<?php endif; ?>

<div class="footer">
    <table>
        <tr>
            <td>SISADMIN ERP - Módulo de Tesorería</td>
            <td class="text-end">Página <span class="page-number"></span></td>
        </tr>
    </table>
</div>

</body>
</html>