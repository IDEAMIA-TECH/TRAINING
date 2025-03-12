<?php
require_once '../includes/header.php';

$error = null;
$certificate = null;

if (!empty($_GET['code'])) {
    try {
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
            WHERE c.certificate_number = ?
        ");
        $stmt->execute([$_GET['code']]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$certificate) {
            throw new Exception("Certificado no encontrado");
        }
        
        // Registrar verificación
        $stmt = $conn->prepare("
            INSERT INTO certificate_verifications (
                certificate_id, verifier_ip, verifier_user_agent
            ) VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $certificate['id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="verify-container">
    <div class="verify-header">
        <h1>Verificación de Certificados</h1>
        <p>Ingresa el código del certificado para verificar su autenticidad</p>
    </div>
    
    <div class="verify-form">
        <form method="get" class="search-form">
            <div class="input-group">
                <input type="text" 
                       name="code" 
                       class="form-control" 
                       placeholder="Ingresa el código del certificado"
                       value="<?php echo $_GET['code'] ?? ''; ?>"
                       required>
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Verificar
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php elseif ($certificate): ?>
        <div class="verify-result">
            <div class="result-header">
                <i class="fas fa-check-circle text-success"></i>
                <h2>Certificado Válido</h2>
            </div>
            
            <div class="certificate-info">
                <div class="info-item">
                    <span class="label">Estudiante:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['user_name']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="label">Curso:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['course_title']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="label">Examen:</span>
                    <span class="value"><?php echo htmlspecialchars($certificate['exam_title']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="label">Calificación:</span>
                    <span class="value"><?php echo $certificate['score']; ?>%</span>
                </div>
                
                <div class="info-item">
                    <span class="label">Fecha de Emisión:</span>
                    <span class="value">
                        <?php echo date('d/m/Y', strtotime($certificate['issued_date'])); ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="label">Número de Certificado:</span>
                    <span class="value"><?php echo $certificate['certificate_number']; ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/verify.css">

<?php require_once '../includes/footer.php'; ?> 