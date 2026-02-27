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
        $sql = 'SELECT id, codigo_biometrico, fecha_hora_marca, tipo_marca, nombre_dispositivo, procesado, created_at, created_by
                FROM asistencia_logs_biometrico
                ORDER BY fecha_hora_marca DESC, id DESC';

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

    public function obtenerDashboardDiario(string $fecha): array
    {
        $diaSemana = (int) date('N', strtotime($fecha));

        $sql = 'SELECT t.id,
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
                LEFT JOIN asistencia_registros ar ON ar.id_tercero = t.id AND ar.fecha = :fecha
                WHERE t.es_empleado = 1
                  AND t.deleted_at IS NULL
                ORDER BY t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'dia_semana' => $diaSemana,
            'fecha' => $fecha,
        ]);

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
}
