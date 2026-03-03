<?php
/**
 * SCRIPT DE DEPURACIÓN AUTÓNOMO
 * No requiere archivos externos para evitar errores de ruta.
 */
declare(strict_types=1);

// --- CONFIGURA ESTOS DATOS ---
$db_host = 'localhost';
$db_name = 'sisadmin2'; // Confirmado por tu archivo .sql
$db_user = 'root';
$db_pass = ''; 

echo "<h2>🔍 Probando Actualización Directa en BD</h2>";

try {
    // 1. Conexión Directa
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p style='color:green;'>✅ Conexión establecida.</p>";

    // 2. Buscar la primera cuenta disponible
    $stmt = $pdo->query("SELECT id, codigo, nombre, estado FROM conta_cuentas LIMIT 1");
    $cuenta = $stmt->fetch();

    if (!$cuenta) {
        die("<p style='color:red;'>❌ La tabla 'conta_cuentas' está vacía.</p>");
    }

    $id = (int)$cuenta['id'];
    $estadoOriginal = (int)$cuenta['estado'];
    $nuevoEstado = ($estadoOriginal === 1) ? 0 : 1;

    echo "<p>Cuenta a probar: <b>{$cuenta['nombre']}</b> (ID: $id)</p>";
    echo "<p>Estado actual: <b>$estadoOriginal</b>. Intentando cambiar a: <b>$nuevoEstado</b>...</p>";

    // 3. Ejecutar el UPDATE directamente
    $update = $pdo->prepare("UPDATE conta_cuentas SET estado = :nuevo WHERE id = :id");
    $update->execute(['nuevo' => $nuevoEstado, 'id' => $id]);

    // 4. Verificar si el cambio se guardó
    $stmtVerificar = $pdo->prepare("SELECT estado FROM conta_cuentas WHERE id = :id");
    $stmtVerificar->execute(['id' => $id]);
    $estadoFinal = (int)$stmtVerificar->fetchColumn();

    echo "<hr>";
    if ($estadoFinal === $nuevoEstado) {
        echo "<h1 style='color:green;'>✅ ¡LA BASE DE DATOS FUNCIONA!</h1>";
        echo "<p>El cambio se guardó correctamente en MySQL.</p>";
        echo "<p><b>Conclusión:</b> Si el switch no funciona en el sistema, el problema es que el <b>Formulario HTML</b> o el <b>Controlador PHP</b> no están procesando la petición.</p>";
    } else {
        echo "<h1 style='color:red;'>❌ LA BASE DE DATOS NO CAMBIÓ</h1>";
        echo "<p>El UPDATE se ejecutó pero el valor no cambió. Revisa si tienes 'Triggers' o restricciones en la tabla.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}