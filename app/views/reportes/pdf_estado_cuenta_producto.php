<?php
/**
 * Declaración de variables para el editor (Intelephense)
 * @var array $f
 * @var array $porProducto
 * @var array $resumen
 * @var array $config
 */
$fechaDesdeFmt = date('d/m/Y', strtotime((string)($f['fecha_desde'] ?? date('Y-m-d'))));
$fechaHastaFmt = date('d/m/Y', strtotime((string)($f['fecha_hasta'] ?? date('Y-m-d'))));
$clienteNombre = !empty($f['cliente']) ? $f['cliente'] : 'TODOS LOS CLIENTES';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de Productos - <?php echo htmlspecialchars($clienteNombre); ?></title>
    <style>
        @page { margin: 1.5cm; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10px; 
            color: #333; 
            margin: 0; 
            padding: 0; 
        }
        
        .cabecera-pdf { width: 100%; border-bottom: 2px solid #0B5ED7; margin-bottom: 15px; padding-bottom: 10px; }
        .cabecera-pdf td { vertical-align: middle; }
        .titulo-empresa { font-size: 16px; font-weight: bold; color: #0B5ED7; text-transform: uppercase; }
        .subtitulo { font-size: 11px; color: #555; font-style: italic; margin-top: 4px; }
        .logo-container { text-align: right; }
        .logo-container img { max-height: 50px; }

        .info-cliente { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-cliente td { padding: 4px 0; font-size: 11px; }
        .info-cliente .label { font-weight: bold; color: #000; width: 15%; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        
        .detalle-tabla th { 
            background-color: #1A1A1A !important; 
            color: #FFFFFF !important; 
            font-weight: bold; 
            text-align: center; 
            font-size: 10px;
        }
        
        .detalle-tabla tbody tr:nth-child(even) { background-color: #F7F7F7 !important; }
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .cargo { color: #d32f2f !important; font-weight: bold; }
        
        tfoot td { background-color: #EAEAEA !important; border-top: 2px solid #000 !important; font-size: 11px;}
        
        .clear { clear: both; }
    </style>
</head>
<body>

    <?php 
        $nombreEmpresa = $config['nombre_empresa'] ?? 'NUESTRA EMPRESA';
        $rutaLogo = $config['ruta_logo'] ?? '';
        
        $imgTag = '';
        if (!empty($rutaLogo)) {
            $rutaLimpia = ltrim($rutaLogo, '/\\');
            $logoPath = (strpos($rutaLimpia, 'public/') === 0) ? BASE_PATH . '/' . $rutaLimpia : BASE_PATH . '/public/' . $rutaLimpia;
            if (file_exists($logoPath)) {
                $imgData = base64_encode(file_get_contents($logoPath));
                $mime = mime_content_type($logoPath);
                $imgTag = '<img src="data:' . $mime . ';base64,' . $imgData . '" alt="Logo">';
            }
        }
    ?>

    <table class="cabecera-pdf">
        <tr>
            <td style="width: 70%;">
                <div class="titulo-empresa"><?php echo htmlspecialchars($nombreEmpresa); ?> - RESUMEN DE PRODUCTOS</div>
                <div class="subtitulo">Periodo del reporte: <?php echo $fechaDesdeFmt; ?> al <?php echo $fechaHastaFmt; ?></div>
            </td>
            <td style="width: 30%;" class="logo-container">
                <?php echo $imgTag; ?>
            </td>
        </tr>
    </table>
    
    <table class="info-cliente">
        <tr>
            <td class="label">CLIENTE FILTRADO:</td>
            <td><strong><?php echo htmlspecialchars($clienteNombre); ?></strong></td>
        </tr>
    </table>

    <table class="detalle-tabla">
        <thead>
            <tr>
                <th style="width: 40%;">PRODUCTO</th>
                <th style="width: 20%;">CANTIDAD VENDIDA</th>
                <th style="width: 20%;">TOTAL FACTURADO</th>
                <th style="width: 20%;">DEUDA PENDIENTE</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($porProducto)): ?>
                <tr>
                    <td colspan="4" class="text-center" style="padding: 25px; color: #777;">No hay productos registrados en este periodo.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($porProducto as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars((string)($row['producto'] ?? '')); ?></strong></td>
                        <td class="text-center"><?php echo number_format((float)($row['total_cantidad'] ?? 0), 2); ?></td>
                        <td class="text-right">S/ <?php echo number_format((float)($row['total_facturado'] ?? 0), 2); ?></td>
                        <td class="text-right cargo">S/ <?php echo number_format((float)($row['total_saldo'] ?? 0), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($porProducto)): ?>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right"><strong>TOTALES GENERALES:</strong></td>
                <td class="text-right"><strong>S/ <?php echo number_format((float)($resumen['total_facturado'] ?? 0), 2); ?></strong></td>
                <td class="text-right cargo"><strong>S/ <?php echo number_format((float)($resumen['total_saldo'] ?? 0), 2); ?></strong></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    
    <div class="clear"></div>

</body>
</html>