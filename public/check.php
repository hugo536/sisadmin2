<?php
// 1. CONFIGURACIÓN DE CABECERAS PARA FORZAR JSON
// Esto es vital. Si ves HTML en la respuesta de este archivo, 
// tu servidor web (Apache/Nginx) está inyectando cosas antes de PHP.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// 2. CAPTURA DE ERRORES (Buffer de Salida)
// Iniciamos un buffer para que si hay un "Notice" o "Warning", 
// no rompa el JSON, sino que lo capturemos.
ob_start();

$debug = [
    'status' => 'pending',
    'timestamp' => date('Y-m-d H:i:s'),
    'system' => [],
    'session' => [],
    'database' => [],
    'request' => [],
    'errors' => []
];

try {
    // --- A. CHEQUEO DEL SISTEMA ---
    $debug['system']['php_version'] = phpversion();
    $debug['system']['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $debug['system']['document_root'] = $_SERVER['DOCUMENT_ROOT'];

    // --- B. CHEQUEO DE SESIÓN ---
    // Intentamos iniciar sesión. Si falla, sabremos que el directorio de sesiones no tiene permisos.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $debug['session']['status'] = 'Active';
    $debug['session']['id'] = session_id();
    $debug['session']['data'] = $_SESSION; // Muestra qué datos tiene el usuario actual
    
    // Prueba de escritura en sesión
    $_SESSION['check_test'] = 'Funciona: ' . time();

    // --- C. CHEQUEO DE BASE DE DATOS ---
    // ADVERTENCIA: Rellena esto con tus datos reales del config
    $dbHost = 'localhost';     // <--- CAMBIAR SI ES NECESARIO
    $dbName = 'nombre_de_tu_bd'; // <--- PON EL NOMBRE DE TU BD AQUÍ
    $dbUser = 'root';          // <--- TU USUARIO
    $dbPass = '';              // <--- TU CONTRASEÑA

    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $debug['database']['connection'] = 'SUCCESS';
        $debug['database']['info'] = 'Conectado a ' . $dbName;
        
        // Prueba simple de consulta
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug['database']['query_test'] = $result;

    } catch (PDOException $e) {
        $debug['database']['connection'] = 'ERROR';
        $debug['database']['error_msg'] = $e->getMessage();
    }

    // --- D. CHEQUEO DE LA SOLICITUD (REQUEST) ---
    // Esto verifica si el servidor recibe bien los datos JSON o POST
    $rawInput = file_get_contents('php://input');
    $debug['request']['method'] = $_SERVER['REQUEST_METHOD'];
    $debug['request']['raw_input'] = $rawInput;
    $debug['request']['json_decoded'] = json_decode($rawInput, true);
    $debug['request']['post_data'] = $_POST;

    $debug['status'] = 'ok';

} catch (Throwable $e) {
    $debug['status'] = 'error';
    $debug['errors'][] = [
        'type' => 'Exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

// 3. LIMPIEZA DEL BUFFER
// Si hubo algún echo, print o warning de PHP antes de esto, lo guardamos en 'output_buffer'
$outputBuffer = ob_get_clean();
if (!empty($outputBuffer)) {
    $debug['errors'][] = [
        'type' => 'Output Buffer (Unexpected Content)',
        'content' => $outputBuffer // <--- AQUÍ APARECERÁ EL HTML "COLADO" SI LO HAY
    ];
}

// 4. SALIDA FINAL
echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);