<?php
declare(strict_types=1);

class Router
{
    // Cambiar a true solo si necesitas depurar rutas fallidas
    private bool $debug = false;

    public function dispatch(): void
    {
        // Normalización de ruta: 'modulo/accion'
        $ruta = trim((string)($_GET['ruta'] ?? 'login/index'));
        if ($ruta === '') $ruta = 'login/index';

        // Seguridad básica: evitar Directory Traversal
        if (str_contains($ruta, '..')) {
            $this->render_not_found();
            return;
        }

        $partes = array_values(array_filter(explode('/', $ruta)));
        $modulo = $partes[0] ?? 'login';
        $accion = $partes[1] ?? 'index';

        if ($modulo === '') $modulo = 'login';
        if ($accion === '') $accion = 'index';

        // Convención: 'roles' -> 'RolesController'
        $controlador_clase_base = ucfirst($modulo) . 'Controller';
        
        // Mapeo de Alias
        $mapa_alias = [
            'LoginController'         => 'AuthController', 
            'ConfiguracionController' => 'EmpresaController',
            'ConfigController'        => 'EmpresaController',
            'Cajas_bancosController'  => 'CajasBancosController',
            'ItemsController'         => 'ItemController',
            'CategoriasController'    => 'CategoriaController',
            'AtributosController'     => 'AtributoController',
            'Cierre_contableController' => 'CierreContableController'
        ];

        $controlador_clase = $mapa_alias[$controlador_clase_base] ?? $controlador_clase_base;

        // --- MANEJO DE MÓDULOS ESPECÍFICOS ---

        if ($modulo === 'dashboard') {
            $modulo = 'reportes';
            $controlador_clase = 'ReportesController';
            $accion = $accion === 'index' ? 'dashboard' : $accion;
        }

        // --- MANEJO DE MÓDULOS ESPECÍFICOS ---

        // Rutas de INVENTARIO
        if ($modulo === 'inventario') {
            if ($accion === 'kardex') {
                $controlador_clase = 'InventarioKardexController';
                $accion = 'index';
            } elseif ($accion === 'envases') {
                $controlador_clase = 'EnvasesController';
                
                // CORRECCIÓN AQUÍ: Permite leer si la acción es "guardar", sino usa "index"
                $accion = $partes[2] ?? 'index'; 
            }
        }

        if ($modulo === 'produccion') {
            if ($accion === 'ordenes') {
                $controlador_clase = 'ProduccionOrdenesController';
            } else {
                $controlador_clase = 'ProduccionRecetasController';
                if ($accion === 'index') {
                    $accion = 'recetas';
                }
            }
        }

        // Rutas RRHH
        if ($modulo === 'rrhh') {
            if ($accion === 'config_rrhh') {
                $controlador_clase = 'ConfigRrhhController';
                $accion = $partes[2] ?? 'index';
                if ($accion === '') {
                    $accion = 'index';
                }
            }
        }

        // Rutas de CONTABILIDAD
        if ($modulo === 'contabilidad') {
            if ($accion === 'centros_costo') {
                $controlador_clase = 'CentroCostoController';
                $accion = 'index'; 
            } elseif ($accion === 'guardar_centro_costo') {
                $controlador_clase = 'CentroCostoController';
                $accion = 'guardar'; 
            } elseif ($accion === 'prorrateos') {
                $controlador_clase = 'ProrrateoController';
                $accion = 'index';
            } elseif ($accion === 'guardar_prorrateo') {
                $controlador_clase = 'ProrrateoController';
                $accion = 'guardar';
            } elseif ($accion === 'asientos') {
                $controlador_clase = 'AsientoController';
                $accion = 'index';
            } elseif ($accion === 'guardar_asiento') {
                $controlador_clase = 'AsientoController';
                $accion = 'guardar';
            } elseif ($accion === 'anular_asiento') {
                $controlador_clase = 'AsientoController';
                $accion = 'anular';
            } elseif ($accion === 'reportes') {
                $controlador_clase = 'ReporteContableController';
                $accion = 'index';
            }
        }

        // Rutas de CONCILIACIÓN BANCARIA
        if ($modulo === 'conciliacion') {
            $controlador_clase = 'ConciliacionController';
            if ($accion === '') {
                $accion = 'index';
            }
        }

        // Rutas de COSTOS
        if ($modulo === 'costos') {
            if ($accion === 'cierres') {
                // Si la url es costos/cierres, usa el nuevo controlador
                $controlador_clase = 'CierresController';
                $accion = 'index';
            } else {
                // Para configuracion o alertas, usa CostosController
                $controlador_clase = 'CostosController';
                if ($accion === 'index' || $accion === '') {
                    $accion = 'configuracion';
                }
            }
        }

        // Rutas de ITEMS
        if ($modulo === 'items') {
            if ($accion === 'perfil') {
                $controlador_clase = 'ItemPerfilController';
                $accion = $partes[2] ?? 'index';
                if ($accion === '') {
                    $accion = 'index';
                }
            } elseif ($accion === 'packs') {
                // 👇 NUEVA REGLA PARA PACKS Y COMBOS 👇
                $controlador_clase = 'PacksController';
                $accion = $partes[2] ?? 'index'; // Si la URL es items/packs/guardar, la acción será 'guardar'
                if ($accion === '') {
                    $accion = 'index';
                }
            }
        }

        // Búsqueda del archivo
        $archivo = $this->resolver_controlador_archivo($controlador_clase);
        
        if (!$archivo) {
            if ($this->debug) {
                die("<h3>Debug Router:</h3><p>No se encontró el archivo para <strong>$controlador_clase</strong>.</p>");
            }
            $this->render_not_found();
            return;
        }

        require_once $archivo;

        // Instancia y Ejecución
        if (!class_exists($controlador_clase)) {
            $this->render_server_error("La clase <strong>$controlador_clase</strong> no está definida en el archivo.");
            return;
        }

        $controlador = new $controlador_clase();

        if (!method_exists($controlador, $accion)) {
            if ($this->debug) {
                die("<h3>Debug Router:</h3><p>Método <strong>$accion()</strong> no encontrado en $controlador_clase.</p>");
            }
            $this->render_not_found(); 
            return;
        }

        // Armar auditoría automática para toda mutación HTTP (POST/PUT/PATCH/DELETE)
        if (class_exists('AuditLogger')) {
            AuditLogger::arm($ruta, $controlador_clase, $accion);
        }

        // Ejecutar acción
        $controlador->{$accion}();
    }

