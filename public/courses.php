<?php
require_once '../includes/init.php';

// Parámetros de búsqueda y filtrado
$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'date_asc'; // Opciones: date_asc, date_desc, price_asc, price_desc

try {
    // Construir la consulta base
    $query = "
        SELECT c.*, 
               COUNT(DISTINCT cr.id) as registered_students,
               (SELECT image_url FROM course_images WHERE course_id = c.id AND is_main = 1 LIMIT 1) as main_image
        FROM courses c
        LEFT JOIN course_registrations cr ON c.id = cr.course_id
        WHERE c.status = 'active'
    ";
    $params = [];

    // Aplicar filtros
    if ($search) {
        $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($category) {
        $query .= " AND c.category_id = ?";
        $params[] = $category;
    }

    if ($min_price > 0) {
        $query .= " AND c.price >= ?";
        $params[] = $min_price;
    }

    if ($max_price > 0) {
        $query .= " AND c.price <= ?";
        $params[] = $max_price;
    }

    if ($date_from) {
        $query .= " AND c.start_date >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $query .= " AND c.start_date <= ?";
        $params[] = $date_to;
    }

    // Agrupar resultados
    $query .= " GROUP BY c.id";

    // Aplicar ordenamiento
    switch ($sort) {
        case 'date_desc':
            $query .= " ORDER BY c.start_date DESC";
            break;
        case 'price_asc':
            $query .= " ORDER BY c.price ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY c.price DESC";
            break;
        default: // date_asc
            $query .= " ORDER BY c.start_date ASC";
    }

    // Obtener categorías para el filtro
    $categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Ejecutar la consulta principal
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener precios mínimo y máximo para el filtro
    $price_range = $db->query("
        SELECT MIN(price) as min_price, MAX(price) as max_price 
        FROM courses 
        WHERE status = 'active'
    ")->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../templates/header.php'; ?>

<div class="container py-5">
    <!-- Filtros -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Nombre o descripción del curso">
                        </div>

                        <div class="col-md-4">
                            <label for="category" class="form-label">Categoría</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="sort" class="form-label">Ordenar por</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>
                                    Fecha (más próximos)
                                </option>
                                <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>
                                    Fecha (más lejanos)
                                </option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>
                                    Precio (menor a mayor)
                                </option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>
                                    Precio (mayor a menor)
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Rango de Precios</label>
                            <div class="row">
                                <div class="col">
                                    <input type="number" class="form-control" name="min_price" 
                                           value="<?php echo $min_price ?: ''; ?>" 
                                           placeholder="Precio mínimo" min="0">
                                </div>
                                <div class="col">
                                    <input type="number" class="form-control" name="max_price" 
                                           value="<?php echo $max_price ?: ''; ?>" 
                                           placeholder="Precio máximo" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Rango de Fechas</label>
                            <div class="row">
                                <div class="col">
                                    <input type="date" class="form-control" name="date_from" 
                                           value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col">
                                    <input type="date" class="form-control" name="date_to" 
                                           value="<?php echo $date_to; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="<?php echo BASE_URL; ?>/courses.php" class="btn btn-secondary">Limpiar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultados -->
    <div class="row">
        <?php if (isset($error)): ?>
            <div class="col-12">
                <div class="alert alert-danger"><?php echo $error; ?></div>
            </div>
        <?php elseif (empty($courses)): ?>
            <div class="col-12">
                <div class="alert alert-info">No se encontraron cursos con los criterios especificados.</div>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <img src="<?php echo BASE_URL; ?>/assets/uploads/courses/<?php echo $course['main_image']; ?>" 
                             class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?></li>
                                <li><i class="bi bi-people"></i> <?php echo $course['capacity'] - $course['registered_students']; ?> lugares disponibles</li>
                                <li><i class="bi bi-tag"></i> $<?php echo number_format($course['price'], 2); ?></li>
                            </ul>
                        </div>
                        <div class="card-footer">
                            <a href="<?php echo BASE_URL; ?>/courses.php?id=<?php echo $course['id']; ?>" 
                               class="btn btn-primary w-100">Ver Detalles</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?> 