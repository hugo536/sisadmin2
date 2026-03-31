<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Cuenta - <?php echo htmlspecialchars($f['cliente'] ?: 'General'); ?></title>
    <style>
        /* ESTILOS FLUIDOS PARA CUALQUIER TAMAÑO DE HOJA */
        @page { margin: 1cm; }
        
        /* Forzamos al navegador a imprimir los colores de fondo (gris) */
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 12px; 
            color: #000; 
            margin: 0; 
            padding: 0; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        
        /* Contenedores elásticos (Idéntico a Ventas) */
        .cabecera { width: 100%; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; box-sizing: border-box; }
        .logo-container { width: 45%; float: left; }
        .logo-img { max-height: 80px; max-width: 100%; object-fit: contain; }
        
        .datos-empresa { width: 50%; float: right; text-align: right; line-height: 1.4; }
        .datos-empresa h2 { margin: 0 0 5px 0; font-size: 15px; text-transform: uppercase; }
        
        .titulo-doc { clear: both; text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-top: 20px; margin-bottom: 20px; padding: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; letter-spacing: 1px; }

        /* Tablas (Idéntico a Ventas) */
        .info-cliente { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-cliente td { padding: 6px 10px; border: 1px solid #000; }
        .info-cliente .label { font-weight: bold; background-color: #eee !important; width: 15%; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #000; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 10px; text-align: left; }
        .detalle-tabla th { background-color: #eee !important; font-weight: bold; text-align: center; text-transform: uppercase; }
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        
        .cargo { color: #000; }
        .abono { color: #000; font-style: italic; }

        /* Tabla de resumen final (Adoptando el estilo de la plantilla) */
        .resumen-tabla { width: 40%; float: right; border-collapse: collapse; margin-top: 20px; border: 1px solid #000; }
        .resumen-tabla th, .resumen-tabla td { border: 1px solid #000; padding: 10px; }
        .resumen-tabla th { background-color: #eee !important; text-align: left; font-weight: bold; width: 50%; }
        .resumen-tabla td { text-align: right; }
        
        .clear { clear: both; }
    </style>
</head>
<body>

    <div class="cabecera">
        <div class="logo-container">
            <?php if (!empty($config['ruta_logo'])): ?>
                <img src="<?php echo base_url() . '/' . ltrim($config['ruta_logo'], '/'); ?>" class="logo-img" alt="Logo">
            <?php else: ?>
                <h2><?php echo htmlspecialchars($config['nombre_empresa'] ?? 'EMPRESA'); ?></h2>
            <?php endif; ?>
        </div>

        <div class="datos-empresa">
            <h2><?php echo htmlspecialchars($config['nombre_empresa'] ?? 'EMPRESA NO CONFIGURADA'); ?></h2>
            <strong>RUC:</strong> <?php echo htmlspecialchars($config['ruc'] ?? '-'); ?><br>
            <?php echo htmlspecialchars($config['direccion'] ?? '-'); ?><br>
            <strong>Telf:</strong> <?php echo htmlspecialchars($config['telefono'] ?? '-'); ?> | 
            <strong>Email:</strong> <?php echo htmlspecialchars($config['email'] ?? '-'); ?>
        </div>
        <div class="clear"></div>
    </div>

    <div class="titulo-doc">
        ESTADO DE CUENTA
    </div>

    <table class="info-cliente">
        <tr>
            <td class="label">CLIENTE:</td>
            <td colspan="3"><strong><?php echo htmlspecialchars($f['cliente'] ?: 'TODOS LOS CLIENTES'); ?></strong></td>
        </tr>
        <tr>
            <td class="label">FECHA DESDE:</td>
            <td><?php echo date('d/m/Y', strtotime($f['fecha_desde'])); ?></td>
            <td class="label">FECHA HASTA:</td>
            <td><?php echo date('d/m/Y', strtotime($f['fecha_hasta'])); ?></td>
        </tr>
    </table>

    <table class="detalle-tabla">
        <thead>
            <tr>
                <th style="width: 12%;">FECHA</th>
                <th style="width: 28%;">DOC. / REFERENCIA</th>
                <th style="width: 45%;">CONCEPTO</th>
                <th style="width: 15%;">MONTO</th>
            </tr>
        </thead>
        <tbody>
            <?php $rows = $detalle['rows'] ?? []; ?>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="4" class="text-center" style="padding: 20px;">No hay movimientos registrados en este periodo.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): 
                    $esCargo = ($row['tipo_transaccion'] ?? 'CARGO') === 'CARGO';
                    $fechaFmt = !empty($row['fecha_atencion']) ? date('d/m/Y', strtotime($row['fecha_atencion'])) : '';
                ?>
                    <tr>
                        <td class="text-center"><?php echo $fechaFmt; ?></td>
                        <td>
                            <?php if(empty($f['cliente'])): ?>
                                <strong><?php echo htmlspecialchars((string)($row['cliente'] ?? '')); ?></strong><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars((string)($row['documento'] ?? '')); ?>
                        </td>
                        <td>
                            <?php if ($esCargo): ?>
                                <?php echo htmlspecialchars((string)($row['producto'] ?? '')); ?><br>
                                <span style="font-size: 11px;">
                                    <?php echo number_format((float)($row['cantidad'] ?? 0), 2); ?> x S/ <?php echo number_format((float)($row['precio_unitario'] ?? 0), 2); ?>
                                </span>
                            <?php else: ?>
                                <strong>Depósito / Pago del cliente</strong>
                            <?php endif; ?>
                        </td>
                        <td class="text-right <?php echo $esCargo ? 'cargo' : 'abono'; ?>">
                            <?php if ($esCargo): ?>
                                <strong>+ S/ <?php echo number_format((float)($row['monto_transaccion'] ?? 0), 2); ?></strong>
                            <?php else: ?>
                                - S/ <?php echo number_format((float)($row['monto_transaccion'] ?? 0), 2); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php $res = $detalle['resumen'] ?? []; ?>
    <table class="resumen-tabla">
        <tr>
            <th>TOTAL CARGOS:</th>
            <td>S/ <?php echo number_format((float)($res['total_facturado'] ?? 0), 2); ?></td>
        </tr>
        <tr>
            <th>TOTAL ABONOS:</th>
            <td>S/ <?php echo number_format((float)($res['total_pagado'] ?? 0), 2); ?></td>
        </tr>
        <tr>
            <th style="font-size: 14px;">SALDO PENDIENTE:</th>
            <td style="font-size: 14px;"><strong>S/ <?php echo number_format((float)($res['total_saldo'] ?? 0), 2); ?></strong></td>
        </tr>
    </table>
    <div class="clear"></div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>