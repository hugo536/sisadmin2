<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ConfigModel.php';

class ConfigController extends Controlador
{
    private const MAX_LOGO_SIZE = 2097152;
    private const EXTENSIONES_PERMITIDAS = ['png', 'jpg', 'jpeg', 'webp'];

    private ConfigModel $configModel;

    public function __construct()
    {
        $this->configModel = new ConfigModel();
    }

    public function empresa(): void
    {
        AuthMiddleware::handle();

        $mensaje = '';
        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            [$mensaje, $error] = $this->guardar_empresa();
        }

        $config = $this->configModel->obtener_config_activa();

        $this->render('config/empresa', [
            'config' => $config,
            'mensaje' => $mensaje,
            'error' => $error,
            'ruta_actual' => 'config/empresa',
        ]);
    }

    private function guardar_empresa(): array
    {
        $userId = (int) ($_SESSION['id'] ?? 0);

        $configActual = $this->configModel->obtener_config_activa();
        $logoPath = (string) ($configActual['logo_path'] ?? '');

        $nuevaRutaLogo = $this->procesar_logo();
        if ($nuevaRutaLogo === null && isset($_FILES['logo']) && (int) ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return ['', 'No se pudo procesar el logo. Verifica extensión permitida (png/jpg/jpeg/webp) y tamaño máximo de 2MB.'];
        }

        if (is_string($nuevaRutaLogo)) {
            $logoPath = $nuevaRutaLogo;
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
            $this->obtener_ip(),
            $this->obtener_user_agent()
        );

        return ['Configuración de empresa actualizada correctamente.', ''];
    }

    private function procesar_logo(): ?string
    {
        if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
            return null;
        }

        $archivo = $_FILES['logo'];
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            return null;
        }

        $size = (int) ($archivo['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_LOGO_SIZE) {
            return null;
        }

        $nombreOriginal = (string) ($archivo['name'] ?? '');
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return null;
        }

        $directorioRelativo = 'assets/img/empresa';
        $directorioAbsoluto = BASE_PATH . '/public/' . $directorioRelativo;
        if (!is_dir($directorioAbsoluto) && !mkdir($directorioAbsoluto, 0775, true) && !is_dir($directorioAbsoluto)) {
            return null;
        }

        $nombreFinal = 'logo_empresa.' . $extension;
        $rutaAbsoluta = $directorioAbsoluto . '/' . $nombreFinal;

        if (!move_uploaded_file((string) $archivo['tmp_name'], $rutaAbsoluta)) {
            return null;
        }

        return $directorioRelativo . '/' . $nombreFinal;
    }

    private function obtener_ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function obtener_user_agent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    }
}
