<?php
$esProveedores = (bool)($esProveedores ?? false);
$acuerdo = $acuerdoSeleccionado ?? [];
$matriz = $matriz ?? [];
$modo = $modoVista ?? 'acuerdo';

$nombreAcuerdo = $esProveedores
    ? (string)($acuerdo['proveedor_nombre'] ?? 'Sin proveedor')
    : (string)($acuerdo['cliente_nombre'] ?? 'Sin cliente');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color:#1f2937; }
        h1 { font-size: 18px; margin:0 0 4px; }
        .meta { margin-bottom: 14px; color:#4b5563; }
        table { width:100%; border-collapse: collapse; }
        th, td { border:1px solid #d1d5db; padding:6px 8px; }
        th { background:#111827; color:#fff; text-align:left; }
        .center { text-align:center; }
        .right { text-align:right; }
    </style>
</head>
<body>
    <h1><?php echo $esProveedores ? 'Acuerdo con Proveedor' : 'Acuerdo Comercial'; ?></h1>
    <div class="meta">
        <strong><?php echo $esProveedores ? 'Proveedor' : 'Cliente'; ?>:</strong> <?php echo e($nombreAcuerdo); ?><br>
        <strong>Generado:</strong> <?php echo date('d/m/Y H:i'); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th class="center" style="width:50px;">Nro</th>
                <th>Producto</th>
                <?php if ($esProveedores): ?>
                    <th>Unidad / Present.</th>
                    <th class="right" style="width:140px;">Precio Und</th>
                <?php elseif ($modo === 'volumen'): ?>
                    <th class="right" style="width:140px;">Cant. Mínima</th>
                    <th class="right" style="width:140px;">Precio Und</th>
                <?php else: ?>
                    <th class="right" style="width:140px;">Precio Und</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($matriz)): ?>
                <tr><td colspan="4">Sin registros para imprimir.</td></tr>
            <?php else: ?>
                <?php foreach ($matriz as $i => $row): ?>
                    <tr>
                        <td class="center"><?php echo (int)$i + 1; ?></td>
                        <td><?php echo e((string)($row['producto_nombre'] ?? '')); ?></td>
                        <?php if ($esProveedores): ?>
                            <td><?php echo e((string)($row['unidad_compra'] ?? 'Unidad Base')); ?></td>
                            <td class="right">S/ <?php echo number_format((float)($row['precio_recomendado'] ?? 0), 4); ?></td>
                        <?php elseif ($modo === 'volumen'): ?>
                            <td class="right"><?php echo number_format((float)($row['cantidad_minima'] ?? 0), 2); ?></td>
                            <td class="right">S/ <?php echo number_format((float)($row['precio_unitario'] ?? $row['precio_pactado'] ?? 0), 4); ?></td>
                        <?php else: ?>
                            <td class="right">S/ <?php echo number_format((float)($row['precio_pactado'] ?? 0), 4); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
