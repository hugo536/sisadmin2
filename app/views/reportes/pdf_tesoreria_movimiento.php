<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Movimientos de Tesorería</title>
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
        
        /* Footer fijo para la paginación */
        footer { position: fixed; bottom: -1cm; left: 0px; right: 0px; height: 1cm; text-align: right; font-size: 9px; color: #555; }
        .page-number:after { content: "Página " counter(page); }

        .titulo-doc { clear: both; text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; padding: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; letter-spacing: 1px; }

        .info-filtros { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-filtros td { padding: 5px 8px; border: 1px solid #000; font-size: 10px;}
        .info-filtros .label { font-weight: bold; background-color: #eee !important; text-align: right; width: 15%; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        .detalle-tabla th { background-color: #eee !important; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 9.5px;}
        
        /* Evita que las filas se corten a la mitad en el salto de página */
        .detalle-tabla tr { page-break-inside: avoid; }
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .text-success { color: #2e7d32 !important; font-weight: bold; }
        .text-danger { color: #d32f2f !important; font-weight: bold; }
    </style>
</head>
<body>

    <footer>
        <span class="page-number"></span>
    </footer>

    <?php 
        $tituloReporte = 'REPORTE DE MOVIMIENTOS DE TESORERÍA';

        // 1. Mapeo de Nombres de Filtros
        $cuentaNombre = 'TODAS LAS CUENTAS';
        if (!empty($filtros['id_cuenta'])) {
            foreach($resumenCuentas ?? [] as $c) {
                if ((string)$c['id'] === (string)$filtros['id_cuenta']) {
                    $cuentaNombre = $c['nombre']; 
                    break;
                }
            }
        }

        $metodoNombre = 'TODOS LOS MÉTODOS';
        if (!empty($filtros['id_metodo_pago'])) {
            foreach($metodos ?? [] as $m) {
                if ((string)$m['id'] === (string)$filtros['id_metodo_pago']) {
                    $metodoNombre = $m['nombre']; 
                    break;
                }
            }
        }

        $origenNombre = empty($filtros['origen']) ? 'TODOS LOS ORÍGENES' : strtoupper($filtros['origen']);
    ?>

    <div class="titulo-doc"><?php echo $tituloReporte; ?></div>

    <table class="info-filtros">
        <tr>
            <td class="label">PERIODO:</td>
            <td style="width: 35%;">
                <?php echo date('d/m/Y', strtotime($filtros['fecha_desde'] ?? date('Y-m-d'))); ?> AL <?php echo date('d/m/Y', strtotime($filtros['fecha_hasta'] ?? date('Y-m-d'))); ?>
            </td>
            <td class="label">CUENTA / CAJA:</td>
            <td style="width: 35%;">
                <?php echo htmlspecialchars($cuentaNombre); ?>
            </td>
        </tr>
        <tr>
            <td class="label">MÉTODO PAGO:</td>
            <td>
                <?php echo htmlspecialchars($metodoNombre); ?>
            </td>
            <td class="label">ORIGEN:</td>
            <td>
                <?php echo htmlspecialchars($origenNombre); ?>
            </td>
        </tr>
    </table>

    <table class="detalle-tabla">
        <thead>
            <tr>
                <th style="width: 10%;">FECHA</th>
                <th style="width: 10%;">TIPO</th>
                <th style="width: 25%;">TERCERO / BENEFICIARIO</th>
                <th style="width: 10%;">ORIGEN</th>
                <th style="width: 20%;">CUENTA / MÉTODO</th>
                <th style="width: 10%;">ESTADO</th>
                <th style="width: 15%;" class="text-right">MONTO</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalCobros = 0;
            $totalPagos = 0;
            
            if(empty($movimientosPdf)): ?>
                <tr><td colspan="7" class="text-center">No hay registros de movimientos para los filtros seleccionados.</td></tr>
            <?php else: ?>
                <?php foreach ($movimientosPdf as $r): 
                    $tipo = strtoupper((string)($r['tipo'] ?? ''));
                    $origen = strtoupper((string)($r['origen'] ?? ''));
                    $estado = strtoupper((string)($r['estado'] ?? ''));
                    $monto = (float)($r['monto'] ?? 0);
                    
                    if ($estado === 'CONFIRMADO') {
                        if ($tipo === 'COBRO') $totalCobros += $monto;
                        if ($tipo === 'PAGO') $totalPagos += $monto;
                    }

                    $esCobro = $tipo === 'COBRO';

                    // Formateo de Tercero y Origen
                    if ($origen === 'TRANSFERENCIA') {
                        $tercero = 'Cuentas Propias (Interno)';
                        $origenStr = 'TRF #' . ($r['id_origen'] ?? '');
                    } else {
                        $tercero = (string)($r['tercero_nombre'] ?? ('#' . ($r['id_tercero'] ?? 0)));
                        $origenStr = $origen . ' #' . ($r['id_origen'] ?? '');
                    }

                    // Formateo de Cuenta + Método
                    $cuenta = (string)($r['cuenta_nombre'] ?? '');
                    $metodo = (string)($r['metodo_pago'] ?? '');
                    $cuentaMetodo = ($metodo !== '' && $metodo !== 'N/D') ? $cuenta . ' - ' . $metodo : $cuenta;

                    $fechaFormat = !empty($r['fecha']) ? date('d/m/Y', strtotime($r['fecha'])) : '';
                ?>
                <tr>
                    <td class="text-center"><?php echo $fechaFormat; ?></td>
                    <td class="text-center <?php echo $esCobro ? 'text-success' : 'text-danger'; ?>"><?php echo $tipo; ?></td>
                    <td><?php echo htmlspecialchars($tercero); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($origenStr); ?></td>
                    <td><?php echo htmlspecialchars($cuentaMetodo); ?></td>
                    <td class="text-center"><?php echo $estado; ?></td>
                    <td class="text-right fw-bold <?php echo $esCobro ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $esCobro ? '+' : '-'; ?> S/ <?php echo number_format($monto, 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <tr>
                    <td colspan="5" style="border-right: none;"></td>
                    <td class="text-right fw-bold" style="border-left: none; background-color: #eee;">TOTAL COBROS:</td>
                    <td class="text-right fw-bold text-success" style="background-color: #eee;">S/ <?php echo number_format($totalCobros, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="5" style="border-right: none; border-top: none;"></td>
                    <td class="text-right fw-bold" style="border-left: none; background-color: #eee;">TOTAL PAGOS:</td>
                    <td class="text-right fw-bold text-danger" style="background-color: #eee;">S/ <?php echo number_format($totalPagos, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="5" style="border-right: none; border-top: none;"></td>
                    <td class="text-right fw-bold" style="border-left: none; background-color: #e3f2fd;">FLUJO NETO:</td>
                    <td class="text-right fw-bold" style="background-color: #e3f2fd; color: #0056b3;">S/ <?php echo number_format($totalCobros - $totalPagos, 2); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>