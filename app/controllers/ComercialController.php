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

    // --- NUEVO MÉTODO: OBTENER DATOS PARA EDITAR ---
    // Este es el método que tu JavaScript llamará vía AJAX
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
            // Recoger datos del formulario
            // NOTA: Eliminamos 'nombre' de aquí porque ya no existe en la BD
            $datos = [
                'id' => $_POST['id'] ?? null,
                'id_item' => $_POST['id_item'],
                // 'nombre' => ... (ELIMINADO)
                'factor' => $_POST['factor'],
                'precio_x_menor' => $_POST['precio_x_menor'],
                'precio_x_mayor' => $_POST['precio_x_mayor'] ?? null,
                'cantidad_minima_mayor' => $_POST['cantidad_minima_mayor'] ?? null
            ];

            // Validaciones básicas
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

    // ... (El resto del código de Listas y Asignación se mantiene igual, no lo toques) ...
    // ... SOLO COPIA LA PARTE SUPERIOR O ASEGÚRATE DE NO BORRAR LO DE ABAJO ...
    
    // =========================================================================
    // 2. GESTIÓN DE LISTAS DE PRECIOS
    // =========================================================================
    
    public function listas() {
         // ... (Mismo código que tenías)
         $idLista = $_GET['id'] ?? null;
         $datos = [
             'titulo' => 'Listas de Precios',
             'listas' => $this->listaPrecioModel->listarListas(),
             'precios_matriz' => [],
             'lista_seleccionada' => null
         ];
         if ($idLista) {
             foreach ($datos['listas'] as $l) {
                 if ($l['id'] == $idLista) {
                     $datos['lista_seleccionada'] = $l;
                     break;
                 }
             }
             $datos['precios_matriz'] = $this->listaPrecioModel->obtenerMatrizPrecios($idLista);
         }
         $this->vista('comercial/listas_precios', $datos);
    }
    
    public function crearLista() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre']);
            $desc = trim($_POST['descripcion']);
            if ($this->listaPrecioModel->crearLista($nombre, $desc)) {
                redirect('comercial/listas');
            } else {
                redirect('comercial/listas?error=crear');
            }
        }
    }

    public function actualizarPreciosLista() {
         // ... (Mismo código que tenías)
         if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $idLista = $_POST['id_lista'];
            $precios = $_POST['precios'] ?? [];
            foreach ($precios as $idPresentacion => $precio) {
                if ($precio === '' || $precio === null) {
                    $this->listaPrecioModel->eliminarPrecioEspecial($idLista, $idPresentacion);
                } else {
                    $this->listaPrecioModel->guardarPrecioEspecial($idLista, $idPresentacion, $precio);
                }
            }
            redirect('comercial/listas?id=' . $idLista . '&success=actualizado');
        }
    }

    // =========================================================================
    // 3. ASIGNACIÓN DE CLIENTES & HELPERS
    // =========================================================================

    public function asignacion() {
        // ... (Mismo código)
        $datos = [
            'titulo' => 'Asignación de Clientes',
            'clientes' => $this->asignacionModel->listarClientes(),
            'listas_combo' => $this->listaPrecioModel->listarListas() 
        ];
        $this->vista('comercial/asignacion_clientes', $datos);
    }

    public function guardarAsignacionAjax() {
        // ... (Mismo código)
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