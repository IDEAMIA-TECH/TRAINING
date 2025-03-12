<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Academee - Education Center'; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Bree+Serif&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/main.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/components.css">
    <?php if (strpos($_SERVER['REQUEST_URI'], 'login.php') !== false || 
              strpos($_SERVER['REQUEST_URI'], 'register.php') !== false): ?>
        <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/auth.css">
    <?php endif; ?>
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <nav class="nav-container">
            <a href="<?php echo BASE_URL; ?>/index.php" class="logo">
                <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="Academee">
            </a>
            <div class="nav-menu">
                <a href="<?php echo BASE_URL; ?>/index.php" class="nav-link">HOME</a>
                <a href="<?php echo BASE_URL; ?>/features.php" class="nav-link">FEATURES</a>
                <a href="<?php echo BASE_URL; ?>/courses.php" class="nav-link">COURSES</a>
                <a href="<?php echo BASE_URL; ?>/teachers.php" class="nav-link">TEACHERS</a>
                <a href="<?php echo BASE_URL; ?>/blog.php" class="nav-link">BLOG</a>
                <a href="<?php echo BASE_URL; ?>/store.php" class="nav-link">STORE</a>
                <?php if ($user_authenticated): ?>
                    <a href="<?php echo BASE_URL; ?>/cart.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-primary">Dashboard</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
</body>
</html> 