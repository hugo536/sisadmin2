<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
// CAMBIO: Apunta al archivo en plural
require_once BASE_PATH . '/app/models/ItemsModel.php';

class ItemsController extends Controlador
{
    // CAMBIO: Propiedad en plural
    private ItemsModel $itemsModel;

    public function __construct()
    {
        // CAMBIO: Instancia la clase en plural
        $this->itemsModel = new ItemsModel(); 
    }

    public function index(): void
    {
        
        AuthMiddleware::handle();
        require_permiso('items.ver');

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'datatable') {
            json_response($this->itemsModel->datatable());
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'opciones_atributos_items') {
            json_response([
                'ok' => true,
                'marcas' => $this->itemsModel->listarMarcas(true),
                'sabores' => $this->itemsModel->listarSabores(true),
                'presentaciones' => $this->itemsModel->listarPresentaciones(true),
            ]);
            return;
        }

        if (es_ajax() && (string) ($_POST['accion'] ?? '') === 'toggle_estado_item') {
            require_permiso('items.editar');
            $id = (int) ($_POST['id'] ?? 0);
            $estado = ((int) ($_POST['estado'] ?? 0) === 1) ? 1 : 0;

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
                    $data = $this->normalizarBanderas($_POST);
                    $data = $this->validarItem($data, false);
                    
                    if ($data['sku'] !== '' && $this->itemsModel->skuExiste($data['sku'])) {
                        throw new RuntimeException('El SKU ya se encuentra registrado.');
                    }
                    
                    $nuevoId = $this->itemsModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem creado correctamente.', 'id' => $nuevoId];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem creado correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('items.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    $data = $this->normalizarBanderas($_POST);
                    $data = $this->validarItem($data, true);
                    $actual = $this->itemsModel->obtener($id);
                    
                    if ($actual === []) throw new RuntimeException('El ítem no existe.');

                    $skuIngresado = trim((string) ($data['sku'] ?? ''));
                    if ($skuIngresado !== '' && $skuIngresado !== (string) ($actual['sku'] ?? '') && $this->itemsModel->skuExiste($skuIngresado, $id)) {
                        throw new RuntimeException('El SKU ya se encuentra registrado.');
                    }

                    $this->itemsModel->actualizar($id, $data, $userId);
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
                    $this->itemsModel->crearCategoria($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Categoría creada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Categoría creada correctamente.'];
                }

                if ($accion === 'editar_categoria') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de categoría inválido.');
                    }

                    $data = $this->validarCategoria($_POST);
                    $this->itemsModel->actualizarCategoria($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Categoría actualizada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Categoría actualizada correctamente.'];
                }

