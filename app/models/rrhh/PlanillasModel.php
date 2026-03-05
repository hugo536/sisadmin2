<?php
declare(strict_types=1);

class PlanillasModel extends Modelo
{
    /**
     * Obtiene el resumen consolidado de asistencia y sueldos para el cálculo de planilla.
     */
    public function obtenerResumenPlanilla(string $desde, string $hasta, ?int $idTercero = null): array
    {
        $sql = "SELECT 
                    t.id AS id_tercero,
                    t.numero_documento,
                    t.nombre_completo,
                    te.cargo,
                    te.sueldo_basico,
                    te.tipo_pago,
                    te.pago_diario,
                    te.moneda,
                    te.asignacion_familiar,
                    
                    -- Agrupación Matemática de Asistencia
                    COUNT(CASE WHEN ar.estado_asistencia IN ('PUNTUAL', 'TARDANZA', 'TARDANZA JUSTIFICADA', 'INCOMPLETO') THEN 1 END) AS dias_asistidos,
                    COUNT(CASE WHEN ar.estado_asistencia = 'FALTA' THEN 1 END) AS dias_falta,
                    COUNT(CASE WHEN ar.estado_asistencia IN ('FALTA JUSTIFICADA', 'PERMISO', 'VACACIONES', 'DESCANSO MEDICO') THEN 1 END) AS dias_justificados,
                    SUM(COALESCE(ar.horas_trabajadas, 0)) AS total_horas,
                    SUM(COALESCE(ar.horas_extras, 0)) AS total_horas_extras,
                    SUM(COALESCE(ar.minutos_tardanza, 0)) AS total_minutos_tardanza,
                    
                    -- LÓGICA DE ESTADO DE PAGO:
                    CASE 
                        WHEN COUNT(ar.id) = 0 THEN 'SIN_REGISTROS'
                        WHEN SUM(CASE WHEN ar.estado_pago = 'PENDIENTE' THEN 1 ELSE 0 END) > 0 THEN 'PENDIENTE'
                        ELSE 'PAGADA'
                    END AS estado_pago
                    
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                LEFT JOIN asistencia_registros ar ON ar.id_tercero = t.id 
                      AND ar.fecha BETWEEN :desde AND :hasta
                WHERE t.es_empleado = 1 
                  AND t.estado = 1 
                  AND t.deleted_at IS NULL";

        $params = [
            'desde' => $desde,
            'hasta' => $hasta
        ];

        if ($idTercero !== null && $idTercero > 0) {
            $sql .= " AND t.id = :id_tercero";
            $params['id_tercero'] = $idTercero;
        }

        $sql .= " GROUP BY 
                    t.id, t.numero_documento, t.nombre_completo, te.cargo, 
                    te.sueldo_basico, te.tipo_pago, te.pago_diario, te.moneda, te.asignacion_familiar
                  ORDER BY t.nombre_completo ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene el detalle diario de un empleado específico para imprimir su Boleta/Ticket.
     */
    public function obtenerDetalleAsistenciaEmpleado(int $idTercero, string $desde, string $hasta): array
    {
        $sql = "SELECT 
                    ar.fecha,
                    ar.hora_ingreso,
                    ar.hora_salida,
                    ar.estado_asistencia,
                    ar.minutos_tardanza,
                    ar.horas_trabajadas,
                    ar.horas_extras,
                    ar.observaciones
                FROM asistencia_registros ar
                WHERE ar.id_tercero = :id_tercero
                  AND ar.fecha BETWEEN :desde AND :hasta
                ORDER BY ar.fecha ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_tercero' => $idTercero,
            'desde' => $desde,
            'hasta' => $hasta
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Procesa el pago, descuenta de tesorería y marca las asistencias como pagadas.
     * MEJORA: Validación de saldo antes de descontar.
     */
    public function registrarPagoPlanilla(array $datos, int $userId): bool
    {
        $db = $this->db();
        
        try {
            $db->beginTransaction();

            // -- MEJORA DE SEGURIDAD: Verificar saldo suficiente --
            $stmtVerificar = $db->prepare("SELECT saldo_actual FROM tesoreria_cuentas WHERE id = :id_cuenta FOR UPDATE");
            $stmtVerificar->execute(['id_cuenta' => $datos['id_cuenta']]);
            $cuenta = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$cuenta || (float)$cuenta['saldo_actual'] < (float)$datos['monto_pagar']) {
                // Lanzamos una excepción que será capturada por el catch, cancelando la transacción
                throw new Exception("Saldo insuficiente en la cuenta de tesorería seleccionada.");
            }
            // -----------------------------------------------------

            // 1. Insertar el Egreso en Tesorería (Salida de dinero)
            $stmtMov = $db->prepare("INSERT INTO tesoreria_movimientos 
                (tipo, id_tercero, origen, id_origen, id_cuenta, id_metodo_pago, fecha, moneda, monto, referencia, observaciones, estado, created_by, created_at, updated_at) 
                VALUES 
                ('PAGO', :id_tercero, 'PLANILLA', 0, :id_cuenta, :id_metodo_pago, :fecha, 'PEN', :monto, :referencia, :observaciones, 'CONFIRMADO', :created_by, NOW(), NOW())");
            
            $stmtMov->execute([
                'id_tercero' => $datos['id_empleado'],
                'id_cuenta' => $datos['id_cuenta'],
                'id_metodo_pago' => $datos['id_metodo_pago'],
                'fecha' => $datos['fecha_pago'],
                'monto' => $datos['monto_pagar'],
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => "Pago de Planilla periodo " . $datos['fecha_inicio'] . " al " . $datos['fecha_fin'],
                'created_by' => $userId
            ]);
            
            $idMovimientoTesoreria = (int) $db->lastInsertId();

            // 2. Descontar el saldo de la cuenta de origen (Caja/Banco)
            $stmtCuenta = $db->prepare("UPDATE tesoreria_cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id_cuenta");
            $stmtCuenta->execute([
                'monto' => $datos['monto_pagar'],
                'id_cuenta' => $datos['id_cuenta']
            ]);

            // 3. Crear el Recibo Maestro en RRHH
            $stmtNomina = $db->prepare("INSERT INTO rrhh_pagos_nomina 
                (id_empleado, fecha_inicio, fecha_fin, monto_base, total_pagado, id_cuenta_origen, id_movimiento_tesoreria, estado, created_by, created_at) 
                VALUES 
                (:id_empleado, :fecha_inicio, :fecha_fin, :monto_base, :total_pagado, :id_cuenta, :id_movimiento, 'PAGADA', :created_by, NOW())");
            
            $stmtNomina->execute([
                'id_empleado' => $datos['id_empleado'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'monto_base' => $datos['monto_pagar'], 
                'total_pagado' => $datos['monto_pagar'],
                'id_cuenta' => $datos['id_cuenta'],
                'id_movimiento' => $idMovimientoTesoreria,
                'created_by' => $userId
            ]);
            
            $idPagoNomina = (int) $db->lastInsertId();

            // 4. Actualizar las asistencias de ese periodo para que pasen a "PAGADA" y queden enlazadas al recibo
            $stmtAsistencias = $db->prepare("UPDATE asistencia_registros 
                SET estado_pago = 'PAGADA', id_pago_nomina = :id_pago_nomina 
                WHERE id_tercero = :id_empleado 
                  AND fecha BETWEEN :fecha_inicio AND :fecha_fin 
                  AND estado_pago = 'PENDIENTE'");
            
            $stmtAsistencias->execute([
                'id_pago_nomina' => $idPagoNomina,
                'id_empleado' => $datos['id_empleado'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin']
            ]);

            $db->commit();
            return true;

        } catch (Exception $e) {
            $db->rollBack();
            // Registramos el error en el log del servidor para poder depurarlo
            error_log("Error en registrarPagoPlanilla: " . $e->getMessage());
            return false;
        }
    }
}