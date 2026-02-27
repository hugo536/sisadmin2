<?php
declare(strict_types=1);

class HorarioModel extends Modelo
{
    public function listarHorarios(): array
    {
        $sql = 'SELECT id, nombre, hora_entrada, hora_salida, tolerancia_minutos, estado
                FROM asistencia_horarios
                WHERE deleted_at IS NULL
                ORDER BY estado DESC, nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearHorario(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_horarios (nombre, hora_entrada, hora_salida, tolerancia_minutos, estado, created_by)
                VALUES (:nombre, :hora_entrada, :hora_salida, :tolerancia_minutos, 1, :created_by)';

        return $this->db()->prepare($sql)->execute([
            'nombre' => $data['nombre'],
            'hora_entrada' => $data['hora_entrada'],
            'hora_salida' => $data['hora_salida'],
            'tolerancia_minutos' => $data['tolerancia_minutos'],
            'created_by' => $userId,
        ]);
    }

    public function actualizarHorario(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE asistencia_horarios
                SET nombre = :nombre,
                    hora_entrada = :hora_entrada,
                    hora_salida = :hora_salida,
                    tolerancia_minutos = :tolerancia_minutos,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => $data['nombre'],
            'hora_entrada' => $data['hora_entrada'],
            'hora_salida' => $data['hora_salida'],
            'tolerancia_minutos' => $data['tolerancia_minutos'],
            'updated_by' => $userId,
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
        $sql = 'SELECT t.id, t.nombre_completo, te.codigo_biometrico
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE t.es_empleado = 1
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
                       ah.hora_entrada,
                       ah.hora_salida,
                       aeh.dia_semana
                FROM asistencia_empleado_horario aeh
                INNER JOIN terceros t ON t.id = aeh.id_tercero
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                INNER JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                ORDER BY aeh.dia_semana ASC, t.nombre_completo ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardarAsignacion(int $idTercero, int $idHorario, int $diaSemana, int $userId): bool
    {
        $check = $this->db()->prepare(
            'SELECT id FROM asistencia_empleado_horario WHERE id_tercero = :id_tercero AND dia_semana = :dia_semana LIMIT 1'
        );
        $check->execute(['id_tercero' => $idTercero, 'dia_semana' => $diaSemana]);
        $existente = $check->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $stmt = $this->db()->prepare('UPDATE asistencia_empleado_horario SET id_horario = :id_horario WHERE id = :id');
            return $stmt->execute(['id_horario' => $idHorario, 'id' => (int) $existente['id']]);
        }

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
}
