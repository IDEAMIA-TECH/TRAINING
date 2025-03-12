<?php
require_once '../includes/header.php';
require_once '../includes/ExamManager.php';

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

if (empty($_GET['exam_id'])) {
    header("Location: ../courses.php");
    exit();
}

try {
    // Verificar si el usuario aprobó el examen
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            e.title as exam_title,
            co.title as course_title,
            u.name as user_name
        FROM certificates c
        JOIN exams e ON c.exam_id = e.id
        JOIN courses co ON c.course_id = co.id
        JOIN users u ON c.user_id = u.id
        WHERE c.exam_id = ? AND c.user_id = ?
    ");
    $stmt->execute([$_GET['exam_id'], $_SESSION['user_id']]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificate) {
        throw new Exception("No has obtenido este certificado aún");
    }
    
    // Generar número de certificado si no existe
    if (empty($certificate['certificate_number'])) {
        $certificate_number = 'CERT-' . strtoupper(uniqid());
        
        $stmt = $conn->prepare("
            UPDATE certificates 
            SET certificate_number = ?
            WHERE id = ?
        ");
        $stmt->execute([$certificate_number, $certificate['id']]);
        
        $certificate['certificate_number'] = $certificate_number;
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="certificate-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
            <a href="../courses.php" class="btn btn-primary mt-3">Volver a Cursos</a>
        </div>
    <?php else: ?>
        <div class="certificate">
            <div class="certificate-header">
                <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="Logo" class="logo">
                <h1>Certificado de Finalización</h1>
            </div>
            
            <div class="certificate-content">
                <p class="recipient">Se certifica que</p>
                <h2 class="user-name"><?php echo htmlspecialchars($certificate['user_name']); ?></h2>
                <p class="achievement">
                    ha completado exitosamente el examen<br>
                    <strong><?php echo htmlspecialchars($certificate['exam_title']); ?></strong><br>
                    del curso<br>
                    <strong><?php echo htmlspecialchars($certificate['course_title']); ?></strong>
                </p>
                
                <div class="certificate-details">
                    <div class="detail-item">
                        <span class="label">Calificación:</span>
                        <span class="value"><?php echo $certificate['score']; ?>%</span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="label">Fecha de Emisión:</span>
                        <span class="value">
                            <?php echo date('d/m/Y', strtotime($certificate['issued_date'])); ?>
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="label">Número de Certificado:</span>
                        <span class="value"><?php echo $certificate['certificate_number']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="certificate-footer">
                <div class="signature">
                    <div class="signature-line"></div>
                    <p class="signature-name">Director Académico</p>
                </div>
                
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php 
                        echo urlencode(BASE_URL . '/certificates/verify.php?code=' . $certificate['certificate_number']); 
                    ?>" alt="QR Code">
                </div>
            </div>
        </div>
        
        <div class="certificate-actions">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir Certificado
            </button>
            
            <a href="../courses.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Cursos
            </a>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/certificate.css">

<?php require_once '../includes/footer.php'; ?> 