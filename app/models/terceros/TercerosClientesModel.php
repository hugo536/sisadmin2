<?php
declare(strict_types=1);

class TercerosClientesModel extends Modelo
{
    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $sql = "INSERT INTO terceros_clientes (id_tercero, dias_credito, limite_credito, condicion_pago, ruta_reparto, updated_by)
                VALUES (:id_tercero, :dias_credito, :limite_credito, :condicion_pago, :ruta_reparto, :updated_by)
                ON DUPLICATE KEY UPDATE
                    dias_credito = VALUES(dias_credito),
                    limite_credito = VALUES(limite_credito),
                    condicion_pago = VALUES(condicion_pago),
                    ruta_reparto = VALUES(ruta_reparto),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()";

        $this->db()->prepare($sql)->execute([
            'id_tercero'      => $idTercero,
            'dias_credito'    => (int)($data['cliente_dias_credito'] ?? 0),
            'limite_credito'  => (float)($data['cliente_limite_credito'] ?? 0),
            'condicion_pago'  => $data['cliente_condicion_pago'] ?? null,
            'ruta_reparto'    => $data['cliente_ruta_reparto'] ?? null,
            'updated_by'      => $userId
        ]);
    }
}
