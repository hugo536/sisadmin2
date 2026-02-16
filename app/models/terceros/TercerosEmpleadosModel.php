<?php
declare(strict_types=1);

class TercerosEmpleadosModel extends Modelo
{
    /** @var array<string,bool> */
    private array $columnCache = [];

    /**
     * Guarda o actualiza la información del empleado y sus hijos.
     */
    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $this->db()->beginTransaction();

        try {
            // 1. GUARDAR DATOS DEL EMPLEADO (Lógica original)
            $tieneCumpleanos = $this->hasColumn('terceros_empleados', 'recordar_cumpleanos')
                && $this->hasColumn('terceros_empleados', 'fecha_nacimiento');

            $columnas = [
                'id_tercero', 'cargo', 'area', 'fecha_ingreso', 'fecha_cese', 'estado_laboral',
                'tipo_contrato', 'sueldo_basico', 'moneda', 'asignacion_familiar',
                'tipo_pago', 'pago_diario', 'regimen_pensionario', 'tipo_comision_afp', 'cuspp', 'essalud',
                'genero', 'estado_civil', 'nivel_educativo',
                'contacto_emergencia_nombre', 'contacto_emergencia_telf', 'tipo_sangre',
            ];

            if ($this->hasColumn('terceros_empleados', 'created_by')) {
                $columnas[] = 'created_by';
            }

            if ($tieneCumpleanos) {
                $columnas[] = 'recordar_cumpleanos';
                $columnas[] = 'fecha_nacimiento';
            }

            $columnas[] = 'updated_by';

            $placeholders = array_map(static fn (string $col): string => ':' . $col, $columnas);
            // Filtramos id_tercero y created_by para que no se sobreescriban en el UPDATE
            $updates = array_map(static fn (string $col): string => $col . ' = VALUES(' . $col . ')', array_filter($columnas, static fn (string $col): bool => !in_array($col, ['id_tercero', 'created_by'], true)));
            $updates[] = 'updated_at = NOW()';

            $sql = 'INSERT INTO terceros_empleados (' . implode(', ', $columnas) . ')
                    VALUES (' . implode(', ', $placeholders) . ')
                    ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

            $fechaIngresoGuardada = $this->obtenerFechaIngresoActual($idTercero);
            $fechaIngreso = $fechaIngresoGuardada['existe_registro']
                ? $fechaIngresoGuardada['fecha_ingreso']
                : ($data['fecha_ingreso'] ?? null);

            $asignacionFamiliar = !empty($data['asignacion_familiar']) ? 1 : 0;

            $params = [
                'id_tercero' => $idTercero,
                'cargo' => $data['cargo'] ?? null,
                'area' => $data['area'] ?? null,
                'fecha_ingreso' => $fechaIngreso,
                'fecha_cese' => $data['fecha_cese'] ?? null,
                'estado_laboral' => $data['estado_laboral'] ?? 'activo',
                'tipo_contrato' => $data['tipo_contrato'] ?? null,
                'sueldo_basico' => (float) ($data['sueldo_basico'] ?? 0),
                'moneda' => $data['moneda'] ?? 'PEN',
                'asignacion_familiar' => $asignacionFamiliar,
                'tipo_pago' => $data['tipo_pago'] ?? null,
                'pago_diario' => (float) ($data['pago_diario'] ?? 0),
                'regimen_pensionario' => $data['regimen_pensionario'] ?? null,
                'tipo_comision_afp' => $data['tipo_comision_afp'] ?? null,
                'cuspp' => $data['cuspp'] ?? null,
                'essalud' => !empty($data['essalud']) ? 1 : 0,
                'genero' => $data['genero'] ?? null,
                'estado_civil' => $data['estado_civil'] ?? null,
                'nivel_educativo' => $data['nivel_educativo'] ?? null,
                'contacto_emergencia_nombre' => $data['contacto_emergencia_nombre'] ?? null,
                'contacto_emergencia_telf' => $data['contacto_emergencia_telf'] ?? null,
                'tipo_sangre' => $data['tipo_sangre'] ?? null,
                'updated_by' => $userId,
            ];

            if ($this->hasColumn('terceros_empleados', 'created_by')) {
                $params['created_by'] = $userId;
            }

            if ($tieneCumpleanos) {
                $params['recordar_cumpleanos'] = !empty($data['recordar_cumpleanos']) ? 1 : 0;
                $params['fecha_nacimiento'] = !empty($data['recordar_cumpleanos']) && !empty($data['fecha_nacimiento'])
                    ? $data['fecha_nacimiento']
                    : null;
            }

            $this->db()->prepare($sql)->execute($params);

            // 2. PROCESAR HIJOS (Lógica integrada del modelo eliminado)
            if (isset($data['hijos_lista']) && is_array($data['hijos_lista'])) {
                $this->procesarHijos($idTercero, $data['hijos_lista'], $asignacionFamiliar === 1);
            } elseif ($asignacionFamiliar === 0) {
                // Si no se envía lista pero se desactivó la asignación, borramos todo por seguridad
                $this->eliminarTodosLosHijos($idTercero);
            }

            $this->db()->commit();
        } catch (Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }

