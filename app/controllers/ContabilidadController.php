<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaCuentaModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaPeriodoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaParametrosModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';

class ContabilidadController extends Controlador
{
    private ContaCuentaModel $cuentaModel;
    private ContaPeriodoModel $periodoModel;
    private ContaAsientoModel $asientoModel;
    private ContaParametrosModel $paramModel;
    private CentroCostoModel $centroCostoModel;

    public function __construct()
    {
        parent::__construct();
        $this->cuentaModel = new ContaCuentaModel();
        $this->periodoModel = new ContaPeriodoModel();
        $this->asientoModel = new ContaAsientoModel();
        $this->paramModel = new ContaParametrosModel();
        $this->centroCostoModel = new CentroCostoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.ver');
        redirect('contabilidad/plan');
    }

    public function plan(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.ver');

        $this->render('contabilidad/conta_plan', [
            'ruta_actual' => 'contabilidad/plan',
            'cuentas' => $this->cuentaModel->listar(),
            'parametros' => $this->paramModel->listar(),
            'cuentasMovimiento' => $this->cuentaModel->listarMovimientoActivas(),
        ]);
    }

    public function guardar_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('contabilidad/plan');
        }

        try {
            $this->cuentaModel->guardar($_POST, $this->uid());
            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function inactivar_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        try {
            $this->cuentaModel->inactivar((int)($_POST['id_cuenta'] ?? 0), $this->uid());
            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function cambiar_estado_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('contabilidad/plan');
        }

        try {
            $idCuenta = (int)($_POST['id_cuenta'] ?? 0);
            $estado = (int)($_POST['estado'] ?? 0);
            $this->cuentaModel->cambiarEstado($idCuenta, $estado, $this->uid());
            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function guardar_parametro(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('contabilidad/plan');
        }
        try {
            $this->paramModel->guardar((string)($_POST['clave'] ?? ''), (int)($_POST['id_cuenta'] ?? 0), $this->uid());
            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function periodos(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.periodos.ver');

        $anio = (int)($_GET['anio'] ?? date('Y'));
        $this->render('contabilidad/conta_periodos', [
            'ruta_actual' => 'contabilidad/periodos',
            'anio' => $anio,
            'periodos' => $this->periodoModel->listarPorAnio($anio),
        ]);
    }

    public function crear_periodo(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.periodos.cerrar');
        try {
            $anio = (int)($_POST['anio'] ?? date('Y'));
            $mes = (int)($_POST['mes'] ?? 0);
            $this->periodoModel->crearSiNoExiste($anio, $mes, $this->uid());
            redirect('contabilidad/periodos?anio=' . $anio . '&ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/periodos?error=' . urlencode($e->getMessage()));
        }
    }

    public function cerrar_periodo(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.periodos.cerrar');
        try {
            $this->periodoModel->cambiarEstado((int)($_POST['id_periodo'] ?? 0), 'CERRADO', $this->uid());
            redirect('contabilidad/periodos?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/periodos?error=' . urlencode($e->getMessage()));
        }
    }

    public function abrir_periodo(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.periodos.cerrar');
        try {
            $this->periodoModel->cambiarEstado((int)($_POST['id_periodo'] ?? 0), 'ABIERTO', $this->uid());
            redirect('contabilidad/periodos?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/periodos?error=' . urlencode($e->getMessage()));
        }
    }

    public function asientos(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.ver');

        $filtros = [
            'id_periodo' => (int)($_GET['id_periodo'] ?? 0),
            'fecha_desde' => trim((string)($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string)($_GET['fecha_hasta'] ?? '')),
            'pagina' => (int)($_GET['pagina'] ?? 1),
            'por_pagina' => 20,
        ];

        $total = $this->asientoModel->contar($filtros);
        $totalPaginas = max(1, (int)ceil($total / (int)$filtros['por_pagina']));
        if ($filtros['pagina'] > $totalPaginas) {
            $filtros['pagina'] = $totalPaginas;
        }

        $this->render('contabilidad/conta_asientos', [
            'ruta_actual' => 'contabilidad/asientos',
            'asientos' => $this->asientoModel->listar($filtros),
            'cuentas' => $this->cuentaModel->listarMovimientoActivas(),
            'periodos' => $this->periodoModel->listarPorAnio((int)date('Y')),
            'centrosCosto' => $this->centroCostoModel->listar(),
            'detalleFn' => fn(int $id) => $this->asientoModel->obtenerDetalle($id),
            'filtros' => $filtros,
            'totalAsientos' => $total,
            'totalPaginas' => $totalPaginas,
        ]);
    }

    public function guardar_asiento(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.asientos.crear');

        try {
            $cabecera = [
                'fecha' => (string)($_POST['fecha'] ?? date('Y-m-d')),
                'id_periodo' => (int)($_POST['id_periodo'] ?? 0),
                'glosa' => trim((string)($_POST['glosa'] ?? '')),
                'origen_modulo' => 'MANUAL',
                'id_origen' => null,
                'estado' => 'REGISTRADO',
            ];
            $lineas = [];
            $idCuentas = $_POST['id_cuenta'] ?? [];
            $debe = $_POST['debe'] ?? [];
            $haber = $_POST['haber'] ?? [];
            foreach ($idCuentas as $i => $idCuenta) {
                $lineas[] = [
                    'id_cuenta' => (int)$idCuenta,
                    'debe' => (float)($debe[$i] ?? 0),
                    'haber' => (float)($haber[$i] ?? 0),
                    'referencia' => trim((string)($_POST['referencia'][$i] ?? '')),
                    'id_centro_costo' => (int)($_POST['id_centro_costo'][$i] ?? 0),
                ];
            }
            $this->asientoModel->crearManual($cabecera, $lineas, $this->uid());
            redirect('contabilidad/asientos?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/asientos?error=' . urlencode($e->getMessage()));
        }
    }

    public function anular_asiento(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.asientos.anular');
        try {
            $this->asientoModel->anularConReversion((int)($_POST['id_asiento'] ?? 0), $this->uid());
            redirect('contabilidad/asientos?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/asientos?error=' . urlencode($e->getMessage()));
        }
    }

    public function centros_costo(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');

        $this->render('contabilidad/centros_costo', [
            'ruta_actual' => 'contabilidad/centros_costo',
            'centros' => $this->centroCostoModel->listar(),
        ]);
    }

    public function guardar_centro_costo(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');
        try {
            $this->centroCostoModel->guardar($_POST, $this->uid());
            redirect('contabilidad/centros_costo?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/centros_costo?error=' . urlencode($e->getMessage()));
        }
    }

    public function reportes(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.reportes.ver');

        $filtros = [
            'id_periodo' => (int)($_GET['id_periodo'] ?? 0),
            'fecha_desde' => trim((string)($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string)($_GET['fecha_hasta'] ?? '')),
            'pagina' => 1,
            'por_pagina' => 300,
        ];
        $rows = $this->asientoModel->listar($filtros);

        $where = ['d.deleted_at IS NULL', 'a.deleted_at IS NULL', 'a.estado = "REGISTRADO"'];
        $params = [];
        if ($filtros['id_periodo'] > 0) {
            $where[] = 'a.id_periodo = :id_periodo';
            $params['id_periodo'] = $filtros['id_periodo'];
        }
        if ($filtros['fecha_desde'] !== '') {
            $where[] = 'a.fecha >= :fecha_desde';
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if ($filtros['fecha_hasta'] !== '') {
            $where[] = 'a.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        $sql = 'SELECT c.codigo, c.nombre, SUM(d.debe) AS debe, SUM(d.haber) AS haber, SUM(d.debe)-SUM(d.haber) AS saldo
                FROM conta_asientos_detalle d
                INNER JOIN conta_asientos a ON a.id = d.id_asiento
                INNER JOIN conta_cuentas c ON c.id = d.id_cuenta
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY c.id, c.codigo, c.nombre
                ORDER BY c.codigo ASC';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute($params);
        $balance = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->render('contabilidad/conta_reportes', [
            'ruta_actual' => 'contabilidad/reportes',
            'libroDiario' => $rows,
            'detalleFn' => fn(int $id) => $this->asientoModel->obtenerDetalle($id),
            'balance' => $balance,
            'filtros' => $filtros,
            'periodos' => $this->periodoModel->listarPorAnio((int)date('Y')),
            'centrosCosto' => $this->centroCostoModel->listar(),
        ]);
    }

    private function uid(): int
    {
        return (int)($_SESSION['id'] ?? 0);
    }
}
