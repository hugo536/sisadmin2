<?php

declare(strict_types=1);

class TesoreriaSaldosModel extends Modelo
{
    public function obtenerSaldoInicialPorTercero(string $tipo, int $idTercero): ?array
    {
        $tipoNormalizado = strtoupper(trim($tipo));
        if (!in_array($tipoNormalizado, ['CLIENTE', 'PROVEEDOR'], true) || $idTercero <= 0) {
            return null;
        }

        $tabla = $tipoNormalizado === 'CLIENTE' ? 'tesoreria_cxc' : 'tesoreria_cxp';
        $columnaTercero = $tipoNormalizado === 'CLIENTE' ? 'id_cliente' : 'id_proveedor';

        $sql = "SELECT id, monto_total, monto_pagado, saldo
                FROM {$tabla}
                WHERE {$columnaTercero} = :id_tercero
                  AND origen = 'MIGRACION'
                  AND deleted_at IS NULL
                ORDER BY id DESC
                LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_tercero' => $idTercero]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Registra un saldo inicial a favor (Cuenta por Cobrar - Cliente)
     */
    public function crearSaldoCxc(array $data, int $userId): int
    {
        $db = Conexion::get();
        
        // Nota: Se envía NULL a id_documento_venta y se marca el origen como MIGRACION
        $sql = 'INSERT INTO tesoreria_cxc
            (id_cliente, id_documento_venta, origen, documento_referencia, fecha_emision, fecha_vencimiento, moneda, monto_total, monto_pagado, saldo, estado, observaciones, created_by, updated_by, created_at, updated_at)
            VALUES
            (:id_cliente, NULL, "MIGRACION", :doc, :fecha_emision, :fecha_vencimiento, :moneda, :monto_total, 0, :saldo, :estado, :observaciones, :created_by, :updated_by, NOW(), NOW())';
            
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_cliente'        => $data['id_tercero'],
            'doc'               => $data['documento_referencia'],
            'fecha_emision'     => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'moneda'            => $data['moneda'],
            'monto_total'       => $data['monto_total'],
            'saldo'             => $data['monto_total'], // Al nacer, el saldo es igual al monto total
            'estado'            => $data['estado'],
            'observaciones'     => $data['observaciones'],
            'created_by'        => $userId,
            'updated_by'        => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Registra un saldo inicial en contra (Cuenta por Pagar - Proveedor)
     */
    public function crearSaldoCxp(array $data, int $userId): int
    {
        $db = Conexion::get();
        
        // Nota: Se envía NULL a id_recepcion y se marca el origen como MIGRACION
        $sql = 'INSERT INTO tesoreria_cxp
            (id_proveedor, id_recepcion, origen, documento_referencia, fecha_emision, fecha_vencimiento, moneda, monto_total, monto_pagado, saldo, estado, observaciones, created_by, updated_by, created_at, updated_at)
            VALUES
            (:id_proveedor, NULL, "MIGRACION", :doc, :fecha_emision, :fecha_vencimiento, :moneda, :monto_total, 0, :saldo, :estado, :observaciones, :created_by, :updated_by, NOW(), NOW())';
            
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_proveedor'      => $data['id_tercero'],
            'doc'               => $data['documento_referencia'],
            'fecha_emision'     => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'moneda'            => $data['moneda'],
            'monto_total'       => $data['monto_total'],
            'saldo'             => $data['monto_total'], // Al nacer, el saldo es igual al monto total
            'estado'            => $data['estado'],
            'observaciones'     => $data['observaciones'],
            'created_by'        => $userId,
            'updated_by'        => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    public function actualizarSaldoCxc(int $idCuenta, array $data, int $userId): void
    {
        $sql = 'UPDATE tesoreria_cxc
                SET documento_referencia = :doc,
                    fecha_emision = :fecha_emision,
                    fecha_vencimiento = :fecha_vencimiento,
                    moneda = :moneda,
                    monto_total = :monto_total,
                    saldo = :saldo,
                    estado = :estado,
                    observaciones = :observaciones,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $this->db()->prepare($sql)->execute([
            'id' => $idCuenta,
            'doc' => $data['documento_referencia'],
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'moneda' => $data['moneda'],
            'monto_total' => $data['monto_total'],
            'saldo' => $data['saldo'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
            'updated_by' => $userId,
        ]);
    }

    public function actualizarSaldoCxp(int $idCuenta, array $data, int $userId): void
    {
        $sql = 'UPDATE tesoreria_cxp
                SET documento_referencia = :doc,
                    fecha_emision = :fecha_emision,
                    fecha_vencimiento = :fecha_vencimiento,
                    moneda = :moneda,
                    monto_total = :monto_total,
                    saldo = :saldo,
                    estado = :estado,
                    observaciones = :observaciones,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $this->db()->prepare($sql)->execute([
            'id' => $idCuenta,
            'doc' => $data['documento_referencia'],
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'moneda' => $data['moneda'],
            'monto_total' => $data['monto_total'],
            'saldo' => $data['saldo'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
            'updated_by' => $userId,
        ]);
    }
}
