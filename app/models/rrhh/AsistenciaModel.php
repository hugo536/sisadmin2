<?php

declare(strict_types=1);

class AsistenciaModel extends Modelo
{
    public function guardarLogBiometrico(array $data, int $userId): bool
    {
        // 1. Verificamos si esta marcación exacta ya existe en la base de datos
        // Comparamos el código del empleado y la fecha y hora EXACTA de la marca
        $sqlCheck = 'SELECT id FROM asistencia_logs_biometrico 
                     WHERE codigo_biometrico = :codigo_biometrico 
                       AND fecha_hora_marca = :fecha_hora_marca 
                     LIMIT 1';
                     
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute([
            'codigo_biometrico' => $data['codigo_biometrico'],
            'fecha_hora_marca' => $data['fecha_hora_marca']
        ]);

        // Si la marcación ya existe, devolvemos false para ignorarla
        if ($stmtCheck->fetch()) {
            return false;
        }

        // 2. Si es nueva, la insertamos normal para que sea procesada
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

    public function obtenerTurnoEfectivoPorFecha(int $idTercero, string $fecha): ?array
    {
        $sqlExcepcion = "
            SELECT ah.id,
                   ah.nombre,
                   ah.t1_entrada, ah.t1_salida,
                   ah.t2_entrada, ah.t2_salida,
                   ah.t3_entrada, ah.t3_salida,
                   ah.tolerancia_minutos,
                   1 AS es_excepcion
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
            'fecha' => $fecha,
        ]);

        $turno = $stmtExcepcion->fetch(PDO::FETCH_ASSOC);
        if ($turno) {
            return $turno;
        }

        $diaSemana = (int) date('N', strtotime($fecha));
        $sqlRegular = 'SELECT ah.id,
                              ah.nombre,
                              ah.t1_entrada, ah.t1_salida,
                              ah.t2_entrada, ah.t2_salida,
                              ah.t3_entrada, ah.t3_salida,
                              ah.tolerancia_minutos,
                              0 AS es_excepcion
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

    public function obtenerDetalleMarcacionesDia(int $idTercero, string $fecha): array
    {
         $turno = $this->obtenerTurnoEfectivoPorFecha($idTercero, $fecha) ?? [];

        $esperadas = [];
        for ($i = 1; $i <= 3; $i++) {
            $entrada = (string) ($turno['t' . $i . '_entrada'] ?? '');
            $salida = (string) ($turno['t' . $i . '_salida'] ?? '');
            if ($entrada !== '' || $salida !== '') {
                $esperadas[] = [
                    'tramo' => $i,
                    'entrada' => $entrada !== '' ? substr($entrada, 0, 5) : '',
                    'salida' => $salida !== '' ? substr($salida, 0, 5) : '',
                ];
            }
        }

        $sqlRegistro = 'SELECT hora_ingreso, hora_salida
                        FROM asistencia_registros
                        WHERE id_tercero = :id_tercero AND fecha = :fecha
                        ORDER BY id ASC';
        $stmtRegistro = $this->db()->prepare($sqlRegistro);
        $stmtRegistro->execute([
            'id_tercero' => $idTercero,
            'fecha' => $fecha,
        ]);
        $rowsRegistro = $stmtRegistro->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ingresos = [];
        $salidas = [];
        foreach ($rowsRegistro as $row) {
            if (!empty($row['hora_ingreso'])) {
                $ingresos[] = substr((string) $row['hora_ingreso'], 11, 5);
            }
            if (!empty($row['hora_salida'])) {
                $salidas[] = substr((string) $row['hora_salida'], 11, 5);
            }
        }

        return [
            'turno' => [
                'id' => (int) ($turno['id'] ?? 0),
                'nombre' => (string) ($turno['nombre'] ?? ''),
                'es_excepcion' => (int) ($turno['es_excepcion'] ?? 0),
                'tolerancia_minutos' => (int) ($turno['tolerancia_minutos'] ?? 0),
            ],
            'tramos_esperados' => $esperadas,
            'tramos_activos' => count($esperadas) > 0 ? count($esperadas) : 1,
            'ingresos_reales' => array_slice($ingresos, 0, 3),
            'salidas_reales' => array_slice($salidas, 0, 3),
        ];
    }

