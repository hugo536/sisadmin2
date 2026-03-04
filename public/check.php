<?php
/**
 * SCRIPT DE DEPURACIÓN: Periodos Contables
 */
declare(strict_types=1);

// --- CONFIGURA ESTOS DATOS ---
$db_host = 'localhost';
$db_name = 'sisadmin2'; 
$db_user = 'root';
$db_pass = ''; 
$userId  = 1; // Un ID de usuario válido para simular quién hace el cambio

echo "<h2>🔍 Probando Actualización Directa: Conta Periodos</h2>";

try {
    // 1. Conexión Directa
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p style='color:green;'>✅ Conexión establecida.</p>";

    // 2. Buscar el primer periodo disponible
    $stmt = $pdo->query("SELECT id, anio, mes, estado FROM conta_periodos WHERE deleted_at IS NULL LIMIT 1");
    $periodo = $stmt->fetch();

    if (!$periodo) {
        die("<p style='color:red;'>❌ La tabla 'conta_periodos' está vacía o todos están eliminados.</p>");
    }

    $id = (int)$periodo['id'];
    $estadoOriginal = $periodo['estado'];
    
    // Invertimos el estado para probar
    $nuevoEstado = ($estadoOriginal === 'ABIERTO') ? 'CERRADO' : 'ABIERTO';

    echo "<p>Periodo a probar: <b>Año {$periodo['anio']} - Mes {$periodo['mes']}</b> (ID: $id)</p>";
    echo "<p>Estado actual: <b>$estadoOriginal</b>. Intentando cambiar a: <b>$nuevoEstado</b>...</p>";

    // 3. Ejecutar el UPDATE exactamente como lo hace tu Modelo
    $esCerrado = ($nuevoEstado === 'CERRADO');
    $sql = 'UPDATE conta_periodos
            SET estado = :estado,
                cerrado_at = ' . ($esCerrado ? 'NOW()' : 'NULL') . ',
                cerrado_by = ' . ($esCerrado ? ':cerrado_by' : 'NULL') . ',
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL';

    $params = [
        'estado' => $nuevoEstado,
        'updated_by' => $userId,
        'id' => $id,
    ];

    if ($esCerrado) {
        $params['cerrado_by'] = $userId;
    }

    $update = $pdo->prepare($sql);
    $update->execute($params);

    // 4. Verificar si el cambio se guardó
    $stmtVerificar = $pdo->prepare("SELECT estado FROM conta_periodos WHERE id = :id");
    $stmtVerificar->execute(['id' => $id]);
    $estadoFinal = $stmtVerificar->fetchColumn();

    echo "<hr>";
    if ($estadoFinal === $nuevoEstado) {
        echo "<h1 style='color:green;'>✅ ¡LA BASE DE DATOS FUNCIONA!</h1>";
        echo "<p>El cambio se guardó correctamente. El error del F12 <b>no es de MySQL</b>.</p>";
    } else {
        echo "<h1 style='color:red;'>❌ LA BASE DE DATOS NO CAMBIÓ</h1>";
        echo "<p>Revisa si hay algún Trigger bloqueando el UPDATE.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error SQL: " . $e->getMessage() . "</p>";
}