                if ($accion === 'eliminar_categoria') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de categoría inválido.');
                    }

                    $this->itemsModel->eliminarCategoria($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Categoría eliminada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Categoría eliminada correctamente.'];
                }

                if ($accion === 'crear_marca') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarAtributoItem($_POST);
                    $id = $this->itemsModel->crearMarca($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Marca creada correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Marca creada correctamente.'];
                }

                if ($accion === 'editar_marca') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de marca inválido.');
                    }
                    $data = $this->validarAtributoItem($_POST);
                    $this->itemsModel->actualizarMarca($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Marca actualizada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Marca actualizada correctamente.'];
                }

                if ($accion === 'eliminar_marca') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de marca inválido.');
                    }
                    $this->itemsModel->eliminarMarca($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Marca eliminada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Marca eliminada correctamente.'];
                }


                if ($accion === 'crear_sabor') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarAtributoItem($_POST);
                    $id = $this->itemsModel->crearSabor($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Sabor creado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Sabor creado correctamente.'];
                }

                if ($accion === 'editar_sabor') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de sabor inválido.');
                    }

                    // --- INICIO BLOQUE DE SEGURIDAD ---
                    // Buscamos si este ID corresponde al registro "Ninguno"
                    $todosLosSabores = $this->itemsModel->listarSabores();
                    foreach ($todosLosSabores as $saborExistente) {
                        if ((int)$saborExistente['id'] === $id && $saborExistente['nombre'] === 'Ninguno') {
                            throw new RuntimeException('Acción denegada: El sabor "Ninguno" es un registro del sistema y no se puede editar.');
                        }
                    }
                    // --- FIN BLOQUE DE SEGURIDAD ---

                    $data = $this->validarAtributoItem($_POST);
                    $this->itemsModel->actualizarSabor($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Sabor actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Sabor actualizado correctamente.'];
                }

                if ($accion === 'eliminar_sabor') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de sabor inválido.');
                    }

                    // --- INICIO BLOQUE DE SEGURIDAD ---
                    // Verificamos antes de intentar eliminar
                    $todosLosSabores = $this->itemsModel->listarSabores();
                    foreach ($todosLosSabores as $saborExistente) {
                        if ((int)$saborExistente['id'] === $id && $saborExistente['nombre'] === 'Ninguno') {
                            throw new RuntimeException('Acción denegada: El sabor "Ninguno" es un registro del sistema protegido.');
                        }
                    }
                    // --- FIN BLOQUE DE SEGURIDAD ---

                    $this->itemsModel->eliminarSabor($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Sabor eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Sabor eliminado correctamente.'];
                }

                if ($accion === 'crear_presentacion') {
                    require_permiso('configuracion.editar');
                    $data = $this->validarAtributoItem($_POST);
                    $id = $this->itemsModel->crearPresentacion($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Presentación creada correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Presentación creada correctamente.'];
                }

                if ($accion === 'editar_presentacion') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de presentación inválido.');
                    }
                    $data = $this->validarAtributoItem($_POST);
                    $this->itemsModel->actualizarPresentacion($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Presentación actualizada correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Presentación actualizada correctamente.'];
                }

                if ($accion === 'eliminar_presentacion') {
                    require_permiso('configuracion.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID de presentación inválido.');
                    }
                    $this->itemsModel->eliminarPresentacion($id, $userId);
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

        $this->render('items', [
            'items' => $this->itemsModel->listar(),
            'categorias' => $this->itemsModel->listarCategoriasActivas(),
            'categorias_gestion' => $this->itemsModel->listarCategorias(),
            'marcas' => $this->itemsModel->listarMarcas(true),
            'marcas_gestion' => $this->itemsModel->listarMarcas(),
            'sabores' => $this->itemsModel->listarSabores(true),
            'sabores_gestion' => $this->itemsModel->listarSabores(),
            'presentaciones' => $this->itemsModel->listarPresentaciones(true),
            'presentaciones_gestion' => $this->itemsModel->listarPresentaciones(),
            'flash' => $flash,
            'ruta_actual' => 'items', 
        ]);
    }


    public function perfil(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

        $idItem = (int) ($_GET['id'] ?? 0);
        if ($idItem <= 0) {
            header('Location: ?ruta=items');
            exit;
        }

        $item = $this->itemsModel->obtenerPerfil($idItem);
        if ($item === []) {
            header('Location: ?ruta=items');
            exit;
        }

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'subir_documento_item') {
                    require_permiso('items.editar');
                    $tipoDoc = trim((string) ($_POST['tipo_documento'] ?? 'OTRO'));

                    if (empty($_FILES['archivo']['name'])) {
                        throw new RuntimeException('No se ha seleccionado ningún archivo.');
                    }

                    $file = $_FILES['archivo'];
                    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
                    if (!in_array($ext, $allowed, true)) {
                        throw new RuntimeException('Formato de archivo no permitido.');
                    }

                    $uploadDir = BASE_PATH . '/public/uploads/items/' . $idItem . '/';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('No se pudo crear el directorio de documentos.');
                    }

                    $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $file['name']);
                    $fileName = uniqid('item_', true) . '_' . ($safeOriginal !== '' ? $safeOriginal : ('archivo.' . $ext));
                    $targetPath = $uploadDir . $fileName;
                    $publicPath = 'uploads/items/' . $idItem . '/' . $fileName;

                    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
                        throw new RuntimeException('Error al guardar el archivo en el servidor.');
                    }

                    $this->itemsModel->guardarDocumento([
                        'id_item' => $idItem,
                        'tipo_documento' => $tipoDoc,
                        'nombre_archivo' => (string) $file['name'],
                        'ruta_archivo' => $publicPath,
                        'extension' => $ext,
                        'created_by' => $userId > 0 ? $userId : null,
                    ]);

                    $_SESSION['items_flash'] = ['tipo' => 'success', 'texto' => 'Documento subido correctamente.'];
                    header('Location: ?ruta=items/perfil&id=' . $idItem . '&tab=documentos');
                    exit;
                }

                if ($accion === 'editar_documento_item') {
                    require_permiso('items.editar');
                    $docId = (int) ($_POST['id_documento'] ?? 0);
                    $tipoDoc = trim((string) ($_POST['tipo_documento'] ?? ''));

                    if ($docId <= 0 || $tipoDoc === '') {
                        throw new RuntimeException('Datos inválidos para editar el documento.');
                    }

                    $this->itemsModel->actualizarDocumento($docId, $tipoDoc);
                    $_SESSION['items_flash'] = ['tipo' => 'success', 'texto' => 'Documento actualizado correctamente.'];
                    header('Location: ?ruta=items/perfil&id=' . $idItem . '&tab=documentos');
                    exit;
                }

                if ($accion === 'eliminar_documento_item') {
                    require_permiso('items.editar');
                    $docId = (int) ($_POST['id_documento'] ?? 0);

                    if ($docId <= 0) {
                        throw new RuntimeException('ID de documento inválido.');
                    }

                    $this->itemsModel->eliminarDocumento($docId);
                    $_SESSION['items_flash'] = ['tipo' => 'success', 'texto' => 'Documento eliminado.'];
                    header('Location: ?ruta=items/perfil&id=' . $idItem . '&tab=documentos');
                    exit;
                }
            } catch (Throwable $e) {
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        if (isset($_SESSION['items_flash']) && is_array($_SESSION['items_flash'])) {
            $flash = [
                'tipo' => (string) ($_SESSION['items_flash']['tipo'] ?? ''),
                'texto' => (string) ($_SESSION['items_flash']['texto'] ?? ''),
            ];
            unset($_SESSION['items_flash']);
        }

        $this->render('items_perfil', [
            'item' => $item,
            'documentos' => $this->itemsModel->listarDocumentos($idItem),
            'flash' => $flash,
            'ruta_actual' => 'items/perfil',
        ]);
    }

    private function normalizarBanderas(array $data): array
    {
        foreach (['controla_stock', 'permite_decimales', 'requiere_lote', 'requiere_vencimiento'] as $flag) {
            $data[$flag] = isset($data[$flag]) ? 1 : 0;
        }

        return $data;
    }

    private function validarItem(array $data, bool $esEdicion): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $tipo = strtolower(trim((string) ($data['tipo_item'] ?? '')));
        $tiposPermitidos = ['producto_terminado', 'materia_prima', 'material_empaque', 'servicio', 'insumo', 'semielaborado'];

        if ($nombre === '' || $tipo === '') {
            throw new RuntimeException('Nombre y tipo de ítem son obligatorios.');
        }

        if (!in_array($tipo, $tiposPermitidos, true)) {
            throw new RuntimeException('El tipo de ítem no es válido.');
        }

        $data['tipo_item'] = $tipo;

        $idCategoria = isset($data['id_categoria']) && $data['id_categoria'] !== ''
            ? (int) $data['id_categoria']
            : 0;

        if ($idCategoria > 0 && !$this->itemsModel->categoriaExisteActiva($idCategoria)) {
            throw new RuntimeException('La categoría seleccionada no existe o está inactiva.');
        }

        $idMarca = isset($data['id_marca']) && $data['id_marca'] !== ''
            ? (int) $data['id_marca']
            : 0;

        $data['id_marca'] = $idMarca > 0 ? $idMarca : null;
        $data['marca'] = null;

        if ($idMarca > 0) {
            foreach ($this->itemsModel->listarMarcas() as $marca) {
                if ((int) ($marca['id'] ?? 0) === $idMarca) {
                    $data['marca'] = trim((string) ($marca['nombre'] ?? ''));
                    break;
                }
            }

            if ($data['marca'] === null) {
                throw new RuntimeException('La marca seleccionada no existe o está inactiva.');
            }
        }

        $esItemDetallado = in_array($tipo, ['producto_terminado', 'semielaborado'], true);

        if ($esItemDetallado) {
            if (empty($data['id_marca']) && empty($data['marca'])) {
                throw new RuntimeException('La marca es obligatoria para ítems de tipo producto terminado o semielaborado.');
            }

            if (empty($data['id_sabor'])) {
                throw new RuntimeException('El sabor es obligatorio para ítems de tipo producto terminado o semielaborado.');
            }

            if (empty($data['id_presentacion'])) {
                throw new RuntimeException('La presentación es obligatoria para ítems de tipo producto terminado o semielaborado.');
            }
        }

        if (!$esItemDetallado) {
            $data['id_sabor'] = null;
            $data['id_presentacion'] = null;
        }


        if ($tipo === 'semielaborado') {
            $data['controla_stock'] = 1;
            $data['requiere_lote'] = 1;
            $data['requiere_vencimiento'] = 1;
            if (!isset($data['permite_decimales'])) {
                $data['permite_decimales'] = 0;
            }
        }

        if ($tipo === 'servicio') {
            $data['id_marca'] = null;
            $data['marca'] = null;
            $data['controla_stock'] = 0;
            $data['stock_minimo'] = 0;
            $data['permite_decimales'] = 0;
            $data['requiere_lote'] = 0;
            $data['requiere_vencimiento'] = 0;
            $data['dias_alerta_vencimiento'] = null;
        }

        if ($tipo === 'materia_prima') {
            $data['permite_decimales'] = 1;
            $data['requiere_lote'] = 1;
        }

        if ($tipo === 'material_empaque') {
            $data['permite_decimales'] = 0;
        }

        if ((int) ($data['controla_stock'] ?? 0) !== 1) {
            $data['stock_minimo'] = 0;
        }

        if ($esItemDetallado && trim((string) ($data['sku'] ?? '')) === '') {
            $data['sku'] = $this->generarSkuProductoTerminado($data);
        }

        return $data;
    }


    private function generarSkuProductoTerminado(array $data): string
    {
        $categoriaId = (int) ($data['id_categoria'] ?? 0);
        $categoriaNombre = '';
        if ($categoriaId > 0) {
            foreach ($this->itemsModel->listarCategorias() as $categoria) {
                if ((int) ($categoria['id'] ?? 0) === $categoriaId) {
                    $categoriaNombre = (string) ($categoria['nombre'] ?? '');
                    break;
                }
            }
        }

        $saborId = (int) ($data['id_sabor'] ?? 0);
        $saborNombre = '';
        if ($saborId > 0) {
            foreach ($this->itemsModel->listarSabores() as $sabor) {
                if ((int) ($sabor['id'] ?? 0) === $saborId) {
                    $saborNombre = (string) ($sabor['nombre'] ?? '');
                    break;
                }
            }
        }

        $presentacionId = (int) ($data['id_presentacion'] ?? 0);
        $presentacionNombre = '';
        if ($presentacionId > 0) {
            foreach ($this->itemsModel->listarPresentaciones() as $presentacion) {
                if ((int) ($presentacion['id'] ?? 0) === $presentacionId) {
                    $presentacionNombre = trim((string) ($presentacion['nombre'] ?? ''));
                    break;
                }
            }
        }

        $marcaNombre = trim((string) ($data['marca'] ?? ''));

        $bloques = [];
        $prefCategoria = $this->prefijoSku($categoriaNombre);
        $prefMarca = $this->prefijoSku($marcaNombre);
        if ($prefCategoria !== '') {
            $bloques[] = $prefCategoria;
        }
        if ($prefMarca !== '') {
            $bloques[] = $prefMarca;
        }

        if (!$this->esSaborOmitible($saborNombre)) {
            $prefSabor = $this->prefijoSku($saborNombre);
            if ($prefSabor !== '') {
                $bloques[] = $prefSabor;
            }
        }

        if ($presentacionNombre !== '') {
            $bloques[] = $presentacionNombre;
        }

        return implode('-', $bloques);
    }

    private function prefijoSku(string $texto): string
    {
        $normalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($normalizado === false) {
            $normalizado = $texto;
        }

        $normalizado = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $normalizado)));
        return substr((string) $normalizado, 0, 2);
    }

    private function esSaborOmitible(string $sabor): bool
    {
        $baseNormalizada = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $sabor);
        if ($baseNormalizada === false) {
            $baseNormalizada = $sabor;
        }

        $base = strtolower(trim((string) $baseNormalizada));
        return $base === '' || $base === 'ninguno' || $base === 'sin sabor';
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
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
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
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }
}
