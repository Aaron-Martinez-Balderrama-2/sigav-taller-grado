<?php
/**
 * SIGAV-UAGRM | Dashboard - Monitor en Vivo
 * Muestra estadísticas en tiempo real y la tabla de últimos accesos.
 * Los datos se actualizan mediante AJAX sin recargar la página.
 */
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Tarjetas de Estadísticas -->
<div class="stats-grid" id="statsGrid">
    <div class="stat-card blue">
        <div class="stat-icon">🚗</div>
        <div class="stat-value" id="statAforo">0</div>
        <div class="stat-label">Vehículos Adentro (Aforo)</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">📥</div>
        <div class="stat-value" id="statIngresos">0</div>
        <div class="stat-label">Ingresos Hoy</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon">📤</div>
        <div class="stat-value" id="statSalidas">0</div>
        <div class="stat-label">Salidas Hoy</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">🚨</div>
        <div class="stat-value" id="statAlertas">0</div>
        <div class="stat-label">Alertas Críticas</div>
    </div>
</div>

<!-- Tabla de Últimos Accesos en Vivo -->
<div class="table-wrapper">
    <div class="table-header">
        <h3>🔴 Últimos Accesos Detectados</h3>
        <div class="live-indicator">
            <span class="live-dot"></span> Actualización automática
        </div>
    </div>
    <div class="table-scroll">
    <table class="data-table" id="liveTable">
        <thead>
            <tr>
                <th>Hora</th>
                <th>Placa</th>
                <th>Tipo</th>
                <th>Confianza IA</th>
                <th>Puerta</th>
                <th>Estado</th>
                <th>Evidencia</th>
            </tr>
        </thead>
        <tbody id="liveTableBody">
            <tr>
                <td colspan="7" style="text-align: center; color: var(--on-surface-muted); padding: 40px;">
                    Esperando detecciones de la IA...
                </td>
            </tr>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal de Evidencia Fotográfica -->
<div class="modal-overlay" id="evidenceModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">Evidencia - Placa: ---</h3>
            <button class="modal-close" onclick="closeModal()" id="modalCloseBtn">&times;</button>
        </div>
        <div class="modal-body">
            <div class="evidence-grid" id="evidenceGrid">
                <!-- Se llena dinámicamente por JS -->
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