    private function resolver_controlador_archivo(string $clase): ?string
    {
        // Añadida la ruta para la nueva carpeta 'costos'
        $rutas_posibles = [
            BASE_PATH . '/app/controllers/' . $clase . '.php',
            BASE_PATH . '/app/controllers/inventario/' . $clase . '.php',
            BASE_PATH . '/app/controllers/items/' . $clase . '.php',
            BASE_PATH . '/app/controllers/configuracion/' . $clase . '.php',
            BASE_PATH . '/app/controllers/rrhh/' . $clase . '.php',
            BASE_PATH . '/app/controllers/contabilidad/' . $clase . '.php',
            BASE_PATH . '/app/controllers/produccion/' . $clase . '.php',
            BASE_PATH . '/app/controllers/costos/' . $clase . '.php', // <-- NUEVA RUTA AQUÍ
            BASE_PATH . '/app/controladores/' . $clase . '.php',
        ];

        foreach ($rutas_posibles as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }
        return null;
    }

    private function render_not_found(): void
    {
        http_response_code(404);
        if (is_file(BASE_PATH . '/app/views/404.php')) {
            require BASE_PATH . '/app/views/404.php';
        } else {
            echo "<h1>404 Not Found</h1><p>La página solicitada no existe.</p>";
        }
    }

    private function render_server_error(string $msg = ''): void
    {
        http_response_code(500);
        echo "<h1>500 Server Error</h1><p>$msg</p>";
    }
}