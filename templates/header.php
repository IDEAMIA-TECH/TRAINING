<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Academee - Education Center'; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Bree+Serif&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <nav class="nav-container">
            <a href="/" class="logo">
                <img src="/assets/images/logo.png" alt="Academee">
            </a>
            <div class="nav-menu">
                <a href="/" class="nav-link">HOME</a>
                <a href="/features" class="nav-link">FEATURES</a>
                <a href="/courses" class="nav-link">COURSES</a>
                <a href="/teachers" class="nav-link">TEACHERS</a>
                <a href="/blog" class="nav-link">BLOG</a>
                <a href="/store" class="nav-link">STORE</a>
                <?php if ($user_authenticated): ?>
                    <a href="/cart" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="/dashboard" class="btn btn-primary">Dashboard</a>
                <?php else: ?>
                    <a href="/login" class="btn btn-primary">Login</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
</body>
</html> 