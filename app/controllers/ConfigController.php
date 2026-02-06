<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ConfigModel.php';

class ConfigController extends Controlador
{
    private const MAX_LOGO_SIZE = 2097152; // 2MB
    private const MIME_PERMITIDOS = ['image/png', 'image/jpeg', 'image/webp'];
    private ConfigModel $configModel;

    public function __construct()
    {
        $this->configModel = new ConfigModel();
    }

    public function empresa(): void
    {
        AuthMiddleware::handle();
        // require_permiso('config.ver'); // Descomentar si usas sistema de permisos

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                // require_permiso('config.editar'); // Descomentar si usas sistema de permisos
                $this->procesarGuardado();
                $flash = ['tipo' => 'success', 'texto' => 'Configuración actualizada correctamente.'];
            } catch (Throwable $e) {
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('config/empresa', [
            'config' => $this->configModel->obtener(),
            'flash' => $flash,
            'ruta_actual' => 'config/empresa',
        ]);
    }

    private function procesarGuardado(): void
    {
        $userId = (int) ($_SESSION['id'] ?? 0);
        $actual = $this->configModel->obtener();

        // Validaciones simples
        if (empty(trim($_POST['razon_social'] ?? ''))) {
            throw new RuntimeException('La razón social es obligatoria.');
        }

        // Procesar subida de logo
        $nuevoLogo = $this->subirLogo((string)($actual['ruta_logo'] ?? ''));
        
        // MAPEO: Vista (POST) -> Base de Datos
        $datos = [
            'nombre_empresa' => trim((string) ($_POST['razon_social'] ?? '')),
            'ruc'            => preg_replace('/[^0-9]/', '', (string)($_POST['ruc'] ?? '')),
            'direccion'      => trim((string) ($_POST['direccion'] ?? '')),
            'telefono'       => trim((string) ($_POST['telefono'] ?? '')),
            'email'          => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'moneda'         => trim((string) ($_POST['moneda'] ?? 'S/')),
            'impuesto'       => (float) ($_POST['impuesto'] ?? 18),
            'slogan'         => trim((string) ($_POST['slogan'] ?? '')),
            'color_sistema'  => trim((string) ($_POST['tema'] ?? 'light')),
            'ruta_logo'      => $nuevoLogo // Si es null, el modelo lo ignora
        ];

        $this->configModel->guardar($datos, $userId);

        // Actualizar sesión para reflejar cambios inmediatos (opcional)
        $_SESSION['config_empresa'] = $this->configModel->obtener();

        // Bitácora
        $this->configModel->registrar_bitacora(
            $userId, 'CONFIG_UPDATE', 'Actualización datos empresa',
            (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
        );
    }

    private function subirLogo(string $logoAnterior): ?string
    {
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES['logo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error en la subida del archivo.');
        }
        if ($file['size'] > self::MAX_LOGO_SIZE) {
            throw new RuntimeException('El logo excede los 2MB permitidos.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!in_array($finfo->file($file['tmp_name']), self::MIME_PERMITIDOS, true)) {
            throw new RuntimeException('Formato de imagen no válido (solo JPG, PNG, WEBP).');
        }

        $relativeDir = 'uploads/config';
        $absoluteDir = BASE_PATH . '/public/' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) {
            throw new RuntimeException('No se pudo crear el directorio de subida.');
        }

        $filename = 'logo_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $filename)) {
            throw new RuntimeException('Error al guardar el archivo en el servidor.');
        }

        // Borrar anterior
        if (!empty($logoAnterior)) {
            $pathAnterior = BASE_PATH . '/public/' . $logoAnterior;
            if (file_exists($pathAnterior) && is_file($pathAnterior)) {
                @unlink($pathAnterior);
            }
        }

        return $relativeDir . '/' . $filename;
    }
}