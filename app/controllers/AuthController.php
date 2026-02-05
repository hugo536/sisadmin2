<?php
declare(strict_types=1);

class AuthController
{
    public function login(): void
    {
        require BASE_PATH . '/app/views/auth/login.php';
    }
}
