<?php
// app/controladores/ComercialController.php

require_once BASE_PATH . '/app/models/comercial/ListaPrecioModel.php';

class ComercialController extends Controlador {

    private $listaPrecioModel;

    public function __construct() {
        parent::__construct();
        if (!isset($_SESSION['id'])) {
            redirect('login');
        }

        $this->listaPrecioModel = new ListaPrecioModel();
    }

    // =========================================================================
    // ACUERDOS COMERCIALES (MATRIZ DE TARIFAS POR CLIENTE)
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
            'presentaciones_habilitadas' => $this->listaPrecioModel->soportaPresentacionesComerciales(),
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

        if (!$this->listaPrecioModel->soportaPresentacionesComerciales()) {
            json_response([
                'success' => true,
                'data' => [],
                'message' => 'Las presentaciones comerciales fueron retiradas y ya no están disponibles.'
            ]);
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

        if (!$this->listaPrecioModel->soportaPresentacionesComerciales()) {
            json_response([
                'success' => false,
                'message' => 'No se pueden agregar tarifas por presentación porque ese módulo fue retirado.'
            ], 422);
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

    private function esPeticionAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}
