<?php
declare(strict_types=1);

class AsistenciaModel extends Modelo
{
    // =========================================================================
    // 1. CONFIGURACIÓN DINÁMICA
    // =========================================================================
    private function obtenerConfiguracionRRHH(): array
    {
        try {
            $stmt = $this->db()->query("SELECT * FROM rrhh_configuracion WHERE id = 1 LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            return $config ?: [
                'meta_horas_diarias' => 8, 
                'bloque_minutos' => 30, 
                'minutos_tolerancia' => 14
            ];
        } catch (Exception $e) {
            return [
                'meta_horas_diarias' => 8, 
                'bloque_minutos' => 30, 
                'minutos_tolerancia' => 14
            ];
        }
    }

    // =========================================================================
    // 2. BIOMÉTRICO (Crudo)
    // =========================================================================
    public function guardarLogBiometrico(array $data, int $userId): bool
    {
        $sqlCheck = 'SELECT id FROM asistencia_logs_biometrico 
                     WHERE codigo_biometrico = :codigo_biometrico 
                       AND fecha_hora_marca = :fecha_hora_marca LIMIT 1';
                     
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute([
            'codigo_biometrico' => $data['codigo_biometrico'],
            'fecha_hora_marca' => $data['fecha_hora_marca']
        ]);

        if ($stmtCheck->fetch()) return false;

        $sql = 'INSERT INTO asistencia_logs_biometrico 
                (codigo_biometrico, fecha_hora_marca, tipo_marca, nombre_dispositivo, procesado, created_by) 
                VALUES (:codigo_biometrico, :fecha_hora_marca, :tipo_marca, :nombre_dispositivo, 0, :created_by)';

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
        $sql = 'SELECT alb.id, alb.codigo_biometrico, alb.fecha_hora_marca, alb.tipo_marca, alb.nombre_dispositivo, 
                       alb.procesado, alb.created_at, alb.created_by, t.nombre_completo
                FROM asistencia_logs_biometrico alb
                LEFT JOIN terceros_empleados te ON alb.codigo_biometrico = te.codigo_biometrico
                LEFT JOIN terceros t ON te.id_tercero = t.id AND t.deleted_at IS NULL
                ORDER BY alb.fecha_hora_marca DESC, alb.id DESC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerLogsPendientes(): array
    {
        $sql = 'SELECT id, codigo_biometrico, fecha_hora_marca, tipo_marca
                FROM asistencia_logs_biometrico WHERE procesado = 0
                ORDER BY fecha_hora_marca ASC, id ASC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function mapearEmpleadoPorCodigoBiometrico(): array
    {
        $sql = 'SELECT te.codigo_biometrico, te.id_tercero, t.nombre_completo
                FROM terceros_empleados te
                INNER JOIN terceros t ON t.id = te.id_tercero
                WHERE te.codigo_biometrico IS NOT NULL AND te.codigo_biometrico <> "" AND t.deleted_at IS NULL';

        $rows = $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            if (empty($row['codigo_biometrico'])) continue;
            $map[$row['codigo_biometrico']] = [
                'id_tercero' => (int) $row['id_tercero'],
                'nombre_completo' => $row['nombre_completo'],
            ];
        }
        return $map;
    }

    public function marcarLogsProcesados(array $ids): void
    {
        if (empty($ids)) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE asistencia_logs_biometrico SET procesado = 1 WHERE id IN ($placeholders)";
        $this->db()->prepare($sql)->execute(array_values($ids));
    }


    // =========================================================================
    // 3. MOTOR MATEMÁTICO (NUEVO PARADIGMA DE BLOQUES)
    // =========================================================================
    
    public function calcularResumenDesdeMarcas(int $idTercero, string $fecha, array $marcas): array
    {
        $marcasNormalizadas = array_values(array_filter(array_map(static fn($m) => trim((string) $m), $marcas), static fn($m) => $m !== ''));
        sort($marcasNormalizadas);
        $marcasCount = count($marcasNormalizadas);

        // 1. Obtener las políticas con las dos tolerancias separadas
        $config = $this->obtenerConfiguracionRRHH();
        $bloqueMinutos = max(1, (int)($config['bloque_minutos'] ?? 30));
        $tolEntrada = (int)($config['tolerancia_entrada'] ?? 15);
        $tolSalida = (int)($config['tolerancia_salida'] ?? 15);
        $metaDiaria = (float)($config['meta_horas_diarias'] ?? 8.0);

        $ingresos = [];
        $salidas = [];
        foreach ($marcasNormalizadas as $index => $marca) {
            if (($index % 2) === 0) $ingresos[] = $marca;
            else $salidas[] = $marca;
        }

        $paresCompletos = (count($ingresos) === count($salidas));
        $horaIngreso = $marcasNormalizadas[0] ?? null;
        $horaSalida = ($marcasCount >= 2) ? $marcasNormalizadas[$marcasCount - 1] : null;

        $estado = 'INCOMPLETO';
        $horasTrabajadas = 0.00;
        $horasExtras = 0.00;

        if ($marcasCount > 0 && $paresCompletos) {
            $estado = 'PUNTUAL';
            $totalHorasEfectivas = 0;

            for ($k = 0; $k < count($ingresos); $k++) {
                $tsInReal = strpos($ingresos[$k], ' ') !== false ? strtotime($ingresos[$k]) : strtotime($fecha . ' ' . $ingresos[$k]);
                $tsOutReal = strpos($salidas[$k], ' ') !== false ? strtotime($salidas[$k]) : strtotime($fecha . ' ' . $salidas[$k]);

                if ($tsInReal === false || $tsOutReal === false) continue;
                if ($tsOutReal < $tsInReal) $tsOutReal = strtotime('+1 day', $tsOutReal);
                if ($tsOutReal <= $tsInReal) continue; 

                // Convertir reglas a segundos para el cálculo exacto
                $b = $bloqueMinutos * 60;
                $tIn = $tolEntrada * 60;
                $tOut = $tolSalida * 60;

                // ========================================================
                // REDONDEO INTELIGENTE DE ENTRADA (Tolerancia de Entrada)
                // ========================================================
                $prevIn = floor($tsInReal / $b) * $b;
                $nextIn = ceil($tsInReal / $b) * $b;
                $pasadoIn = $tsInReal - $prevIn;

                if ($pasadoIn <= $tIn) {
                    $tsInEfectivo = $prevIn; // Premio temprano o Perdona tardanza leve
                } else {
                    $tsInEfectivo = $nextIn; // Castiga, manda al siguiente bloque
                }

                // ========================================================
                // REDONDEO INTELIGENTE DE SALIDA (Tolerancia de Salida)
                // ========================================================
                $prevOut = floor($tsOutReal / $b) * $b;
                $nextOut = ceil($tsOutReal / $b) * $b;
                $faltaOut = $nextOut - $tsOutReal;

                if ($tsOutReal == $prevOut) {
                    $tsOutEfectivo = $tsOutReal; // Salió en el minuto exacto
                } elseif ($faltaOut <= $tOut) {
                    $tsOutEfectivo = $nextOut; // Premia tiempo extra o Perdona salida temprana leve
                } else {
                    $tsOutEfectivo = $prevOut; // Castiga cortando al bloque anterior
                }

                // Acumular las horas procesadas si son coherentes
                if ($tsOutEfectivo > $tsInEfectivo) {
                    $totalHorasEfectivas += ($tsOutEfectivo - $tsInEfectivo) / 3600;
                }
            }

            // Separar regulares de extras según la meta diaria
            if ($totalHorasEfectivas > $metaDiaria) {
                $horasTrabajadas = $metaDiaria;
                $horasExtras = round($totalHorasEfectivas - $metaDiaria, 2);
            } else {
                $horasTrabajadas = round($totalHorasEfectivas, 2);
            }
        } elseif ($marcasCount > 0 && !$paresCompletos) {
            $estado = 'INCOMPLETO';
        } else {
            $estado = 'FALTA';
        }

        return [
            'hora_ingreso' => $horaIngreso,
            'hora_salida' => $horaSalida,
            'hora_entrada_esperada' => null, 
            'hora_salida_esperada' => null,  
            'tolerancia_minutos' => $tolEntrada, // Referencial para logs
            'estado_asistencia' => $estado,
            'minutos_tardanza' => 0, 
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extras' => $horasExtras,
            'detalle_tardanza' => [],
        ];
    }

    public function upsertRegistroAsistencia(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_registros (
                    id_tercero, fecha, hora_ingreso, hora_salida,
                    marcas_ingresos, marcas_salidas,
                    hora_entrada_esperada, hora_salida_esperada,
                    tolerancia_minutos, estado_asistencia, minutos_tardanza,
                    horas_trabajadas, horas_extras, observaciones,
                    created_by, updated_by
                ) VALUES (
                    :id_tercero, :fecha, :hora_ingreso, :hora_salida,
                    :marcas_ingresos, :marcas_salidas,
                    NULL, NULL,
                    :tolerancia_minutos, :estado_asistencia, 0,
                    :horas_trabajadas, :horas_extras, :observaciones,
                    :created_by, :updated_by
                )
                ON DUPLICATE KEY UPDATE
                    hora_ingreso = VALUES(hora_ingreso),
                    hora_salida = VALUES(hora_salida),
                    marcas_ingresos = VALUES(marcas_ingresos),
                    marcas_salidas = VALUES(marcas_salidas),
                    tolerancia_minutos = VALUES(tolerancia_minutos),
                    estado_asistencia = VALUES(estado_asistencia),
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
            'marcas_ingresos' => $data['marcas_ingresos'] ?? null,
            'marcas_salidas' => $data['marcas_salidas'] ?? null,
            'tolerancia_minutos' => $data['tolerancia_minutos'] ?? 0,
            'estado_asistencia' => $data['estado_asistencia'],
            'horas_trabajadas' => $data['horas_trabajadas'],
            'horas_extras' => $data['horas_extras'],
            'observaciones' => $data['observaciones'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function existeRegistroAsistencia(int $idTercero, string $fecha): bool
    {
        $sql = 'SELECT id FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_tercero' => $idTercero, 'fecha' => $fecha]);
        return (bool) $stmt->fetch();
    }


    // =========================================================================
    // 4. GRID DE EXCEL (Gestión de Asistencia)
    // =========================================================================

    public function obtenerDatosParaGridExcel(int $idTercero, string $periodo, array $filtros): array
    {
        $desde = date('Y-m-d');
        $hasta = date('Y-m-d');
        $rangoLabel = '';

        if ($periodo === 'semana') {
            $semana = $filtros['semana'] ?? date('o-\WW');
            if (preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
                $fechaSemana = new DateTimeImmutable();
                $fechaSemana = $fechaSemana->setISODate((int) $m[1], (int) $m[2], 1);
                $desde = $fechaSemana->format('Y-m-d');
                $hasta = $fechaSemana->modify('+6 days')->format('Y-m-d');
                $rangoLabel = 'Semana ' . $m[2] . ', ' . $m[1];
            }
        } elseif ($periodo === 'mes') {
            $mes = $filtros['mes'] ?? date('Y-m');
            if (preg_match('/^(\d{4})-(\d{2})$/', $mes, $m)) {
                $inicioMes = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%s-%s-01', $m[1], $m[2]));
                if ($inicioMes instanceof DateTimeImmutable) {
                    $desde = $inicioMes->format('Y-m-d');
                    $hasta = $inicioMes->modify('last day of this month')->format('Y-m-d');
                    $rangoLabel = 'Mes: ' . $inicioMes->format('m/Y');
                }
            }
        } elseif ($periodo === 'rango') {
            $desde = $filtros['fecha_inicio'] ?? date('Y-m-d');
            $hasta = $filtros['fecha_fin'] ?? date('Y-m-d');
            if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
            $rangoLabel = 'Rango: ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta));
        }

        $sql = "SELECT * FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha BETWEEN :desde AND :hasta ORDER BY fecha ASC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_tercero' => $idTercero, 'desde' => $desde, 'hasta' => $hasta]);
        
        $registrosBD = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $registrosBD[$row['fecha']] = $row;
        }

        $dias = [];
        $fechaActual = strtotime($desde);
        $fechaFinTs = strtotime($hasta);
        $diasSemanaNombres = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        
        // NUEVO: Contadores separados para horas regulares y extras
        $totalRegulares = 0.0;
        $totalExtras = 0.0;

        while ($fechaActual <= $fechaFinTs) {
            $fechaStr = date('Y-m-d', $fechaActual);
            $diaW = (int) date('w', $fechaActual);
            
            $reg = $registrosBD[$fechaStr] ?? null;
            $ingresosArr = $reg && $reg['marcas_ingresos'] ? array_filter(explode('|', $reg['marcas_ingresos']), fn($h) => $h !== '') : [];
            $salidasArr = $reg && $reg['marcas_salidas'] ? array_filter(explode('|', $reg['marcas_salidas']), fn($h) => $h !== '') : [];
            
            if (!$reg) {
                if ($fechaStr < date('Y-m-d')) {
                    $estadoStr = 'FALTA';
                    $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle fw-bold';
                } else {
                    $estadoStr = 'Sin datos';
                    $badgeClass = 'bg-light text-muted border';
                }
            } else {
                $estadoStr = $reg['estado_asistencia'];
                $badgeClass = 'bg-secondary-subtle text-secondary';
                if ($estadoStr === 'PUNTUAL') $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                if ($estadoStr === 'FALTA') $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle fw-bold';
                if ($estadoStr === 'INCOMPLETO') $badgeClass = 'bg-secondary text-white border border-secondary shadow-sm';
                if (strpos($estadoStr, 'JUSTIFICADA') !== false || strpos($estadoStr, 'PERMISO') !== false || strpos($estadoStr, 'MEDICO') !== false) {
                    $badgeClass = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                }
            }

            // NUEVO: Acumular horas separadas y formatear el total del día
            $strTotalDia = '0h';
            if ($reg) {
                $horasTrabajadas = (float) ($reg['horas_trabajadas'] ?? 0);
                $horasExtrasDia = (float) ($reg['horas_extras'] ?? 0);
                
                $totalRegulares += $horasTrabajadas;
                $totalExtras += $horasExtrasDia;

                // Formato exacto del día para sobrescribir el cálculo del frontend
                $totalDiaDecimal = $horasTrabajadas + $horasExtrasDia;
                $diaH = floor($totalDiaDecimal);
                $diaM = round(($totalDiaDecimal - $diaH) * 60);
                if ($totalDiaDecimal > 0) {
                    $strTotalDia = $diaH . 'h' . ($diaM > 0 ? " {$diaM}m" : '');
                }
            }

            $dias[] = [
                'fecha' => $fechaStr,
                'nombre_dia' => $diasSemanaNombres[$diaW],
                'fecha_formateada' => date('d/m/Y', $fechaActual),
                't1_in' => isset($ingresosArr[0]) && $ingresosArr[0] !== 'null' ? substr($ingresosArr[0], 11, 5) : '',
                't1_out' => isset($salidasArr[0]) && $salidasArr[0] !== 'null' ? substr($salidasArr[0], 11, 5) : '',
                't2_in' => isset($ingresosArr[1]) && $ingresosArr[1] !== 'null' ? substr($ingresosArr[1], 11, 5) : '',
                't2_out' => isset($salidasArr[1]) && $salidasArr[1] !== 'null' ? substr($salidasArr[1], 11, 5) : '',
                't3_in' => isset($ingresosArr[2]) && $ingresosArr[2] !== 'null' ? substr($ingresosArr[2], 11, 5) : '',
                't3_out' => isset($salidasArr[2]) && $salidasArr[2] !== 'null' ? substr($salidasArr[2], 11, 5) : '',
                'estado_label' => $estadoStr,
                'badge_class' => $badgeClass,
                'es_descanso' => false,
                'total_dia_formateado' => $strTotalDia // <-- INYECTAMOS EL RESULTADO MATEMÁTICO AL JS
            ];
            
            $fechaActual = strtotime('+1 day', $fechaActual);
        }

        // NUEVO: Formatear totales separados a strings ("XXh YYm")
        $regH = floor($totalRegulares);
        $regM = round(($totalRegulares - $regH) * 60);
        $strTotalReg = $regH . 'h' . ($regM > 0 ? " {$regM}m" : '');

        $extH = floor($totalExtras);
        $extM = round(($totalExtras - $extH) * 60);
        $strTotalExt = $extH . 'h' . ($extM > 0 ? " {$extM}m" : '');

        // NUEVO: Retornar las llaves que el JS realmente está esperando
        return [
            'dias' => $dias,
            'rango_label' => $rangoLabel,
            'total_regulares_str' => $strTotalReg,
            'total_extras_str' => $strTotalExt,
            'empleado_sin_horario' => false
        ];
    }

