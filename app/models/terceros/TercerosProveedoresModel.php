<?php
declare(strict_types=1);

class TercerosProveedoresModel extends Modelo
{
    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $sql = "INSERT INTO terceros_proveedores (id_tercero, dias_credito, condicion_pago, forma_pago, updated_by)
                VALUES (:id_tercero, :dias_credito, :condicion_pago, :forma_pago, :updated_by)
                ON DUPLICATE KEY UPDATE
                    dias_credito = VALUES(dias_credito),
                    condicion_pago = VALUES(condicion_pago),
                    forma_pago = VALUES(forma_pago),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()";

        $this->db()->prepare($sql)->execute([
            'id_tercero'      => $idTercero,
            'dias_credito'    => (int)($data['proveedor_dias_credito'] ?? 0),
            'condicion_pago'  => $data['proveedor_condicion_pago'] ?? null,
            'forma_pago'      => $data['proveedor_forma_pago'] ?? null,
            'updated_by'      => $userId
        ]);
    }
}
