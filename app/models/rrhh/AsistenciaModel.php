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

    /**
     * Obtiene el horario que un empleado debe cumplir en una fecha.
     * Prioriza Excepciones (Grupos) sobre el Horario Regular.
     * * NOTA: Cambiamos el parámetro de int $diaSemana a string $fecha.
     */
    public function obtenerHorarioEsperado(int $idTercero, string $fecha): ?array
    {
        // 1. PRIMERO BUSCAMOS SI HAY UNA EXCEPCIÓN PLANIFICADA PARA ESTE EMPLEADO EN ESTA FECHA
        $sqlExcepcion = "
            SELECT ah.id,
                   ah.nombre,
                   COALESCE(ah.t1_entrada, ah.t2_entrada, ah.t3_entrada) AS hora_entrada,
                   COALESCE(ah.t3_salida, ah.t2_salida, ah.t1_salida) AS hora_salida,
                   ah.tolerancia_minutos,
                   1 as es_excepcion
            FROM asistencia_grupo_empleados ge
            INNER JOIN asistencia_planificacion p ON ge.id_grupo = p.id_grupo
            INNER JOIN asistencia_horarios ah ON p.id_horario = ah.id
            INNER JOIN asistencia_grupos g ON ge.id_grupo = g.id
            WHERE ge.id_tercero = :id_tercero 
              AND g.estado = 1 
              AND :fecha BETWEEN p.fecha_inicio AND p.fecha_fin
            LIMIT 1
        ";
        
        $stmtExcepcion = $this->db()->prepare($sqlExcepcion);
        $stmtExcepcion->execute([
            'id_tercero' => $idTercero,
            'fecha' => $fecha
        ]);
        
        $horarioExcepcion = $stmtExcepcion->fetch(PDO::FETCH_ASSOC);

        if ($horarioExcepcion) {
            return $horarioExcepcion;
        }

        // 2. SI NO HAY EXCEPCIÓN, BUSCAMOS EL HORARIO REGULAR (Base de datos original)
        $diaSemana = (int) date('N', strtotime($fecha));

        $sqlRegular = 'SELECT ah.id,
                               ah.nombre,
                               COALESCE(ah.t1_entrada, ah.t2_entrada, ah.t3_entrada) AS hora_entrada,
                               COALESCE(ah.t3_salida, ah.t2_salida, ah.t1_salida) AS hora_salida,
                               ah.tolerancia_minutos,
                               0 as es_excepcion
                        FROM asistencia_empleado_horario aeh
                        INNER JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                        WHERE aeh.id_tercero = :id_tercero
                          AND aeh.dia_semana = :dia_semana
                          AND ah.estado = 1
                          AND ah.deleted_at IS NULL
                        LIMIT 1';

        $stmtRegular = $this->db()->prepare($sqlRegular);
        $stmtRegular->execute([
            'id_tercero' => $idTercero,
            'dia_semana' => $diaSemana,
        ]);

        $rowRegular = $stmtRegular->fetch(PDO::FETCH_ASSOC);
        return $rowRegular ?: null;
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
                       COALESCE(ah.t1_entrada, ah.t2_entrada, ah.t3_entrada) AS hora_entrada,
                       COALESCE(ah.t3_salida, ah.t2_salida, ah.t1_salida) AS hora_salida,
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
                       COALESCE(ah.t1_entrada, ah.t2_entrada, ah.t3_entrada) AS hora_entrada,
                       COALESCE(ah.t3_salida, ah.t2_salida, ah.t1_salida) AS hora_salida,
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
        $sqlCheck = "SELECT id, hora_ingreso, hora_salida FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1";
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute([
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha']
        ]);
        $registroExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $minutosTardanza = 0;
        $horaRealLlegada = null;

        if ($registroExistente && !empty($registroExistente['hora_ingreso'])) {
            $horaRealLlegada = substr($registroExistente['hora_ingreso'], 11, 5); 
        }

        if ($horaRealLlegada && empty($data['aplicar_justificacion'])) {
            $tsEsperada = strtotime($data['fecha'] . ' ' . $data['hora_entrada_esperada'] . ':00');
            $tsReal = strtotime($registroExistente['hora_ingreso']);
            
            if ($tsReal > $tsEsperada) {
                $minutosTardanza = (int) floor(($tsReal - $tsEsperada) / 60);
            }
        }

        $estadoFinal = 'FALTA';
        
        if (!empty($data['aplicar_justificacion'])) {
            $estadoFinal = $data['nuevo_estado']; 
            $minutosTardanza = 0; 
        } elseif ($horaRealLlegada) {
            $estadoFinal = ($minutosTardanza > 0) ? 'TARDANZA' : 'PUNTUAL';
        }

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
            'horas_trabajadas' => 0, 
            'horas_extras' => 0,
            'observaciones' => $observacionEstructurada,
        ];

        return $this->upsertRegistroAsistencia($upsertData, $userId);
    }

    public function guardarAsistenciaManual(array $data, int $userId): bool
    {
        $sqlCheck = 'SELECT id, hora_ingreso, hora_salida, observaciones
                     FROM asistencia_registros
                     WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1';
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute([
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha']
        ]);
        $registroExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $obsActual = $registroExistente && !empty($registroExistente['observaciones'])
                     ? (string)$registroExistente['observaciones'] . ' | '
                     : '';
        $nuevaObs = $obsActual . '[Manual]: ' . $data['observaciones'];

        if ($registroExistente) {
            $sql = 'UPDATE asistencia_registros
                    SET hora_ingreso = COALESCE(:hora_ingreso, hora_ingreso),
                        hora_salida = COALESCE(:hora_salida, hora_salida),
                        observaciones = :observaciones,
                        updated_at = NOW(),
                        updated_by = :updated_by
                    WHERE id = :id';
                    
            return $this->db()->prepare($sql)->execute([
                'hora_ingreso'  => !empty($data['hora_ingreso']) ? $data['hora_ingreso'] : null,
                'hora_salida'   => !empty($data['hora_salida']) ? $data['hora_salida'] : null,
                'observaciones' => $nuevaObs,
                'updated_by'    => $userId,
                'id'            => $registroExistente['id']
            ]);
        } else {
            $sql = 'INSERT INTO asistencia_registros
                    (id_tercero, fecha, hora_ingreso, hora_salida, estado_asistencia, observaciones, created_at, created_by, updated_by)
                    VALUES (:id_tercero, :fecha, :hora_ingreso, :hora_salida, :estado_asistencia, :observaciones, NOW(), :created_by, :updated_by)';
            
            $estado_asistencia = (!empty($data['hora_ingreso']) && !empty($data['hora_salida'])) ? 'PUNTUAL' : 'INCOMPLETO';

            return $this->db()->prepare($sql)->execute([
                'id_tercero'        => $data['id_tercero'],
                'fecha'             => $data['fecha'],
                'hora_ingreso'      => !empty($data['hora_ingreso']) ? $data['hora_ingreso'] : null,
                'hora_salida'       => !empty($data['hora_salida']) ? $data['hora_salida'] : null,
                'estado_asistencia' => $estado_asistencia,
                'observaciones'     => $nuevaObs,
                'created_by'        => $userId,
                'updated_by'        => $userId
            ]);
        }
    }

    // ============================================================================
    // NUEVAS FUNCIONES PARA GESTIÓN DE GRUPOS Y PLANIFICACIÓN DE EXCEPCIONES
    // ============================================================================

    public function listarGruposExcepcion(): array
    {
        $sql = 'SELECT 
                    g.id, 
                    g.nombre, 
                    g.color,
                    p.fecha_inicio,
                    p.fecha_fin,
                    COUNT(ge.id_tercero) AS total_miembros
                FROM asistencia_grupos g
                LEFT JOIN asistencia_planificacion p ON p.id_grupo = g.id
                LEFT JOIN asistencia_grupo_empleados ge ON ge.id_grupo = g.id
                WHERE g.estado = 1
                GROUP BY g.id, g.nombre, g.color, p.fecha_inicio, p.fecha_fin
                ORDER BY p.fecha_inicio DESC, g.id DESC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarEmpleadosSinGrupo(): array
    {
        $sql = 'SELECT t.id, t.nombre_completo
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE t.es_empleado = 1
                  AND t.deleted_at IS NULL
                  AND t.id NOT IN (
                      SELECT ge.id_tercero 
                      FROM asistencia_grupo_empleados ge
                      INNER JOIN asistencia_grupos g ON g.id = ge.id_grupo
                      WHERE g.estado = 1
                  )
                ORDER BY t.nombre_completo ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Valida si los empleados seleccionados ya pertenecen a un grupo activo
     * que se solape con el nuevo rango de fechas.
     */
    public function validarSolapamientoEmpleados(array $empleados_ids, string $fecha_inicio, string $fecha_fin): array
    {
        if (empty($empleados_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($empleados_ids), '?'));

        $sql = "
            SELECT ge.id_tercero, t.nombre_completo, g.nombre as nombre_grupo
            FROM asistencia_grupo_empleados ge
            INNER JOIN asistencia_planificacion p ON ge.id_grupo = p.id_grupo
            INNER JOIN asistencia_grupos g ON ge.id_grupo = g.id
            LEFT JOIN terceros t ON ge.id_tercero = t.id
            WHERE g.estado = 1 
              AND ge.id_tercero IN ($placeholders)
              AND (p.fecha_inicio <= ? AND p.fecha_fin >= ?)
        ";

        $params = array_merge($empleados_ids, [$fecha_fin, $fecha_inicio]);

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; 
    }

    public function crearGrupoExcepcion(array $data, int $userId): bool
    {
        try {
            $this->db()->beginTransaction();

            // 1. Guardar el nuevo turno (horario) como una excepción
            $nombreHorario = "Especial: " . $data['nombre'] . " (" . date('d/m', strtotime($data['fecha_inicio'])) . ")";
            $sqlHorario = 'INSERT INTO asistencia_horarios (
                               nombre, 
                               t1_entrada, t1_salida, 
                               t2_entrada, t2_salida, 
                               t3_entrada, t3_salida, 
                               tolerancia_minutos, 
                               estado, 
                               created_by
                           ) VALUES (
                               :nombre, 
                               :t1_e, :t1_s, 
                               :t2_e, :t2_s, 
                               :t3_e, :t3_s, 
                               :tolerancia, /* <-- Aquí inyectamos la tolerancia dinámica */
                               1, 
                               :user
                           )';
                           
            $stmtH = $this->db()->prepare($sqlHorario);
            $stmtH->execute([
                'nombre'     => $nombreHorario,
                't1_e'       => !empty($data['t1_entrada']) ? $data['t1_entrada'] : null,
                't1_s'       => !empty($data['t1_salida']) ? $data['t1_salida'] : null,
                't2_e'       => !empty($data['t2_entrada']) ? $data['t2_entrada'] : null,
                't2_s'       => !empty($data['t2_salida']) ? $data['t2_salida'] : null,
                't3_e'       => !empty($data['t3_entrada']) ? $data['t3_entrada'] : null,
                't3_s'       => !empty($data['t3_salida']) ? $data['t3_salida'] : null,
                'tolerancia' => $data['tolerancia_minutos'], // <-- Pasamos el dato
                'user'       => $userId
            ]);
            
            $idHorario = (int) $this->db()->lastInsertId();

            // 2. Crear el Grupo
            $sqlGrupo = 'INSERT INTO asistencia_grupos (nombre, color, estado) VALUES (:nombre, :color, 1)';
            $stmtG = $this->db()->prepare($sqlGrupo);
            $stmtG->execute([
                'nombre' => $data['nombre'],
                'color'  => $data['color']
            ]);
            
            $idGrupo = (int) $this->db()->lastInsertId();

            // 3. Crear la Planificación (Unir Grupo + Horario + Fechas)
            $sqlPlan = 'INSERT INTO asistencia_planificacion (id_grupo, id_horario, fecha_inicio, fecha_fin, created_by) 
                        VALUES (:grupo, :horario, :f_ini, :f_fin, :user)';
            $stmtP = $this->db()->prepare($sqlPlan);
            $stmtP->execute([
                'grupo'   => $idGrupo,
                'horario' => $idHorario,
                'f_ini'   => $data['fecha_inicio'],
                'f_fin'   => $data['fecha_fin'],
                'user'    => $userId
            ]);

            // 4. Asignar los Empleados al Grupo
            $sqlEmp = 'INSERT INTO asistencia_grupo_empleados (id_grupo, id_tercero) VALUES (:grupo, :empleado)';
            $stmtE = $this->db()->prepare($sqlEmp);
            
            foreach ($data['empleados'] as $idTercero) {
                $stmtE->execute([
                    'grupo'    => $idGrupo,
                    'empleado' => (int) $idTercero
                ]);
            }

            $this->db()->commit();
            return true;

        } catch (Throwable $e) {
            $this->db()->rollBack();
            // Puedes loguear el error aquí: error_log($e->getMessage());
            return false;
        }
    }

    public function eliminarGrupoExcepcion(int $idGrupo): bool
    {
        $sql = 'DELETE FROM asistencia_grupos WHERE id = :id';
        return $this->db()->prepare($sql)->execute(['id' => $idGrupo]);
    }

    public function listarEmpleadosDisponibles(string $fechaInicio, string $fechaFin): array
    {
        // Traemos a todos los empleados EXCEPTO los que tengan un grupo 
        // activo cuyas fechas se crucen con el rango solicitado.
        $sql = 'SELECT t.id, t.nombre_completo
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE t.es_empleado = 1
                  AND t.deleted_at IS NULL
                  AND t.id NOT IN (
                      SELECT ge.id_tercero 
                      FROM asistencia_grupo_empleados ge
                      INNER JOIN asistencia_planificacion p ON p.id_grupo = ge.id_grupo
                      INNER JOIN asistencia_grupos g ON g.id = ge.id_grupo
                      WHERE g.estado = 1
                        AND p.fecha_inicio <= :fecha_fin 
                        AND p.fecha_fin >= :fecha_inicio
                  )
                ORDER BY t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerDetalleGrupo(int $idGrupo): ?array
    {
        // 1. Obtener los datos del grupo y su horario
        $sql = 'SELECT g.nombre, g.color, 
                       h.t1_entrada, h.t1_salida, 
                       h.t2_entrada, h.t2_salida, 
                       h.t3_entrada, h.t3_salida, 
                       h.tolerancia_minutos
                FROM asistencia_grupos g
                LEFT JOIN asistencia_planificacion p ON p.id_grupo = g.id
                LEFT JOIN asistencia_horarios h ON p.id_horario = h.id
                WHERE g.id = :id AND g.estado = 1 LIMIT 1';
                
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idGrupo]);
        $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$grupo) {
            return null;
        }

        // 2. Obtener los empleados asignados a este grupo
        $sqlEmp = 'SELECT ge.id_tercero as id, t.nombre_completo 
                   FROM asistencia_grupo_empleados ge
                   INNER JOIN terceros t ON ge.id_tercero = t.id
                   WHERE ge.id_grupo = :id';
                   
        $stmtEmp = $this->db()->prepare($sqlEmp);
        $stmtEmp->execute(['id' => $idGrupo]);
        $grupo['empleados'] = $stmtEmp->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $grupo;
    }
}