    public function actualizarCeldaAsistencia(int $idTercero, string $fecha, string $campo, string $valor, int $userId): array
    {
        $sql = "SELECT * FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_tercero' => $idTercero, 'fecha' => $fecha]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        $ingresos = $registro && !empty($registro['marcas_ingresos']) ? explode('|', $registro['marcas_ingresos']) : ['', '', ''];
        $salidas = $registro && !empty($registro['marcas_salidas']) ? explode('|', $registro['marcas_salidas']) : ['', '', ''];

        $ingresos = array_pad($ingresos, 3, '');
        $salidas = array_pad($salidas, 3, '');

        $valorDb = $valor !== '' ? $fecha . ' ' . $valor . ':00' : '';
        
        switch ($campo) {
            case 't1_in': $ingresos[0] = $valorDb; break;
            case 't1_out': $salidas[0] = $valorDb; break;
            case 't2_in': $ingresos[1] = $valorDb; break;
            case 't2_out': $salidas[1] = $valorDb; break;
            case 't3_in': $ingresos[2] = $valorDb; break;
            case 't3_out': $salidas[2] = $valorDb; break;
        }

        $todasLasMarcas = [];
        for ($i = 0; $i < 3; $i++) {
            if ($ingresos[$i] !== '' && $ingresos[$i] !== 'null') $todasLasMarcas[] = $ingresos[$i];
            if ($salidas[$i] !== '' && $salidas[$i] !== 'null') $todasLasMarcas[] = $salidas[$i];
        }

        $resumenMotor = $this->calcularResumenDesdeMarcas($idTercero, $fecha, $todasLasMarcas);

        $obsAnterior = $registro['observaciones'] ?? '';
        if (strpos($obsAnterior, '[Modificado Excel]') === false && $valorDb !== '') {
            $obsAnterior .= empty($obsAnterior) ? '[Modificado Excel]' : ' | [Modificado Excel]';
        }

        $upsertData = [
            'id_tercero' => $idTercero,
            'fecha' => $fecha,
            'hora_ingreso' => $resumenMotor['hora_ingreso'],
            'hora_salida' => $resumenMotor['hora_salida'],
            'marcas_ingresos' => implode('|', $ingresos),
            'marcas_salidas' => implode('|', $salidas),
            'tolerancia_minutos' => $resumenMotor['tolerancia_minutos'],
            'estado_asistencia' => $resumenMotor['estado_asistencia'],
            'horas_trabajadas' => $resumenMotor['horas_trabajadas'],
            'horas_extras' => $resumenMotor['horas_extras'],
            'observaciones' => $obsAnterior,
        ];

        $this->upsertRegistroAsistencia($upsertData, $userId);

        $estadoStr = $resumenMotor['estado_asistencia'];
        $badgeClass = 'bg-secondary-subtle text-secondary';
        if ($estadoStr === 'PUNTUAL') $badgeClass = 'bg-success-subtle text-success';
        if ($estadoStr === 'FALTA') $badgeClass = 'bg-danger-subtle text-danger';
        if (strpos($estadoStr, 'JUSTIFICADA') !== false || strpos($estadoStr, 'PERMISO') !== false || strpos($estadoStr, 'MEDICO') !== false) {
            $badgeClass = 'bg-info-subtle text-info-emphasis';
        }

        // ========================================================
        // NUEVO: Calcular el total del día redondeado para el JS
        // ========================================================
        $totalDiaDecimal = (float)$resumenMotor['horas_trabajadas'] + (float)$resumenMotor['horas_extras'];
        $diaH = floor($totalDiaDecimal);
        $diaM = round(($totalDiaDecimal - $diaH) * 60);
        
        $strTotalDia = '0h';
        if ($totalDiaDecimal > 0) {
            $strTotalDia = $diaH . 'h' . ($diaM > 0 ? " {$diaM}m" : '');
        }

        return [
            'nuevo_estado_label' => $estadoStr,
            'badge_class' => $badgeClass,
            'total_dia_formateado' => $strTotalDia // <-- ESTO SOBRESCRIBIRÁ EL CÁLCULO DEL JS
        ];
    }

