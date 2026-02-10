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
                'sabores' => $this->itemsModel->listarSabores(true),
                'presentaciones' => $this->itemsModel->listarPresentaciones(true),
            ]);
            return;
        }

        $flash = ['tipo' => '', 'texto' => ''];

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
                    if ($skuIngresado !== '' && $skuIngresado !== (string) ($actual['sku'] ?? '')) {
                        throw new RuntimeException('El SKU es inmutable y no puede modificarse.');
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

                if (isset($respuesta) && es_ajax()) {
                    json_response($respuesta);
                    return;
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
            'sabores' => $this->itemsModel->listarSabores(true),
            'sabores_gestion' => $this->itemsModel->listarSabores(),
            'presentaciones' => $this->itemsModel->listarPresentaciones(true),
            'presentaciones_gestion' => $this->itemsModel->listarPresentaciones(),
            'flash' => $flash,
            'ruta_actual' => 'items', 
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
        $tiposPermitidos = ['producto', 'servicio', 'insumo', 'activo', 'gasto'];

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

        return $data;
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
