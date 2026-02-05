<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ConfigModel.php';

class ConfigController extends Controlador
{
    private const MAX_LOGO_SIZE = 2097152;
    private const EXTENSIONES_PERMITIDAS = ['png', 'jpg', 'jpeg', 'webp'];
    private const MIME_PERMITIDOS = ['image/png', 'image/jpeg', 'image/webp'];

    private ConfigModel $configModel;

    public function __construct()
    {
        $this->configModel = new ConfigModel();
    }

    public function empresa(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.empresa.ver');

        $flash = ['tipo' => '', 'texto' => ''];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                $this->guardar();
                $flash = ['tipo' => 'success', 'texto' => 'Configuración actualizada correctamente.'];
            } catch (Throwable $e) {
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('config/empresa', [
            'config' => $this->configModel->obtener_config_activa(),
            'flash' => $flash,
            'ruta_actual' => 'config/empresa',
        ]);
    }

    private function guardar(): void
    {
        $userId = (int) ($_SESSION['id'] ?? 0);
        $actual = $this->configModel->obtener_config_activa();

        $logoPath = (string) ($actual['logo_path'] ?? '');
        $nuevoLogo = $this->procesarLogo();
        if ($nuevoLogo !== null) {
            $logoPath = $nuevoLogo;
        }

        $data = [
            'razon_social' => trim((string) ($_POST['razon_social'] ?? '')),
            'ruc' => trim((string) ($_POST['ruc'] ?? '')),
            'direccion' => trim((string) ($_POST['direccion'] ?? '')),
            'telefono' => trim((string) ($_POST['telefono'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'tema' => trim((string) ($_POST['tema'] ?? '')),
            'moneda' => trim((string) ($_POST['moneda'] ?? '')),
            'logo_path' => $logoPath,
        ];

        $this->configModel->guardar_config($data, $userId);
        $this->configModel->registrar_bitacora(
            $userId,
            'CONFIG_EMPRESA_UPDATE',
            'Actualización datos empresa',
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
        );
    }

    private function procesarLogo(): ?string
    {
        if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
            return null;
        }

        $file = $_FILES['logo'];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir el logo.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_LOGO_SIZE) {
            throw new RuntimeException('Logo inválido: tamaño máximo 2MB.');
        }

        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES_PERMITIDAS, true)) {
            throw new RuntimeException('Formato de logo no permitido.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, (string) $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!in_array($mime, self::MIME_PERMITIDOS, true)) {
            throw new RuntimeException('El archivo no es una imagen válida.');
        }

        $relativeDir = 'assets/img/empresa';
        $absoluteDir = BASE_PATH . '/public/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('No se pudo crear carpeta de logo.');
        }

        foreach (glob($absoluteDir . '/logo_empresa.*') ?: [] as $old) {
            @unlink($old);
        }

        $filename = 'logo_empresa.' . $ext;
        $absolutePath = $absoluteDir . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            throw new RuntimeException('No se pudo guardar el logo.');
        }

        return $relativeDir . '/' . $filename;
    }
}