    public function forzarEstadoAsistencia(int $idTercero, string $fecha, string $estado, string $observacion, int $userId): bool
    {
        $sql = "SELECT * FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_tercero' => $idTercero, 'fecha' => $fecha]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        $obsNueva = '[Justificado]: ' . $observacion;

        if (!$registro) {
            $upsertData = [
                'id_tercero' => $idTercero,
                'fecha' => $fecha,
                'hora_ingreso' => null,
                'hora_salida' => null,
                'marcas_ingresos' => null,
                'marcas_salidas' => null,
                'tolerancia_minutos' => 0,
                'estado_asistencia' => $estado,
                'horas_trabajadas' => 0,
                'horas_extras' => 0,
                'observaciones' => $obsNueva,
            ];
            return $this->upsertRegistroAsistencia($upsertData, $userId);
        } else {
            $obsActual = $registro['observaciones'] ?? '';
            $obsGuardar = empty($obsActual) ? $obsNueva : $obsActual . ' | ' . $obsNueva;

            $horasExtras = $registro['horas_extras'];

            if ($estado === 'ASISTENCIA' || $estado === 'PUNTUAL') {
                $todasLasMarcas = [];
                $ingresos = !empty($registro['marcas_ingresos']) ? explode('|', $registro['marcas_ingresos']) : [];
                $salidas = !empty($registro['marcas_salidas']) ? explode('|', $registro['marcas_salidas']) : [];
                $maxT = max(count($ingresos), count($salidas));
                for ($i = 0; $i < $maxT; $i++) {
                    if (!empty($ingresos[$i]) && $ingresos[$i] !== 'null') $todasLasMarcas[] = $ingresos[$i];
                    if (!empty($salidas[$i]) && $salidas[$i] !== 'null') $todasLasMarcas[] = $salidas[$i];
                }
                $resumen = $this->calcularResumenDesdeMarcas($idTercero, $fecha, $todasLasMarcas);
                
                $estado = $resumen['estado_asistencia'];
                $horasExtras = $resumen['horas_extras'];
                $obsGuardar = $obsActual . ' | [Cálculo Restaurado]';
            } else {
                if (in_array($estado, ['FALTA JUSTIFICADA', 'VACACIONES', 'DESCANSO_MEDICO'])) {
                    $horasExtras = 0;
                }
            }

            $sqlUpd = "UPDATE asistencia_registros 
                       SET estado_asistencia = :estado,
                           horas_extras = :h_ext,
                           observaciones = :obs,
                           updated_by = :uid,
                           updated_at = NOW()
                       WHERE id = :id";
                       
            return $this->db()->prepare($sqlUpd)->execute([
                'estado' => $estado,
                'h_ext' => $horasExtras,
                'obs' => $obsGuardar,
                'uid' => $userId,
                'id' => $registro['id']
            ]);
        }
    }

