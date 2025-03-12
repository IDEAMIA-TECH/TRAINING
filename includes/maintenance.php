<?php
function check_maintenance_mode() {
    global $settings;
    
    // Verificar si el modo mantenimiento está activo
    $maintenance_mode = $settings->get('maintenance_mode', 'false');
    
    // Si está en mantenimiento y no es admin
    if ($maintenance_mode === 'true' && !is_admin()) {
        // Si no estamos ya en la página de mantenimiento
        if (strpos($_SERVER['PHP_SELF'], '/public/maintenance.php') === false) {
            // Guardar mensaje de mantenimiento si existe
            $_SESSION['maintenance_message'] = $settings->get('maintenance_message');
            redirect('/maintenance.php');
        }
    }
} 