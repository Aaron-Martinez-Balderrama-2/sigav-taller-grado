<?php
/**
 * SIGAV-UAGRM | Gestión de Usuarios (CU03, CU04, CU05)
 * Solo Administradores. Reutiliza modal para creación y edición, y controla estado activo/inactivo.
 */
$requireAdmin = true;
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$msg = '';
$msgType = '';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

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
                $stmt = $pdo->prepare("INSERT INTO usuarios (id_rol, nombres, usuario, password_hash, estado) VALUES (:rol, :nombres, :usuario, :hash, 1)");
                $stmt->execute([':rol'=>$idRol, ':nombres'=>$nombres, ':usuario'=>$usuario, ':hash'=>$hash]);
                $msg = "Usuario '" . htmlspecialchars($usuario) . "' creado exitosamente.";
                $msgType = 'success';
            }
        }
    } elseif ($action === 'editar') {
        $id       = (int) ($_POST['id'] ?? 0);
        $nombres  = htmlspecialchars(trim($_POST['nombres'] ?? ''));
        $usuario  = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $idRol    = (int) ($_POST['id_rol'] ?? 2);

        if ($id <= 0 || empty($nombres) || empty($usuario)) {
            $msg = 'El nombre completo y el usuario son obligatorios.';
            $msgType = 'error';
        } elseif ($id === $currentUserId && $idRol !== 1) {
            $msg = 'No puede revocar su propio rol de Administrador.';
            $msgType = 'error';
        } elseif ($password !== '' && strlen($password) < 6) {
            $msg = 'La nueva contraseña debe tener al menos 6 caracteres.';
            $msgType = 'error';
        } else {
            $check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :u AND id != :id");
            $check->execute([':u' => $usuario, ':id' => $id]);
            if ($check->fetch()) {
                $msg = 'El nombre de usuario ya está en uso por otra cuenta.';
                $msgType = 'error';
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET id_rol = :rol, nombres = :nombres, usuario = :usuario, password_hash = :hash WHERE id = :id");
                    $stmt->execute([':rol'=>$idRol, ':nombres'=>$nombres, ':usuario'=>$usuario, ':hash'=>$hash, ':id'=>$id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET id_rol = :rol, nombres = :nombres, usuario = :usuario WHERE id = :id");
                    $stmt->execute([':rol'=>$idRol, ':nombres'=>$nombres, ':usuario'=>$usuario, ':id'=>$id]);
                }
                if ($id === $currentUserId) {
                    $_SESSION['user_name'] = $nombres;
                    $_SESSION['usuario']   = $usuario;
                }
                $msg = "Usuario '" . htmlspecialchars($usuario) . "' actualizado correctamente.";
                $msgType = 'success';
            }
        }
    } elseif ($action === 'toggle_estado') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $msg = 'Usuario no válido.';
            $msgType = 'error';
        } elseif ($id === $currentUserId) {
            $msg = 'No puede desactivar su propia cuenta activa.';
            $msgType = 'error';
        } else {
            if (isset($_POST['estado']) && $_POST['estado'] !== '') {
                $nuevoEstado = ((int) $_POST['estado']) ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE usuarios SET estado = :estado WHERE id = :id");
                $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET estado = NOT estado WHERE id = :id");
                $stmt->execute([':id' => $id]);
            }
            $msg = 'Estado del usuario actualizado correctamente.';
            $msgType = 'success';
        }
    }
}

$stmt = $pdo->query("SELECT u.id, u.id_rol, u.nombres, u.usuario, u.estado, r.nombre_rol FROM usuarios u JOIN roles r ON u.id_rol = r.id ORDER BY u.id ASC");
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
            <button type="button" class="btn btn-primary btn-sm" onclick="openCreateUserModal()" id="newUserBtn">
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
                <th>Acciones</th>
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
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <button type="button" class="btn btn-outline btn-sm"
                                    style="padding:4px 10px;font-size:0.74rem;"
                                    data-id="<?php echo (int) $u['id']; ?>"
                                    data-nombres="<?php echo htmlspecialchars($u['nombres'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-usuario="<?php echo htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-rol="<?php echo (int) $u['id_rol']; ?>"
                                    onclick="openEditUserModal(this)">
                                ✏️ Editar
                            </button>
                            <?php if ((int) $u['id'] !== $currentUserId): ?>
                                <form method="POST" action="" style="display:inline-block;">
                                    <input type="hidden" name="action" value="toggle_estado">
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <button type="submit"
                                            class="btn btn-sm <?php echo $u['estado'] ? 'btn-danger' : 'btn-success'; ?>"
                                            style="padding:4px 10px;font-size:0.74rem;">
                                        <?php echo $u['estado'] ? '⏸️ Desactivar' : '▶️ Activar'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:0.72rem;color:var(--on-surface-muted);">(Sesión actual)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal reutilizable para crear o editar usuario -->
<div class="modal-overlay" id="userModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="userModalTitle">Crear Nuevo Usuario</h3>
            <button class="modal-close" onclick="document.getElementById('userModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="userForm">
                <input type="hidden" name="action" id="userFormAction" value="crear">
                <input type="hidden" name="id" id="userFormId" value="">
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
                        <label for="passwordInput" id="passwordLabel">Contraseña *</label>
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
    function openCreateUserModal() {
        const form = document.getElementById('userForm');
        form.reset();
        document.getElementById('userFormAction').value = 'crear';
        document.getElementById('userFormId').value = '';
        document.getElementById('userModalTitle').textContent = 'Crear Nuevo Usuario';
        const passInput = document.getElementById('passwordInput');
        passInput.required = true;
        passInput.placeholder = 'Mínimo 6 caracteres';
        document.getElementById('passwordLabel').textContent = 'Contraseña *';
        document.getElementById('createUserBtn').textContent = 'Crear';
        document.getElementById('userModal').classList.add('show');
    }

    function openEditUserModal(btn) {
        document.getElementById('userFormAction').value = 'editar';
        document.getElementById('userFormId').value = btn.dataset.id || '';
        document.getElementById('nombres').value = btn.dataset.nombres || '';
        document.getElementById('usuarioInput').value = btn.dataset.usuario || '';
        document.getElementById('id_rol').value = btn.dataset.rol || '2';
        const passInput = document.getElementById('passwordInput');
        passInput.value = '';
        passInput.required = false;
        passInput.placeholder = 'Dejar vacío para mantener actual';
        document.getElementById('passwordLabel').textContent = 'Nueva Contraseña (opcional)';
        document.getElementById('userModalTitle').textContent = 'Editar Usuario — ' + (btn.dataset.usuario || '');
        document.getElementById('createUserBtn').textContent = 'Guardar Cambios';
        document.getElementById('userModal').classList.add('show');
    }

    document.getElementById('userModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.getElementById('userModal').classList.remove('show'); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
