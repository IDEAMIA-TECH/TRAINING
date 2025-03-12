<?php
require_once 'includes/header.php';

// Obtener banners activos
$db = new Database();
$conn = $db->getConnection();

$banners_query = "SELECT * FROM banner_images WHERE active = 1 ORDER BY order_index ASC";
$banners = $conn->query($banners_query)->fetchAll(PDO::FETCH_ASSOC);

// Obtener cursos próximos
$upcoming_courses_query = "
    SELECT * FROM courses 
    WHERE start_date > NOW() 
    AND status = 'active'
    ORDER BY start_date ASC 
    LIMIT 6
";
$upcoming_courses = $conn->query($upcoming_courses_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Banner Rotatorio -->
<?php if (!empty($banners)): ?>
    <div class="banner-slider">
        <div class="banner-slides">
            <?php foreach ($banners as $banner): ?>
                <div class="banner-slide">
                    <?php if ($banner['action_url']): ?>
                        <a href="<?php echo htmlspecialchars($banner['action_url']); ?>">
                    <?php endif; ?>
                    
                    <img src="<?php echo BASE_URL . '/' . $banner['image_url']; ?>" 
                         alt="<?php echo htmlspecialchars($banner['title']); ?>">
                    
                    <?php if ($banner['action_url']): ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button class="banner-nav prev" onclick="moveSlide(-1)">❮</button>
        <button class="banner-nav next" onclick="moveSlide(1)">❯</button>
        
        <div class="banner-dots">
            <?php for ($i = 0; $i < count($banners); $i++): ?>
                <span class="dot" onclick="currentSlide(<?php echo $i + 1; ?>)"></span>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Sección de Cursos Próximos -->
<section class="upcoming-courses">
    <div class="container">
        <h2>Próximos Entrenamientos</h2>
        <div class="courses-grid">
            <?php foreach ($upcoming_courses as $course): ?>
                <div class="course-card">
                    <?php if ($course['image_url']): ?>
                        <img src="<?php echo UPLOADS_URL . '/courses/' . $course['image_url']; ?>" 
                             alt="<?php echo htmlspecialchars($course['title']); ?>">
                    <?php endif; ?>
                    <div class="course-info">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p class="course-date">
                            <i class="far fa-calendar"></i>
                            <?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?>
                        </p>
                        <p class="course-price">
                            <i class="fas fa-tag"></i>
                            $<?php echo number_format($course['price'], 2); ?> MXN
                        </p>
                        <p class="course-capacity">
                            <i class="fas fa-users"></i>
                            Cupos disponibles: <?php echo $course['max_capacity']; ?>
                        </p>
                        <a href="<?php echo BASE_URL . '/courses/' . $course['id']; ?>" 
                           class="btn btn-primary">Ver Detalles</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
let slideIndex = 1;
let slideInterval;

function showSlides(n) {
    const slides = document.getElementsByClassName("banner-slide");
    const dots = document.getElementsByClassName("dot");
    
    if (n > slides.length) slideIndex = 1;
    if (n < 1) slideIndex = slides.length;
    
    for (let i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";
        dots[i].className = dots[i].className.replace(" active", "");
    }
    
    slides[slideIndex-1].style.display = "block";
    dots[slideIndex-1].className += " active";
}

function moveSlide(n) {
    clearInterval(slideInterval);
    showSlides(slideIndex += n);
    startAutoSlide();
}

function currentSlide(n) {
    clearInterval(slideInterval);
    showSlides(slideIndex = n);
    startAutoSlide();
}

function startAutoSlide() {
    slideInterval = setInterval(() => {
        moveSlide(1);
    }, 5000); // Cambiar cada 5 segundos
}

// Inicializar slider
document.addEventListener('DOMContentLoaded', function() {
    showSlides(slideIndex);
    startAutoSlide();
});
</script>

<?php require_once 'includes/footer.php'; ?> 