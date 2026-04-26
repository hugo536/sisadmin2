<?php
/**
 * 🔍 SCRIPT DE DEPURACIÓN DIRECTA V2: Buscador de Clientes
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

echo "<div style='font-family: system-ui, sans-serif; padding: 30px; background: #0d1117; color: #c9d1d9; border-radius: 8px;'>";
echo "<h1 style='color: #58a6ff;'>🕵️‍♂️ CHECK.PHP - Depurando Buscador de Clientes</h1>";
echo "<hr style='border-color: #30363d;'>";

// CREDENCIALES
$host = '127.0.0.1';
$db   = 'sisadmin2';
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

    // Vamos a probar con la palabra "suy"
    $termino_busqueda = 'suy'; 
    
    echo "<h3 style='color: #ff7b72;'>▶ Simulando lo que busca TomSelect: '$termino_busqueda'</h3>";

    // CORREGIDO: numero_documento
    $sql = "SELECT id, nombre_completo, numero_documento, estado, deleted_at, es_cliente 
            FROM terceros 
            WHERE (nombre_completo LIKE :q1 OR numero_documento LIKE :q2)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['q1' => "%$termino_busqueda%", 'q2' => "%$termino_busqueda%"]);
    $coincidencias = $stmt->fetchAll();

    if(count($coincidencias) === 0) {
        echo "<p style='color: #f85149;'>❌ FATAL: No existe ningún registro en la tabla `terceros` que contenga la palabra '$termino_busqueda'.</p>";
    } else {
        echo "<p style='color: #3fb950;'>✅ Se encontraron " . count($coincidencias) . " posibles coincidencias.</p>";
        
        echo "<table style='width:100%; text-align:left; border-collapse: collapse; margin-top: 10px; background: #161b22; border-radius: 8px; overflow: hidden;'>";
        echo "<tr style='border-bottom: 1px solid #30363d; background: #21262d;'><th style='padding:10px;'>ID</th><th>Nombre</th><th>es_cliente (Debe ser 1)</th><th>estado (Debe ser 1)</th><th>deleted_at (Debe ser NULL)</th><th>¿LO MUESTRA TOM SELECT?</th></tr>";
        
        $pasaron_prueba = 0;

        foreach($coincidencias as $c) {
            $es_cliente_ok = ($c['es_cliente'] == 1);
            $estado_ok = ($c['estado'] == 1);
            $no_eliminado_ok = ($c['deleted_at'] === null);

            $esValido = ($es_cliente_ok && $estado_ok && $no_eliminado_ok);
            if($esValido) $pasaron_prueba++;

            $color = $esValido ? '#3fb950' : '#f85149';
            $textoAparece = $esValido ? '✅ SÍ' : '❌ NO';
            
            echo "<tr style='border-bottom: 1px solid #30363d;'>";
            echo "<td style='padding:10px;'>" . $c['id'] . "</td>";
            echo "<td>" . htmlspecialchars($c['nombre_completo']) . "</td>";
            echo "<td style='color: " . ($es_cliente_ok ? '#c9d1d9' : '#f85149') . ";'>" . $c['es_cliente'] . "</td>";
            echo "<td style='color: " . ($estado_ok ? '#c9d1d9' : '#f85149') . ";'>" . $c['estado'] . "</td>";
            echo "<td style='color: " . ($no_eliminado_ok ? '#c9d1d9' : '#f85149') . ";'>" . ($c['deleted_at'] ?? 'NULL') . "</td>";
            echo "<td style='color: $color; font-weight:bold;'>$textoAparece</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<div style='background: #1f2428; padding: 20px; margin-top:20px; border-radius: 5px; border-left: 5px solid #58a6ff;'>";
        echo "<h4 style='margin-top:0;'>🕵️‍♂️ DIAGNÓSTICO FINAL:</h4>";
        
        if($pasaron_prueba === 0) {
            echo "<p style='color:#ff7b72'><b>El problema son los datos en la tabla.</b> Tus clientes llamados 'suy' o 'oficina' tienen el estado en 0 o no están marcados como clientes.</p>";
        } else {
            echo "<p style='color:#3fb950'><b>La base de datos está PERFECTA.</b> Si esto no carga en pantalla, el culpable es tu <b>Controlador PHP</b> (el archivo que recibe <code>accion=buscar_clientes</code>).</p>";
            echo "Asegúrate de que en el controlador tengas algo como esto:<br>";
            echo "<code>\$resultados = \$modelo->buscarClientes(\$_GET['q'] ?? '');<br>";
            echo "echo json_encode(['data' => \$resultados, 'ok' => true]);<br>";
            echo "exit;</code>";
        }
        echo "</div>";
    }

} catch (\PDOException $e) {
    echo "<p style='color: #f85149;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";