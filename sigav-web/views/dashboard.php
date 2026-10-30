<?php
/**
 * SIGAV-UAGRM | Dashboard - Monitor en Vivo
 * Muestra estadísticas en tiempo real y la tabla de últimos accesos.
 * Los datos se actualizan mediante AJAX sin recargar la página.
 */
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (($_GET['error'] ?? '') === 'acceso_denegado'): ?>
    <div class="login-error" id="accessDeniedMsg">
        ⛔ Acceso restringido: su rol operativo no tiene permisos para acceder a módulos administrativos.
    </div>
<?php endif; ?>

<!-- Banner de Alerta de Seguridad en Tiempo Real (RF10) -->
<div class="alert-banner" id="securityAlertBanner" role="alert">
    <span id="securityAlertText"></span>
    <button type="button" class="modal-close" onclick="dismissSecurityBanner()" aria-label="Cerrar alerta">&times;</button>
</div>

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
                <th>Color</th>
                <th>Confianza IA</th>
                <th>Puerta</th>
                <th>Estado</th>
                <th>Evidencia</th>
            </tr>
        </thead>
        <tbody id="liveTableBody">
            <tr>
                <td colspan="8" style="text-align: center; color: var(--on-surface-muted); padding: 40px;">
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

<!-- Modal de Corrección Manual de Placa (RF12) -->
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
