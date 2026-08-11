<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletas de Pago</title>
    <style>
        /* --- CONFIGURACIÓN OPTIMIZADA PARA DOMPDF --- */
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #212529;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        /* --- DISEÑO DE LA BOLETA --- */
        .boleta-ticket {
            width: 46%; /* Ajustado para dar margen */
            float: left;
            margin: 0 2% 20px 0;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #ffffff;
            padding: 10px;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        /* Cabecera compacta */
        .header-boleta {
            width: 100%;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 6px;
            padding-bottom: 4px;
        }
        .header-boleta td {
            vertical-align: middle;
        }
        .empresa-title {
            font-size: 12px;
            font-weight: bold;
            margin: 0;
            color: #0d6efd;
        }
        .badge-periodo {
            background-color: #cfe2ff;
            color: #0d6efd;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        /* Info del trabajador compacta */
        .info-trabajador {
            width: 100%;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        .info-trabajador td {
            padding: 6px 8px;
        }
        .info-trabajador p {
            margin: 0 0 2px 0;
            font-size: 8px;
            color: #6c757d;
        }
        .info-trabajador strong {
            color: #212529;
            font-size: 10px;
        }

        /* Tablas ultra compactas */
        table.table-modern {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        table.table-modern th {
            background-color: #f8f9fa;
            color: #6c757d;
            font-weight: bold;
            font-size: 8px;
            padding: 4px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        table.table-modern td {
            padding: 4px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 9px;
        }
        
        .text-end { text-align: right !important; }
        .text-center { text-align: center !important; }
        .fw-bold { font-weight: bold; }
        .text-success { color: #198754; }
        .text-danger { color: #dc3545; }
        
        .total-row td {
            background-color: #f8f9fa;
            font-weight: bold;
            border-bottom: 1px solid #dee2e6;
            border-top: 1px solid #dee2e6;
        }

        /* Resumen Final Compacto */
        .resumen-pago {
            width: 60%;
            float: right;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
        }
        .resumen-pago table {
            width: 100%;
            border-collapse: collapse;
        }
        .resumen-pago td {
            padding: 5px 8px;
            text-align: right;
        }
        .neto-row {
            background-color: #0d6efd;
        }
        .neto-row td {
            font-size: 11px;
            font-weight: bold;
            color: #ffffff !important;
        }

        .clear { clear: both; }
    </style>
</head>
<body>

    <?php $contador = 0; foreach ($boletas as $boleta): ?>
    <!-- Se remueve el margen derecho en las boletas pares para alinear en 2 columnas -->
    <div class="boleta-ticket" <?php echo ($contador % 2 != 0) ? 'style="margin-right: 0;"' : ''; ?>>

        <!-- CABECERA ADAPTADA A TABLA (COMPLETAMENTE LIMPIA SIN IMÁGENES) -->
        <table class="header-boleta">
            <tr>
                <td align="left">
                    <h2 class="empresa-title">Boleta de Pago</h2>
                </td>
                <td align="right">
                    <span class="badge-periodo">
                        <?php echo htmlspecialchars($boleta['fecha_inicio'] ?? ''); ?> al <?php echo htmlspecialchars($boleta['fecha_fin'] ?? ''); ?>
                    </span>
                </td>
            </tr>
        </table>

        <!-- INFO TRABAJADOR ADAPTADA A TABLA -->
        <table class="info-trabajador">
            <tr>
                <td align="left">
                    <p>Trabajador:</p>
                    <strong><?php echo htmlspecialchars($boleta['nombre_completo'] ?? ''); ?></strong>
                </td>
                <td align="right">
                    <p>Días pagados:</p>
                    <strong><?php echo (int)($boleta['dias_pagados'] ?? 0); ?> D</strong>
                </td>
            </tr>
        </table>

        <table class="table-modern">
            <thead>
                <tr>
                    <th>Día</th>
                    <th class="text-end">H. Norm.</th>
                    <th class="text-end">H. Ext.</th>
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
                    <td class="fw-bold"><?php echo htmlspecialchars($diaRow['dia'] ?? ''); ?></td>
                    <td class="text-end"><?php echo $hn > 0 ? number_format($hn, 2) : '-'; ?></td>
                    <td class="text-end text-success"><?php echo $he > 0 ? number_format($he, 2) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="text-end"><?php echo number_format($totalNormales, 2); ?></td>
                    <td class="text-end text-success"><?php echo number_format($totalExtras, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <table class="table-modern">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="text-end">Ing. (S/)</th>
                    <th class="text-end">Desc. (S/)</th>
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
                    <td><?php echo htmlspecialchars($c['categoria'] ?? ''); ?></td>
                    <td class="text-end fw-bold text-success">
                        <?php echo $esIngreso ? number_format((float)$c['monto'], 2) : ''; ?>
                    </td>
                    <td class="text-end fw-bold text-danger">
                        <?php echo !$esIngreso ? number_format((float)$c['monto'], 2) : ''; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="3" class="text-center" style="color: #999;">Sin conceptos</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="resumen-pago">
            <table>
                <tr class="neto-row">
                    <td style="text-align: left;">NETO:</td>
                    <td style="text-align: right;">S/ <?php echo number_format((float)($boleta['neto_a_pagar'] ?? 0), 2); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- FIX PARA FLOTANTES EN DOMPDF -->
        <div class="clear"></div>

    </div>
    
    <?php 
        $contador++;
        // Salto de página cada 4 boletas (opcional, ajusta según la altura de tus tickets)
        if ($contador % 4 == 0) {
            echo '<div style="page-break-before: always; clear: both;"></div>';
        }
    endforeach; 
    ?>

</body>
</html>