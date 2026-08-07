<?php
/**
 * 🔍 SUPER CHECKER V6 - El Cazador de TomSelect
 * Diagnóstico de APIs AJAX, JSON y renderizado de Dropdowns.
 */
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors', '1');

// Detectamos la ruta base automáticamente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$uri = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = $protocol . "://" . $host . $uri;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Debugger TomSelect V6</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Cargar TomSelect nativo para la prueba -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: 'Segoe UI', system-ui, sans-serif; padding: 20px; }
        .card { background-color: #161b22; border: 1px solid #30363d; }
        .card-header { background-color: #21262d; border-bottom: 1px solid #30363d; color: #fff; font-weight: bold; }
        .terminal { background-color: #010409; color: #58a6ff; font-family: monospace; font-size: 13.5px; padding: 15px; border-radius: 8px; height: 400px; overflow-y: auto; border: 1px solid #30363d; }
        .log-error { color: #ff7b72; }
        .log-success { color: #3fb950; }
        .log-warning { color: #d29922; }
        .log-info { color: #a5d6ff; }
        .ts-wrapper { background: #fff !important; border-radius: 5px; } /* Fondo blanco para aislar el input de prueba */
    </style>
</head>
<body>

<div class="container-fluid" style="max-width: 1400px;">
    <h2 class="mb-3 fw-bold text-white"><i class="bi bi-bug text-danger"></i> DIAGNÓSTICO V6: Cazador de TomSelect</h2>
    <p class="text-muted">Aislamiento de entorno para detectar por qué falla la búsqueda AJAX en el módulo de Ventas.</p>

    <div class="row g-4 mt-2">
        <!-- COLUMNA IZQUIERDA: Controles y Sandbox -->
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><i class="bi bi-play-circle"></i> Panel de Pruebas</div>
                <div class="card-body p-4">
                    <button id="btnRunAll" class="btn btn-primary w-100 fw-bold mb-3 py-2">
                        <i class="bi bi-rocket-takeoff me-2"></i> Iniciar Diagnóstico Completo
                    </button>
                    <hr class="border-secondary opacity-25">
                    <p class="small text-muted mb-2"><strong>Sandbox de TomSelect</strong> (Debería funcionar si la API responde bien):</p>
                    <div class="mb-3 bg-white p-3 rounded">
                        <select id="sandboxCliente" class="form-select" placeholder="Escribe para buscar..."></select>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info border-info-subtle bg-transparent">
                <h6 class="fw-bold"><i class="bi bi-info-circle"></i> ¿Qué busca este test?</h6>
                <ul class="small mb-0 ps-3">
                    <li>Si la ruta <code>index.php?ruta=ventas&accion=buscar_clientes</code> da Error 404 o 500.</li>
                    <li>Si PHP está inyectando HTML/Errores ocultos que rompen el formato <code>JSON</code>.</li>
                    <li>Si la librería de TomSelect está colisionando.</li>
                </ul>
            </div>
        </div>

        <!-- COLUMNA DERECHA: Terminal de logs -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-terminal"></i> Terminal de Diagnóstico</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('terminal').innerHTML=''">Limpiar</button>
                </div>
                <div class="card-body p-0">
                    <div id="terminal" class="terminal">
                        <div class="log-info">> Esperando inicio de diagnóstico...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
    const terminal = document.getElementById('terminal');
    const baseUrl = "<?php echo $baseUrl; ?>";

    function logTerminal(msg, type = 'info') {
        const div = document.createElement('div');
        div.className = `log-${type} mb-1`;
        
        let icon = 'bi-chevron-right';
        if(type === 'error') icon = 'bi-x-circle-fill';
        if(type === 'success') icon = 'bi-check-circle-fill';
        if(type === 'warning') icon = 'bi-exclamation-triangle-fill';

        div.innerHTML = `<i class="bi ${icon} me-2"></i>${msg}`;
        terminal.appendChild(div);
        terminal.scrollTop = terminal.scrollHeight;
    }

    async function runDiagnostics() {
        terminal.innerHTML = '';
        logTerminal('Iniciando batería de pruebas V6...', 'info');

        // PRUEBA 1: Verificar TomSelect
        logTerminal('Prueba 1: Verificando instancia de TomSelect...', 'info');
        if (typeof TomSelect === 'undefined') {
            logTerminal('FATAL: TomSelect no está cargado en el entorno.', 'error');
            return;
        }
        logTerminal(`TomSelect detectado correctamente (versión presumida 2.x).`, 'success');

        // PRUEBA 2: Ping al backend (buscar_clientes)
        logTerminal('Prueba 2: Lanzando petición AJAX pura a buscar_clientes (Simulando "suy")...', 'info');
        const urlCliente = `${baseUrl}/index.php?ruta=ventas&accion=buscar_clientes&q=suy`;
        logTerminal(`URL destino: <a href="${urlCliente}" target="_blank" class="text-info">${urlCliente}</a>`, 'warning');

        try {
            const response = await fetch(urlCliente, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            logTerminal(`Estado HTTP de respuesta: ${response.status} ${response.statusText}`, response.ok ? 'success' : 'error');

            const textData = await response.text(); // Capturamos como texto primero para ver si hay basura PHP
            
            if (!textData.trim().startsWith('{') && !textData.trim().startsWith('[')) {
                logTerminal(`FATAL: El servidor no devolvió un JSON limpio. Devolvió esto:`, 'error');
                logTerminal(textData.substring(0, 150) + '...', 'error');
                logTerminal(`Solución: Hay un 'echo' o un error PHP imprimiéndose antes del 'json_response()' en VentasController.php`, 'warning');
                return;
            }

            const jsonData = JSON.parse(textData);
            logTerminal(`JSON parseado con éxito.`, 'success');

            if (!jsonData.ok) {
                logTerminal(`El backend respondió con ok=false. Mensaje: ${jsonData.mensaje}`, 'error');
            } else {
                logTerminal(`El backend encontró ${jsonData.data ? jsonData.data.length : 0} registros para 'suy'.`, 'success');
            }

        } catch (error) {
            logTerminal(`FATAL: La petición Fetch falló estrepitosamente: ${error.message}`, 'error');
            return;
        }

        // PRUEBA 3: Ping al backend (buscar_items)
        logTerminal('Prueba 3: Lanzando petición AJAX a buscar_items (Cargando catálogo vacío)...', 'info');
        const urlItem = `${baseUrl}/index.php?ruta=ventas&accion=buscar_items&q=&id_cliente=1&cantidad=1`;
        
        try {
            const response = await fetch(urlItem, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const textData = await response.text(); 
            const jsonData = JSON.parse(textData);
            logTerminal(`JSON Items parseado con éxito. Encontró ${jsonData.data ? jsonData.data.length : 0} ítems.`, 'success');
        } catch (error) {
            logTerminal(`FATAL en buscar_items: ${error.message}. Puede que el JSON esté corrupto.`, 'error');
            return;
        }

        // PRUEBA 4: Aislamiento TomSelect en el Sandbox
        logTerminal('Prueba 4: Construyendo el TomSelect en el Sandbox HTML...', 'info');
        try {
            const sandboxSelect = document.getElementById('sandboxCliente');
            new TomSelect(sandboxSelect, {
                valueField: 'id',
                labelField: 'text',
                searchField: ['text'],
                loadThrottle: 300,
                load: function(query, callback) {
                    const termino = encodeURIComponent(query.trim());
                    const fetchUrl = `${baseUrl}/index.php?ruta=ventas&accion=buscar_clientes&q=${termino}`;
                    logTerminal(`[TomSelect Fetch] Solicitando: ${termino || '<vacío>'}`, 'warning');
                    
                    fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(json => {
                            const mapData = (json.data || []).map(item => ({
                                id: item.id,
                                text: `${item.nombre_completo} (${item.num_doc})`
                            }));
                            logTerminal(`[TomSelect Fetch] Insertando ${mapData.length} opciones en la UI.`, 'success');
                            callback(mapData);
                        }).catch(e => {
                            logTerminal(`[TomSelect Fetch] Error en el render: ${e.message}`, 'error');
                            callback();
                        });
                }
            });
            logTerminal(`TomSelect instanciado correctamente en la interfaz. ¡Prueba a escribir "suy" en el recuadro blanco de la izquierda!`, 'success');
        } catch (error) {
            logTerminal(`FATAL al instanciar TomSelect: ${error.message}`, 'error');
        }

        logTerminal('--- DIAGNÓSTICO FINALIZADO ---', 'info');
    }

    document.getElementById('btnRunAll').addEventListener('click', runDiagnostics);
</script>

</body>
</html>