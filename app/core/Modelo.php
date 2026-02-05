<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/config/Conexion.php';

class Modelo
{
    protected function db(): PDO
    {
        return Conexion::get();
    }
}
