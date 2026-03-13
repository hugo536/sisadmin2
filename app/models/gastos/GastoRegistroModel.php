<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';

class GastoRegistroModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $where = ['gr.deleted_at IS NULL'];
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'gr.fecha >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'gr.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $sql = 'SELECT gr.*, gc.nombre AS concepto, gc.codigo AS concepto_codigo, t.nombre_completo AS proveedor
                FROM gastos_registros gr
                INNER JOIN gastos_conceptos gc ON gc.id = gr.id_concepto
                INNER JOIN terceros t ON t.id = gr.id_proveedor
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY gr.fecha DESC, gr.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crear(array $data, int $userId): int
    {
        $db = $this->db();
        $localTx = !$db->inTransaction();
        if ($localTx) {
            $db->beginTransaction();
        }

        try {
            $fecha = trim((string) ($data['fecha'] ?? date('Y-m-d')));
            $idProveedor = (int) ($data['id_proveedor'] ?? 0);
            $idConcepto = (int) ($data['id_concepto'] ?? 0);
            $monto = round((float) ($data['monto'] ?? 0), 4);
            $impuestoTipo = strtoupper(trim((string) ($data['impuesto_tipo'] ?? 'NINGUNO')));

            if ($fecha === '' || $idProveedor <= 0 || $idConcepto <= 0 || $monto <= 0) {
                throw new RuntimeException('Complete todos los campos obligatorios del registro de gasto.');
            }

            $factorImpuesto = ($impuestoTipo === 'IGV' || $impuestoTipo === 'IVA') ? 0.18 : 0.0;
            $impuestoMonto = round($monto * $factorImpuesto, 4);
            $total = round($monto + $impuestoMonto, 4);

            $stmtConcepto = $db->prepare('SELECT id_cuenta_contable, nombre FROM gastos_conceptos WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmtConcepto->execute(['id' => $idConcepto]);
            $concepto = $stmtConcepto->fetch(PDO::FETCH_ASSOC);
            if (!$concepto) {
                throw new RuntimeException('Concepto de gasto inválido.');
            }

            $stmt = $db->prepare('INSERT INTO gastos_registros
                (fecha, id_proveedor, id_concepto, monto, impuesto_tipo, impuesto_monto, total, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:fecha, :id_proveedor, :id_concepto, :monto, :impuesto_tipo, :impuesto_monto, :total, "PENDIENTE", :created_by, :updated_by, NOW(), NOW())');
            $stmt->execute([
                'fecha' => $fecha,
                'id_proveedor' => $idProveedor,
                'id_concepto' => $idConcepto,
                'monto' => $monto,
                'impuesto_tipo' => $impuestoTipo,
                'impuesto_monto' => $impuestoMonto,
                'total' => $total,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $idRegistro = (int) $db->lastInsertId();

            $cxpModel = new TesoreriaCxpModel();
            $idCxp = $cxpModel->crearDesdeGasto($idRegistro, $userId);

            $asientoModel = new ContaAsientoModel();
            $idAsiento = $asientoModel->registrarAutomaticoGasto($db, [
                'id_gasto' => $idRegistro,
                'fecha' => $fecha,
                'id_proveedor' => $idProveedor,
                'total' => $total,
                'id_cuenta_gasto' => (int) ($concepto['id_cuenta_contable'] ?? 0),
                'glosa' => 'Registro de gasto: ' . (string) ($concepto['nombre'] ?? ''),
            ], $userId);

            $stmtFin = $db->prepare('UPDATE gastos_registros SET id_cxp = :id_cxp, id_asiento = :id_asiento, updated_by = :user, updated_at = NOW() WHERE id = :id');
            $stmtFin->execute([
                'id_cxp' => $idCxp,
                'id_asiento' => $idAsiento,
                'user' => $userId,
                'id' => $idRegistro,
            ]);

            if ($localTx) {
                $db->commit();
            }
            return $idRegistro;
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
