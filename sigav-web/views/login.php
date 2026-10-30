<?php
/**
 * SIGAV-UAGRM | Login
 * Pantalla de acceso al sistema con validación segura.
 */
session_start();

// Si ya hay sesión activa, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/database.php';

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT u.id, u.id_rol, u.usuario, u.nombres, u.password_hash, u.estado, r.nombre_rol 
            FROM usuarios u 
            JOIN roles r ON u.id_rol = r.id 
            WHERE u.usuario = :usuario
        ");
        $stmt->execute([':usuario' => $usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['estado']) {
                $error = 'Su cuenta ha sido desactivada. Contacte al administrador.';
            } else {
                // Regenerar ID de sesión para prevenir ataques de fijación
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int) $user['id'];
                $_SESSION['id_rol']    = (int) $user['id_rol'];
                $_SESSION['usuario']   = $user['usuario'];
                $_SESSION['user_name'] = $user['nombres'];
                $_SESSION['user_role'] = $user['nombre_rol'];
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inicio de Sesión - SIGAV UAGRM">
    <title>SIGAV - Iniciar Sesión</title>
    <link rel="icon" type="image/png" href="../assets/img/uagrm_logo.png">
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/styles.css') ?: '2'; ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <img src="../assets/img/uagrm_logo.png" alt="UAGRM" id="loginLogo">
                <h1>SIGAV</h1>
                <span class="subtitle">Sistema Inteligente de Gestión de Acceso Vehicular</span>
            </div>

            <?php if ($error): ?>
                <div class="login-error" id="loginError"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" autocomplete="off">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" placeholder="Ingrese su usuario" 
                           value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" id="loginSubmitBtn">
                    Ingresar al Sistema
                </button>
            </form>
        </div>
    </div>
</body>
</html>
