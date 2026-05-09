<?php
declare(strict_types=1);

class TercerosClientesModel extends Modelo
{
    /**
     * Guarda o actualiza la información de crédito y pago de un cliente.
     */
    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $sql = "INSERT INTO terceros_clientes (id_tercero, dias_credito, limite_credito, condicion_pago, updated_by)
                VALUES (:id_tercero, :dias_credito, :limite_credito, :condicion_pago, :updated_by)
                ON DUPLICATE KEY UPDATE
                    dias_credito = VALUES(dias_credito),
                    limite_credito = VALUES(limite_credito),
                    condicion_pago = VALUES(condicion_pago),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()";

        $this->db()->prepare($sql)->execute([
            'id_tercero'      => $idTercero,
            'dias_credito'    => (int)($data['cliente_dias_credito'] ?? 0),
            'limite_credito'  => (float)($data['cliente_limite_credito'] ?? 0),
            'condicion_pago'  => $data['cliente_condicion_pago'] ?? null,
            'updated_by'      => $userId
        ]);
    }

    /**
     * Suma un monto al saldo a favor actual del cliente.
     * Se utiliza cuando se revierte un pedido que ya tenía pagos registrados en caja.
     *
     * @param int $idTercero El ID del cliente (tercero).
     * @param float $monto El dinero que se convertirá en saldo a favor.
     * @return bool True si se actualizó correctamente, False en caso contrario.
     */
    public function sumarSaldoFavor(int $idTercero, float $monto): bool
    {
        // Validación de seguridad: no podemos sumar montos negativos o ceros
        if ($monto <= 0) {
            return false;
        }

        // COALESCE(saldo_favor, 0) asegura que si el campo es NULL, se trate como 0.
        $sql = "UPDATE terceros_clientes 
                SET saldo_favor = COALESCE(saldo_favor, 0) + :monto,
                    updated_at = NOW()
                WHERE id_tercero = :id_tercero";

        $stmt = $this->db()->prepare($sql);
        
        return $stmt->execute([
            'monto'      => $monto,
            'id_tercero' => $idTercero
        ]);
    }

    /**
     * Obtiene el saldo a favor actual de un cliente.
     */
    public function obtenerSaldoFavor(int $idTercero): float
    {
        $sql = "SELECT COALESCE(saldo_favor, 0) 
                FROM terceros_clientes 
                WHERE id_tercero = :id";
                
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idTercero]);
        
        return (float) ($stmt->fetchColumn() ?: 0.0);
    }
}