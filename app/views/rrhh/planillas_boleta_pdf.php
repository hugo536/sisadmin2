<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletas de pago</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --brand-primary: #0d6efd;
            --brand-success: #198754;
            --brand-danger: #dc3545;
            --ink-main: #212529;
            --ink-soft: #6c757d;
            --border-soft: #dee2e6;
            --bg-light: #f8f9fa;
            --surface: #ffffff;
            --surface-primary-subtle: #cfe2ff;
        }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 10px; /* Letra un poco más pequeña */
            color: var(--ink-main);
            margin: 0;
            padding: 10px; /* Padding reducido en pantalla */
            background: #f3f6fb;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* --- CONFIGURACIÓN ESTRICTA PARA IMPRESIÓN --- */
        @media print {
            @page {
                /* Esto elimina la URL, fecha y título que pone el navegador automáticamente */
                margin: 5mm; /* Margen físico de la hoja muy pequeño */
            }
            body {
                background: #fff !important;
                padding: 0 !important;
            }
            .actions { display: none !important; }
            .boleta-ticket {
                box-shadow: none !important;
                border: 1px solid var(--border-soft) !important;
                /* Márgenes muy ajustados para que entren varias filas */
                margin: 0 1% 1% 0 !important; 
                width: 49% !important; /* Aprovechar más el ancho */
            }
            .boleta-ticket:nth-child(even) {
                margin-right: 0 !important;
            }
        }

        /* --- DISEÑO DE LA BOLETA COMPACTA --- */
        .boleta-ticket {
            width: 48%;
            float: left;
            margin: 0 2% 2% 0;
            border: 1px solid var(--border-soft);
            border-radius: 6px;
            background: var(--surface);
            padding: 8px 10px; /* PADDING INTERNO REDUCIDO DRASTICAMENTE */
            box-sizing: border-box;
            page-break-inside: avoid; /* Intenta no cortar una boleta por la mitad */
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        /* Cabecera compacta */
        .header-boleta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-soft); /* Borde más fino */
            padding-bottom: 4px; /* Menos espacio abajo */
            margin-bottom: 6px; /* Menos espacio de separación */
        }
        .empresa-title {
            font-size: 11px; /* Título más pequeño */
            font-weight: bold;
            margin: 0;
        }
        .badge-periodo {
            background-color: var(--surface-primary-subtle);
            color: var(--brand-primary);
            padding: 2px 6px; /* Badge más delgado */
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
        }

        /* Info del trabajador compacta */
        .info-trabajador {
            background-color: var(--bg-light);
            border: 1px solid var(--border-soft);
            border-radius: 4px;
            padding: 4px 8px; /* Padding muy reducido */
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }
        .info-trabajador p {
            margin: 0;
            font-size: 8px;
            color: var(--ink-soft);
        }
        .info-trabajador strong {
            color: var(--ink-main);
            font-size: 10px;
        }

        /* Tablas ultra compactas */
        table.table-modern {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px; /* Menos margen entre tablas */
        }
        table.table-modern th {
            background-color: var(--bg-light);
            color: var(--ink-soft);
            font-weight: bold;
            font-size: 8px; /* Letra de cabecera pequeña */
            padding: 2px 4px; /* Celdas de cabecera delgadas */
            text-align: left;
            border-bottom: 1px solid var(--border-soft);
        }
        table.table-modern td {
            padding: 2px 4px; /* Celdas de datos muy delgadas */
            border-bottom: 1px solid #f0f0f0; /* Borde sutil */
            font-size: 9px;
        }
        .text-end { text-align: right !important; }
        .text-center { text-align: center !important; }
        .fw-bold { font-weight: bold; }
        .text-success { color: var(--brand-success); }
        .text-danger { color: var(--brand-danger); }
        
        .total-row td {
            background-color: var(--bg-light);
            font-weight: bold;
            border-bottom: 1px solid var(--border-soft);
            border-top: 1px solid var(--border-soft);
        }

        /* Resumen Final Compacto */
        .resumen-pago {
            float: right;
            width: 60%; /* Un poco más ancho para que entre bien en una línea */
            border: 1px solid var(--border-soft);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 2px;
        }
        .resumen-pago table {
            width: 100%;
            border-collapse: collapse;
        }
        .resumen-pago td {
            padding: 4px 6px; /* Celdas del total compactas */
            text-align: right;
        }
        .neto-row {
            background-color: var(--brand-primary);
            color: white;
        }
        .neto-row td {
            font-size: 11px; /* Letra un poco más pequeña que antes pero destacada */
            font-weight: bold;
            color: white !important;
        }

        .clear { clear: both; }

        /* Botonera superior */
        .actions {
            position: sticky;
            top: 0;
            background: #fff;
            border-bottom: 1px solid #ddd;
            margin: -10px -10px 10px -10px;
            padding: 8px 10px;
            display: flex;
            justify-content: flex-end;
            z-index: 10;
        }
        .btn {
            background: var(--brand-primary);
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body>

    <div class="actions">
        <button type="button" class="btn" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Imprimir Boletas
        </button>
    </div>

    <?php foreach ($boletas as $boleta): ?>
    <div class="boleta-ticket">

        <div class="header-boleta">
            <h2 class="empresa-title">Boleta de Pago</h2>
            <span class="badge-periodo">
                <?php echo htmlspecialchars($boleta['fecha_inicio'] ?? ''); ?> al <?php echo htmlspecialchars($boleta['fecha_fin'] ?? ''); ?>
            </span>
        </div>

        <div class="info-trabajador">
            <div>
                <p>Trabajador:</p>
                <strong><?php echo htmlspecialchars($boleta['nombre_completo'] ?? ''); ?></strong>
            </div>
            <div style="text-align: right;">
                <p>Días pagados:</p>
                <strong><?php echo (int)($boleta['dias_pagados'] ?? 0); ?> D</strong>
            </div>
        </div>

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
                    <td>S/ <?php echo number_format((float)($boleta['neto_a_pagar'] ?? 0), 2); ?></td>
                </tr>
            </table>
        </div>
        <div class="clear"></div>

    </div>
    <?php endforeach; ?>

    <div class="clear"></div>
</body>
</html>