<?php
/**
 * 🔍 SCRIPT DE DEPURACIÓN DIRECTA: Módulo de Envases
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

echo "<div style='font-family: system-ui, sans-serif; padding: 30px; background: #0d1117; color: #c9d1d9; border-radius: 8px;'>";
echo "<h1 style='color: #58a6ff;'>🔬 CHECK.PHP - Conexión Directa</h1>";
echo "<hr style='border-color: #30363d;'>";

// CREDENCIALES POR DEFECTO DE XAMPP (Cámbialas si tienes contraseña en tu root)
$host = '127.0.0.1';
$db   = 'sisadmin2'; // Asumo este nombre por tu ruta C:\xampp\htdocs\sisadmin2
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p style='color: #3fb950;'>✅ Conexión a la BD `$db` exitosa.</p>";

    // ==========================================
    // AUTOPSIA DE TERCEROS (CLIENTES)
    // ==========================================
    echo "<h3 style='color: #ff7b72;'>▶ Autopsia a la tabla `terceros`</h3>";
    $stmt = $pdo->query("SELECT id, nombre_completo, estado, deleted_at, es_cliente FROM terceros");
    $terceros = $stmt->fetchAll();

    $total = count($terceros);
    $es_cliente = 0;
    $activos = 0;
    $no_eliminados = 0;
    $validos_para_modal = 0;

    foreach($terceros as $t) {
        if ($t['es_cliente'] == 1) $es_cliente++;
        if ($t['estado'] == 1) $activos++;
        if ($t['deleted_at'] === null) $no_eliminados++;
        
        // LA CONDICIÓN EXACTA DE TU MODELO:
        if ($t['es_cliente'] == 1 && $t['estado'] == 1 && $t['deleted_at'] === null) {
            $validos_para_modal++;
        }
    }

    echo "<ul>";
    echo "<li>Total de registros en la tabla: <b>$total</b></li>";
    echo "<li>Tienen check de `es_cliente`: <b>$es_cliente</b></li>";
    echo "<li>Están con `estado = 1`: <b>$activos</b></li>";
    echo "<li>No están eliminados (`deleted_at` es NULL): <b>$no_eliminados</b></li>";
    echo "<li style='color: #58a6ff; font-size: 1.2em;'>👉 Válidos que aparecerán en el modal: <b>$validos_para_modal</b></li>";
    echo "</ul>";

    // ==========================================
    // AUTOPSIA DE ÍTEMS (ENVASES)
    // ==========================================
    echo "<h3 style='color: #ff7b72;'>▶ Autopsia a la tabla `items`</h3>";
    $stmt = $pdo->query("SELECT id, nombre, estado, deleted_at FROM items");
    $items = $stmt->fetchAll();

    $total_items = count($items);
    $validos_items = 0;

    foreach($items as $i) {
        if ($i['estado'] == 1 && $i['deleted_at'] === null) {
            $validos_items++;
        }
    }

    echo "<ul>";
    echo "<li>Total de registros en la tabla: <b>$total_items</b></li>";
    echo "<li style='color: #58a6ff; font-size: 1.2em;'>👉 Válidos que aparecerán en el modal: <b>$validos_items</b></li>";
    echo "</ul>";

} catch (\PDOException $e) {
    echo "<p style='color: #f85149;'>❌ Error de conexión: " . $e->getMessage() . "</p>";
    echo "<p>Si tu base de datos no se llama 'sisadmin2' o tu usuario 'root' tiene clave, edita las líneas 13-16 de este script.</p>";
}

echo "</div>";