    /**
     * Calcula el resumen diario desde un listado cronológico de marcas biométricas.
     * Reglas:
     * - Si no hay salida (solo ingreso), el estado queda INCOMPLETO y no calcula tardanza.
     * - La tardanza se suma por tramo esperado usando las entradas reales detectadas.
     */
    public function calcularResumenDesdeMarcas(int $idTercero, string $fecha, array $marcas): array
    {
        $marcasNormalizadas = array_values(array_filter(array_map(static fn($m) => trim((string) $m), $marcas), static fn($m) => $m !== ''));
        sort($marcasNormalizadas);

        $horaIngreso = $marcasNormalizadas[0] ?? null;
        $marcasCount = count($marcasNormalizadas);
        $diaCompleto = $marcasCount >= 2 && (($marcasCount % 2) === 0);
        $horaSalida = $diaCompleto ? $marcasNormalizadas[$marcasCount - 1] : null;

        $ingresos = [];
        $salidas = [];
        foreach ($marcasNormalizadas as $index => $marca) {
            if (($index % 2) === 0) {
                $ingresos[] = $marca;
            } else {
                $salidas[] = $marca;
            }
        }

        $turno = $this->obtenerTurnoEfectivoPorFecha($idTercero, $fecha);
        $entradasEsperadas = $this->obtenerEntradasEsperadasDesdeTurno($turno);
        [$horaEntradaEsperada, $horaSalidaEsperada] = $this->obtenerLimitesEsperadosDesdeTurno($fecha, $turno);
        $tolerancia = (int) ($turno['tolerancia_minutos'] ?? 0);

        if (empty($entradasEsperadas) && $horaEntradaEsperada) {
            $entradasEsperadas = [substr((string) $horaEntradaEsperada, 11, 8)];
        }

        $estado = 'INCOMPLETO';
        $minutosTardanza = 0;
        $detalleTardanza = [];
        if ($diaCompleto && $horaIngreso !== null && $horaSalida !== null) {
            $estado = 'PUNTUAL';
            $calcTardanza = $this->calcularTardanzaPorTramos($fecha, $ingresos, $entradasEsperadas, $tolerancia);
            $minutosTardanza = $calcTardanza['total'];
            $detalleTardanza = $calcTardanza['detalle'];

            if ($minutosTardanza > 0) {
                $estado = 'TARDANZA';
            }
        } elseif ($horaIngreso === null) {
            $estado = 'FALTA';
        }

        $horasTrabajadas = 0.00;
        if ($diaCompleto && $horaIngreso !== null && $horaSalida !== null) {
            $tsIn = strtotime($horaIngreso);
            $tsOut = strtotime($horaSalida);
            if ($tsIn !== false && $tsOut !== false && $tsOut > $tsIn) {
                $horasTrabajadas = round(($tsOut - $tsIn) / 3600, 2);
            }
        }

        $horasExtras = 0.00;
        if ($horaEntradaEsperada && $horaSalidaEsperada) {
            $esperadaInTs = strtotime($horaEntradaEsperada);
            $esperadaOutTs = strtotime($horaSalidaEsperada);
            if ($esperadaInTs !== false && $esperadaOutTs !== false && $esperadaOutTs > $esperadaInTs) {
                $horasEsperadas = ($esperadaOutTs - $esperadaInTs) / 3600;
                if ($horasTrabajadas > $horasEsperadas) {
                    $horasExtras = round($horasTrabajadas - $horasEsperadas, 2);
                }
            }
        }

        return [
            'hora_ingreso' => $horaIngreso,
            'hora_salida' => $horaSalida,
            'hora_entrada_esperada' => $horaEntradaEsperada,
            'hora_salida_esperada' => $horaSalidaEsperada,
            'tolerancia_minutos' => $tolerancia,
            'estado_asistencia' => $estado,
            'minutos_tardanza' => $minutosTardanza,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extras' => $horasExtras,
            'detalle_tardanza' => $detalleTardanza,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function obtenerEntradasEsperadasDesdeTurno(?array $turno): array
    {
        $entradas = [];
        for ($i = 1; $i <= 3; $i++) {
            $entrada = trim((string) ($turno['t' . $i . '_entrada'] ?? ''));
            if ($entrada !== '') {
                $entradas[] = substr($entrada, 0, 8);
            }
        }
        return $entradas;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function obtenerLimitesEsperadosDesdeTurno(string $fecha, ?array $turno): array
    {
        $entrada = null;
        $salida = null;

        for ($i = 1; $i <= 3; $i++) {
            $e = trim((string) ($turno['t' . $i . '_entrada'] ?? ''));
            if ($e !== '' && $entrada === null) {
                $entrada = $fecha . ' ' . substr($e, 0, 8);
            }
        }

        for ($i = 3; $i >= 1; $i--) {
            $s = trim((string) ($turno['t' . $i . '_salida'] ?? ''));
            if ($s !== '' && $salida === null) {
                $salida = $fecha . ' ' . substr($s, 0, 8);
            }
        }

        return [$entrada, $salida];
    }

    /**
     * Suma tardanza por tramo, comparando cada ingreso real contra su entrada esperada.
     * Se mantiene el redondeo por minuto usando floor().
     *
     * @param array<int, string> $ingresosReales
     * @param array<int, string> $entradasEsperadas
     * @return array{total:int,detalle:array<int,array<string,mixed>>}
     */
    private function calcularTardanzaPorTramos(string $fecha, array $ingresosReales, array $entradasEsperadas, int $tolerancia): array
    {
        $total = 0;
        $detalle = [];

        $ingresosNorm = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $ingresosReales), static fn($h) => $h !== ''));
        sort($ingresosNorm);

        foreach ($entradasEsperadas as $idx => $horaEsperada) {
            if (!isset($ingresosNorm[$idx])) {
                break;
            }

            $esperadaTs = strtotime($fecha . ' ' . substr((string) $horaEsperada, 0, 8));
            $realRaw = (string) $ingresosNorm[$idx];
            $realTs = strpos($realRaw, ' ') !== false
                ? strtotime($realRaw)
                : strtotime($fecha . ' ' . substr($realRaw, 0, 8));

            if ($esperadaTs === false || $realTs === false) {
                continue;
            }

            $minutos = 0;
            if ($realTs > ($esperadaTs + ($tolerancia * 60))) {
                $retrasoBruto = (int) floor(($realTs - $esperadaTs) / 60);
                $minutos = max(0, $retrasoBruto - $tolerancia);
            }

            $total += $minutos;
            $detalle[] = [
                'tramo' => $idx + 1,
                'esperada' => substr((string) $horaEsperada, 0, 5),
                'real' => date('H:i', $realTs),
                'minutos' => $minutos,
            ];
        }

        return ['total' => $total, 'detalle' => $detalle];
    }

