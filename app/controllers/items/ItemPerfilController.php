<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/items/ItemPerfilModel.php';

class ItemPerfilController extends Controlador
{
    private ItemPerfilModel $itemPerfilModel;

    public function __construct()
    {
        $this->itemPerfilModel = new ItemPerfilModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

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

        $idItem = (int) ($_GET['id'] ?? 0);
        if ($idItem <= 0) {
            header('Location: ?ruta=items');
            exit;
        }

        $item = $this->itemPerfilModel->obtenerPerfil($idItem);
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

                    $this->itemPerfilModel->guardarDocumento([
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

                    $this->itemPerfilModel->actualizarDocumento($docId, $tipoDoc);
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

                    $this->itemPerfilModel->eliminarDocumento($docId);
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

        $this->render('items/perfil', [
            'item' => $item,
            'documentos' => $this->itemPerfilModel->listarDocumentos($idItem),
            'flash' => $flash,
            'ruta_actual' => 'items/perfil',
            'csrf_token' => $csrfToken,
        ]);
    }
}
