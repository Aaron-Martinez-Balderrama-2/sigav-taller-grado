<?php
/**
 * SIGAV-UAGRM | Bitácora Histórica
 * Tabla paginada con búsqueda por placa.
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

// Parámetros de búsqueda y paginación
$search = htmlspecialchars(trim($_GET['q'] ?? ''));
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Contar total
$countSql = "SELECT COUNT(*) FROM bitacora_accesos";
$params = [];
if ($search) {
    $countSql .= " WHERE placa LIKE :search";
    $params[':search'] = "%{$search}%";
}
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

// Obtener registros
$sql = "SELECT * FROM bitacora_accesos";
if ($search) {
    $sql .= " WHERE placa LIKE :search";
}
$sql .= " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();
?>

<div class="table-wrapper">
    <div class="table-header">
        <h3>📋 Bitácora Histórica de Accesos</h3>
        <form method="GET" action="" class="table-search" id="searchForm">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" id="searchInput" placeholder="Buscar por placa..."
                   value="<?php echo $search; ?>">
        </form>
    </div>
    <div class="table-scroll">
    <table class="data-table" id="bitacoraTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Placa</th>
                <th>Tipo</th>
                <th>Confianza</th>
                <th>Ingreso</th>
                <th>Puerta Ingreso</th>
                <th>Salida</th>
                <th>Puerta Salida</th>
                <th>Alerta</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($registros)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--on-surface-muted); padding: 40px;">
                        No se encontraron registros.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($registros as $r): ?>
                    <tr class="<?php echo $r['alerta_disparada'] ? 'alert-row' : ''; ?>">
                        <td><?php echo $r['id']; ?></td>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($r['placa']); ?></td>
                        <td><?php echo htmlspecialchars($r['tipo_vehiculo_ia'] ?: '-'); ?></td>
                        <td><?php echo $r['confianza_ia'] ? $r['confianza_ia'] . '%' : '-'; ?></td>
                        <td><?php echo $r['hora_ingreso'] ? date('d/m/Y H:i:s', strtotime($r['hora_ingreso'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($r['puerta_ingreso']); ?></td>
                        <td><?php echo $r['hora_salida'] ? date('d/m/Y H:i:s', strtotime($r['hora_salida'])) : '<span class="badge badge-green">Adentro</span>'; ?></td>
                        <td><?php echo htmlspecialchars($r['puerta_salida'] ?: '-'); ?></td>
                        <td>
                            <?php if ($r['alerta_disparada']): ?>
                                <span class="badge badge-red">🚨 ALERTA</span>
                            <?php else: ?>
                                <span class="badge badge-green">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Paginación -->
    <div class="pagination">
        <div class="page-info">
            Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $limit, $total); ?> de <?php echo $total; ?> registros
        </div>
        <div class="page-btns">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>" class="page-btn" id="prevPageBtn">‹</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>" 
                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"
                   id="pageBtn-<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>" class="page-btn" id="nextPageBtn">›</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
