<?php
declare(strict_types=1);

// =====================================================
// index.php - Punto de Entrada Principal
// =====================================================

// 1. CONSTANTES Y ENTORNO BASE
// Define la raíz del proyecto (subiendo un nivel desde 'public')
define('BASE_PATH', dirname(__DIR__));

// Carga de Composer (Librerías externas)
$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die("<h3>Error Crítico</h3><p>Falta el archivo <code>vendor/autoload.php</code>. Ejecuta <code>composer install</code>.</p>");
}
require $autoload;

// Carga de variables de entorno (.env)
if (file_exists(BASE_PATH . '/.env') && class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

$sessionCookieLifetime = filter_var($_ENV['SESSION_COOKIE_LIFETIME'] ?? getenv('SESSION_COOKIE_LIFETIME'), FILTER_VALIDATE_INT);
if ($sessionCookieLifetime === false || $sessionCookieLifetime <= 0) {
    $sessionCookieLifetime = 28800; // 8 horas
}

// 2. CONFIGURACIÓN DE SESIÓN
if (session_status() === PHP_SESSION_NONE) {
    // Detectar HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');

    // Limpiar host para cookie domain
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', $host);

    $cookieParams = [
        'lifetime' => $sessionCookieLifetime,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ];

    // Solo asignar dominio si no es localhost/IP para evitar problemas locales
    if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false && $host !== 'localhost') {
        $cookieParams['domain'] = $host;
    }

    ini_set('session.gc_maxlifetime', (string) $sessionCookieLifetime);
    session_set_cookie_params($cookieParams);
    session_start();
}

// Configuración de Errores (Hardcoded a development para que puedas ver errores)
$env = 'development';

if ($env === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 3. CARGA DEL NÚCLEO (CORE)
// Funciones globales de ayuda (base_url, redirect, etc.)
$helpers = BASE_PATH . '/app/core/helpers.php';
if (file_exists($helpers)) {
    require_once $helpers;
}

// Archivos esenciales del MVC
$coreFiles = [
    BASE_PATH . '/app/config/Conexion.php', // Base de datos
    BASE_PATH . '/app/core/Modelo.php',     // Clase padre Modelos
    BASE_PATH . '/app/core/Controlador.php',// Clase padre Controladores
    BASE_PATH . '/app/core/Router.php'      // Enrutador
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        die("<h1>Error Crítico</h1><p>No se encuentra el archivo del núcleo: <code>" . htmlspecialchars($file) . "</code></p>");
    }
}

// 4. EJECUCIÓN (DISPATCH)
try {
    if (!class_exists('Router')) {
        throw new Exception("La clase 'Router' no se encuentra. Revisa app/core/Router.php");
    }

    $router = new Router();

    if (!method_exists($router, 'dispatch')) {
        throw new Exception("El Router no tiene el método 'dispatch()'.");
    }

    // Iniciar el enrutamiento
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
        // Mensaje genérico para producción
        echo "<h1>500 - Error Interno</h1>";
        echo "<p>Ocurrió un problema inesperado. Por favor contacta al soporte.</p>";
    }
    exit;
}
