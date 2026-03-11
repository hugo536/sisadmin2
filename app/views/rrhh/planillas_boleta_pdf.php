<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Impresión Masiva - Lote #<?php echo $boletas[0]['id_nomina'] ?? ''; ?></title>
    <style>
        :root {
            --brand-primary: #1d4ed8;
            --brand-secondary: #0f766e;
            --ink-main: #1f2937;
            --ink-soft: #6b7280;
            --line-soft: #dbe3ef;
            --surface: #f8fbff;
            --surface-strong: #eef5ff;
        }
        body {
            font-family: 'Inter', 'Segoe UI', 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: var(--ink-main);
            margin: 0;
            padding: 12px;
            background:
                radial-gradient(circle at 5% 5%, #f0f7ff 0%, transparent 38%),
                radial-gradient(circle at 95% 0%, #f3fffb 0%, transparent 32%),
                #f3f6fb;
        }
        .boleta-ticket {
            width: 47%;
            float: left;
            margin: 1%;
            border: 1px dashed #8da5c5;
            border-radius: 10px;
            background: #fff;
            padding: 12px;
            box-sizing: border-box;
            page-break-inside: avoid;
            position: relative;
            box-shadow: 0 8px 24px rgba(16, 24, 40, 0.08);
        }
        .boleta-ticket::before {
            content: "✂";
            position: absolute;
            top: -10px;
            left: -5px;
            background: white;
            font-size: 14px;
            color: #64748b;
        }

        .mini-header {
            border-bottom: 1px solid #cdd8e6;
            padding-bottom: 7px;
            margin-bottom: 7px;
        }
        .mini-header table { width: 100%; border-collapse: collapse; }
        .mini-header td { vertical-align: top; }
        h1 {
            font-size: 12px;
            margin: 0 0 2px 0;
            color: var(--brand-primary);
            text-align: right;
            letter-spacing: 0.4px;
        }

        .chip-periodo {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 8.5px;
            background: var(--surface-strong);
            color: #1e3a8a;
            border: 1px solid #c9dafc;
        }

        table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 4px 5px; border: 1px solid #dee8f5; }
        .info-label { font-weight: bold; background-color: var(--surface); width: 25%; color: #475569; }

        .details-table { margin-top: 5px; margin-bottom: 5px; }
        .details-table th {
            background: linear-gradient(180deg, #f6fbff 0%, #ebf3ff 100%);
            border: 1px solid #cfdced;
            color: #1e3a8a;
            padding: 4px;
            text-align: left;
        }
        .details-table td { border: 1px solid #d7e3f2; padding: 3px 4px; }
        .amount-col { text-align: right; width: 25%; }

        .hours-table { margin-top: 8px; margin-bottom: 5px; }
        .hours-table th {
            background: linear-gradient(180deg, #eff6ff 0%, #e1ecff 100%);
            border: 1px solid #c9d9ee;
            color: #0f2f6f;
            padding: 4px;
            text-align: center;
        }
        .hours-table td { border: 1px solid #d6e1f0; padding: 3px 4px; }
        .hours-table .day-col { font-weight: bold; width: 45%; }
        .hours-table .num-col { text-align: right; width: 27.5%; }
        .hours-table .total-row td {
            font-weight: bold;
            background: #f2f8ff;
            color: #0f3b8f;
        }

        .totals-table { width: 60%; float: right; margin-top: 5px; }
        .totals-table td { padding: 4px 5px; border: 1px solid #c6d6ea; text-align: right; }
        .neto-row td {
            background: linear-gradient(90deg, #1e3a8a 0%, #0f766e 100%);
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
            background: linear-gradient(90deg, #1d4ed8 0%, #0f766e 100%);
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
                        <h1>TIRA DE PAGO</h1>
                        <span class="chip-periodo">
                            <strong>Periodo:</strong> <?php echo htmlspecialchars($boleta['fecha_inicio'] ?? ''); ?> al <?php echo htmlspecialchars($boleta['fecha_fin'] ?? ''); ?>
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
