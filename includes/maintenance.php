<?php
function check_maintenance_mode() {
    global $settings, $is_admin;

    // Si es admin, permitir acceso
    if ($is_admin) {
        return;
    }

    // Verificar si el modo mantenimiento está activo
    if ($settings->get('maintenance_mode', false)) {
        // Verificar si no estamos ya en la página de mantenimiento
        if (!str_ends_with($_SERVER['PHP_SELF'], '/maintenance.php')) {
            redirect('/maintenance.php');
        }
    } else {
        // Si el modo mantenimiento está desactivado y estamos en la página de mantenimiento
        if (str_ends_with($_SERVER['PHP_SELF'], '/maintenance.php')) {
            redirect('/');
        }
    }
} 