<?php
/**
 * SIGAV-UAGRM | Punto de entrada principal
 * Redirige al login o al dashboard según la sesión.
 * Maneja el cierre de sesión (logout).
 */
session_start();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: views/login.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: views/dashboard.php');
} else {
    header('Location: views/login.php');
}
exit;
