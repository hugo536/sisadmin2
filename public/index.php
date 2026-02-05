<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// BOOTSTRAP BASE (Semana 0)
// =====================================================
define('BASE_PATH', dirname(__DIR__));

// Composer autoload
$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo "Falta vendor/autoload.php. Ejecuta: composer install";
    exit;
}
require $autoload;

// Cargar .env (si tienes vlucas/phpdotenv)
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile) && class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// Helpers (si los usas)
$helpers = BASE_PATH . '/app/core/helpers.php';
if (file_exists($helpers)) {
    require_once $helpers;
}

// Core base classes
$modeloBase = BASE_PATH . '/app/core/Modelo.php';
if (file_exists($modeloBase)) {
    require_once $modeloBase;
}

$controladorBase = BASE_PATH . '/app/core/Controlador.php';
if (file_exists($controladorBase)) {
    require_once $controladorBase;
}

// =====================================================
// ROUTING
// =====================================================
// Si tu Router.php ya existe, lo cargamos:
$routerFile = BASE_PATH . '/app/core/Router.php';
if (!file_exists($routerFile)) {
    http_response_code(500);
    echo "Falta app/core/Router.php";
    exit;
}
require_once $routerFile;

// --- Enganche estándar ---
// Ajusta SOLO esta parte si tu clase Router usa otro nombre de método.
try {
    if (class_exists('Router')) {
        $router = new Router();

        // ✅ OPCIÓN A (común): $router->dispatch();
        if (method_exists($router, 'dispatch')) {
            $router->dispatch();
            exit;
        }

        // ✅ OPCIÓN B: $router->run();
        if (method_exists($router, 'run')) {
            $router->run();
            exit;
        }

        // ✅ OPCIÓN C: $router->direccionar();
        if (method_exists($router, 'direccionar')) {
            $router->direccionar();
            exit;
        }

        // Fallback: si no encuentro método típico
        http_response_code(500);
        echo "Router cargado, pero no encuentro método dispatch/run/direccionar. Abre app/core/Router.php y dime el método.";
        exit;
    }

    http_response_code(500);
    echo "No existe la clase Router en app/core/Router.php";
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "Error en index.php: " . htmlspecialchars($e->getMessage());
    exit;
}
