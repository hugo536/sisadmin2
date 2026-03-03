<?php

declare(strict_types=1);

class TesoreriaCuentaModel extends Modelo
{
    public function listarActivas(): array
    {
        $sql = 'SELECT id, codigo, nombre, tipo, moneda
                FROM tesoreria_cuentas
                WHERE estado = 1 
                  AND deleted_at IS NULL
                ORDER BY tipo ASC, nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarGestion(): array
    {
        $sql = 'SELECT c.*, cb.nombre AS banco_nombre
                FROM tesoreria_cuentas c
                LEFT JOIN configuracion_cajas_bancos cb ON cb.id = c.config_banco_id
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

        if ($data['nombre'] === '') {
            throw new RuntimeException('El nombre es obligatorio.');
        }

        if (!in_array($data['tipo'], ['CAJA', 'BANCO', 'BILLETERA'], true)) {
            throw new RuntimeException('Tipo de cuenta inválido.');
        }

        if (!in_array($data['moneda'], ['PEN', 'USD'], true)) {
            throw new RuntimeException('Moneda inválida.');
        }

        if ($data['codigo'] === '') {
            $data['codigo'] = $this->generarCodigoDisponible($data['tipo']);
        }

        $data['config_banco_id'] = $data['config_banco_id'] > 0 ? $data['config_banco_id'] : null;
        $data['fecha_saldo_inicial'] = $data['fecha_saldo_inicial'] !== '' ? $data['fecha_saldo_inicial'] : null;
        $data['titular'] = $data['titular'] !== '' ? $data['titular'] : null;
        $data['tipo_cuenta'] = $data['tipo_cuenta'] !== '' ? $data['tipo_cuenta'] : null;
        $data['numero_cuenta'] = $data['numero_cuenta'] !== '' ? $data['numero_cuenta'] : null;
        $data['cci'] = $data['cci'] !== '' ? $data['cci'] : null;
        $data['observaciones'] = $data['observaciones'] !== '' ? $data['observaciones'] : null;

        if ($data['tipo'] === 'CAJA') {
            $data['config_banco_id'] = null;
            $data['titular'] = null;
            $data['tipo_cuenta'] = null;
            $data['numero_cuenta'] = null;
            $data['cci'] = null;
        }

        if ($data['tipo'] === 'BILLETERA') {
            $digits = preg_replace('/\D+/', '', (string) ($data['numero_cuenta'] ?? ''));
            $data['numero_cuenta'] = $digits !== '' ? substr($digits, 0, 9) : null;
            $data['cci'] = null;
        }

        $db = $this->db();
        $stmtExiste = $db->prepare('SELECT id FROM tesoreria_cuentas WHERE codigo = :codigo AND deleted_at IS NULL AND id <> :id LIMIT 1');
        $stmtExiste->execute(['codigo' => $data['codigo'], 'id' => $id]);
        if ($stmtExiste->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Ya existe una cuenta con ese código.');
        }

        if ($id > 0) {
            $sql = 'UPDATE tesoreria_cuentas SET
                        codigo = :codigo,
                        nombre = :nombre,
                        tipo = :tipo,
                        moneda = :moneda,
                        config_banco_id = :config_banco_id,
                        titular = :titular,
                        tipo_cuenta = :tipo_cuenta,
                        numero_cuenta = :numero_cuenta,
                        cci = :cci,
                        permite_cobros = :permite_cobros,
                        permite_pagos = :permite_pagos,
                        saldo_inicial = :saldo_inicial,
                        fecha_saldo_inicial = :fecha_saldo_inicial,
                        principal = :principal,
                        observaciones = :observaciones,
                        estado = :estado,
                        updated_by = :user,
                        updated_at = NOW()
                    WHERE id = :id AND deleted_at IS NULL';

            $stmt = $db->prepare($sql);
            $stmt->execute(array_merge($data, ['id' => $id, 'user' => $userId]));

            return $id;
        }

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

        return (int) $db->lastInsertId();
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
            if (!$stmtExiste->fetch(PDO::FETCH_ASSOC)) {
                return $codigo;
            }
            $correlativo++;
        }
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT * FROM tesoreria_cuentas 
                WHERE id = :id 
                  AND deleted_at IS NULL 
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
