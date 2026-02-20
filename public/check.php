<?php
// check.php - Depurador 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2 style='font-family: sans-serif;'>Depurador de Búsqueda de Ítems</h2>";

// 1. Configuración de BD 
$host = '127.0.0.1';
$db   = 'sisadmin2';
$user = 'root';
$pass = ''; // Pon tu contraseña si no está vacía en tu entorno local

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green; font-family: sans-serif;'>✅ Conexión a BD exitosa.</p>";
} catch (PDOException $e) {
    die("<p style='color:red; font-family: sans-serif;'>❌ Error de conexión: " . $e->getMessage() . "</p>");
}

// 2. Búsqueda de prueba
$termino = 'Belén'; // Usamos un término real de tu base de datos
echo "<h3 style='font-family: sans-serif;'>Prueba 1: Buscando en la BD '$termino'</h3>";

$sql = 'SELECT id, sku, nombre, tipo_item AS tipo, requiere_lote, requiere_vencimiento
        FROM items
        WHERE estado = 1
          AND deleted_at IS NULL
          AND (sku LIKE :termino OR nombre LIKE :termino)';

$stmt = $pdo->prepare($sql);
$stmt->execute(['termino' => "%$termino%"]);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p style='font-family: sans-serif;'><strong>Resultados crudos de la BD:</strong> " . count($resultados) . " encontrados.</p>";
echo "<pre style='background:#f4f4f4; padding:10px; border-radius:5px;'>" . print_r($resultados, true) . "</pre>";

// 3. Simulando el filtro de JavaScript
echo "<hr><h3 style='font-family: sans-serif;'>Prueba 2: Simulación del Filtro de JS</h3>";
$tiposNoPermitidos = ['semielaborado', 'producto_terminado', 'producto'];

$filtrados = array_filter($resultados, function($item) use ($tiposNoPermitidos) {
    $tipoItem = strtolower(trim($item['tipo']));
    return !in_array($tipoItem, $tiposNoPermitidos);
});

echo "<p style='font-family: sans-serif;'><strong>Resultados DESPUÉS del filtro de JS:</strong> " . count($filtrados) . " que realmente se mostrarían en la lista.</p>";

if (count($filtrados) === 0 && count($resultados) > 0) {
    echo "<div style='background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; font-family: sans-serif;'>
            <strong>⚠️ ALERTA:</strong> La base de datos sí está devolviendo los productos, pero tu código JavaScript los está borrando todos porque su 'tipo_item' está en la lista de tipos no permitidos.
          </div>";
} else {
    echo "<pre style='background:#f4f4f4; padding:10px; border-radius:5px;'>" . print_r($filtrados, true) . "</pre>";
}
?>