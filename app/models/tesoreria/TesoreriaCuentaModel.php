<?php

declare(strict_types=1);

class TesoreriaCuentaModel extends Modelo
{
    /**
     * Lista las cuentas activas para su uso en formularios de cobro/pago.
     * Incluye el id_cuenta_contable para automatizar el asiento.
     */
    public function listarActivas(): array
    {
        $sql = 'SELECT id, codigo, nombre, tipo, moneda, id_cuenta_contable
                FROM tesoreria_cuentas
                WHERE estado = 1 
                  AND deleted_at IS NULL
                ORDER BY tipo ASC, nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lista las cuentas para el panel de gestión de tesorería.
     */
    public function listarGestion(): array
    {
        $sql = 'SELECT c.*, cb.nombre AS banco_nombre,
                       cc.nombre AS cuenta_contable_nombre,
                       cc.codigo AS cuenta_contable_codigo,
                       (COALESCE(c.saldo_inicial, 0) + COALESCE(mov.saldo_delta, 0)) AS saldo_actual
                FROM tesoreria_cuentas c
                LEFT JOIN configuracion_cajas_bancos cb ON cb.id = c.config_banco_id
                LEFT JOIN conta_cuentas cc ON cc.id = c.id_cuenta_contable
                LEFT JOIN (
                    SELECT id_cuenta,
                           SUM(CASE WHEN estado = "CONFIRMADO" AND tipo = "COBRO" THEN monto
                                    WHEN estado = "CONFIRMADO" AND tipo = "PAGO" THEN -monto
                                    ELSE 0 END) AS saldo_delta
                    FROM tesoreria_movimientos
                    WHERE deleted_at IS NULL
                    GROUP BY id_cuenta
                ) mov ON mov.id_cuenta = c.id
                WHERE c.deleted_at IS NULL
                ORDER BY c.principal DESC, c.estado DESC, c.nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarBancosConfigurados(): array
    {
        $sql = 'SELECT id, nombre, tipo
                FROM configuracion_cajas_bancos
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Guarda o actualiza una cuenta de tesorería vinculándola al Plan Contable.
     */
    public function guardar(array $payload, int $userId): int
    {
        $id = (int) ($payload['id'] ?? 0);

        $data = [
            'codigo' => strtoupper(trim((string) ($payload['codigo'] ?? ''))),
            'nombre' => trim((string) ($payload['nombre'] ?? '')),
            'tipo' => strtoupper(trim((string) ($payload['tipo'] ?? 'CAJA'))),
            'moneda' => strtoupper(trim((string) ($payload['moneda'] ?? 'PEN'))),
            'id_cuenta_contable' => (int) ($payload['id_cuenta_contable'] ?? 0), // Campo clave para la vinculación
            'config_banco_id' => (int) ($payload['config_banco_id'] ?? 0),
            'titular' => trim((string) ($payload['titular'] ?? '')),
            'tipo_cuenta' => trim((string) ($payload['tipo_cuenta'] ?? '')),
            'numero_cuenta' => trim((string) ($payload['numero_cuenta'] ?? '')),
            'cci' => trim((string) ($payload['cci'] ?? '')),
            'permite_cobros' => (int) ($payload['permite_cobros'] ?? 0),
            'permite_pagos' => (int) ($payload['permite_pagos'] ?? 0),
            'saldo_inicial' => round((float) ($payload['saldo_inicial'] ?? 0), 4),
            'fecha_saldo_inicial' => trim((string) ($payload['fecha_saldo_inicial'] ?? '')),
            'principal' => (int) ($payload['principal'] ?? 0),
            'observaciones' => trim((string) ($payload['observaciones'] ?? '')),
            'estado' => (int) ($payload['estado'] ?? 1),
        ];

        // --- VALIDACIONES ---
        if ($data['nombre'] === '') throw new RuntimeException('El nombre es obligatorio.');
        if ($data['id_cuenta_contable'] <= 0) throw new RuntimeException('Debe vincular una cuenta del Plan Contable.');
        if (!in_array($data['tipo'], ['CAJA', 'BANCO', 'BILLETERA'], true)) throw new RuntimeException('Tipo de cuenta inválido.');
        if (!in_array($data['moneda'], ['PEN', 'USD'], true)) throw new RuntimeException('Moneda inválida.');

        // Autogenerar código si es necesario
        if ($data['codigo'] === '') {
            $data['codigo'] = $this->generarCodigoDisponible($data['tipo']);
        }

        // Limpiar datos según el tipo de cuenta
        $data['config_banco_id'] = $data['config_banco_id'] > 0 ? $data['config_banco_id'] : null;
        $data['fecha_saldo_inicial'] = $data['fecha_saldo_inicial'] !== '' ? $data['fecha_saldo_inicial'] : null;
        
        if ($data['tipo'] === 'CAJA') {
            $data['config_banco_id'] = null;
            $data['titular'] = null;
            $data['tipo_cuenta'] = null;
            $data['numero_cuenta'] = null;
            $data['cci'] = null;
        }

        $db = $this->db();
        
        // Verificar código duplicado
        $stmtExiste = $db->prepare('SELECT id FROM tesoreria_cuentas WHERE codigo = :codigo AND deleted_at IS NULL AND id <> :id LIMIT 1');
        $stmtExiste->execute(['codigo' => $data['codigo'], 'id' => $id]);
        if ($stmtExiste->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Ya existe una cuenta con ese código.');
        }

        $db->beginTransaction();

        try {
            // Manejar lógica de cuenta principal por moneda
            if ($data['principal'] === 1 && $data['id_cuenta_contable'] > 0) {
                // Si esta cuenta es la PRINCIPAL de Tesorería, 
                // actualizamos automáticamente el parámetro global del Plan Contable.
                $paramModel = new ContaParametrosModel();
                $paramModel->guardar('CTA_CAJA_DEFECTO', (int)$data['id_cuenta_contable'], $userId);
            }

            $db->commit();
            return $id > 0 ? $id : $idCreado;

            if ($id > 0) {
                // --- UPDATE ---
                $sql = 'UPDATE tesoreria_cuentas SET
                        codigo = :codigo, nombre = :nombre, tipo = :tipo, moneda = :moneda,
                        id_cuenta_contable = :id_cuenta_contable, config_banco_id = :config_banco_id,
                        titular = :titular, tipo_cuenta = :tipo_cuenta, numero_cuenta = :numero_cuenta,
                        cci = :cci, permite_cobros = :permite_cobros, permite_pagos = :permite_pagos,
                        saldo_inicial = :saldo_inicial, fecha_saldo_inicial = :fecha_saldo_inicial,
                        principal = :principal, observaciones = :observaciones, estado = :estado,
                        updated_by = :user, updated_at = NOW()
                    WHERE id = :id AND deleted_at IS NULL';

                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($data, ['id' => $id, 'user' => $userId]));
                $db->commit();
                return $id;
            }

            // --- INSERT ---
            $sql = 'INSERT INTO tesoreria_cuentas
                    (codigo, nombre, tipo, moneda, id_cuenta_contable, config_banco_id, titular, tipo_cuenta, numero_cuenta, cci,
                     permite_cobros, permite_pagos, saldo_inicial, fecha_saldo_inicial, principal, observaciones,
                     estado, created_by, updated_by, created_at, updated_at)
                VALUES
                    (:codigo, :nombre, :tipo, :moneda, :id_cuenta_contable, :config_banco_id, :titular, :tipo_cuenta, :numero_cuenta, :cci,
                     :permite_cobros, :permite_pagos, :saldo_inicial, :fecha_saldo_inicial, :principal, :observaciones,
                     :estado, :created_by, :updated_by, NOW(), NOW())';

            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge($data, ['created_by' => $userId, 'updated_by' => $userId]));

            $idCreado = (int) $db->lastInsertId();
            $db->commit();
            return $idCreado;

        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function generarCodigoDisponible(string $tipo): string
    {
        $prefijo = $tipo === 'CAJA' ? 'CJ-' : ($tipo === 'BILLETERA' ? 'WL-' : 'BN-');
        $stmt = $this->db()->prepare('SELECT codigo FROM tesoreria_cuentas WHERE codigo LIKE :prefijo AND deleted_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute(['prefijo' => $prefijo . '%']);
        $ultimo = (string) ($stmt->fetchColumn() ?: '');

        $correlativo = 1;
        if ($ultimo !== '' && preg_match('/(\d+)$/', $ultimo, $m)) {
            $correlativo = ((int) $m[1]) + 1;
        }

        while (true) {
            $codigo = $prefijo . str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
            $stmtExiste = $this->db()->prepare('SELECT id FROM tesoreria_cuentas WHERE codigo = :codigo AND deleted_at IS NULL LIMIT 1');
            $stmtExiste->execute(['codigo' => $codigo]);
            if (!$stmtExiste->fetch(PDO::FETCH_ASSOC)) return $codigo;
            $correlativo++;
        }
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT * FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}