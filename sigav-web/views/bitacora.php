<?php
/**
 * SIGAV-UAGRM | Bitácora Histórica (RF9, RF10, RF11, RF12, RF13)
 * Tabla paginada con filtros por placa, fechas y nivel de alerta,
 * corrección manual de placas, visualización de evidencias y exportación CSV.
 */
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

// Parámetros de búsqueda, filtrado y paginación
$search       = trim((string) ($_GET['q'] ?? ''));
$fechaInicio  = trim((string) ($_GET['fecha_inicio'] ?? ''));
$fechaFin          = trim((string) ($_GET['fecha_fin'] ?? ''));
$alertaFiltro      = trim((string) ($_GET['alerta'] ?? ''));
$puertaFiltro      = trim((string) ($_GET['puerta'] ?? ''));
$tipoFiltro        = trim((string) ($_GET['tipo'] ?? ''));
$colorFiltro       = trim((string) ($_GET['color'] ?? ''));
$permanenciaFiltro = trim((string) ($_GET['permanencia'] ?? ''));
$vista             = (($_GET['vista'] ?? 'tabla') === 'galeria') ? 'galeria' : 'tabla';
$page              = max(1, (int) ($_GET['page'] ?? 1));
$limit             = 10;
$offset            = ($page - 1) * $limit;

if (!function_exists('resolveBitacoraEvidenceUrl')) {
    function resolveBitacoraEvidenceUrl(?string $src): string {
        $clean = trim((string) ($src ?? ''));
        if ($clean === '') {
            return '';
        }
        if (
            str_starts_with($clean, 'data:image/') ||
            str_starts_with($clean, 'http://') ||
            str_starts_with($clean, 'https://') ||
            str_starts_with($clean, '../')
        ) {
            return $clean;
        }
        if (str_starts_with($clean, 'assets/')) {
            return '../' . $clean;
        }
        return '../assets/img/evidencias/' . rawurlencode($clean);
    }
}

$where  = [];
$params = [];

if ($search !== '') {
    $where[]           = "placa LIKE :search";
    $params[':search'] = "%{$search}%";
}
if ($fechaInicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
    $where[]                 = "DATE(hora_ingreso) >= :fecha_inicio";
    $params[':fecha_inicio'] = $fechaInicio;
} else {
    $fechaInicio = '';
}
if ($fechaFin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    $where[]              = "DATE(hora_ingreso) <= :fecha_fin";
    $params[':fecha_fin'] = $fechaFin;
} else {
    $fechaFin = '';
}
if ($alertaFiltro === 'con_alerta') {
    $where[] = "alerta_disparada > 0";
} elseif (in_array($alertaFiltro, ['0', '1', '2', '3'], true)) {
    $where[]           = "alerta_disparada = :alerta";
    $params[':alerta'] = (int) $alertaFiltro;
} else {
    $alertaFiltro = '';
}
if ($puertaFiltro !== '') {
    $where[]               = "(puerta_ingreso = :puerta_ing OR puerta_salida = :puerta_sal)";
    $params[':puerta_ing'] = $puertaFiltro;
    $params[':puerta_sal'] = $puertaFiltro;
}
if ($tipoFiltro !== '') {
    $where[]         = "tipo_vehiculo_ia = :tipo";
    $params[':tipo'] = $tipoFiltro;
}
if ($colorFiltro !== '') {
    $where[]          = "color_vehiculo = :color";
    $params[':color'] = $colorFiltro;
}
if ($permanenciaFiltro === 'adentro') {
    $where[] = "hora_salida IS NULL";
} elseif ($permanenciaFiltro === 'salida') {
    $where[] = "hora_salida IS NOT NULL";
} else {
    $permanenciaFiltro = '';
}

