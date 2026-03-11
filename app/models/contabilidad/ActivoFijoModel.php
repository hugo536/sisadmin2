<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaPeriodoModel.php';

class ActivoFijoModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT a.*, c1.codigo AS cuenta_activo_codigo, c2.codigo AS cuenta_dep_codigo, c3.codigo AS cuenta_gasto_codigo, cc.codigo AS centro_costo_codigo
                FROM activos_fijos a
                INNER JOIN conta_cuentas c1 ON c1.id = a.id_cuenta_activo
                INNER JOIN conta_cuentas c2 ON c2.id = a.id_cuenta_depreciacion
                INNER JOIN conta_cuentas c3 ON c3.id = a.id_cuenta_gasto
                LEFT JOIN conta_centros_costo cc ON cc.id = a.id_centro_costo
                WHERE a.deleted_at IS NULL
                ORDER BY a.fecha_adquisicion DESC, a.id DESC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $data, int $userId): int
    {
        $id = (int)($data['id'] ?? 0);
        $payload = [
            'codigo_activo' => strtoupper(trim((string)($data['codigo_activo'] ?? ''))),
            'nombre' => trim((string)($data['nombre'] ?? '')),
            'fecha_adquisicion' => (string)($data['fecha_adquisicion'] ?? date('Y-m-d')),
            'costo_adquisicion' => round((float)($data['costo_adquisicion'] ?? 0), 4),
            'vida_util_meses' => max(1, (int)($data['vida_util_meses'] ?? 1)),
            'valor_residual' => round((float)($data['valor_residual'] ?? 0), 4),
            'id_cuenta_activo' => (int)($data['id_cuenta_activo'] ?? 0),
            'id_cuenta_depreciacion' => (int)($data['id_cuenta_depreciacion'] ?? 0),
            'id_cuenta_gasto' => (int)($data['id_cuenta_gasto'] ?? 0),
            'id_centro_costo' => empty($data['id_centro_costo']) ? null : (int)$data['id_centro_costo'],
            'estado' => (string)($data['estado'] ?? 'ACTIVO'),
        ];

        if ($payload['codigo_activo'] === '' || $payload['nombre'] === '' || $payload['id_cuenta_activo'] <= 0 || $payload['id_cuenta_depreciacion'] <= 0 || $payload['id_cuenta_gasto'] <= 0) {
            throw new RuntimeException('Complete los campos obligatorios y las cuentas del activo.');
        }

        $valorLibros = max(0, $payload['costo_adquisicion'] - $payload['valor_residual']);

        if ($id > 0) {
            $stmt = $this->db()->prepare('UPDATE activos_fijos SET codigo_activo=:codigo_activo, nombre=:nombre, fecha_adquisicion=:fecha_adquisicion,
                costo_adquisicion=:costo_adquisicion, vida_util_meses=:vida_util_meses, valor_residual=:valor_residual,
                id_cuenta_activo=:id_cuenta_activo, id_cuenta_depreciacion=:id_cuenta_depreciacion, id_cuenta_gasto=:id_cuenta_gasto, id_centro_costo=:id_centro_costo, estado=:estado,
                valor_libros = GREATEST(:valor_libros, valor_libros), updated_by=:user, updated_at=NOW() WHERE id=:id');
            $stmt->execute($payload + ['valor_libros' => $valorLibros, 'user' => $userId, 'id' => $id]);
            return $id;
        }

        // CORRECCIÓN: Separamos :user en :user_created y :user_updated
        $stmt = $this->db()->prepare('INSERT INTO activos_fijos
            (codigo_activo,nombre,fecha_adquisicion,costo_adquisicion,vida_util_meses,valor_residual,valor_libros,id_cuenta_activo,id_cuenta_depreciacion,id_cuenta_gasto,id_centro_costo,estado,created_by,updated_by,created_at,updated_at)
            VALUES (:codigo_activo,:nombre,:fecha_adquisicion,:costo_adquisicion,:vida_util_meses,:valor_residual,:valor_libros,:id_cuenta_activo,:id_cuenta_depreciacion,:id_cuenta_gasto,:id_centro_costo,:estado,:user_created,:user_updated,NOW(),NOW())');
        
        $stmt->execute($payload + [
            'valor_libros' => $valorLibros, 
            'user_created' => $userId, 
            'user_updated' => $userId
        ]);
        
        return (int)$this->db()->lastInsertId();
    }
    public function depreciarMensual(string $periodoYYYYMM, int $userId): int
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            [$anio, $mes] = array_map('intval', explode('-', $periodoYYYYMM));
            $periodoModel = new ContaPeriodoModel();
            $periodoConta = $periodoModel->crearSiNoExiste($anio, $mes, $userId);

            $activos = $db->query("SELECT * FROM activos_fijos WHERE deleted_at IS NULL AND estado = 'ACTIVO'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $asientoModel = new ContaAsientoModel();
            $procesados = 0;

            foreach ($activos as $a) {
                $existe = $db->prepare('SELECT 1 FROM conta_depreciaciones WHERE id_activo_fijo=:id AND periodo=:periodo AND deleted_at IS NULL');
                $existe->execute(['id' => (int)$a['id'], 'periodo' => $periodoYYYYMM]);
                if ($existe->fetchColumn()) continue;

                $base = max(0, (float)$a['costo_adquisicion'] - (float)$a['valor_residual']);
                $mensual = round($base / max(1, (int)$a['vida_util_meses']), 4);
                $disponible = max(0, (float)$a['valor_libros'] - (float)$a['valor_residual']);
                $monto = min($mensual, $disponible);
                if ($monto <= 0) continue;

                $ccId = !empty($a['id_centro_costo']) ? (int)$a['id_centro_costo'] : 0;

                // LA MAGIA CONTABLE: Debe a Gasto, Haber a Depreciación Acumulada
                $idAsiento = $asientoModel->crearManual([
                    'fecha' => sprintf('%04d-%02d-28', $anio, $mes),
                    'id_periodo' => (int)$periodoConta['id'],
                    'glosa' => 'Depreciación mensual activo ' . $a['codigo_activo'] . ' (' . $periodoYYYYMM . ')',
                    'origen_modulo' => 'MANUAL',
                    'estado' => 'REGISTRADO',
                ], [
                    ['id_cuenta' => (int)$a['id_cuenta_gasto'], 'debe' => $monto, 'haber' => 0, 'referencia' => 'DEP-' . $a['codigo_activo'], 'id_centro_costo' => $ccId],
                    ['id_cuenta' => (int)$a['id_cuenta_depreciacion'], 'debe' => 0, 'haber' => $monto, 'referencia' => 'DEP-' . $a['codigo_activo'], 'id_centro_costo' => 0],
                ], $userId);

                $ins = $db->prepare('INSERT INTO conta_depreciaciones (id_activo_fijo,periodo,monto,id_asiento,created_by,updated_by,created_at,updated_at) VALUES (:id,:periodo,:monto,:id_asiento,:user,:user,NOW(),NOW())');
                $ins->execute(['id' => (int)$a['id'], 'periodo' => $periodoYYYYMM, 'monto' => $monto, 'id_asiento' => $idAsiento, 'user' => $userId]);

                $upd = $db->prepare("UPDATE activos_fijos SET depreciacion_acumulada = depreciacion_acumulada + :monto, valor_libros = GREATEST(valor_residual, valor_libros - :monto), estado = CASE WHEN valor_libros - :monto <= valor_residual THEN 'DEPRECIADO' ELSE estado END, updated_by=:user, updated_at=NOW() WHERE id=:id");
                $upd->execute(['monto' => $monto, 'user' => $userId, 'id' => (int)$a['id']]);
                $procesados++;
            }

            $db->commit();
            return $procesados;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}