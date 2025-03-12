<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Obtener total de cursos
$total_stmt = $conn->query("SELECT COUNT(*) FROM courses");
$total_courses = $total_stmt->fetchColumn();
$total_pages = ceil($total_courses / $per_page);

// Obtener cursos para la página actual
$courses_query = "
    SELECT c.*, 
           COUNT(e.id) as enrollment_count,
           SUM(CASE WHEN e.payment_status = 'completed' THEN 1 ELSE 0 END) as paid_enrollments
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id
    GROUP BY c.id
    ORDER BY c.start_date DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($courses_query);
$stmt->execute([$per_page, $offset]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="index.php" class="active">Cursos</a>
            <a href="../users/">Usuarios</a>
            <a href="../banners/">Banners</a>
            <a href="../reports/">Reportes</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="content-header">
            <h2>Gestión de Cursos</h2>
            <a href="create.php" class="btn btn-primary">Nuevo Curso</a>
        </div>

        <div class="courses-table">
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Fecha</th>
                        <th>Capacidad</th>
                        <th>Inscritos</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['title']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($course['start_date'])); ?></td>
                            <td><?php echo $course['max_capacity']; ?></td>
                            <td>
                                <?php echo $course['paid_enrollments']; ?> / 
                                <?php echo $course['enrollment_count']; ?>
                            </td>
                            <td>$<?php echo number_format($course['price'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $course['status']; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="edit.php?id=<?php echo $course['id']; ?>" 
                                   class="btn btn-sm btn-secondary">
                                    Editar
                                </a>
                                <a href="enrollments.php?course_id=<?php echo $course['id']; ?>" 
                                   class="btn btn-sm btn-info">
                                    Inscritos
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="<?php echo $page === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 