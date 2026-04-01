<?php
// app/controladores/ComercialController.php

require_once BASE_PATH . '/app/models/comercial/ListaPrecioModel.php';
require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';

class ComercialController extends Controlador {

    private $listaPrecioModel;

    public function __construct() {
        parent::__construct();
        AuthMiddleware::handle();

        $this->listaPrecioModel = new ListaPrecioModel();
    }

    // =========================================================================
    // ACUERDOS COMERCIALES (MATRIZ DE TARIFAS POR CLIENTE)
    // =========================================================================

    public function listas() {
        $idAcuerdo = (int)($_GET['id'] ?? 0);
        $acuerdos = $this->listaPrecioModel->listarAcuerdos();

        $matrizVolumen = $this->listaPrecioModel->obtenerMatrizPreciosVolumen();
        // Magia: extraemos solo los IDs de los ítems y contamos los únicos
        $itemsUnicos = count(array_unique(array_column($matrizVolumen, 'id_item')));

        $tarifaGeneral = [
            'id' => 0,
            'cliente_nombre' => '🌟 Tarifa General (Por Volumen)',
            'total_productos' => $itemsUnicos, // Ahora cuenta productos, no escalas
            'estado' => 1,
            'sin_tarifas' => 0,
            'modo' => 'volumen',
        ];
        array_unshift($acuerdos, $tarifaGeneral);

        $acuerdoSeleccionado = null;
        $matriz = [];

        if ($idAcuerdo === 0) {
            $acuerdoSeleccionado = $tarifaGeneral;
            $matriz = $this->listaPrecioModel->obtenerMatrizPreciosVolumen();
        } elseif ($idAcuerdo > 0) {
            $acuerdoSeleccionado = $this->listaPrecioModel->obtenerAcuerdo($idAcuerdo);
            if ($acuerdoSeleccionado) {
                $matriz = $this->listaPrecioModel->obtenerMatrizPrecios($idAcuerdo);
            }
        }

        if (!$acuerdoSeleccionado && !empty($acuerdos)) {
            $idAcuerdo = (int)$acuerdos[0]['id'];
            if ($idAcuerdo === 0) {
                $acuerdoSeleccionado = $tarifaGeneral;
                $matriz = $this->listaPrecioModel->obtenerMatrizPreciosVolumen();
            } else {
                $acuerdoSeleccionado = $this->listaPrecioModel->obtenerAcuerdo($idAcuerdo);
                $matriz = $this->listaPrecioModel->obtenerMatrizPrecios($idAcuerdo);
            }
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

    public function proveedores() {
        $idAcuerdo = (int)($_GET['id'] ?? 0);
        $acuerdos = $this->listaPrecioModel->listarAcuerdosProveedor();

        $acuerdoSeleccionado = null;
        $matriz = [];

        if ($idAcuerdo > 0) {
            $acuerdoSeleccionado = $this->listaPrecioModel->obtenerAcuerdoProveedor($idAcuerdo);
            if ($acuerdoSeleccionado) {
                $matriz = $this->listaPrecioModel->obtenerMatrizPreciosProveedor($idAcuerdo);
            }
        }

        if (!$acuerdoSeleccionado && !empty($acuerdos)) {
            $idAcuerdo = (int)$acuerdos[0]['id'];
            $acuerdoSeleccionado = $this->listaPrecioModel->obtenerAcuerdoProveedor($idAcuerdo);
            $matriz = $this->listaPrecioModel->obtenerMatrizPreciosProveedor($idAcuerdo);
        }

        $this->vista('comercial/proveedores_precios', [
            'titulo' => 'Acuerdos con Proveedores',
            'acuerdos' => $acuerdos,
            'acuerdo_seleccionado' => $acuerdoSeleccionado,
            'precios_matriz' => $matriz,
        ]);
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

    public function proveedoresDisponiblesAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        json_response([
            'success' => true,
            'data' => $this->listaPrecioModel->listarProveedoresDisponibles(),
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

    public function crearListaProveedor() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['success' => false, 'message' => 'Método inválido'], 405);
            return;
        }

        $idTercero = (int)($_POST['id_tercero'] ?? 0);
        $observaciones = trim((string)($_POST['observaciones'] ?? ''));

        if ($idTercero <= 0) {
            json_response(['success' => false, 'message' => 'Proveedor inválido'], 422);
            return;
        }

        try {
            $id = $this->listaPrecioModel->crearAcuerdoProveedor($idTercero, $observaciones !== '' ? $observaciones : null);
            if ($id <= 0) {
                json_response(['success' => false, 'message' => 'No existe la estructura de acuerdos de proveedor. Ejecuta el script SQL primero.'], 409);
                return;
            }
            json_response(['success' => true, 'id' => $id]);
        } catch (PDOException $e) {
            json_response(['success' => false, 'message' => 'No fue posible vincular el proveedor.'], 500);
        }
    }

    public function obtenerMatrizProveedorAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_GET['id_acuerdo'] ?? 0);
        if ($idAcuerdo <= 0) {
            json_response(['success' => false, 'message' => 'Acuerdo inválido'], 422);
            return;
        }

        $acuerdo = $this->listaPrecioModel->obtenerAcuerdoProveedor($idAcuerdo);
        if (!$acuerdo) {
            json_response(['success' => false, 'message' => 'Acuerdo no encontrado'], 404);
            return;
        }

        json_response([
            'success' => true,
            'acuerdo' => $acuerdo,
            'matriz' => $this->listaPrecioModel->obtenerMatrizPreciosProveedor($idAcuerdo),
        ]);
    }

