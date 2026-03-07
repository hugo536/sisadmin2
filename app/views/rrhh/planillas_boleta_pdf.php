<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta de Pago - <?php echo htmlspecialchars($boleta['nombre_completo'] ?? 'Empleado'); ?></title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td {
            vertical-align: top;
        }
        .title-box {
            text-align: right;
        }
        .title-box h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        .info-box {
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-box td {
            padding: 5px 10px;
            border: 1px solid #eee;
        }
        .info-label {
            font-weight: bold;
            background-color: #f9f9f9;
            width: 15%;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .details-table th {
            background-color: #f2f2f2;
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        .details-table td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            vertical-align: top;
        }
        .amount-col {
            text-align: right;
            width: 20%;
        }
        .totals-table {
            width: 50%;
            float: right;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 8px;
            border: 1px solid #ccc;
        }
        .total-label {
            font-weight: bold;
            background-color: #f9f9f9;
            text-align: right;
        }
        .total-amount {
            text-align: right;
            font-weight: bold;
        }
        .neto-row td {
            background-color: #2c3e50;
            color: white;
            font-size: 13px;
        }
        .signatures {
            width: 100%;
            margin-top: 80px;
        }
        .signatures table {
            width: 100%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 200px;
            margin: 0 auto;
            padding-top: 5px;
            font-weight: bold;
        }
        .clear {
            clear: both;
        }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td style="width: 50%;">
                    <strong><?php echo htmlspecialchars($empresa['nombre'] ?? 'Empresa S.A.C.'); ?></strong><br>
                    RUC: <?php echo htmlspecialchars($empresa['ruc'] ?? '20000000000'); ?><br>
                    <?php echo htmlspecialchars($empresa['direccion'] ?? 'Dirección de la empresa'); ?>
                </td>
                <td class="title-box">
                    <h1>BOLETA DE PAGO</h1>
                    <strong>Lote:</strong> <?php echo htmlspecialchars($boleta['nombre_lote'] ?? '-'); ?><br>
                    <strong>Periodo:</strong> <?php echo htmlspecialchars($boleta['fecha_inicio'] ?? ''); ?> al <?php echo htmlspecialchars($boleta['fecha_fin'] ?? ''); ?>
                </td>
            </tr>
        </table>
    </div>

    <table class="info-box">
        <tr>
            <td class="info-label">Empleado:</td>
            <td style="width: 35%;"><strong><?php echo htmlspecialchars($boleta['nombre_completo'] ?? ''); ?></strong></td>
            <td class="info-label">Documento:</td>
            <td><?php echo htmlspecialchars($boleta['numero_documento'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="info-label">Cargo:</td>
            <td><?php echo htmlspecialchars($boleta['cargo'] ?? ''); ?></td>
            <td class="info-label">Días Pagados:</td>
            <td><?php echo (int)($boleta['dias_pagados'] ?? 0); ?></td>
        </tr>
    </table>

    <table class="details-table">
        <thead>
            <tr>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 50%;">Concepto</th>
                <th class="amount-col">Ingresos (S/)</th>
                <th class="amount-col">Descuentos (S/)</th>
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
                <td><?php echo $esIngreso ? 'ING' : 'DES'; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($c['categoria'] ?? ''); ?></strong><br>
                    <span style="color: #666; font-size: 10px;"><?php echo htmlspecialchars($c['descripcion'] ?? ''); ?></span>
                </td>
                <td class="amount-col">
                    <?php echo $esIngreso ? number_format((float)$c['monto'], 2) : ''; ?>
                </td>
                <td class="amount-col">
                    <?php echo !$esIngreso ? number_format((float)$c['monto'], 2) : ''; ?>
                </td>
            </tr>
            <?php 
                endforeach; 
            else: 
            ?>
            <tr>
                <td colspan="4" style="text-align: center; color: #999;">No hay conceptos registrados.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="total-label">Total Ingresos:</td>
            <td class="total-amount">S/ <?php echo number_format((float)($boleta['total_percepciones'] ?? 0), 2); ?></td>
        </tr>
        <tr>
            <td class="total-label">Total Descuentos:</td>
            <td class="total-amount">S/ <?php echo number_format((float)($boleta['total_deducciones'] ?? 0), 2); ?></td>
        </tr>
        <tr class="neto-row">
            <td class="total-label" style="background-color: transparent; color: white;">NETO A PAGAR:</td>
            <td class="total-amount">S/ <?php echo number_format((float)($boleta['neto_a_pagar'] ?? 0), 2); ?></td>
        </tr>
    </table>
    
    <div class="clear"></div>

    <div class="signatures">
        <table>
            <tr>
                <td>
                    <div class="signature-line">Firma del Empleador</div>
                </td>
                <td>
                    <div class="signature-line">Firma del Trabajador</div>
                    <div style="font-size: 10px; margin-top: 5px; color: #666;">
                        DNI: <?php echo htmlspecialchars($boleta['numero_documento'] ?? ''); ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>