<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
require_once BASE_PATH . '/app/core/UploadHelper.php';

class EmpresaController extends Controlador
{
    private const MAX_LOGO_SIZE = 2097152; // 2MB
    private const MIME_PERMITIDOS = ['image/png', 'image/jpeg', 'image/webp'];
    private EmpresaModel $empresaModel;

    public function __construct()
    {
        $this->empresaModel = new EmpresaModel();
    }

    public function empresa(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.ver');

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                require_permiso('config.editar');
                $this->procesarGuardado();
                $flash = ['tipo' => 'success', 'texto' => 'Configuración actualizada correctamente.'];

                if (es_ajax()) {
                    json_response([
                        'ok' => true,
                        'mensaje' => 'Configuración actualizada correctamente.',
                        'config' => $this->empresaModel->obtenerConfigActiva(),
                    ]);
                    return;
                }
            } catch (Throwable $e) {
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
                if (es_ajax()) {
                    json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
                    return;
                }
            }
        }

        $this->render('configuracion/empresa', [
            'config' => $this->empresaModel->obtener(),
            'flash' => $flash,
            'ruta_actual' => 'empresa/empresa',
        ]);
    }

    private function procesarGuardado(): void
    {
        $userId = (int) ($_SESSION['id'] ?? 0);
        $actual = $this->empresaModel->obtener();

        if (empty(trim($_POST['razon_social'] ?? ''))) {
            throw new RuntimeException('La razón social es obligatoria.');
        }

        $nuevoLogo = UploadHelper::subirImagen(
            'logo',
            'uploads/config',
            self::MAX_LOGO_SIZE,
            self::MIME_PERMITIDOS,
            (string) ($actual['ruta_logo'] ?? '')
        );

        $datos = [
            'nombre_empresa' => trim((string) ($_POST['razon_social'] ?? '')),
            'ruc'            => preg_replace('/[^0-9]/', '', (string)($_POST['ruc'] ?? '')),
            'direccion'      => trim((string) ($_POST['direccion'] ?? '')),
            'telefono'       => trim((string) ($_POST['telefono'] ?? '')),
            'email'          => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'moneda'         => trim((string) ($_POST['moneda'] ?? 'S/')),
            'impuesto'       => (float) ($_POST['impuesto'] ?? 18),
            'slogan'         => trim((string) ($_POST['slogan'] ?? '')),
            'color_sistema'  => trim((string) ($_POST['color_sistema'] ?? $_POST['tema'] ?? 'light')),
            'ruta_logo'      => $nuevoLogo,
        ];

        $this->empresaModel->guardar($datos, $userId);

        $_SESSION['config_empresa'] = $this->empresaModel->obtenerConfigActiva();

        $this->empresaModel->registrar_bitacora(
            $userId,
            'CONFIG_UPDATE',
            'Actualización datos empresa',
            (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
        );
    }

}
