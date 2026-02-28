<?php
declare(strict_types=1);

class AsistenciaModel extends Modelo
{
    public function guardarLogBiometrico(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_logs_biometrico (
                    codigo_biometrico,
                    fecha_hora_marca,
                    tipo_marca,
                    nombre_dispositivo,
                    procesado,
                    created_by
                ) VALUES (
                    :codigo_biometrico,
                    :fecha_hora_marca,
                    :tipo_marca,
                    :nombre_dispositivo,
                    0,
                    :created_by
                )';

        return $this->db()->prepare($sql)->execute([
            'codigo_biometrico' => $data['codigo_biometrico'],
            'fecha_hora_marca' => $data['fecha_hora_marca'],
            'tipo_marca' => $data['tipo_marca'],
            'nombre_dispositivo' => $data['nombre_dispositivo'],
            'created_by' => $userId,
        ]);
    }

    public function listarLogsBiometricos(): array
    {
        // Modificamos la consulta para traer el nombre_completo usando LEFT JOIN
        $sql = 'SELECT 
                    alb.id, 
                    alb.codigo_biometrico, 
                    alb.fecha_hora_marca, 
                    alb.tipo_marca, 
                    alb.nombre_dispositivo, 
                    alb.procesado, 
                    alb.created_at, 
                    alb.created_by,
                    t.nombre_completo
                FROM asistencia_logs_biometrico alb
                LEFT JOIN terceros_empleados te ON alb.codigo_biometrico = te.codigo_biometrico
                LEFT JOIN terceros t ON te.id_tercero = t.id AND t.deleted_at IS NULL
                ORDER BY alb.fecha_hora_marca DESC, alb.id DESC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerLogsPendientes(): array
    {
        $sql = 'SELECT id, codigo_biometrico, fecha_hora_marca, tipo_marca
                FROM asistencia_logs_biometrico
                WHERE procesado = 0
                ORDER BY fecha_hora_marca ASC, id ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function mapearEmpleadoPorCodigoBiometrico(): array
    {
        $sql = 'SELECT te.codigo_biometrico, te.id_tercero, t.nombre_completo
                FROM terceros_empleados te
                INNER JOIN terceros t ON t.id = te.id_tercero
                WHERE te.codigo_biometrico IS NOT NULL
                  AND te.codigo_biometrico <> ""
                  AND t.deleted_at IS NULL';

        $stmt = $this->db()->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $codigo = (string) ($row['codigo_biometrico'] ?? '');
            if ($codigo === '') continue;
            $map[$codigo] = [
                'id_tercero' => (int) ($row['id_tercero'] ?? 0),
                'nombre_completo' => (string) ($row['nombre_completo'] ?? ''),
            ];
        }

        return $map;
    }

    public function obtenerHorarioEsperado(int $idTercero, int $diaSemana): ?array
    {
        $sql = 'SELECT ah.id, ah.nombre, ah.hora_entrada, ah.hora_salida, ah.tolerancia_minutos
                FROM asistencia_empleado_horario aeh
                INNER JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                WHERE aeh.id_tercero = :id_tercero
                  AND aeh.dia_semana = :dia_semana
                  AND ah.estado = 1
                  AND ah.deleted_at IS NULL
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_tercero' => $idTercero,
            'dia_semana' => $diaSemana,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertRegistroAsistencia(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_registros (
                    id_tercero,
                    fecha,
                    hora_ingreso,
                    hora_salida,
                    estado_asistencia,
                    minutos_tardanza,
                    horas_trabajadas,
                    horas_extras,
                    observaciones,
                    created_by,
                    updated_by
                ) VALUES (
                    :id_tercero,
                    :fecha,
                    :hora_ingreso,
                    :hora_salida,
                    :estado_asistencia,
                    :minutos_tardanza,
                    :horas_trabajadas,
                    :horas_extras,
                    :observaciones,
                    :created_by,
                    :updated_by
                )
                ON DUPLICATE KEY UPDATE
                    hora_ingreso = VALUES(hora_ingreso),
                    hora_salida = VALUES(hora_salida),
                    estado_asistencia = VALUES(estado_asistencia),
                    minutos_tardanza = VALUES(minutos_tardanza),
                    horas_trabajadas = VALUES(horas_trabajadas),
                    horas_extras = VALUES(horas_extras),
                    observaciones = VALUES(observaciones),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()';

        return $this->db()->prepare($sql)->execute([
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha'],
            'hora_ingreso' => $data['hora_ingreso'],
            'hora_salida' => $data['hora_salida'],
            'estado_asistencia' => $data['estado_asistencia'],
            'minutos_tardanza' => $data['minutos_tardanza'],
            'horas_trabajadas' => $data['horas_trabajadas'],
            'horas_extras' => $data['horas_extras'],
            'observaciones' => $data['observaciones'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function marcarLogsProcesados(array $ids): void
    {
        if (empty($ids)) return;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE asistencia_logs_biometrico SET procesado = 1 WHERE id IN ($placeholders)";
        $this->db()->prepare($sql)->execute(array_values($ids));
    }

    public function obtenerDashboardDiario(string $fecha, ?int $idTercero = null, string $estado = ''): array
    {
        $diaSemana = (int) date('N', strtotime($fecha));

        $sql = 'SELECT t.id,
                       :fecha_dashboard AS fecha,
                       t.nombre_completo,
                       ah.nombre AS horario_nombre,
                       ah.hora_entrada,
                       ah.hora_salida,
                       ar.hora_ingreso,
                       ar.hora_salida AS hora_salida_real,
                       ar.estado_asistencia,
                       ar.minutos_tardanza
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                LEFT JOIN asistencia_empleado_horario aeh ON aeh.id_tercero = t.id AND aeh.dia_semana = :dia_semana
                LEFT JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                LEFT JOIN asistencia_registros ar ON ar.id_tercero = t.id AND ar.fecha = :fecha_registro
                WHERE t.es_empleado = 1
                  AND t.deleted_at IS NULL';

        $params = [
            'dia_semana' => $diaSemana,
            'fecha_dashboard' => $fecha,
            'fecha_registro' => $fecha,
        ];

        if ($idTercero !== null && $idTercero > 0) {
            $sql .= ' AND t.id = :id_tercero';
            $params['id_tercero'] = $idTercero;
        }

        if ($estado !== '') {
            if ($estado === 'FALTA') {
                $sql .= ' AND COALESCE(ar.estado_asistencia, "FALTA") = :estado';
            } else {
                $sql .= ' AND ar.estado_asistencia = :estado';
            }
            $params['estado'] = $estado;
        }

        $sql .= '
                ORDER BY t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerDashboardRango(string $desde, string $hasta, ?int $idTercero = null, string $estado = ''): array
    {
        $sql = 'SELECT t.id,
                       ar.fecha,
                       t.nombre_completo,
                       ah.nombre AS horario_nombre,
                       ah.hora_entrada,
                       ah.hora_salida,
                       ar.hora_ingreso,
                       ar.hora_salida AS hora_salida_real,
                       ar.estado_asistencia,
                       ar.minutos_tardanza
                FROM asistencia_registros ar
                INNER JOIN terceros t ON t.id = ar.id_tercero
                LEFT JOIN asistencia_empleado_horario aeh
                    ON aeh.id_tercero = t.id
                   AND aeh.dia_semana = (WEEKDAY(ar.fecha) + 1)
                LEFT JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                WHERE ar.fecha BETWEEN :desde AND :hasta
                  AND t.es_empleado = 1
                  AND t.deleted_at IS NULL';

        $params = [
            'desde' => $desde,
            'hasta' => $hasta,
        ];

        if ($idTercero !== null && $idTercero > 0) {
            $sql .= ' AND t.id = :id_tercero';
            $params['id_tercero'] = $idTercero;
        }

        if ($estado !== '') {
            $sql .= ' AND ar.estado_asistencia = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY ar.fecha DESC, t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarEmpleadosParaIncidencias(): array
    {
        $sql = 'SELECT t.id, t.nombre_completo
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE t.es_empleado = 1
                  AND t.deleted_at IS NULL
                ORDER BY t.nombre_completo ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarIncidencias(): array
    {
        $sql = 'SELECT ai.id,
                       ai.id_tercero,
                       t.nombre_completo AS empleado,
                       ai.tipo_incidencia,
                       ai.fecha_inicio,
                       ai.fecha_fin,
                       ai.con_goce_sueldo,
                       ai.documento_respaldo,
                       ai.estado,
                       ai.created_at
                FROM asistencia_incidencias ai
                INNER JOIN terceros t ON t.id = ai.id_tercero
                WHERE ai.deleted_at IS NULL
                ORDER BY ai.fecha_inicio DESC, ai.id DESC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardarIncidencia(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_incidencias (
                    id_tercero,
                    tipo_incidencia,
                    fecha_inicio,
                    fecha_fin,
                    con_goce_sueldo,
                    documento_respaldo,
                    estado,
                    created_by,
                    updated_by
                ) VALUES (
                    :id_tercero,
                    :tipo_incidencia,
                    :fecha_inicio,
                    :fecha_fin,
                    :con_goce_sueldo,
                    :documento_respaldo,
                    1,
                    :created_by,
                    :updated_by
                )';

        return $this->db()->prepare($sql)->execute([
            'id_tercero' => $data['id_tercero'],
            'tipo_incidencia' => $data['tipo_incidencia'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'],
            'con_goce_sueldo' => $data['con_goce_sueldo'],
            'documento_respaldo' => $data['documento_respaldo'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function eliminarIncidencia(int $id, int $userId): bool
    {
        $sql = 'UPDATE asistencia_incidencias
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    estado = 0,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function gestionarExcepcionDiaria(array $data, int $userId): bool
    {
        // 1. Verificamos si ya existe un registro en asistencia_registros para este empleado y fecha
        $sqlCheck = "SELECT id, hora_ingreso, hora_salida FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1";
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute([
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha']
        ]);
        $registroExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        // 2. Calculamos los minutos de tardanza en base al nuevo horario esperado
        $minutosTardanza = 0;
        $horaRealLlegada = null;

        // Si ya hay un registro con hora real de ingreso (del biométrico o manual previo), la usamos para recalcular
        if ($registroExistente && !empty($registroExistente['hora_ingreso'])) {
            $horaRealLlegada = substr($registroExistente['hora_ingreso'], 11, 5); // Ej: "07:30"
        }

        // Si hay una hora real y no estamos justificando, recalculamos la tardanza
        if ($horaRealLlegada && empty($data['aplicar_justificacion'])) {
            $tsEsperada = strtotime($data['fecha'] . ' ' . $data['hora_entrada_esperada'] . ':00');
            $tsReal = strtotime($registroExistente['hora_ingreso']);
            
            if ($tsReal > $tsEsperada) {
                $minutosTardanza = (int) floor(($tsReal - $tsEsperada) / 60);
            }
        }

        // 3. Preparamos el estado final
        $estadoFinal = 'FALTA';
        
        if (!empty($data['aplicar_justificacion'])) {
            $estadoFinal = $data['nuevo_estado']; // Ej: "TARDANZA JUSTIFICADA"
            $minutosTardanza = 0; // Si se justifica, perdonamos los minutos para planillas
        } elseif ($horaRealLlegada) {
            $estadoFinal = ($minutosTardanza > 0) ? 'TARDANZA' : 'PUNTUAL';
        }

        // 4. Preparamos los datos para el UPSERT
        // OJO: Guardamos el horario editado en el campo "observaciones" de forma estructurada para el registro histórico,
        // ya que la tabla "asistencia_empleado_horario" guarda la regla general, no la del día específico.
        
        $observacionEstructurada = "Horario Excepción: {$data['hora_entrada_esperada']} a {$data['hora_salida_esperada']}.";
        if (!empty($data['aplicar_justificacion'])) {
            $observacionEstructurada .= " Justificación: " . $data['observacion'];
        }

        $upsertData = [
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha'],
            'hora_ingreso' => $registroExistente['hora_ingreso'] ?? null,
            'hora_salida' => $registroExistente['hora_salida'] ?? null,
            'estado_asistencia' => $estadoFinal,
            'minutos_tardanza' => $minutosTardanza,
            'horas_trabajadas' => 0, // Se calcula al final del día normalmente
            'horas_extras' => 0,
            'observaciones' => $observacionEstructurada,
        ];

        return $this->upsertRegistroAsistencia($upsertData, $userId);
    }
}

