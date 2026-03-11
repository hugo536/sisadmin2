<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaPeriodoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';

class ReporteContableController extends Controlador
{
    private ContaAsientoModel $asientoModel;
    private ContaPeriodoModel $periodoModel;
    private CentroCostoModel $centroCostoModel;

    public function __construct()
    {
        parent::__construct();
        // Solo instanciamos los 3 modelos que usa esta pantalla
        $this->asientoModel = new ContaAsientoModel();
        $this->periodoModel = new ContaPeriodoModel();
        $this->centroCostoModel = new CentroCostoModel();
    }

    // Antes se llamaba reportes() en el controlador general
    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.reportes.ver');

        // 1. Capturamos los filtros
        $filtros = [
            'id_periodo' => (int)($_GET['id_periodo'] ?? 0),
            'id_centro_costo' => (int)($_GET['id_centro_costo'] ?? 0),
            'fecha_desde' => trim((string)($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string)($_GET['fecha_hasta'] ?? '')),
            'pagina' => 1,
            'por_pagina' => 300,
        ];
        
        $rows = $this->asientoModel->listar($filtros);

        // 2. Preparamos las condiciones para el SQL del Balance
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
        if ($filtros['id_centro_costo'] > 0) {
            $where[] = 'd.id_centro_costo = :id_centro_costo';
            $params['id_centro_costo'] = $filtros['id_centro_costo'];
        }

        // 3. Ejecutamos la suma matemática
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

        // 4. Enviamos los datos a tu vista
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
}