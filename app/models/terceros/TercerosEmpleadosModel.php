<?php
declare(strict_types=1);

class TercerosEmpleadosModel extends Modelo
{
    /** @var array<string,bool> */
    private array $columnCache = [];

    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $tieneCumpleanos = $this->hasColumn('terceros_empleados', 'recordar_cumpleanos')
            && $this->hasColumn('terceros_empleados', 'fecha_nacimiento');

        $columnas = [
            'id_tercero', 'cargo', 'area', 'fecha_ingreso', 'fecha_cese', 'estado_laboral',
            'tipo_contrato', 'sueldo_basico', 'moneda', 'asignacion_familiar',
            'tipo_pago', 'pago_diario', 'regimen_pensionario', 'tipo_comision_afp', 'cuspp', 'essalud',
        ];

        if ($tieneCumpleanos) {
            $columnas[] = 'recordar_cumpleanos';
            $columnas[] = 'fecha_nacimiento';
        }

        $columnas[] = 'updated_by';

        $placeholders = array_map(static fn (string $col): string => ':' . $col, $columnas);
        $updates = array_map(static fn (string $col): string => $col . ' = VALUES(' . $col . ')', array_filter($columnas, static fn (string $col): bool => $col !== 'id_tercero'));
        $updates[] = 'updated_at = NOW()';

        $sql = 'INSERT INTO terceros_empleados (' . implode(', ', $columnas) . ')
                VALUES (' . implode(', ', $placeholders) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

        $params = [
            'id_tercero' => $idTercero,
            'cargo' => $data['cargo'] ?? null,
            'area' => $data['area'] ?? null,
            'fecha_ingreso' => $data['fecha_ingreso'] ?? null,
            'fecha_cese' => $data['fecha_cese'] ?? null,
            'estado_laboral' => $data['estado_laboral'] ?? 'activo',
            'tipo_contrato' => $data['tipo_contrato'] ?? null,
            'sueldo_basico' => (float) ($data['sueldo_basico'] ?? 0),
            'moneda' => $data['moneda'] ?? 'PEN',
            'asignacion_familiar' => !empty($data['asignacion_familiar']) ? 1 : 0,
            'tipo_pago' => $data['tipo_pago'] ?? null,
            'pago_diario' => (float) ($data['pago_diario'] ?? 0),
            'regimen_pensionario' => $data['regimen_pensionario'] ?? null,
            'tipo_comision_afp' => $data['tipo_comision_afp'] ?? null,
            'cuspp' => $data['cuspp'] ?? null,
            'essalud' => !empty($data['essalud']) ? 1 : 0,
            'updated_by' => $userId,
        ];

        if ($tieneCumpleanos) {
            $params['recordar_cumpleanos'] = !empty($data['recordar_cumpleanos']) ? 1 : 0;
            $params['fecha_nacimiento'] = !empty($data['recordar_cumpleanos']) && !empty($data['fecha_nacimiento'])
                ? $data['fecha_nacimiento']
                : null;
        }

        $this->db()->prepare($sql)->execute($params);
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
}
