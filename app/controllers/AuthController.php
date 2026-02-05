<?php
declare(strict_types=1);

class AuthController extends Controlador
{
    public function index(): void
    {
        $this->render('auth/login');
    }

    public function login(): void
    {
        $this->index();
    }
}