<?php
// check.php V3 - EL ULTIMATUM
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));

echo "<body style='background:#f4f4f9; font-family:sans-serif; padding:20px; line-height:1.6;'>";
echo "<h1 style='color:#2c3e50;'>🕵️‍♂️ Super-Check de Búsqueda (V3)</h1>";
echo "<a href='check.php' style='background:#3498db; color:white; padding:8px 15px; text-decoration:none; border-radius:5px;'>🔄 Volver a Probar</a><hr>";

try {
    // --- 1. CARGAR ENTORNO ---
    require_once BASE_PATH . '/vendor/autoload.php';
    if (file_exists(BASE_PATH . '/.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
        $dotenv->safeLoad();
    }

    require_once BASE_PATH . '/app/config/Conexion.php';
    require_once BASE_PATH . '/app/core/Modelo.php';
    require_once BASE_PATH . '/app/models/VentasDocumentoModel.php';

    $model = new VentasDocumentoModel();
    $termino = "wa"; // Término que sabemos que existe: "Walter"

    echo "<h3>1. Prueba de Datos Crudos (Directo a MySQL)</h3>";
    $db = Conexion::get(); // Usamos tu clase real de conexión
    
    // Consulta para ver TODO lo que hay de Walter sin filtros
    $stmtRaw = $db->prepare("SELECT id, nombre_completo, es_cliente, estado, deleted_at FROM terceros WHERE nombre_completo LIKE ?");
    $stmtRaw->execute(["%$termino%"]);
    $rawRows = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='width:100%; border-collapse:collapse; background:white;'>";
    echo "<tr style='background:#ecf0f1;'><th>ID</th><th>Nombre</th><th>es_cliente</th><th>estado</th><th>deleted_at</th><th>¿Visible para el Sistema?</th></tr>";
    
    foreach ($rawRows as $r) {
        $visible = ($r['es_cliente'] == 1 && $r['estado'] == 1 && $r['deleted_at'] == null);
        $color = $visible ? "#d4edda" : "#f8d7da";
        $txt = $visible ? "✅ SÍ" : "❌ NO (Revisar campos)";
        echo "<tr style='background:$color;'>
                <td>{$r['id']}</td>
                <td>{$r['nombre_completo']}</td>
                <td align='center'>{$r['es_cliente']}</td>
                <td align='center'>{$r['estado']}</td>
                <td>" . ($r['deleted_at'] ?? 'NULL') . "</td>
                <td><strong>$txt</strong></td>
              </tr>";
    }
    echo "</table>";

    echo "<h3>2. Prueba de la Función buscarClientes('$termino')</h3>";
    $resultados = $model->buscarClientes($termino);

    if (empty($resultados)) {
        echo "<div style='padding:20px; background:#e74c3c; color:white; border-radius:5px;'>";
        echo "<strong>¡PROBLEMA DETECTADO!</strong> El modelo devuelve un array VACÍO.<br>";
        echo "Esto significa que aunque los datos existen, la consulta SQL del Modelo los está filtrando o tiene un error de lógica.";
        echo "</div>";
    } else {
        echo "<div style='padding:20px; background:#27ae60; color:white; border-radius:5px;'>";
        echo "<strong>✅ EL BACKEND FUNCIONA:</strong> Se encontraron " . count($resultados) . " registros.<br>";
        echo "Si en la web sigue saliendo 'No encontrado', el problema está en <strong>ventas.js</strong> o el <strong>Controlador</strong>.";
        echo "</div>";
        echo "<h4>Datos que el sistema está intentando enviar al navegador:</h4>";
        echo "<pre style='background:#2c3e50; color:#bdc3c7; padding:15px; border-radius:5px;'>" . json_encode($resultados, JSON_PRETTY_PRINT) . "</pre>";
    }

} catch (Throwable $e) {
    echo "<div style='padding:20px; background:#c0392b; color:white;'>";
    echo "<h2>🔥 ERROR DE PHP</h2>";
    echo "<b>Mensaje:</b> " . $e->getMessage() . "<br>";
    echo "<b>Línea:</b> " . $e->getLine() . "<br>";
    echo "<b>Archivo:</b> " . $e->getFile() . "</div>";
}
echo "</body>";