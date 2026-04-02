<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Cuenta - <?php echo htmlspecialchars($f['proveedor'] ?: 'General'); ?></title>
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

        .info-cliente { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-cliente td { padding: 5px 8px; border: 1px solid #000; font-size: 11px;}
        .info-cliente .label { font-weight: bold; background-color: #eee !important; text-align: right; width: 10%; }

        .detalle-tabla { width: 100%; border-collapse: collapse; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        .detalle-tabla th { background-color: #eee !important; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 9.5px;}
        
        .fila-saldo-anterior td { background-color: #f9f9f9 !important; font-weight: bold; }

        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        
        .cargo { color: #d32f2f !important; font-weight: bold; }
        .abono { color: #2e7d32 !important; font-weight: bold; }

        /* NUEVA TABLA DE RESUMEN: 1 Fila de Títulos, 1 Fila de Valores */
        .resumen-tabla { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .resumen-tabla th, .resumen-tabla td { border: 1px solid #000; padding: 8px; font-size: 11px; text-align: center; width: 25%; }
        .resumen-tabla th { background-color: #eee !important; font-weight: bold; }
        
        .clear { clear: both; }
    </style>
</head>
<body>

    <div class="titulo-doc">ESTADO DE CUENTA</div>

    <?php $res = $detalle['resumen'] ?? []; ?>
    
    <table class="info-cliente">
        <tr>
            <td class="label">PROVEEDOR:</td>
            <td style="width: 48%;"><strong><?php echo htmlspecialchars($f['proveedor'] ?: 'TODOS LOS PROVEEDORES'); ?></strong></td>
            <td class="label">DESDE:</td>
            <td style="width: 11%; text-align: center;"><?php echo date('d/m/Y', strtotime($f['fecha_desde'])); ?></td>
            <td class="label">HASTA:</td>
            <td style="width: 11%; text-align: center;"><?php echo date('d/m/Y', strtotime($f['fecha_hasta'])); ?></td>
        </tr>
    </table>

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
                // EL TRUCO: Volteamos el array solo aquí para que el PDF se lea cronológicamente (viejo a nuevo)
                // Esto no afecta la vista de tu web principal.
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
                    <td colspan="6" class="text-center" style="padding: 15px;">No hay movimientos registrados en este periodo.</td>
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
                            <?php if(empty($f['proveedor'])): ?>
                                <strong><?php echo htmlspecialchars((string)($row['proveedor'] ?? '')); ?></strong><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars((string)($row['documento'] ?? '')); ?>
                        </td>
                        <td>
                            <?php if ($esCargo): ?>
                                <?php echo htmlspecialchars((string)($row['producto'] ?? '')); ?>
                            <?php else: ?>
                                <strong>Pago / Abono al proveedor</strong>
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

    <table class="resumen-tabla">
        <thead>
            <tr>
                <th>SALDO ANTERIOR</th>
                <th>(+) CARGOS</th>
                <th>(-) ABONOS</th>
                <th style="font-size: 12px;">SALDO FINAL</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>S/ <?php echo number_format((float)($res['saldo_anterior'] ?? 0), 2); ?></td>
                <td>S/ <?php echo number_format((float)($res['total_facturado'] ?? 0), 2); ?></td>
                <td>S/ <?php echo number_format((float)($res['total_pagado'] ?? 0), 2); ?></td>
                <td style="font-size: 12px;"><strong>S/ <?php echo number_format($saldoEnLinea, 2); ?></strong></td>
            </tr>
        </tbody>
    </table>
    <div class="clear"></div>

</body>
</html>
