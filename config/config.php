<?php
// Configuración general
$subdir = dirname($_SERVER['PHP_SELF']);
$subdir = ($subdir === '/') ? '' : $subdir;
define('BASE_URL', '//' . $_SERVER['HTTP_HOST'] . $subdir);
define('SITE_NAME', 'Sistema de Cursos');

// Configuración de correo
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', 'password');
define('SMTP_PORT', 587);

// Configuración de pagos
define('PAYPAL_CLIENT_ID', 'your_client_id');
define('PAYPAL_CLIENT_SECRET', 'your_client_secret');
define('PAYPAL_SANDBOX', true);
define('PAYPAL_WEBHOOK_ID', 'your_webhook_id');
define('STRIPE_PUBLIC_KEY', 'your_publishable_key');
define('STRIPE_SECRET_KEY', 'your_secret_key');
define('STRIPE_WEBHOOK_SECRET', 'your_webhook_secret');

// Directorios
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/'); 