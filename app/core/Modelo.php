<?php
declare(strict_types=1);

// Aseguramos que la conexión esté disponible
require_once BASE_PATH . '/app/config/Conexion.php';

class Modelo
{
    // Definimos la propiedad $db para que los hijos puedan usar $this->db->query(...)
    protected PDO $db;

    public function __construct()
    {
        // Al instanciar cualquier modelo, cargamos la conexión automáticamente
        $this->db = Conexion::get();
    }

    // Mantenemos tu método original por compatibilidad, aunque ya no es estrictamente necesario
    protected function db(): PDO
    {
        return $this->db;
    }
}