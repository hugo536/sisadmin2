<?php
declare(strict_types=1);

// Requerimos el controlador base
require_once BASE_PATH . '/app/core/Controlador.php';
require_once BASE_PATH . '/app/models/inventario/ControlEnvasesModel.php'; 
require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';

class EnvasesController extends Controlador
{
    private ControlEnvasesModel $envasesModel;

    public function __construct()
    {
        AuthMiddleware::handle();
        parent::__construct();
        $this->envasesModel = new ControlEnvasesModel();
    }

    /**
     * Función principal que carga la pantalla
     */
    public function index(): void
    {
        require_permiso('inventario.ver');

        $datos = [
            'titulo' => 'Control de Envases Retornables',
            'ruta_actual' => 'inventario/envases',
            'saldos' => $this->envasesModel->obtenerSaldosGlobales(),
            'clientes' => $this->envasesModel->obtenerClientes(),
            'items' => $this->envasesModel->obtenerEnvasesDisponibles(),
            'almacenes' => $this->envasesModel->obtenerAlmacenesActivos(),
        ];

        $this->vista('inventario/envases', $datos);
    }

    /**
     * Función que recibe los datos del Modal y los guarda en la BD
     */
    public function guardar(): void
    {
        require_permiso('inventario.movimiento.crear');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 1. Recibimos los datos del formulario (AJAX)
            $id_tercero = (int)($_POST['id_tercero'] ?? 0);
            $id_item = (int)($_POST['id_item_envase'] ?? 0);
            $tipo = $_POST['tipo_operacion'] ?? '';
            $cantidad = (int)($_POST['cantidad'] ?? 0);
            $obs = $_POST['observaciones'] ?? '';
            $id_almacen = (int)($_POST['id_almacen'] ?? 0);
            $idUsuario = (int)($_SESSION['id'] ?? 0);

            // 2. Validamos que no vengan vacíos
            if ($id_tercero === 0 || $id_item === 0 || $tipo === '' || $cantidad <= 0) {
                http_response_code(400); // Bad Request
                echo "Faltan datos obligatorios";
                return;
            }

            try {
                // 3. Enviamos a guardar a la base de datos
                $tiposValidos = ['RECEPCION_VACIO', 'ENTREGA_LLENO', 'AJUSTE_CLIENTE'];
                if (!in_array($tipo, $tiposValidos, true)) {
                    http_response_code(400);
                    echo "Tipo de operación inválido";
                    return;
                }

                $exito = $this->envasesModel->registrarMovimientoConKardex(
                    $id_tercero,
                    $id_item,
                    $tipo,
                    $cantidad,
                    null,
                    $obs,
                    $idUsuario,
                    $id_almacen
                );

                if ($exito) {
                    http_response_code(200); // OK
                    echo "Guardado correctamente";
                } else {
                    http_response_code(500); // Error de servidor
                    echo "No se pudo guardar en la base de datos";
                }
            } catch (Throwable $e) {
                // Si la base de datos rechaza la inserción (ej. falta una columna)
                http_response_code(500);
                echo "Error SQL: " . $e->getMessage();
            }
        } else {
            http_response_code(405); // Método no permitido
            echo "Método no permitido";
        }
    }

    /**
     * NUEVA FUNCIÓN: Devuelve el historial en formato JSON para el Modal
     */
    public function historial(): void
    {
        // 1. Recibimos los parámetros por GET
        $id_tercero = (int)($_GET['tercero'] ?? 0);
        $id_item = (int)($_GET['item'] ?? 0);

        // 2. Validamos
        if ($id_tercero === 0 || $id_item === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan parámetros']);
            return;
        }

        try {
            // 3. Solicitamos los datos al modelo
            $datos = $this->envasesModel->obtenerHistorial($id_tercero, $id_item);
            
            // 4. Respondemos en formato JSON
            header('Content-Type: application/json');
            echo json_encode($datos);
            
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener el historial: ' . $e->getMessage()]);
        }
    }
}
