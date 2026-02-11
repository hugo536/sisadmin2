<?php
declare(strict_types=1);

class DashboardModel extends Modelo
{
    /** @var array<string,bool> */
    private array $columnCache = [];

    public function obtener_totales(): array
    {
        return [
            'items' => $this->contar_activos('items'),
            'terceros' => $this->contar_activos('terceros'),
            'usuarios' => $this->contar_activos('usuarios'),
        ];
    }

    public function obtener_ultimos_eventos(int $limite = 10): array
    {
        $sql = 'SELECT b.created_at, b.evento, COALESCE(u.usuario, "Sistema") AS usuario
                FROM bitacora_seguridad b
                LEFT JOIN usuarios u ON u.id = b.created_by
                ORDER BY b.created_at DESC
                LIMIT :limite';
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }


    public function obtener_cumpleanos_semana(): array
    {
        if (!$this->hasColumn('terceros_empleados', 'recordar_cumpleanos') || !$this->hasColumn('terceros_empleados', 'fecha_nacimiento')) {
            return [];
        }

        $sql = 'SELECT t.id, t.nombre_completo, te.cargo, te.area, te.fecha_nacimiento
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE t.deleted_at IS NULL
                  AND t.estado = 1
                  AND t.es_empleado = 1
                  AND te.recordar_cumpleanos = 1
                  AND te.fecha_nacimiento IS NOT NULL';

        $rows = $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $hoy = new DateTimeImmutable('today');
        $limite = $hoy->modify('+6 days');
        $resultado = [];

        foreach ($rows as $row) {
            $fecha = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($row['fecha_nacimiento'] ?? ''));
            if (!$fecha) {
                continue;
            }

            $cumpleAnioActual = DateTimeImmutable::createFromFormat('Y-m-d', $hoy->format('Y') . '-' . $fecha->format('m-d'));
            if (!$cumpleAnioActual) {
                continue;
            }

            if ($cumpleAnioActual < $hoy) {
                $cumpleAnioActual = $cumpleAnioActual->modify('+1 year');
            }

            if ($cumpleAnioActual > $limite) {
                continue;
            }

            $resultado[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre_completo' => (string) ($row['nombre_completo'] ?? ''),
                'cargo' => (string) ($row['cargo'] ?? ''),
                'area' => (string) ($row['area'] ?? ''),
                'fecha_nacimiento' => (string) ($row['fecha_nacimiento'] ?? ''),
                'fecha_cumple' => $cumpleAnioActual->format('Y-m-d'),
                'edad_cumple' => max(0, (int) $cumpleAnioActual->format('Y') - (int) $fecha->format('Y')),
                'dias_restantes' => (int) $hoy->diff($cumpleAnioActual)->days,
            ];
        }

        usort($resultado, static fn (array $a, array $b): int => strcmp($a['fecha_cumple'], $b['fecha_cumple']));

        return $resultado;
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        $stmt = $this->db()->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        $this->columnCache[$cacheKey] = (bool) $stmt->fetchColumn();
        return $this->columnCache[$cacheKey];
    }

    private function contar_activos(string $tabla): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $tabla . ' WHERE estado = 1';
        return (int) $this->db()->query($sql)->fetchColumn();
    }
}
