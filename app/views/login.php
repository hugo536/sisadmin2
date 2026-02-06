<?php
$error = isset($error) ? (string) $error : '';
$configEmpresa = is_array($configEmpresa ?? null) ? $configEmpresa : [];
$empresa = trim((string) ($configEmpresa['razon_social'] ?? '')) ?: 'SISADMIN';
$logoPath = trim((string) ($configEmpresa['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? base_url() . '/' . ltrim($logoPath, '/') : '';
$temaSistema = strtolower((string) ($configEmpresa['color_sistema'] ?? 'light'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | <?php echo htmlspecialchars($empresa); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo asset_url('css/login.css'); ?>">
</head>
<body data-theme="<?php echo e($temaSistema); ?>">

<div class="login-wrapper">
    <div class="brand-panel">
        <div class="brand-overlay"></div>
        <div class="brand-content">
            <div class="brand-header">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo Empresa" class="brand-logo">
                <?php endif; ?>
                <span class="badge-system">ERP SYSTEM v2.0</span>
            </div>
            
            <div class="brand-text">
                <h1><?php echo htmlspecialchars($empresa); ?></h1>
                <p class="lead">Plataforma integral de gestión y control empresarial.</p>
            </div>

            <div class="brand-info">
                <?php if (!empty($configEmpresa['ruc'])): ?>
                    <div class="info-item"><i class="bi bi-qr-code"></i> <span>RUC: <?php echo e((string) $configEmpresa['ruc']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($configEmpresa['email'])): ?>
                    <div class="info-item"><i class="bi bi-envelope-at"></i> <span><?php echo e((string) $configEmpresa['email']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($configEmpresa['direccion'])): ?>
                    <div class="info-item"><i class="bi bi-geo-alt"></i> <span><?php echo e((string) $configEmpresa['direccion']); ?></span></div>
                <?php endif; ?>
            </div>
            
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($empresa); ?>. Todos los derechos reservados.
            </div>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-card">
            <div class="form-header">
                <div class="mobile-brand">
                     <?php if ($logoUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                
                <h2>Iniciar Sesión</h2>
                <p class="subtitle">Ingresa tus credenciales para acceder al panel.</p>
            </div>

            <form id="login-form" method="post" action="?ruta=login/authenticate" novalidate data-error="<?php echo htmlspecialchars($error); ?>">
                <div class="form-group">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="bi bi-person"></i></span>
                        <input id="usuario" name="usuario" type="text" class="form-input" placeholder="Nombre de usuario" autocomplete="username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="clave" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="bi bi-shield-lock"></i></span>
                        <input id="clave" name="clave" type="password" class="form-input" placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" id="toggle-password" class="toggle-pass" aria-label="Ver contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-extras">
                        <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    Acceder al Sistema <i class="bi bi-arrow-right-short"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo asset_url('js/app.js/login.js'); ?>"></script>
</body>
</html>