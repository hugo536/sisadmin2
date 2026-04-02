<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/items/ItemModel.php';
require_once BASE_PATH . '/app/models/items/RubroModel.php';
require_once BASE_PATH . '/app/models/items/CategoriaModel.php';
require_once BASE_PATH . '/app/models/items/AtributoModel.php';
require_once BASE_PATH . '/app/models/items/UnidadConversionModel.php';

class ItemController extends Controlador
{
    private ItemModel $itemsModel;
    private RubroModel $rubroModel;
    private CategoriaModel $categoriaModel;
    private AtributoModel $atributoModel;
    private UnidadConversionModel $unidadConversionModel;

    public function __construct()
    {
        $this->itemsModel = new ItemModel();
        $this->rubroModel = new RubroModel();
        $this->categoriaModel = new CategoriaModel();
        $this->atributoModel = new AtributoModel();
        $this->unidadConversionModel = new UnidadConversionModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

        // --- INICIO BLOQUE SEGURIDAD CSRF ---
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
        }
        $csrfToken = $_SESSION['csrf_token'];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $postToken = (string) ($_POST['csrf_token'] ?? '');
            if (!hash_equals($csrfToken, $postToken)) {
                if (es_ajax()) {
                    json_response(['ok' => false, 'mensaje' => 'Error de seguridad (CSRF). Recarga la página.'], 403);
                    return;
                }
                die('Error de seguridad (CSRF). Por favor, retrocede y recarga la página.');
            }
        }
        // --- FIN BLOQUE SEGURIDAD CSRF ---

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'validar_sku') {
            $sku = trim((string) ($_GET['sku'] ?? ''));
            if ($sku === '') {
                json_response(['ok' => true, 'existe' => false]);
                return;
            }
            $existe = $this->itemsModel->skuExiste($sku);
            json_response(['ok' => true, 'existe' => $existe]);
            return;
        }

        // Ya no usamos datatable por ajax, pero lo dejamos por si otras vistas lo usan
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'datatable') {
            $pagina = (int) ($_GET['pagina'] ?? 1);
            $limite = (int) ($_GET['limite'] ?? 20);
            
            $filtros = [
                'busqueda' => (string) ($_GET['busqueda'] ?? ''),
                'categoria' => (string) ($_GET['categoria'] ?? ''),
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'estado' => (string) ($_GET['estado'] ?? ''),
            ];

            json_response($this->itemsModel->datatable($pagina, $limite, $filtros));
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'opciones_atributos_items') {
            json_response([
                'ok' => true,
                'rubros' => $this->rubroModel->listarRubrosActivos(),
                'marcas' => $this->atributoModel->listarMarcas(true),
                'sabores' => $this->atributoModel->listarSabores(true),
                'presentaciones' => $this->atributoModel->listarPresentaciones(true),
            ]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar_unidades_conversion') {
            json_response([
                'ok' => true,
                'items' => $this->unidadConversionModel->listarUnidadesConversion(),
            ]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar_detalle_unidades_conversion') {
            require_permiso('items.ver');
            $idItem = (int) ($_GET['id_item'] ?? 0);
            json_response([
                'ok' => true,
                'items' => $this->unidadConversionModel->listarDetalleUnidadesConversion($idItem),
            ]);
            return;
        }

        if (es_ajax() && (string) ($_POST['accion'] ?? '') === 'toggle_estado_item') {
            require_permiso('items.editar');
            $id = (int) ($_POST['id'] ?? 0);
            $estado = in_array($_POST['estado'] ?? 0, [1, '1', 'on', true], true) ? 1 : 0;

            if ($id <= 0) {
                json_response(['ok' => false, 'mensaje' => 'ID inválido.'], 400);
                return;
            }

            $userId = (int) ($_SESSION['id'] ?? 0);
            $this->itemsModel->actualizarEstado($id, $estado, $userId);
            json_response(['ok' => true, 'mensaje' => 'Estado actualizado.']);
            return;
        }

        $flash = ['tipo' => '', 'texto' => ''];
        if (isset($_SESSION['items_flash']) && is_array($_SESSION['items_flash'])) {
            $flash = [
                'tipo' => (string) ($_SESSION['items_flash']['tipo'] ?? ''),
                'texto' => (string) ($_SESSION['items_flash']['texto'] ?? ''),
            ];
            unset($_SESSION['items_flash']);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'crear') {
                    require_permiso('items.crear');
                    $data = $this->validarItem($_POST, false);
                    
                    $autoIdentidad = (isset($_POST['autogenerar_identidad']) && $_POST['autogenerar_identidad'] == '1');
                    
                    if ($data['sku'] !== '') {
                        if ($autoIdentidad) {
                            $baseSku = $data['sku'];
                            $contador = 1;
                            while ($this->itemsModel->skuExiste($data['sku'])) {
                                $data['sku'] = $baseSku . '-' . str_pad((string)$contador, 2, '0', STR_PAD_LEFT);
                                $contador++;
                            }
                        } else {
                            if ($this->itemsModel->skuExiste($data['sku'])) {
                                throw new RuntimeException('El SKU ya se encuentra registrado. Por favor, escribe uno diferente.');
                            }
                        }
                    }
                    
                    $nuevoId = $this->itemsModel->crear($data, $userId);
                    $this->itemsModel->sincronizarDependenciasConfiguracion($nuevoId, $data, $userId);
                    
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem creado correctamente.', 'id' => $nuevoId];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem creado correctamente.'];
                }

                if ($accion === 'crear_item_unidad_conversion') {
                    require_permiso('items.editar');
                    $data = $this->validarUnidadConversion($_POST);
                    $id = $this->unidadConversionModel->crearUnidadConversion($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Unidad de conversión creada correctamente.', 'id' => $id];
                }

                if ($accion === 'editar_item_unidad_conversion') {
                    require_permiso('items.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID inválido.');
                    }
                    $data = $this->validarUnidadConversion($_POST);
                    $this->unidadConversionModel->actualizarUnidadConversion($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Unidad de conversión actualizada correctamente.'];
                }

                if ($accion === 'eliminar_item_unidad_conversion') {
                    require_permiso('items.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    $idItem = (int) ($_POST['id_item'] ?? 0);
                    if ($id <= 0 || $idItem <= 0) {
                        throw new RuntimeException('Parámetros inválidos.');
                    }
                    $this->unidadConversionModel->eliminarUnidadConversion($id, $idItem, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Unidad de conversión eliminada correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('items.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    $actual = $this->itemsModel->obtener($id);
                    if ($actual === []) throw new RuntimeException('El ítem no existe.');

                    $data = $this->validarItem($_POST, true);
                    
                    if (!isset($data['estado'])) {
                        $data['estado'] = (int) ($actual['estado'] ?? 1);
                    }

                    unset($data['sku']);
                    $data['unidad_base'] = (string) ($actual['unidad_base'] ?? 'UND');

                    $this->itemsModel->actualizar($id, $data, $userId);
                    $this->itemsModel->sincronizarDependenciasConfiguracion($id, $data, $userId);
                    
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    require_permiso('items.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    $this->itemsModel->eliminar($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem eliminado correctamente.'];
                }

                if ($accion === 'crear_categoria') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarCategoria($_POST);
                    $this->categoriaModel->crearCategoria($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Categoría creada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Categoría creada correctamente.'];
                }

                if ($accion === 'crear_rubro') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarRubro($_POST);
                    $this->rubroModel->crearRubro($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Rubro creado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Rubro creado correctamente.'];
                }

                if ($accion === 'editar_rubro') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de rubro inválido.');

                    $data = $this->validarRubro($_POST);
                    $this->rubroModel->actualizarRubro($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Rubro actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Rubro actualizado correctamente.'];
                }

                if ($accion === 'eliminar_rubro') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de rubro inválido.');

                    $this->rubroModel->eliminarRubro($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Rubro eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Rubro eliminado correctamente.'];
                }

                if ($accion === 'editar_categoria') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de categoría inválido.');

                    $data = $this->validarCategoria($_POST);
                    $this->categoriaModel->actualizarCategoria($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Categoría actualizada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Categoría actualizada correctamente.'];
                }

                if ($accion === 'eliminar_categoria') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de categoría inválido.');

                    $this->categoriaModel->eliminarCategoria($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Categoría eliminada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Categoría eliminada correctamente.'];
                }

                if ($accion === 'crear_marca') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarAtributoItem($_POST);
                    $id = $this->atributoModel->crearMarca($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Marca creada correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Marca creada correctamente.'];
                }

                if ($accion === 'editar_marca') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de marca inválido.');
                    
                    $data = $this->validarAtributoItem($_POST);
                    $this->atributoModel->actualizarMarca($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Marca actualizada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Marca actualizada correctamente.'];
                }

                if ($accion === 'eliminar_marca') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de marca inválido.');
                    
                    $this->atributoModel->eliminarMarca($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Marca eliminada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Marca eliminada correctamente.'];
                }

                if ($accion === 'crear_sabor') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarAtributoItem($_POST);
                    $id = $this->atributoModel->crearSabor($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Sabor creado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Sabor creado correctamente.'];
                }

                if ($accion === 'editar_sabor') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de sabor inválido.');

                    $todosLosSabores = $this->atributoModel->listarSabores();
                    foreach ($todosLosSabores as $saborExistente) {
                        if ((int)$saborExistente['id'] === $id && $saborExistente['nombre'] === 'Ninguno') {
                            throw new RuntimeException('Acción denegada: El sabor "Ninguno" es un registro del sistema y no se puede editar.');
                        }
                    }

                    $data = $this->validarAtributoItem($_POST);
                    $this->atributoModel->actualizarSabor($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Sabor actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Sabor actualizado correctamente.'];
                }

                if ($accion === 'eliminar_sabor') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de sabor inválido.');

                    $todosLosSabores = $this->atributoModel->listarSabores();
                    foreach ($todosLosSabores as $saborExistente) {
                        if ((int)$saborExistente['id'] === $id && $saborExistente['nombre'] === 'Ninguno') {
                            throw new RuntimeException('Acción denegada: El sabor "Ninguno" es un registro del sistema protegido.');
                        }
                    }

                    $this->atributoModel->eliminarSabor($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Sabor eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Sabor eliminado correctamente.'];
                }

                if ($accion === 'crear_presentacion') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarAtributoItem($_POST);
                    $id = $this->atributoModel->crearPresentacion($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Presentación creada correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Presentación creada correctamente.'];
                }

                if ($accion === 'editar_presentacion') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de presentación inválido.');
                    
                    $data = $this->validarAtributoItem($_POST);
                    $this->atributoModel->actualizarPresentacion($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Presentación actualizada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Presentación actualizada correctamente.'];
                }

                if ($accion === 'eliminar_presentacion') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID de presentación inválido.');
                    
                    $this->atributoModel->eliminarPresentacion($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Presentación eliminada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Presentación eliminada correctamente.'];
                }

                if (isset($respuesta)) {
                    if (es_ajax()) {
                        json_response($respuesta);
                        return;
                    }

                    $_SESSION['items_flash'] = $flash;
                    header('Location: ' . (string) ($_SERVER['REQUEST_URI'] ?? '?ruta=items'));
                    exit;
                }
            } catch (Throwable $e) {
                if (es_ajax()) {
                    json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
                    return;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('items/index', [
            // ESTA ES LA LÍNEA QUE FALTABA
            'items' => $this->itemsModel->listarTodos(),

            'rubros' => $this->rubroModel->listarRubrosActivos(),
            'rubros_gestion' => $this->rubroModel->listarRubros(),
            'categorias' => $this->categoriaModel->listarCategoriasActivas(),
            'categorias_gestion' => $this->categoriaModel->listarCategorias(),
            'marcas' => $this->atributoModel->listarMarcas(true),
            'marcas_gestion' => $this->atributoModel->listarMarcas(),
            'sabores' => $this->atributoModel->listarSabores(true),
            'sabores_gestion' => $this->atributoModel->listarSabores(),
            'presentaciones' => $this->atributoModel->listarPresentaciones(true),
            'presentaciones_gestion' => $this->atributoModel->listarPresentaciones(),
            'unidades_conversion' => $this->unidadConversionModel->listarUnidadesConversion(),
            'flash' => $flash,
            'ruta_actual' => 'items',
            'csrf_token' => $csrfToken,
        ]);
    }

    private function validarItem(array $data, bool $esEdicion): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del ítem es obligatorio.');
        }

        $tipo = strtolower(trim((string) ($data['tipo_item'] ?? '')));
        $tiposPermitidos = ['producto_terminado', 'materia_prima', 'material_empaque', 'servicio', 'insumo', 'semielaborado'];

        if ($tipo === '') {
            throw new RuntimeException('El tipo de ítem es obligatorio.');
        }

        if (!in_array($tipo, $tiposPermitidos, true)) {
            throw new RuntimeException('El tipo de ítem no es válido.');
        }

        $idCategoria = isset($data['id_categoria']) && $data['id_categoria'] !== '' ? (int) $data['id_categoria'] : 0;
        $idRubro = isset($data['id_rubro']) && $data['id_rubro'] !== '' ? (int) $data['id_rubro'] : 0;

        if ($idRubro <= 0) throw new RuntimeException('El rubro es obligatorio.');
        if ($idCategoria <= 0) throw new RuntimeException('La categoría es obligatoria.');

        if (!$this->rubroModel->rubroExisteActivo($idRubro)) {
            throw new RuntimeException('El rubro seleccionado no existe o está inactivo.');
        }
        if (!$this->categoriaModel->categoriaExisteActiva($idCategoria)) {
            throw new RuntimeException('La categoría seleccionada no existe o está inactiva.');
        }

        // --- 1. Reglas Generales Base ---
        // Evaluamos correctamente si viene "on", "1", 1, etc.
        $controla_stock_crudo = $data['controla_stock'] ?? 0;
        $activa_stock = in_array($controla_stock_crudo, [1, '1', 'on', true], true);

        if (!$activa_stock) {
            $data['controla_stock'] = 0;
            $data['stock_minimo'] = 0;
        } else {
            // Pasamos el valor crudo para que tu Modelo (mapPayload) lo procese correctamente
            $data['controla_stock'] = $controla_stock_crudo;
        }

        // --- 2. Normalización y limpieza inicial de atributos opcionales ---
        $idMarca = 0;
        $idSabor = isset($data['id_sabor']) && $data['id_sabor'] !== '' ? (int) $data['id_sabor'] : 0;
        $idPresentacion = isset($data['id_presentacion']) && $data['id_presentacion'] !== '' ? (int) $data['id_presentacion'] : 0;

        // CAPTURA CRUDA: Guardamos exactamente lo que envía el formulario ('on' o '1')
        $post_formula_bom = $data['requiere_formula_bom'] ?? 0;
        $post_envase      = $data['es_envase_retornable'] ?? 0;
        $post_lote        = $data['requiere_lote'] ?? 0;
        $post_vcto        = $data['requiere_vencimiento'] ?? 0;
        $post_decimales   = $data['permite_decimales'] ?? 0;
        
        // ¡NUEVO!: También protegemos el factor de conversión del casteo (int)
        $post_factor_conv = $data['requiere_factor_conversion'] ?? 0;

        $data['marca'] = null;
        $data['id_sabor'] = null;
        $data['id_presentacion'] = null;
        
        // Limpieza de seguridad por defecto
        $data['requiere_formula_bom'] = 0; 
        $data['es_envase_retornable'] = 0;
        $data['requiere_lote'] = 0;
        $data['requiere_vencimiento'] = 0;
        $data['permite_decimales'] = 0;
        
        // Asignamos su valor crudo en vez del casteo (int)
        $data['requiere_factor_conversion'] = $post_factor_conv;

        // --- 3. Aplicación estricta de reglas según tipo ---
        switch ($tipo) {
            case 'producto_terminado':
                $idMarca = isset($data['id_marca']) && $data['id_marca'] !== '' ? (int) $data['id_marca'] : 0;
                if (empty($idMarca)) throw new RuntimeException('La marca es obligatoria para productos terminados.');

                if (!$esEdicion) {
                    $data['controla_stock'] = 1;
                }

                $data['id_sabor'] = $idSabor > 0 ? $idSabor : null;
                if (empty($data['id_sabor'])) throw new RuntimeException('El sabor es obligatorio para productos terminados.');

                $data['id_presentacion'] = $idPresentacion > 0 ? $idPresentacion : null;
                if (empty($data['id_presentacion'])) throw new RuntimeException('La presentación es obligatoria para productos terminados.');

                // Devolvemos el valor crudo capturado
                $data['requiere_formula_bom'] = $post_formula_bom;
                $data['es_envase_retornable'] = $post_envase;
                $data['permite_decimales']    = $post_decimales;
                $data['requiere_lote']        = $post_lote;
                $data['requiere_vencimiento'] = $post_vcto;
                break;

            case 'semielaborado':
                $data['id_sabor'] = $idSabor > 0 ? $idSabor : null;
                
                $data['requiere_formula_bom'] = $post_formula_bom;
                $data['permite_decimales']    = $post_decimales;
                
                if (!$esEdicion) {
                    $data['controla_stock'] = 1;
                    $data['requiere_lote'] = 1;
                    $data['requiere_vencimiento'] = 1;
                } else {
                    $data['requiere_lote'] = $post_lote;
                    $data['requiere_vencimiento'] = $post_vcto;
                }
                break;

            case 'materia_prima':
                $data['permite_decimales']    = $post_decimales;
                $data['requiere_lote']        = $post_lote;
                $data['requiere_vencimiento'] = $post_vcto;
                break;

            case 'material_empaque':
                if (!$esEdicion) {
                    $data['controla_stock'] = 1;
                }
                $data['es_envase_retornable'] = $post_envase;
                $data['permite_decimales'] = 0; 
                break;

            case 'insumo':
                $data['permite_decimales'] = 0; 
                break;
                
            case 'servicio':
                $data['controla_stock'] = 0;
                $data['stock_minimo'] = 0;
                $data['permite_decimales'] = 0;
                $data['dias_alerta_vencimiento'] = null;
                break;
        }

        // --- 4. Validación de Marca ---
        if ($idMarca > 0) {
            foreach ($this->atributoModel->listarMarcas() as $marca) {
                if ((int) ($marca['id'] ?? 0) === $idMarca) {
                    $data['marca'] = trim((string) ($marca['nombre'] ?? ''));
                    break;
                }
            }
            if ($data['marca'] === null) {
                throw new RuntimeException('La marca seleccionada no existe o está inactiva.');
            }
        }

        if (!isset($data['sku']) || trim($data['sku']) === '') {
             $data['sku'] = '';
        }

        $pesoKgRaw = trim((string) ($data['peso_kg'] ?? ''));
        if ($pesoKgRaw === '') {
            $data['peso_kg'] = 0;
        } else {
            $pesoKg = (float) $pesoKgRaw;
            if ($pesoKg < 0) {
                throw new RuntimeException('El peso del ítem no puede ser negativo.');
            }
            $data['peso_kg'] = round($pesoKg, 3);
        }

        return $data;
    }

    private function validarUnidadConversion(array $data): array
    {
        $idItem = (int) ($data['id_item'] ?? 0);
        if ($idItem <= 0) {
            throw new RuntimeException('Debe seleccionar un ítem válido.');
        }

        $item = $this->itemsModel->obtener($idItem);
        if ($item === []) {
            throw new RuntimeException('El ítem seleccionado no existe.');
        }

        if ((int) ($item['requiere_factor_conversion'] ?? 0) !== 1) {
            throw new RuntimeException('El ítem no tiene habilitado factor de conversión.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la unidad es obligatorio.');
        }

        $factor = (float) ($data['factor_conversion'] ?? 0);
        if ($factor <= 0) {
            throw new RuntimeException('El factor de conversión debe ser mayor a 0.');
        }

        $pesoKg = (float) ($data['peso_kg'] ?? 0);
        if ($pesoKg < 0) {
            throw new RuntimeException('El peso no puede ser negativo.');
        }

        return [
            'id_item' => $idItem,
            'nombre' => $nombre,
            'codigo_unidad' => $this->generarCodigoUnidadConversion($item, $nombre),
            'factor_conversion' => round($factor, 4),
            'peso_kg' => round($pesoKg, 3),
            'estado' => in_array($data['estado'] ?? 1, [1, '1', 'on', true], true) ? 1 : 0,
        ];
    }

    private function generarCodigoUnidadConversion(array $item, string $nombreUnidad): string
    {
        $skuBase = strtoupper(trim((string) ($item['sku'] ?? 'UND')));
        if ($skuBase === '') {
            $skuBase = 'UND';
        }

        $normalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombreUnidad);
        if ($normalizado === false) {
            $normalizado = $nombreUnidad;
        }

        $slug = strtoupper((string) $normalizado);
        $slug = preg_replace('/[^A-Z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = preg_replace('/-{2,}/', '-', $slug) ?? '';
        if ($slug === '') {
            $slug = 'UNIDAD';
        }

        return substr($skuBase . '-' . substr($slug, 0, 24), 0, 40);
    }

    private function validarCategoria(array $data): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la categoría es obligatorio.');
        }

        return [
            'nombre' => $nombre,
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'estado' => isset($data['estado']) ? (in_array($data['estado'], [1, '1', 'on', true], true) ? 1 : 0) : 1,
        ];
    }

    private function validarAtributoItem(array $data): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre es obligatorio.');
        }

        return [
            'nombre' => $nombre,
            'estado' => isset($data['estado']) ? (in_array($data['estado'], [1, '1', 'on', true], true) ? 1 : 0) : 1,
        ];
    }

    private function validarRubro(array $data): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del rubro es obligatorio.');
        }

        return [
            'nombre' => $nombre,
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'estado' => isset($data['estado']) ? (in_array($data['estado'], [1, '1', 'on', true], true) ? 1 : 0) : 1,
        ];
    }
}
