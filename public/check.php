<?php
/**
 * 🔍 SCRIPT DE DEPURACIÓN PROFUNDA: Módulo de Ítems
 */
declare(strict_types=1);

// Configuramos errores al máximo para que nada se escape
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Subimos un nivel porque check.php está dentro de /public
define('BASE_PATH', dirname(__DIR__));

echo "<div style='font-family: system-ui, sans-serif; padding: 30px; background: #0d1117; color: #c9d1d9; border-radius: 8px;'>";
echo "<h1 style='color: #58a6ff;'>🔬 CHECK.PHP - Laboratorio de Pruebas de Ítems</h1>";
echo "<hr style='border-color: #30363d;'>";

try {
    // --- PRUEBA 1: Carga de dependencias ---
    echo "<h3 style='color: #ff7b72;'>▶ Prueba 1: Carga de Archivos Base</h3>";
    $archivos_necesarios = [
        BASE_PATH . '/app/config/config.php', // Ajusta si tu config se llama diferente
        BASE_PATH . '/app/core/Database.php', // Ajusta si tu DB está en otra ruta
        BASE_PATH . '/app/core/Modelo.php',
        BASE_PATH . '/app/models/items/ItemModel.php'
    ];

    foreach ($archivos_necesarios as $archivo) {
        if (file_exists($archivo)) {
            require_once $archivo;
            echo "<p style='color: #3fb950; margin: 2px 0;'>✅ Archivo encontrado: " . basename($archivo) . "</p>";
        } else {
            echo "<p style='color: #d2a8ff; margin: 2px 0;'>⚠️ Archivo no encontrado (Puede ser normal si se autocarga): " . basename($archivo) . "</p>";
        }
    }

    // --- PRUEBA 2: Instanciar Modelo ---
    echo "<h3 style='color: #ff7b72; margin-top: 20px;'>▶ Prueba 2: Instanciar ItemModel</h3>";
    if (!class_exists('ItemModel')) {
        throw new Exception("La clase ItemModel no existe. Falla el autoload o el require.");
    }
    $modelo = new ItemModel();
    echo "<p style='color: #3fb950;'>✅ Modelo instanciado correctamente.</p>";

    // --- PRUEBA 3: Testeando la función listarTodos() ---
    echo "<h3 style='color: #ff7b72; margin-top: 20px;'>▶ Prueba 3: Ejecutando listarTodos() (Tabla Estática)</h3>";
    $items = $modelo->listarTodos();
    
    $total_items = count($items);
    if ($total_items > 0) {
        echo "<p style='color: #3fb950;'>✅ Éxito: La base de datos devolvió <b>$total_items</b> ítems.</p>";
        echo "<p>Mostrando la estructura del primer ítem para verificar:</p>";
        echo "<pre style='background: #161b22; padding: 15px; border-radius: 5px; color: #a5d6ff; overflow-x: auto;'>";
        print_r($items[0]);
        echo "</pre>";
    } else {
        echo "<p style='color: #f85149;'>⚠️ Advertencia: La consulta funcionó, pero devolvió 0 ítems. ¿La tabla está vacía?</p>";
    }

    // --- PRUEBA 4: Testeando el antiguo datatable() ---
    echo "<h3 style='color: #ff7b72; margin-top: 20px;'>▶ Prueba 4: Ejecutando datatable() (El método viejo)</h3>";
    $dt = $modelo->datatable(1, 10, []);
    echo "<p style='color: #3fb950;'>✅ El datatable no está roto. Devolvió recordsFiltered: <b>" . $dt['recordsFiltered'] . "</b></p>";

    echo "<hr style='border-color: #30363d; margin-top: 30px;'>";
    echo "<h2 style='color: #3fb950;'>🚀 CONCLUSIÓN DEL BACKEND</h2>";
    echo "<p>Si ves datos arriba y ningún error fatal rojo, significa que <b>tu PHP y tu Base de Datos están trabajando de manera impecable</b>.</p>";

} catch (Throwable $e) {
    echo "<hr style='border-color: #30363d; margin-top: 30px;'>";
    echo "<h2 style='color: #f85149;'>❌ ERROR FATAL DETECTADO</h2>";
    echo "<p><b>Mensaje:</b> " . $e->getMessage() . "</p>";
    echo "<p><b>Archivo:</b> " . $e->getFile() . " (Línea " . $e->getLine() . ")</p>";
    echo "<b>Trace:</b><pre style='background: #161b22; padding: 15px; color: #ff7b72;'>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";