// Conteo de filtros avanzados aplicados
$filtrosActivosCount = 0;
if ($fechaInicio !== '') $filtrosActivosCount++;
if ($fechaFin !== '') $filtrosActivosCount++;
if ($alertaFiltro !== '') $filtrosActivosCount++;
if ($puertaFiltro !== '') $filtrosActivosCount++;
if ($tipoFiltro !== '') $filtrosActivosCount++;
if ($colorFiltro !== '') $filtrosActivosCount++;
if ($permanenciaFiltro !== '') $filtrosActivosCount++;

$whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// Contar total
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM bitacora_accesos" . $whereSql);
$stmtCount->execute($params);
$total      = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

// Obtener registros
$sql = "SELECT * FROM bitacora_accesos" . $whereSql . " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Query string base para exportación CSV y paginación
$baseQueryParams = [
    'q'            => $search,
    'fecha_inicio' => $fechaInicio,
    'fecha_fin'    => $fechaFin,
    'alerta'       => $alertaFiltro,
    'puerta'       => $puertaFiltro,
    'tipo'         => $tipoFiltro,
    'color'        => $colorFiltro,
    'permanencia'  => $permanenciaFiltro,
];
$filterQuery = http_build_query($baseQueryParams);
$pageQuery   = http_build_query(array_merge($baseQueryParams, ['vista' => $vista]));
$resetHref   = ($vista === 'galeria') ? 'bitacora.php?vista=galeria' : 'bitacora.php';
?>

