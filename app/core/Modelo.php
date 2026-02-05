<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/config/Conexion.php';

abstract class Modelo
{
    protected function get_pdo(): PDO
    {
        return Conexion::get();
    }
}