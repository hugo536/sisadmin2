<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/rrhh/HorarioModel.php';

class HorarioController extends Controlador
{
    private HorarioModel $horarioModel;

    public function __construct()
    {
        parent::__construct();
        $this->horarioModel = new HorarioModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->procesarFormulario();
            return;
        }

        // 1. Obtenemos las asignaciones crudas de la BD (1 fila = 1 día)
        $asignacionesRaw = $this->horarioModel->listarAsignaciones();

        // 2. Lógica para agrupar por Empleado (Para la tabla visual)
        $empleadosAgrupados = [];
        $nombresDias = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
        ];

        foreach ($asignacionesRaw as $row) {
            $idEmp = (int)($row['id_tercero'] ?? 0);
            
            // Si el empleado aún no está en nuestra lista agrupada, lo creamos
            if (!isset($empleadosAgrupados[$idEmp])) {
                $empleadosAgrupados[$idEmp] = [
                    'nombre_completo'   => $row['empleado'] ?? $row['nombre_completo'] ?? 'Sin Nombre',
                    'codigo_biometrico' => $row['codigo_biometrico'] ?? '',
                    'dias_asignados'    => []
                ];
            }
            
            // Añadimos el día a la lista de este empleado
            $diaNumero = (int)($row['dia_semana'] ?? 0);
            if ($diaNumero > 0) {
                $empleadosAgrupados[$idEmp]['dias_asignados'][$diaNumero] = [
                    'id_asignacion' => $row['id'], 
                    'nombre_dia'    => $nombresDias[$diaNumero] ?? 'Día',
                    'nombre_horario'=> $row['horario'] ?? $row['horario_nombre'] ?? 'Turno', 
                    // Ajustamos el tooltip para mostrar el primer tramo como referencia
                    'hora_entrada'  => substr((string)($row['t1_entrada'] ?? ''), 0, 5),
                    'hora_salida'   => substr((string)($row['t1_salida'] ?? ''), 0, 5)
                ];
            }
        }

        // 3. Renderizamos la vista mandando la nueva variable agrupada
        $this->render('rrhh/horario', [
            'ruta_actual' => 'horario/index',
            'horarios' => $this->horarioModel->listarHorarios(),
            'empleados' => $this->horarioModel->listarEmpleados(),
            'asignaciones' => $asignacionesRaw, 
            'empleadosAgrupados' => $empleadosAgrupados,
            'flash' => [
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    private function procesarFormulario(): void
    {
        require_permiso('terceros.editar');

        $accion = (string) ($_POST['accion'] ?? '');
        $userId = (int) ($_SESSION['id'] ?? 0);

        try {
            if ($accion === 'guardar_horario') {
                $this->guardarHorario($userId);
                return;
            }

            if ($accion === 'cambiar_estado_horario') {
                $this->cambiarEstadoHorario($userId);
                return;
            }

            if ($accion === 'guardar_asignacion') {
                $this->guardarAsignacion($userId);
                return;
            }

            if ($accion === 'eliminar_asignacion') {
                $this->eliminarAsignacion();
                return;
            }

            if ($accion === 'limpiar_semana_empleado') {
                $this->limpiarSemanaEmpleado();
                return;
            }

            redirect('horario/index?tipo=error&msg=Acción no válida.');
        } catch (Throwable $e) {
            redirect('horario/index?tipo=error&msg=' . urlencode($e->getMessage()));
        }
    }

    // --- FUNCIÓN MATEMÁTICA AUXILIAR PARA CALCULAR HORAS ---
    private function calcularHorasTramo(string $entrada, string $salida): float
    {
        if (empty($entrada) || empty($salida)) return 0.0;
        
        $in = strtotime($entrada);
        $out = strtotime($salida);
        
        // Si la hora de salida es menor a la de entrada (ej: Entra 22:00, Sale 06:00)
        // Significa que cruzó la medianoche, sumamos 24 horas (86400 segundos) a la salida.
        if ($out < $in) {
            $out += 86400;
        }
        
        return round(($out - $in) / 3600, 2); // Devuelve las horas en decimales (Ej: 8.5)
    }

    private function guardarHorario(int $userId): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $tolerancia = (int) ($_POST['tolerancia_minutos'] ?? 0);

        // Recibimos los 6 posibles tramos
        $t1e = trim((string) ($_POST['t1_entrada'] ?? ''));
        $t1s = trim((string) ($_POST['t1_salida'] ?? ''));
        $t2e = trim((string) ($_POST['t2_entrada'] ?? ''));
        $t2s = trim((string) ($_POST['t2_salida'] ?? ''));
        $t3e = trim((string) ($_POST['t3_entrada'] ?? ''));
        $t3s = trim((string) ($_POST['t3_salida'] ?? ''));

        // Validaciones Básicas
        if ($nombre === '' || $t1e === '' || $t1s === '') {
            throw new RuntimeException('El nombre y el primer tramo (entrada y salida) son obligatorios.');
        }

        $timePattern = '/^\d{2}:\d{2}$/';
        if (!preg_match($timePattern, $t1e) || !preg_match($timePattern, $t1s)) {
            throw new RuntimeException('Las horas del primer tramo deben tener el formato HH:MM.');
        }

        // Calculamos el total de horas de la suma de los tramos que existan
        $totalHoras = $this->calcularHorasTramo($t1e, $t1s) + 
                      $this->calcularHorasTramo($t2e, $t2s) + 
                      $this->calcularHorasTramo($t3e, $t3s);

        // Preparamos el array de datos (añadiendo los segundos ':00' para MySQL)
        $payload = [
            'nombre'             => $nombre,
            't1_entrada'         => $t1e !== '' ? $t1e . ':00' : null,
            't1_salida'          => $t1s !== '' ? $t1s . ':00' : null,
            't2_entrada'         => $t2e !== '' ? $t2e . ':00' : null,
            't2_salida'          => $t2s !== '' ? $t2s . ':00' : null,
            't3_entrada'         => $t3e !== '' ? $t3e . ':00' : null,
            't3_salida'          => $t3s !== '' ? $t3s . ':00' : null,
            'total_horas_pago'   => $totalHoras,
            'tolerancia_minutos' => max(0, $tolerancia),
        ];

        if ($id > 0) {
            $ok = $this->horarioModel->actualizarHorario($id, $payload, $userId);
        } else {
            $ok = $this->horarioModel->crearHorario($payload, $userId);
        }

        if (!$ok) {
            throw new RuntimeException('No se pudo guardar el horario.');
        }

        redirect('horario/index?tipo=success&msg=Horario guardado correctamente.');
    }

    private function cambiarEstadoHorario(int $userId): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $estado = ((int) ($_POST['estado'] ?? 0) === 1) ? 1 : 0;

        if ($id <= 0) {
            throw new RuntimeException('Horario inválido.');
        }

        if (!$this->horarioModel->cambiarEstadoHorario($id, $estado, $userId)) {
            throw new RuntimeException('No se pudo cambiar el estado del horario.');
        }

        redirect('horario/index?tipo=success&msg=Estado de horario actualizado.');
    }

    private function guardarAsignacion(int $userId): void
    {
        $idHorario = (int) ($_POST['id_horario'] ?? 0);
        
        $idsTerceros = $_POST['id_terceros'] ?? [];
        $diasSeleccionados = $_POST['dias'] ?? [];

        if (!is_array($idsTerceros)) $idsTerceros = [];
        if (!is_array($diasSeleccionados)) $diasSeleccionados = [];

        $idsTerceros = array_values(array_unique(array_filter(array_map(
            static fn($id): int => (int) $id,
            $idsTerceros
        ), static fn(int $id): bool => $id > 0)));

        $diasSeleccionados = array_values(array_unique(array_filter(array_map(
            static fn($dia): int => (int) $dia,
            $diasSeleccionados
        ), static fn(int $dia): bool => $dia >= 1 && $dia <= 7)));

        if (empty($idsTerceros) || empty($diasSeleccionados) || $idHorario <= 0) {
            throw new RuntimeException('Datos inválidos. Asegúrese de seleccionar empleado(s), día(s) y un turno.');
        }

        foreach ($idsTerceros as $idTercero) {
            foreach ($diasSeleccionados as $dia) {
                if (!$this->horarioModel->guardarAsignacion($idTercero, $idHorario, $dia, $userId)) {
                    throw new RuntimeException('No se pudo guardar una o más asignaciones.');
                }
            }
        }

        $cantidadEmpleados = count($idsTerceros);
        $mensaje = $cantidadEmpleados > 1 
            ? "Asignaciones guardadas correctamente para {$cantidadEmpleados} empleados." 
            : "Asignación guardada correctamente.";

        redirect('horario/index?tipo=success&msg=' . urlencode($mensaje));
    }

    private function eliminarAsignacion(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Asignación inválida.');
        }

        if (!$this->horarioModel->eliminarAsignacion($id)) {
            throw new RuntimeException('No se pudo eliminar la asignación.');
        }

        redirect('horario/index?tipo=success&msg=Turno eliminado correctamente.');
    }

    private function limpiarSemanaEmpleado(): void
    {
        $idTercero = (int) ($_POST['id_tercero'] ?? 0);
        if ($idTercero <= 0) {
            throw new RuntimeException('Empleado inválido.');
        }

        if (!$this->horarioModel->limpiarSemanaEmpleado($idTercero)) {
            throw new RuntimeException('No se pudo limpiar la semana del empleado.');
        }

        redirect('horario/index?tipo=success&msg=Se eliminaron todos los turnos de la semana para este empleado.');
    }
}