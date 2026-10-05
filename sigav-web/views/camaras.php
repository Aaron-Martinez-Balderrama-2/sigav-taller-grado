<?php
/**
 * SIGAV-UAGRM | Gestión de Cámaras (Fuentes de Video)
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

// Auto-crear tabla si no existe
$pdo->exec("
CREATE TABLE IF NOT EXISTS camaras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ip_url VARCHAR(255) NOT NULL,
    sentido ENUM('ingreso', 'salida') NOT NULL,
    activa BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        $nombre  = htmlspecialchars(trim($_POST['nombre'] ?? ''));
        $ip_url  = trim($_POST['ip_url'] ?? '');
        $sentido = $_POST['sentido'] ?? 'ingreso';

        if (empty($nombre) || empty($ip_url)) {
            $msg = 'Nombre e IP son obligatorios.';
            $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO camaras (nombre, ip_url, sentido, activa) VALUES (:nombre, :ip, :sentido, 1)");
            $stmt->execute([':nombre'=>$nombre, ':ip'=>$ip_url, ':sentido'=>$sentido]);
            $msg = "Cámara '{$nombre}' añadida exitosamente.";
            $msgType = 'success';
        }
    } elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $estado = (int) $_POST['estado'];
        $stmt = $pdo->prepare("UPDATE camaras SET activa = :estado WHERE id = :id");
        $stmt->execute([':estado'=>$estado, ':id'=>$id]);
        $msg = "Estado de cámara actualizado.";
        $msgType = 'success';
    } elseif ($action === 'eliminar') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM camaras WHERE id = :id");
        $stmt->execute([':id'=>$id]);
        $msg = "Cámara eliminada.";
        $msgType = 'success';
    }
}

$camaras = $pdo->query("SELECT * FROM camaras ORDER BY id DESC")->fetchAll();
?>

<?php if ($msg): ?>
    <div class="<?php echo $msgType === 'success' ? 'msg-success' : 'login-error'; ?>">
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<div class="table-wrapper">
    <div class="table-header">
        <h3>📹 Conexión de Cámaras (<?php echo count($camaras); ?>)</h3>
        <div class="table-actions">
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('camModal').classList.add('show')">
                + Conectar Nueva Cámara
            </button>
        </div>
    </div>
    <div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre / Ubicación</th>
                <th>IP o URL</th>
                <th>Tipo (Sentido)</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($camaras)): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--on-surface-muted)">No hay cámaras configuradas. Usa el botón superior para conectar un celular.</td></tr>
            <?php endif; ?>
            <?php foreach ($camaras as $c): ?>
                <tr>
                    <td><?php echo $c['id']; ?></td>
                    <td style="font-weight:600"><?php echo htmlspecialchars($c['nombre']); ?></td>
                    <td style="font-family:monospace;color:var(--primary)"><?php echo htmlspecialchars($c['ip_url']); ?></td>
                    <td>
                        <span class="badge <?php echo $c['sentido'] === 'ingreso' ? 'badge-blue' : 'badge-red'; ?>">
                            <?php echo strtoupper($c['sentido']); ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="estado" value="<?php echo $c['activa'] ? '0' : '1'; ?>">
                            <button type="submit" class="btn btn-sm <?php echo $c['activa'] ? 'btn-success' : 'btn-outline'; ?>" style="font-size:0.7rem;padding:4px 8px;">
                                <?php echo $c['activa'] ? '🟢 ON' : '⚫ OFF'; ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Eliminar esta cámara?');">
                            <input type="hidden" name="action" value="eliminar">
                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm" style="font-size:0.7rem;padding:4px 8px;">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="camModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Conectar Nueva Cámara</h3>
            <button class="modal-close" onclick="document.getElementById('camModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear">
                <div class="form-group">
                    <label>Nombre de Ubicación (Ej: Puerta 1 - Bush)</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>IP de la Cámara / Celular (Ej: http://192.168.1.10:8080/video)</label>
                    <input type="text" name="ip_url" required placeholder="También puedes usar 0 para la webcam local">
                </div>
                <div class="form-group">
                    <label>Sentido (¿Qué detecta esta cámara?)</label>
                    <select name="sentido">
                        <option value="ingreso">Ingreso de Vehículos</option>
                        <option value="salida">Salida de Vehículos</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('camModal').classList.remove('show')">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Guardar Cámara</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('camModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.getElementById('camModal').classList.remove('show'); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
