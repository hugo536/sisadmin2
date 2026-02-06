<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/controllers/AuthController.php';

/**
 * Controlador legacy mantenido por compatibilidad.
 * Redirige toda la lógica al flujo oficial centralizado en AuthController.
 */
class LoginController extends AuthController
{
}
