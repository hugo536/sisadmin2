<?php
declare(strict_types=1);

// =====================================================
// 1. CONFIGURACIÓN DE SESIÓN
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', $host);
    
    $cookieParams = [
        'lifetime' => 28800,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ];

    if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false) {
        $cookieParams['domain'] = $host;
    }

    session_set_cookie_params($cookieParams);
    session_start();
}

// =====================================================
// 2. CONSTANTES Y ENTORNO
// =====================================================

define('BASE_PATH', dirname(__DIR__)); 

$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die("<h3>Error Crítico</h3><p>Falta el archivo <code>vendor/autoload.php</code>. Ejecuta <code>composer install</code>.</p>");
}
require $autoload;

if (file_exists(BASE_PATH . '/.env') && class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// FORZAR MODO DESARROLLO (Para ver errores si algo falla ahora)
// Cuando termines todo, puedes cambiar esto a 'production'
$env = 'development'; 

if ($env === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// =====================================================
// 3. CARGA DEL NÚCLEO (CORE)
// =====================================================

$helpers = BASE_PATH . '/app/core/helpers.php';
if (file_exists($helpers)) {
    require_once $helpers;
}

$coreFiles = [
    BASE_PATH . '/app/core/Modelo.php',
    BASE_PATH . '/app/core/Controlador.php',
    BASE_PATH . '/app/core/Router.php'
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// =====================================================
// 4. EJECUCIÓN (DISPATCH)
// =====================================================
try {
    if (!class_exists('Router')) {
        throw new Exception("La clase 'Router' no se encuentra.");
    }

    $router = new Router();

    if (!method_exists($router, 'dispatch')) {
        throw new Exception("El Router no tiene el método 'dispatch()'.");
    }

    $router->dispatch();

} catch (Throwable $e) {
    http_response_code(500);
    
    if ($env === 'development') {
        echo "<div style='font-family: monospace; background:#fff3cd; color:#856404; padding:20px; border:1px solid #ffeeba; margin: 20px;'>";
        echo "<h3 style='margin-top:0;'>⚠️ Error del Sistema</h3>";
        echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . " (Línea " . $e->getLine() . ")</p>";
        echo "<hr><pre>" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    } else {
        echo "<h1>500 - Error Interno</h1>";
        echo "<p>Ocurrió un problema inesperado.</p>";
    }
    exit;
}