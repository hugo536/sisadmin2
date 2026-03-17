<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';

class GastoRegistroModel extends Modelo
{
    private bool $tablaValidada = false;

    public function listar(array $filtros = []): array
    {
        $this->asegurarTablaRegistros();

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

        $sql = 'SELECT gr.*, gc.nombre AS concepto, gc.codigo AS concepto_codigo, t.nombre_completo AS proveedor,
                       COALESCE(ccr.codigo, ccc.codigo, "SCC") AS centro_costo_codigo,
                       COALESCE(ccr.nombre, ccc.nombre, "Sin centro") AS centro_costo_nombre
                FROM gastos_registros gr
                INNER JOIN gastos_conceptos gc ON gc.id = gr.id_concepto
                INNER JOIN terceros t ON t.id = gr.id_proveedor
                LEFT JOIN conta_centros_costo ccr ON ccr.id = gr.id_centro_costo
                LEFT JOIN conta_centros_costo ccc ON ccc.id = gc.id_centro_costo
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY gr.fecha DESC, gr.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crear(array $data, int $userId): int
    {
        $this->asegurarTablaRegistros();

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
            $idCentroCosto = (int) ($data['id_centro_costo'] ?? 0);

            if ($fecha === '' || $idProveedor <= 0 || $idConcepto <= 0 || $monto <= 0) {
                throw new RuntimeException('Complete todos los campos obligatorios del registro de gasto.');
            }

            $factorImpuesto = ($impuestoTipo === 'IGV' || $impuestoTipo === 'IVA') ? 0.18 : 0.0;
            $impuestoMonto = round($monto * $factorImpuesto, 4);
            $total = round($monto + $impuestoMonto, 4);

            $stmtConcepto = $db->prepare('SELECT id_cuenta_contable, id_centro_costo, nombre FROM gastos_conceptos WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmtConcepto->execute(['id' => $idConcepto]);
            $concepto = $stmtConcepto->fetch(PDO::FETCH_ASSOC);
            if (!$concepto) {
                throw new RuntimeException('Concepto de gasto inválido.');
            }

            // 1. Guardar Gasto
            $stmt = $db->prepare('INSERT INTO gastos_registros
                (fecha, id_proveedor, id_concepto, id_centro_costo, monto, impuesto_tipo, impuesto_monto, total, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:fecha, :id_proveedor, :id_concepto, :id_centro_costo, :monto, :impuesto_tipo, :impuesto_monto, :total, "REGISTRADO", :created_by, :updated_by, NOW(), NOW())');
            $stmt->execute([
                'fecha' => $fecha,
                'id_proveedor' => $idProveedor,
                'id_concepto' => $idConcepto,
                'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : (int) ($concepto['id_centro_costo'] ?? 0),
                'monto' => $monto,
                'impuesto_tipo' => $impuestoTipo,
                'impuesto_monto' => $impuestoMonto,
                'total' => $total,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $idRegistro = (int) $db->lastInsertId();

            // 2. Generar Cuenta por Pagar (CXP)
            $cxpModel = new TesoreriaCxpModel();
            $idCxp = $cxpModel->crearDesdeGasto($idRegistro, $userId);
            
            if ($idCxp === 0) {
                throw new RuntimeException('Falla de conexión: No se pudo generar la Cuenta por Pagar.');
            }

            // 3. Generar Asiento Contable
            $asientoModel = new ContaAsientoModel();
            $idAsiento = $asientoModel->registrarAutomaticoGasto($db, [
                'id_gasto' => $idRegistro,
                'fecha' => $fecha,
                'id_proveedor' => $idProveedor,
                'total' => $total,
                'id_cuenta_gasto' => (int) ($concepto['id_cuenta_contable'] ?? 0),
                'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : (int) ($concepto['id_centro_costo'] ?? 0),
                'glosa' => 'Registro de gasto: ' . (string) ($concepto['nombre'] ?? ''),
            ], $userId);

            // 4. Vincular los IDs en el Gasto Maestro
            $stmtFin = $db->prepare('UPDATE gastos_registros SET id_cxp = :id_cxp, id_asiento = :id_asiento, updated_by = :user, updated_at = NOW() WHERE id = :id');
            $stmtFin->execute([
                'id_cxp' => $idCxp,
                'id_asiento' => $idAsiento, // Si este sigue siendo 0 después, revisaremos ContaAsientoModel
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

    public function anular(int $id, int $userId): void
    {
        $this->asegurarTablaRegistros();

        if ($id <= 0) {
            throw new RuntimeException('Registro de gasto inválido.');
        }

        $db = $this->db();
        $localTx = !$db->inTransaction();
        if ($localTx) {
            $db->beginTransaction();
        }

        try {
            $stmt = $db->prepare('SELECT id, estado, id_cxp FROM gastos_registros WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('No se encontró el registro de gasto.');
            }

            $estadoActual = strtoupper((string) ($row['estado'] ?? ''));
            if ($estadoActual === 'ANULADO') {
                if ($localTx) {
                    $db->commit();
                }
                return;
            }

            if ($estadoActual === 'PAGADO') {
                throw new RuntimeException('No se puede anular un gasto que ya está pagado.');
            }

            $db->prepare('UPDATE gastos_registros
                SET estado = "ANULADO", updated_by = :user, updated_at = NOW()
                WHERE id = :id')
                ->execute(['id' => $id, 'user' => $userId]);

            $idCxp = (int) ($row['id_cxp'] ?? 0);
            if ($idCxp > 0) {
                $db->prepare('UPDATE tesoreria_cxp
                    SET estado = "ANULADA", updated_by = :user, updated_at = NOW()
                    WHERE id = :id')
                    ->execute(['id' => $idCxp, 'user' => $userId]);
            }

            if ($localTx) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function asegurarTablaRegistros(): void
    {
        if ($this->tablaValidada) {
            return;
        }

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabla');
        $stmt->execute(['tabla' => 'gastos_registros']);
        $existe = (int) $stmt->fetchColumn() > 0;

        if (!$existe) {
            $this->db()->exec('CREATE TABLE IF NOT EXISTS gastos_registros (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fecha DATE NOT NULL,
                id_proveedor INT NOT NULL,
                id_concepto INT NOT NULL,
                id_centro_costo INT NULL,
                monto DECIMAL(14,4) NOT NULL DEFAULT 0,
                impuesto_tipo VARCHAR(10) NOT NULL DEFAULT "NINGUNO",
                impuesto_monto DECIMAL(14,4) NOT NULL DEFAULT 0,
                total DECIMAL(14,4) NOT NULL DEFAULT 0,
                estado VARCHAR(20) NOT NULL DEFAULT "PENDIENTE",
                id_cxp INT NULL,
                id_asiento INT NULL,
                created_by INT NULL,
                updated_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                KEY idx_gastos_registros_fecha (fecha),
                KEY idx_gastos_registros_proveedor (id_proveedor),
                KEY idx_gastos_registros_concepto (id_concepto),
                KEY idx_gastos_registros_centro (id_centro_costo),
                KEY idx_gastos_registros_cxp (id_cxp),
                KEY idx_gastos_registros_asiento (id_asiento),
                CONSTRAINT fk_gastos_registros_proveedor FOREIGN KEY (id_proveedor) REFERENCES terceros(id),
                CONSTRAINT fk_gastos_registros_concepto FOREIGN KEY (id_concepto) REFERENCES gastos_conceptos(id),
                CONSTRAINT fk_gastos_registros_centro FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id),
                CONSTRAINT fk_gastos_registros_cxp FOREIGN KEY (id_cxp) REFERENCES tesoreria_cxp(id),
                CONSTRAINT fk_gastos_registros_asiento FOREIGN KEY (id_asiento) REFERENCES conta_asientos(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        $this->tablaValidada = true;
    }
}