    // =========================================================================
    // 5. EDICIÓN MANUAL Y DASHBOARD
    // =========================================================================

    public function guardarAsistenciaManual(array $data, int $userId): bool
    {
        $sqlCheck = 'SELECT id, observaciones FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1';
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute([
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha']
        ]);
        $registroExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $ingresosTramo = $data['horas_ingreso'] ?? [];
        $salidasTramo = $data['horas_salida'] ?? [];
        if (!is_array($ingresosTramo)) $ingresosTramo = [];
        if (!is_array($salidasTramo)) $salidasTramo = [];
        
        $ingresosTramo = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $ingresosTramo), static fn($h) => $h !== ''));
        $salidasTramo = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $salidasTramo), static fn($h) => $h !== ''));

        $todasLasMarcas = [];
        $maxTramos = max(count($ingresosTramo), count($salidasTramo));
        for ($i = 0; $i < $maxTramos; $i++) {
            if (isset($ingresosTramo[$i])) $todasLasMarcas[] = $data['fecha'] . ' ' . $ingresosTramo[$i] . ':00';
            if (isset($salidasTramo[$i])) $todasLasMarcas[] = $data['fecha'] . ' ' . $salidasTramo[$i] . ':00';
        }

        $resumenMotor = $this->calcularResumenDesdeMarcas((int)$data['id_tercero'], $data['fecha'], $todasLasMarcas);

        $obsActual = $registroExistente && !empty($registroExistente['observaciones'])
                     ? (string)$registroExistente['observaciones'] . ' | '
                     : '';
        $nuevaObs = $obsActual . '[Manual]: ' . ($data['observaciones'] ?? 'Registro manual');

        $marcasIngresosStr = !empty($ingresosTramo) ? implode('|', array_map(fn($h) => strpos($h, ' ') !== false ? $h : $data['fecha'] . ' ' . $h . ':00', $ingresosTramo)) : null;
        $marcasSalidasStr = !empty($salidasTramo) ? implode('|', array_map(fn($h) => strpos($h, ' ') !== false ? $h : $data['fecha'] . ' ' . $h . ':00', $salidasTramo)) : null;

        $upsertData = [
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha'],
            'hora_ingreso' => $resumenMotor['hora_ingreso'],
            'hora_salida' => $resumenMotor['hora_salida'],
            'marcas_ingresos' => $marcasIngresosStr,
            'marcas_salidas' => $marcasSalidasStr,
            'tolerancia_minutos' => $resumenMotor['tolerancia_minutos'],
            'estado_asistencia' => $resumenMotor['estado_asistencia'],
            'horas_trabajadas' => $resumenMotor['horas_trabajadas'],
            'horas_extras' => $resumenMotor['horas_extras'],
            'observaciones' => $nuevaObs,
        ];

        return $this->upsertRegistroAsistencia($upsertData, $userId);
    }

    public function obtenerDetalleMarcacionesDia(int $idTercero, string $fecha): array
    {
        $sqlRegistro = 'SELECT hora_ingreso, hora_salida FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha ORDER BY id ASC';
        $stmtRegistro = $this->db()->prepare($sqlRegistro);
        $stmtRegistro->execute(['id_tercero' => $idTercero, 'fecha' => $fecha]);
        $rowsRegistro = $stmtRegistro->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ingresos = [];
        $salidas = [];
        foreach ($rowsRegistro as $row) {
            if (!empty($row['hora_ingreso'])) $ingresos[] = substr((string) $row['hora_ingreso'], 11, 5);
            if (!empty($row['hora_salida'])) $salidas[] = substr((string) $row['hora_salida'], 11, 5);
        }

        return [
            'turno' => [ 'id' => 0, 'nombre' => 'Horario Dinámico', 'es_excepcion' => 0, 'tolerancia_minutos' => 0 ],
            'tramos_esperados' => [],
            'tramos_activos' => count($ingresos) > 0 ? count($ingresos) : 1,
            'ingresos_reales' => array_slice($ingresos, 0, 3),
            'salidas_reales' => array_slice($salidas, 0, 3),
        ];
    }

    public function gestionarExcepcionDiaria(array $data, int $userId): bool
    {
        $sqlCheck = "SELECT * FROM asistencia_registros WHERE id_tercero = :id_tercero AND fecha = :fecha LIMIT 1";
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute(['id_tercero' => $data['id_tercero'], 'fecha' => $data['fecha']]);
        $registroExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $ingresos = $_POST['horas_ingreso_real'] ?? $data['horas_ingreso_real'] ?? [];
        $salidas = $_POST['horas_salida_real'] ?? $data['horas_salida_real'] ?? [];
        
        if (!is_array($ingresos)) $ingresos = is_string($ingresos) ? explode(',', $ingresos) : [$ingresos];
        if (!is_array($salidas)) $salidas = is_string($salidas) ? explode(',', $salidas) : [$salidas];

        $ingresos = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $ingresos), static fn($h) => $h !== ''));
        $salidas = array_values(array_filter(array_map(static fn($h) => trim((string) $h), $salidas), static fn($h) => $h !== ''));

        $todasLasMarcas = [];
        $maxTramos = max(count($ingresos), count($salidas));
        for ($i = 0; $i < $maxTramos; $i++) {
            if (isset($ingresos[$i])) $todasLasMarcas[] = $data['fecha'] . ' ' . $ingresos[$i] . ':00';
            if (isset($salidas[$i])) $todasLasMarcas[] = $data['fecha'] . ' ' . $salidas[$i] . ':00';
        }

        $resumenMotor = $this->calcularResumenDesdeMarcas((int)$data['id_tercero'], $data['fecha'], $todasLasMarcas);

        $estadoFinal = $resumenMotor['estado_asistencia'];
        $horasTrabajadas = $resumenMotor['horas_trabajadas'];
        $horasExtras = $resumenMotor['horas_extras'];
        
        if (!empty($data['aplicar_justificacion']) || !empty($_POST['aplicar_justificacion'])) {
            $estadoFinal = $_POST['nuevo_estado'] ?? $data['nuevo_estado'] ?? 'JUSTIFICADA';
            if (in_array($estadoFinal, ['FALTA JUSTIFICADA', 'VACACIONES', 'DESCANSO MEDICO'])) {
                $horasExtras = 0;
            }
        }

        $obsActual = $registroExistente && !empty($registroExistente['observaciones']) ? $registroExistente['observaciones'] . ' | ' : '';
        $nuevaObs = $obsActual;
        $motivo = $_POST['observacion'] ?? $data['observacion'] ?? '';
        if ($motivo !== '') {
            $prefix = (!empty($_POST['aplicar_justificacion']) || !empty($data['aplicar_justificacion'])) ? '[Justificado]' : '[Editado Manual]';
            $nuevaObs .= $prefix . ' ' . $motivo;
        }

        $marcasIngresosStr = !empty($ingresos) ? implode('|', array_map(fn($h) => strpos($h, ' ') !== false ? $h : $data['fecha'] . ' ' . $h . ':00', $ingresos)) : null;
        $marcasSalidasStr = !empty($salidas) ? implode('|', array_map(fn($h) => strpos($h, ' ') !== false ? $h : $data['fecha'] . ' ' . $h . ':00', $salidas)) : null;

        $upsertData = [
            'id_tercero' => $data['id_tercero'],
            'fecha' => $data['fecha'],
            'hora_ingreso' => $resumenMotor['hora_ingreso'],
            'hora_salida' => $resumenMotor['hora_salida'],
            'marcas_ingresos' => $marcasIngresosStr,
            'marcas_salidas' => $marcasSalidasStr,
            'tolerancia_minutos' => $resumenMotor['tolerancia_minutos'],
            'estado_asistencia' => $estadoFinal,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extras' => $horasExtras,
            'observaciones' => $nuevaObs,
        ];

        return $this->upsertRegistroAsistencia($upsertData, $userId);
    }

    private function procesarFilasDashboard(array $rows, string $estadoFiltro = ''): array
    {
        $procesados = [];
        foreach ($rows as $row) {
            $estadoGeneral = $this->calcularEstadoGeneral($row['estados_asistencia'] ?? null);
            if ($estadoFiltro !== '' && $estadoGeneral !== $estadoFiltro) continue;

            $row['esperada_formateada'] = 'Dinámico';

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

            $row['estado_asistencia'] = $estadoGeneral;
            $row['id_tercero'] = (int) ($row['id_tercero'] ?? $row['id'] ?? 0);
            $row['minutos_tardanza'] = 0; 
            $row['hora_entrada'] = '';
            $row['hora_salida'] = '';

            $procesados[] = $row;
        }
        return $procesados;
    }

    private function calcularEstadoGeneral(?string $estadosStr): string
    {
        if (empty($estadosStr)) return 'FALTA';
        $estados = explode('|', $estadosStr);
        if (in_array('FALTA', $estados)) return 'FALTA';
        if (in_array('INCOMPLETO', $estados)) return 'INCOMPLETO';
        
        foreach($estados as $est) {
            if (strpos($est, 'JUSTIFICADA') !== false || strpos($est, 'PERMISO') !== false || strpos($est, 'OLVIDO') !== false) {
                return $est; 
            }
        }
        return 'PUNTUAL';
    }

    public function obtenerDashboardDiario(string $fecha, ?int $idTercero = null, string $estado = ''): array
    {
        $sql = 'SELECT t.id, t.id AS id_tercero, :fecha_dashboard AS fecha, t.nombre_completo,
                       COALESCE(ar.marcas_ingresos, ar.hora_ingreso) AS horas_ingreso,
                       COALESCE(ar.marcas_salidas, ar.hora_salida) AS horas_salida,
                       GROUP_CONCAT(ar.estado_asistencia SEPARATOR "|") AS estados_asistencia
                FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                LEFT JOIN asistencia_registros ar ON ar.id_tercero = t.id AND ar.fecha = :fecha_registro
                WHERE t.es_empleado = 1 AND t.deleted_at IS NULL';

        $params = ['fecha_dashboard' => $fecha, 'fecha_registro' => $fecha];

        if ($idTercero !== null && $idTercero > 0) {
            $sql .= ' AND t.id = :id_tercero';
            $params['id_tercero'] = $idTercero;
        }

        $sql .= ' GROUP BY t.id, t.nombre_completo ORDER BY t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $this->procesarFilasDashboard($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $estado);
    }

    public function obtenerDashboardRango(string $desde, string $hasta, ?int $idTercero = null, string $estado = ''): array
    {
        $sql = 'SELECT t.id, t.id AS id_tercero, ar.fecha, t.nombre_completo,
                       COALESCE(ar.marcas_ingresos, ar.hora_ingreso) AS horas_ingreso,
                       COALESCE(ar.marcas_salidas, ar.hora_salida) AS horas_salida,
                       GROUP_CONCAT(ar.estado_asistencia SEPARATOR "|") AS estados_asistencia
                FROM asistencia_registros ar
                INNER JOIN terceros t ON t.id = ar.id_tercero
                WHERE ar.fecha BETWEEN :desde AND :hasta AND t.es_empleado = 1 AND t.deleted_at IS NULL';

        $params = ['desde' => $desde, 'hasta' => $hasta];

        if ($idTercero !== null && $idTercero > 0) {
            $sql .= ' AND t.id = :id_tercero';
            $params['id_tercero'] = $idTercero;
        }

        $sql .= ' GROUP BY t.id, ar.fecha, t.nombre_completo ORDER BY ar.fecha DESC, t.nombre_completo ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $this->procesarFilasDashboard($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $estado);
    }

    public function listarEmpleadosParaIncidencias(): array
    {
        $sql = 'SELECT t.id, t.nombre_completo, te.codigo_biometrico FROM terceros t
                INNER JOIN terceros_empleados te ON te.id_tercero = t.id
                WHERE t.es_empleado = 1 AND t.deleted_at IS NULL ORDER BY t.nombre_completo ASC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarIncidencias(): array
    {
        $sql = 'SELECT ai.id, ai.id_tercero, t.nombre_completo AS empleado, ai.tipo_incidencia, ai.fecha_inicio, ai.fecha_fin, ai.con_goce_sueldo, ai.documento_respaldo, ai.estado, ai.created_at
                FROM asistencia_incidencias ai INNER JOIN terceros t ON t.id = ai.id_tercero
                WHERE ai.deleted_at IS NULL ORDER BY ai.fecha_inicio DESC, ai.id DESC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardarIncidencia(array $data, int $userId): bool
    {
        $sql = 'INSERT INTO asistencia_incidencias (id_tercero, tipo_incidencia, fecha_inicio, fecha_fin, con_goce_sueldo, documento_respaldo, estado, created_by, updated_by) 
                VALUES (:id_tercero, :tipo_incidencia, :fecha_inicio, :fecha_fin, :con_goce_sueldo, :documento_respaldo, 1, :created_by, :updated_by)';
        return $this->db()->prepare($sql)->execute([
            'id_tercero' => $data['id_tercero'], 'tipo_incidencia' => $data['tipo_incidencia'], 'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'], 'con_goce_sueldo' => $data['con_goce_sueldo'], 'documento_respaldo' => $data['documento_respaldo'],
            'created_by' => $userId, 'updated_by' => $userId,
        ]);
    }

    public function eliminarIncidencia(int $id, int $userId): bool
    {
        $sql = 'UPDATE asistencia_incidencias SET deleted_at = NOW(), deleted_by = :deleted_by, estado = 0, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute(['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]);
    }

    // =========================================================================
    // 6. FUNCIONES DUMMY (Para no romper el Controlador)
    // =========================================================================
    public function obtenerHorarioEsperado(int $idTercero, string $fecha): ?array { return ['dummy' => true]; }
    public function obtenerTurnoEfectivoPorFecha(int $idTercero, string $fecha): ?array { return ['dummy' => true]; }
    public function listarGruposExcepcion(): array { return []; }
    public function listarEmpleadosSinGrupo(): array { return []; }
    public function validarSolapamientoEmpleados(array $empleados_ids, string $fecha_inicio, string $fecha_fin): array { return []; }
    public function crearGrupoExcepcion(array $data, int $userId): bool { return false; }
    public function eliminarGrupoExcepcion(int $idGrupo): bool { return false; }
    public function listarEmpleadosDisponibles(string $fechaInicio, string $fechaFin): array { return []; }
    public function obtenerDetalleGrupo(int $idGrupo): ?array { return null; }
}