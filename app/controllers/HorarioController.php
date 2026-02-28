<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/HorarioModel.php';

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

        // 2. Lógica para agrupar por Empleado
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
                    'hora_entrada'  => substr((string)($row['hora_entrada'] ?? ''), 0, 5),
                    'hora_salida'   => substr((string)($row['hora_salida'] ?? ''), 0, 5)
                ];
            }
        }

        // 3. Renderizamos la vista mandando la nueva variable agrupada
        $this->render('horario', [
            'ruta_actual' => 'horario/index',
            'horarios' => $this->horarioModel->listarHorarios(),
            'empleados' => $this->horarioModel->listarEmpleados(),
            'asignaciones' => $asignacionesRaw, // Mantenemos la original por retrocompatibilidad
            'empleadosAgrupados' => $empleadosAgrupados, // Enviamos nuestra lista agrupada a la vista
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

            redirect('horario/index?tipo=error&msg=Acción no válida.');
        } catch (Throwable $e) {
            redirect('horario/index?tipo=error&msg=' . urlencode($e->getMessage()));
        }
    }

    private function guardarHorario(int $userId): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $horaEntrada = trim((string) ($_POST['hora_entrada'] ?? ''));
        $horaSalida = trim((string) ($_POST['hora_salida'] ?? ''));
        $tolerancia = (int) ($_POST['tolerancia_minutos'] ?? 0);

        if ($nombre === '' || $horaEntrada === '' || $horaSalida === '') {
            throw new RuntimeException('Nombre, hora de entrada y salida son obligatorios.');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $horaEntrada) || !preg_match('/^\d{2}:\d{2}$/', $horaSalida)) {
            throw new RuntimeException('Las horas deben tener el formato HH:MM.');
        }

        $payload = [
            'nombre' => $nombre,
            'hora_entrada' => $horaEntrada . ':00',
            'hora_salida' => $horaSalida . ':00',
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
        $diaSemana = (int) ($_POST['dia_semana'] ?? 0);
        $idsTerceros = $_POST['id_terceros'] ?? [];

        if (!is_array($idsTerceros)) {
            $idsTerceros = [];
        }

        // Retrocompatibilidad por si llega el campo antiguo.
        $idTerceroLegacy = (int) ($_POST['id_tercero'] ?? 0);
        if ($idTerceroLegacy > 0) {
            $idsTerceros[] = $idTerceroLegacy;
        }

        $idsTerceros = array_values(array_unique(array_filter(array_map(
            static fn($id): int => (int) $id,
            $idsTerceros
        ), static fn(int $id): bool => $id > 0)));

        if (empty($idsTerceros) || $idHorario <= 0 || $diaSemana < 0 || $diaSemana > 7) {
            throw new RuntimeException('Datos inválidos para la asignación.');
        }

        $diasObjetivo = $diaSemana === 0 ? [1, 2, 3, 4, 5, 6, 7] : [$diaSemana];

        foreach ($idsTerceros as $idTercero) {
            foreach ($diasObjetivo as $dia) {
                if (!$this->horarioModel->guardarAsignacion($idTercero, $idHorario, $dia, $userId)) {
                    throw new RuntimeException('No se pudo guardar una o más asignaciones.');
                }
            }
        }

        $cantidad = count($idsTerceros);
        $esSemanaCompleta = $diaSemana === 0;
        $mensaje = $esSemanaCompleta
            ? ($cantidad > 1
                ? "Se asignó la semana completa a {$cantidad} empleados correctamente."
                : 'Se asignó la semana completa correctamente.')
            : ($cantidad > 1
                ? "Se asignó el turno a {$cantidad} empleados correctamente."
                : 'Asignación guardada correctamente.');

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

        redirect('horario/index?tipo=success&msg=Asignación eliminada correctamente.');
    }
}
