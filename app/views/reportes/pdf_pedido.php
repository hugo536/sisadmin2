<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Despacho <?php echo htmlspecialchars($venta['codigo']); ?></title>
    <style>
        /* ESTILOS FLUIDOS PARA CUALQUIER TAMAÑO DE HOJA */
        
        /* 1. Regla general para los márgenes de impresión sin forzar el tamaño */
        @page {
            margin: 1cm;
        }

        /* 2. Tamaños de fuente equilibrados y uso de anchos al 100% */
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #000; margin: 0; padding: 0; }
        
        /* Contenedores elásticos */
        .cabecera { width: 100%; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; box-sizing: border-box; }
        .logo-container { width: 45%; float: left; }
        .logo-img { max-height: 80px; max-width: 100%; object-fit: contain; } 
        
        .datos-empresa { width: 50%; float: right; text-align: right; line-height: 1.4; }
        .datos-empresa h2 { margin: 0 0 5px 0; font-size: 15px; text-transform: uppercase; }
        
        .titulo-doc { clear: both; text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-top: 20px; margin-bottom: 20px; padding: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; letter-spacing: 1px; }

        /* Tablas que ocupan el 100% del ancho disponible */
        .info-cliente { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-cliente td { padding: 6px 10px; border: 1px solid #000; }
        .info-cliente .label { font-weight: bold; background-color: #eee; width: 15%; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #000; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #000; padding: 10px; text-align: left; }
        .detalle-tabla th { background-color: #eee; font-weight: bold; text-align: center; text-transform: uppercase; }
        
        .text-center { text-align: center !important; }
        
        .observaciones { width: 100%; margin-top: 25px; border: 1px solid #000; padding: 10px; min-height: 60px; box-sizing: border-box; }
        .observaciones-titulo { font-weight: bold; text-transform: uppercase; margin-bottom: 5px; border-bottom: 1px dashed #000; padding-bottom: 3px; }
        
        /* Estilo para el salto de página */
        .salto-pagina { page-break-after: always; }
    </style>
</head>
<body>
    <?php 
        // 1. Recibimos la variable $paginas desde el controlador (por defecto 1)
        $totalPaginas = isset($paginas) && $paginas > 0 ? (int) $paginas : 1;

        // 2. Iniciamos el bucle para generar las copias solicitadas
        for ($i = 1; $i <= $totalPaginas; $i++): 
    ?>

        <?php
            $tipoDocumentoCliente = strtoupper(trim((string) ($venta['cliente_doc_tipo'] ?? '')));
            $labelDocumentoCliente = $tipoDocumentoCliente !== '' ? $tipoDocumentoCliente : 'RUC/DNI';
            $pesoTotal = 0.0;
            $hayDespachoRegistrado = false;
            foreach (($venta['detalle'] ?? []) as $lineaPeso) {
                if ((float) ($lineaPeso['cantidad_despachada'] ?? 0) > 0) {
                    $hayDespachoRegistrado = true;
                    break;
                }
            }

            foreach (($venta['detalle'] ?? []) as $lineaPeso) {
                $cantidadBase = $hayDespachoRegistrado
                    ? (float) ($lineaPeso['cantidad_despachada'] ?? 0)
                    : (float) ($lineaPeso['cantidad'] ?? 0);

                if ($cantidadBase <= 0) {
                    continue;
                }

                $pesoItem = (float) ($lineaPeso['peso_kg'] ?? 0);
                $pesoTotal += ($pesoItem * $cantidadBase);
            }
        ?>

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
                <td colspan="5"><strong><?php echo htmlspecialchars($venta['cliente'] ?? 'Consumidor Final'); ?></strong></td>
            </tr>
            <tr>
                <td class="label"><?php echo htmlspecialchars($labelDocumentoCliente); ?>:</td>
                <td><?php echo htmlspecialchars($venta['cliente_doc'] ?? 'S/D'); ?></td>
                <td class="label">FECHA EMISIÓN:</td>
                <td><?php echo date('d/m/Y', strtotime($venta['fecha_emision'])); ?></td>
                <td class="label">PESO:</td>
                <td><?php echo number_format($pesoTotal, 2); ?> kg</td>
            </tr>
            <tr>
                <td class="label">DIRECCIÓN:</td>
                <td colspan="5"><?php echo htmlspecialchars($venta['cliente_direccion'] ?? 'No registrada'); ?></td>
            </tr>
        </table>

        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 10%;">NRO</th>
                    <th style="width: 65%;">DESCRIPCIÓN DEL PRODUCTO</th>
                    <th style="width: 25%;">CANT. ENTREGADA</th>
                </tr>
            </thead>
            <tbody>
                <?php $contadorItems = 1; ?>
                <?php foreach ($venta['detalle'] as $item): ?>
                    <?php
                        $cantidadMostrar = $hayDespachoRegistrado
                            ? (float) ($item['cantidad_despachada'] ?? 0)
                            : (float) ($item['cantidad'] ?? 0);

                        if ($cantidadMostrar <= 0) {
                            continue;
                        }
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $contadorItems++; ?></td>
                        <td><?php echo htmlspecialchars($item['item_nombre']); ?></td>
                        <td class="text-center" style="font-size: 14px;">
                            <strong>
                                <?php 
                                    // LÓGICA INTELIGENTE: Si no hay decimales reales, muestra el número entero.
                                    echo floor($cantidadMostrar) == $cantidadMostrar 
                                        ? number_format($cantidadMostrar, 0) 
                                        : number_format($cantidadMostrar, 2); 
                                ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="observaciones">
            <div class="observaciones-titulo">Observaciones de Entrega:</div>
            <?php echo nl2br(htmlspecialchars($venta['observaciones'] ?? 'Ninguna.')); ?>
        </div>

        <?php 
            // 3. Aplicamos el salto de página SOLO si no es la última copia
            if ($i < $totalPaginas): 
        ?>
            <div class="salto-pagina"></div>
        <?php endif; ?>

    <?php endfor; ?>
</body>
</html>