<div class="table-wrapper">
    <div class="table-header">
        <h3>📋 Bitácora Histórica de Accesos (<?php echo $total; ?>)</h3>
        <div class="table-actions">
            <div class="table-search">
                <span class="search-icon">🔍</span>
                <input type="text" form="advancedFiltersForm" name="q" id="searchInput" placeholder="Buscar por placa..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="view-switcher" role="group" aria-label="Modo de visualización">
                <button type="button"
                        class="view-switch-btn <?php echo $vista === 'tabla' ? 'active' : ''; ?>"
                        id="btnVistaTabla"
                        onclick="setBitacoraView('tabla')"
                        title="Vista en Tabla">
                    📋 Tabla
                </button>
                <button type="button"
                        class="view-switch-btn <?php echo $vista === 'galeria' ? 'active' : ''; ?>"
                        id="btnVistaGaleria"
                        onclick="setBitacoraView('galeria')"
                        title="Vista en Galería">
                    🖼️ Galería
                </button>
            </div>

            <button type="button" class="btn btn-outline btn-sm filter-toggle-btn <?php echo $filtrosActivosCount > 0 ? 'active' : ''; ?>" 
                    id="toggleFiltrosBtn" onclick="toggleAdvancedFilters()">
                🎛️ Filtros Avanzados <?php if ($filtrosActivosCount > 0): ?><span class="filter-badge-count"><?php echo $filtrosActivosCount; ?></span><?php endif; ?> <span id="filterToggleIcon"><?php echo $filtrosActivosCount > 0 ? '▴' : '▾'; ?></span>
            </button>

            <?php if ($filtrosActivosCount > 0 || $search !== ''): ?>
                <a href="<?php echo htmlspecialchars($resetHref); ?>" class="btn btn-outline btn-sm js-reset-filters-link" style="text-decoration:none;" title="Restablecer filtros">
                    Limpiar
                </a>
            <?php endif; ?>

            <a href="../api/exportar_reporte.php?<?php echo htmlspecialchars($filterQuery); ?>"
               class="btn btn-primary btn-sm" id="exportCsvBtn" style="text-decoration:none;">
                📥 Exportar CSV
            </a>
        </div>
    </div>

    <!-- Panel Desplegable de Filtros Avanzados -->
    <div class="advanced-filters-panel <?php echo $filtrosActivosCount > 0 ? 'open' : ''; ?>" id="advancedFiltersPanel">
        <form method="GET" action="bitacora.php" id="advancedFiltersForm">
            <input type="hidden" name="vista" id="vistaInput" value="<?php echo htmlspecialchars($vista); ?>">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>📅 Fecha Desde</label>
                    <input type="date" name="fecha_inicio" class="filter-control-v2"
                           value="<?php echo htmlspecialchars($fechaInicio); ?>">
                </div>
                <div class="filter-group">
                    <label>📅 Fecha Hasta</label>
                    <input type="date" name="fecha_fin" class="filter-control-v2"
                           value="<?php echo htmlspecialchars($fechaFin); ?>">
                </div>
                <div class="filter-group">
                    <label>🚨 Nivel de Alerta</label>
                    <select name="alerta" class="filter-control-v2">
                        <option value="" <?php echo $alertaFiltro === '' ? 'selected' : ''; ?>>Todos los estados</option>
                        <option value="0" <?php echo $alertaFiltro === '0' ? 'selected' : ''; ?>>✅ Permitido (OK)</option>
                        <option value="con_alerta" <?php echo $alertaFiltro === 'con_alerta' ? 'selected' : ''; ?>>🚨 Todas las Alertas</option>
                        <option value="1" <?php echo $alertaFiltro === '1' ? 'selected' : ''; ?>>🔴 No Empadronado</option>
                        <option value="2" <?php echo $alertaFiltro === '2' ? 'selected' : ''; ?>>🟠 Acceso Denegado</option>
                        <option value="3" <?php echo $alertaFiltro === '3' ? 'selected' : ''; ?>>⚫ Lista Negra</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>🚪 Garita / Puerta</label>
                    <select name="puerta" class="filter-control-v2">
                        <option value="" <?php echo $puertaFiltro === '' ? 'selected' : ''; ?>>Todas las puertas</option>
                        <option value="Puerta 1 - Av. Busch" <?php echo $puertaFiltro === 'Puerta 1 - Av. Busch' ? 'selected' : ''; ?>>Puerta 1 - Av. Busch</option>
                        <option value="Puerta 2 - Av. Sanabria" <?php echo $puertaFiltro === 'Puerta 2 - Av. Sanabria' ? 'selected' : ''; ?>>Puerta 2 - Av. Sanabria</option>
                        <option value="Puerta 3 - Av. 26 de Febrero" <?php echo $puertaFiltro === 'Puerta 3 - Av. 26 de Febrero' ? 'selected' : ''; ?>>Puerta 3 - Av. 26 de Febrero</option>
                        <option value="Puerta 4 - Av. Centenario" <?php echo $puertaFiltro === 'Puerta 4 - Av. Centenario' ? 'selected' : ''; ?>>Puerta 4 - Av. Centenario</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>🚗 Tipo de Vehículo</label>
                    <select name="tipo" class="filter-control-v2">
                        <option value="" <?php echo $tipoFiltro === '' ? 'selected' : ''; ?>>Todos los tipos</option>
                        <option value="car" <?php echo $tipoFiltro === 'car' ? 'selected' : ''; ?>>Auto (car)</option>
                        <option value="motorcycle" <?php echo $tipoFiltro === 'motorcycle' ? 'selected' : ''; ?>>Moto (motorcycle)</option>
                        <option value="truck" <?php echo $tipoFiltro === 'truck' ? 'selected' : ''; ?>>Camión (truck)</option>
                        <option value="bus" <?php echo $tipoFiltro === 'bus' ? 'selected' : ''; ?>>Bus (bus)</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>🎨 Color del Vehículo</label>
                    <select name="color" class="filter-control-v2">
                        <option value="" <?php echo $colorFiltro === '' ? 'selected' : ''; ?>>Todos los colores</option>
                        <option value="Blanco" <?php echo $colorFiltro === 'Blanco' ? 'selected' : ''; ?>>Blanco</option>
                        <option value="Gris / Plateado" <?php echo $colorFiltro === 'Gris / Plateado' ? 'selected' : ''; ?>>Gris / Plateado</option>
                        <option value="Negro" <?php echo $colorFiltro === 'Negro' ? 'selected' : ''; ?>>Negro</option>
                        <option value="Rojo" <?php echo $colorFiltro === 'Rojo' ? 'selected' : ''; ?>>Rojo</option>
                        <option value="Azul" <?php echo $colorFiltro === 'Azul' ? 'selected' : ''; ?>>Azul</option>
                        <option value="Verde" <?php echo $colorFiltro === 'Verde' ? 'selected' : ''; ?>>Verde</option>
                        <option value="Amarillo" <?php echo $colorFiltro === 'Amarillo' ? 'selected' : ''; ?>>Amarillo</option>
                        <option value="Naranja" <?php echo $colorFiltro === 'Naranja' ? 'selected' : ''; ?>>Naranja</option>
                        <option value="Celeste" <?php echo $colorFiltro === 'Celeste' ? 'selected' : ''; ?>>Celeste</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>⏱️ Estado de Permanencia</label>
                    <select name="permanencia" class="filter-control-v2">
                        <option value="" <?php echo $permanenciaFiltro === '' ? 'selected' : ''; ?>>Todos los tránsitos</option>
                        <option value="adentro" <?php echo $permanenciaFiltro === 'adentro' ? 'selected' : ''; ?>>🟢 Solo Adentro (En Campus)</option>
                        <option value="salida" <?php echo $permanenciaFiltro === 'salida' ? 'selected' : ''; ?>>🔵 Visitas Cerradas (Ya salieron)</option>
                    </select>
                </div>
            </div>

            <div class="filters-footer">
                <div style="font-size:0.78rem;color:var(--on-surface-muted);">
                    <?php if ($filtrosActivosCount > 0): ?>
                        <span>Hay <strong><?php echo $filtrosActivosCount; ?></strong> filtro(s) activo(s)</span>
                    <?php else: ?>
                        <span>Sin filtros activos</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if ($filtrosActivosCount > 0 || $search !== ''): ?>
                        <a href="<?php echo htmlspecialchars($resetHref); ?>" class="btn btn-outline btn-sm js-reset-filters-link" style="text-decoration:none;">
                            🗑️ Limpiar Todo
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        🔍 Aplicar Filtros
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Vista 1: Tabla (por defecto) -->
    <div class="table-scroll" id="bitacoraTableView"<?php echo $vista === 'galeria' ? ' style="display:none;"' : ''; ?>>
    <table class="data-table" id="bitacoraTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Placa</th>
                <th>Tipo</th>
                <th>Color</th>
                <th>Confianza</th>
                <th>Ingreso</th>
                <th>Puerta Ingreso</th>
                <th>Salida</th>
                <th>Puerta Salida</th>
                <th>Alerta</th>
                <th>Evidencia</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($registros)): ?>
                <tr>
                    <td colspan="11" style="text-align: center; color: var(--on-surface-muted); padding: 40px;">
                        No se encontraron registros.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($registros as $r): ?>
                    <?php
                    $nivel = (int) ($r['alerta_disparada'] ?? 0);
                    $rowClass = '';
                    $badgeClass = 'badge-green';
                    $badgeLabel = 'OK';
                    if ($nivel === 1) {
                        $rowClass = 'alert-red';
                        $badgeClass = 'badge-red';
                        $badgeLabel = '🚨 NO EMPADRONADO';
                    } elseif ($nivel === 2) {
                        $rowClass = 'alert-orange';
                        $badgeClass = 'badge-amber';
                        $badgeLabel = '⚠️ ACCESO DENEGADO';
                    } elseif ($nivel === 3) {
                        $rowClass = 'alert-black';
                        $badgeClass = 'badge-dark';
                        $badgeLabel = '⛔ LISTA NEGRA';
                    }
                    $rid = (int) $r['id'];
                    ?>
                    <tr id="access-row-<?php echo $rid; ?>" class="<?php echo $rowClass; ?>" data-salida="<?php echo $r['hora_salida'] ? '1' : '0'; ?>">
                        <td><?php echo $rid; ?></td>
                        <td style="font-weight: 700; letter-spacing: 0.04em;">
                            <span class="plate-cell-wrap">
                                <span id="plate-text-<?php echo $rid; ?>"><?php echo htmlspecialchars($r['placa']); ?></span>
                                <button type="button" class="btn-icon-edit"
                                        id="edit-plate-btn-<?php echo $rid; ?>"
                                        title="Corregir lectura de placa"
                                        onclick="openCorregirModal(<?php echo $rid; ?>, '<?php echo htmlspecialchars($r['placa'], ENT_QUOTES, 'UTF-8'); ?>')">✏️</button>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($r['tipo_vehiculo_ia'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['color_vehiculo'] ?: '-'); ?></td>
                        <td><?php echo $r['confianza_ia'] ? $r['confianza_ia'] . '%' : '-'; ?></td>
                        <td><?php echo $r['hora_ingreso'] ? date('d/m/Y H:i:s', strtotime($r['hora_ingreso'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($r['puerta_ingreso']); ?></td>
                        <td><?php echo $r['hora_salida'] ? date('d/m/Y H:i:s', strtotime($r['hora_salida'])) : '<span class="badge badge-green">Adentro</span>'; ?></td>
                        <td><?php echo htmlspecialchars($r['puerta_salida'] ?: '-'); ?></td>
                        <td>
                            <span class="badge <?php echo $badgeClass; ?>" id="status-badge-<?php echo $rid; ?>"><?php echo $badgeLabel; ?></span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline" onclick="showEvidence(<?php echo $rid; ?>)">📷 Ver</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Vista 2: Galería Fotográfica -->
    <div class="gallery-view-wrap" id="bitacoraGalleryView"<?php echo $vista !== 'galeria' ? ' style="display:none;"' : ''; ?>>
        <?php if (empty($registros)): ?>
            <div class="gallery-empty">
                No se encontraron registros.
            </div>
        <?php else: ?>
            <div class="gallery-grid" id="bitacoraGalleryGrid">
                <?php foreach ($registros as $r): ?>
                    <?php
                    $nivel = (int) ($r['alerta_disparada'] ?? 0);
                    $rowClass = '';
                    $badgeClass = 'badge-green';
                    $badgeLabel = 'OK';
                    if ($nivel === 1) {
                        $rowClass = 'alert-red';
                        $badgeClass = 'badge-red';
                        $badgeLabel = '🚨 NO EMPADRONADO';
                    } elseif ($nivel === 2) {
                        $rowClass = 'alert-orange';
                        $badgeClass = 'badge-amber';
                        $badgeLabel = '⚠️ ACCESO DENEGADO';
                    } elseif ($nivel === 3) {
                        $rowClass = 'alert-black';
                        $badgeClass = 'badge-dark';
                        $badgeLabel = '⛔ LISTA NEGRA';
                    }
                    $rid = (int) $r['id'];

                    $slides = [];
                    $ingUrl = resolveBitacoraEvidenceUrl($r['evidencia_ingreso_foto'] ?? null);
                    $plaUrl = resolveBitacoraEvidenceUrl($r['evidencia_ingreso_placa'] ?? null);
                    $salUrl = resolveBitacoraEvidenceUrl($r['evidencia_salida_foto'] ?? null);

                    if ($ingUrl !== '') {
                        $slides[] = ['src' => $ingUrl, 'short' => 'Ingreso', 'title' => '📸 Ingreso', 'kind' => 'panoramica'];
                    }
                    if ($plaUrl !== '') {
                        $slides[] = ['src' => $plaUrl, 'short' => 'Placa', 'title' => '🔍 Placa', 'kind' => 'placa'];
                    }
                    if ($salUrl !== '') {
                        $slides[] = ['src' => $salUrl, 'short' => 'Salida', 'title' => '📸 Salida', 'kind' => 'panoramica'];
                    }
                    $slideCount = count($slides);
                    ?>
                    <div class="gallery-card <?php echo $rowClass; ?>"
                         id="gallery-card-<?php echo $rid; ?>"
                         data-salida="<?php echo $r['hora_salida'] ? '1' : '0'; ?>"
                         data-slide-index="0"
                         data-slide-count="<?php echo $slideCount; ?>">
                        <div class="gallery-stage <?php echo $slideCount > 1 ? 'has-multiple' : ''; ?>"
                             <?php if ($slideCount > 1): ?>onclick="stepGallerySlide(<?php echo $rid; ?>, 1, event)" title="Clic para pasar a la siguiente foto"<?php endif; ?>>
                            <?php if ($slideCount === 0): ?>
                                <div class="gallery-no-photo">
                                    <span class="no-photo-icon">📷</span>
                                    <span>Evidencia no disponible</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($slides as $idx => $sl): ?>
                                    <img src="<?php echo htmlspecialchars($sl['src']); ?>"
                                         alt="<?php echo htmlspecialchars($sl['title'] . ' - ' . $r['placa']); ?>"
                                         class="gallery-slide-img <?php echo $sl['kind'] === 'placa' ? 'is-plate-crop' : ''; ?> <?php echo $idx === 0 ? 'active' : ''; ?>"
                                         data-slide="<?php echo $idx; ?>"
                                         data-caption="<?php echo htmlspecialchars($sl['title']); ?>"
                                         onerror="this.src='../assets/img/uagrm_logo.png'">
                                <?php endforeach; ?>
                                <span class="gallery-stage-pill" id="gallery-caption-<?php echo $rid; ?>">
                                    <?php echo htmlspecialchars($slides[0]['title']); ?> (1/<?php echo $slideCount; ?>)
                                </span>
                                <?php if ($slideCount > 1): ?>
                                    <button type="button" class="gallery-nav-btn prev"
                                            onclick="stepGallerySlide(<?php echo $rid; ?>, -1, event)"
                                            aria-label="Imagen anterior" title="Imagen anterior">‹</button>
                                    <button type="button" class="gallery-nav-btn next"
                                            onclick="stepGallerySlide(<?php echo $rid; ?>, 1, event)"
                                            aria-label="Siguiente imagen" title="Siguiente imagen">›</button>
                                    <div class="gallery-dots" onclick="event.stopPropagation()">
                                        <?php foreach ($slides as $idx => $sl): ?>
                                            <button type="button"
                                                    class="gallery-dot-btn <?php echo $idx === 0 ? 'active' : ''; ?>"
                                                    data-dot="<?php echo $idx; ?>"
                                                    onclick="goToGallerySlide(<?php echo $rid; ?>, <?php echo $idx; ?>, event)">
                                                <?php echo htmlspecialchars($sl['short']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span class="badge <?php echo $badgeClass; ?> gallery-stage-alert" id="gallery-status-badge-<?php echo $rid; ?>"><?php echo $badgeLabel; ?></span>
                        </div>

                        <div class="gallery-card-body">
                            <div class="gallery-card-top">
                                <div class="gallery-plate-group">
                                    <span class="gallery-plate-code" id="gallery-plate-text-<?php echo $rid; ?>"><?php echo htmlspecialchars($r['placa']); ?></span>
                                    <button type="button" class="btn-icon-edit"
                                            id="gallery-edit-btn-<?php echo $rid; ?>"
                                            title="Corregir lectura de placa"
                                            onclick="openCorregirModal(<?php echo $rid; ?>, '<?php echo htmlspecialchars($r['placa'], ENT_QUOTES, 'UTF-8'); ?>')">✏️</button>
                                    <span class="gallery-id-tag">#<?php echo $rid; ?></span>
                                </div>
                                <button type="button" class="btn btn-outline btn-xs" onclick="showEvidence(<?php echo $rid; ?>)" title="Ampliar todas las capturas">
                                    🔍 Ampliar
                                </button>
                            </div>

                            <div class="gallery-specs-row">
                                <span class="gallery-chip">🚗 <?php echo htmlspecialchars($r['tipo_vehiculo_ia'] ?: '-'); ?></span>
                                <span class="gallery-chip">🎨 <?php echo htmlspecialchars($r['color_vehiculo'] ?: '-'); ?></span>
                                <span class="gallery-chip">🎯 <?php echo $r['confianza_ia'] ? $r['confianza_ia'] . '%' : '-'; ?></span>
                                <?php if ($r['hora_salida']): ?>
                                    <span class="badge badge-blue">Salida</span>
                                <?php else: ?>
                                    <span class="badge badge-green">Adentro</span>
                                <?php endif; ?>
                            </div>

                            <div class="gallery-meta-list">
                                <div class="gallery-meta-item">
                                    <span class="meta-label">Ingreso:</span>
                                    <span class="meta-val"><?php echo $r['hora_ingreso'] ? date('d/m/Y H:i:s', strtotime($r['hora_ingreso'])) : '-'; ?> · <?php echo htmlspecialchars($r['puerta_ingreso']); ?></span>
                                </div>
                                <div class="gallery-meta-item">
                                    <span class="meta-label">Salida:</span>
                                    <span class="meta-val"><?php echo $r['hora_salida'] ? (date('d/m/Y H:i:s', strtotime($r['hora_salida'])) . ' · ' . htmlspecialchars($r['puerta_salida'] ?: '-')) : 'Aún en campus'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <div class="pagination">
        <div class="page-info">
            Mostrando <?php echo $total > 0 ? ($offset + 1) : 0; ?> a <?php echo min($offset + $limit, $total); ?> de <?php echo $total; ?> registros
        </div>
        <div class="page-btns">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&<?php echo htmlspecialchars($pageQuery); ?>" class="page-btn" id="prevPageBtn">‹</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=<?php echo $i; ?>&<?php echo htmlspecialchars($pageQuery); ?>" 
                   class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"
                   id="pageBtn-<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&<?php echo htmlspecialchars($pageQuery); ?>" class="page-btn" id="nextPageBtn">›</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal reutilizable de Evidencia Fotográfica (RF11) -->
<div class="modal-overlay" id="evidenceModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">Evidencia - Placa: ---</h3>
            <button class="modal-close" onclick="closeModal()" id="modalCloseBtn">&times;</button>
        </div>
        <div class="modal-body">
            <div class="evidence-grid" id="evidenceGrid"></div>
        </div>
    </div>
</div>

<!-- Modal reutilizable de Corrección Manual de Placa (RF12) -->
<div class="modal-overlay" id="corregirPlacaModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3>✏️ Corregir Lectura de Placa</h3>
            <button class="modal-close" onclick="closeCorregirModal()" id="corregirCloseBtn">&times;</button>
        </div>
        <div class="modal-body">
            <div id="corregirPlacaPreview" style="display:none;margin-bottom:14px;text-align:center;">
                <img id="corregirPlacaImg" src="" alt="Captura de placa"
                     style="max-height:110px;border-radius:var(--radius-sm);border:1px solid var(--outline-variant);object-fit:contain;">
            </div>
            <div id="corregirPlacaFeedback" class="login-error" style="display:none;"></div>
            <form id="corregirPlacaForm" onsubmit="submitCorregirPlaca(event)">
                <input type="hidden" id="corregirRegistroId" value="">
                <div class="form-group">
                    <label for="placaCorregidaInput">Matrícula Correcta *</label>
                    <input type="text" id="placaCorregidaInput" maxlength="15" required
                           placeholder="Ej: 233BTP"
                           style="text-transform:uppercase;font-weight:700;letter-spacing:0.08em;font-size:1.05rem;">
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeCorregirModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="guardarCorreccionBtn">Guardar Corrección</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/dashboard.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/dashboard.js') ?: '2'; ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
