<?php
// check.php V4 - DEBUG DE PRODUCTOS TERMINADOS
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));

echo "<body style='background:#f4f4f9; font-family:sans-serif; padding:20px; line-height:1.6;'>";
echo "<h1 style='color:#2c3e50;'>📦 Diagnóstico de Productos (V4)</h1>";
echo "<a href='check.php' style='background:#3498db; color:white; padding:8px 15px; text-decoration:none; border-radius:5px;'>🔄 Recargar Diagnóstico</a><hr>";

try {
    // 1. CARGAR ENTORNO
    require_once BASE_PATH . '/vendor/autoload.php';
    if (file_exists(BASE_PATH . '/.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
        $dotenv->safeLoad();
    }

    require_once BASE_PATH . '/app/config/Conexion.php';
    require_once BASE_PATH . '/app/core/Modelo.php';
    require_once BASE_PATH . '/app/models/VentasDocumentoModel.php';

    $db = Conexion::get();
    $model = new VentasDocumentoModel();
    $termino = "ag"; // Término de búsqueda (para 'agua')

    // --- PRUEBA A: ¿QUÉ TIPOS DE ITEMS EXISTEN REALMENTE? ---
    echo "<h3>1. Análisis de Categorías (tipo_item) en la Base de Datos</h3>";
    echo "<p>Esto sirve para detectar si hay errores de escritura (ej: 'Producto Terminado' con T mayúscula).</p>";
    
    $stmtTypes = $db->query("SELECT tipo_item, COUNT(*) as total FROM items GROUP BY tipo_item");
    $tipos = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

    echo "<ul>";
    foreach ($tipos as $t) {
        $estilo = ($t['tipo_item'] === 'Producto terminado') ? "color:green; font-weight:bold;" : "color:gray;";
        echo "<li style='$estilo'>Tipo: '{$t['tipo_item']}' — Cantidad: {$t['total']}</li>";
    }
    echo "</ul>";

    // --- PRUEBA B: BUSCAR COINCIDENCIAS CRUDAS ---
    echo "<h3>2. Búsqueda Cruda de '$termino' (Sin filtros de tipo)</h3>";
    $stmtRaw = $db->prepare("SELECT id, sku, nombre, tipo_item, estado, deleted_at FROM items WHERE nombre LIKE ? OR sku LIKE ?");
    $termFull = "%$termino%";
    $stmtRaw->execute([$termFull, $termFull]);
    $rawItems = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rawItems)) {
        echo "<div style='padding:15px; background:#f8d7da; border:1px solid #f5c6cb;'>❌ No existe NADA que coincida con '$termino' en la tabla items.</div>";
    } else {
        echo "<table border='1' style='width:100%; border-collapse:collapse; background:white;'>";
        echo "<tr style='background:#ecf0f1;'><th>ID</th><th>SKU</th><th>Nombre</th><th>Tipo Item</th><th>Estado</th><th>Deleted</th><th>¿Pasaría el filtro?</th></tr>";
        
        foreach ($rawItems as $i) {
            // Lógica de validación
            $esTipoOk = ($i['tipo_item'] === 'Producto terminado');
            $esEstadoOk = ($i['estado'] == 1);
            $esNotDeleted = ($i['deleted_at'] === null);
            
            $visible = ($esTipoOk && $esEstadoOk && $esNotDeleted);
            $color = $visible ? "#d4edda" : "#f8d7da";
            
            $motivo = [];
            if (!$esTipoOk) $motivo[] = "Tipo no es 'Producto terminado'";
            if (!$esEstadoOk) $motivo[] = "Estado no es 1";
            if (!$esNotDeleted) $motivo[] = "Está borrado (deleted_at)";
            
            echo "<tr style='background:$color;'>
                    <td>{$i['id']}</td>
                    <td>{$i['sku']}</td>
                    <td>{$i['nombre']}</td>
                    <td>'{$i['tipo_item']}'</td>
                    <td align='center'>{$i['estado']}</td>
                    <td>" . ($i['deleted_at'] ?? 'NULL') . "</td>
                    <td>" . ($visible ? "✅ LISTO" : "❌ BLOQUEADO: " . implode(', ', $motivo)) . "</td>
                  </tr>";
        }
        echo "</table>";
    }

    // --- PRUEBA C: EJECUTAR EL MODELO REAL ---
    echo "<h3>3. Resultado Real del Modelo (buscarItems)</h3>";
    $resultadosModelo = $model->buscarItems($termino);

    if (empty($resultadosModelo)) {
        echo "<div style='padding:20px; background:#e74c3c; color:white; border-radius:5px;'>
                <strong>PROBLEMA DETECTADO:</strong> El Modelo no devuelve nada.<br>
                Posibles causas:
                <ul>
                    <li>El texto 'Producto terminado' en la consulta SQL no coincide exactamente con el de la tabla (revisa mayúsculas/acentos).</li>
                    <li>Hay un error en la sintaxis SQL de la función buscarItems.</li>
                </ul>
              </div>";
    } else {
        echo "<div style='padding:20px; background:#27ae60; color:white; border-radius:5px;'>
                ✅ <strong>EL MODELO FUNCIONA:</strong> Se enviarán " . count($resultadosModelo) . " productos al buscador.
              </div>";
        echo "<pre style='background:#2c3e50; color:#bdc3c7; padding:15px; margin-top:10px; border-radius:5px;'>" . json_encode($resultadosModelo, JSON_PRETTY_PRINT) . "</pre>";
    }

} catch (Throwable $e) {
    echo "<div style='padding:20px; background:#c0392b; color:white;'>";
    echo "<h2>🔥 ERROR DE PHP</h2>";
    echo "<b>Mensaje:</b> " . $e->getMessage() . "<br>";
    echo "<b>Archivo:</b> " . $e->getFile() . " (Línea " . $e->getLine() . ")</div>";
}
echo "</body>";