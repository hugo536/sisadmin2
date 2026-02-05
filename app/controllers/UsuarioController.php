<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/UsuarioModel.php';

class UsuariosController extends Controlador
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        
        $mensaje = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'id' => $_POST['id'] ?? null,
                'nombre' => trim($_POST['nombre_completo'] ?? ''),
                'usuario' => trim($_POST['usuario'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'clave' => $_POST['clave'] ?? '',
                'id_rol' => (int)($_POST['id_rol'] ?? 0),
                'estado' => (int)($_POST['estado'] ?? 1)
            ];
            
            if ($this->usuarioModel->guardar($data)) {
                $mensaje = 'Operación realizada correctamente.';
            } else {
                $mensaje = 'Error al guardar. Verifica que el usuario o email no existan ya.';
            }
        }

        $usuarios = $this->usuarioModel->obtener_todos();
        $roles = $this->usuarioModel->obtener_roles();

        // AQUÍ ESTÁ EL CAMBIO: renderiza 'usuario' (busca app/views/usuario.php)
        $this->render('usuario', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'mensaje' => $mensaje,
            'ruta_actual' => 'usuarios'
        ]);
    }
}