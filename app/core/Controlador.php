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
            die('Error: No se encontró la vista en: ' . $archivoVista);
        }

        $configEmpresa = $this->obtenerConfigEmpresa();

        if ($rutaVista === 'login') {
            extract(array_merge($datos, ['configEmpresa' => $configEmpresa]));
            require $archivoVista;
            return;
        }

        extract(array_merge($datos, ['configEmpresa' => $configEmpresa]));
        $vista = $archivoVista;

        require_once BASE_PATH . '/app/views/layout.php';
    }

    private function obtenerConfigEmpresa(): array
    {
        if (isset($_SESSION['config_empresa']) && is_array($_SESSION['config_empresa'])) {
            return $_SESSION['config_empresa'];
        }

        $empresaModelPath = BASE_PATH . '/app/models/EmpresaModel.php';
        if (!is_readable($empresaModelPath)) {
            return [];
        }

        require_once $empresaModelPath;

        try {
            $configEmpresa = (new EmpresaModel())->obtenerConfigActiva();
            $_SESSION['config_empresa'] = $configEmpresa;
            return $configEmpresa;
        } catch (Throwable $e) {
            return [];
        }
    }
}
