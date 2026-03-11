<?php

declare(strict_types=1);

class ConciliacionBancariaModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT c.*, t.nombre AS cuenta_nombre,
                       (SELECT COALESCE(SUM(d.monto),0) FROM tesoreria_conciliaciones_detalle d WHERE d.id_conciliacion = c.id AND d.conciliado = 0 AND d.deleted_at IS NULL) AS pendiente
                FROM tesoreria_conciliaciones c
                INNER JOIN tesoreria_cuentas t ON t.id = c.id_cuenta_bancaria
                WHERE c.deleted_at IS NULL
                ORDER BY c.periodo DESC, c.id DESC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cuentasBancarias(): array
    {
        return $this->db()->query("SELECT id, codigo, nombre FROM tesoreria_cuentas WHERE deleted_at IS NULL AND estado = 1 AND tipo = 'BANCO' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $data, int $userId): int
    {
        $id = (int)($data['id'] ?? 0);
        $payload = [
            'id_cuenta_bancaria' => (int)($data['id_cuenta_bancaria'] ?? 0),
            'periodo' => substr((string)($data['periodo'] ?? date('Y-m')), 0, 7),
            'saldo_estado_cuenta' => round((float)($data['saldo_estado_cuenta'] ?? 0), 4),
            'observaciones' => trim((string)($data['observaciones'] ?? '')),
        ];

        $saldoSistema = $this->calcularSaldoSistema($payload['id_cuenta_bancaria'], $payload['periodo']);
        $diferencia = round($payload['saldo_estado_cuenta'] - $saldoSistema, 4);
        $estado = ($diferencia === 0.0) ? 'CERRADA' : 'ABIERTA';

        if ($id > 0) {
            $stmt = $this->db()->prepare('UPDATE tesoreria_conciliaciones SET id_cuenta_bancaria=:id_cuenta_bancaria, periodo=:periodo, saldo_estado_cuenta=:saldo_estado_cuenta, saldo_sistema=:saldo_sistema, diferencia=:diferencia, estado=:estado, observaciones=:observaciones, updated_by=:user, updated_at=NOW() WHERE id=:id');
            $stmt->execute($payload + ['saldo_sistema' => $saldoSistema, 'diferencia' => $diferencia, 'estado' => $estado, 'user' => $userId, 'id' => $id]);
            return $id;
        }

        $stmt = $this->db()->prepare('INSERT INTO tesoreria_conciliaciones (id_cuenta_bancaria,periodo,saldo_estado_cuenta,saldo_sistema,diferencia,estado,observaciones,created_by,updated_by,created_at,updated_at) VALUES (:id_cuenta_bancaria,:periodo,:saldo_estado_cuenta,:saldo_sistema,:diferencia,:estado,:observaciones,:user,:user,NOW(),NOW())');
        $stmt->execute($payload + ['saldo_sistema' => $saldoSistema, 'diferencia' => $diferencia, 'estado' => $estado, 'user' => $userId]);
        return (int)$this->db()->lastInsertId();
    }

    public function importarCsv(int $idConciliacion, array $file, int $userId): int
    {
        if (($file['tmp_name'] ?? '') === '' || !is_file((string)$file['tmp_name'])) {
            throw new RuntimeException('Archivo inválido para importar.');
        }
        $fh = fopen((string)$file['tmp_name'], 'rb');
        if (!$fh) throw new RuntimeException('No se pudo leer el archivo.');
        $n = 0;
        fgetcsv($fh);
        $ins = $this->db()->prepare('INSERT INTO tesoreria_conciliaciones_detalle (id_conciliacion,fecha,descripcion,monto,referencia,created_by,updated_by,created_at,updated_at) VALUES (:id_conciliacion,:fecha,:descripcion,:monto,:referencia,:user,:user,NOW(),NOW())');
        while (($row = fgetcsv($fh)) !== false) {
                if (count($row) < 3) continue;
                
                // MAGIA DE FECHAS: Convertir DD/MM/YYYY a YYYY-MM-DD
                $fechaRaw = trim($row[0]);
                $fechaFormat = DateTime::createFromFormat('d/m/Y', $fechaRaw);
                $fechaSql = $fechaFormat ? $fechaFormat->format('Y-m-d') : $fechaRaw; // Fallback si ya viene bien

                $ins->execute([
                    'id_conciliacion' => $idConciliacion,
                    'fecha' => $fechaSql,
                    'descripcion' => trim($row[1]),
                    'monto' => round((float)$row[2], 4),
                    'referencia' => trim($row[3] ?? ''),
                    'user' => $userId,
                ]);
                $n++;
            }
        fclose($fh);
        return $n;
    }

    public function marcarDetalle(int $idDetalle, bool $conciliado, int $userId): void
    {
        // 1. Actualiza el detalle
        $stmt = $this->db()->prepare('UPDATE tesoreria_conciliaciones_detalle SET conciliado=:conciliado, updated_by=:user, updated_at=NOW() WHERE id=:id');
        $stmt->execute(['conciliado' => $conciliado ? 1 : 0, 'user' => $userId, 'id' => $idDetalle]);

        // 2. Busca a qué conciliación pertenece este detalle
        $idConc = $this->db()->query("SELECT id_conciliacion FROM tesoreria_conciliaciones_detalle WHERE id=$idDetalle")->fetchColumn();
        
        // 3. OBTENEMOS LOS DATOS Y RECALCULAMOS
        if ($idConc) {
            $conc = $this->db()->query("SELECT id_cuenta_bancaria, periodo, saldo_estado_cuenta FROM tesoreria_conciliaciones WHERE id=$idConc")->fetch(PDO::FETCH_ASSOC);
            $nuevoSaldoSis = $this->calcularSaldoSistema((int)$conc['id_cuenta_bancaria'], $conc['periodo']);
            $nuevaDif = round((float)$conc['saldo_estado_cuenta'] - $nuevoSaldoSis, 4);
            
            $upd = $this->db()->prepare("UPDATE tesoreria_conciliaciones SET saldo_sistema=:sis, diferencia=:dif WHERE id=:id");
            $upd->execute(['sis' => $nuevoSaldoSis, 'dif' => $nuevaDif, 'id' => $idConc]);
        }
    }

    public function obtenerDetalle(int $idConciliacion): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tesoreria_conciliaciones_detalle WHERE id_conciliacion = :id AND deleted_at IS NULL ORDER BY fecha ASC, id ASC');
        $stmt->execute(['id' => $idConciliacion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cerrarSiCorresponde(int $idConciliacion, int $userId): void
    {
        $stmt = $this->db()->prepare('SELECT diferencia, observaciones FROM tesoreria_conciliaciones WHERE id=:id');
        $stmt->execute(['id' => $idConciliacion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Conciliación no encontrada.');
        $dif = round((float)$row['diferencia'], 4);
        if ($dif !== 0.0 && trim((string)$row['observaciones']) === '') {
            throw new RuntimeException('Debe justificar la diferencia para cerrar la conciliación.');
        }
        $upd = $this->db()->prepare("UPDATE tesoreria_conciliaciones SET estado='CERRADA', updated_by=:user, updated_at=NOW() WHERE id=:id");
        $upd->execute(['id' => $idConciliacion, 'user' => $userId]);
    }

    private function calcularSaldoSistema(int $idCuenta, string $periodo): float
    {
        $stmt = $this->db()->prepare('SELECT COALESCE(SUM(CASE WHEN tipo = "COBRO" THEN monto ELSE -monto END),0)
            FROM tesoreria_movimientos WHERE id_cuenta = :id_cuenta AND DATE_FORMAT(fecha, "%Y-%m") = :periodo AND estado = "CONFIRMADO" AND deleted_at IS NULL');
        $stmt->execute(['id_cuenta' => $idCuenta, 'periodo' => $periodo]);
        return round((float)$stmt->fetchColumn(), 4);
    }
}
