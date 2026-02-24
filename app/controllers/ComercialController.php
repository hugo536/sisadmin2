<?php
// app/controladores/ComercialController.php

require_once BASE_PATH . '/app/models/comercial/PresentacionModel.php';
require_once BASE_PATH . '/app/models/comercial/ListaPrecioModel.php';
require_once BASE_PATH . '/app/models/ItemsModel.php';

class ComercialController extends Controlador {

    private $presentacionModel;
    private $listaPrecioModel;
    private $itemModel;

    public function __construct() {
        parent::__construct();
        if (!isset($_SESSION['id'])) {
            redirect('login');
        }

        $this->presentacionModel = new PresentacionModel();
        $this->listaPrecioModel = new ListaPrecioModel();
        $this->itemModel = new ItemsModel();
    }

    // =========================================================================
    // 1. GESTIÓN DE PRESENTACIONES
    // =========================================================================

    public function presentaciones() {
        $datos = [
            'titulo' => 'Gestión de Presentaciones',
            'items' => $this->presentacionModel->listarProductosParaSelect(),
            'componentes_pack' => $this->presentacionModel->listarComponentesPackParaSelect(),
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
            
            // 1. Recoger bandera de tipo (Mixto o Estándar)
            $esMixto = isset($_POST['es_mixto']) && (int)$_POST['es_mixto'] === 1;
            $detalleMixto = [];

            // 2. Procesar Detalle Mixto (si aplica)
            if (!empty($_POST['detalle_mixto']) && is_array($_POST['detalle_mixto'])) {
                foreach ($_POST['detalle_mixto'] as $linea) {
                    $idItem = (int) ($linea['id_item'] ?? 0);
                    $cantidad = (float) ($linea['cantidad'] ?? 0);
                    if ($idItem > 0 && $cantidad > 0) {
                        $detalleMixto[] = [
                            'id_item' => $idItem,
                            'cantidad' => $cantidad,
                        ];
                    }
                }
            }

            $idsDetalleMixto = array_map(static function ($linea) {
                return (int)($linea['id_item'] ?? 0);
            }, $detalleMixto);

            if (!$this->presentacionModel->sonItemsPermitidosComoComponente($idsDetalleMixto)) {
                redirect('comercial/presentaciones?error=componentes_no_permitidos');
                return;
            }

            // 3. Preparar array de datos para el Modelo
            $datos = [
                'id' => !empty($_POST['id']) ? (int)$_POST['id'] : null,
                'es_mixto' => $esMixto ? 1 : 0,
                
                // Campos Estándar (id_item aquí es el semielaborado base seleccionado)
                'id_item' => !empty($_POST['id_item']) ? (int)$_POST['id_item'] : null,
                'factor' => !empty($_POST['factor']) ? (float)$_POST['factor'] : 0,
                
                // Campos Mixtos / Manuales / Notas
                'nombre_manual' => !empty($_POST['nombre_manual']) ? trim($_POST['nombre_manual']) : null,
                'nota_pack' => !empty($_POST['nota_pack']) ? trim($_POST['nota_pack']) : null, 
                'codigo_presentacion' => !empty($_POST['codigo_presentacion']) ? trim($_POST['codigo_presentacion']) : null,
                'detalle_mixto' => $detalleMixto,
                
                // Precios y Pesos
                'precio_x_menor' => !empty($_POST['precio_x_menor']) ? (float)$_POST['precio_x_menor'] : 0,
                'precio_x_mayor' => !empty($_POST['precio_x_mayor']) ? (float)$_POST['precio_x_mayor'] : null,
                'cantidad_minima_mayor' => !empty($_POST['cantidad_minima_mayor']) ? (int)$_POST['cantidad_minima_mayor'] : null,
                'peso_bruto' => isset($_POST['peso_bruto']) ? (float) $_POST['peso_bruto'] : 0,
                'stock_minimo' => isset($_POST['stock_minimo']) ? (float) $_POST['stock_minimo'] : 0,

                // Configuración Avanzada (Calidad y Fechas)
                'exigir_lote' => isset($_POST['exigir_lote']) ? 1 : 0,
                'requiere_vencimiento' => isset($_POST['requiere_vencimiento']) ? 1 : 0,
                'dias_vencimiento_alerta' => isset($_POST['dias_vencimiento_alerta']) ? (int)$_POST['dias_vencimiento_alerta'] : 0,
            ];

            // 4. VALIDACIONES SERVIDOR

            // Caso A: Presentación Estándar (Requiere Producto Padre y Factor)
            if (!$esMixto) {
                if (empty($datos['id_item']) || $datos['factor'] <= 0) {
                    redirect('comercial/presentaciones?error=campos_vacios_estandar');
                    return;
                }
            } 
            // Caso B: Presentación Mixta (Requiere Detalle)
            else {
                if (empty($detalleMixto)) {
                    redirect('comercial/presentaciones?error=sin_detalle_mixto');
                    return;
                }
            }

            // 5. Enviar al Modelo
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

    private function esPeticionAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}
