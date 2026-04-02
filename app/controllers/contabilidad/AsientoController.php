<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaCuentaModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaPeriodoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';

class AsientoController extends Controlador
{
    private ContaAsientoModel $asientoModel;
    private ContaCuentaModel $cuentaModel;
    private ContaPeriodoModel $periodoModel;
    private CentroCostoModel $centroCostoModel;

    public function __construct()
    {
        parent::__construct();
        $this->asientoModel = new ContaAsientoModel();
        $this->cuentaModel = new ContaCuentaModel();
        $this->periodoModel = new ContaPeriodoModel();
        $this->centroCostoModel = new CentroCostoModel();
    }

    // Antes era asientos()
    public function index(): void
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

    // Antes era guardar_asiento()
    public function guardar(): void
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

    // Antes era anular_asiento()
    public function anular(): void
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

    private function uid(): int
    {
        return (int)($_SESSION['id'] ?? 0);
    }
}