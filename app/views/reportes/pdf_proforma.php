<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proforma <?php echo htmlspecialchars($venta['codigo']); ?></title>
    <style>
        /* ESTILOS FLUIDOS PARA CUALQUIER TAMAÑO DE HOJA */
        @page { margin: 1cm; }

        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #000; margin: 0; padding: 0; }
        
        /* Contenedores elásticos */
        .cabecera { width: 100%; border-bottom: 2px solid #0d6efd; padding-bottom: 15px; margin-bottom: 20px; box-sizing: border-box; }
        .logo-container { width: 45%; float: left; }
        .logo-img { max-height: 80px; max-width: 100%; object-fit: contain; } 
        
        .datos-empresa { width: 50%; float: right; text-align: right; line-height: 1.4; }
        .datos-empresa h2 { margin: 0 0 5px 0; font-size: 16px; text-transform: uppercase; color: #0d6efd; }
        
        .titulo-doc { clear: both; text-align: center; font-size: 18px; font-weight: bold; text-transform: uppercase; margin-top: 20px; margin-bottom: 20px; padding: 8px; background-color: #f8f9fa; border: 1px solid #dee2e6; letter-spacing: 1px; color: #212529; }

        /* Tablas que ocupan el 100% del ancho disponible */
        .info-cliente { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
        .info-cliente td { padding: 6px 10px; border: 1px solid #dee2e6; }
        .info-cliente .label { font-weight: bold; background-color: #f8f9fa; width: 15%; }

        .detalle-tabla { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #dee2e6; }
        .detalle-tabla th, .detalle-tabla td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        .detalle-tabla th { background-color: #0d6efd; color: white; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 11px; }
        
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        
        /* Secciones finales: Totales y Términos */
        .footer-container { width: 100%; margin-top: 20px; display: block; overflow: hidden; }
        
        .terminos { width: 55%; float: left; font-size: 11px; color: #555; padding-right: 15px; box-sizing: border-box; }
        .terminos-titulo { font-weight: bold; color: #000; margin-bottom: 5px; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        
        .totales-container { width: 40%; float: right; }
        .totales-tabla { width: 100%; border-collapse: collapse; }
        .totales-tabla td { padding: 6px; border-bottom: 1px solid #eee; }
        .total-final { font-size: 16px; font-weight: bold; color: #0d6efd; border-top: 2px solid #0d6efd !important; }

        .observaciones { width: 100%; margin-top: 15px; border: 1px solid #dee2e6; background-color: #fcfcfc; padding: 10px; min-height: 40px; box-sizing: border-box; clear: both; }
        
        .salto-pagina { page-break-after: always; }
    </style>
</head>
<body>
    <?php 
        $totalPaginas = isset($paginas) && $paginas > 0 ? (int) $paginas : 1;

        for ($i = 1; $i <= $totalPaginas; $i++): 
    ?>

        <?php
            $tipoDocumentoCliente = strtoupper(trim((string) ($venta['cliente_doc_tipo'] ?? '')));
            $labelDocumentoCliente = $tipoDocumentoCliente !== '' ? $tipoDocumentoCliente : 'RUC/DNI';
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
            PROFORMA / COTIZACIÓN N° <?php echo htmlspecialchars($venta['codigo']); ?>
        </div>

        <table class="info-cliente">
            <tr>
                <td class="label">CLIENTE:</td>
                <td colspan="3"><strong><?php echo htmlspecialchars($venta['cliente'] ?? 'Consumidor Final'); ?></strong></td>
                <td class="label">FECHA:</td>
                <td><?php echo date('d/m/Y', strtotime($venta['created_at'] ?? $venta['fecha_emision'])); ?></td>
            </tr>
            <tr>
                <td class="label"><?php echo htmlspecialchars($labelDocumentoCliente); ?>:</td>
                <td><?php echo htmlspecialchars($venta['cliente_doc'] ?? 'S/D'); ?></td>
                <td class="label">MONEDA:</td>
                <td>Soles (S/)</td>
                <td class="label">VALIDEZ:</td>
                <td>15 Días</td>
            </tr>
            <tr>
                <td class="label">DIRECCIÓN:</td>
                <td colspan="5"><?php echo htmlspecialchars($venta['cliente_direccion'] ?? 'No registrada'); ?></td>
            </tr>
        </table>

        <table class="detalle-tabla">
            <thead>
                <tr>
                    <th style="width: 5%;">ITEM</th>
                    <th style="width: 50%;">DESCRIPCIÓN DEL PRODUCTO</th>
                    <th style="width: 15%;">CANTIDAD</th>
                    <th style="width: 15%;">PRECIO UNIT.</th>
                    <th style="width: 15%;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php $contadorItems = 1; ?>
                <?php foreach ($venta['detalle'] as $item): ?>
                    <?php 
                        // En proforma sí mostramos las cantidades solicitadas, aunque sean 0 en despacho
                        $cantidad = (float) ($item['cantidad'] ?? 0);
                        if ($cantidad <= 0) continue; 
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $contadorItems++; ?></td>
                        <td><?php echo htmlspecialchars($item['item_nombre']); ?></td>
                        <td class="text-center"><strong><?php echo number_format($cantidad, 2); ?></strong></td>
                        <td class="text-right">S/ <?php echo number_format((float)($item['precio_unitario'] ?? 0), 2); ?></td>
                        <td class="text-right">S/ <?php echo number_format((float)($item['subtotal'] ?? 0), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer-container">
            <div class="terminos">
                <div class="terminos-titulo">Términos y Condiciones Comerciales</div>
                <p style="margin: 3px 0;"><strong>1. Validez:</strong> Esta cotización es válida por 15 días calendario.</p>
                <p style="margin: 3px 0;"><strong>2. Forma de Pago:</strong> Contado / Transferencia Bancaria.</p>
                <p style="margin: 3px 0;"><strong>3. Cuentas Bancarias:</strong><br>
                   - BCP Soles: 123-456789-0-12 (CCI: 002123456789012345)<br>
                   - BBVA Soles: 987-654321-0-98
                </p>
                <p style="margin: 3px 0; margin-top: 10px; font-style: italic;">* Para confirmar su pedido, favor de enviar la constancia de pago.</p>
            </div>

            <div class="totales-container">
                <table class="totales-tabla">
                    <tr>
                        <td class="text-right"><strong>SUBTOTAL:</strong></td>
                        <td class="text-right">S/ <?php echo number_format((float)($venta['subtotal'] ?? 0), 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-right"><strong>IGV (18%):</strong></td>
                        <td class="text-right">S/ <?php echo number_format((float)($venta['igv_monto'] ?? 0), 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-right total-final">TOTAL:</td>
                        <td class="text-right total-final">S/ <?php echo number_format((float)($venta['total'] ?? 0), 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if (!empty($venta['observaciones'])): ?>
        <div class="observaciones">
            <strong>Notas Adicionales:</strong> <?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?>
        </div>
        <?php endif; ?>

        <?php if ($i < $totalPaginas): ?>
            <div class="salto-pagina"></div>
        <?php endif; ?>

    <?php endfor; ?>
</body>
</html>