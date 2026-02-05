<?php
declare(strict_types=1);

class DashboardController extends Controlador
{
    public function index(): void
    {
        echo "<h1>Dashboard</h1>";
        echo "<pre>"; print_r($_SESSION); echo "</pre>";
    }
}
