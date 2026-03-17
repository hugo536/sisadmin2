<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/costos/CierresModel.php'; // <-- Ahora llama a CierresModel

class CierresController extends Controlador
{
    private CierresModel $cierresModel; // <-- Usamos la clase correcta

    public function __construct()
    {
        $this->cierresModel = new CierresModel(); // <-- Instanciamos el modelo correcto
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.produccion.ver');
        
        $userId = (int) ($_SESSION['id'] ?? 0);
        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = $_POST['accion'] ?? '';

            // 1. AJAX: Buscar lo absorbido en el mes seleccionado
            if ($accion === 'obtener_absorbido_ajax') {
                ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                $periodo = trim((string)($_POST['periodo'] ?? ''));
                
                if (empty($periodo)) {
                    echo json_encode(['success' => false, 'message' => 'Periodo no válido']);
                    exit;
                }

                // Llamada al modelo corregida
                $datos = $this->cierresModel->obtenerCostosAbsorbidosPorPeriodo($periodo);
                echo json_encode(['success' => true, 'data' => $datos]);
                exit;
            }

            // 2. POST: Guardar Cierre
            if ($accion === 'guardar_cierre') {
                try {
                    $periodo = trim((string)($_POST['periodo'] ?? ''));
                    $modAbs = (float)($_POST['mod_absorbida'] ?? 0);
                    $modReal = (float)($_POST['mod_real'] ?? 0);
                    $cifAbs = (float)($_POST['cif_absorbido'] ?? 0);
                    $cifReal = (float)($_POST['cif_real'] ?? 0);
                    $obs = trim((string)($_POST['observaciones'] ?? ''));

                    $modVar = $modAbs - $modReal;
                    $cifVar = $cifAbs - $cifReal;

                    // Llamada al modelo corregida
                    $this->cierresModel->registrarCierre([
                        'periodo' => $periodo,
                        'mod_absorbida' => $modAbs,
                        'mod_real_pagada' => $modReal,
                        'mod_variacion' => $modVar,
                        'cif_absorbido' => $cifAbs,
                        'cif_real_pagado' => $cifReal,
                        'cif_variacion' => $cifVar,
                        'observaciones' => $obs,
                        'created_by' => $userId
                    ]);

                    $this->setFlash('success', "Cierre del periodo $periodo registrado exitosamente.");
                    header('Location: ?ruta=costos/cierres');
                    exit;

                } catch (PDOException $e) {
                    if ($e->getCode() == 23000 || $e->getCode() == 1062) {
                        $flash = ['tipo' => 'error', 'texto' => 'Ese periodo ya se encuentra cerrado.'];
                    } else {
                        $flash = ['tipo' => 'error', 'texto' => 'Error de base de datos: ' . $e->getMessage()];
                    }
                } catch (Exception $e) {
                    $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
                }
            }
        }

        // Llamada al modelo corregida
        $listadoCierres = $this->cierresModel->listarCierres();

        $this->render('costos/cierres', [
            'ruta_actual' => 'costos/cierres',
            'cierres' => $listadoCierres,
            'flash' => $flash
        ]);
    }

    private function setFlash(string $tipo, string $texto): void
    {
        $_SESSION['cierres_flash'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function obtenerFlash(): array
    {
        $flash = ['tipo' => '', 'texto' => ''];
        if (isset($_SESSION['cierres_flash']) && is_array($_SESSION['cierres_flash'])) {
            $flash = $_SESSION['cierres_flash'];
            unset($_SESSION['cierres_flash']);
        }
        return $flash;
    }
}