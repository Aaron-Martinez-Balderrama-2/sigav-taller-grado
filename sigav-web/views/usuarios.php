<?php
/**
 * SIGAV-UAGRM | Gestión de Usuarios
 * Solo Administradores. Formulario en modal. Tabla con scroll.
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

if (($_SESSION['user_role'] ?? '') !== 'Administrador') {
    echo '<div class="login-error" style="margin-bottom:16px">⛔ No tiene permisos para acceder a esta sección.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pdo = getDBConnection();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        $nombres  = htmlspecialchars(trim($_POST['nombres'] ?? ''));
        $usuario  = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $idRol    = (int) ($_POST['id_rol'] ?? 2);

        if (empty($nombres) || empty($usuario) || empty($password)) {
            $msg = 'Todos los campos son obligatorios.';
            $msgType = 'error';
        } elseif (strlen($password) < 6) {
            $msg = 'La contraseña debe tener al menos 6 caracteres.';
            $msgType = 'error';
        } else {
            $check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :u");
            $check->execute([':u' => $usuario]);
            if ($check->fetch()) {
                $msg = 'El nombre de usuario ya está en uso.';
                $msgType = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (id_rol, nombres, usuario, password_hash) VALUES (:rol, :nombres, :usuario, :hash)");
                $stmt->execute([':rol'=>$idRol, ':nombres'=>$nombres, ':usuario'=>$usuario, ':hash'=>$hash]);
                $msg = "Usuario '{$usuario}' creado exitosamente.";
                $msgType = 'success';
            }
        }
    }
}

$stmt = $pdo->query("SELECT u.id, u.nombres, u.usuario, u.estado, r.nombre_rol FROM usuarios u JOIN roles r ON u.id_rol = r.id ORDER BY u.id ASC");
$usuarios = $stmt->fetchAll();
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
?>

<?php if ($msg): ?>
    <div class="<?php echo $msgType === 'success' ? 'msg-success' : 'login-error'; ?>">
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<!-- Tabla de Usuarios -->
<div class="table-wrapper">
    <div class="table-header">
        <h3>👥 Usuarios del Sistema (<?php echo count($usuarios); ?>)</h3>
        <div class="table-actions">
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('userModal').classList.add('show')" id="newUserBtn">
                + Crear Usuario
            </button>
        </div>
    </div>
    <div class="table-scroll">
    <table class="data-table" id="usersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td style="font-weight:600"><?php echo htmlspecialchars($u['nombres']); ?></td>
                    <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                    <td><span class="badge badge-blue"><?php echo htmlspecialchars($u['nombre_rol']); ?></span></td>
                    <td>
                        <?php if ($u['estado']): ?>
                            <span class="badge badge-green">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-red">Inactivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal para crear usuario -->
<div class="modal-overlay" id="userModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Crear Nuevo Usuario</h3>
            <button class="modal-close" onclick="document.getElementById('userModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="userForm">
                <input type="hidden" name="action" value="crear">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombres">Nombre Completo *</label>
                        <input type="text" id="nombres" name="nombres" placeholder="Juan Pérez" required>
                    </div>
                    <div class="form-group">
                        <label for="usuarioInput">Usuario *</label>
                        <input type="text" id="usuarioInput" name="usuario" placeholder="jperez" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="passwordInput">Contraseña *</label>
                        <input type="password" id="passwordInput" name="password" placeholder="Mínimo 6 caracteres" required>
                    </div>
                    <div class="form-group">
                        <label for="id_rol">Rol</label>
                        <select id="id_rol" name="id_rol">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['nombre_rol']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('userModal').classList.remove('show')">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="createUserBtn">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('userModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.getElementById('userModal').classList.remove('show'); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