    public function itemsProveedorDisponiblesAjax() {
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
            'data' => $this->listaPrecioModel->listarItemsDisponiblesAcuerdoProveedor($idAcuerdo),
        ]);
    }

    public function agregarProductoProveedorAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_POST['id_acuerdo'] ?? 0);
        $idItem = (int)($_POST['id_item'] ?? 0);
        
        // NUEVO: Capturar el id_unidad (si viene vacío o 0, lo dejamos como null)
        $idUnidad = !empty($_POST['id_unidad']) ? (int)$_POST['id_unidad'] : null; 
        
        $precio = (float)($_POST['precio_recomendado'] ?? 0);

        if ($idAcuerdo <= 0 || $idItem <= 0 || $precio <= 0) {
            json_response(['success' => false, 'message' => 'Datos incompletos o inválidos'], 422);
            return;
        }

        try {
            // NUEVO: Pasamos la variable $idUnidad a la función del modelo
            $ok = $this->listaPrecioModel->agregarPrecioProveedor($idAcuerdo, $idItem, $idUnidad, $precio);
            if (!$ok) {
                json_response(['success' => false, 'message' => 'No se pudo guardar la recomendación. Verifica si las tablas existen.'], 409);
                return;
            }
            json_response(['success' => $ok]);
        } catch (PDOException $e) {
            // Cambié un poco el mensaje de error para reflejar que evalúa también la unidad
            json_response(['success' => false, 'message' => 'El producto con esa unidad ya está vinculado al proveedor.'], 409);
        }
    }

    public function actualizarPrecioProveedorAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idDetalle = (int)($_POST['id_detalle'] ?? 0);
        $precio = (float)($_POST['precio_recomendado'] ?? -1);
        if ($idDetalle <= 0 || $precio < 0) {
            json_response(['success' => false, 'message' => 'Datos inválidos'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->actualizarPrecioProveedor($idDetalle, $precio);
        json_response(['success' => $ok]);
    }

    public function eliminarPrecioProveedorAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idDetalle = (int)($_POST['id_detalle'] ?? 0);
        if ($idDetalle <= 0) {
            json_response(['success' => false, 'message' => 'Detalle inválido'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->eliminarPrecioProveedor($idDetalle);
        json_response(['success' => $ok]);
    }

    public function precioRecomendadoProveedorAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idProveedor = (int)($_GET['id_proveedor'] ?? 0);
        $idItem = (int)($_GET['id_item'] ?? 0);
        
        // NUEVO: Capturar el id_unidad desde la petición GET
        $idUnidad = !empty($_GET['id_unidad']) ? (int)$_GET['id_unidad'] : null;

        if ($idProveedor <= 0 || $idItem <= 0) {
            json_response(['success' => false, 'message' => 'Parámetros inválidos'], 422);
            return;
        }

        // NUEVO: Pasamos $idUnidad al modelo para que busque el precio de esa presentación
        $precio = $this->listaPrecioModel->obtenerPrecioRecomendadoProveedor($idProveedor, $idItem, $idUnidad);
        
        json_response([
            'success' => true,
            'encontrado' => $precio !== null,
            'precio_recomendado' => $precio,
        ]);
    }

    public function obtenerMatrizAcuerdoAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idAcuerdo = (int)($_GET['id_acuerdo'] ?? 0);
        if ($idAcuerdo === 0) {
            json_response([
                'success' => true,
                'modo' => 'volumen',
                'acuerdo' => [
                    'id' => 0,
                    'cliente_nombre' => '🌟 Tarifa General (Por Volumen)',
                ],
                'matriz' => $this->listaPrecioModel->obtenerMatrizPreciosVolumen(),
            ]);
            return;
        }

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
            'modo' => 'acuerdo',
            'acuerdo' => $acuerdo,
            'matriz' => $this->listaPrecioModel->obtenerMatrizPrecios($idAcuerdo),
        ]);
    }

    public function itemsVolumenDisponiblesAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        json_response([
            'success' => true,
            'data' => $this->listaPrecioModel->listarItemsParaVolumen(),
        ]);
    }

    public function agregarPrecioVolumenAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idItem = (int)($_POST['id_item'] ?? 0);
        $cantidadMinima = (float)($_POST['cantidad_minima'] ?? 0);
        $precioUnitario = (float)($_POST['precio_unitario'] ?? 0);

        if ($idItem <= 0 || $cantidadMinima <= 0 || $precioUnitario <= 0) {
            json_response(['success' => false, 'message' => 'Datos inválidos para la tarifa por volumen.'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->agregarPrecioVolumen($idItem, $cantidadMinima, $precioUnitario, (int)($_SESSION['id'] ?? 0));
        json_response(['success' => $ok]);
    }

    public function actualizarPrecioVolumenAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idDetalle = (int)($_POST['id_detalle'] ?? 0);
        $cantidadMinima = (float)($_POST['cantidad_minima'] ?? 0);
        $precioUnitario = (float)($_POST['precio_unitario'] ?? 0);

        if ($idDetalle <= 0 || $cantidadMinima <= 0 || $precioUnitario < 0) {
            json_response(['success' => false, 'message' => 'Datos inválidos.'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->actualizarPrecioVolumen($idDetalle, $cantidadMinima, $precioUnitario, (int)($_SESSION['id'] ?? 0));
        json_response(['success' => $ok]);
    }

    public function eliminarPrecioVolumenAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idDetalle = (int)($_POST['id_detalle'] ?? 0);
        if ($idDetalle <= 0) {
            json_response(['success' => false, 'message' => 'Registro inválido.'], 422);
            return;
        }

        $ok = $this->listaPrecioModel->eliminarPrecioVolumen($idDetalle);
        json_response(['success' => $ok]);
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

    public function obtenerUnidadesItemAjax() {
        if (!$this->esPeticionAjax()) {
            json_response(['success' => false, 'message' => 'Petición inválida'], 400);
            return;
        }

        $idItem = (int)($_GET['id_item'] ?? 0);
        
        if ($idItem <= 0) {
            json_response(['success' => false, 'message' => 'Item inválido'], 422);
            return;
        }

        // Aquí llamas al modelo donde pusiste la función del Paso 1
        // (Ajusta $this->listaPrecioModel según cómo se llame en tu controlador)
        $unidades = $this->listaPrecioModel->obtenerUnidadesPorItem($idItem);

        json_response([
            'success' => true,
            'data' => $unidades
        ]);
    }
}
