<?php
$error = isset($error) ? (string) $error : '';
$configEmpresa = is_array($configEmpresa ?? null) ? $configEmpresa : [];
$empresa = trim((string) ($configEmpresa['razon_social'] ?? '')) ?: 'SISADMIN2';
$logoPath = trim((string) ($configEmpresa['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? base_url() . '/' . ltrim($logoPath, '/') : '';
$temaSistema = strtolower((string) ($configEmpresa['color_sistema'] ?? 'light'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo htmlspecialchars($empresa); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo asset_url('css/login.css'); ?>">
</head>
<body data-theme="<?php echo e($temaSistema); ?>">

<div class="login-container">
    <div class="login-brand-side">
        <div class="brand-content">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="brand-logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($empresa); ?></h1>
            <p>Plataforma de Gestión Empresarial</p>

            <div class="company-data">
                <?php if (!empty($configEmpresa['ruc'])): ?><span><i class="bi bi-upc-scan"></i> RUC: <?php echo e((string) $configEmpresa['ruc']); ?></span><?php endif; ?>
                <?php if (!empty($configEmpresa['telefono'])): ?><span><i class="bi bi-telephone"></i> <?php echo e((string) $configEmpresa['telefono']); ?></span><?php endif; ?>
                <?php if (!empty($configEmpresa['email'])): ?><span><i class="bi bi-envelope"></i> <?php echo e((string) $configEmpresa['email']); ?></span><?php endif; ?>
                <?php if (!empty($configEmpresa['direccion'])): ?><span><i class="bi bi-geo-alt"></i> <?php echo e((string) $configEmpresa['direccion']); ?></span><?php endif; ?>
            </div>
        </div>
        <div class="brand-footer">
            &copy; <?php echo date('Y'); ?> Todos los derechos reservados.
        </div>
    </div>

    <div class="login-form-side">
        <div class="login-panel">
            <div class="mobile-header">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
                <?php endif; ?>
                <h2><?php echo htmlspecialchars($empresa); ?></h2>
            </div>

            <div class="header-text">
                <h3>Bienvenido de nuevo</h3>
                <p class="subtitle">Ingresa tus credenciales para acceder</p>
            </div>

            <form id="login-form" method="post" action="?ruta=login/authenticate" novalidate data-error="<?php echo htmlspecialchars($error); ?>">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <div class="input-wrap">
                        <i class="bi bi-person input-icon"></i>
                        <input id="usuario" name="usuario" type="text" placeholder="Ej. admin" autocomplete="username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="clave">Contraseña</label>
                    <div class="password-wrap">
                        <i class="bi bi-lock input-icon"></i>
                        <input id="clave" name="clave" type="password" placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" id="toggle-password" class="toggle-password" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    Ingresar al Sistema
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo asset_url('js/app.js/login.js'); ?>"></script>
</body>
</html>
