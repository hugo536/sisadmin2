<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Despacho <?php echo htmlspecialchars($venta['codigo']); ?></title>
    <style>
        /* ESTILOS FLUIDOS PARA CUALQUIER TAMAÑO DE HOJA */
        
        /* 1. Regla general para los márgenes de impresión sin forzar el tamaño */
        @page {
            margin: 1cm; /* Un margen de 1 centímetro es seguro para casi cualquier impresora */
        }

        /* 2. Tamaños de fuente equilibrados y uso de anchos al 100% */
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #000; margin: 0; padding: 0; }
        
        /* Contenedores elásticos */
        .cabecera { width: 100%; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; box-sizing: border-box; }
        .logo-container { width: 45%; float: left; }
        .logo-img { max-height: 80px; max-width: 100%; object-fit: contain; } /* Asegura que el logo no se deforme */
        
        .datos-empresa { width: 50%; float: right; text-align: right; line-height: 1.4; }
        .datos-empresa h2 { margin: 0 0 5px 0; font-size: 15px; text-transform: uppercase; }
        
        .titulo-doc { clear: both; text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-top: 20px; margin-bottom: 20px; padding: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; letter-spacing: 1px; }

        /* Tablas que ocupan el 100% del ancho disponible */
        .info-cliente { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-cliente td { padding: 6px 10px; border: 1px solid #000; }
        .info-cliente .label { font-weight: bold; background-color: #eee; width: 15%; /* Porcentaje en lugar de pixeles fijos */ }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #000; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 10px; text-align: left; }
        .detalle-tabla th { background-color: #eee; font-weight: bold; text-align: center; text-transform: uppercase; }
        
        .text-center { text-align: center !important; }
        
        .observaciones { width: 100%; margin-top: 25px; border: 1px solid #000; padding: 10px; min-height: 60px; box-sizing: border-box; }
        .observaciones-titulo { font-weight: bold; text-transform: uppercase; margin-bottom: 5px; border-bottom: 1px dashed #000; padding-bottom: 3px; }
        
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
        <div style="clear: both;"></div>
    </div>

    <div class="titulo-doc">
        ORDEN DE DESPACHO / ENTREGA N° <?php echo htmlspecialchars($venta['codigo']); ?>
    </div>

    <table class="info-cliente">
        <tr>
            <td class="label">CLIENTE:</td>
            <td colspan="3"><strong><?php echo htmlspecialchars($venta['cliente'] ?? 'Consumidor Final'); ?></strong></td>
        </tr>
        <tr>
            <td class="label">RUC/DNI:</td>
            <td><?php echo htmlspecialchars($venta['cliente_doc'] ?? 'S/D'); ?></td>
            <td class="label">FECHA EMISIÓN:</td>
            <td><?php echo date('d/m/Y', strtotime($venta['fecha_emision'])); ?></td>
        </tr>
        <tr>
            <td class="label">DIRECCIÓN:</td>
            <td colspan="3"><?php echo htmlspecialchars($venta['cliente_direccion'] ?? 'No registrada'); ?></td>
        </tr>
    </table>

    <table class="detalle-tabla">
        <thead>
            <tr>
                <th style="width: 15%;">CÓDIGO</th>
                <th style="width: 65%;">DESCRIPCIÓN DEL PRODUCTO</th>
                <th style="width: 20%;">CANT. ENTREGADA</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($venta['detalle'] as $item): ?>
                <?php 
                    // Solo mostramos las filas que realmente tienen algo despachado
                    if ($item['cantidad_despachada'] <= 0) continue; 
                ?>
                <tr>
                    <td class="text-center"><?php echo htmlspecialchars($item['sku']); ?></td>
                    <td><?php echo htmlspecialchars($item['item_nombre']); ?></td>
                    <td class="text-center" style="font-size: 14px;">
                        <strong><?php echo number_format($item['cantidad_despachada'], 2); ?></strong>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="observaciones">
        <div class="observaciones-titulo">Observaciones de Entrega:</div>
        <?php echo nl2br(htmlspecialchars($venta['observaciones'] ?? 'Ninguna.')); ?>
    </div>

</body>
</html>