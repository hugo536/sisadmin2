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
        // 1. Extraer los datos para que sean variables ($usuarios, $mensaje, etc.)
        extract($datos);

        // 2. Definir la ruta real del archivo de la vista
        $archivoVista = BASE_PATH . '/app/views/' . $rutaVista . '.php';

        // 3. Verificar si existe la vista
        if (is_readable($archivoVista)) {
            // Pasamos la ruta de la vista al layout usando la variable $vista
            $vista = $archivoVista;
            
            // 4. Cargar el Layout Principal (que a su vez cargará $vista)
            require_once BASE_PATH . '/app/views/layout.php';
        } else {
            // Error amigable si no encuentra el archivo
            die("Error: No se encontró la vista en: " . $archivoVista);
        }
    }
}