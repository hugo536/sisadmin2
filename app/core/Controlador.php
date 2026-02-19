<?php
declare(strict_types=1);

class Controlador
{
    // =========================================================================
    // 1. SOLUCIÓN AL ERROR "Cannot call constructor"
    // Definimos un constructor, aunque esté vacío, para que los hijos
    // puedan llamar a parent::__construct() sin fallar.
    // =========================================================================
    public function __construct()
    {
        // Futuro: Aquí puedes inicializar validaciones de sesión globales, etc.
    }

    // =========================================================================
    // 2. SOLUCIÓN DE COMPATIBILIDAD (Alias)
    // Tus controladores usan $this->vista(), así que creamos este puente.
    // =========================================================================
    protected function vista(string $rutaVista, array $datos = []): void
    {
        $this->render($rutaVista, $datos);
    }

    /**
     * Renderiza una vista dentro del layout principal.
     * @param string $rutaVista Nombre del archivo en views (sin .php)
     * @param array $datos Datos a pasar a la vista (['usuarios' => $...])
     */
    protected function render(string $rutaVista, array $datos = []): void
    {
        // Define la ruta base si no está definida (por seguridad)
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2));
        }

        $archivoVista = BASE_PATH . '/app/views/' . $rutaVista . '.php';

        if (!is_readable($archivoVista)) {
            // Intenta buscar en carpeta 'vistas' si 'views' falla (por si acaso cambiaste nombres)
            $archivoVistaAlternativo = BASE_PATH . '/app/vistas/' . $rutaVista . '.php';
            if (is_readable($archivoVistaAlternativo)) {
                $archivoVista = $archivoVistaAlternativo;
            } else {
                die('Error Crítico: No se encontró la vista en: ' . $archivoVista);
            }
        }

        $configEmpresa = $this->obtenerConfigEmpresa();

        // Si es login, no carga el layout completo
        if ($rutaVista === 'login' || $rutaVista === 'auth/login') {
            extract(array_merge($datos, ['configEmpresa' => $configEmpresa]));
            require $archivoVista;
            return;
        }

        // Extrae los datos para que sean variables en la vista ($titulo, $usuarios, etc.)
        extract(array_merge($datos, ['configEmpresa' => $configEmpresa]));
        
        // Variable $vista usada por el layout para incluir el contenido
        $vista = $archivoVista;

        // Carga el Layout Principal
        // Verifica si existe layout.php, si no, busca header/footer por separado
        $layoutPath = BASE_PATH . '/app/views/layout.php';
        
        if (file_exists($layoutPath)) {
            require_once $layoutPath;
        } else {
            // Soporte legacy si usas header.php y footer.php separados
            if (file_exists(BASE_PATH . '/app/views/header.php')) require_once BASE_PATH . '/app/views/header.php';
            require $archivoVista;
            if (file_exists(BASE_PATH . '/app/views/footer.php')) require_once BASE_PATH . '/app/views/footer.php';
        }
    }

    private function obtenerConfigEmpresa(): array
    {
        if (isset($_SESSION['config_empresa']) && is_array($_SESSION['config_empresa'])) {
            return $_SESSION['config_empresa'];
        }

        // Intenta cargar el modelo de empresa si existe
        $empresaModelPath = BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
        if (!is_readable($empresaModelPath)) {
            $empresaModelPath = BASE_PATH . '/app/models/EmpresaModel.php';
        }
        
        if (!is_readable($empresaModelPath)) {
            return [];
        }

        require_once $empresaModelPath;

        try {
            if (class_exists('EmpresaModel')) {
                $configEmpresa = (new EmpresaModel())->obtenerConfigActiva();
                $_SESSION['config_empresa'] = $configEmpresa;
                return $configEmpresa;
            }
            return [];
        } catch (Throwable $e) {
            return [];
        }
    }
}