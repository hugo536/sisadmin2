<?php
declare(strict_types=1);

class HorarioModel extends Modelo
{
    public function listarHorarios(): array
    {
        // Añadimos una subconsulta 'usos' para saber si el turno está asignado a alguien
        $sql = 'SELECT h.id, h.nombre, h.t1_entrada, h.t1_salida, h.t2_entrada, h.t2_salida, 
                       h.t3_entrada, h.t3_salida, h.total_horas_pago, h.tolerancia_minutos, h.estado,
                       (SELECT COUNT(*) FROM asistencia_empleado_horario aeh WHERE aeh.id_horario = h.id) AS usos
                FROM asistencia_horarios h
                WHERE h.deleted_at IS NULL
                ORDER BY h.estado DESC, h.nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function verificarUsoHorario(int $idHorario): bool
    {
        $sql = 'SELECT 1 FROM asistencia_empleado_horario WHERE id_horario = :id LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idHorario]);
        return (bool) $stmt->fetchColumn();
    }

    public function eliminarHorario(int $id, int $userId): bool
    {
        $sql = 'UPDATE asistencia_horarios SET deleted_at = NOW(), updated_by = :uid WHERE id = :id';
        return $this->db()->prepare($sql)->execute(['uid' => $userId, 'id' => $id]);
    }

    public function crearHorario(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_horarios (nombre, t1_entrada, t1_salida, t2_entrada, t2_salida, t3_entrada, t3_salida, total_horas_pago, tolerancia_minutos, estado, created_by)
                VALUES (:nombre, :t1_entrada, :t1_salida, :t2_entrada, :t2_salida, :t3_entrada, :t3_salida, :total_horas_pago, :tolerancia_minutos, 1, :created_by)';

        return $this->db()->prepare($sql)->execute([
            'nombre'             => $data['nombre'],
            't1_entrada'         => !empty($data['t1_entrada']) ? $data['t1_entrada'] : null,
            't1_salida'          => !empty($data['t1_salida'])  ? $data['t1_salida']  : null,
            't2_entrada'         => !empty($data['t2_entrada']) ? $data['t2_entrada'] : null,
            't2_salida'          => !empty($data['t2_salida'])  ? $data['t2_salida']  : null,
            't3_entrada'         => !empty($data['t3_entrada']) ? $data['t3_entrada'] : null,
            't3_salida'          => !empty($data['t3_salida'])  ? $data['t3_salida']  : null,
            'total_horas_pago'   => $data['total_horas_pago'] ?? 0.00,
            'tolerancia_minutos' => $data['tolerancia_minutos'],
            'created_by'         => $userId,
        ]);
    }

    public function actualizarHorario(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE asistencia_horarios
                SET nombre = :nombre,
                    t1_entrada = :t1_entrada,
                    t1_salida = :t1_salida,
                    t2_entrada = :t2_entrada,
                    t2_salida = :t2_salida,
                    t3_entrada = :t3_entrada,
                    t3_salida = :t3_salida,
                    total_horas_pago = :total_horas_pago,
                    tolerancia_minutos = :tolerancia_minutos,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id'                 => $id,
            'nombre'             => $data['nombre'],
            't1_entrada'         => !empty($data['t1_entrada']) ? $data['t1_entrada'] : null,
            't1_salida'          => !empty($data['t1_salida'])  ? $data['t1_salida']  : null,
            't2_entrada'         => !empty($data['t2_entrada']) ? $data['t2_entrada'] : null,
            't2_salida'          => !empty($data['t2_salida'])  ? $data['t2_salida']  : null,
            't3_entrada'         => !empty($data['t3_entrada']) ? $data['t3_entrada'] : null,
            't3_salida'          => !empty($data['t3_salida'])  ? $data['t3_salida']  : null,
            'total_horas_pago'   => $data['total_horas_pago'] ?? 0.00,
            'tolerancia_minutos' => $data['tolerancia_minutos'],
            'updated_by'         => $userId,
        ]);
    }

    public function cambiarEstadoHorario(int $id, int $estado, int $userId): bool
    {
        $sql = 'UPDATE asistencia_horarios
                SET estado = :estado,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'estado' => $estado,
            'updated_by' => $userId,
        ]);
    }

    public function listarEmpleados(): array
    {
        // Añadido t.estado = 1 para traer solo personal activo al modal de Asignación Masiva
        $sql = 'SELECT t.id, t.nombre_completo, te.codigo_biometrico
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE t.es_empleado = 1
                  AND t.estado = 1 
                  AND t.deleted_at IS NULL
                ORDER BY t.nombre_completo ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarAsignaciones(): array
    {
        $sql = 'SELECT aeh.id,
                       aeh.id_tercero,
                       t.nombre_completo AS empleado,
                       te.codigo_biometrico,
                       aeh.id_horario,
                       ah.nombre AS horario,
                       ah.t1_entrada,
                       ah.t1_salida,
                       ah.t2_entrada,
                       ah.t2_salida,
                       ah.t3_entrada,
                       ah.t3_salida,
                       aeh.dia_semana
                FROM asistencia_empleado_horario aeh
                INNER JOIN terceros t ON t.id = aeh.id_tercero
                LEFT JOIN terceros_empleados te ON te.id_tercero = t.id
                INNER JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                WHERE t.estado = 1 
                  AND t.es_empleado = 1  /* <-- ESTA LÍNEA ES LA CLAVE */
                  AND t.deleted_at IS NULL
                ORDER BY aeh.dia_semana ASC, t.nombre_completo ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardarAsignacion(int $idTercero, int $idHorario, int $diaSemana, int $userId): bool
    {
        // Busca si ya existe un turno asignado para ese empleado ese día exacto
        $check = $this->db()->prepare(
            'SELECT id FROM asistencia_empleado_horario WHERE id_tercero = :id_tercero AND dia_semana = :dia_semana LIMIT 1'
        );
        $check->execute(['id_tercero' => $idTercero, 'dia_semana' => $diaSemana]);
        $existente = $check->fetch(PDO::FETCH_ASSOC);

        // Si existe, lo actualizamos silenciosamente
        if ($existente) {
            $stmt = $this->db()->prepare('UPDATE asistencia_empleado_horario SET id_horario = :id_horario WHERE id = :id');
            return $stmt->execute(['id_horario' => $idHorario, 'id' => (int) $existente['id']]);
        }

        // Si no existe, lo insertamos
        $sql = 'INSERT INTO asistencia_empleado_horario (id_tercero, id_horario, dia_semana, created_by)
                VALUES (:id_tercero, :id_horario, :dia_semana, :created_by)';

        return $this->db()->prepare($sql)->execute([
            'id_tercero' => $idTercero,
            'id_horario' => $idHorario,
            'dia_semana' => $diaSemana,
            'created_by' => $userId,
        ]);
    }

    public function eliminarAsignacion(int $id): bool
    {
        return $this->db()->prepare('DELETE FROM asistencia_empleado_horario WHERE id = :id')->execute(['id' => $id]);
    }

    public function limpiarSemanaEmpleado(int $idTercero): bool
    {
        $sql = 'DELETE FROM asistencia_empleado_horario WHERE id_tercero = :id_tercero';
        return $this->db()->prepare($sql)->execute(['id_tercero' => $idTercero]);
    }
}