<?php
declare(strict_types=1);

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// CONSTANTES Y CONFIGURACIÓN
// =====================================================
// Asume que index.php está en /public. Si está en raíz, usa __DIR__
define('BASE_PATH', dirname(__DIR__)); 

// 1. Cargar Composer (Autoload)
$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die("Error crítico: Falta vendor/autoload.php. Ejecuta 'composer install'.");
}
require $autoload;

// 2. Cargar Variables de Entorno (.env)
if (file_exists(BASE_PATH . '/.env') && class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// 3. Cargar Helpers (Funciones globales no son clases, requieren include manual)
$helpers = BASE_PATH . '/app/core/helpers.php';
if (file_exists($helpers)) {
    require_once $helpers;
}

// =====================================================
// CARGA DEL NÚCLEO (CORE)
// =====================================================
// NOTA: Si configuras PSR-4 en composer.json, estos requires no son necesarios.
// Los dejo por si aún no has configurado el autoload para 'app/core'.
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
// EJECUCIÓN (DISPATCH)
// =====================================================
try {
    // Verificamos que la clase Router exista (cargada por require o composer)
    if (!class_exists('Router')) {
        throw new Exception("La clase 'Router' no se encuentra. Verifica el namespace o el archivo.");
    }

    $router = new Router();

    // Estandarizamos a 'dispatch'. Asegúrate que tu Router.php tenga este método.
    if (!method_exists($router, 'dispatch')) {
        throw new Exception("El Router no tiene el método 'dispatch()'.");
    }

    $router->dispatch();

} catch (Throwable $e) {
    // En producción, aquí deberías registrar el log y mostrar un mensaje genérico.
    http_response_code(500);
    
    // Solo mostrar detalles en entorno de desarrollo
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        echo "<div style='background:#f8d7da; color:#721c24; padding:20px; border:1px solid #f5c6cb; font-family:sans-serif;'>";
        echo "<strong>Error del Sistema:</strong> " . htmlspecialchars($e->getMessage());
        echo "<br><small>En: " . $e->getFile() . " línea " . $e->getLine() . "</small>";
        echo "</div>";
    } else {
        echo "Ocurrió un error interno en el servidor.";
    }
    exit;
}