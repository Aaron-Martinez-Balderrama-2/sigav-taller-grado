<?php
/**
 * SIGAV-UAGRM | Padrón Vehicular
 * Formulario en modal emergente. Tabla con scroll horizontal.
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'registrar') {
        $placa       = strtoupper(trim($_POST['placa'] ?? ''));
        $tipo        = htmlspecialchars(trim($_POST['tipo_vehiculo'] ?? ''));
        $propietario = htmlspecialchars(trim($_POST['propietario'] ?? ''));
        $tipoProp    = htmlspecialchars(trim($_POST['tipo_propietario'] ?? ''));
        $facultad    = htmlspecialchars(trim($_POST['facultad'] ?? ''));
        $estado      = $_POST['estado_acceso'] ?? 'PERMITIDO';

        if (empty($placa) || empty($propietario)) {
            $msg = 'La placa y el propietario son obligatorios.';
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
                $msg = "Vehículo {$placa} registrado correctamente.";
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
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('padronModal').classList.add('show')" id="newVehicleBtn">
                + Nuevo Vehículo
            </button>
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
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vehiculos)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--on-surface-muted);padding:40px;">No hay vehículos registrados.</td></tr>
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
                            $badgeMap = ['PERMITIDO'=>'badge-green','DENEGADO'=>'badge-amber','LISTA_NEGRA'=>'badge-red'];
                            $bc = $badgeMap[$v['estado_acceso']] ?? 'badge-blue';
                            ?>
                            <span class="badge <?php echo $bc; ?>"><?php echo $v['estado_acceso']; ?></span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($v['fecha_registro'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal para crear nuevo vehículo -->
<div class="modal-overlay" id="padronModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Registrar Nuevo Vehículo</h3>
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
                            <option value="DENEGADO">⛔ Denegado</option>
                            <option value="LISTA_NEGRA">🚨 Lista Negra</option>
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
    // Cerrar modal con Escape o click fuera
    document.getElementById('padronModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.getElementById('padronModal').classList.remove('show'); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
