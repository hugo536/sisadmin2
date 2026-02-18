<?php
// check.php - Herramienta de Diagnóstico Full Stack
// COLOCAR EN LA RAÍZ DEL PROYECTO (Junto a index.php)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db_name = 'sisadmin2';
$user = 'root';
$pass = ''; // Tu contraseña

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico SISADMIN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 20px; font-family: sans-serif; }
        .card { margin-bottom: 20px; border: none; shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-ok { color: green; font-weight: bold; }
        .status-err { color: red; font-weight: bold; }
        pre { background: #2d2d2d; color: #fff; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mb-4">🛠️ Diagnóstico de Fallo en Botón Editar</h2>

    <div class="card p-3">
        <h4>1. Verificación de Backend (PHP/MySQL)</h4>
        <?php
        $id_prueba = 0;
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $user, $pass);
            echo "<div class='status-ok'>✅ Conexión a Base de Datos EXITOSA</div>";
            
            // Buscar un ID real
            $stmt = $pdo->query("SELECT id FROM precios_presentaciones LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $id_prueba = $row['id'];
                echo "<div>ℹ️ Usaremos el ID <strong>$id_prueba</strong> para las pruebas.</div>";
            } else {
                echo "<div class='status-err'>❌ La tabla está vacía. Crea un registro manualmente primero.</div>";
            }

        } catch (PDOException $e) {
            echo "<div class='status-err'>❌ Error de Conexión: " . $e->getMessage() . "</div>";
            die(); // Detener si no hay BD
        }
        ?>
    </div>

    <div class="card p-3">
        <h4>2. Verificación de Ruta JSON (Simulación)</h4>
        <p>Intentando conectar con tu Controlador...</p>
        <div id="box-backend">⏳ Esperando prueba...</div>
        <small class="text-muted">La URL probada es: <code>?ruta=comercial/obtenerPresentacion&id=<?php echo $id_prueba; ?></code></small>
    </div>

    <div class="card p-3">
        <h4>3. Prueba de Botón (Frontend)</h4>
        <p>Haz clic en el botón de abajo. Este botón usa un script <strong>aislado</strong> (sin caché).</p>
        
        <div class="d-flex gap-3 align-items-center">
            <button class="btn btn-primary js-prueba-editar" data-id="<?php echo $id_prueba; ?>">
                <i class="bi bi-pencil"></i> PROBAR EDICIÓN (Click Aquí)
            </button>
            <span id="msg-click" class="text-muted"><-- Esperando clic...</span>
        </div>

        <div class="mt-3">
            <strong>Resultado del JS:</strong>
            <pre id="console-output">Esperando acción...</pre>
        </div>
    </div>

    <div class="alert alert-info">
        <strong>Interpretación:</strong><br>
        1. Si el <strong>Paso 2</strong> falla (Rojo), tu archivo <code>ComercialController.php</code> no tiene la función o la ruta está mal.<br>
        2. Si el <strong>Paso 3</strong> funciona aquí pero NO en tu sistema, el problema es que tu archivo <code>comercial.js</code> no se está cargando (Error 404) o tienes un error de sintaxis en otro archivo JS que rompe la página.
    </div>

</div>

<script>
    const output = document.getElementById('console-output');
    const boxBackend = document.getElementById('box-backend');
    const msgClick = document.getElementById('msg-click');
    const testId = <?php echo $id_prueba; ?>;

    // Función para escribir en el log visual
    function log(msg, type = 'info') {
        const color = type === 'error' ? '#ffcccc' : (type === 'success' ? '#ccffcc' : '#fff');
        output.style.color = type === 'error' ? '#ff5555' : (type === 'success' ? '#55ff55' : '#fff');
        output.innerText = msg;
    }

    // 1. AUTO-TEST DE RUTA (PASO 2)
    fetch(`index.php?ruta=comercial/obtenerPresentacion&id=${testId}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.text();
        })
        .then(text => {
            try {
                const json = JSON.parse(text);
                if (json.success) {
                    boxBackend.innerHTML = `<span class='status-ok'>✅ La ruta responde JSON correctamente.</span>`;
                } else {
                    boxBackend.innerHTML = `<span class='status-err'>⚠️ La ruta responde JSON pero con error: ${json.message}</span>`;
                }
            } catch (e) {
                console.error("Respuesta no válida:", text);
                boxBackend.innerHTML = `<span class='status-err'>❌ ERROR GRAVE: La ruta no devuelve JSON. Devuelve HTML o Texto. (Mira la consola F12)</span>`;
                log("El servidor devolvió HTML en lugar de JSON. Probablemente un error PHP o redirect.", 'error');
            }
        })
        .catch(err => {
            boxBackend.innerHTML = `<span class='status-err'>❌ Error de conexión: ${err.message}</span>`;
        });


    // 2. LISTENER DEL BOTÓN (PASO 3)
    document.querySelector('.js-prueba-editar').addEventListener('click', function() {
        msgClick.innerText = "✅ ¡Clic detectado por JS!";
        msgClick.style.color = "green";
        msgClick.style.fontWeight = "bold";

        log("Iniciando petición AJAX...", 'info');

        fetch(`index.php?ruta=comercial/obtenerPresentacion&id=${this.dataset.id}`)
            .then(res => res.json())
            .then(data => {
                log("¡ÉXITO! Datos recibidos:\n" + JSON.stringify(data, null, 2), 'success');
                alert("¡FUNCIONA! El sistema es capaz de leer los datos.\n\nSi ves esto aquí, tu JS original tiene un error de carga.");
            })
            .catch(err => {
                log("Error en petición JS: " + err, 'error');
                alert("Error: " + err);
            });
    });
</script>

</body>
</html>