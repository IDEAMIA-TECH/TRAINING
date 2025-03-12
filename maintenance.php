<?php
require_once 'includes/init.php';

// Si el modo mantenimiento está desactivado, redirigir al inicio
if (!$settings->get('maintenance_mode', false)) {
    redirect('/');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitio en Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
        }
        .maintenance-container {
            text-align: center;
            padding: 2rem;
        }
        .maintenance-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 2rem;
        }
        .maintenance-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .maintenance-text {
            font-size: 1.2rem;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="maintenance-container">
            <div class="maintenance-icon">
                <i class="bi bi-gear-fill"></i>
            </div>
            <h1 class="maintenance-title">Sitio en Mantenimiento</h1>
            <p class="maintenance-text">
                Estamos realizando trabajos de mantenimiento para mejorar nuestros servicios.
                Por favor, vuelve a intentarlo más tarde.
            </p>
            <?php if ($is_admin): ?>
                <div class="mt-4">
                    <a href="/admin/settings" class="btn btn-primary">
                        Ir al Panel de Administración
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 