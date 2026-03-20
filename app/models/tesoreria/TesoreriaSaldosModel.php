<?php

declare(strict_types=1);

class TesoreriaSaldosModel extends Modelo
{
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
}