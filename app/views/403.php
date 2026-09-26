<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso Denegado - ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .error-card {
            max-width: 480px;
            width: 100%;
        }
        .icon-lock {
            font-size: 4.5rem;
            color: #e74a3b; /* Rojo profesional */
        }
        .bg-light-danger {
            background-color: #fdeaea;
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto;
        }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-5">

    <div class="container d-flex justify-content-center">
        <div class="card border-0 shadow-lg rounded-4 p-4 p-sm-5 text-center error-card">
            
            <div class="mb-4">
                <div class="bg-light-danger">
                    <i class="bi bi-shield-lock-fill icon-lock"></i>
                </div>
            </div>
            
            <h1 class="h3 fw-bold text-dark mb-2">Acceso Restringido</h1>
            <p class="text-muted mb-4">Lo sentimos, tu perfil no cuenta con los permisos necesarios para visualizar este módulo o realizar esta acción.</p>
            
            <div class="d-flex flex-column gap-2 mb-4">
                <a href="javascript:history.back()" class="btn btn-primary fw-semibold rounded-pill py-2">
                    <i class="bi bi-arrow-left me-2"></i>Volver a la página anterior
                </a>
                <a href="?ruta=reportes/dashboard" class="btn btn-light border fw-semibold rounded-pill py-2 text-secondary">
                    <i class="bi bi-house-door me-2"></i>Ir al Panel Principal
                </a>
            </div>

            <!-- Zona técnica para administradores -->
            <?php if (isset($_GET['ruta']) || isset($_SESSION['permisos'])): ?>
                <div class="accordion accordion-flush" id="accordionDebug">
                    <div class="accordion-item border-0 bg-transparent">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-muted small shadow-none justify-content-center p-0 pb-2" type="button" data-bs-toggle="collapse" data-bs-target="#debugInfo">
                                <i class="bi bi-bug me-2"></i> Ver detalles técnicos
                            </button>
                        </h2>
                        <div id="debugInfo" class="accordion-collapse collapse mt-2">
                            <div class="text-start">
                                <?php if (isset($_GET['ruta'])): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-secondary fw-normal">Ruta bloqueada: <?php echo htmlspecialchars($_GET['ruta']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <pre class="bg-dark text-success p-3 rounded-3" style="font-size: 0.75rem; max-height: 200px; overflow-y: auto;"><?php 
                                    echo "Permisos en sesión:\n";
                                    print_r($_SESSION['permisos'] ?? 'VACÍO'); 
                                ?></pre>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>