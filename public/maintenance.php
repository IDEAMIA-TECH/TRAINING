<?php
require_once '../includes/init.php';

// Verificar si el usuario es admin
if (is_admin()) {
    // Los administradores pueden acceder al sitio incluso en mantenimiento
    redirect('/admin/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitio en Mantenimiento - <?php echo SITE_NAME; ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f5f5f5;
        }
        .container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
        }
        p {
            color: #666;
            margin-bottom: 1.5rem;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🛠️</div>
        <h1>Sitio en Mantenimiento</h1>
        <p>Estamos realizando algunas mejoras. Por favor, vuelve más tarde.</p>
        <?php if (isset($_SESSION['maintenance_message'])): ?>
            <p><?php echo htmlspecialchars($_SESSION['maintenance_message']); ?></p>
        <?php endif; ?>
    </div>
</body>
</html> 