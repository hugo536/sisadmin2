<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/AsistenciaModel.php';

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

        $this->render('asistencia_importar', [
            'ruta_actual' => 'asistencia/importar',
            'logs' => $this->asistenciaModel->listarLogsBiometricos(),
            'flash' => [
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    public function dashboard(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        $fecha = (string) ($_GET['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $this->render('asistencia_dashboard', [
            'ruta_actual' => 'asistencia/dashboard',
            'fecha' => $fecha,
            'registros' => $this->asistenciaModel->obtenerDashboardDiario($fecha),
            'flash' => [
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
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

        $this->render('asistencia_incidencias', [
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
            redirect('asistencia/importar?tipo=error&msg=No se pudo identificar al usuario actual.');
        }

        if (!isset($_FILES['archivo_txt']) || !is_array($_FILES['archivo_txt'])) {
            redirect('asistencia/importar?tipo=error&msg=Debes seleccionar un archivo TXT.');
        }

        $archivo = $_FILES['archivo_txt'];
        if ((int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            redirect('asistencia/importar?tipo=error&msg=No fue posible subir el archivo.');
        }

        $nombreOriginal = (string) ($archivo['name'] ?? '');
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($extension !== 'txt') {
            redirect('asistencia/importar?tipo=error&msg=Formato inválido. Solo se aceptan archivos .txt');
        }

        $tmpPath = (string) ($archivo['tmp_name'] ?? '');
        $handle = @fopen($tmpPath, 'rb');
        if ($handle === false) {
            redirect('asistencia/importar?tipo=error&msg=No se pudo leer el archivo cargado.');
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
            redirect('asistencia/importar?tipo=error&msg=No se pudo identificar al usuario actual.');
        }

        $logs = $this->asistenciaModel->obtenerLogsPendientes();
        if (empty($logs)) {
            redirect('asistencia/importar?tipo=success&msg=No hay registros pendientes por procesar.');
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

                $diaSemana = (int) date('N', strtotime($fecha));
                $horario = $this->asistenciaModel->obtenerHorarioEsperado((int) $idTercero, $diaSemana);

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
            redirect('asistencia/incidencias?tipo=error&msg=Datos de incidencia inválidos.');
        }

        $rutaDocumento = null;
        if (!empty($_FILES['documento_respaldo']['name'])) {
            $file = $_FILES['documento_respaldo'];
            if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                redirect('asistencia/incidencias?tipo=error&msg=No se pudo subir el documento de respaldo.');
            }

            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                redirect('asistencia/incidencias?tipo=error&msg=Solo se permite PDF o imágenes (jpg, jpeg, png).');
            }

            $dirAbs = BASE_PATH . '/public/uploads/asistencia/incidencias/';
            if (!is_dir($dirAbs)) {
                mkdir($dirAbs, 0755, true);
            }

            $name = uniqid('inc_', true) . '.' . $ext;
            $destAbs = $dirAbs . $name;
            if (!move_uploaded_file((string) $file['tmp_name'], $destAbs)) {
                redirect('asistencia/incidencias?tipo=error&msg=No se pudo guardar el documento de respaldo.');
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
            redirect('asistencia/incidencias?tipo=error&msg=No fue posible guardar la incidencia.');
        }

        redirect('asistencia/incidencias?tipo=success&msg=Incidencia guardada correctamente.');
    }

    private function eliminarIncidencia(): void
    {
        require_permiso('terceros.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('asistencia/incidencias?tipo=error&msg=Incidencia inválida.');
        }

        if (!$this->asistenciaModel->eliminarIncidencia($id, $userId)) {
            redirect('asistencia/incidencias?tipo=error&msg=No se pudo eliminar la incidencia.');
        }

        redirect('asistencia/incidencias?tipo=success&msg=Incidencia eliminada correctamente.');
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
