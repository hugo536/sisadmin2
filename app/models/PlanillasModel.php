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
                    SUM(COALESCE(ar.minutos_tardanza, 0)) AS total_minutos_tardanza
                    
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
}