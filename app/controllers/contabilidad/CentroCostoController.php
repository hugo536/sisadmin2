<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';

class CentroCostoController extends Controlador
{
    private CentroCostoModel $centroCostoModel;

    public function __construct()
    {
        parent::__construct();
        // Solo inicializamos el modelo que este controlador necesita
        $this->centroCostoModel = new CentroCostoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');

        $this->render('contabilidad/centros_costo', [
            'ruta_actual' => 'contabilidad/centros_costo',
            'centros' => $this->centroCostoModel->listar(),
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');
        
        // 1. Le decimos al navegador que vamos a devolver un JSON, no un HTML
        header('Content-Type: application/json; charset=utf-8');

        // 2. NUEVA MEJORA: Validación del Token CSRF
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        $tokenGuardado = $_SESSION['csrf_token'] ?? '';

        // Comparamos los tokens de forma segura
        if (empty($tokenEnviado) || !hash_equals($tokenGuardado, $tokenEnviado)) {
            http_response_code(403); // Error de prohibido
            echo json_encode([
                'status' => 'error', 
                'message' => 'Sesión caducada o token de seguridad inválido. Por favor, recarga la página.'
            ]);
            exit; // Detenemos la ejecución aquí mismo
        }
        
        // 3. Bloque Try-Catch adaptado para AJAX
        try {
            // Mandamos a guardar los datos al modelo
            $this->centroCostoModel->guardar($_POST, $this->uid());
            
            // Si todo sale bien, devolvemos un JSON de éxito
            echo json_encode([
                'status' => 'success', 
                'message' => 'El Centro de Costo se guardó correctamente.'
            ]);
            exit;

        } catch (Throwable $e) {
            // Si el modelo lanza un error (ej: Código duplicado) lo atrapamos aquí
            http_response_code(400); // Error de solicitud incorrecta
            echo json_encode([
                'status' => 'error', 
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    // Mantenemos la función de ayuda para obtener el ID del usuario
    private function uid(): int
    {
        return (int)($_SESSION['id'] ?? 0);
    }
}