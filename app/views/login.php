<?php
$error = isset($error) ? (string) $error : '';
$configEmpresa = is_array($configEmpresa ?? null) ? $configEmpresa : [];
$empresa = trim((string) ($configEmpresa['razon_social'] ?? '')) ?: 'SISADMIN2';
$tema = strtolower(trim((string) ($configEmpresa['tema'] ?? 'light')));
if (!in_array($tema, ['light', 'dark', 'blue'], true)) {
    $tema = 'light';
}
$logoPath = trim((string) ($configEmpresa['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? base_url() . '/' . ltrim($logoPath, '/') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo e($empresa); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/app.css')); ?>">
</head>
<body class="login-page theme-<?php echo e($tema); ?>">
<div class="login-layout container-fluid">
    <div class="row g-0 min-vh-100">
        <aside class="col-lg-6 login-split d-none d-lg-flex">
            <div class="login-brand-wrap">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?php echo e($logoUrl); ?>" alt="Logo de <?php echo e($empresa); ?>" class="login-company-logo mb-4">
                <?php endif; ?>
                <h1 class="h2 fw-bold mb-2"><?php echo e($empresa); ?></h1>
                <p class="mb-0 opacity-75">Bienvenido al entorno corporativo de SISADMIN2. Gestiona la operación con seguridad y productividad.</p>
            </div>
        </aside>

        <main class="col-12 col-lg-6 d-flex align-items-center justify-content-center p-3 p-md-5 login-form-side">
            <div class="card login-card w-100">
                <div class="card-body p-4 p-md-5">
                    <p class="text-uppercase text-muted small mb-2">Acceso seguro</p>
                    <h2 class="h4 mb-4">Iniciar sesión</h2>

                    <form id="login-form" method="post" action="?ruta=login/authenticate" novalidate data-error="<?php echo e($error); ?>">
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Usuario</label>
                            <input id="usuario" name="usuario" type="text" class="form-control" autocomplete="username" required>
                        </div>

                        <div class="mb-4">
                            <label for="clave" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <input id="clave" name="clave" type="password" class="form-control" autocomplete="current-password" required>
                                <button id="toggle-password" class="btn btn-outline-secondary" type="button" aria-label="Mostrar contraseña" aria-pressed="false">Ver</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">Ingresar</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo e(asset_url('js/app.js/login.js')); ?>"></script>
</body>
</html>
