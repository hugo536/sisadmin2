<?php
// app/controladores/ComercialController.php

require_once BASE_PATH . '/app/models/comercial/PresentacionModel.php';
require_once BASE_PATH . '/app/models/comercial/ListaPrecioModel.php';
require_once BASE_PATH . '/app/models/comercial/AsignacionModel.php';
require_once BASE_PATH . '/app/models/ItemsModel.php';

class ComercialController extends Controlador {

    private $presentacionModel;
    private $listaPrecioModel;
    private $asignacionModel;
    private $itemModel;

    public function __construct() {
        parent::__construct();
        if (!isset($_SESSION['id'])) {
            redirect('login');
        }

        $this->presentacionModel = new PresentacionModel();
        $this->listaPrecioModel = new ListaPrecioModel();
        $this->asignacionModel = new AsignacionModel();
        $this->itemModel = new ItemsModel();
    }

    // =========================================================================
    // 1. GESTIÓN DE PRESENTACIONES
    // =========================================================================

    public function presentaciones() {
        $datos = [
            'titulo' => 'Gestión de Presentaciones',
            'items' => $this->presentacionModel->listarProductosParaSelect(),
            'presentaciones' => $this->presentacionModel->listarTodo()
        ];

        $this->vista('comercial/presentaciones', $datos);
    }

    public function obtenerPresentacion() {
        $id = (int)($_GET['id'] ?? 0);

        if ($id > 0) {
            $data = $this->presentacionModel->obtener($id);
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No encontrado']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
        }
    }

    public function guardarPresentacion() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = [
                'id' => $_POST['id'] ?? null,
                'id_item' => $_POST['id_item'],
                'factor' => $_POST['factor'],
                'precio_x_menor' => $_POST['precio_x_menor'],
                'precio_x_mayor' => $_POST['precio_x_mayor'] ?? null,
                'cantidad_minima_mayor' => $_POST['cantidad_minima_mayor'] ?? null,
                'peso_bruto' => isset($_POST['peso_bruto']) ? (float) $_POST['peso_bruto'] : 0
            ];

            if (empty($datos['id_item']) || empty($datos['factor'])) {
                redirect('comercial/presentaciones?error=campos_vacios');
                return;
            }

