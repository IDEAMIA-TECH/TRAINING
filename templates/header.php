<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Academee - Education Center'; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Bree+Serif&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <nav class="nav-container">
            <a href="<?php echo BASE_URL; ?>/" class="logo">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="Academee">
            </a>
            <div class="nav-menu">
                <a href="<?php echo BASE_URL; ?>/" class="nav-link">HOME</a>
                <a href="<?php echo BASE_URL; ?>/features" class="nav-link">FEATURES</a>
                <a href="<?php echo BASE_URL; ?>/courses" class="nav-link">COURSES</a>
                <a href="<?php echo BASE_URL; ?>/teachers" class="nav-link">TEACHERS</a>
                <a href="<?php echo BASE_URL; ?>/blog" class="nav-link">BLOG</a>
                <a href="<?php echo BASE_URL; ?>/store" class="nav-link">STORE</a>
                <?php if ($user_authenticated): ?>
                    <a href="<?php echo BASE_URL; ?>/cart" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/dashboard" class="btn btn-primary">Dashboard</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
</body>
</html> 