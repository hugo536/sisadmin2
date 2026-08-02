<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Cuenta - <?php echo htmlspecialchars($f['cliente'] ?: 'General'); ?></title>
    <style>
        @page { margin: 1.5cm; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10px; 
            color: #333; 
            margin: 0; 
            padding: 0; 
        }
        
        /* --- CABECERA Y LOGO --- */
        .cabecera-pdf { width: 100%; border-bottom: 2px solid #0B5ED7; margin-bottom: 15px; padding-bottom: 10px; }
        .cabecera-pdf td { vertical-align: middle; }
        .titulo-empresa { font-size: 16px; font-weight: bold; color: #0B5ED7; text-transform: uppercase; }
        .subtitulo { font-size: 11px; color: #555; font-style: italic; margin-top: 4px; }
        .logo-container { text-align: right; }
        .logo-container img { max-height: 50px; }

        /* --- DATOS DEL REPORTE --- */
        .info-cliente { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-cliente td { padding: 4px 0; font-size: 11px; }
        .info-cliente .label { font-weight: bold; color: #000; width: 15%; }

        /* --- TABLA PRINCIPAL (DISEÑO EXCEL) --- */
        .detalle-tabla { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        
        /* Encabezados negros con texto blanco */
        .detalle-tabla th { 
            background-color: #1A1A1A !important; 
            color: #FFFFFF !important; 
            font-weight: bold; 
            text-align: center; 
            font-size: 10px;
        }
        
        /* Filas cebra (Gris suave) */
        .detalle-tabla tbody tr:nth-child(even) { background-color: #F7F7F7 !important; }
        
        .fila-saldo-anterior td { background-color: #EAEAEA !important; font-weight: bold; color: #000; }

        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        
        .cargo { color: #d32f2f !important; font-weight: bold; }
        .abono { color: #2e7d32 !important; font-weight: bold; }

        /* --- TABLA DE RESUMEN FINAL --- */
        .resumen-tabla { width: 100%; border-collapse: collapse; margin-top: 15px; border: 2px solid #ddd; }
        .resumen-tabla th, .resumen-tabla td { border: 1px solid #ddd; padding: 10px; font-size: 11px; text-align: center; width: 25%; }
        .resumen-tabla th { background-color: #F7F7F7 !important; font-weight: bold; color: #555; }
        .resumen-final-celda { background-color: #1A1A1A !important; color: #FFF !important; font-size: 12px; font-weight: bold; }

        .clear { clear: both; }
    </style>
</head>
<body>

    <?php 
        $res = $detalle['resumen'] ?? []; 
        $nombreEmpresa = $config['nombre_empresa'] ?? 'NUESTRA EMPRESA';
        $rutaLogo = $config['ruta_logo'] ?? '';
        
        // Procesamiento del logo para el PDF
        $imgTag = '';
        if (!empty($rutaLogo)) {
            $rutaLimpia = ltrim($rutaLogo, '/\\');
            $logoPath = (strpos($rutaLimpia, 'public/') === 0) ? BASE_PATH . '/' . $rutaLimpia : BASE_PATH . '/public/' . $rutaLimpia;
            if (file_exists($logoPath)) {
                // Convertir imagen a Base64 para máxima compatibilidad con DomPDF
                $imgData = base64_encode(file_get_contents($logoPath));
                $mime = mime_content_type($logoPath);
                $imgTag = '<img src="data:' . $mime . ';base64,' . $imgData . '" alt="Logo">';
            }
        }
    ?>

    <!-- CABECERA DEL REPORTE -->
    <table class="cabecera-pdf">
        <tr>
            <td style="width: 70%;">
                <div class="titulo-empresa"><?php echo htmlspecialchars($nombreEmpresa); ?> - ESTADO DE CUENTA</div>
                <div class="subtitulo">Periodo del reporte: <?php echo date('d/m/Y', strtotime($f['fecha_desde'])); ?> al <?php echo date('d/m/Y', strtotime($f['fecha_hasta'])); ?></div>
            </td>
            <td style="width: 30%;" class="logo-container">
                <?php echo $imgTag; ?>
            </td>
        </tr>
    </table>
    
    <!-- DATOS DEL CLIENTE -->
    <table class="info-cliente">
        <tr>
            <td class="label">CLIENTE FILTRADO:</td>
            <td><strong><?php echo htmlspecialchars($f['cliente'] ?: 'TODOS LOS CLIENTES'); ?></strong></td>
        </tr>
    </table>

    <!-- TABLA DE MOVIMIENTOS -->
    <table class="detalle-tabla">
        <thead>
            <tr>
                <th style="width: 10%;">FECHA</th>
                <th style="width: 15%;">DOC.</th>
                <th style="width: 40%;">PRODUCTO / CONCEPTO</th>
                <th style="width: 10%;">CANT.</th>
                <th style="width: 10%;">PRECIO</th>
                <th style="width: 15%;">MONTO (+ / -)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $rows = array_reverse($detalle['rows'] ?? []); 
                $saldoEnLinea = (float)($res['saldo_anterior'] ?? 0); 
            ?>
            
            <tr class="fila-saldo-anterior">
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td>SALDO ANTERIOR AL <?php echo date('d/m/Y', strtotime($f['fecha_desde'])); ?></td>
                <td class="text-center">-</td>
                <td class="text-right">-</td>
                <td class="text-right">S/ <?php echo number_format($saldoEnLinea, 2); ?></td>
            </tr>

            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 25px; color: #777;">No hay movimientos registrados en este periodo.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): 
                    $esCargo = ($row['tipo_transaccion'] ?? 'CARGO') === 'CARGO';
                    $fechaFmt = !empty($row['fecha_atencion']) ? date('d/m/Y', strtotime($row['fecha_atencion'])) : '';
                    $montoFila = (float)($row['monto_transaccion'] ?? 0);
                    
                    if ($esCargo) {
                        $saldoEnLinea += $montoFila; 
                    } else {
                        $saldoEnLinea -= $montoFila; 
                    }
                ?>
                    <tr>
                        <td class="text-center"><?php echo $fechaFmt; ?></td>
                        <td class="text-center">
                            <?php if(empty($f['cliente'])): ?>
                                <strong><?php echo htmlspecialchars((string)($row['cliente'] ?? '')); ?></strong><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars((string)($row['documento'] ?? '')); ?>
                        </td>
                        <td>
                            <?php if ($esCargo): ?>
                                <?php echo htmlspecialchars((string)($row['producto'] ?? '')); ?>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars((string)($row['producto'] ?? '')); ?></strong>
                            <?php endif; ?>
                        </td>
                        
                        <td class="text-center">
                            <?php echo $esCargo ? number_format((float)($row['cantidad'] ?? 0), 2) : '-'; ?>
                        </td>
                        <td class="text-right">
                            <?php echo $esCargo ? number_format((float)($row['precio_unitario'] ?? 0), 2) : '-'; ?>
                        </td>
                        
                        <td class="text-right <?php echo $esCargo ? 'cargo' : 'abono'; ?>">
                            <?php if ($esCargo): ?>
                                + S/ <?php echo number_format($montoFila, 2); ?>
                            <?php else: ?>
                                - S/ <?php echo number_format($montoFila, 2); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- RESUMEN FINAL -->
    <table class="resumen-tabla">
        <thead>
            <tr>
                <th>SALDO ANTERIOR</th>
                <th>(+) DEUDA / CARGOS</th>
                <th>(-) PAGOS / ABONOS</th>
                <th style="background-color: #1A1A1A !important; color: #FFF !important;">SALDO FINAL</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>S/ <?php echo number_format((float)($res['saldo_anterior'] ?? 0), 2); ?></td>
                <td>S/ <?php echo number_format((float)($res['total_facturado'] ?? 0), 2); ?></td>
                <td>S/ <?php echo number_format((float)($res['total_pagado'] ?? 0), 2); ?></td>
                <td class="resumen-final-celda">S/ <?php echo number_format($saldoEnLinea, 2); ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="clear"></div>

</body>
</html>