            if ($this->presentacionModel->guardar($datos)) {
                redirect('comercial/presentaciones?success=guardado');
            } else {
                redirect('comercial/presentaciones?error=db_error');
            }
        }
    }

    public function eliminarPresentacion() {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            if ($this->esPeticionAjax()) {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
            } else {
                redirect('comercial/presentaciones?error=id_invalido');
            }
            return;
        }
        if ($this->presentacionModel->eliminar($id)) {
            if ($this->esPeticionAjax()) {
                echo json_encode(['success' => true]);
            } else {
                redirect('comercial/presentaciones');
            }
        } else {
            if ($this->esPeticionAjax()) echo json_encode(['success' => false]);
        }
    }

    public function toggleEstadoPresentacion() {
        $id = (int) ($_GET['id'] ?? 0);
        $estado = (int) ($_GET['estado'] ?? -1);

        if ($id <= 0 || !in_array($estado, [0, 1], true)) {
            redirect('comercial/presentaciones?error=parametros_invalidos');
            return;
        }

        if ($this->presentacionModel->actualizarEstado($id, $estado)) {
            redirect('comercial/presentaciones?success=estado_actualizado');
            return;
        }

        redirect('comercial/presentaciones?error=estado_no_actualizado');
    }

    // =========================================================================
    // 2. ACUERDOS COMERCIALES (MATRIZ DE TARIFAS POR CLIENTE)
    // =========================================================================

    public function listas() {
        $idAcuerdo = (int)($_GET['id'] ?? 0);
        $acuerdos = $this->listaPrecioModel->listarAcuerdos();

        $acuerdoSeleccionado = null;
        $matriz = [];

        if ($idAcuerdo > 0) {
            $acuerdoSeleccionado = $this->listaPrecioModel->obtenerAcuerdo($idAcuerdo);
            if ($acuerdoSeleccionado) {
                $matriz = $this->listaPrecioModel->obtenerMatrizPrecios($idAcuerdo);
            }
        }

        if (!$acuerdoSeleccionado && !empty($acuerdos)) {
            $idAcuerdo = (int)$acuerdos[0]['id'];
            $acuerdoSeleccionado = $this->listaPrecioModel->obtenerAcuerdo($idAcuerdo);
            $matriz = $this->listaPrecioModel->obtenerMatrizPrecios($idAcuerdo);
        }

        $datos = [
            'titulo' => 'Acuerdos Comerciales',
            'acuerdos' => $acuerdos,
            'acuerdo_seleccionado' => $acuerdoSeleccionado,
            'precios_matriz' => $matriz,
        ];

        $this->vista('comercial/listas_precios', $datos);
    }

    public function clientesDisponiblesAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        json_response([
            'success' => true,
            'data' => $this->listaPrecioModel->listarClientesDisponibles()
        ]);
    }

    public function crearLista() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'Método inválido'], 405);
            return;
        }

        $idTercero = (int)($_POST['id_tercero'] ?? 0);
        $observaciones = trim((string)($_POST['observaciones'] ?? ''));

        if ($idTercero <= 0) {
            json_response(['success' => false, 'message' => 'Cliente inválido'], 422);
            return;
        }

        try {
            $id = $this->listaPrecioModel->crearAcuerdo($idTercero, $observaciones !== '' ? $observaciones : null);
            json_response(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            json_response(['success' => false, 'message' => 'No fue posible vincular el cliente.'], 500);
        }
    }

    public function obtenerMatrizAcuerdoAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_GET['id_acuerdo'] ?? 0);
        if ($idAcuerdo <= 0) {
            json_response(['success' => false, 'message' => 'Acuerdo inválido'], 422);
            return;
        }

        $acuerdo = $this->listaPrecioModel->obtenerAcuerdo($idAcuerdo);
        if (!$acuerdo) {
            json_response(['success' => false, 'message' => 'Acuerdo no encontrado'], 404);
            return;
        }

        json_response([
            'success' => true,
            'acuerdo' => $acuerdo,
            'matriz' => $this->listaPrecioModel->obtenerMatrizPrecios($idAcuerdo),
        ]);
    }

    public function presentacionesDisponiblesAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_GET['id_acuerdo'] ?? 0);
        if ($idAcuerdo <= 0) {
            json_response(['success' => false, 'message' => 'Acuerdo inválido'], 422);
            return;
        }

        json_response([
            'success' => true,
            'data' => $this->listaPrecioModel->listarPresentacionesDisponibles($idAcuerdo)
        ]);
    }

    public function agregarProductoAcuerdoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_POST['id_acuerdo'] ?? 0);
        $idPresentacion = (int)($_POST['id_presentacion'] ?? 0);
        $precio = (float)($_POST['precio_pactado'] ?? 0);

        if ($idAcuerdo <= 0 || $idPresentacion <= 0 || $precio <= 0) {
            json_response(['success' => false, 'message' => 'Datos incompletos o inválidos'], 422);
            return;
        }

        try {
            $ok = $this->listaPrecioModel->agregarProductoAcuerdo($idAcuerdo, $idPresentacion, $precio);
            json_response(['success' => $ok]);
        } catch (PDOException $e) {
            json_response(['success' => false, 'message' => 'El producto ya está vinculado al acuerdo.'], 409);
        }
    }

    public function actualizarPrecioPactadoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idDetalle = (int)($_POST['id_detalle'] ?? 0);
        $precio = (float)($_POST['precio_pactado'] ?? -1);

        if ($idDetalle <= 0 || $precio < 0) {
            json_response(['success' => false, 'message' => 'Datos inválidos'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->actualizarPrecioPactado($idDetalle, $precio);
        json_response(['success' => $ok]);
    }

    public function toggleEstadoPrecioAcuerdoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idDetalle = (int)($_POST['id_detalle'] ?? 0);
        $estado = (int)($_POST['estado'] ?? -1);

        if ($idDetalle <= 0 || !in_array($estado, [0, 1], true)) {
            json_response(['success' => false, 'message' => 'Parámetros inválidos'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->actualizarEstadoPrecio($idDetalle, $estado);
        json_response(['success' => $ok]);
    }

    public function eliminarProductoAcuerdoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idDetalle = (int)($_POST['id_detalle'] ?? 0);

        if ($idDetalle <= 0) {
            json_response(['success' => false, 'message' => 'Detalle inválido'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->eliminarProductoAcuerdo($idDetalle);
        json_response(['success' => $ok]);
    }

    public function suspenderAcuerdoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_POST['id_acuerdo'] ?? 0);

        if ($idAcuerdo <= 0) {
            json_response(['success' => false, 'message' => 'Acuerdo inválido'], 422);
            return;
        }

        if ($this->listaPrecioModel->contarPreciosAcuerdo($idAcuerdo) === 0) {
            json_response(['success' => false, 'message' => 'El acuerdo no tiene tarifas para suspender.'], 409);
            return;
        }

        $ok = $this->listaPrecioModel->actualizarEstadoAcuerdo($idAcuerdo, 0);
        json_response(['success' => $ok]);
    }

    public function activarAcuerdoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_POST['id_acuerdo'] ?? 0);

        if ($idAcuerdo <= 0) {
            json_response(['success' => false, 'message' => 'Acuerdo inválido'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->actualizarEstadoAcuerdo($idAcuerdo, 1);
        json_response(['success' => $ok]);
    }

    public function eliminarAcuerdoAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_POST['id_acuerdo'] ?? 0);

        if ($idAcuerdo <= 0) {
            json_response(['success' => false, 'message' => 'Acuerdo inválido'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->eliminarAcuerdo($idAcuerdo);
        json_response(['success' => $ok]);
    }

    // =========================================================================
    // 3. ASIGNACIÓN DE CLIENTES & HELPERS
    // =========================================================================

    public function asignacion() {
        $datos = [
            'titulo' => 'Asignación de Clientes',
            'clientes' => $this->asignacionModel->listarClientes(),
            'listas_combo' => $this->listaPrecioModel->listarAcuerdos()
        ];
        $this->vista('comercial/asignacion_clientes', $datos);
    }

    public function guardarAsignacionAjax() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['id_cliente'])) {
            $idCliente = $input['id_cliente'];
            $idLista = $input['id_lista'];
            if ($this->asignacionModel->actualizarListaCliente($idCliente, $idLista)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error BD']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        }
    }

    private function esPeticionAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}
