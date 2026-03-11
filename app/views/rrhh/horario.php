<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Impresión Masiva - Lote #<?php echo $boletas[0]['id_nomina'] ?? ''; ?></title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px; /* Reducido para que quepan más */
            color: #333;
            margin: 0;
            padding: 10px;
        }
        /* Configuración de la tira (Ticket) */
        .boleta-ticket {
            width: 47%; /* Dos columnas */
            float: left;
            margin: 1%;
            border: 1px dashed #888; /* Línea de corte */
            padding: 10px;
            box-sizing: border-box;
            page-break-inside: avoid; /* Evita que una boleta se parta a la mitad de la hoja */
            position: relative;
        }
        .boleta-ticket::before {
            content: "✂"; /* Icono de tijera */
            position: absolute;
            top: -10px;
            left: -5px;
            background: white;
            font-size: 14px;
            color: #888;
        }
        
        /* Mini Cabecera */
        .mini-header { border-bottom: 1px solid #333; padding-bottom: 5px; margin-bottom: 5px; }
        .mini-header table { width: 100%; border-collapse: collapse; }
        .mini-header td { vertical-align: top; }
        h1 { font-size: 12px; margin: 0 0 2px 0; color: #2c3e50; text-align: right; }
        
        /* Tablas internas */
        table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 3px; border: 1px solid #eee; }
        .info-label { font-weight: bold; background-color: #f9f9f9; width: 25%; }
        
        .details-table { margin-top: 5px; margin-bottom: 5px; }
        .details-table th { background-color: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
        .details-table td { border: 1px solid #ccc; padding: 3px 4px; }
        .amount-col { text-align: right; width: 25%; }
        
        .totals-table { width: 60%; float: right; margin-top: 5px; }
        .totals-table td { padding: 3px 4px; border: 1px solid #ccc; text-align: right; }
        .total-label { font-weight: bold; background-color: #f9f9f9; }
        .neto-row td { background-color: #2c3e50; color: white; font-weight: bold; font-size: 11px; }
        
        /* Firmas */
        .signatures { margin-top: 40px; text-align: center; }
        .signature-line { border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 3px; font-weight: bold; font-size: 9px; }
        
        .clear { clear: both; }
    </style>
</head>
<body>

    <?php foreach ($boletas as $boleta): ?>
    <div class="boleta-ticket">
        
        <div class="mini-header">
            <table>
                <tr>
                    <td style="width: 55%; font-size: 9px;">
                        <strong><?php echo htmlspecialchars($empresa['nombre'] ?? ''); ?></strong><br>
                        RUC: <?php echo htmlspecialchars($empresa['ruc'] ?? ''); ?>
                    </td>
                    <td style="text-align: right; font-size: 9px;">
                        <h1>TIRA DE PAGO</h1>
                        <strong>Periodo:</strong> <?php echo htmlspecialchars($boleta['fecha_inicio'] ?? ''); ?> al <?php echo htmlspecialchars($boleta['fecha_fin'] ?? ''); ?>
                    </td>
                </tr>
            </table>
        </div>

        <table class="info-box">
            <tr>
                <td class="info-label">Trabajador:</td>
                <td colspan="3"><strong><?php echo htmlspecialchars($boleta['nombre_completo'] ?? ''); ?></strong></td>
            </tr>
            <tr>
                <td class="info-label">DNI:</td>
                <td><?php echo htmlspecialchars($boleta['numero_documento'] ?? ''); ?></td>
                <td class="info-label">Días:</td>
                <td><?php echo (int)($boleta['dias_pagados'] ?? 0); ?></td>
            </tr>
        </table>

        <table class="details-table">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="amount-col">Ing. (S/)</th>
                    <th class="amount-col">Des. (S/)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $conceptos = $boleta['conceptos'] ?? [];
                if (!empty($conceptos)): 
                    foreach ($conceptos as $c): 
                        $esIngreso = ($c['tipo'] === 'PERCEPCION');
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($c['categoria'] ?? ''); ?></strong>
                    </td>
                    <td class="amount-col">
                        <?php echo $esIngreso ? number_format((float)$c['monto'], 2) : ''; ?>
                    </td>
                    <td class="amount-col">
                        <?php echo !$esIngreso ? number_format((float)$c['monto'], 2) : ''; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="3" style="text-align: center; color: #999;">Sin conceptos</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="totals-table">
            <tr class="neto-row">
                <td style="background-color: transparent; color: white;">NETO:</td>
                <td>S/ <?php echo number_format((float)($boleta['neto_a_pagar'] ?? 0), 2); ?></td>
            </tr>
        </table>
        <div class="clear"></div>

        <div class="signatures">
            <div class="signature-line">Firma del Trabajador</div>
        </div>

    </div>
    <?php endforeach; ?>
    
    <div class="clear"></div>

</body>
</html>