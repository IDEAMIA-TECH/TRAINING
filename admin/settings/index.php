<?php
require_once '../../includes/init.php';

if (!$is_admin) {
    redirect('/login.php');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $db->prepare("
                UPDATE system_settings 
                SET setting_value = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE setting_key = ?
            ");
            
            // Validar según el tipo
            $stmt_type = $db->prepare("SELECT type FROM system_settings WHERE setting_key = ?");
            $stmt_type->execute([$key]);
            $type = $stmt_type->fetchColumn();

            switch ($type) {
                case 'boolean':
                    $value = !empty($value) ? '1' : '0';
                    break;
                case 'number':
                    $value = filter_var($value, FILTER_VALIDATE_INT) ? $value : '0';
                    break;
                case 'email':
                    $value = filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
                    break;
                case 'json':
                    $value = is_array($value) ? json_encode($value) : '[]';
                    break;
            }

            $stmt->execute([$value, $key]);
        }

        // Registrar en el log
        $logger->log('update', 'settings', null, ['updated_keys' => array_keys($_POST['settings'])]);

        redirect('/admin/settings?success=1');
    }

    // Obtener configuraciones agrupadas por tipo
    $stmt = $db->query("
        SELECT * 
        FROM system_settings 
        ORDER BY FIELD(type, 'text', 'email', 'number', 'boolean', 'json')
    ");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<?php require_once '../../templates/admin/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Configuración del Sistema</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            Configuración actualizada exitosamente
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <?php foreach ($settings as $setting): ?>
                            <div class="mb-3">
                                <label class="form-label">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                </label>

                                <?php switch($setting['type']): 
                                    case 'boolean': ?>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" 
                                                   class="form-check-input" 
                                                   name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                   value="1"
                                                   <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                        </div>
                                        <?php break; ?>

                                    <?php case 'number': ?>
                                        <input type="number" 
                                               class="form-control" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                               required>
                                        <?php break; ?>

                                    <?php case 'json': ?>
                                        <?php $values = json_decode($setting['setting_value'], true); ?>
                                        <select class="form-select" 
                                                name="settings[<?php echo $setting['setting_key']; ?>][]" 
                                                multiple>
                                            <option value="paypal" <?php echo in_array('paypal', $values) ? 'selected' : ''; ?>>
                                                PayPal
                                            </option>
                                            <option value="stripe" <?php echo in_array('stripe', $values) ? 'selected' : ''; ?>>
                                                Stripe
                                            </option>
                                        </select>
                                        <?php break; ?>

                                    <?php default: ?>
                                        <input type="<?php echo $setting['type']; ?>" 
                                               class="form-control"
                                               name="settings[<?php echo $setting['setting_key']; ?>]"
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                               required>
                                <?php endswitch; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validación del formulario
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php require_once '../../templates/admin/footer.php'; ?> 