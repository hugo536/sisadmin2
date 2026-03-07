<?php
declare(strict_types=1);

class PlanillasModel extends Modelo
{
    /**
     * ========================================================================
     * 1. LECTURA DE DATOS (Muestra en la vista)
     * ========================================================================
     */

    // Obtiene los lotes recientes para el Dropdown superior
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

    // Obtiene la cabecera (KPIs) de un Lote específico
    public function obtenerLotePorId(int $idLote): ?array
    {
        $sql = "SELECT * FROM rrhh_nominas WHERE id = :id";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idLote]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }

    // Obtiene la lista de empleados (recibos) dentro de un Lote
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
                    -- Detecta si tiene bonos (conceptos manuales de tipo PERCEPCION)
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

    /**
     * ========================================================================
     * 2. GENERACIÓN DEL LOTE (El motor de cálculo)
     * ========================================================================
     */
    public function generarLoteNomina(array $datos, int $userId): int
    {
        $db = $this->db();
        $frecuencia = strtoupper((string)($datos['frecuencia'] ?? 'TODOS'));
        
        try {
            $db->beginTransaction();

            // 1. Crear la cabecera del Lote en estado BORRADOR
            $stmtLote = $db->prepare("INSERT INTO rrhh_nominas 
                (referencia, nombre, fecha_inicio, fecha_fin, frecuencia, estado, created_by) 
                VALUES (:referencia, :nombre, :fecha_inicio, :fecha_fin, :frecuencia, 'BORRADOR', :created_by)");
            
            $referencia = 'NOM-' . date('Ym') . '-' . rand(1000, 9999); // Genera ref única
            
            $stmtLote->execute([
                'referencia' => $referencia,
                'nombre' => $datos['nombre_lote'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'frecuencia' => $frecuencia,
                'created_by' => $userId
            ]);
            
            $idLote = (int) $db->lastInsertId();

            // 2. Extraer asistencia y sueldos de empleados (Reutilizamos tu lógica antigua adaptada)
            $sqlAsistencia = "SELECT 
                                t.id AS id_tercero,
                                te.sueldo_basico,
                                te.pago_diario,
                                COUNT(CASE WHEN ar.estado_asistencia IN ('PUNTUAL', 'TARDANZA', 'TARDANZA JUSTIFICADA', 'INCOMPLETO') THEN 1 END) AS dias_asistidos,
                                COUNT(CASE WHEN ar.estado_asistencia IN ('FALTA JUSTIFICADA', 'PERMISO', 'VACACIONES', 'DESCANSO MEDICO') THEN 1 END) AS dias_justificados,
                                COUNT(CASE WHEN ar.estado_asistencia = 'FALTA' THEN 1 END) AS dias_falta,
                                SUM(COALESCE(ar.minutos_tardanza, 0)) AS minutos_tardanza
                              FROM terceros t
                              INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                              LEFT JOIN asistencia_registros ar ON ar.id_tercero = t.id AND ar.fecha BETWEEN :desde AND :hasta
                              WHERE t.es_empleado = 1 AND t.estado = 1 AND t.deleted_at IS NULL";
                              
            if ($frecuencia !== 'TODOS') {
                $sqlAsistencia .= " AND UPPER(te.tipo_pago) = :frecuencia";
            }
            $sqlAsistencia .= " GROUP BY t.id, te.sueldo_basico, te.pago_diario";
            
            $stmtParams = ['desde' => $datos['fecha_inicio'], 'hasta' => $datos['fecha_fin']];
            if ($frecuencia !== 'TODOS') {
                $stmtParams['frecuencia'] = $frecuencia;
            }
            
            $stmtAsist = $db->prepare($sqlAsistencia);
            $stmtAsist->execute($stmtParams);
            $empleados = $stmtAsist->fetchAll(PDO::FETCH_ASSOC);

            $loteTotalBruto = 0;
            $loteTotalDeducciones = 0;
            $loteTotalNeto = 0;
            $cantidadEmpleados = 0;

            // 3. Procesar empleado por empleado y guardar su detalle
            $stmtDetalle = $db->prepare("INSERT INTO rrhh_nominas_detalles 
                (id_nomina, id_tercero, dias_pagados, dias_falta, minutos_tardanza, sueldo_base_calculado, total_percepciones, total_deducciones, neto_a_pagar) 
                VALUES (:id_nomina, :id_tercero, :dias_pagados, :dias_falta, :min_tardanza, :sueldo_base, :percepciones, :deducciones, :neto)");
            
            $stmtConcepto = $db->prepare("INSERT INTO rrhh_nominas_conceptos 
                (id_detalle_nomina, tipo, categoria, descripcion, monto, es_automatico) 
                VALUES (:id_detalle, :tipo, :categoria, :descripcion, :monto, 1)");

            foreach ($empleados as $emp) {
                // Cálculo matemático
                $diasPagados = (int) $emp['dias_asistidos'] + (int) $emp['dias_justificados'];
                $diasFalta = (int) $emp['dias_falta'];
                
                // Sueldo base (Si es proporcional o fijo)
                $sueldoBaseCalculado = (float) $emp['pago_diario'] * $diasPagados;
                
                // Deducción por tardanzas (Ejemplo básico: 1 sol por minuto, ajústalo a tu lógica real)
                // Lo ideal sería: (sueldo_diario / 8 horas / 60 min) * minutos_tardanza
                $valorMinuto = ((float)$emp['pago_diario'] / 8) / 60; 
                $descuentoTardanzas = $valorMinuto * (int) $emp['minutos_tardanza'];
                
                $neto = $sueldoBaseCalculado - $descuentoTardanzas;

                // Insertar Detalle (Boleta)
                $stmtDetalle->execute([
                    'id_nomina' => $idLote,
                    'id_tercero' => $emp['id_tercero'],
                    'dias_pagados' => $diasPagados,
                    'dias_falta' => $diasFalta,
                    'min_tardanza' => $emp['minutos_tardanza'],
                    'sueldo_base' => $sueldoBaseCalculado,
                    'percepciones' => $sueldoBaseCalculado,
                    'deducciones' => $descuentoTardanzas,
                    'neto' => $neto > 0 ? $neto : 0
                ]);
                
                $idDetalle = (int) $db->lastInsertId();

                // Insertar Concepto: Sueldo Base
                $stmtConcepto->execute([
                    'id_detalle' => $idDetalle,
                    'tipo' => 'PERCEPCION',
                    'categoria' => 'Sueldo Base',
                    'descripcion' => 'Sueldo proporcional a ' . $diasPagados . ' días',
                    'monto' => $sueldoBaseCalculado
                ]);

                // Insertar Concepto: Descuento si hubo tardanzas
                if ($descuentoTardanzas > 0) {
                    $stmtConcepto->execute([
                        'id_detalle' => $idDetalle,
                        'tipo' => 'DEDUCCION',
                        'categoria' => 'Tardanza',
                        'descripcion' => 'Descuento por ' . $emp['minutos_tardanza'] . ' minutos de tardanza',
                        'monto' => $descuentoTardanzas
                    ]);
                }

                // Sumarizadores del Lote
                $loteTotalBruto += $sueldoBaseCalculado;
                $loteTotalDeducciones += $descuentoTardanzas;
                $loteTotalNeto += ($neto > 0 ? $neto : 0);
                $cantidadEmpleados++;
            }

            // 4. Actualizar Lote con los totales finales
            $stmtUpdateLote = $db->prepare("UPDATE rrhh_nominas 
                SET total_bruto = :bruto, total_deducciones = :deducciones, total_neto = :neto, cantidad_empleados = :cantidad 
                WHERE id = :id");
            $stmtUpdateLote->execute([
                'bruto' => $loteTotalBruto,
                'deducciones' => $loteTotalDeducciones,
                'neto' => $loteTotalNeto,
                'cantidad' => $cantidadEmpleados,
                'id' => $idLote
            ]);

            $db->commit();
            return $idLote;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al generar Lote de Nómina: " . $e->getMessage());
            throw new Exception("Error al generar la nómina: " . $e->getMessage());
        }
    }

    /**
     * ========================================================================
     * 3. AJUSTES Y FLUJO DE ESTADOS (Bonos, Aprobación)
     * ========================================================================
     */

    // Agrega un bono manual o descuento a un empleado específico (Solo en estado BORRADOR)
    public function agregarConceptoManual(array $datos): bool
    {
        $db = $this->db();
        try {
            $db->beginTransaction();

            $idDetalle = (int) $datos['id_detalle_nomina'];
            $tipo = strtoupper($datos['tipo_concepto']); // PERCEPCION o DEDUCCION
            $monto = (float) $datos['monto'];

            // 1. Insertar el concepto manual
            $stmt = $db->prepare("INSERT INTO rrhh_nominas_conceptos 
                (id_detalle_nomina, tipo, categoria, descripcion, monto, es_automatico) 
                VALUES (:id_detalle, :tipo, :categoria, :descripcion, :monto, 0)");
            
            $stmt->execute([
                'id_detalle' => $idDetalle,
                'tipo' => $tipo,
                'categoria' => $datos['categoria_concepto'],
                'descripcion' => $datos['descripcion'],
                'monto' => $monto
            ]);

            // 2. Recalcular el detalle (Boleta) de este empleado
            $signo = ($tipo === 'PERCEPCION') ? '+' : '-';
            $campoAfectado = ($tipo === 'PERCEPCION') ? 'total_percepciones' : 'total_deducciones';
            
            $db->exec("UPDATE rrhh_nominas_detalles 
                       SET {$campoAfectado} = {$campoAfectado} + {$monto},
                           neto_a_pagar = neto_a_pagar {$signo} {$monto}
                       WHERE id = {$idDetalle}");

            // 3. Recalcular la cabecera (El Lote Completo)
            // Primero obtenemos a qué lote pertenece este detalle
            $stmtLoteId = $db->query("SELECT id_nomina FROM rrhh_nominas_detalles WHERE id = {$idDetalle}");
            $idLote = $stmtLoteId->fetchColumn();

            $campoLote = ($tipo === 'PERCEPCION') ? 'total_bruto' : 'total_deducciones';
            $db->exec("UPDATE rrhh_nominas 
                       SET {$campoLote} = {$campoLote} + {$monto},
                           total_neto = total_neto {$signo} {$monto}
                       WHERE id = {$idLote}");

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al agregar concepto manual: " . $e->getMessage());
            return false;
        }
    }

    // Congela la nómina
    public function aprobarLote(int $idLote): bool
    {
        $sql = "UPDATE rrhh_nominas SET estado = 'APROBADO' WHERE id = :id AND estado = 'BORRADOR'";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute(['id' => $idLote]);
    }

    /**
     * ========================================================================
     * 4. TESORERÍA: PAGAR LOTE (En bloque)
     * ========================================================================
     */
    public function pagarLoteNomina(array $datos, int $userId): bool
    {
        $db = $this->db();
        $idLote = (int) $datos['id_lote'];
        
        try {
            $db->beginTransaction();

            // 1. Obtener datos del lote
            $stmtLote = $db->prepare("SELECT * FROM rrhh_nominas WHERE id = :id FOR UPDATE");
            $stmtLote->execute(['id' => $idLote]);
            $lote = $stmtLote->fetch(PDO::FETCH_ASSOC);

            if (!$lote || $lote['estado'] !== 'APROBADO') {
                throw new Exception("El lote no existe o no está en estado APROBADO.");
            }

            // 2. Verificar saldo en cuenta
            $stmtCuenta = $db->prepare("SELECT saldo_actual FROM tesoreria_cuentas WHERE id = :id_cuenta FOR UPDATE");
            $stmtCuenta->execute(['id_cuenta' => $datos['id_cuenta']]);
            $cuenta = $stmtCuenta->fetch(PDO::FETCH_ASSOC);

            if (!$cuenta || (float)$cuenta['saldo_actual'] < (float)$lote['total_neto']) {
                throw new Exception("Saldo insuficiente en la cuenta de tesorería seleccionada.");
            }

            // 3. Registrar Salida en Tesorería (Una sola transacción para todos los empleados)
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

            // 4. Descontar de la cuenta bancaria/caja
            $stmtUpdateCuenta = $db->prepare("UPDATE tesoreria_cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id_cuenta");
            $stmtUpdateCuenta->execute([
                'monto' => $lote['total_neto'],
                'id_cuenta' => $datos['id_cuenta']
            ]);

            // 5. Actualizar el estado del Lote a PAGADO
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

    /**
     * ========================================================================
     * 5. GENERACIÓN DE BOLETAS (PDF)
     * ========================================================================
     */
    
    // Obtiene toda la información de un recibo específico con sus conceptos desglosados
    public function obtenerDatosBoletaPdf(int $idDetalle): ?array
    {
        // 1. Obtenemos los datos del empleado, del detalle y de la cabecera del Lote
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
            return null; // Si no existe, retornamos null
        }

        // 2. Obtenemos el desglose de los conceptos (Sueldo, Bonos, Tardanzas)
        // Ordenamos por 'tipo' DESC para que las PERCEPCIONES salgan primero y las DEDUCCIONES después
        $sqlConceptos = "SELECT tipo, categoria, descripcion, monto, es_automatico 
                         FROM rrhh_nominas_conceptos 
                         WHERE id_detalle_nomina = :id_detalle 
                         ORDER BY tipo DESC, id ASC";
                         
        $stmtConc = $this->db()->prepare($sqlConceptos);
        $stmtConc->execute(['id_detalle' => $idDetalle]);
        
        // Guardamos los conceptos dentro del mismo arreglo de la boleta
        $boleta['conceptos'] = $stmtConc->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $boleta;
    }
}
