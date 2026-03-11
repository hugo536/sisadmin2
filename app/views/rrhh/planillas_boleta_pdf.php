<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletas de pago</title>
    <style>
        :root {
            --brand-primary: #0b57d0;
            --ink-main: #111827;
            --ink-soft: #6b7280;
            --line-soft: #b7c9e8;
            --surface: #eef4ff;
            --surface-strong: #dbe8ff;
        }
        body {
            font-family: 'Inter', 'Segoe UI', 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: var(--ink-main);
            margin: 0;
            padding: 12px;
            background: #f3f6fb;
        }
        .boleta-ticket {
            width: 47%;
            float: left;
            margin: 1%;
            border: 1px solid var(--line-soft);
            border-radius: 10px;
            background: #fff;
            padding: 12px;
            box-sizing: border-box;
            page-break-inside: avoid;
            position: relative;
            box-shadow: 0 4px 12px rgba(16, 24, 40, 0.06);
        }

        .mini-header {
            border-bottom: 1px solid #cdd8e6;
            padding-bottom: 7px;
            margin-bottom: 7px;
        }
        .mini-header table { width: 100%; border-collapse: collapse; }
        .mini-header td { vertical-align: top; }
        .chip-periodo {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            background: var(--surface-strong);
            color: var(--brand-primary);
            border: 1px solid #b9cdf3;
            white-space: nowrap;
        }

        table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 4px 5px; border: 1px solid #dee8f5; }
        .info-label { font-weight: bold; background-color: var(--surface); width: 25%; color: #475569; }

        .details-table { margin-top: 5px; margin-bottom: 5px; }
        .details-table th {
            background: #e6efff;
            border: 1px solid #cfdced;
            color: var(--brand-primary);
            padding: 4px;
            text-align: left;
        }
        .details-table td { border: 1px solid #d7e3f2; padding: 3px 4px; }
        .amount-col { text-align: right; width: 25%; }

        .hours-table { margin-top: 8px; margin-bottom: 5px; }
        .hours-table th {
            background: #e6efff;
            border: 1px solid #c9d9ee;
            color: var(--brand-primary);
            padding: 4px;
            text-align: center;
        }
        .hours-table td { border: 1px solid #d6e1f0; padding: 3px 4px; }
        .hours-table .day-col { font-weight: bold; width: 45%; }
        .hours-table .num-col { text-align: right; width: 27.5%; }
        .hours-table .total-row td {
            font-weight: bold;
            background: #f2f8ff;
            color: var(--brand-primary);
        }

        .totals-table { width: 60%; float: right; margin-top: 5px; }
        .totals-table td { padding: 4px 5px; border: 1px solid #c6d6ea; text-align: right; }
        .neto-row td {
            background: var(--brand-primary);
            color: white;
            font-weight: bold;
            font-size: 11px;
        }

        .clear { clear: both; }

        .actions {
            position: sticky;
            top: 0;
            background: #fff;
            border-bottom: 1px solid #ddd;
            margin: -10px -10px 10px -10px;
            padding: 8px 10px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            z-index: 10;
        }
        .btn {
            border: 1px solid #1e3a8a;
            background: var(--brand-primary);
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
        }

        @media print {
            .actions { display: none !important; }
            body {
                padding: 0;
                background: #fff;
            }
            .boleta-ticket {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" class="btn" onclick="window.print()">Imprimir PDF</button>
    </div>

    <?php foreach ($boletas as $boleta): ?>
    <div class="boleta-ticket">

        <div class="mini-header">
            <table>
                <tr>
                    <td style="width: 55%; font-size: 9px;"></td>
                    <td style="text-align: right; font-size: 9px;">
                        <span class="chip-periodo">
                            <?php echo htmlspecialchars($boleta['fecha_inicio'] ?? ''); ?> al <?php echo htmlspecialchars($boleta['fecha_fin'] ?? ''); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <table class="info-box">
            <tr>
                <td class="info-label">Trabajador:</td>
                <td><strong><?php echo htmlspecialchars($boleta['nombre_completo'] ?? ''); ?></strong></td>
                <td class="info-label">Días trabajados:</td>
                <td><?php echo (int)($boleta['dias_pagados'] ?? 0); ?></td>
            </tr>
        </table>

        <table class="hours-table">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Horas normales</th>
                    <th>Horas extras</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $resumenDias = $boleta['resumen_dias'] ?? [];
                $totalNormales = 0.0;
                $totalExtras = 0.0;
                foreach ($resumenDias as $diaRow):
                    $hn = (float)($diaRow['horas_normales'] ?? 0);
                    $he = (float)($diaRow['horas_extras'] ?? 0);
                    $totalNormales += $hn;
                    $totalExtras += $he;
                ?>
                <tr>
                    <td class="day-col"><?php echo htmlspecialchars($diaRow['dia'] ?? ''); ?></td>
                    <td class="num-col"><?php echo $hn > 0 ? number_format($hn, 2) : '-'; ?></td>
                    <td class="num-col"><?php echo $he > 0 ? number_format($he, 2) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>TOTAL HORAS</td>
                    <td class="num-col"><?php echo number_format($totalNormales, 2); ?></td>
                    <td class="num-col"><?php echo number_format($totalExtras, 2); ?></td>
                </tr>
            </tbody>
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
                    <td><strong><?php echo htmlspecialchars($c['categoria'] ?? ''); ?></strong></td>
                    <td class="amount-col"><?php echo $esIngreso ? number_format((float)$c['monto'], 2) : ''; ?></td>
                    <td class="amount-col"><?php echo !$esIngreso ? number_format((float)$c['monto'], 2) : ''; ?></td>
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

    </div>
    <?php endforeach; ?>

    <div class="clear"></div>
</body>
</html>
