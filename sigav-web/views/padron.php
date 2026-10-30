<?php
/**
 * SIGAV-UAGRM | Padrón Vehicular
 * Consulta permitida para Administrador y Guardia (CU07).
 * Registro, edición y gestión de lista negra exclusivos para Administrador (CU06, CU08, CU09).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$esAdminSesion = (($_SESSION['user_role'] ?? '') === 'Administrador') || ((int) ($_SESSION['id_rol'] ?? 0) === 1);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$esAdminSesion) {
    header('Location: dashboard.php?error=acceso_denegado');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';
    if ($action === 'registrar') {
        $placa       = strtoupper(trim($_POST['placa'] ?? ''));
        $tipo        = htmlspecialchars(trim($_POST['tipo_vehiculo'] ?? ''));
        $propietario = htmlspecialchars(trim($_POST['propietario'] ?? ''));
        $tipoProp    = htmlspecialchars(trim($_POST['tipo_propietario'] ?? ''));
        $facultad    = htmlspecialchars(trim($_POST['facultad'] ?? ''));
        $estado      = $_POST['estado_acceso'] ?? 'PERMITIDO';
        $estadosValidos = ['PERMITIDO', 'DENEGADO', 'LISTA_NEGRA'];

        if (empty($placa) || empty($propietario)) {
            $msg = 'La placa y el propietario son obligatorios.';
            $msgType = 'error';
        } elseif (!in_array($estado, $estadosValidos, true)) {
            $msg = 'Estado de acceso no válido.';
            $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO padron_vehicular (placa, tipo_vehiculo, propietario, tipo_propietario, facultad, estado_acceso)
                    VALUES (:placa, :tipo, :prop, :tipoProp, :facultad, :estado)
                    ON DUPLICATE KEY UPDATE
                        tipo_vehiculo = VALUES(tipo_vehiculo), propietario = VALUES(propietario),
                        tipo_propietario = VALUES(tipo_propietario), facultad = VALUES(facultad),
                        estado_acceso = VALUES(estado_acceso)
                ");
                $stmt->execute([':placa'=>$placa, ':tipo'=>$tipo, ':prop'=>$propietario, ':tipoProp'=>$tipoProp, ':facultad'=>$facultad, ':estado'=>$estado]);
                $msg = "Vehículo {$placa} guardado correctamente en el padrón.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = 'Error al guardar el vehículo.';
                $msgType = 'error';
            }
        }
    }
}

$search = htmlspecialchars(trim($_GET['q'] ?? ''));
$sql = "SELECT * FROM padron_vehicular";
$params = [];
if ($search) {
    $sql .= " WHERE placa LIKE :search OR propietario LIKE :search2";
    $params[':search'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}
$sql .= " ORDER BY fecha_registro DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehiculos = $stmt->fetchAll();
?>

<?php if ($msg): ?>
    <div class="<?php echo $msgType === 'success' ? 'msg-success' : 'login-error'; ?>">
        <?php echo $msg; ?>
    </div>
<?php endif; ?>

<!-- Tabla del Padrón -->
<div class="table-wrapper">
    <div class="table-header">
        <h3>🚗 Padrón Vehicular (<?php echo count($vehiculos); ?>)</h3>
        <div class="table-actions">
            <form method="GET" action="" class="table-search">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" id="padronSearchInput" placeholder="Buscar placa o propietario..."
                       value="<?php echo $search; ?>">
            </form>
            <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="openNewVehicle()" id="newVehicleBtn">
                + Nuevo Vehículo
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-scroll">
    <table class="data-table" id="padronTable">
        <thead>
            <tr>
                <th>Placa</th>
                <th>Tipo</th>
                <th>Propietario</th>
                <th>Categoría</th>
                <th>Facultad</th>
                <th>Estado</th>
                <th>Fecha Registro</th>
                <?php if ($isAdmin): ?>
                <th>Acciones</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vehiculos)): ?>
                <tr><td colspan="<?php echo $isAdmin ? 8 : 7; ?>" style="text-align:center;color:var(--on-surface-muted);padding:40px;">No hay vehículos registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($vehiculos as $v): ?>
                    <tr>
                        <td style="font-weight:700"><?php echo htmlspecialchars($v['placa']); ?></td>
                        <td><?php echo htmlspecialchars($v['tipo_vehiculo'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($v['propietario']); ?></td>
                        <td><?php echo htmlspecialchars($v['tipo_propietario'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($v['facultad'] ?: '-'); ?></td>
                        <td>
                            <?php
                            $badgeMap = ['PERMITIDO'=>'badge-green','DENEGADO'=>'badge-amber','LISTA_NEGRA'=>'badge-dark'];
                            $bc = $badgeMap[$v['estado_acceso']] ?? 'badge-blue';
                            ?>
                            <span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($v['estado_acceso']); ?></span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($v['fecha_registro'])); ?></td>
                        <?php if ($isAdmin): ?>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm"
                                    style="padding:4px 10px;font-size:0.74rem;"
                                    data-placa="<?php echo htmlspecialchars($v['placa'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-tipo="<?php echo htmlspecialchars($v['tipo_vehiculo'] ?? 'Auto', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-propietario="<?php echo htmlspecialchars($v['propietario'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-tipoprop="<?php echo htmlspecialchars($v['tipo_propietario'] ?? 'Estudiante', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-facultad="<?php echo htmlspecialchars($v['facultad'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-estado="<?php echo htmlspecialchars($v['estado_acceso'] ?? 'PERMITIDO', ENT_QUOTES, 'UTF-8'); ?>"
                                    onclick="openEditVehicle(this)">
                                ✏️ Editar
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal reutilizable para registrar o editar vehículo -->
<div class="modal-overlay" id="padronModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="padronModalTitle">Registrar Nuevo Vehículo</h3>
            <button class="modal-close" onclick="document.getElementById('padronModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="padronForm">
                <input type="hidden" name="action" value="registrar">
                <div class="form-row">
                    <div class="form-group">
                        <label for="placa">Placa *</label>
                        <input type="text" id="placa" name="placa" placeholder="Ej: 233BTP" required style="text-transform:uppercase">
                    </div>
                    <div class="form-group">
                        <label for="propietario">Propietario *</label>
                        <input type="text" id="propietario" name="propietario" placeholder="Nombre completo" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo_vehiculo">Tipo de Vehículo</label>
                        <select id="tipo_vehiculo" name="tipo_vehiculo">
                            <option value="Auto">Auto</option>
                            <option value="Moto">Moto</option>
                            <option value="Camioneta">Camioneta</option>
                            <option value="Bus">Bus</option>
                            <option value="Bicicleta">Bicicleta</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tipo_propietario">Tipo de Propietario</label>
                        <select id="tipo_propietario" name="tipo_propietario">
                            <option value="Estudiante">Estudiante</option>
                            <option value="Docente">Docente</option>
                            <option value="Administrativo">Administrativo</option>
                            <option value="Visitante">Visitante</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="facultad">Facultad</label>
                        <input type="text" id="facultad" name="facultad" placeholder="Ej: Ingeniería">
                    </div>
                    <div class="form-group">
                        <label for="estado_acceso">Estado de Acceso</label>
                        <select id="estado_acceso" name="estado_acceso">
                            <option value="PERMITIDO">✅ Permitido</option>
                            <option value="DENEGADO">⚠️ Denegado</option>
                            <option value="LISTA_NEGRA">⛔ Lista Negra</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('padronModal').classList.remove('show')">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="padronSubmitBtn">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openNewVehicle() {
        const form = document.getElementById('padronForm');
        form.reset();
        const placaInput = document.getElementById('placa');
        placaInput.readOnly = false;
        document.getElementById('padronModalTitle').textContent = 'Registrar Nuevo Vehículo';
        document.getElementById('padronModal').classList.add('show');
    }

    function openEditVehicle(btn) {
        document.getElementById('padronModalTitle').textContent = 'Editar Vehículo — ' + btn.dataset.placa;
        const placaInput = document.getElementById('placa');
        placaInput.value = btn.dataset.placa || '';
        placaInput.readOnly = true;
        document.getElementById('propietario').value = btn.dataset.propietario || '';
        document.getElementById('tipo_vehiculo').value = btn.dataset.tipo || 'Auto';
        document.getElementById('tipo_propietario').value = btn.dataset.tipoprop || 'Estudiante';
        document.getElementById('facultad').value = btn.dataset.facultad || '';
        document.getElementById('estado_acceso').value = btn.dataset.estado || 'PERMITIDO';
        document.getElementById('padronModal').classList.add('show');
    }

    // Cerrar modal con Escape o click fuera
    document.getElementById('padronModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.getElementById('padronModal').classList.remove('show'); });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
