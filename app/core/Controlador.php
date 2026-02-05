<?php
declare(strict_types=1);

abstract class Controlador
{
    protected function render(string $vista, array $data = []): void
    {
        if (str_contains($vista, '..')) {
            throw new InvalidArgumentException('Vista inválida.');
        }

        $vista_normalizada = trim($vista, '/');
        $vista_archivo = BASE_PATH . '/app/views/' . $vista_normalizada . '.php';

        if (!is_file($vista_archivo)) {
            throw new RuntimeException('Vista no encontrada: ' . $vista_normalizada);
        }

        extract($data, EXTR_SKIP);

        $layout_archivo = BASE_PATH . '/app/views/layout.php';
        if (is_file($layout_archivo)) {
            $contenido_vista = $vista_archivo;
            require $layout_archivo;
            return;
        }

        require $vista_archivo;
    }
}
