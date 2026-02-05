<?php
declare(strict_types=1);

class Controlador
{
    /**
     * Renderiza una vista dentro del layout principal.
     * * @param string $rutaVista Nombre del archivo en views (sin .php)
     * @param array $datos Datos a pasar a la vista (['usuarios' => $...])
     */
    protected function render(string $rutaVista, array $datos = []): void
    {
        $archivoVista = BASE_PATH . '/app/views/' . $rutaVista . '.php';

        if (!is_readable($archivoVista)) {
            die("Error: No se encontró la vista en: " . $archivoVista);
        }

        if ($rutaVista === 'login') {
            $configEmpresa = null;
            $configModelPath = BASE_PATH . '/app/models/ConfigModel.php';
            if (is_readable($configModelPath)) {
                require_once $configModelPath;
                try {
                    $configEmpresa = (new ConfigModel())->obtener_config_activa();
                } catch (Throwable $e) {
                    $configEmpresa = null;
                }
            }

            extract(array_merge($datos, ['configEmpresa' => $configEmpresa]));
            require $archivoVista;
            return;
        }

        // 1. Extraer los datos para que sean variables ($usuarios, $mensaje, etc.)
        extract($datos);

        // Pasamos la ruta de la vista al layout usando la variable $vista
        $vista = $archivoVista;

        // 4. Cargar el Layout Principal (que a su vez cargará $vista)
        require_once BASE_PATH . '/app/views/layout.php';
    }
}
