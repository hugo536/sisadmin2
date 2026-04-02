<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/DepreciacionModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaPeriodoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaParametrosModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class CierreContableController extends Controlador
{
    private DepreciacionModel $depModel;
    private ContaPeriodoModel $periodoModel;
    private ContaAsientoModel $asientoModel;
    private ContaParametrosModel $paramModel;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        parent::__construct();
        $this->depModel = new DepreciacionModel();
        $this->periodoModel = new ContaPeriodoModel();
        $this->asientoModel = new ContaAsientoModel();
        $this->paramModel = new ContaParametrosModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.cierre.mensual');

        $periodos = $this->periodoModel->listarPorAnio((int)($_GET['anio'] ?? date('Y')));
        $this->render('contabilidad/cierres', [
            'ruta_actual' => 'cierre_contable/index',
            'periodos' => $periodos,
        ]);
    }

    public function ejecutar_depreciacion(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.depreciacion.ejecutar');
        try {
            $periodo = (string)($_POST['periodo'] ?? date('Y-m'));
            $n = $this->depModel->ejecutar($periodo, (int)($_SESSION['id'] ?? 0));
            $this->logEvento('depreciacion_masiva', 'Depreciación mensual ejecutada para ' . $periodo . '. Registros: ' . $n);
            redirect('cierre_contable/index?ok=1&msg=' . urlencode("Depreciaciones ejecutadas: {$n}"));
        } catch (Throwable $e) {
            redirect('cierre_contable/index?error=' . urlencode($e->getMessage()));
        }
    }

    public function cierre_mensual(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.cierre.mensual');
        try {
            $idPeriodo = (int)($_POST['id_periodo'] ?? 0);
            $periodo = $this->periodoModel->obtenerPorId($idPeriodo);
            if (!$periodo) {
                throw new RuntimeException('Periodo no encontrado.');
            }
            $periodoYm = sprintf('%04d-%02d', (int)$periodo['anio'], (int)$periodo['mes']);

            $this->validarConciliacionesCerradas($periodoYm);
            $this->validarDepreciacionesEjecutadas($periodoYm);
            $this->validarAsientosBalanceados($idPeriodo);

            $this->recalcularCifRealProduccion($periodoYm, (int)($_SESSION['id'] ?? 0));

            $this->periodoModel->cambiarEstado($idPeriodo, 'CERRADO', (int)($_SESSION['id'] ?? 0));
            $this->logEvento('cierre_contable_mensual', 'Cierre mensual ejecutado para periodo ' . $periodoYm);
            redirect('cierre_contable/index?ok=1&msg=' . urlencode('Cierre mensual completado: ' . $periodoYm));
        } catch (Throwable $e) {
            redirect('cierre_contable/index?error=' . urlencode($e->getMessage()));
        }
    }

    public function cierre_anual(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.cierre.anual');
        try {
            $anio = (int)($_POST['anio'] ?? date('Y'));
            $this->trasladarResultadoEjercicioAPatrimonio($anio);

            foreach ($this->periodoModel->listarPorAnio($anio) as $p) {
                if ((string)$p['estado'] === 'ABIERTO') {
                    $this->periodoModel->cambiarEstado((int)$p['id'], 'CERRADO', (int)($_SESSION['id'] ?? 0));
                }
            }
            $this->logEvento('cierre_contable_anual', 'Cierre anual ejecutado para año ' . $anio);
            redirect('cierre_contable/index?ok=1&msg=' . urlencode('Año fiscal bloqueado: ' . $anio));
        } catch (Throwable $e) {
            redirect('cierre_contable/index?error=' . urlencode($e->getMessage()));
        }
    }

    public function estados_financieros(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.reportes.ver');
        $desde = trim((string)($_GET['fecha_desde'] ?? date('Y-01-01')));
        $hasta = trim((string)($_GET['fecha_hasta'] ?? date('Y-m-d')));

        $sql = 'SELECT c.tipo, SUM(d.debe) AS debe, SUM(d.haber) AS haber
                FROM conta_asientos_detalle d
                INNER JOIN conta_asientos a ON a.id = d.id_asiento
                INNER JOIN conta_cuentas c ON c.id = d.id_cuenta
                WHERE a.estado = "REGISTRADO" AND a.deleted_at IS NULL AND d.deleted_at IS NULL
                AND a.fecha BETWEEN :desde AND :hasta
                GROUP BY c.tipo';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = ['ACTIVO' => 0, 'PASIVO' => 0, 'PATRIMONIO' => 0, 'INGRESO' => 0, 'GASTO' => 0];
        foreach ($rows as $r) {
            $map[$r['tipo']] = round((float)$r['debe'] - (float)$r['haber'], 4);
        }

        if (((string)($_GET['formato'] ?? '')) === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="estados_financieros.csv"');
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['reporte','concepto','monto']);
            fputcsv($out, ['estado_resultados','ingresos', -1 * $map['INGRESO']]);
            fputcsv($out, ['estado_resultados','costos_gastos', $map['GASTO']]);
            fputcsv($out, ['estado_resultados','resultado_neto', (-1 * $map['INGRESO']) - $map['GASTO']]);
            fputcsv($out, ['balance_general','activos', $map['ACTIVO']]);
            fputcsv($out, ['balance_general','pasivos', -1 * $map['PASIVO']]);
            fputcsv($out, ['balance_general','patrimonio', -1 * $map['PATRIMONIO']]);
            fclose($out);
            exit;
        }

        $this->render('contabilidad/estados_financieros', [
            'ruta_actual' => 'cierre_contable/estados_financieros',
            'filtros' => ['fecha_desde' => $desde, 'fecha_hasta' => $hasta],
            'estadoResultados' => [
                'ingresos' => -1 * $map['INGRESO'],
                'costos_gastos' => $map['GASTO'],
                'resultado_neto' => (-1 * $map['INGRESO']) - $map['GASTO'],
            ],
            'balanceGeneral' => [
                'activos' => $map['ACTIVO'],
                'pasivos' => -1 * $map['PASIVO'],
                'patrimonio' => -1 * $map['PATRIMONIO'],
            ],
        ]);
    }

    private function validarConciliacionesCerradas(string $periodoYm): void
    {
        $sql = 'SELECT COUNT(1) FROM tesoreria_conciliaciones WHERE periodo = :periodo AND deleted_at IS NULL';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute(['periodo' => $periodoYm]);
        $total = (int)$stmt->fetchColumn();
        if ($total <= 0) {
            throw new RuntimeException('No existen conciliaciones bancarias para el periodo ' . $periodoYm . '.');
        }

        $sqlOpen = 'SELECT COUNT(1) FROM tesoreria_conciliaciones WHERE periodo = :periodo AND estado <> "CERRADA" AND deleted_at IS NULL';
        $stmtOpen = Conexion::get()->prepare($sqlOpen);
        $stmtOpen->execute(['periodo' => $periodoYm]);
        if ((int)$stmtOpen->fetchColumn() > 0) {
            throw new RuntimeException('Existen conciliaciones bancarias abiertas para el periodo ' . $periodoYm . '.');
        }
    }

    private function validarDepreciacionesEjecutadas(string $periodoYm): void
    {
        $sql = 'SELECT COUNT(1)
                FROM activos_fijos a
                WHERE a.deleted_at IS NULL
                  AND a.estado IN ("ACTIVO", "DEPRECIADO")
                  AND a.valor_libros > a.valor_residual
                  AND NOT EXISTS (
                      SELECT 1 FROM conta_depreciaciones d
                      WHERE d.id_activo_fijo = a.id AND d.periodo = :periodo AND d.deleted_at IS NULL
                  )';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute(['periodo' => $periodoYm]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('Debe ejecutar depreciaciones del periodo ' . $periodoYm . ' antes del cierre mensual.');
        }
    }

    private function validarAsientosBalanceados(int $idPeriodo): void
    {
        $sql = 'SELECT COUNT(1)
                FROM (
                  SELECT a.id, ROUND(SUM(d.debe),4) AS debe, ROUND(SUM(d.haber),4) AS haber
                  FROM conta_asientos a
                  INNER JOIN conta_asientos_detalle d ON d.id_asiento = a.id AND d.deleted_at IS NULL
                  WHERE a.id_periodo = :id_periodo AND a.deleted_at IS NULL AND a.estado = "REGISTRADO"
                  GROUP BY a.id
                ) t WHERE t.debe <> t.haber';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute(['id_periodo' => $idPeriodo]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('Existen asientos desbalanceados en el periodo a cerrar.');
        }
    }

    private function trasladarResultadoEjercicioAPatrimonio(int $anio): void
    {
        $map = $this->paramModel->obtenerMapa();
        if (!isset($map['CTA_RESULTADO_EJERCICIO']) || !isset($map['CTA_PATRIMONIO_RESULTADOS'])) {
            throw new RuntimeException('Faltan parámetros contables CTA_RESULTADO_EJERCICIO y/o CTA_PATRIMONIO_RESULTADOS.');
        }

        $stmt = Conexion::get()->prepare('SELECT COALESCE(SUM(d.haber - d.debe),0)
            FROM conta_asientos a
            INNER JOIN conta_asientos_detalle d ON d.id_asiento = a.id AND d.deleted_at IS NULL
            INNER JOIN conta_cuentas c ON c.id = d.id_cuenta
            WHERE a.deleted_at IS NULL AND a.estado = "REGISTRADO" AND a.fecha BETWEEN :desde AND :hasta
              AND c.tipo IN ("INGRESO", "GASTO")');
        $stmt->execute(['desde' => $anio . '-01-01', 'hasta' => $anio . '-12-31']);
        $resultado = round((float)$stmt->fetchColumn(), 4);
        if ($resultado == 0.0) {
            return;
        }

        $periodo = $this->periodoModel->crearSiNoExiste($anio, 12, (int)($_SESSION['id'] ?? 0));
        $lineas = [];
        if ($resultado > 0) {
            $lineas[] = ['id_cuenta' => (int)$map['CTA_RESULTADO_EJERCICIO'], 'debe' => $resultado, 'haber' => 0, 'referencia' => 'CIERRE-' . $anio];
            $lineas[] = ['id_cuenta' => (int)$map['CTA_PATRIMONIO_RESULTADOS'], 'debe' => 0, 'haber' => $resultado, 'referencia' => 'CIERRE-' . $anio];
        } else {
            $monto = abs($resultado);
            $lineas[] = ['id_cuenta' => (int)$map['CTA_PATRIMONIO_RESULTADOS'], 'debe' => $monto, 'haber' => 0, 'referencia' => 'CIERRE-' . $anio];
            $lineas[] = ['id_cuenta' => (int)$map['CTA_RESULTADO_EJERCICIO'], 'debe' => 0, 'haber' => $monto, 'referencia' => 'CIERRE-' . $anio];
        }

        $this->asientoModel->crearManual([
            'fecha' => $anio . '-12-31',
            'id_periodo' => (int)$periodo['id'],
            'glosa' => 'Traslado de resultado del ejercicio ' . $anio . ' a patrimonio',
            'origen_modulo' => 'MANUAL',
            'estado' => 'REGISTRADO',
        ], $lineas, (int)($_SESSION['id'] ?? 0));
    }


    private function recalcularCifRealProduccion(string $periodoYm, int $userId): void
    {
        [$anio, $mes] = array_map('intval', explode('-', $periodoYm));
        if ($anio <= 0 || $mes <= 0) {
            return;
        }

        $fechaInicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        $db = Conexion::get();
        $iniciaTx = !$db->inTransaction();
        if ($iniciaTx) {
            $db->beginTransaction();
        }

        try {
            $stmtEnergia = $db->prepare('SELECT COALESCE(SUM(d.debe - d.haber), 0) AS energia_real
                                         FROM conta_asientos a
                                         INNER JOIN conta_asientos_detalle d ON d.id_asiento = a.id AND d.deleted_at IS NULL
                                         INNER JOIN conta_cuentas c ON c.id = d.id_cuenta
                                         WHERE a.deleted_at IS NULL
                                           AND a.estado = "REGISTRADO"
                                           AND a.fecha >= :desde
                                           AND a.fecha <= :hasta
                                           AND c.tipo = "GASTO"
                                           AND (
                                             UPPER(c.nombre) LIKE "%LUZ%"
                                             OR UPPER(c.nombre) LIKE "%ENERG%"
                                             OR UPPER(a.glosa) LIKE "%LUZ%"
                                             OR UPPER(a.glosa) LIKE "%ENERG%"
                                             OR UPPER(COALESCE(d.referencia, "")) LIKE "%LUZ%"
                                             OR UPPER(COALESCE(d.referencia, "")) LIKE "%ENERG%"
                                           )');
            $stmtEnergia->execute(['desde' => $fechaInicio, 'hasta' => $fechaFin]);
            $energiaReal = (float) ($stmtEnergia->fetchColumn() ?: 0);
            if ($energiaReal <= 0) {
                if ($iniciaTx) {
                    $db->commit();
                }
                return;
            }

            $stmtOps = $db->prepare('SELECT id,
                                            COALESCE(NULLIF(cantidad_producida, 0), cantidad_planificada, 0) AS unidades,
                                            COALESCE(total_md_real, costo_md_real, 0) AS md_real,
                                            COALESCE(total_mod_real, costo_mod_real, 0) AS mod_real
                                     FROM produccion_ordenes
                                     WHERE deleted_at IS NULL
                                       AND estado = 2
                                       AND fecha_fin >= :desde
                                       AND fecha_fin <= :hasta');
            $stmtOps->execute(['desde' => $fechaInicio . ' 00:00:00', 'hasta' => $fechaFin . ' 23:59:59']);
            $ordenes = $stmtOps->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($ordenes === []) {
                if ($iniciaTx) {
                    $db->commit();
                }
                return;
            }

            $totalUnidades = 0.0;
            foreach ($ordenes as $o) {
                $totalUnidades += max(0.0, (float) ($o['unidades'] ?? 0));
            }
            if ($totalUnidades <= 0) {
                if ($iniciaTx) {
                    $db->commit();
                }
                return;
            }

            $stmtDelete = $db->prepare('DELETE FROM produccion_ordenes_cif
                                        WHERE id_orden = :id_orden
                                          AND (
                                                UPPER(concepto) LIKE "CIF ESTIMADO%"
                                             OR UPPER(concepto) LIKE "CUOTA CIF PREDETERMINADA%"
                                             OR UPPER(concepto) LIKE "CIF REAL RECLASIFICADO%"
                                          )');

            $stmtInsert = $db->prepare('INSERT INTO produccion_ordenes_cif
                                            (id_orden, concepto, id_activo, base_distribucion, costo_aplicado)
                                        VALUES
                                            (:id_orden, :concepto, NULL, :base_distribucion, :costo_aplicado)');

            $stmtUpdate = $db->prepare('UPDATE produccion_ordenes
                                        SET costo_cif_real = :cif_real,
                                            total_cif_real = :cif_real,
                                            costo_real_total = (:md_real + :mod_real + :cif_real),
                                            costo_real_unitario = CASE WHEN :unidades > 0 THEN ((:md_real + :mod_real + :cif_real) / :unidades) ELSE costo_real_unitario END,
                                            costo_unitario_real = CASE WHEN :unidades > 0 THEN ((:md_real + :mod_real + :cif_real) / :unidades) ELSE costo_unitario_real END,
                                            updated_at = NOW(),
                                            updated_by = :updated_by
                                        WHERE id = :id_orden
                                          AND deleted_at IS NULL');

            foreach ($ordenes as $o) {
                $idOrden = (int) ($o['id'] ?? 0);
                $unidades = max(0.0, (float) ($o['unidades'] ?? 0));
                $mdReal = (float) ($o['md_real'] ?? 0);
                $modReal = (float) ($o['mod_real'] ?? 0);
                if ($idOrden <= 0 || $unidades <= 0) {
                    continue;
                }

                $cifRealOrden = $energiaReal * ($unidades / $totalUnidades);

                $stmtDelete->execute(['id_orden' => $idOrden]);
                $stmtInsert->execute([
                    'id_orden' => $idOrden,
                    'concepto' => 'CIF real reclasificado cierre ' . $periodoYm,
                    'base_distribucion' => 'Unidades',
                    'costo_aplicado' => number_format($cifRealOrden, 4, '.', ''),
                ]);

                $stmtUpdate->execute([
                    'cif_real' => number_format($cifRealOrden, 4, '.', ''),
                    'md_real' => number_format($mdReal, 4, '.', ''),
                    'mod_real' => number_format($modReal, 4, '.', ''),
                    'unidades' => number_format($unidades, 4, '.', ''),
                    'updated_by' => $userId,
                    'id_orden' => $idOrden,
                ]);
            }

            if ($iniciaTx) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($iniciaTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function logEvento(string $evento, string $descripcion): void
    {
        $this->usuariosModel->insertar_bitacora(
            (int)($_SESSION['id'] ?? 0),
            $evento,
            $descripcion,
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI')
        );
    }
}
