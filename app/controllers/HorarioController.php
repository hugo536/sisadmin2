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

        $this->render('horario', [
            'ruta_actual' => 'horario/index',
            'horarios' => $this->horarioModel->listarHorarios(),
            'empleados' => $this->horarioModel->listarEmpleados(),
            'asignaciones' => $this->horarioModel->listarAsignaciones(),
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
        $idTercero = (int) ($_POST['id_tercero'] ?? 0);
        $idHorario = (int) ($_POST['id_horario'] ?? 0);
        $diaSemana = (int) ($_POST['dia_semana'] ?? 0);

        if ($idTercero <= 0 || $idHorario <= 0 || $diaSemana < 1 || $diaSemana > 7) {
            throw new RuntimeException('Datos inválidos para la asignación.');
        }

        if (!$this->horarioModel->guardarAsignacion($idTercero, $idHorario, $diaSemana, $userId)) {
            throw new RuntimeException('No se pudo guardar la asignación (puede estar duplicada).');
        }

        redirect('horario/index?tipo=success&msg=Asignación guardada correctamente.');
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
