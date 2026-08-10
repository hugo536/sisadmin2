<?php
declare(strict_types=1);

class PlanillasModel extends Modelo
{
    private function resolverPagoDiario(float $sueldoBasico, string $tipoPago): float
    {
        $tipoPagoEmpleado = strtoupper(trim($tipoPago));

        if ($tipoPagoEmpleado === 'SEMANAL') {
            return $sueldoBasico;
        }

        if ($tipoPagoEmpleado === 'QUINCENAL') {
            return $sueldoBasico / 15;
        }

        return $sueldoBasico / 30; // Mensual por defecto
    }

    public function obtenerLotesRecientes(int $limite = 15): array
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
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerDetallesLote(int $idLote): array
    {
        $lote = $this->obtenerLotePorId($idLote);
        
        // 1. Si es BORRADOR, calculamos en tiempo real con las reglas actuales
        if ($lote && strtoupper(trim((string)$lote['estado'])) === 'BORRADOR') {
            return $this->calcularNominaEnMemoria($lote);
        }

        // 2. Si es CERRADO (APROBADO), cargamos los datos congelados y extraemos los conceptos
        $sql = "SELECT
                    nd.id,
                    nd.id_tercero,
                    t.nombre_completo,
                    t.numero_documento,
                    te.cargo,
                    nd.dias_pagados,
                    nd.sueldo_base_calculado,
                    nd.total_percepciones,
                    nd.total_deducciones,
                    nd.neto_a_pagar,
                    (nd.dias_pagados * 8) AS horas_acumuladas,
                    0 AS horas_extras, -- Solo referencial para la vista en cerrados
                    
                    COALESCE((SELECT SUM(monto) FROM rrhh_nominas_conceptos nc 
                              WHERE nc.id_detalle_nomina = nd.id AND nc.tipo = 'PERCEPCION' AND nc.es_automatico = 0), 0) AS monto_bonos,
                              
                    COALESCE((SELECT SUM(monto) FROM rrhh_nominas_conceptos nc 
                              WHERE nc.id_detalle_nomina = nd.id AND nc.tipo = 'DEDUCCION' AND nc.categoria = 'Tardanza'), 0) AS descuento_tardanzas,
                              
                    COALESCE((SELECT SUM(monto) FROM rrhh_nominas_conceptos nc 
                              WHERE nc.id_detalle_nomina = nd.id AND nc.tipo = 'DEDUCCION' AND nc.categoria = 'Adelanto de Sueldo'), 0) AS descuento_adelanto,
                              
                    0 AS tiene_conflicto -- Los cerrados ya pasaron la validación
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

        require_once BASE_PATH . '/app/models/rrhh/AsistenciaModel.php';
        $asistenciaModel = new AsistenciaModel();

        // Obtener empleados activos
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

        // Generar detalles temporales en BD si no existen
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

        // Obtener la asistencia y aplicar reglas del motor
        $sqlAsistencia = "SELECT id_tercero, fecha, hora_ingreso, hora_salida,
                                 marcas_ingresos, marcas_salidas, estado_asistencia, minutos_tardanza, horas_trabajadas, horas_extras
                          FROM asistencia_registros
                          WHERE fecha BETWEEN :desde AND :hasta
                          AND (id_nomina_pago IS NULL OR id_nomina_pago = :id_lote)";
                          
        $stmtAsist = $db->prepare($sqlAsistencia);
        $stmtAsist->execute(['desde' => $fechaInicio, 'hasta' => $fechaFin, 'id_lote' => $idLote]);
        $registrosAsistencia = $stmtAsist->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mapaAsistencia = [];
        foreach ($registrosAsistencia as $ar) {
            $idT = (int)$ar['id_tercero'];
            $fecha = $ar['fecha'];

            $marcasDia = [];
            if (!empty($ar['marcas_ingresos'])) $marcasDia = array_merge($marcasDia, explode('|', (string)$ar['marcas_ingresos']));
            elseif (!empty($ar['hora_ingreso'])) $marcasDia[] = (string)$ar['hora_ingreso'];

            if (!empty($ar['marcas_salidas'])) $marcasDia = array_merge($marcasDia, explode('|', (string)$ar['marcas_salidas']));
            elseif (!empty($ar['hora_salida'])) $marcasDia[] = (string)$ar['hora_salida'];

            $marcasDia = array_values(array_filter(array_map(static fn($m) => trim((string)$m), $marcasDia), static fn($m) => $m !== ''));
            sort($marcasDia);

            if (!empty($marcasDia)) {
                $resumenVivo = $asistenciaModel->calcularResumenDesdeMarcas($idT, $fecha, $marcasDia);
                $ar['estado_asistencia'] = $resumenVivo['estado_asistencia'] ?? $ar['estado_asistencia'];
                $ar['minutos_tardanza'] = (int)($resumenVivo['minutos_tardanza'] ?? $ar['minutos_tardanza']);
                $ar['horas_trabajadas'] = (float)($resumenVivo['horas_trabajadas'] ?? $ar['horas_trabajadas']);
                $ar['horas_extras'] = (float)($resumenVivo['horas_extras'] ?? $ar['horas_extras']);
            }
            
            if (!isset($mapaAsistencia[$idT])) {
                $mapaAsistencia[$idT] = [
                    'asistidos' => 0, 'justificados' => 0, 'faltas' => 0,
                    'tardanzas' => 0, 'horas_trabajadas' => 0.0, 'horas_extras' => 0.0, 'tiene_conflicto' => false
                ];
            }

            $estado = strtoupper((string)$ar['estado_asistencia']);
            $ingresos = !empty($ar['marcas_ingresos']) ? explode('|', $ar['marcas_ingresos']) : (!empty($ar['hora_ingreso']) ? [$ar['hora_ingreso']] : []);
            $salidas = !empty($ar['marcas_salidas']) ? explode('|', $ar['marcas_salidas']) : (!empty($ar['hora_salida']) ? [$ar['hora_salida']] : []);
            
            $diaValido = (count($ingresos) === count($salidas)) && (count($ingresos) > 0);
            $esJustificada = in_array($estado, ['FALTA JUSTIFICADA', 'PERMISO', 'VACACIONES', 'DESCANSO MEDICO', 'TARDANZA JUSTIFICADA', 'OLVIDO MARCACION']);

            if (!$diaValido && !$esJustificada && count($ingresos) > 0) {
                $estado = 'INCOMPLETO'; 
            }

            if ($estado === 'INCOMPLETO') {
                $mapaAsistencia[$idT]['tiene_conflicto'] = true;
            }

            if (in_array($estado, ['PUNTUAL', 'TARDANZA', 'TARDANZA JUSTIFICADA'])) {
                $mapaAsistencia[$idT]['asistidos']++;
                $mapaAsistencia[$idT]['horas_trabajadas'] += (float) ($ar['horas_trabajadas'] ?? 0);
                $mapaAsistencia[$idT]['horas_extras'] += (float) ($ar['horas_extras'] ?? 0);
            } elseif ($esJustificada && $estado !== 'TARDANZA JUSTIFICADA') {
                $mapaAsistencia[$idT]['justificados']++;
            } elseif ($estado === 'FALTA') {
                $mapaAsistencia[$idT]['faltas']++;
            }
            
            $mapaAsistencia[$idT]['tardanzas'] += (int) $ar['minutos_tardanza'];
        }

        // Obtener adelantos pendientes para descontar automáticamente
        $sqlAdelantos = "SELECT id, id_tercero, saldo_pendiente FROM rrhh_adelantos WHERE estado = 'PENDIENTE' AND saldo_pendiente > 0 ORDER BY fecha ASC";
        $mapaAdelantos = [];
        try {
            $adelantosPendientes = $db->query($sqlAdelantos)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($adelantosPendientes as $ad) {
                $mapaAdelantos[$ad['id_tercero']][] = $ad;
            }
        } catch (Exception $e) { /* La tabla aún no existe, omitir */ }

        // Obtener ajustes manuales guardados (Bonos/Deducciones extras)
        $sqlManuales = "SELECT nc.id_detalle_nomina, nc.tipo, nc.categoria, nc.descripcion, nc.monto 
                        FROM rrhh_nominas_conceptos nc
                        INNER JOIN rrhh_nominas_detalles nd ON nd.id = nc.id_detalle_nomina
                        WHERE nd.id_nomina = :id_nomina AND nc.es_automatico = 0";
        $stmtMan = $db->prepare($sqlManuales);
        $stmtMan->execute(['id_nomina' => $idLote]);
        $conceptosManuales = $stmtMan->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mapaManuales = [];
        foreach ($conceptosManuales as $cm) {
            $idD = $cm['id_detalle_nomina'];
            if (!isset($mapaManuales[$idD])) $mapaManuales[$idD] = ['percepciones' => 0, 'deducciones' => 0, 'bonos' => 0, 'movimientos' => []];
            
            if ($cm['tipo'] === 'PERCEPCION') {
                $mapaManuales[$idD]['percepciones'] += $cm['monto'];
                $mapaManuales[$idD]['bonos'] += $cm['monto'];
            } else {
                $mapaManuales[$idD]['deducciones'] += $cm['monto'];
            }
        }

        // Calcular la nómina final para la vista
        $resultados = [];
        foreach ($empleadosProcesar as $emp) {
            $idTercero = $emp['id'];
            $idDetalle = $emp['id_detalle'];
            
            $asis = $mapaAsistencia[$idTercero] ?? [
                'asistidos' => 0, 'justificados' => 0, 'faltas' => 0, 
                'tardanzas' => 0, 'horas_trabajadas' => 0.0, 'horas_extras' => 0.0, 'tiene_conflicto' => false
            ];
            
            $tieneConflicto = $asis['tiene_conflicto'];
            $diasPagados = $asis['asistidos'] + $asis['justificados'];
            $horasAcumuladas = $asis['horas_trabajadas'];
            
            $pagoDiario = $this->resolverPagoDiario((float) $emp['sueldo_basico'], (string)($emp['tipo_pago'] ?? 'MENSUAL'));
            $pagoPorHora = $pagoDiario / 8;

            if ($tieneConflicto) {
                $sueldoBaseCalculado = 0; $diasPagados = 0; $horasAcumuladas = 0; $descuentoTardanzas = 0;
            } else {
                $sueldoBaseCalculado = $pagoDiario * $diasPagados;
                $horasEsperadas = $diasPagados * 8; 
                
                if ($horasAcumuladas < $horasEsperadas) {
                    $horasPerdidas = $horasEsperadas - $horasAcumuladas;
                    $descuentoTardanzas = round($horasPerdidas * $pagoPorHora, 2);
                } else {
                    $descuentoTardanzas = 0;
                }
            }

            // Las horas extras se pagan 1x (Valor normal) si el ERP lo dicta, 
            // esto se puede parametrizar más adelante.
            $horasExtras = $tieneConflicto ? 0 : $asis['horas_extras'];
            $pagoHorasExtras = round($pagoPorHora * $horasExtras, 2);

            $manuales = $mapaManuales[$idDetalle] ?? ['percepciones' => 0, 'deducciones' => 0, 'bonos' => 0];

            $totalPercepciones = $sueldoBaseCalculado + $pagoHorasExtras + $manuales['percepciones'];
            $deduccionesPrevias = $descuentoTardanzas + $manuales['deducciones'];
            
            $netoTemporal = $totalPercepciones - $deduccionesPrevias;
            
            $descuentoAdelanto = 0;
            $adelantosAplicados = [];
            
            // Cobro automático de adelantos si el neto alcanza
            if (!$tieneConflicto && isset($mapaAdelantos[$idTercero]) && $netoTemporal > 0) {
                foreach ($mapaAdelantos[$idTercero] as &$ad) {
                    if ($netoTemporal <= 0) break;
                    $aDescontar = min($netoTemporal, (float)$ad['saldo_pendiente']);
                    $descuentoAdelanto += $aDescontar;
                    $netoTemporal -= $aDescontar;
                    $adelantosAplicados[] = ['id' => $ad['id'], 'monto' => $aDescontar];
                    $ad['saldo_pendiente'] -= $aDescontar;
                }
            }

            $totalDeducciones = round($deduccionesPrevias + $descuentoAdelanto, 2);
            $totalPercepciones = round($totalPercepciones, 2);
            $netoFinal = round($totalPercepciones - $totalDeducciones, 2);

            $resultados[] = [
                'id' => $idDetalle,
                'id_tercero' => $idTercero,
                'nombre_completo' => $emp['nombre_completo'],
                'numero_documento' => $emp['numero_documento'],
                'cargo' => $emp['cargo'],
                'frecuencia' => $emp['tipo_pago'],
                'dias_pagados' => $diasPagados,
                'horas_acumuladas' => round($horasAcumuladas, 2),
                'horas_extras' => round($horasExtras, 2),
                'pago_horas_extras' => $pagoHorasExtras,
                'sueldo_base_calculado' => round($sueldoBaseCalculado, 2),
                'total_percepciones' => $totalPercepciones,
                'total_deducciones' => $totalDeducciones,
                'neto_a_pagar' => max(0, $netoFinal),
                'monto_bonos' => round($manuales['bonos'], 2),
                'descuento_tardanzas' => round($descuentoTardanzas, 2),
                'descuento_adelanto' => round($descuentoAdelanto, 2),
                'adelantos_aplicados' => json_encode($adelantosAplicados),
                'tiene_conflicto' => $tieneConflicto
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
        
        $nombreLote = "NOM - " . date('d/m/Y', strtotime($fechaInicio)) . " al " . date('d/m/Y', strtotime($fechaFin));

        try {
            $db->beginTransaction();

            $stmtLote = $db->prepare("INSERT INTO rrhh_nominas 
                (referencia, nombre, fecha_inicio, fecha_fin, frecuencia, estado, created_by) 
                VALUES (:referencia, :nombre, :fecha_inicio, :fecha_fin, :frecuencia, 'BORRADOR', :created_by)");
            
            $referencia = 'NOM-' . date('Ym') . '-' . rand(1000, 9999);
            
            $stmtLote->execute([
                'referencia' => $referencia,
                'nombre' => $nombreLote,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'frecuencia' => $frecuencia,
                'created_by' => $userId
            ]);
            
            $idLote = (int) $db->lastInsertId();
            $db->commit();
            return $idLote;

        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Error al generar el encabezado de la nómina.");
        }
    }

    public function agregarConceptoManual(array $datos): bool
    {
        $db = $this->db();
        try {
            $movimientos = $datos['movimientos'] ?? [];
            $idDetalle = (int) ($datos['id_detalle_nomina'] ?? 0);

            if ($idDetalle <= 0) throw new InvalidArgumentException('Detalle de nómina inválido.');

            $stmtDetalle = $db->prepare('SELECT n.estado FROM rrhh_nominas_detalles nd
                                         INNER JOIN rrhh_nominas n ON n.id = nd.id_nomina
                                         WHERE nd.id = :id_detalle LIMIT 1');
            $stmtDetalle->execute(['id_detalle' => $idDetalle]);
            $detalle = $stmtDetalle->fetch(PDO::FETCH_ASSOC);
            
            if (!$detalle || strtoupper(trim((string) $detalle['estado'])) !== 'BORRADOR') {
                throw new InvalidArgumentException('Solo se pueden editar movimientos en lotes BORRADOR.');
            }

            $db->beginTransaction();

            $db->prepare('DELETE FROM rrhh_nominas_conceptos WHERE id_detalle_nomina = :id_detalle AND es_automatico = 0')
               ->execute(['id_detalle' => $idDetalle]);

            $stmt = $db->prepare("INSERT INTO rrhh_nominas_conceptos
                (id_detalle_nomina, tipo, categoria, descripcion, monto, es_automatico)
                VALUES (:id_detalle, :tipo, :categoria, :descripcion, :monto, 0)");

            $vistos = [];
            foreach ($movimientos as $mov) {
                $tipo = strtoupper(trim((string)($mov['tipo_concepto'] ?? '')));
                $categoria = trim((string)($mov['categoria_concepto'] ?? ''));
                $descripcion = trim((string)($mov['descripcion'] ?? ''));
                $monto = (float)($mov['monto'] ?? 0);

                if (!in_array($tipo, ['PERCEPCION', 'DEDUCCION']) || $categoria === '' || $descripcion === '' || $monto <= 0) {
                    continue; // Saltar inválidos silenciosamente
                }

                $llave = $tipo . '::' . strtolower($categoria) . '::' . strtolower($descripcion);
                if (isset($vistos[$llave])) throw new InvalidArgumentException('Hay movimientos repetidos.');
                $vistos[$llave] = true;

                $stmt->execute([
                    'id_detalle' => $idDetalle,
                    'tipo' => $tipo,
                    'categoria' => $categoria,
                    'descripcion' => $descripcion,
                    'monto' => $monto
                ]);
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return false;
        }
    }

    public function obtenerMovimientosManualesDetalle(int $idDetalle): array
    {
        $sql = 'SELECT nc.tipo, nc.categoria, nc.descripcion, nc.monto
                FROM rrhh_nominas_conceptos nc
                INNER JOIN rrhh_nominas_detalles nd ON nd.id = nc.id_detalle_nomina
                INNER JOIN rrhh_nominas n ON n.id = nd.id_nomina
                WHERE nc.id_detalle_nomina = :id_detalle AND nc.es_automatico = 0 AND UPPER(TRIM(n.estado)) = "BORRADOR"
                ORDER BY nc.id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_detalle' => $idDetalle]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function aprobarLote(int $idLote): bool
    {
        $db = $this->db();
        try {
            $db->beginTransaction();

            $lote = $this->obtenerLotePorId($idLote);
            if (!$lote || strtoupper(trim((string) $lote['estado'])) !== 'BORRADOR') {
                throw new Exception("El lote no es válido o ya fue cerrado.");
            }

            $nominaCalculada = $this->calcularNominaEnMemoria($lote);

            // Eliminar conceptos automáticos antiguos
            $db->prepare("DELETE FROM rrhh_nominas_conceptos WHERE es_automatico = 1 AND id_detalle_nomina IN (SELECT id FROM rrhh_nominas_detalles WHERE id_nomina = ?)")
               ->execute([$idLote]);

            $stmtUpdateDet = $db->prepare("UPDATE rrhh_nominas_detalles 
                SET dias_pagados = :dp, sueldo_base_calculado = :sbc, 
                    total_percepciones = :tp, total_deducciones = :td, neto_a_pagar = :neto
                WHERE id = :id");

            $stmtConcepto = $db->prepare("INSERT INTO rrhh_nominas_conceptos 
                (id_detalle_nomina, tipo, categoria, descripcion, monto, es_automatico) 
                VALUES (:id_det, :tipo, :cat, :desc, :monto, 1)");
                
            $stmtMarcarAsistencia = $db->prepare("UPDATE asistencia_registros 
                SET id_nomina_pago = :id_lote 
                WHERE id_tercero = :id_tercero AND fecha BETWEEN :desde AND :hasta AND id_nomina_pago IS NULL");
                  
            $stmtPagarAdelanto = $db->prepare("UPDATE rrhh_adelantos 
                SET saldo_pendiente = saldo_pendiente - :descuento,
                    estado = IF(saldo_pendiente - :descuento <= 0, 'PAGADO', 'PENDIENTE')
                WHERE id = :id_adelanto");

            $loteBruto = 0; $loteDeducciones = 0; $loteNeto = 0;
            $idsValidos = []; 

            foreach ($nominaCalculada as $calc) {
                if ($calc['neto_a_pagar'] <= 0 && $calc['dias_pagados'] == 0 && $calc['monto_bonos'] == 0) continue;

                $idsValidos[] = $calc['id']; 

                $stmtUpdateDet->execute([
                    'dp' => $calc['dias_pagados'],
                    'sbc' => $calc['sueldo_base_calculado'],
                    'tp' => $calc['total_percepciones'],
                    'td' => $calc['total_deducciones'],
                    'neto' => $calc['neto_a_pagar'],
                    'id' => $calc['id']
                ]);

                $stmtMarcarAsistencia->execute([
                    'id_lote' => $idLote, 'id_tercero' => $calc['id_tercero'],
                    'desde' => $lote['fecha_inicio'], 'hasta' => $lote['fecha_fin']
                ]);

                // Insertar los conceptos finales para la boleta
                if ($calc['sueldo_base_calculado'] > 0) {
                    $stmtConcepto->execute(['id_det' => $calc['id'], 'tipo' => 'PERCEPCION', 'cat' => 'Sueldo Base', 'desc' => 'Sueldo por ' . $calc['dias_pagados'] . ' días', 'monto' => $calc['sueldo_base_calculado']]);
                }
                if ($calc['pago_horas_extras'] > 0) {
                    $stmtConcepto->execute(['id_det' => $calc['id'], 'tipo' => 'PERCEPCION', 'cat' => 'Horas Extras', 'desc' => 'Pago por ' . $calc['horas_extras'] . ' horas', 'monto' => $calc['pago_horas_extras']]);
                }
                if ($calc['descuento_tardanzas'] > 0) {
                    $stmtConcepto->execute(['id_det' => $calc['id'], 'tipo' => 'DEDUCCION', 'cat' => 'Tardanza', 'desc' => 'Descuento por tardanzas/salidas', 'monto' => $calc['descuento_tardanzas']]);
                }
                if ($calc['descuento_adelanto'] > 0) {
                    $adelantos = json_decode($calc['adelantos_aplicados'], true);
                    foreach ($adelantos as $ad) {
                        $stmtPagarAdelanto->execute(['descuento' => $ad['monto'], 'id_adelanto' => $ad['id']]);
                    }
                    $stmtConcepto->execute(['id_det' => $calc['id'], 'tipo' => 'DEDUCCION', 'cat' => 'Adelanto de Sueldo', 'desc' => 'Cobro automático de préstamo', 'monto' => $calc['descuento_adelanto']]);
                }

                $loteBruto += $calc['total_percepciones'];
                $loteDeducciones += $calc['total_deducciones'];
                $loteNeto += $calc['neto_a_pagar'];
            }

            // Limpiar empleados vacíos
            if (!empty($idsValidos)) {
                $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
                $db->prepare("DELETE FROM rrhh_nominas_detalles WHERE id_nomina = ? AND id NOT IN ($placeholders)")->execute(array_merge([$idLote], $idsValidos));
            } else {
                $db->prepare("DELETE FROM rrhh_nominas_detalles WHERE id_nomina = ?")->execute([$idLote]);
            }

            // Cerrar Lote (Guardar como APROBADO para la BD)
            $db->prepare("UPDATE rrhh_nominas SET estado = 'APROBADO', total_bruto = :tb, total_deducciones = :td, total_neto = :tn, cantidad_empleados = :cant WHERE id = :id")
               ->execute(['tb' => $loteBruto, 'td' => $loteDeducciones, 'tn' => $loteNeto, 'cant' => count($idsValidos), 'id' => $idLote]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    public function obtenerDatosBoletaPdf(int $idDetalle): ?array
    {
        $sqlCabecera = "SELECT nd.*, t.nombre_completo, t.numero_documento, te.cargo, te.sueldo_basico,
                               n.referencia AS referencia_lote, n.nombre AS nombre_lote, 
                               n.fecha_inicio, n.fecha_fin, n.fecha_pago, n.estado AS estado_lote
                        FROM rrhh_nominas_detalles nd
                        INNER JOIN rrhh_nominas n ON n.id = nd.id_nomina
                        INNER JOIN terceros t ON t.id = nd.id_tercero
                        INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                        WHERE nd.id = :id_detalle";
        $stmtC = $this->db()->prepare($sqlCabecera);
        $stmtC->execute(['id_detalle' => $idDetalle]);
        $boleta = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$boleta) return null;

        $sqlConceptos = "SELECT tipo, categoria, descripcion, monto, es_automatico 
                         FROM rrhh_nominas_conceptos WHERE id_detalle_nomina = :id_detalle ORDER BY tipo DESC, id ASC";
        $stmtConc = $this->db()->prepare($sqlConceptos);
        $stmtConc->execute(['id_detalle' => $idDetalle]);
        $boleta['conceptos'] = $stmtConc->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $boleta;
    }

    public function obtenerBoletasMasivasPdf(int $idLote): array
    {
        $sqlCabecera = "SELECT nd.*, t.nombre_completo, t.numero_documento, te.cargo, te.sueldo_basico,
                               n.referencia AS referencia_lote, n.nombre AS nombre_lote, 
                               n.fecha_inicio, n.fecha_fin, n.fecha_pago, n.estado AS estado_lote
                        FROM rrhh_nominas_detalles nd
                        INNER JOIN rrhh_nominas n ON n.id = nd.id_nomina
                        INNER JOIN terceros t ON t.id = nd.id_tercero
                        INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                        WHERE nd.id_nomina = :id_lote AND nd.neto_a_pagar > 0
                        ORDER BY t.nombre_completo ASC";
        $stmtC = $this->db()->prepare($sqlCabecera);
        $stmtC->execute(['id_lote' => $idLote]);
        $boletas = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($boletas)) return [];

        $sqlConceptos = "SELECT nc.* FROM rrhh_nominas_conceptos nc
                         INNER JOIN rrhh_nominas_detalles nd ON nd.id = nc.id_detalle_nomina
                         WHERE nd.id_nomina = :id_lote ORDER BY nc.tipo DESC, nc.id ASC";
        $stmtConc = $this->db()->prepare($sqlConceptos);
        $stmtConc->execute(['id_lote' => $idLote]);
        $conceptos = $stmtConc->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $conceptosAgrupados = [];
        foreach ($conceptos as $c) {
            $conceptosAgrupados[$c['id_detalle_nomina']][] = $c;
        }

        $sqlAsistencia = "SELECT fecha, estado_asistencia, horas_trabajadas, horas_extras
                          FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha BETWEEN :fecha_inicio AND :fecha_fin";
        $stmtAsistencia = $this->db()->prepare($sqlAsistencia);

        foreach ($boletas as &$b) {
            $b['conceptos'] = $conceptosAgrupados[$b['id']] ?? [];

            $stmtAsistencia->execute([
                'id_tercero' => (int) $b['id_tercero'],
                'fecha_inicio' => $b['fecha_inicio'],
                'fecha_fin' => $b['fecha_fin'],
            ]);
            
            $asistencias = $stmtAsistencia->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $resumenDias = [];
            
            foreach ($asistencias as $asistencia) {
                $resumenDias[] = [
                    'fecha' => $asistencia['fecha'],
                    'estado' => strtoupper((string) ($asistencia['estado_asistencia'] ?? '')),
                    'horas_normales' => round((float) ($asistencia['horas_trabajadas'] ?? 0), 2),
                    'horas_extras' => round((float) ($asistencia['horas_extras'] ?? 0), 2),
                ];
            }
            $b['resumen_dias'] = $resumenDias;
        }
        unset($b);

        return $boletas;
    }
}