<?php
require_once '../../includes/header.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';

// Obtener configuración actual
$settings_query = "SELECT * FROM settings";
$settings = $conn->query($settings_query)->fetchAll(PDO::FETCH_KEY_PAIR);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->beginTransaction();
        
        // Actualizar configuraciones generales
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        // Configuraciones del sitio
        $site_settings = [
            'site_name' => sanitize_input($_POST['site_name']),
            'site_description' => sanitize_input($_POST['site_description']),
            'contact_email' => sanitize_input($_POST['contact_email']),
            'contact_phone' => sanitize_input($_POST['contact_phone'])
        ];
        
        // Configuraciones de pagos
        $payment_settings = [
            'bank_name' => sanitize_input($_POST['bank_name']),
            'bank_account' => sanitize_input($_POST['bank_account']),
            'bank_clabe' => sanitize_input($_POST['bank_clabe']),
            'bank_holder' => sanitize_input($_POST['bank_holder'])
        ];
        
        // Guardar todas las configuraciones
        foreach (array_merge($site_settings, $payment_settings) as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        
        // Procesar logo si se subió uno nuevo
        if (!empty($_FILES['site_logo']['name'])) {
            $logo = $_FILES['site_logo'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($logo['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception("Formato de imagen no válido");
            }
            
            $filename = 'logo_' . time() . '.' . $ext;
            $destination = '../../uploads/site/' . $filename;
            
            if (move_uploaded_file($logo['tmp_name'], $destination)) {
                // Actualizar logo en la base de datos
                $stmt->execute(['site_logo', $filename]);
            }
        }
        
        $conn->commit();
        $success = "Configuraciones actualizadas exitosamente";
        
        // Recargar configuraciones
        $settings = $conn->query($settings_query)->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error al actualizar configuraciones: " . $e->getMessage();
    }
}
?>

<div class="admin-container">
    <div class="admin-sidebar">
        <h3>Panel de Administración</h3>
        <nav>
            <a href="../dashboard.php">Dashboard</a>
            <a href="../courses/">Cursos</a>
            <a href="../payments/">Pagos</a>
            <a href="../users/">Usuarios</a>
            <a href="../reports/">Reportes</a>
            <a href="index.php" class="active">Configuración</a>
        </nav>
    </div>

    <div class="admin-content">
        <h2>Configuración del Sistema</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="settings-form">
            <div class="settings-section">
                <h3>Configuración General</h3>
                
                <div class="form-group">
                    <label for="site_name">Nombre del Sitio</label>
                    <input type="text" id="site_name" name="site_name" required
                           value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="site_description">Descripción del Sitio</label>
                    <textarea id="site_description" name="site_description" rows="3"><?php 
                        echo htmlspecialchars($settings['site_description'] ?? ''); 
                    ?></textarea>
                </div>

                <div class="form-group">
                    <label for="site_logo">Logo del Sitio</label>
                    <?php if (!empty($settings['site_logo'])): ?>
                        <div class="current-logo">
                            <img src="<?php echo UPLOADS_URL . '/site/' . $settings['site_logo']; ?>" 
                                 alt="Logo actual">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="site_logo" name="site_logo" accept="image/*">
                </div>
            </div>

            <div class="settings-section">
                <h3>Información de Contacto</h3>
                
                <div class="form-group">
                    <label for="contact_email">Email de Contacto</label>
                    <input type="email" id="contact_email" name="contact_email" required
                           value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="contact_phone">Teléfono de Contacto</label>
                    <input type="tel" id="contact_phone" name="contact_phone"
                           value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>">
                </div>
            </div>

            <div class="settings-section">
                <h3>Información Bancaria</h3>
                
                <div class="form-group">
                    <label for="bank_name">Banco</label>
                    <input type="text" id="bank_name" name="bank_name" required
                           value="<?php echo htmlspecialchars($settings['bank_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="bank_holder">Titular de la Cuenta</label>
                    <input type="text" id="bank_holder" name="bank_holder" required
                           value="<?php echo htmlspecialchars($settings['bank_holder'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="bank_account">Número de Cuenta</label>
                    <input type="text" id="bank_account" name="bank_account" required
                           value="<?php echo htmlspecialchars($settings['bank_account'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="bank_clabe">CLABE Interbancaria</label>
                    <input type="text" id="bank_clabe" name="bank_clabe" required
                           value="<?php echo htmlspecialchars($settings['bank_clabe'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 