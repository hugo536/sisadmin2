<?php

declare(strict_types=1);

class TesoreriaCuentaModel extends Modelo
{
    /**
     * Lista las cuentas activas para su uso en formularios de cobro/pago.
     */
    public function listarActivas(): array
    {
        $sql = 'SELECT c.id,
                       c.codigo,
                       c.nombre,
                       c.tipo,
                       c.moneda,
                       c.id_cuenta_contable,
                       (COALESCE(c.saldo_inicial, 0) + COALESCE(mov.saldo_delta, 0)) AS saldo,
                       (COALESCE(c.saldo_inicial, 0) + COALESCE(mov.saldo_delta, 0)) AS saldo_actual
                FROM tesoreria_cuentas c
                LEFT JOIN (
                    SELECT id_cuenta,
                           SUM(CASE WHEN estado = "CONFIRMADO" AND tipo = "COBRO" THEN monto
                                    WHEN estado = "CONFIRMADO" AND tipo = "PAGO" THEN -monto
                                    ELSE 0 END) AS saldo_delta
                    FROM tesoreria_movimientos
                    WHERE deleted_at IS NULL
                    GROUP BY id_cuenta
                ) mov ON mov.id_cuenta = c.id
                WHERE estado = 1 
                  AND c.deleted_at IS NULL
                ORDER BY c.tipo ASC, c.nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lista las cuentas para el panel de gestión de tesorería.
     */
    public function listarGestion(): array
    {
        $sql = 'SELECT c.*, cb.nombre AS banco_nombre,
                       COALESCE(mov.total_movimientos, 0) AS total_movimientos,
                       (COALESCE(c.saldo_inicial, 0) + COALESCE(mov.saldo_delta, 0)) AS saldo_actual
                FROM tesoreria_cuentas c
                LEFT JOIN configuracion_cajas_bancos cb ON cb.id = c.config_banco_id
                LEFT JOIN (
                    SELECT id_cuenta,
                           COUNT(*) AS total_movimientos,
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
     * Guarda o actualiza una cuenta de tesorería.
     */
    public function guardar(array $payload, int $userId): int
    {
        $id = (int) ($payload['id'] ?? 0);

        $data = [
            'codigo' => strtoupper(trim((string) ($payload['codigo'] ?? ''))),
            'nombre' => trim((string) ($payload['nombre'] ?? '')),
            'tipo' => strtoupper(trim((string) ($payload['tipo'] ?? 'CAJA'))),
            'moneda' => strtoupper(trim((string) ($payload['moneda'] ?? 'PEN'))),
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
        if (!in_array($data['tipo'], ['CAJA', 'BANCO', 'BILLETERA'], true)) throw new RuntimeException('Tipo de cuenta inválido.');
        if (!in_array($data['moneda'], ['PEN', 'USD'], true)) throw new RuntimeException('Moneda inválida.');

        $cuentaActual = null;
        if ($id > 0) {
            $cuentaActual = $this->obtenerPorId($id);
            if (!$cuentaActual) {
                throw new RuntimeException('La cuenta de tesorería no existe o fue eliminada.');
            }

            // Campos bloqueados en edición
            $data['codigo'] = (string) ($cuentaActual['codigo'] ?? '');
            $data['config_banco_id'] = (int) ($cuentaActual['config_banco_id'] ?? 0);
            $data['titular'] = (string) ($cuentaActual['titular'] ?? '');
            $data['tipo_cuenta'] = (string) ($cuentaActual['tipo_cuenta'] ?? '');
            $data['numero_cuenta'] = (string) ($cuentaActual['numero_cuenta'] ?? '');
            $data['cci'] = (string) ($cuentaActual['cci'] ?? '');
            $data['saldo_inicial'] = round((float) ($cuentaActual['saldo_inicial'] ?? 0), 4);
            $data['fecha_saldo_inicial'] = trim((string) ($cuentaActual['fecha_saldo_inicial'] ?? ''));
        } elseif ($data['codigo'] === '') {
            // Autogenerar código si es necesario
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
            if ($id > 0) {
                // --- UPDATE ---
                $sql = 'UPDATE tesoreria_cuentas SET
                        codigo = :codigo, nombre = :nombre, tipo = :tipo, moneda = :moneda,
                        config_banco_id = :config_banco_id,
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
                    (codigo, nombre, tipo, moneda, config_banco_id, titular, tipo_cuenta, numero_cuenta, cci,
                     permite_cobros, permite_pagos, saldo_inicial, fecha_saldo_inicial, principal, observaciones,
                     estado, created_by, updated_by, created_at, updated_at)
                VALUES
                    (:codigo, :nombre, :tipo, :moneda, :config_banco_id, :titular, :tipo_cuenta, :numero_cuenta, :cci,
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

    public function vincularCuentaContable(int $idCuentaTesoreria, int $idCuentaContable, int $userId): void
    {
        if ($idCuentaTesoreria <= 0 || $idCuentaContable <= 0) {
            throw new RuntimeException('Debe seleccionar una cuenta de tesorería y una cuenta contable válidas.');
        }

        $db = $this->db();

        $stmtTes = $db->prepare('SELECT id FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmtTes->execute(['id' => $idCuentaTesoreria]);
        if (!(bool)$stmtTes->fetchColumn()) {
            throw new RuntimeException('La cuenta de tesorería seleccionada no existe.');
        }

        $stmtConta = $db->prepare('SELECT id FROM conta_cuentas WHERE id = :id AND estado = 1 AND permite_movimiento = 1 AND deleted_at IS NULL LIMIT 1');
        $stmtConta->execute(['id' => $idCuentaContable]);
        if (!(bool)$stmtConta->fetchColumn()) {
            throw new RuntimeException('La cuenta contable debe estar activa y permitir movimiento.');
        }

        $stmt = $db->prepare('UPDATE tesoreria_cuentas
                              SET id_cuenta_contable = :id_cuenta_contable,
                                  updated_by = :user,
                                  updated_at = NOW()
                              WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id_cuenta_contable' => $idCuentaContable,
            'user' => $userId,
            'id' => $idCuentaTesoreria,
        ]);
    }

    public function eliminar(int $id, int $userId): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Cuenta inválida para eliminar.');
        }

        $stmtExiste = $this->db()->prepare('SELECT id FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmtExiste->execute(['id' => $id]);
        if (!$stmtExiste->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('La cuenta no existe o ya fue eliminada.');
        }

        $stmtMov = $this->db()->prepare('SELECT COUNT(*) FROM tesoreria_movimientos WHERE id_cuenta = :id_cuenta AND deleted_at IS NULL');
        $stmtMov->execute(['id_cuenta' => $id]);
        $totalMov = (int) $stmtMov->fetchColumn();

        if ($totalMov > 0) {
            throw new RuntimeException('No se puede eliminar la cuenta porque ya tiene movimientos registrados.');
        }

        $stmt = $this->db()->prepare('UPDATE tesoreria_cuentas
                                      SET deleted_at = NOW(),
                                          deleted_by = :user,
                                          updated_at = NOW(),
                                          updated_by = :user
                                      WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'user' => $userId,
        ]);
    }
}