    public function upsertRegistroAsistencia(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_registros (
                    id_tercero,
                    fecha,
                    hora_ingreso,
                    hora_salida,
                    hora_entrada_esperada,
                    hora_salida_esperada,
                    tolerancia_minutos,
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
                    :hora_entrada_esperada,
                    :hora_salida_esperada,
                    :tolerancia_minutos,
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
                    /* Solo actualizamos las horas esperadas si vienen con datos nuevos (para proteger la memoria al usar el engranaje) */
                    hora_entrada_esperada = COALESCE(VALUES(hora_entrada_esperada), hora_entrada_esperada),
                    hora_salida_esperada = COALESCE(VALUES(hora_salida_esperada), hora_salida_esperada),
                    tolerancia_minutos = COALESCE(VALUES(tolerancia_minutos), tolerancia_minutos),
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
            'hora_entrada_esperada' => $data['hora_entrada_esperada'] ?? null,
            'hora_salida_esperada' => $data['hora_salida_esperada'] ?? null,
            'tolerancia_minutos' => $data['tolerancia_minutos'] ?? 0,
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

    // ============================================================================
    // NUEVAS FUNCIONES PARA PROCESAR EL DASHBOARD POR TRAMOS
    // ============================================================================

    private function calcularEstadoGeneral(?string $estadosStr): string
    {
        if (empty($estadosStr)) return 'FALTA';
        $estados = explode('|', $estadosStr);
        
        // Jerarquía de estados generales del día
        if (in_array('FALTA', $estados)) return 'FALTA';
        if (in_array('INCOMPLETO', $estados)) return 'INCOMPLETO';
        if (in_array('TARDANZA', $estados)) return 'TARDANZA';
        
        foreach($estados as $est) {
            if (strpos($est, 'JUSTIFICADA') !== false || strpos($est, 'PERMISO') !== false || strpos($est, 'OLVIDO') !== false) {
                return $est; // Retorna la primera justificación encontrada
            }
        }
        return 'PUNTUAL';
    }

    private function procesarFilasDashboard(array $rows, string $estadoFiltro = ''): array
    {
        $procesados = [];
        foreach ($rows as $row) {
            // 1. Determinar el Estado General del día
            $estadoGeneral = $this->calcularEstadoGeneral($row['estados_asistencia'] ?? null);

            // 2. Si hay filtro de estado, aplicarlo aquí (ya que agrupamos por SQL)
            if ($estadoFiltro !== '' && $estadoGeneral !== $estadoFiltro) {
                continue;
            }

            // 3. Formatear Horas Esperadas Compactas (prioriza memoria congelada del registro)
            $arrEsperadas = [];
            $entradaMemoria = trim((string) ($row['hora_entrada_esperada_registro'] ?? ''));
            $salidaMemoria = trim((string) ($row['hora_salida_esperada_registro'] ?? ''));

            if ($entradaMemoria !== '') {
                $arrEsperadas[] = substr($entradaMemoria, 11, 5) . ' - ' . ($salidaMemoria !== '' ? substr($salidaMemoria, 11, 5) : '--:--');
            } else {
                if (!empty($row['t1_entrada'])) $arrEsperadas[] = substr($row['t1_entrada'], 0, 5) . ' - ' . (!empty($row['t1_salida']) ? substr($row['t1_salida'], 0, 5) : '--:--');
                if (!empty($row['t2_entrada'])) $arrEsperadas[] = substr($row['t2_entrada'], 0, 5) . ' - ' . (!empty($row['t2_salida']) ? substr($row['t2_salida'], 0, 5) : '--:--');
                if (!empty($row['t3_entrada'])) $arrEsperadas[] = substr($row['t3_entrada'], 0, 5) . ' - ' . (!empty($row['t3_salida']) ? substr($row['t3_salida'], 0, 5) : '--:--');
            }
            $row['esperada_formateada'] = !empty($arrEsperadas) ? implode("\n", $arrEsperadas) : '-';

            // 4. Formatear Horas Reales Compactas
            $arrReales = [];
            $ingresos = !empty($row['horas_ingreso']) ? explode('|', $row['horas_ingreso']) : [];
            $salidas = !empty($row['horas_salida']) ? explode('|', $row['horas_salida']) : [];
            $maxTramos = max(count($ingresos), count($salidas));
            
            if ($maxTramos > 0) {
                for ($i = 0; $i < $maxTramos; $i++) {
                    $in = (!empty($ingresos[$i]) && $ingresos[$i] !== 'null') ? substr($ingresos[$i], 11, 5) : '--:--';
                    $out = (!empty($salidas[$i]) && $salidas[$i] !== 'null') ? substr($salidas[$i], 11, 5) : '--:--';
                    if ($in !== '--:--' || $out !== '--:--') {
                        $arrReales[] = $in . ' - ' . $out;
                    }
                }
            }
            $row['real_formateada'] = !empty($arrReales) ? implode("\n", $arrReales) : '-';

            // 4.1 Recalcular tardanza mostrada para evitar desfasajes históricos
            $estadoGeneralUpper = strtoupper((string) $estadoGeneral);
            $esJustificada = strpos($estadoGeneralUpper, 'JUSTIFICADA') !== false
                || strpos($estadoGeneralUpper, 'PERMISO') !== false
                || strpos($estadoGeneralUpper, 'OLVIDO') !== false;

            $ingresosCalc = [];
            foreach ($ingresos as $ing) {
                $ing = trim((string) $ing);
                if ($ing !== '' && $ing !== 'null') {
                    $ingresosCalc[] = $ing;
                }
            }
            $salidasCalc = [];
            foreach ($salidas as $out) {
                $out = trim((string) $out);
                if ($out !== '' && $out !== 'null') {
                    $salidasCalc[] = $out;
                }
            }

            $diaCompleto = count($ingresosCalc) > 0 && count($salidasCalc) > 0 && count($ingresosCalc) === count($salidasCalc);
            if ($diaCompleto && !$esJustificada) {
                $entradasEsperadasCalc = [];
                if ($entradaMemoria !== '') {
                    $entradasEsperadasCalc[] = substr($entradaMemoria, 11, 8);
                } else {
                    if (!empty($row['t1_entrada'])) $entradasEsperadasCalc[] = substr((string) $row['t1_entrada'], 0, 8);
                    if (!empty($row['t2_entrada'])) $entradasEsperadasCalc[] = substr((string) $row['t2_entrada'], 0, 8);
                    if (!empty($row['t3_entrada'])) $entradasEsperadasCalc[] = substr((string) $row['t3_entrada'], 0, 8);
                }

                $toleranciaCalc = (int) ($row['tolerancia_minutos_registro'] ?? 0);
                if ($toleranciaCalc === 0 && !empty($row['tolerancia_horario'])) {
                    $toleranciaCalc = (int) $row['tolerancia_horario'];
                }
                $calc = $this->calcularTardanzaPorTramos((string) ($row['fecha'] ?? ''), $ingresosCalc, $entradasEsperadasCalc, $toleranciaCalc);
                $row['minutos_tardanza_total'] = (int) ($calc['total'] ?? 0);
                if ($estadoGeneral === 'TARDANZA' && (int) $row['minutos_tardanza_total'] === 0) {
                    $estadoGeneral = 'PUNTUAL';
                }
            }

            // 5. Normalizar datos para la vista (compatibilidad con tus modales)
            $row['estado_asistencia'] = $estadoGeneral;
            $row['id_tercero'] = (int) ($row['id_tercero'] ?? $row['id'] ?? 0);
            $row['minutos_tardanza'] = (int) ($row['minutos_tardanza_total'] ?? 0);
            $row['hora_entrada'] = $row['t1_entrada'] ?? '';
            $row['hora_salida'] = $row['t1_salida'] ?? '';

            $procesados[] = $row;
        }
        return $procesados;
    }

    public function obtenerDashboardDiario(string $fecha, ?int $idTercero = null, string $estado = ''): array
    {
        $diaSemana = (int) date('N', strtotime($fecha));

        $sql = 'SELECT t.id,
                       t.id AS id_tercero,
                       :fecha_dashboard AS fecha,
                       t.nombre_completo,
                       IF(ah_exc.id IS NOT NULL, ah_exc.nombre, ah.nombre) AS horario_nombre,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t1_entrada, ah.t1_entrada) AS t1_entrada,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t1_salida, ah.t1_salida) AS t1_salida,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t2_entrada, ah.t2_entrada) AS t2_entrada,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t2_salida, ah.t2_salida) AS t2_salida,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t3_entrada, ah.t3_entrada) AS t3_entrada,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t3_salida, ah.t3_salida) AS t3_salida,
                       IF(ah_exc.id IS NOT NULL, ah_exc.tolerancia_minutos, ah.tolerancia_minutos) AS tolerancia_horario,
                       IF(ah_exc.id IS NOT NULL, 1, 0) AS es_excepcion,
                       GROUP_CONCAT(ar.hora_ingreso ORDER BY ar.hora_ingreso ASC SEPARATOR "|") AS horas_ingreso,
                       GROUP_CONCAT(ar.hora_salida ORDER BY ar.hora_salida ASC SEPARATOR "|") AS horas_salida,
                       MAX(ar.hora_entrada_esperada) AS hora_entrada_esperada_registro,
                       MAX(ar.hora_salida_esperada) AS hora_salida_esperada_registro,
                       MAX(ar.tolerancia_minutos) AS tolerancia_minutos_registro,
                       GROUP_CONCAT(ar.estado_asistencia SEPARATOR "|") AS estados_asistencia,
                       SUM(ar.minutos_tardanza) AS minutos_tardanza_total
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                LEFT JOIN asistencia_empleado_horario aeh ON aeh.id_tercero = t.id AND aeh.dia_semana = :dia_semana
                LEFT JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                LEFT JOIN asistencia_grupo_empleados ge_exc ON ge_exc.id_tercero = t.id
                LEFT JOIN asistencia_grupos g_exc ON g_exc.id = ge_exc.id_grupo AND g_exc.estado = 1
                LEFT JOIN asistencia_planificacion p_exc ON p_exc.id_grupo = g_exc.id
                    AND :fecha_exc BETWEEN p_exc.fecha_inicio AND p_exc.fecha_fin
                LEFT JOIN asistencia_horarios ah_exc ON ah_exc.id = p_exc.id_horario AND p_exc.id IS NOT NULL
                LEFT JOIN asistencia_registros ar ON ar.id_tercero = t.id AND ar.fecha = :fecha_registro
                WHERE t.es_empleado = 1
                  AND t.deleted_at IS NULL';

        $params = [
            'dia_semana' => $diaSemana,
            'fecha_dashboard' => $fecha,
            'fecha_exc' => $fecha,
            'fecha_registro' => $fecha,
        ];

        if ($idTercero !== null && $idTercero > 0) {
            $sql .= ' AND t.id = :id_tercero';
            $params['id_tercero'] = $idTercero;
        }

        $sql .= ' GROUP BY t.id, t.nombre_completo, horario_nombre,
                           t1_entrada, t1_salida, t2_entrada, t2_salida, t3_entrada, t3_salida,
                           tolerancia_horario, es_excepcion
                  ORDER BY t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $this->procesarFilasDashboard($rows, $estado);
    }

    public function obtenerDashboardRango(string $desde, string $hasta, ?int $idTercero = null, string $estado = ''): array
    {
        $sql = 'SELECT t.id,
                       t.id AS id_tercero,
                       ar.fecha,
                       t.nombre_completo,
                       IF(ah_exc.id IS NOT NULL, ah_exc.nombre, ah.nombre) AS horario_nombre,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t1_entrada, ah.t1_entrada) AS t1_entrada,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t1_salida, ah.t1_salida) AS t1_salida,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t2_entrada, ah.t2_entrada) AS t2_entrada,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t2_salida, ah.t2_salida) AS t2_salida,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t3_entrada, ah.t3_entrada) AS t3_entrada,
                       IF(ah_exc.id IS NOT NULL, ah_exc.t3_salida, ah.t3_salida) AS t3_salida,
                       IF(ah_exc.id IS NOT NULL, ah_exc.tolerancia_minutos, ah.tolerancia_minutos) AS tolerancia_horario,
                       IF(ah_exc.id IS NOT NULL, 1, 0) AS es_excepcion,
                       GROUP_CONCAT(ar.hora_ingreso ORDER BY ar.hora_ingreso ASC SEPARATOR "|") AS horas_ingreso,
                       GROUP_CONCAT(ar.hora_salida ORDER BY ar.hora_salida ASC SEPARATOR "|") AS horas_salida,
                       MAX(ar.hora_entrada_esperada) AS hora_entrada_esperada_registro,
                       MAX(ar.hora_salida_esperada) AS hora_salida_esperada_registro,
                       MAX(ar.tolerancia_minutos) AS tolerancia_minutos_registro,
                       GROUP_CONCAT(ar.estado_asistencia SEPARATOR "|") AS estados_asistencia,
                       SUM(ar.minutos_tardanza) AS minutos_tardanza_total
                FROM asistencia_registros ar
                INNER JOIN terceros t ON t.id = ar.id_tercero
                LEFT JOIN asistencia_empleado_horario aeh
                    ON aeh.id_tercero = t.id
                   AND aeh.dia_semana = (WEEKDAY(ar.fecha) + 1)
                LEFT JOIN asistencia_horarios ah ON ah.id = aeh.id_horario
                LEFT JOIN asistencia_grupo_empleados ge_exc ON ge_exc.id_tercero = t.id
                LEFT JOIN asistencia_grupos g_exc ON g_exc.id = ge_exc.id_grupo AND g_exc.estado = 1
                LEFT JOIN asistencia_planificacion p_exc ON p_exc.id_grupo = g_exc.id
                    AND ar.fecha BETWEEN p_exc.fecha_inicio AND p_exc.fecha_fin
                LEFT JOIN asistencia_horarios ah_exc ON ah_exc.id = p_exc.id_horario AND p_exc.id IS NOT NULL
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

        $sql .= ' GROUP BY t.id, ar.fecha, t.nombre_completo, horario_nombre,
                           t1_entrada, t1_salida, t2_entrada, t2_salida, t3_entrada, t3_salida,
                           tolerancia_horario, es_excepcion
                  ORDER BY ar.fecha DESC, t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $this->procesarFilasDashboard($rows, $estado);
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
        $sqlCheck = "SELECT * FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1";
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute([
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha']
        ]);
        $registroExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $ingresos = $data['horas_ingreso_real'] ?? [];
        $salidas = $data['horas_salida_real'] ?? [];
        if (!is_array($ingresos)) $ingresos = [];
        if (!is_array($salidas)) $salidas = [];

        $ingresos = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $ingresos), static fn($h) => $h !== ''));
        $salidas = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $salidas), static fn($h) => $h !== ''));

        $horaIngresoManual = trim((string) ($data['hora_ingreso_real'] ?? ''));
        $horaSalidaManual = trim((string) ($data['hora_salida_real'] ?? ''));
        if ($horaIngresoManual !== '' && empty($ingresos)) {
            $ingresos[] = $horaIngresoManual;
        }
        if ($horaSalidaManual !== '' && empty($salidas)) {
            $salidas[] = $horaSalidaManual;
        }

        $horaIngresoFinal = !empty($ingresos) ? $data['fecha'] . ' ' . $ingresos[0] . ':00' : null;
        $horaSalidaFinal = !empty($salidas) ? $data['fecha'] . ' ' . $salidas[count($salidas) - 1] . ':00' : null;

        $minutosTardanza = 0;
        $estadoFinal = 'INCOMPLETO';

        if ($horaIngresoFinal !== null && $horaSalidaFinal !== null) {
            $estadoFinal = 'PUNTUAL';
        } elseif ($horaIngresoFinal === null && $horaSalidaFinal === null) {
            $estadoFinal = 'FALTA';
        }

        // --- Memoria Activa (Buscamos la info congelada) ---
        $horaEntradaEsperada = $registroExistente['hora_entrada_esperada'] ?? null;
        $horaSalidaEsperada = $registroExistente['hora_salida_esperada'] ?? null;
        $toleranciaBD = (int) ($registroExistente['tolerancia_minutos'] ?? 0);

        // Si no hay memoria (porque era un registro viejo), la buscamos
        if (!$horaEntradaEsperada) {
            $horario = $this->obtenerHorarioEsperado($data['id_tercero'], $data['fecha']);
            if ($horario) {
                $horaEntradaEsperada = $data['fecha'] . ' ' . substr((string) $horario['hora_entrada'], 0, 8);
                $horaSalidaEsperada = $data['fecha'] . ' ' . substr((string) $horario['hora_salida'], 0, 8);
                $toleranciaBD = (int) ($horario['tolerancia_minutos'] ?? 0);
            }
        }

        // Calcular la tardanza por tramo (solo si el día está completo: ingreso + salida)
        if ($horaIngresoFinal !== null && $horaSalidaFinal !== null) {
            $turno = $this->obtenerTurnoEfectivoPorFecha((int) $data['id_tercero'], (string) $data['fecha']);
            $entradasEsperadas = $this->obtenerEntradasEsperadasDesdeTurno($turno);
            if (empty($entradasEsperadas) && $horaEntradaEsperada) {
                $entradasEsperadas = [substr((string) $horaEntradaEsperada, 11, 8)];
            }

            $calcTardanza = $this->calcularTardanzaPorTramos((string) $data['fecha'], $ingresos, $entradasEsperadas, $toleranciaBD);
            $minutosTardanza = (int) ($calcTardanza['total'] ?? 0);
            if ($minutosTardanza > 0) {
                $estadoFinal = 'TARDANZA';
            }
        }

        // --- Justificaciones sobrescriben el estado ---
        if (!empty($data['aplicar_justificacion'])) {
            $estadoFinal = $data['nuevo_estado'];
            $minutosTardanza = 0; 
        }

        // --- Construcción de Observaciones ---
        $obsActual = $registroExistente && !empty($registroExistente['observaciones']) ? $registroExistente['observaciones'] . ' | ' : '';
        $nuevaObs = $obsActual;
        if (!empty($data['observacion'])) {
            $prefix = !empty($data['aplicar_justificacion']) ? '[Justificado]' : '[Editado Manual]';
            $nuevaObs .= $prefix . ' ' . $data['observacion'];
        }

        // Calcular horas trabajadas (opcional pero útil)
        $horasTrabajadas = 0.00;
        if ($horaIngresoFinal && $horaSalidaFinal) {
            $horasTrabajadas = round((strtotime($horaSalidaFinal) - strtotime($horaIngresoFinal)) / 3600, 2);
        }

        $upsertData = [
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha'],
            'hora_ingreso' => $horaIngresoFinal,
            'hora_salida' => $horaSalidaFinal,
            'hora_entrada_esperada' => $horaEntradaEsperada,
            'hora_salida_esperada' => $horaSalidaEsperada,
            'tolerancia_minutos' => $toleranciaBD,
            'estado_asistencia' => $estadoFinal,
            'minutos_tardanza' => $minutosTardanza,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extras' => 0, 
            'observaciones' => $nuevaObs,
        ];

        return $this->upsertRegistroAsistencia($upsertData, $userId);
    }

     public function existeRegistroAsistencia(int $idTercero, string $fecha): bool
    {
        $sql = 'SELECT id FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_tercero' => $idTercero, 'fecha' => $fecha]);
        return (bool) $stmt->fetch();
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

        // 1. Definimos cuáles serán las horas finales a guardar
        $horaIngresoFinal = !empty($data['hora_ingreso']) ? $data['hora_ingreso'] : ($registroExistente['hora_ingreso'] ?? null);
        $horaSalidaFinal = !empty($data['hora_salida']) ? $data['hora_salida'] : ($registroExistente['hora_salida'] ?? null);

        // Obtener todos los ingresos por tramo para cálculo de tardanza
        $ingresosTramo = $data['horas_ingreso'] ?? [];
        $salidasTramo = $data['horas_salida'] ?? [];
        if (!is_array($ingresosTramo)) $ingresosTramo = [];
        if (!is_array($salidasTramo)) $salidasTramo = [];
        $ingresosTramo = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $ingresosTramo), static fn($h) => $h !== ''));
        $salidasTramo = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $salidasTramo), static fn($h) => $h !== ''));

        // 2. Variables por defecto
        $minutosTardanza = 0;
        $estado_asistencia = 'INCOMPLETO';

        if ($horaIngresoFinal !== null && $horaSalidaFinal !== null) {
            $estado_asistencia = 'PUNTUAL';
        } elseif ($horaIngresoFinal === null && $horaSalidaFinal === null) {
            $estado_asistencia = 'FALTA';
        }

        // 3. Obtener turno efectivo para tolerancia y entradas esperadas
        $turno = $this->obtenerTurnoEfectivoPorFecha((int) $data['id_tercero'], (string) $data['fecha']);
        $entradasEsperadas = $this->obtenerEntradasEsperadasDesdeTurno($turno);
        $tolerancia = (int) ($turno['tolerancia_minutos'] ?? 0);
        [$horaEntradaEsperada, $horaSalidaEsperada] = $this->obtenerLimitesEsperadosDesdeTurno((string) $data['fecha'], $turno);

        if (empty($entradasEsperadas)) {
            $horario = $this->obtenerHorarioEsperado((int) $data['id_tercero'], (string) $data['fecha']);
            if ($horario) {
                $entradaHorario = trim((string) ($horario['hora_entrada'] ?? ''));
                if ($entradaHorario !== '') {
                    $entradasEsperadas = [substr($entradaHorario, 0, 8)];
                }
                $tolerancia = (int) ($horario['tolerancia_minutos'] ?? $tolerancia);
                if (!$horaEntradaEsperada) {
                    $horaEntradaEsperada = $data['fecha'] . ' ' . substr((string) $horario['hora_entrada'], 0, 8);
                }
                if (!$horaSalidaEsperada) {
                    $horaSalidaEsperada = $data['fecha'] . ' ' . substr((string) $horario['hora_salida'], 0, 8);
                }
            }
        }

        // 4. APLICAMOS LA LÓGICA DE TARDANZA POR TRAMO (solo cuando está completo)
        if ($horaIngresoFinal !== null && $horaSalidaFinal !== null) {
            // Usar los ingresos por tramo si están disponibles, sino usar el primer ingreso
            $ingresos = !empty($ingresosTramo) ? $ingresosTramo : [];
            if (empty($ingresos) && !empty($horaIngresoFinal)) {
                $ingresos[] = $horaIngresoFinal;
            }

            $calcTardanza = $this->calcularTardanzaPorTramos((string) $data['fecha'], $ingresos, $entradasEsperadas, $tolerancia);
            $minutosTardanza = (int) ($calcTardanza['total'] ?? 0);

            if ($minutosTardanza > 0) {
                $estado_asistencia = 'TARDANZA';
            }
        }

        // Calcular horas trabajadas
        $horasTrabajadas = 0.00;
        if ($horaIngresoFinal && $horaSalidaFinal) {
            $tsIn = strtotime($horaIngresoFinal);
            $tsOut = strtotime($horaSalidaFinal);
            if ($tsIn !== false && $tsOut !== false && $tsOut > $tsIn) {
                $horasTrabajadas = round(($tsOut - $tsIn) / 3600, 2);
            }
        }

        // 5. Guardamos con memoria (hora_entrada_esperada, hora_salida_esperada, tolerancia)
        $upsertData = [
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha'],
            'hora_ingreso' => $horaIngresoFinal,
            'hora_salida' => $horaSalidaFinal,
            'hora_entrada_esperada' => $horaEntradaEsperada,
            'hora_salida_esperada' => $horaSalidaEsperada,
            'tolerancia_minutos' => $tolerancia,
            'estado_asistencia' => $estado_asistencia,
            'minutos_tardanza' => $minutosTardanza,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extras' => 0,
            'observaciones' => $nuevaObs,
        ];

        return $this->upsertRegistroAsistencia($upsertData, $userId);
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
