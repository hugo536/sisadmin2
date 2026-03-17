<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';

class TesoreriaMovimientoModel extends Modelo
{
    public function listarPorOrigen(string $origen, int $idOrigen): array
    {
        $stmt = $this->db()->prepare('SELECT m.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre, mp.nombre AS metodo_nombre
                                      FROM tesoreria_movimientos m
                                      INNER JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                                      INNER JOIN tesoreria_metodos_pago mp ON mp.id = m.id_metodo_pago
                                      WHERE m.origen = :origen AND m.id_origen = :id_origen AND m.deleted_at IS NULL
                                      ORDER BY m.fecha DESC, m.id DESC');
        $stmt->execute(['origen' => strtoupper(trim($origen)), 'id_origen' => $idOrigen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarRecientes(array $filtros = [], int $limite = 20): array
    {
        $where = ['m.deleted_at IS NULL'];
        $params = [];

        $origen = strtoupper(trim((string) ($filtros['origen'] ?? '')));
        if (in_array($origen, ['CXC', 'CXP'], true)) {
            $where[] = 'm.origen = :origen';
            $params['origen'] = $origen;
        }

        $idOrigen = (int) ($filtros['id_origen'] ?? 0);
        if ($idOrigen > 0) {
            $where[] = 'm.id_origen = :id_origen';
            $params['id_origen'] = $idOrigen;
        }

        $idTercero = (int) ($filtros['id_tercero'] ?? 0);
        if ($idTercero > 0) {
            $where[] = 'm.id_tercero = :id_tercero';
            $params['id_tercero'] = $idTercero;
        }

        $sql = 'SELECT m.*, 
                       COALESCE(c.codigo, "S/C") AS cuenta_codigo, 
                       COALESCE(c.nombre, "Cuenta Eliminada") AS cuenta_nombre, 
                       COALESCE(t.nombre_completo, "Tercero Eliminado") AS tercero_nombre
                FROM tesoreria_movimientos m
                LEFT JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                LEFT JOIN terceros t ON t.id = m.id_tercero
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY m.id DESC
                LIMIT :limite';

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function registrar(array $data, int $userId): int
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $origen = strtoupper(trim((string) $data['origen']));
            $idOrigen = (int) $data['id_origen'];
            $monto = round((float) $data['monto'], 4);
            $tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
            $naturalezaPago = strtoupper(trim((string) ($data['naturaleza_pago'] ?? 'DOCUMENTO')));
            $montoCapital = round((float) ($data['monto_capital'] ?? $monto), 4);
            $montoInteres = round((float) ($data['monto_interes'] ?? 0), 4);
            $idCentroCosto = (int) ($data['id_centro_costo'] ?? 0);

            if ($monto <= 0) {
                throw new RuntimeException('El monto de la transacción debe ser mayor a cero.');
            }

            // 1. Obtener datos del documento origen (CXC o CXP)
            if ($origen === 'CXC') {
                $stmtOrigen = $db->prepare('SELECT id, id_cliente AS id_tercero, moneda, saldo, estado FROM tesoreria_cxc WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            } elseif ($origen === 'CXP') {
                $stmtOrigen = $db->prepare('SELECT id, id_proveedor AS id_tercero, moneda, saldo, estado FROM tesoreria_cxp WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            } else {
                throw new RuntimeException('Origen de transacción inválido.');
            }

            $stmtOrigen->execute(['id' => $idOrigen]);
            $origenRow = $stmtOrigen->fetch(PDO::FETCH_ASSOC);
            
            if (!$origenRow) throw new RuntimeException('Documento de origen no encontrado.');
            if (($origenRow['estado'] ?? '') === 'ANULADA') throw new RuntimeException('El documento está anulado.');

            $montoAplicaOrigen = $monto;
            if ($origen === 'CXP' && $tipo === 'PAGO') {
                if (!in_array($naturalezaPago, ['DOCUMENTO', 'CAPITAL', 'INTERES', 'MIXTO'], true)) {
                    throw new RuntimeException('Naturaleza de pago inválida.');
                }
                if ($naturalezaPago === 'INTERES') {
                    $montoCapital = 0;
                    $montoInteres = $monto;
                    $montoAplicaOrigen = 0;
                } elseif ($naturalezaPago === 'CAPITAL') {
                    $montoInteres = 0;
                    $montoCapital = $monto;
                    $montoAplicaOrigen = $montoCapital;
                } elseif ($naturalezaPago === 'MIXTO') {
                    if ($montoCapital <= 0 || $montoInteres <= 0) {
                        throw new RuntimeException('Para pago mixto debe indicar montos de capital e interés mayores a cero.');
                    }
                    if (round($montoCapital + $montoInteres, 4) !== $monto) {
                        throw new RuntimeException('La suma capital + interés debe ser igual al monto total del pago.');
                    }
                    $montoAplicaOrigen = $montoCapital;
                }
                $saldoActual = round((float) ($origenRow['saldo'] ?? 0), 4);
                if ($montoAplicaOrigen > $saldoActual) {
                    throw new RuntimeException('El componente de capital no puede exceder el saldo pendiente de la obligación.');
                }
            }

            // 2. Validar cuenta de tesorería y OBTENER VINCULACIÓN CONTABLE
            // 
            $stmtCuenta = $db->prepare('SELECT id, moneda, estado FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmtCuenta->execute(['id' => (int) $data['id_cuenta']]);
            $cuentaTes = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            
            if (!$cuentaTes || (int)$cuentaTes['estado'] !== 1) throw new RuntimeException('La cuenta de tesorería está inactiva.');

            // 3. Insertar el movimiento de tesorería
            $stmtInsert = $db->prepare('INSERT INTO tesoreria_movimientos
                (tipo, id_tercero, origen, id_origen, id_cuenta, id_metodo_pago, fecha, moneda, monto, naturaleza_pago, monto_capital, monto_interes, id_centro_costo, referencia, observaciones, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:tipo, :id_tercero, :origen, :id_origen, :id_cuenta, :id_metodo_pago, :fecha, :moneda, :monto, :naturaleza_pago, :monto_capital, :monto_interes, :id_centro_costo, :referencia, :observaciones, "CONFIRMADO", :created_by, :updated_by, NOW(), NOW())');

            $stmtInsert->execute([
                'tipo'           => $tipo,
                'id_tercero'     => (int) $origenRow['id_tercero'],
                'origen'         => $origen,
                'id_origen'      => $idOrigen,
                'id_cuenta'      => (int) $data['id_cuenta'],
                'id_metodo_pago' => (int) $data['id_metodo_pago'],
                'fecha'          => $data['fecha'],
                'moneda'         => $data['moneda'],
                'monto'          => $monto,
                'naturaleza_pago' => $naturalezaPago,
                'monto_capital' => $montoCapital,
                'monto_interes' => $montoInteres,
                'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : null,
                'referencia'     => $data['referencia'] ?: null,
                'observaciones'  => $data['observaciones'] ?: null,
                'created_by'     => $userId,
                'updated_by'     => $userId,
            ]);
            $idMovimiento = (int) $db->lastInsertId();

            // 4. Actualizar saldo en origen
            $tablaOrigen = ($origen === 'CXC') ? 'tesoreria_cxc' : 'tesoreria_cxp';
            $stmtUpd = $db->prepare("UPDATE $tablaOrigen SET monto_pagado = ROUND(monto_pagado + :monto_aplica, 4), updated_by = :user, updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['monto_aplica' => $montoAplicaOrigen, 'user' => $userId, 'id' => $idOrigen]);

            // 5. REGISTRO CONTABLE AUTOMÁTICO USANDO LA CUENTA VINCULADA
            // 
            $contaModel = new ContaAsientoModel();
            $contaModel->registrarAutomaticoTesoreria($db, [
                'id_movimiento'      => $idMovimiento,
                'tipo'               => strtoupper(trim((string)$data['tipo'])), // COBRO o PAGO
                'fecha'              => (string)$data['fecha'],
                'monto'              => $monto,
                'id_cuenta_tesoreria' => (int)$data['id_cuenta'],
                'id_tercero'         => (int)$origenRow['id_tercero'],
                'naturaleza_pago'    => $naturalezaPago,
                'monto_capital'      => $montoCapital,
                'monto_interes'      => $montoInteres,
                'id_centro_costo'    => $idCentroCosto
            ], $userId);

            $db->commit();
            return $idMovimiento;
            
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function registrarDistribuido(array $data, array $idsOrigen, int $userId): array
    {
        $db = $this->db();
        
        // Verificamos si ya estamos en una transacción para no anidar
        $localTx = !$db->inTransaction();
        if ($localTx) {
            $db->beginTransaction();
        }

        try {
            $origen = strtoupper(trim((string) ($data['origen'] ?? '')));
            $tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
            $montoTotal = round((float) ($data['monto'] ?? 0), 4);
            $moneda = strtoupper(trim((string) ($data['moneda'] ?? 'PEN')));

            $restante = $montoTotal;
            $movimientosCount = 0;
            $idsAplicados = [];

            foreach ($idsOrigen as $idOrigen) {
                // Si ya no nos queda dinero en el bolsillo, detenemos el bucle
                if ($restante <= 0) break;

                // 1. OBTENEMOS EL SALDO REAL DE ESTA FACTURA ESPECÍFICA
                $tabla = ($origen === 'CXC') ? 'tesoreria_cxc' : 'tesoreria_cxp';
                $stmtSaldo = $db->prepare("SELECT saldo FROM $tabla WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
                $stmtSaldo->execute(['id' => $idOrigen]);
                $saldoDocumento = round((float) $stmtSaldo->fetchColumn(), 4);

                // Si por alguna razón la factura ya está pagada (saldo 0), saltamos a la siguiente
                if ($saldoDocumento <= 0) {
                    continue;
                }

                // 2. MAGIA FIFO: ¿Cuánto vamos a pagar aquí? 
                // Elegimos el valor menor entre lo que nos queda en el bolsillo y lo que debe la factura.
                $montoAPagarAqui = min($restante, $saldoDocumento);

                // 3. Registramos el pago pasándole el monto exacto calculado
                $pagoAqui = $this->registrar([
                    'tipo'           => $tipo,
                    'origen'         => $origen,
                    'id_origen'      => (int)$idOrigen,
                    'id_cuenta'      => (int)$data['id_cuenta'],
                    'id_metodo_pago' => (int)$data['id_metodo_pago'],
                    'fecha'          => $data['fecha'],
                    'moneda'         => $moneda,
                    'monto'          => $montoAPagarAqui, // <-- AQUÍ ESTÁ LA CORRECCIÓN CLAVE
                    'referencia'     => $data['referencia'],
                    'observaciones'  => $data['observaciones']
                ], $userId);

                // 4. Descontamos lo que acabamos de pagar del bolsillo
                $restante = round($restante - $montoAPagarAqui, 4);
                $movimientosCount++;
                $idsAplicados[] = (int)$idOrigen;
            }

            // Opcional: Si $restante > 0 al final del bucle, significa que el usuario pagó
            // MÁS de lo que el proveedor debía en total. Por ahora, ese dinero "sobrante" no se registra,
            // pero el FIFO aplicará correctamente a todo lo que debía.

            if ($localTx) {
                $db->commit();
            }
            
            return ['movimientos' => $movimientosCount, 'origenes' => $idsAplicados];
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function anular(int $idMovimiento, int $userId): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmtMov = $db->prepare('SELECT * FROM tesoreria_movimientos WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            $stmtMov->execute(['id' => $idMovimiento]);
            $mov = $stmtMov->fetch(PDO::FETCH_ASSOC);
            
            if (!$mov) throw new RuntimeException('Movimiento no encontrado.');
            if (($mov['estado'] ?? '') === 'ANULADO') throw new RuntimeException('Ya está anulado.');

            $origen = (string)$mov['origen'];
            $idOrigen = (int)$mov['id_origen'];
            $monto = (float)$mov['monto'];
            $naturaleza = strtoupper((string)($mov['naturaleza_pago'] ?? 'DOCUMENTO'));
            $montoCapital = (float)($mov['monto_capital'] ?? 0);
            $montoAplicaOrigen = $monto;
            if ($origen === 'CXP') {
                if ($naturaleza === 'INTERES') {
                    $montoAplicaOrigen = 0;
                } elseif ($naturaleza === 'MIXTO') {
                    $montoAplicaOrigen = max(0, $montoCapital);
                }
            }

            // 1. Revertir saldo en CXC/CXP
            $tabla = ($origen === 'CXC') ? 'tesoreria_cxc' : 'tesoreria_cxp';
            $stmtUpd = $db->prepare("UPDATE $tabla SET monto_pagado = GREATEST(ROUND(monto_pagado - :monto_aplica, 4), 0), updated_by = :user, updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['monto_aplica' => $montoAplicaOrigen, 'user' => $userId, 'id' => $idOrigen]);

            // 2. Marcar movimiento como anulado
            $db->prepare('UPDATE tesoreria_movimientos SET estado = "ANULADO", updated_by = :user, updated_at = NOW() WHERE id = :id')
               ->execute(['id' => $idMovimiento, 'user' => $userId]);

            // 3. Anular asiento contable asociado
            $stmtAs = $db->prepare('SELECT id FROM conta_asientos WHERE origen_modulo = "TESORERIA" AND id_origen = :id_mov AND estado = "REGISTRADO" LIMIT 1');
            $stmtAs->execute(['id_mov' => $idMovimiento]);
            $idAsiento = (int)$stmtAs->fetchColumn();
            
            if ($idAsiento > 0) {
                $contaModel = new ContaAsientoModel();
                $contaModel->anularConReversion($idAsiento, $userId);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function resumenPorCuenta(): array
    {
        $sql = 'SELECT res.id, res.codigo, res.nombre, res.moneda, res.ingresos, res.egresos, (res.ingresos - res.egresos) AS saldo_teorico
                FROM (
                    SELECT c.id, c.codigo, c.nombre, c.moneda,
                           COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "COBRO" AND DATE(m.fecha) = CURDATE() THEN m.monto ELSE 0 END), 0) AS ingresos,
                           COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "PAGO" AND DATE(m.fecha) = CURDATE() THEN m.monto ELSE 0 END), 0) AS egresos
                    FROM tesoreria_cuentas c
                    LEFT JOIN tesoreria_movimientos m ON m.id_cuenta = c.id AND m.deleted_at IS NULL
                    WHERE c.deleted_at IS NULL AND c.estado = 1
                    GROUP BY c.id, c.codigo, c.nombre, c.moneda
                ) res
                ORDER BY res.nombre ASC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