    /**
     * Lógica de sincronización de hijos (Insertar, Actualizar y Eliminar)
     */
    private function procesarHijos(int $idEmpleado, array $hijos, bool $activo): void
    {
        // Si la asignación familiar está desactivada, eliminamos todos los hijos
        if (!$activo) {
            $this->eliminarTodosLosHijos($idEmpleado);
            return;
        }

        // 1. Obtener IDs actuales para saber qué borrar
        $stmt = $this->db()->prepare('SELECT id FROM terceros_empleados_hijos WHERE id_empleado = :id');
        $stmt->execute(['id' => $idEmpleado]);
        $idsEnBaseDatos = $stmt->fetchAll(PDO::FETCH_COLUMN); // [1, 5, 8]

        $idsRecibidos = [];
        
        // 2. Recorrer la lista enviada desde el formulario
        foreach ($hijos as $hijo) {
            $idHijo = (int)($hijo['id'] ?? 0);
            
            // Validaciones básicas antes de insertar
            if (empty($hijo['nombre_completo']) || empty($hijo['fecha_nacimiento'])) {
                continue;
            }

            $paramsHijo = [
                'id_empleado'      => $idEmpleado,
                'nombre_completo'  => trim($hijo['nombre_completo']),
                'fecha_nacimiento' => $hijo['fecha_nacimiento'],
                'esta_estudiando'  => !empty($hijo['esta_estudiando']) ? 1 : 0,
                'discapacidad'     => !empty($hijo['discapacidad']) ? 1 : 0,
            ];

            if ($idHijo > 0) {
                // UPDATE
                $sql = 'UPDATE terceros_empleados_hijos 
                        SET nombre_completo = :nombre_completo,
                            fecha_nacimiento = :fecha_nacimiento,
                            esta_estudiando = :esta_estudiando,
                            discapacidad = :discapacidad,
                            updated_at = NOW()
                        WHERE id = :id AND id_empleado = :id_empleado';
                $this->db()->prepare($sql)->execute($paramsHijo + ['id' => $idHijo]);
                $idsRecibidos[] = $idHijo;
            } else {
                // INSERT
                $sql = 'INSERT INTO terceros_empleados_hijos (id_empleado, nombre_completo, fecha_nacimiento, esta_estudiando, discapacidad, created_at)
                        VALUES (:id_empleado, :nombre_completo, :fecha_nacimiento, :esta_estudiando, :discapacidad, NOW())';
                $this->db()->prepare($sql)->execute($paramsHijo);
                
                // Agregamos el ID insertado a la lista de "recibidos" para que no se borre si hacemos lógica compleja
                // (Aunque en este flujo simple, los nuevos no están en la DB al principio, así que no afecta el DELETE)
            }
        }

        // 3. Eliminar los que estaban en BD pero no llegaron en el formulario (fueron borrados en el front)
        $idsParaBorrar = array_diff($idsEnBaseDatos, $idsRecibidos);
        if (!empty($idsParaBorrar)) {
            $placeholders = implode(',', array_fill(0, count($idsParaBorrar), '?'));
            $sqlDelete = "DELETE FROM terceros_empleados_hijos WHERE id_empleado = ? AND id IN ($placeholders)";
            // El primer parámetro es id_empleado, luego los IDs a borrar
            $paramsDelete = array_merge([$idEmpleado], array_values($idsParaBorrar));
            $this->db()->prepare($sqlDelete)->execute($paramsDelete);
        }
    }

    private function eliminarTodosLosHijos(int $idEmpleado): void
    {
        $this->db()->prepare('DELETE FROM terceros_empleados_hijos WHERE id_empleado = :id')
             ->execute(['id' => $idEmpleado]);
    }

    // ==========================================
    // MÉTODOS DE LECTURA (Traídos del modelo eliminado)
    // ==========================================

    public function listarHijos(int $idEmpleado): array
    {
        // Nota: Quitamos la verificación de tablaDisponible para mejor rendimiento, 
        // asumiendo que la tabla existe si se ejecuta el código.
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

    public function tieneMayorSinJustificar(int $idEmpleado): bool
    {
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

    // ==========================================
    // HELPERS EXISTENTES
    // ==========================================

    private function obtenerFechaIngresoActual(int $idTercero): array
    {
        $stmt = $this->db()->prepare('SELECT fecha_ingreso FROM terceros_empleados WHERE id_tercero = :id LIMIT 1');
        $stmt->execute(['id' => $idTercero]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['existe_registro' => false, 'fecha_ingreso' => null];
        }

        return [
            'existe_registro' => true,
            'fecha_ingreso' => $row['fecha_ingreso'] ?? null,
        ];
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