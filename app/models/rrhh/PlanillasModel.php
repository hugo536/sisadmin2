<?php
declare(strict_types=1);

class PlanillasModel extends Modelo
{
    private function resolverPagoDiario(float $sueldoBasico, string $tipoPago): float
    {
        $tipoPagoEmpleado = strtoupper(trim($tipoPago));

        return match ($tipoPagoEmpleado) {
            'SEMANAL' => $sueldoBasico,
            'QUINCENAL' => $sueldoBasico / 15,
            default => $sueldoBasico / 30,
        };
    }

    public function obtenerLotesRecientes(int $limite = 10): array
    {
        $sql = "SELECT id, referencia, nombre, fecha_inicio, fecha_fin, estado, total_neto 
                FROM rrhh_nominas 
                ORDER BY id DESC LIMIT :limite";
        
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerLotePorId(int $idLote): ?array
    {
        $sql = "SELECT * FROM rrhh_nominas WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idLote]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }

    public function obtenerDetallesLote(int $idLote): array
    {
        $sql = "SELECT 
                    nd.id,
                    nd.id_tercero,
                    t.nombre_completo,
                    t.numero_documento,
                    te.cargo,
                    te.tipo_pago AS frecuencia,
                    nd.dias_pagados,
                    nd.dias_falta,
                    nd.sueldo_base_calculado,
                    nd.total_percepciones,
                    nd.total_deducciones,
                    nd.neto_a_pagar,
                    (nd.sueldo_base_calculado / NULLIF(nd.dias_pagados, 0) / 8) AS pago_por_hora,
                    (nd.dias_pagados * 8) AS horas_acumuladas,
                    (SELECT SUM(monto) FROM rrhh_nominas_conceptos nc 
                     WHERE nc.id_detalle_nomina = nd.id AND nc.tipo = 'PERCEPCION' AND nc.es_automatico = 0) AS monto_bonos
                FROM rrhh_nominas_detalles nd
                INNER JOIN terceros t ON t.id = nd.id_tercero
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE nd.id_nomina = :id_nomina
                ORDER BY t.nombre_completo ASC";
                
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_nomina' => $idLote]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function calcularNominaEnMemoria(array $lote): array
    {
        $db = $this->db();
        $idLote = (int) $lote['id'];
        $frecuencia = strtoupper((string)($lote['frecuencia'] ?? 'TODOS'));
        $fechaInicio = $lote['fecha_inicio'];
        $fechaFin = $lote['fecha_fin'];

        $sqlEmp = "SELECT t.id, te.tipo_pago, te.sueldo_basico, t.nombre_completo, t.numero_documento, te.cargo
                   FROM terceros t
                   INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                   WHERE t.es_empleado = 1 AND t.estado = 1 AND t.deleted_at IS NULL";
        $paramsEmp = [];
        if ($frecuencia !== 'TODOS') {
            $sqlEmp .= " AND UPPER(te.tipo_pago) = :frecuencia";
            $paramsEmp['frecuencia'] = $frecuencia;
        }
        $stmtEmp = $db->prepare($sqlEmp);
        $stmtEmp->execute($paramsEmp);
        $empleadosActivos = $stmtEmp->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtCheck = $db->prepare("SELECT id, id_tercero FROM rrhh_nominas_detalles WHERE id_nomina = :id_nomina");
        $stmtCheck->execute(['id_nomina' => $idLote]);
        $detallesExistentes = $stmtCheck->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mapaDetalles = [];
        foreach ($detallesExistentes as $det) {
            $mapaDetalles[$det['id_tercero']] = $det['id'];
        }

        $stmtInsertDetalle = $db->prepare("INSERT INTO rrhh_nominas_detalles 
            (id_nomina, id_tercero, dias_pagados, dias_falta, minutos_tardanza, sueldo_base_calculado, total_percepciones, total_deducciones, neto_a_pagar) 
            VALUES (:id_nomina, :id_tercero, 0, 0, 0, 0, 0, 0, 0)");

        $empleadosProcesar = [];
        foreach ($empleadosActivos as $emp) {
            $idTercero = $emp['id'];
            if (!isset($mapaDetalles[$idTercero])) {
                $stmtInsertDetalle->execute(['id_nomina' => $idLote, 'id_tercero' => $idTercero]);
                $idDetalle = (int) $db->lastInsertId();
                $mapaDetalles[$idTercero] = $idDetalle;
            } else {
                $idDetalle = $mapaDetalles[$idTercero];
            }
            $emp['id_detalle'] = $idDetalle;
            $empleadosProcesar[] = $emp;
        }

        $sqlAsistencia = "SELECT id_tercero, estado_asistencia, minutos_tardanza 
                          FROM asistencia_registros 
                          WHERE fecha BETWEEN :desde AND :hasta 
                          AND (id_nomina_pago IS NULL OR id_nomina_pago = :id_lote)";
        $stmtAsist = $db->prepare($sqlAsistencia);
        $stmtAsist->execute(['desde' => $fechaInicio, 'hasta' => $fechaFin, 'id_lote' => $idLote]);
        $registrosAsistencia = $stmtAsist->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mapaAsistencia = [];
        foreach ($registrosAsistencia as $ar) {
            $idT = $ar['id_tercero'];
            if (!isset($mapaAsistencia[$idT])) {
                $mapaAsistencia[$idT] = ['asistidos' => 0, 'justificados' => 0, 'faltas' => 0, 'tardanzas' => 0];
            }
            $estado = strtoupper($ar['estado_asistencia']);
            if (in_array($estado, ['PUNTUAL', 'TARDANZA', 'TARDANZA JUSTIFICADA', 'INCOMPLETO'])) {
                $mapaAsistencia[$idT]['asistidos']++;
            } elseif (in_array($estado, ['FALTA JUSTIFICADA', 'PERMISO', 'VACACIONES', 'DESCANSO MEDICO'])) {
                $mapaAsistencia[$idT]['justificados']++;
            } elseif ($estado === 'FALTA') {
                $mapaAsistencia[$idT]['faltas']++;
            }
            $mapaAsistencia[$idT]['tardanzas'] += (int) $ar['minutos_tardanza'];
        }

        $sqlAdelantos = "SELECT id, id_tercero, saldo_pendiente FROM rrhh_adelantos WHERE estado = 'PENDIENTE' AND saldo_pendiente > 0 ORDER BY fecha ASC";
        $adelantosPendientes = $db->query($sqlAdelantos)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $mapaAdelantos = [];
        foreach ($adelantosPendientes as $ad) {
            $mapaAdelantos[$ad['id_tercero']][] = $ad;
        }

        $sqlManuales = "SELECT nc.id_detalle_nomina, nc.tipo, nc.monto 
                        FROM rrhh_nominas_conceptos nc
                        INNER JOIN rrhh_nominas_detalles nd ON nd.id = nc.id_detalle_nomina
                        WHERE nd.id_nomina = :id_nomina AND nc.es_automatico = 0";
        $stmtMan = $db->prepare($sqlManuales);
        $stmtMan->execute(['id_nomina' => $idLote]);
        $conceptosManuales = $stmtMan->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mapaManuales = [];
        foreach ($conceptosManuales as $cm) {
            $idD = $cm['id_detalle_nomina'];
            if (!isset($mapaManuales[$idD])) {
                $mapaManuales[$idD] = ['percepciones' => 0, 'deducciones' => 0, 'bonos' => 0];
            }
            if ($cm['tipo'] === 'PERCEPCION') {
                $mapaManuales[$idD]['percepciones'] += $cm['monto'];
                $mapaManuales[$idD]['bonos'] += $cm['monto'];
            } else {
                $mapaManuales[$idD]['deducciones'] += $cm['monto'];
            }
        }

        $resultados = [];
        foreach ($empleadosProcesar as $emp) {
            $idTercero = $emp['id'];
            $idDetalle = $emp['id_detalle'];
            
            // --- LÓGICA DE CÁLCULO DE ASISTENCIA Y CONFLICTOS ---
            $resultadoAsistencia = $this->calcularHorasReales((int)$idTercero, $fechaInicio, $fechaFin);
            
            $diasTrabajadosReales = $resultadoAsistencia['dias_trabajados'];
            $horasAcumuladas      = $resultadoAsistencia['horas_acumuladas'];
            $tieneConflicto       = $resultadoAsistencia['tiene_conflicto'];

            // Obtener tabla antigua de asistencias para faltas/justificaciones
            $asis = $mapaAsistencia[$idTercero] ?? ['asistidos' => 0, 'justificados' => 0, 'faltas' => 0, 'tardanzas' => 0];
            
            // Días pagados
            $diasPagados = $diasTrabajadosReales + $asis['justificados'];
            
            $pagoDiario = $this->resolverPagoDiario((float) $emp['sueldo_basico'], (string)($emp['tipo_pago'] ?? 'MENSUAL'));
            $pagoPorHora = $pagoDiario / 8;

            // Regla: Si hay conflicto, el sueldo base es 0 hasta que lo resuelvan
            if ($tieneConflicto) {
                $sueldoBaseCalculado = 0;
            } else {
                $sueldoBaseCalculado = $pagoDiario * $diasPagados;
            }

            $valorMinuto = $pagoPorHora / 60;
            $descuentoTardanzas = $valorMinuto * $asis['tardanzas'];

            $manuales = $mapaManuales[$idDetalle] ?? ['percepciones' => 0, 'deducciones' => 0, 'bonos' => 0];

            $totalPercepciones = $sueldoBaseCalculado + $manuales['percepciones'];
            $deduccionesPrevias = $descuentoTardanzas + $manuales['deducciones'];
            
            $netoTemporal = $totalPercepciones - $deduccionesPrevias;
            
            $descuentoAdelanto = 0;
            $adelantosAplicados = [];
            if (isset($mapaAdelantos[$idTercero]) && $netoTemporal > 0) {
                foreach ($mapaAdelantos[$idTercero] as &$ad) {
                    if ($netoTemporal <= 0) break;
                    $aDescontar = min($netoTemporal, (float)$ad['saldo_pendiente']);
                    $descuentoAdelanto += $aDescontar;
                    $netoTemporal -= $aDescontar;
                    $adelantosAplicados[] = ['id' => $ad['id'], 'monto' => $aDescontar];
                    $ad['saldo_pendiente'] -= $aDescontar;
                }
            }

            $totalDeducciones = $deduccionesPrevias + $descuentoAdelanto;
            $netoFinal = $totalPercepciones - $totalDeducciones;

            $resultados[] = [
                'id' => $idDetalle,
                'id_tercero' => $idTercero,
                'nombre_completo' => $emp['nombre_completo'],
                'numero_documento' => $emp['numero_documento'],
                'cargo' => $emp['cargo'],
                'frecuencia' => $emp['tipo_pago'],
                'dias_pagados' => $diasPagados,
                'dias_falta' => $asis['faltas'],
                'minutos_tardanza' => $asis['tardanzas'],
                'pago_por_hora' => round($pagoPorHora, 2),
                'horas_acumuladas' => $horasAcumuladas,
                'sueldo_base_calculado' => round($sueldoBaseCalculado, 2),
                'total_percepciones' => round($totalPercepciones, 2),
                'total_deducciones' => round($totalDeducciones, 2),
                'neto_a_pagar' => round(max(0, $netoFinal), 2),
                'monto_bonos' => round($manuales['bonos'], 2),
                'descuento_tardanzas' => round($descuentoTardanzas, 2),
                'descuento_adelanto' => round($descuentoAdelanto, 2),
                'adelantos_aplicados' => json_encode($adelantosAplicados),
                'tiene_conflicto' => $tieneConflicto // Variable de alerta visual
            ];
        }

        return $resultados;
    }

    public function generarLoteNomina(array $datos, int $userId): int
    {
        $db = $this->db();
        $frecuencia = strtoupper((string)($datos['frecuencia'] ?? 'TODOS'));
        $fechaInicio = (string) ($datos['fecha_inicio'] ?? '');
        $fechaFin = (string) ($datos['fecha_fin'] ?? '');
        $observaciones = !empty($datos['observaciones']) ? $datos['observaciones'] : null;
        
        $nombreLote = "NOM - " . date('d/m/Y', strtotime($fechaInicio)) . " al " . date('d/m/Y', strtotime($fechaFin));

        try {
            $db->beginTransaction();

            $stmtLote = $db->prepare("INSERT INTO rrhh_nominas 
                (referencia, nombre, fecha_inicio, fecha_fin, frecuencia, estado, observaciones, created_by) 
                VALUES (:referencia, :nombre, :fecha_inicio, :fecha_fin, :frecuencia, 'BORRADOR', :observaciones, :created_by)");
            
            $referencia = 'NOM-' . date('Ym') . '-' . rand(1000, 9999);
            
            $stmtLote->execute([
                'referencia' => $referencia,
                'nombre' => $nombreLote,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'frecuencia' => $frecuencia,
                'observaciones' => $observaciones,
                'created_by' => $userId
            ]);
            
            $idLote = (int) $db->lastInsertId();
            $db->commit();
            return $idLote;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al generar Lote: " . $e->getMessage());
            throw new Exception("Error al generar el encabezado de la nómina.");
        }
    }

    public function agregarConceptoManual(array $datos): bool
    {
        $db = $this->db();
        try {
            $stmt = $db->prepare("INSERT INTO rrhh_nominas_conceptos 
                (id_detalle_nomina, tipo, categoria, descripcion, monto, es_automatico) 
                VALUES (:id_detalle, :tipo, :categoria, :descripcion, :monto, 0)");
            
            $stmt->execute([
                'id_detalle' => (int) $datos['id_detalle_nomina'],
                'tipo' => strtoupper($datos['tipo_concepto']),
                'categoria' => $datos['categoria_concepto'],
                'descripcion' => $datos['descripcion'],
                'monto' => (float) $datos['monto']
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error al agregar concepto manual: " . $e->getMessage());
            return false;
        }
    }

    public function aprobarLote(int $idLote): bool
    {
        $db = $this->db();
        try {
            $db->beginTransaction();

            $lote = $this->obtenerLotePorId($idLote);
            if (!$lote || strtoupper(trim((string) $lote['estado'])) !== 'BORRADOR') {
                throw new Exception("El lote no es válido o ya fue aprobado.");
            }

            $nominaCalculada = $this->calcularNominaEnMemoria($lote);

            $stmtDelAuto = $db->prepare("DELETE FROM rrhh_nominas_conceptos 
                                         WHERE es_automatico = 1 AND id_detalle_nomina IN (
                                            SELECT id FROM rrhh_nominas_detalles WHERE id_nomina = :id_nomina
                                         )");
            $stmtDelAuto->execute(['id_nomina' => $idLote]);

            $stmtUpdateDet = $db->prepare("UPDATE rrhh_nominas_detalles 
                SET dias_pagados = :dp, dias_falta = :df, minutos_tardanza = :mt, 
                    sueldo_base_calculado = :sbc, total_percepciones = :tp, total_deducciones = :td, neto_a_pagar = :neto
                WHERE id = :id");

            $stmtConcepto = $db->prepare("INSERT INTO rrhh_nominas_conceptos 
                (id_detalle_nomina, tipo, categoria, descripcion, monto, es_automatico) 
                VALUES (:id_det, :tipo, :cat, :desc, :monto, 1)");
                
            $stmtMarcarAsistencia = $db->prepare("UPDATE asistencia_registros 
                SET id_nomina_pago = :id_lote 
                WHERE id_tercero = :id_tercero 
                  AND fecha BETWEEN :desde AND :hasta 
                  AND id_nomina_pago IS NULL");
                  
            $stmtPagarAdelanto = $db->prepare("UPDATE rrhh_adelantos 
                SET saldo_pendiente = saldo_pendiente - :descuento,
                    estado = IF(saldo_pendiente - :descuento <= 0, 'PAGADO', 'PENDIENTE')
                WHERE id = :id_adelanto");

            $loteBruto = 0; $loteDeducciones = 0; $loteNeto = 0;
            $idsValidos = []; 

            foreach ($nominaCalculada as $calc) {
                if ($calc['neto_a_pagar'] <= 0 && $calc['dias_pagados'] == 0 && $calc['monto_bonos'] == 0) {
                    continue;
                }

                $idsValidos[] = $calc['id']; 

                $stmtUpdateDet->execute([
                    'dp' => $calc['dias_pagados'],
                    'df' => $calc['dias_falta'],
                    'mt' => $calc['minutos_tardanza'],
                    'sbc' => $calc['sueldo_base_calculado'],
                    'tp' => $calc['total_percepciones'],
                    'td' => $calc['total_deducciones'],
                    'neto' => $calc['neto_a_pagar'],
                    'id' => $calc['id']
                ]);

                $stmtMarcarAsistencia->execute([
                    'id_lote' => $idLote,
                    'id_tercero' => $calc['id_tercero'],
                    'desde' => $lote['fecha_inicio'],
                    'hasta' => $lote['fecha_fin']
                ]);

                if ($calc['sueldo_base_calculado'] > 0) {
                    $stmtConcepto->execute([
                        'id_det' => $calc['id'], 'tipo' => 'PERCEPCION', 'cat' => 'Sueldo Base',
                        'desc' => 'Sueldo proporcional a ' . $calc['dias_pagados'] . ' días', 'monto' => $calc['sueldo_base_calculado']
                    ]);
                }

                if ($calc['descuento_tardanzas'] > 0) {
                    $stmtConcepto->execute([
                        'id_det' => $calc['id'], 'tipo' => 'DEDUCCION', 'cat' => 'Tardanza',
                        'desc' => 'Descuento por ' . $calc['minutos_tardanza'] . ' minutos', 'monto' => $calc['descuento_tardanzas']
                    ]);
                }

                if ($calc['descuento_adelanto'] > 0) {
                    $adelantos = json_decode($calc['adelantos_aplicados'], true);
                    foreach ($adelantos as $ad) {
                        $stmtPagarAdelanto->execute([
                            'descuento' => $ad['monto'],
                            'id_adelanto' => $ad['id']
                        ]);
                    }
                    $stmtConcepto->execute([
                        'id_det' => $calc['id'], 'tipo' => 'DEDUCCION', 'cat' => 'Adelanto de Sueldo',
                        'desc' => 'Cobro automático de adelanto/préstamo', 'monto' => $calc['descuento_adelanto']
                    ]);
                }

                $loteBruto += $calc['total_percepciones'];
                $loteDeducciones += $calc['total_deducciones'];
                $loteNeto += $calc['neto_a_pagar'];
            }

            if (!empty($idsValidos)) {
                $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
                $stmtDelHuerfanos = $db->prepare("DELETE FROM rrhh_nominas_detalles WHERE id_nomina = ? AND id NOT IN ($placeholders)");
                $stmtDelHuerfanos->execute(array_merge([$idLote], $idsValidos));
            } else {
                $db->prepare("DELETE FROM rrhh_nominas_detalles WHERE id_nomina = ?")->execute([$idLote]);
            }

            $stmtUpdateLote = $db->prepare("UPDATE rrhh_nominas 
                SET estado = 'APROBADO', total_bruto = :tb, total_deducciones = :td, total_neto = :tn, cantidad_empleados = :cant
                WHERE id = :id");
            $stmtUpdateLote->execute([
                'tb' => $loteBruto, 'td' => $loteDeducciones, 'tn' => $loteNeto,
                'cant' => count($idsValidos), 'id' => $idLote
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al aprobar lote: " . $e->getMessage());
            return false;
        }
    }

    public function pagarLoteNomina(array $datos, int $userId): bool
    {
        $db = $this->db();
        $idLote = (int) $datos['id_lote'];
        
        try {
            $db->beginTransaction();

            $stmtLote = $db->prepare("SELECT * FROM rrhh_nominas WHERE id = :id FOR UPDATE");
            $stmtLote->execute(['id' => $idLote]);
            $lote = $stmtLote->fetch(PDO::FETCH_ASSOC);

            if (!$lote || $lote['estado'] !== 'APROBADO') {
                throw new Exception("El lote no existe o no está en estado APROBADO.");
            }

            $stmtCuenta = $db->prepare("SELECT saldo_actual FROM tesoreria_cuentas WHERE id = :id_cuenta FOR UPDATE");
            $stmtCuenta->execute(['id_cuenta' => $datos['id_cuenta']]);
            $cuenta = $stmtCuenta->fetch(PDO::FETCH_ASSOC);

            if (!$cuenta || (float)$cuenta['saldo_actual'] < (float)$lote['total_neto']) {
                throw new Exception("Saldo insuficiente en la cuenta de tesorería seleccionada.");
            }

            $stmtMov = $db->prepare("INSERT INTO tesoreria_movimientos 
                (tipo, origen, id_origen, id_cuenta, fecha, moneda, monto, referencia, observaciones, estado, created_by) 
                VALUES ('PAGO', 'LOTE_NOMINA', :id_lote, :id_cuenta, :fecha, 'PEN', :monto, :referencia, :observaciones, 'CONFIRMADO', :created_by)");
            
            $stmtMov->execute([
                'id_lote' => $idLote,
                'id_cuenta' => $datos['id_cuenta'],
                'fecha' => $datos['fecha_pago'],
                'monto' => $lote['total_neto'],
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => "Pago de Lote de Nómina: " . $lote['nombre'],
                'created_by' => $userId
            ]);

            $stmtUpdateCuenta = $db->prepare("UPDATE tesoreria_cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id_cuenta");
            $stmtUpdateCuenta->execute([
                'monto' => $lote['total_neto'],
                'id_cuenta' => $datos['id_cuenta']
            ]);

            $stmtUpdateLote = $db->prepare("UPDATE rrhh_nominas 
                SET estado = 'PAGADO', fecha_pago = :fecha, id_cuenta_origen = :id_cuenta, referencia_pago = :ref 
                WHERE id = :id_lote");
            
            $stmtUpdateLote->execute([
                'fecha' => $datos['fecha_pago'],
                'id_cuenta' => $datos['id_cuenta'],
                'ref' => $datos['referencia'] ?? null,
                'id_lote' => $idLote
            ]);

            $db->commit();
            return true;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en pagarLoteNomina: " . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    public function obtenerDatosBoletaPdf(int $idDetalle): ?array
    {
        $sqlCabecera = "SELECT 
                            nd.*,
                            t.nombre_completo, 
                            t.numero_documento,
                            te.cargo, 
                            te.sueldo_basico,
                            n.referencia AS referencia_lote, 
                            n.nombre AS nombre_lote, 
                            n.fecha_inicio, 
                            n.fecha_fin, 
                            n.fecha_pago,
                            n.estado AS estado_lote
                        FROM rrhh_nominas_detalles nd
                        INNER JOIN rrhh_nominas n ON n.id = nd.id_nomina
                        INNER JOIN terceros t ON t.id = nd.id_tercero
                        INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                        WHERE nd.id = :id_detalle";
        
        $stmtC = $this->db()->prepare($sqlCabecera);
        $stmtC->execute(['id_detalle' => $idDetalle]);
        $boleta = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$boleta) {
            return null;
        }

        $sqlConceptos = "SELECT tipo, categoria, descripcion, monto, es_automatico 
                         FROM rrhh_nominas_conceptos 
                         WHERE id_detalle_nomina = :id_detalle 
                         ORDER BY tipo DESC, id ASC";
                         
        $stmtConc = $this->db()->prepare($sqlConceptos);
        $stmtConc->execute(['id_detalle' => $idDetalle]);
        
        $boleta['conceptos'] = $stmtConc->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $boleta;
    }

    /**
     * Calcula las horas reales trabajadas por un empleado emparejando sus marcaciones.
     * Detecta si hay conflictos (número impar de marcaciones).
     */
    private function calcularHorasReales(int $id_empleado, string $fecha_inicio, string $fecha_fin): array
    {
        // 1. Leer los registros procesados de asistencia por día.
        //    Se usa asistencia_registros porque rrhh_asistencia no existe en el esquema actual.
        $sqlAsistencia = "SELECT fecha, hora_ingreso, hora_salida, estado_asistencia
                          FROM asistencia_registros
                          WHERE id_tercero = :id_empleado
                            AND fecha BETWEEN :fecha_inicio AND :fecha_fin
                          ORDER BY fecha ASC";
        
        $stmt = $this->db()->prepare($sqlAsistencia);
        $stmt->execute([
            ':id_empleado' => $id_empleado,
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        $asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalHorasAcumuladas = 0.0;
        $diasTrabajados = 0;
        $tieneConflicto = false;

        // 2. Procesar cada día de forma independiente
        foreach ($asistencias as $asistencia) {
            $fecha = (string) ($asistencia['fecha'] ?? '');
            $horaIngreso = $asistencia['hora_ingreso'] ?? null;
            $horaSalida = $asistencia['hora_salida'] ?? null;
            $estado = strtoupper((string) ($asistencia['estado_asistencia'] ?? ''));

            // Conflicto cuando no hay un par completo de horas o el estado ya viene incompleto.
            if (empty($horaIngreso) || empty($horaSalida) || $estado === 'INCOMPLETO') {
                $tieneConflicto = true;
                continue;
            }

            $horaEntradaTs = strtotime($fecha . ' ' . substr((string) $horaIngreso, 0, 8));
            $horaSalidaTs = strtotime($fecha . ' ' . substr((string) $horaSalida, 0, 8));

            if ($horaEntradaTs === false || $horaSalidaTs === false || $horaSalidaTs <= $horaEntradaTs) {
                $tieneConflicto = true;
                continue;
            }

            $totalHorasAcumuladas += ($horaSalidaTs - $horaEntradaTs) / 3600;
            $diasTrabajados++;
        }

        return [
            'dias_trabajados' => $diasTrabajados,
            'horas_acumuladas' => round($totalHorasAcumuladas, 2),
            'tiene_conflicto' => $tieneConflicto
        ];
    }
}
