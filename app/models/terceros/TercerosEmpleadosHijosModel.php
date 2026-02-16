<?php
declare(strict_types=1);

class TercerosEmpleadosHijosModel extends Modelo
{
    /** @var array<string,bool> */
    private array $tableCache = [];

    public function tablaDisponible(): bool
    {
        if (array_key_exists('terceros_empleados_hijos', $this->tableCache)) {
            return $this->tableCache['terceros_empleados_hijos'];
        }

        $stmt = $this->db()->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1');
        $stmt->execute(['table_name' => 'terceros_empleados_hijos']);
        $this->tableCache['terceros_empleados_hijos'] = (bool) $stmt->fetchColumn();

        return $this->tableCache['terceros_empleados_hijos'];
    }

    public function existeEmpleado(int $idEmpleado): bool
    {
        $stmt = $this->db()->prepare('SELECT 1 FROM terceros_empleados WHERE id_tercero = :id LIMIT 1');
        $stmt->execute(['id' => $idEmpleado]);
        return (bool) $stmt->fetchColumn();
    }

    public function listarPorEmpleado(int $idEmpleado): array
    {
        if (!$this->tablaDisponible()) return [];

        $sql = 'SELECT id, id_empleado, nombre_completo, fecha_nacimiento, esta_estudiando, discapacidad,
                       TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad_actual,
                       CASE
                           WHEN discapacidad = 1 THEN 1
                           WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) < 18 THEN 1
                           WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) <= 24 AND esta_estudiando = 1 THEN 1
                           ELSE 0
                       END AS es_valido,
                       CASE
                           WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) >= 18 AND esta_estudiando = 0 AND discapacidad = 0 THEN 1
                           ELSE 0
                       END AS mayor_sin_justificar
                FROM terceros_empleados_hijos
                WHERE id_empleado = :id
                ORDER BY fecha_nacimiento DESC, id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idEmpleado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar(array $data): int
    {
        if (!$this->tablaDisponible()) {
            throw new RuntimeException('La tabla terceros_empleados_hijos no está disponible.');
        }

        $id = (int) ($data['id'] ?? 0);
        $params = [
            'id_empleado' => (int) ($data['id_empleado'] ?? 0),
            'nombre_completo' => trim((string) ($data['nombre_completo'] ?? '')),
            'fecha_nacimiento' => (string) ($data['fecha_nacimiento'] ?? ''),
            'esta_estudiando' => !empty($data['esta_estudiando']) ? 1 : 0,
            'discapacidad' => !empty($data['discapacidad']) ? 1 : 0,
        ];

        if ($params['id_empleado'] <= 0) {
            throw new InvalidArgumentException('Empleado inválido.');
        }
        if ($params['nombre_completo'] === '') {
            throw new InvalidArgumentException('El nombre completo es obligatorio.');
        }
        if (!$this->esFechaValida($params['fecha_nacimiento'])) {
            throw new InvalidArgumentException('La fecha de nacimiento no es válida.');
        }

        if ($id > 0) {
            $sql = 'UPDATE terceros_empleados_hijos
                    SET nombre_completo = :nombre_completo,
                        fecha_nacimiento = :fecha_nacimiento,
                        esta_estudiando = :esta_estudiando,
                        discapacidad = :discapacidad,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND id_empleado = :id_empleado';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params + ['id' => $id]);
            return $id;
        }

        $sql = 'INSERT INTO terceros_empleados_hijos (id_empleado, nombre_completo, fecha_nacimiento, esta_estudiando, discapacidad)
                VALUES (:id_empleado, :nombre_completo, :fecha_nacimiento, :esta_estudiando, :discapacidad)';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (int) $this->db()->lastInsertId();
    }

    public function eliminar(int $id, int $idEmpleado): bool
    {
        if (!$this->tablaDisponible()) return false;
        $stmt = $this->db()->prepare('DELETE FROM terceros_empleados_hijos WHERE id = :id AND id_empleado = :id_empleado');
        return $stmt->execute(['id' => $id, 'id_empleado' => $idEmpleado]);
    }

    public function tieneMayorSinJustificar(int $idEmpleado): bool
    {
        if (!$this->tablaDisponible()) return false;

        $sql = 'SELECT 1
                FROM terceros_empleados_hijos
                WHERE id_empleado = :id
                  AND TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) >= 18
                  AND esta_estudiando = 0
                  AND discapacidad = 0
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idEmpleado]);
        return (bool) $stmt->fetchColumn();
    }

    private function esFechaValida(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return false;
        }

        return $date <= new DateTimeImmutable('today');
    }
}
