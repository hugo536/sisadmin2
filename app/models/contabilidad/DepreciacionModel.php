<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/models/contabilidad/ActivoFijoModel.php';

class DepreciacionModel extends Modelo
{
    public function ejecutar(string $periodo, int $userId): int
    {
        return (new ActivoFijoModel())->depreciarMensual($periodo, $userId);
    }
}
