<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/rrhh/AsistenciaModel.php';

class AsistenciaController extends Controlador
{
    private AsistenciaModel $asistenciaModel;

    public function __construct()
    {
        parent::__construct();
        $this->asistenciaModel = new AsistenciaModel();
    }

    public function index(): void
    {
        $this->importar();
    }

    public function importar(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? 'subir_txt');
            if ($accion === 'procesar_asistencia') {
                $this->procesarAsistenciaPendiente();
                return;
            }

            $this->subirLogBiometrico();
            return;
        }

        $this->render('rrhh/asistencia_importar', [
            'ruta_actual' => 'asistencia/importar',
            'logs'        => $this->asistenciaModel->listarLogsBiometricos(),
            'flash'       => [
                'tipo'  => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    public function dashboard(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        // --- PROCESAR GESTIÓN DE EXCEPCIONES Y GRUPOS ---
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            
            if ($accion === 'gestionar_excepcion') {
                $this->gestionarExcepcion();
                return;
            }
            
            // ¡NUEVO! Capturar creación de grupo
            if ($accion === 'crear_grupo') {
                $this->guardarGrupoExcepcion();
                return;
            }
            
            // ¡NUEVO! Capturar eliminación de grupo
            if ($accion === 'eliminar_grupo') {
                $this->eliminarGrupoExcepcion();
                return;
            }

            // ¡NUEVO! Capturar petición AJAX para buscar empleados libres
            if ($accion === 'buscar_disponibles') {
                $f_ini = trim((string) ($_POST['f_ini'] ?? ''));
                $f_fin = trim((string) ($_POST['f_fin'] ?? ''));
                if ($f_fin === '') $f_fin = $f_ini;

                if (method_exists($this->asistenciaModel, 'listarEmpleadosDisponibles')) {
                    $empleados = $this->asistenciaModel->listarEmpleadosDisponibles($f_ini, $f_fin);
                } else {
                    $empleados = [];
                }
                
                header('Content-Type: application/json');
                echo json_encode($empleados);
                exit; // Detenemos la ejecución porque es una respuesta AJAX
            }

            // ¡NUEVO! Capturar petición AJAX para clonar plantilla de grupo
            if ($accion === 'obtener_detalle_grupo') {
                $idG = (int) ($_POST['id_grupo'] ?? 0);
                $detalle = method_exists($this->asistenciaModel, 'obtenerDetalleGrupo') 
                            ? $this->asistenciaModel->obtenerDetalleGrupo($idG) 
                            : null;
                header('Content-Type: application/json');
                echo json_encode($detalle);
                exit; 
            }
        }
        // ----------------------------------------------------------------------

        $periodo = (string) ($_GET['periodo'] ?? 'dia');
        $periodosPermitidos = ['dia', 'semana', 'mes', 'rango'];
        if (!in_array($periodo, $periodosPermitidos, true)) {
            $periodo = 'dia';
        }

        $fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $semana = (string) ($_GET['semana'] ?? date('o-\WW'));
        if (!preg_match('/^\d{4}-W\d{2}$/', $semana)) {
            $semana = date('o-\WW');
        }

        $mes = (string) ($_GET['mes'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }

        $fechaInicio = (string) ($_GET['fecha_inicio'] ?? $fecha);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
            $fechaInicio = $fecha;
        }

        $fechaFin = (string) ($_GET['fecha_fin'] ?? $fecha);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
            $fechaFin = $fecha;
        }

        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $idTercero = (int) ($_GET['id_tercero'] ?? 0);
        if ($idTercero <= 0) {
            $idTercero = null;
        }

        $estado = strtoupper(trim((string) ($_GET['estado'] ?? '')));
        $estadosPermitidos = ['', 'PUNTUAL', 'TARDANZA', 'FALTA', 'INCOMPLETO', 'JUSTIFICADA'];
        if (!in_array($estado, $estadosPermitidos, true)) {
            $estado = '';
        }

        $desde = $fecha;
        $hasta = $fecha;

        if ($periodo === 'semana' && preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            $fechaSemana = new DateTimeImmutable();
            $fechaSemana = $fechaSemana->setISODate((int) $m[1], (int) $m[2], 1);
            $desde = $fechaSemana->format('Y-m-d');
            $hasta = $fechaSemana->modify('+6 days')->format('Y-m-d');
        } elseif ($periodo === 'mes' && preg_match('/^(\d{4})-(\d{2})$/', $mes, $m)) {
            $inicioMes = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%s-%s-01', $m[1], $m[2]));
            if ($inicioMes instanceof DateTimeImmutable) {
                $desde = $inicioMes->format('Y-m-d');
                $hasta = $inicioMes->modify('last day of this month')->format('Y-m-d');
            }
        } elseif ($periodo === 'rango') {
            $desde = $fechaInicio;
            $hasta = $fechaFin;
        }

        $registros = $periodo === 'dia'
            ? $this->asistenciaModel->obtenerDashboardDiario($fecha, $idTercero, $estado)
            : $this->asistenciaModel->obtenerDashboardRango($desde, $hasta, $idTercero, $estado);

        $grupos = (method_exists($this->asistenciaModel, 'listarGruposExcepcion')) 
            ? $this->asistenciaModel->listarGruposExcepcion() 
            : [];
            
        $empleadosSinGrupo = (method_exists($this->asistenciaModel, 'listarEmpleadosSinGrupo'))
            ? $this->asistenciaModel->listarEmpleadosSinGrupo()
            : $this->asistenciaModel->listarEmpleadosParaIncidencias();

        $this->render('rrhh/asistencia_dashboard', [
            'ruta_actual' => 'asistencia/dashboard',
            'periodo' => $periodo,
            'fecha' => $fecha,
            'semana' => $semana,
            'mes' => $mes,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'id_tercero' => $idTercero,
            'estado' => $estado,
            'desde' => $desde,
            'hasta' => $hasta,
            'registros' => $registros,
            'empleados' => $this->asistenciaModel->listarEmpleadosParaIncidencias(),
            'grupos' => $grupos,
            'empleadosSinGrupo' => $empleadosSinGrupo,
            'flash' => [
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    // --- NUEVAS FUNCIONES PARA GESTIONAR GRUPOS ---
    private function guardarGrupoExcepcion(): void
    {
        require_permiso('terceros.editar');

        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Sesión inválida.'));
            return;
        }

        $data = [
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'color' => trim((string) ($_POST['color'] ?? '#eee')),
            'fecha_inicio' => trim((string) ($_POST['fecha_inicio'] ?? '')),
            'fecha_fin' => trim((string) ($_POST['fecha_fin'] ?? '')),
            'empleados' => $_POST['empleados'] ?? [], 
            't1_entrada' => trim((string) ($_POST['t1_entrada'] ?? '')),
            't1_salida' => trim((string) ($_POST['t1_salida'] ?? '')),
            't2_entrada' => trim((string) ($_POST['t2_entrada'] ?? '')),
            't2_salida' => trim((string) ($_POST['t2_salida'] ?? '')),
            't3_entrada' => trim((string) ($_POST['t3_entrada'] ?? '')),
            't3_salida' => trim((string) ($_POST['t3_salida'] ?? '')),
            'tolerancia_minutos' => (int) ($_POST['tolerancia_minutos'] ?? 0), 
        ];

        // Validaciones básicas de campos vacíos
        if ($data['nombre'] === '' || empty($data['empleados']) || $data['fecha_inicio'] === '') {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Datos incompletos para crear el grupo.'));
            return;
        }

        // Validación de fecha (CORREGIDA: Ahora está separada y se ejecutará siempre)
        $hoy = date('Y-m-d');
        if ($data['fecha_inicio'] < $hoy) {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Error: No se pueden crear o modificar grupos en fechas pasadas.'));
            return;
        }

        // Si no mandaron fecha fin (porque el switch estaba en 1 día), la igualamos a inicio
        if ($data['fecha_fin'] === '') {
            $data['fecha_fin'] = $data['fecha_inicio'];
        }
        
        // --- 1. VALIDACIÓN DE SOLAPAMIENTO DE FECHAS Y EMPLEADOS ---
        if (method_exists($this->asistenciaModel, 'validarSolapamientoEmpleados')) {
            $conflictos = $this->asistenciaModel->validarSolapamientoEmpleados(
                $data['empleados'], 
                $data['fecha_inicio'], 
                $data['fecha_fin']
            );

            if (!empty($conflictos)) {
                // Construimos un mensaje amigable indicando quiénes chocan
                $nombresConflicto = array_map(function($c) {
                    return $c['nombre_completo'] . ' (en: ' . $c['nombre_grupo'] . ')';
                }, $conflictos);
                
                $msgError = "Conflicto: Los siguientes empleados ya tienen una excepción en esas fechas: " . implode(', ', $nombresConflicto);
                redirect('asistencia/dashboard?tipo=error&msg=' . urlencode($msgError));
                return;
            }
        }
        // -----------------------------------------------------------

        // 2. CREACIÓN DEL GRUPO EN LA BASE DE DATOS
        if (method_exists($this->asistenciaModel, 'crearGrupoExcepcion')) {
            $ok = $this->asistenciaModel->crearGrupoExcepcion($data, $userId);
            if ($ok) {
                redirect('asistencia/dashboard?tipo=success&msg=' . urlencode('Grupo y excepciones creados correctamente.'));
            } else {
                redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Hubo un error al guardar el grupo en BD.'));
            }
        } else {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Método crearGrupoExcepcion no existe en el Modelo.'));
        }
    }

    private function eliminarGrupoExcepcion(): void
    {
        require_permiso('terceros.editar');
        
        $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
        
        if ($idGrupo <= 0) {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('ID de grupo inválido.'));
            return;
        }

        if (method_exists($this->asistenciaModel, 'eliminarGrupoExcepcion')) {
            $ok = $this->asistenciaModel->eliminarGrupoExcepcion($idGrupo);
            if ($ok) {
                redirect('asistencia/dashboard?tipo=success&msg=' . urlencode('Grupo eliminado correctamente.'));
            } else {
                redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('No se pudo eliminar el grupo.'));
            }
        } else {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Método eliminarGrupoExcepcion no existe en el Modelo.'));
        }
    }
    // ---------------------------------------------

    private function gestionarExcepcion(): void
    {
        require_permiso('terceros.editar');

        $userId = (int) ($_SESSION['id'] ?? 0);
        $fecha = (string) ($_POST['fecha'] ?? '');

        if ($userId <= 0) {
            redirect("asistencia/dashboard?fecha={$fecha}&tipo=error&msg=" . urlencode('No se pudo identificar al usuario actual.'));
            return;
        }

        $data = [
            'id_tercero' => (int) ($_POST['id_tercero'] ?? 0),
            'fecha' => $fecha,
            'hora_entrada_esperada' => trim((string) ($_POST['hora_entrada_esperada'] ?? '')),
            'hora_salida_esperada' => trim((string) ($_POST['hora_salida_esperada'] ?? '')),
            'aplicar_justificacion' => (int) ($_POST['aplicar_justificacion'] ?? 0),
            'nuevo_estado' => trim((string) ($_POST['nuevo_estado'] ?? '')),
            'observacion' => trim((string) ($_POST['observacion'] ?? ''))
        ];

        if ($data['id_tercero'] <= 0 || $data['fecha'] === '' || $data['hora_entrada_esperada'] === '' || $data['hora_salida_esperada'] === '') {
            redirect("asistencia/dashboard?fecha={$fecha}&tipo=error&msg=" . urlencode('Datos incompletos. Verifica las horas ingresadas.'));
            return;
        }

        if ($data['aplicar_justificacion'] === 1 && $data['observacion'] === '') {
            redirect("asistencia/dashboard?fecha={$fecha}&tipo=error&msg=" . urlencode('Debes ingresar un motivo para la justificación.'));
            return;
        }

        $ok = $this->asistenciaModel->gestionarExcepcionDiaria($data, $userId);

        if (!$ok) {
            redirect("asistencia/dashboard?fecha={$fecha}&tipo=error&msg=" . urlencode('No fue posible guardar la excepción.'));
            return;
        }

        redirect("asistencia/dashboard?fecha={$fecha}&tipo=success&msg=" . urlencode('Excepción gestionada correctamente.'));
    }

    public function incidencias(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? 'guardar');
            if ($accion === 'eliminar') {
                $this->eliminarIncidencia();
                return;
            }

            $this->guardarIncidencia();
            return;
        }

        $this->render('rrhh/asistencia_incidencias', [
            'ruta_actual' => 'asistencia/incidencias',
            'empleados' => $this->asistenciaModel->listarEmpleadosParaIncidencias(),
            'incidencias' => $this->asistenciaModel->listarIncidencias(),
            'flash' => [
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    private function subirLogBiometrico(): void
    {
        require_permiso('terceros.editar');

        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            redirect('asistencia/importar?tipo=error&msg=' . urlencode('No se pudo identificar al usuario actual.'));
            return;
        }

        if (!isset($_FILES['archivo_txt']) || !is_array($_FILES['archivo_txt'])) {
            redirect('asistencia/importar?tipo=error&msg=' . urlencode('Debes seleccionar un archivo TXT.'));
            return;
        }

        $archivo = $_FILES['archivo_txt'];
        if ((int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            redirect('asistencia/importar?tipo=error&msg=' . urlencode('No fue posible subir el archivo.'));
            return;
        }

        $nombreOriginal = (string) ($archivo['name'] ?? '');
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($extension !== 'txt') {
            redirect('asistencia/importar?tipo=error&msg=' . urlencode('Formato inválido. Solo se aceptan archivos .txt'));
            return;
        }

        $tmpPath = (string) ($archivo['tmp_name'] ?? '');
        $handle = @fopen($tmpPath, 'rb');
        if ($handle === false) {
            redirect('asistencia/importar?tipo=error&msg=' . urlencode('No se pudo leer el archivo cargado.'));
            return;
        }

        $insertados = 0;
        $lineaNro = 0;

        try {
            while (($linea = fgets($handle)) !== false) {
                $lineaNro++;
                $linea = trim($linea);

                if ($linea === '') {
                    continue;
                }

                if ($lineaNro === 1) {
                    continue;
                }

                $columnas = explode("\t", $linea);
                if (count($columnas) < 7) {
                    continue;
                }

                $codigoBiometrico = trim((string) ($columnas[2] ?? ''));
                $tipoMarca = trim((string) ($columnas[5] ?? ''));
                $fechaHoraRaw = trim((string) ($columnas[6] ?? ''));
                $nombreDispositivo = trim((string) ($columnas[1] ?? ''));

                if ($codigoBiometrico === '' || $fechaHoraRaw === '') {
                    continue;
                }

                $fechaHora = $this->normalizarFechaHora($fechaHoraRaw);
                if ($fechaHora === null) {
                    continue;
                }

                $ok = $this->asistenciaModel->guardarLogBiometrico([
                    'codigo_biometrico' => $codigoBiometrico,
                    'fecha_hora_marca' => $fechaHora,
                    'tipo_marca' => $tipoMarca !== '' ? $tipoMarca : null,
                    'nombre_dispositivo' => $nombreDispositivo !== '' ? $nombreDispositivo : null,
                ], $userId);

                if ($ok) {
                    $insertados++;
                }
            }
        } finally {
            fclose($handle);
        }

        redirect('asistencia/importar?tipo=success&msg=' . urlencode("Archivo procesado. Registros insertados: {$insertados}."));
    }

    private function procesarAsistenciaPendiente(): void
    {
        require_permiso('terceros.editar');

        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            redirect('asistencia/importar?tipo=error&msg=' . urlencode('No se pudo identificar al usuario actual.'));
            return;
        }

        $logs = $this->asistenciaModel->obtenerLogsPendientes();
        if (empty($logs)) {
            redirect('asistencia/importar?tipo=success&msg=' . urlencode('No hay registros pendientes por procesar.'));
            return;
        }

        $mapaEmpleados = $this->asistenciaModel->mapearEmpleadoPorCodigoBiometrico();
        $grupos = [];

        foreach ($logs as $log) {
            $codigo = (string) ($log['codigo_biometrico'] ?? '');
            if (!isset($mapaEmpleados[$codigo])) {
                continue;
            }

            $idTercero = (int) $mapaEmpleados[$codigo]['id_tercero'];
            $fechaHora = (string) ($log['fecha_hora_marca'] ?? '');
            $fecha = substr($fechaHora, 0, 10);

            if (!isset($grupos[$idTercero])) {
                $grupos[$idTercero] = [];
            }
            if (!isset($grupos[$idTercero][$fecha])) {
                $grupos[$idTercero][$fecha] = [
                    'marcas' => [],
                    'logs_ids' => [],
                ];
            }

            $grupos[$idTercero][$fecha]['marcas'][] = $fechaHora;
            $grupos[$idTercero][$fecha]['logs_ids'][] = (int) ($log['id'] ?? 0);
        }

        $procesados = 0;
        $logsProcesados = [];

        foreach ($grupos as $idTercero => $fechas) {
            foreach ($fechas as $fecha => $info) {
                $marcas = $info['marcas'];
                sort($marcas);

                $horaIngreso = $marcas[0] ?? null;
                $horaSalida = count($marcas) > 1 ? $marcas[count($marcas) - 1] : null;

                // ACTUALIZACIÓN DE LLAMADA A HORARIO ESPERADO
                // Le pasamos la fecha exacta en lugar del diaSemana
                $horario = $this->asistenciaModel->obtenerHorarioEsperado((int) $idTercero, $fecha);

                $minutosTardanza = 0;
                $estado = 'INCOMPLETO';
                $horasTrabajadas = 0.00;
                $horasExtras = 0.00;
                $observaciones = null;

                if ($horaIngreso !== null && $horaSalida !== null) {
                    $estado = 'PUNTUAL';

                    $ingresoTs = strtotime($horaIngreso);
                    $salidaTs = strtotime($horaSalida);

                    if ($salidaTs > $ingresoTs) {
                        $horasTrabajadas = round(($salidaTs - $ingresoTs) / 3600, 2);
                    }

                    if ($horario) {
                        $esperadaTs = strtotime($fecha . ' ' . substr((string) $horario['hora_entrada'], 0, 8));
                        $salidaEsperadaTs = strtotime($fecha . ' ' . substr((string) $horario['hora_salida'], 0, 8));
                        $tolerancia = (int) ($horario['tolerancia_minutos'] ?? 0);

                        if ($ingresoTs > ($esperadaTs + ($tolerancia * 60))) {
                            $estado = 'TARDANZA';
                            $minutosTardanza = (int) floor(($ingresoTs - $esperadaTs) / 60);
                        }

                        if ($salidaEsperadaTs > $esperadaTs) {
                            $horasEsperadas = ($salidaEsperadaTs - $esperadaTs) / 3600;
                            if ($horasTrabajadas > $horasEsperadas) {
                                $horasExtras = round($horasTrabajadas - $horasEsperadas, 2);
                            }
                        }
                    }
                } elseif ($horaIngreso !== null && $horaSalida === null) {
                    $estado = 'INCOMPLETO';
                    $observaciones = 'Solo se encontró una marca para el día.';
                } else {
                    $estado = 'FALTA';
                    $observaciones = 'No se encontraron marcas válidas.';
                }

                $ok = $this->asistenciaModel->upsertRegistroAsistencia([
                    'id_tercero' => (int) $idTercero,
                    'fecha' => $fecha,
                    'hora_ingreso' => $horaIngreso,
                    'hora_salida' => $horaSalida,
                    'estado_asistencia' => $estado,
                    'minutos_tardanza' => $minutosTardanza,
                    'horas_trabajadas' => $horasTrabajadas,
                    'horas_extras' => $horasExtras,
                    'observaciones' => $observaciones,
                ], $userId);

                if ($ok) {
                    $procesados++;
                    $logsProcesados = array_merge($logsProcesados, $info['logs_ids']);
                }
            }
        }

        $logsProcesados = array_values(array_unique(array_filter($logsProcesados)));
        $this->asistenciaModel->marcarLogsProcesados($logsProcesados);

        redirect('asistencia/importar?tipo=success&msg=' . urlencode("Asistencias procesadas: {$procesados}. Logs marcados: " . count($logsProcesados) . '.'));
    }

    private function guardarIncidencia(): void
    {
        require_permiso('terceros.editar');

        $userId = (int) ($_SESSION['id'] ?? 0);
        $idTercero = (int) ($_POST['id_tercero'] ?? 0);
        $tipoIncidencia = trim((string) ($_POST['tipo_incidencia'] ?? ''));
        $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($_POST['fecha_fin'] ?? ''));
        $conGoce = ((int) ($_POST['con_goce_sueldo'] ?? 1) === 1) ? 1 : 0;

        $tiposValidos = ['VACACIONES', 'DESCANSO_MEDICO', 'PERMISO_PERSONAL', 'SUBSIDIO'];

        if ($idTercero <= 0 || !in_array($tipoIncidencia, $tiposValidos, true) || $fechaInicio === '' || $fechaFin === '') {
            redirect('asistencia/incidencias?tipo=error&msg=' . urlencode('Datos de incidencia inválidos.'));
            return;
        }

        $rutaDocumento = null;
        if (!empty($_FILES['documento_respaldo']['name'])) {
            $file = $_FILES['documento_respaldo'];
            if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                redirect('asistencia/incidencias?tipo=error&msg=' . urlencode('No se pudo subir el documento de respaldo.'));
                return;
            }

            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                redirect('asistencia/incidencias?tipo=error&msg=' . urlencode('Solo se permite PDF o imágenes (jpg, jpeg, png).'));
                return;
            }

            $dirAbs = BASE_PATH . '/public/uploads/asistencia/incidencias/';
            if (!is_dir($dirAbs)) {
                mkdir($dirAbs, 0755, true);
            }

            $name = uniqid('inc_', true) . '.' . $ext;
            $destAbs = $dirAbs . $name;
            if (!move_uploaded_file((string) $file['tmp_name'], $destAbs)) {
                redirect('asistencia/incidencias?tipo=error&msg=' . urlencode('No se pudo guardar el documento de respaldo.'));
                return;
            }

            $rutaDocumento = 'uploads/asistencia/incidencias/' . $name;
        }

        $ok = $this->asistenciaModel->guardarIncidencia([
            'id_tercero' => $idTercero,
            'tipo_incidencia' => $tipoIncidencia,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'con_goce_sueldo' => $conGoce,
            'documento_respaldo' => $rutaDocumento,
        ], $userId);

        if (!$ok) {
            redirect('asistencia/incidencias?tipo=error&msg=' . urlencode('No fue posible guardar la incidencia.'));
            return;
        }

        redirect('asistencia/incidencias?tipo=success&msg=' . urlencode('Incidencia guardada correctamente.'));
    }

    public function guardar_manual(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.editar');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('asistencia/dashboard');
            return;
        }

        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId <= 0) {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('No se pudo identificar al usuario actual.'));
            return;
        }

        $idTercero = (int) ($_POST['id_tercero'] ?? 0);
        $fecha = trim((string) ($_POST['fecha'] ?? ''));
        $horaIngresoRaw = trim((string) ($_POST['hora_ingreso'] ?? ''));
        $horaSalidaRaw = trim((string) ($_POST['hora_salida'] ?? ''));
        $observaciones = trim((string) ($_POST['observaciones'] ?? ''));

        if ($idTercero <= 0 || $fecha === '' || $observaciones === '') {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Faltan datos obligatorios (Empleado, fecha u observación).'));
            return;
        }

        if ($horaIngresoRaw === '' && $horaSalidaRaw === '') {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Debe registrar al menos una hora (ingreso o salida).'));
            return;
        }

        $horaIngreso = $horaIngresoRaw !== '' ? $fecha . ' ' . $horaIngresoRaw . ':00' : null;
        $horaSalida = $horaSalidaRaw !== '' ? $fecha . ' ' . $horaSalidaRaw . ':00' : null;

        $data = [
            'id_tercero'    => $idTercero,
            'fecha'         => $fecha,
            'hora_ingreso'  => $horaIngreso,
            'hora_salida'   => $horaSalida,
            'observaciones' => $observaciones
        ];

        try {
            $ok = $this->asistenciaModel->guardarAsistenciaManual($data, $userId);
            
            if ($ok) {
                redirect('asistencia/dashboard?tipo=success&msg=' . urlencode('Registro manual guardado correctamente.'));
            } else {
                redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('No se pudo guardar el registro manual.'));
            }
        } catch (Throwable $e) {
            redirect('asistencia/dashboard?tipo=error&msg=' . urlencode('Error al procesar: ' . $e->getMessage()));
        }
    }

    private function eliminarIncidencia(): void
    {
        require_permiso('terceros.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('asistencia/incidencias?tipo=error&msg=' . urlencode('Incidencia inválida.'));
            return;
        }

        if (!$this->asistenciaModel->eliminarIncidencia($id, $userId)) {
            redirect('asistencia/incidencias?tipo=error&msg=' . urlencode('No se pudo eliminar la incidencia.'));
            return;
        }

        redirect('asistencia/incidencias?tipo=success&msg=' . urlencode('Incidencia eliminada correctamente.'));
    }

    private function normalizarFechaHora(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formatos = ['Y-m-d H:i:s', 'Y/m/d H:i:s', 'd/m/Y H:i:s', 'Y-m-d H:i', 'Y/m/d H:i', 'd/m/Y H:i'];
        foreach ($formatos as $formato) {
            $dt = DateTime::createFromFormat($formato, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}