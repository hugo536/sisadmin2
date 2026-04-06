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
    $sessionCookieLifetime = 28800; // 8 horas por defecto
}

$sessionCookieName = trim((string) ($_ENV['SESSION_COOKIE_NAME'] ?? getenv('SESSION_COOKIE_NAME') ?: ''));
if ($sessionCookieName === '') {
    $projectSlug = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '_', basename(BASE_PATH)));
    if ($projectSlug === '') {
        $projectSlug = 'SISADMIN';
    }
    $sessionCookieName = $projectSlug . '_SESSID';
}

// MODIFICACIÓN CLAVE 1: Forzamos el path a la raíz para evitar pérdida de sesión al cambiar de URL
$sessionCookiePath = '/';

$sessionSameSite = strtoupper(trim((string) ($_ENV['SESSION_COOKIE_SAMESITE'] ?? getenv('SESSION_COOKIE_SAMESITE') ?: 'LAX')));
if (!in_array($sessionSameSite, ['LAX', 'STRICT', 'NONE'], true)) {
    $sessionSameSite = 'LAX';
}
if ($sessionSameSite === 'NONE') {
    $sessionSameSite = 'None';
} elseif ($sessionSameSite === 'STRICT') {
    $sessionSameSite = 'Strict';
} else {
    $sessionSameSite = 'Lax';
}

// 2. CONFIGURACIÓN DE SESIÓN
if (session_status() === PHP_SESSION_NONE) {
    // Detectar HTTPS (incluyendo proxies/reverse proxies)
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || str_contains($forwardedProto, 'https')
        || $forwardedSsl === 'on';

    // En HTTP (como suele ser localhost), SameSite=None es rechazado por los navegadores modernos
    $cookieSameSite = $sessionSameSite;
    if ($cookieSameSite === 'None' && !$isSecure) {
        $cookieSameSite = 'Lax';
    }

    // Limpiar host para cookie domain
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/:\d+$/', '', $host);

    $cookieParams = [
        'lifetime' => $sessionCookieLifetime,
        'path'     => $sessionCookiePath,
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => $cookieSameSite,
    ];

    // MODIFICACIÓN CLAVE 2: Comprobar si estamos en localhost o usando una IP local
    $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || filter_var($host, FILTER_VALIDATE_IP) !== false;
    
    // Solo asignar dominio si estamos en un hosting real con dominio, para no romper localhost
    if ($host !== '' && !$isLocalhost) {
        $cookieParams['domain'] = $host;
    }

    session_name($sessionCookieName);
    ini_set('session.gc_maxlifetime', (string) $sessionCookieLifetime);
    session_set_cookie_params($cookieParams);
    session_start();
}

// Configuración de Errores
// NOTA: Recuerda cambiar esto a 'production' cuando subas al hosting final si no quieres que los usuarios vean errores de código
$env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

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
    BASE_PATH . '/app/core/AuditLogger.php',// Auditoría global de mutaciones
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