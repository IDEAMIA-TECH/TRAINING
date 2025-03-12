<?php
session_start();
require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#007bff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Entrenamientos">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="/assets/icons/icon-152x152.png">
    
    <!-- Estilos y Scripts -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    <?php if (is_logged_in() && !is_admin()): ?>
        <link rel="stylesheet" href="/assets/css/client.css">
    <?php elseif (is_admin()): ?>
        <link rel="stylesheet" href="/assets/css/admin.css">
    <?php endif; ?>
    
    <!-- Registro del Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('ServiceWorker registrado:', registration);
                    })
                    .catch((error) => {
                        console.log('Error al registrar ServiceWorker:', error);
                    });
            });
        }
    </script>
    
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo GA_TRACKING_ID; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo GA_TRACKING_ID; ?>', {
            'debug_mode': <?php echo GA_DEBUG_MODE ? 'true' : 'false'; ?>
        });
    </script>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>">
                    <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="Logo">
                </a>
            </div>
            <div class="menu">
                <a href="<?php echo BASE_URL; ?>/courses">Cursos</a>
                <?php if (is_logged_in()): ?>
                    <a href="<?php echo BASE_URL; ?>/client/dashboard.php">Mi Panel</a>
                    <a href="<?php echo BASE_URL; ?>/logout.php">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php">Iniciar Sesión</a>
                    <a href="<?php echo BASE_URL; ?>/register.php">Registrarse</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <main> 