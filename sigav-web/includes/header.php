<?php
/**
 * SIGAV-UAGRM | Header Include (Sidebar + Top Bar)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$isAdmin = (($_SESSION['user_role'] ?? '') === 'Administrador') || ((int) ($_SESSION['id_rol'] ?? 0) === 1);

if (!empty($requireAdmin) && !$isAdmin) {
    header('Location: dashboard.php?error=acceso_denegado');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Usuario');
$userRole = htmlspecialchars($_SESSION['user_role'] ?? 'Guardia');
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SIGAV - Sistema Inteligente de Gestión de Acceso Vehicular - UAGRM">
    <title>SIGAV - <?php echo ucfirst($currentPage); ?></title>
    <link rel="icon" type="image/png" href="../assets/img/uagrm_logo.png">
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/styles.css') ?: '2'; ?>">
    <script>
        // Aplicar tema guardado antes de que se pinte la página (evitar flash)
        (function(){
            var t = localStorage.getItem('sigav-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
<div class="app-layout">
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="../assets/img/uagrm_logo.png" alt="UAGRM">
            <div class="brand-text">
                <h2>SIGAV</h2>
                <span>Control Vehicular</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <span class="nav-section">Monitoreo</span>
            <a href="dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" id="nav-dashboard">
                <span class="nav-icon">📊</span> Monitor en Vivo
            </a>
            <a href="bitacora.php" class="nav-link <?php echo $currentPage === 'bitacora' ? 'active' : ''; ?>" id="nav-bitacora">
                <span class="nav-icon">📋</span> Bitácora Histórica
            </a>

            <span class="nav-section"><?php echo $isAdmin ? 'Administración' : 'Consulta'; ?></span>
            <a href="padron.php" class="nav-link <?php echo $currentPage === 'padron' ? 'active' : ''; ?>" id="nav-padron">
                <span class="nav-icon">🚗</span> Padrón Vehicular
            </a>
            <?php if ($isAdmin): ?>
            <a href="camaras.php" class="nav-link <?php echo $currentPage === 'camaras' ? 'active' : ''; ?>" id="nav-camaras">
                <span class="nav-icon">📹</span> Gestión de Cámaras
            </a>
            <a href="usuarios.php" class="nav-link <?php echo $currentPage === 'usuarios' ? 'active' : ''; ?>" id="nav-usuarios">
                <span class="nav-icon">👤</span> Gestión de Usuarios
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar"><?php echo $userInitial; ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo $userName; ?></div>
                    <div class="user-role"><?php echo $userRole; ?></div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <header class="top-header">
            <div style="display:flex;align-items:center;gap:10px">
                <button class="hamburger-btn" onclick="toggleSidebar()" id="hamburgerBtn" aria-label="Menú">☰</button>
                <span class="page-title" id="pageTitle"><?php echo ucfirst($currentPage); ?></span>
            </div>
            <div class="header-actions">
                <div class="live-indicator" id="liveIndicator">
                    <span class="live-dot"></span> EN VIVO
                </div>
                <button class="theme-toggle" onclick="toggleTheme()" id="themeToggleBtn" title="Cambiar tema" aria-label="Cambiar tema">
                    <span id="themeIcon">☀️</span>
                </button>
                <a href="../index.php?logout=1" class="btn btn-sm btn-outline" id="logoutBtn">Salir</a>
            </div>
        </header>

        <div class="page-content">
