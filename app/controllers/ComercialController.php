<?php
// app/controladores/ComercialController.php

require_once 'app/modelos/comercial/PresentacionModel.php';
require_once 'app/modelos/comercial/ListaPrecioModel.php';
require_once 'app/modelos/comercial/AsignacionModel.php';
require_once 'app/modelos/ItemModel.php'; // Necesitamos cargar items para los selects

class ComercialController extends Controlador {

    private $presentacionModel;
    private $listaPrecioModel;
    private $asignacionModel;
    private $itemModel;

    public function __construct() {
        parent::__construct();
        // Verificar sesión y permisos generales
        if (!isset($_SESSION['id_usuario'])) {
            redirect('login');
        }

        // Instanciar modelos
        $this->presentacionModel = new PresentacionModel();
        $this->listaPrecioModel = new ListaPrecioModel();
        $this->asignacionModel = new AsignacionModel();
        $this->itemModel = new ItemModel();
    }

    // =========================================================================
    // 1. GESTIÓN DE PRESENTACIONES (PACKS Y CAJAS)
    // =========================================================================
    
    public function presentaciones() {
        // Cargar datos para la vista
        $datos = [
            'titulo' => 'Gestión de Presentaciones',
            'items' => $this->itemModel->obtenerTodos(), // Para el select de productos
            'presentaciones' => $this->presentacionModel->listarTodo()
        ];
        
        $this->vista('comercial/presentaciones', $datos);
    }

    public function guardarPresentacion() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Recoger datos del formulario
            $datos = [
                'id' => $_POST['id'] ?? null,
                'id_item' => $_POST['id_item'],
                'nombre' => trim($_POST['nombre']),
                'factor' => $_POST['factor'],
                'precio_x_menor' => $_POST['precio_x_menor'],
                'precio_x_mayor' => $_POST['precio_x_mayor'] ?? null,
                'cantidad_minima_mayor' => $_POST['cantidad_minima_mayor'] ?? null
            ];

            // Validaciones básicas
            if (empty($datos['id_item']) || empty($datos['nombre']) || empty($datos['factor'])) {
                // Aquí podrías usar una alerta de sesión (Flash Message)
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

    public function eliminarPresentacion($id) {
        if ($this->presentacionModel->eliminar($id)) {
            // Respuesta JSON si usas AJAX, o redirect si es link normal
            if ($this->esPeticionAjax()) {
                echo json_encode(['success' => true]);
            } else {
                redirect('comercial/presentaciones');
            }
        } else {
            if ($this->esPeticionAjax()) echo json_encode(['success' => false]);
        }
    }

    // =========================================================================
    // 2. GESTIÓN DE LISTAS DE PRECIOS
    // =========================================================================

    public function listas() {
        $idLista = $_GET['id'] ?? null;
        
        $datos = [
            'titulo' => 'Listas de Precios',
            'listas' => $this->listaPrecioModel->listarListas(),
            'precios_matriz' => [],
            'lista_seleccionada' => null
        ];

        // Si seleccionaron una lista, cargamos su detalle
        if ($idLista) {
            // Buscar nombre de la lista seleccionada
            foreach ($datos['listas'] as $l) {
                if ($l['id'] == $idLista) {
                    $datos['lista_seleccionada'] = $l;
                    break;
                }
            }
            // Cargar la matriz de precios
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $idLista = $_POST['id_lista'];
            $precios = $_POST['precios'] ?? []; // Array [id_presentacion => precio]

            foreach ($precios as $idPresentacion => $precio) {
                if ($precio === '' || $precio === null) {
                    // Si está vacío, borrar precio especial (volver al base)
                    $this->listaPrecioModel->eliminarPrecioEspecial($idLista, $idPresentacion);
                } else {
                    // Guardar o actualizar
                    $this->listaPrecioModel->guardarPrecioEspecial($idLista, $idPresentacion, $precio);
                }
            }
            
            redirect('comercial/listas?id=' . $idLista . '&success=actualizado');
        }
    }

    // =========================================================================
    // 3. ASIGNACIÓN DE CLIENTES
    // =========================================================================

    public function asignacion() {
        $datos = [
            'titulo' => 'Asignación de Clientes',
            'clientes' => $this->asignacionModel->listarClientes(),
            'listas_combo' => $this->listaPrecioModel->listarListas() // Para el select
        ];

        $this->vista('comercial/asignacion_clientes', $datos);
    }

    // Método AJAX para cambio rápido
    public function guardarAsignacionAjax() {
        // Leer input JSON
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['id_cliente'])) {
            $idCliente = $input['id_cliente'];
            $idLista = $input['id_lista']; // Puede ser null

            if ($this->asignacionModel->actualizarListaCliente($idCliente, $idLista)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error BD']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        }
    }
    
    // Helper para detectar AJAX
    private function esPeticionAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';

class ComercialController extends Controlador
{
    public function index(): void
    {
        AuthMiddleware::handle();
        $this->presentaciones();
    }

    public function presentaciones(): void
    {
        AuthMiddleware::handle();
        $this->render('shared/construccion', [
            'ruta_actual' => 'comercial/presentaciones',
            'destino' => 'Gestión Comercial / Presentaciones y Packs',
        ]);
    }

    public function listas(): void
    {
        AuthMiddleware::handle();
        $this->render('shared/construccion', [
            'ruta_actual' => 'comercial/listas',
            'destino' => 'Gestión Comercial / Listas de Precios',
        ]);
    }

    public function asignacion(): void
    {
        AuthMiddleware::handle();
        $this->render('shared/construccion', [
            'ruta_actual' => 'comercial/asignacion',
            'destino' => 'Gestión Comercial / Asignación Masiva',
        ]);
    }
}
