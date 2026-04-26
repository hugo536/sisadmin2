<?php
/**
 * 🔍 SUPER CHECKER - Diagnóstico Total del Buscador (BD + AJAX + FRONTEND)
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

// --- CREDENCIALES ---
$host = '127.0.0.1';
$db   = 'sisadmin2';
$user = 'root';
$pass = '';

$termino_busqueda = $_GET['q'] ?? 'oficina';

echo "<!DOCTYPE html><html><head><title>Debugger Buscador</title>";
// Cargamos TomSelect para la prueba de "Cuarto Limpio"
echo '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">';
echo '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>';
echo "</head><body style='font-family: system-ui, sans-serif; padding: 30px; background: #0d1117; color: #c9d1d9;'>";

echo "<h1 style='color: #58a6ff;'>🕵️‍♂️ DIAGNÓSTICO TOTAL: Buscador de Clientes</h1>";
echo "<p>Simulando búsqueda para el término: <strong style='color:#f0883e;'>'$termino_busqueda'</strong> <i>(Puedes probar otros añadiendo ?q=palabra a la URL)</i></p>";
echo "<hr style='border-color: #30363d;'>";

// ==========================================
// 1. TEST DE BASE DE DATOS
// ==========================================
echo "<h3 style='color: #ff7b72;'>1️⃣ TEST DE BASE DE DATOS</h3>";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Conexión a BD exitosa.<br>";

    $stmt = $pdo->prepare("SELECT id, nombre_completo, numero_documento, estado, deleted_at, es_cliente FROM terceros WHERE (nombre_completo LIKE :q1 OR numero_documento LIKE :q2)");
    $stmt->execute(['q1' => "%$termino_busqueda%", 'q2' => "%$termino_busqueda%"]);
    $clientes = $stmt->fetchAll();

    if (empty($clientes)) {
        echo "❌ <b style='color:#f85149'>No hay coincidencias en la BD</b> para '$termino_busqueda'.<br>";
    } else {
        echo "✅ Se encontraron " . count($clientes) . " registros brutos en la BD.<br>";
        $validos = 0;
        foreach($clientes as $c) {
            if ($c['estado'] == 1 && $c['es_cliente'] == 1 && $c['deleted_at'] === null) $validos++;
        }
        if ($validos === 0) {
            echo "❌ <b style='color:#f85149'>Niguno es válido</b> (Revisa que estado=1, es_cliente=1 y deleted_at=null).<br>";
        } else {
            echo "✅ $validos registros son clientes 100% válidos.<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error BD: " . $e->getMessage() . "<br>";
}

// ==========================================
// 2. TEST DEL ENDPOINT AJAX (CONTROLADOR PHP)
// ==========================================
echo "<h3 style='color: #ff7b72; margin-top:30px;'>2️⃣ TEST DEL CONTROLADOR AJAX</h3>";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['REQUEST_URI']);
$ajax_url = $base_url . "?ruta=ventas&accion=buscar_clientes&q=" . urlencode($termino_busqueda);

echo "<p>Haciendo petición GET a: <code>$ajax_url</code></p>";

$context = stream_context_create(["http" => ["header" => "X-Requested-With: XMLHttpRequest\r\n"]]);
$response = @file_get_contents($ajax_url, false, $context);

if ($response === false) {
    echo "❌ <b style='color:#f85149'>El controlador devolvió un error 500 o la ruta no existe.</b><br>";
} else {
    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ <b style='color:#f85149'>El controlador NO está devolviendo JSON válido.</b> Devuelve esto:<br>";
        echo "<pre style='background:#161b22; padding:10px; border-radius:5px; color:#a5d6ff; max-height:200px; overflow:auto;'>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "✅ El controlador devuelve un JSON válido.<br>";
        if (!isset($json['data'])) {
            echo "❌ <b style='color:#f85149'>Falta la llave 'data' en el JSON.</b> TomSelect crasheará porque necesita `{ \"data\": [...] }`.<br>";
        } else {
            echo "✅ El JSON tiene la llave 'data' con " . count($json['data']) . " elementos.<br>";
            if (count($json['data']) > 0) {
                echo "<i>Así se ve el primer registro que le llega al Javascript:</i><br>";
                echo "<pre style='background:#161b22; padding:10px; border-radius:5px; color:#a5d6ff;'>" . htmlspecialchars(json_encode($json['data'][0], JSON_PRETTY_PRINT)) . "</pre>";
            }
        }
    }
}

// ==========================================
// 3. FRONTEND 'CLEAN ROOM' TEST
// ==========================================
echo "<h3 style='color: #ff7b72; margin-top:30px;'>3️⃣ TEST FRONTEND 'CLEAN ROOM'</h3>";
echo "<p>Esta es una prueba aislada de TomSelect SIN tu app.css y SIN los modales de Bootstrap.</p>";
echo "<div style='background:#ffffff; padding: 20px; border-radius: 8px; color: #000; width: 400px;'>";
echo "<label style='font-weight:bold; display:block; margin-bottom:10px;'>Buscador Aislado:</label>";
echo "<select id='testSelect' placeholder='Escribe para buscar...'></select>";
echo "</div>";
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const baseUrl = "<?php echo $base_url . '?ruta=ventas&accion=buscar_clientes&q='; ?>";
    
    new TomSelect('#testSelect', {
        valueField: 'id',
        labelField: 'text',
        searchField: ['text', 'value'],
        score: function() { return function() { return 1; }; }, // Obliga a mostrar todo lo que traiga el servidor
        load: function(query, callback) {
            fetch(baseUrl + encodeURIComponent(query), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                const items = (json.data || []).map(item => ({
                    id: item.id,
                    text: `${item.nombre_completo} (${item.num_doc || 'S/D'})`
                }));
                console.log("✅ TomSelect recibió:", items);
                callback(items);
            }).catch(e => {
                console.error("❌ Error en JS:", e);
                callback();
            });
        }
    });
});
</script>
</body></html>