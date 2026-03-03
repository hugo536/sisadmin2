<?php
declare(strict_types=1);

// --- CONFIGURACIÓN DE CONEXIÓN ---
$host = 'localhost';
$db   = 'sisadmin2'; // <--- CAMBIA ESTO POR EL NOMBRE REAL
$user = 'root';                        // Usuario por defecto en XAMPP
$pass = '';                            // Contraseña vacía por defecto en XAMPP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

echo "<h2>🔍 Diagnóstico de Sistema Contable</h2>";

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p style='color:green;'>✅ Conexión a la base de datos exitosa.</p>";

    // 1. Verificar si la tabla existe y su estructura
    echo "<h3>1. Estructura de la tabla 'conta_cuentas'</h3>";
    $stmt = $pdo->query("DESCRIBE conta_cuentas");
    echo "<table border='1' style='border-collapse:collapse; width:100%; text-align:left;'>";
    echo "<tr style='background:#eee;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";

    // 2. Probar la consulta que causa el error SQLSTATE[HY093]
    echo "<h3>2. Prueba de consulta de Listado</h3>";
    
    // El error HY093 ocurre cuando pasas un array de parámetros 
    // pero el SQL no tiene los marcadores ":" correspondientes.
    $params = []; 
    $sql = "SELECT id, codigo, nombre FROM conta_cuentas WHERE deleted_at IS NULL ORDER BY codigo ASC";
    
    $stmtPrueba = $pdo->prepare($sql);
    
    // Esta es la línea crítica que queremos probar:
    $stmtPrueba->execute($params); 
    
    echo "<p style='color:blue;'>✅ Prueba superada: Se encontraron " . $stmtPrueba->rowCount() . " registros sin errores de parámetros.</p>";

} catch (\PDOException $e) {
    echo "<div style='background:#ffeeee; padding:10px; border:1px solid red;'>";
    echo "<p style='color:red; font-weight:bold;'>❌ ERROR DETECTADO:</p>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Código SQLSTATE:</strong> " . $e->getCode() . "</p>";
    echo "</div>";
    
    if ($e->getCode() == "1045") {
        echo "<p>💡 <em>Tip: Revisa que el usuario y contraseña en check.php coincidan con los de tu phpMyAdmin.</em></p>";
    }
}

echo "<hr><p>Fin del diagnóstico.</p>";