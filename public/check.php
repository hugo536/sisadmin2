<?php
// Archivo: public/check.php
// Accede en tu navegador a: http://localhost/sisadmin2/public/check.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🕵️ Diagnóstico de Archivos y Modelos</h2><hr>";

// 1. Definir Ruta Base
define('BASE_PATH', dirname(__DIR__));
echo "✅ <b>BASE_PATH:</b> " . BASE_PATH . "<br>";

// 2. Cargar Autoload
$autoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
    echo "✅ <b>Composer:</b> Cargado correctamente.<br>";
} else {
    die("❌ <b>ERROR:</b> No se encuentra vendor/autoload.php");
}

// 3. Cargar Variables de Entorno
if (class_exists(\Dotenv\Dotenv::class) && file_exists(BASE_PATH . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
    echo "✅ <b>Entorno (.env):</b> Cargado.<br>";
}

// 4. Cargar Conexión
$conexionFile = BASE_PATH . '/app/config/Conexion.php';
if (file_exists($conexionFile)) {
    require_once $conexionFile;
    echo "✅ <b>Archivo Conexion.php:</b> Encontrado.<br>";
} else {
    die("❌ <b>ERROR:</b> Falta app/config/Conexion.php");
}

// 5. Probar Conexión BD
try {
    $pdo = Conexion::get();
    echo "✅ <b>Base de Datos:</b> Conectado exitosamente.<br>";
} catch (Exception $e) {
    die("❌ <b>ERROR BD:</b> " . $e->getMessage());
}

// 6. Cargar Modelo Base
require_once BASE_PATH . '/app/core/Modelo.php';

// 7. VERIFICAR MODELO TERCEROS Y SUB-MODELOS
$archivos = [
    '/app/models/terceros/TercerosClientesModel.php',
    '/app/models/terceros/TercerosProveedoresModel.php',
    '/app/models/terceros/TercerosEmpleadosModel.php',
    '/app/models/terceros/DistribuidoresModel.php',
    '/app/models/TercerosModel.php'
];

echo "<hr><h3>Verificando Archivos de Modelos:</h3>";

foreach ($archivos as $archivo) {
    $ruta = BASE_PATH . $archivo;
    if (file_exists($ruta)) {
        echo "✅ Existe: $archivo <br>";
        require_once $ruta;
    } else {
        echo "❌ <b>FALTA:</b> $archivo <br>";
        $error = true;
    }
}

if (isset($error)) {
    die("<br><b>⛔ DETENIDO:</b> Faltan archivos del modelo.");
}

// 8. INTENTO DE INSTANCIA
echo "<hr><h3>Prueba de Instancia (Constructor):</h3>";
try {
    $model = new TercerosModel();
    echo "✅ <b>¡ÉXITO!</b> La clase TercerosModel se instanció correctamente.<br>";
    echo "Los constructores de Clientes, Proveedores, Empleados y Distribuidores funcionaron.";
} catch (Throwable $e) {
    echo "❌ <b>ERROR FATAL AL INSTANCIAR:</b> " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . " en línea " . $e->getLine();
}
?>