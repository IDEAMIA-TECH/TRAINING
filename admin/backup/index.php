<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

try {
    // Obtener lista de backups
    $stmt = $db->prepare("
        SELECT b.*, u.name as created_by_name
        FROM database_backups b
        JOIN users u ON b.created_by = u.id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Backups de Base de Datos</h5>
                    <button type="button" class="btn btn-primary" onclick="generateBackup()">
                        <i class="bi bi-download"></i> Generar Backup
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Tamaño</th>
                                    <th>Fecha</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                        <td><?php echo formatBytes($backup['size']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($backup['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($backup['created_by_name']); ?></td>
                                        <td>
                                            <a href="download.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generateBackup() {
    if (!confirm('¿Estás seguro de generar un nuevo backup?')) {
        return;
    }

    fetch('backup.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Backup generado exitosamente');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al generar el backup');
    });
}
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 