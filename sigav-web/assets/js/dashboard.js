/**
 * SIGAV-UAGRM | Dashboard AJAX en Tiempo Real + Modales Compartidos (Evidencia RF8 / Corrección RF12 / Alertas RF10)
 * Consulta al servidor cada 3 segundos en el Monitor en Vivo y provee funciones reutilizables para Bitácora.
 */

let lastId = 0;               // Último ID recibido para no repetir filas
const MAX_ROWS = 15;          // Máximo de filas visibles en la tabla en vivo
const REFRESH_MS = 3000;      // Intervalo de actualización (3 segundos)
const ALERT_SOUND = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbsGY+K2GY2fDFdUQwT4PI5N+JXDg9cdDy6aFnRTpi0PTwrXVNPWPL7u+1g1hET8Dn8bqOY1BNz+vyt4pcRVbS8/a+k2VRVNfy98OYa1ha2/j8x51wW13g+/3JoHNfYeT+/syjdmFk5/7+zaZ5Y2bo/v7Op3pkaOj+/s6ne2Vp6P7+zqd7ZWno/v7Op3tlaej+/s+nfGZq6f7+0Kh8ZWrp/v7QqHxmaun+/tCofGZq6f7+');

/** Clasificación visual diferenciada por nivel de alerta (RF10) */
function getAlertMeta(nivelRaw, horaSalida, esBitacora = false) {
    const nivel = parseInt(nivelRaw, 10) || 0;
    if (nivel === 1) {
        return {
            badgeClass: 'badge-red',
            label: '🚨 NO EMPADRONADO',
            rowClass: 'alert-red',
            bannerClass: 'red',
            bannerMsg: '🚨 ALERTA ROJA: Vehículo NO EMPADRONADO detectado — Placa '
        };
    }
    if (nivel === 2) {
        return {
            badgeClass: 'badge-amber',
            label: '⚠️ ACCESO DENEGADO',
            rowClass: 'alert-orange',
            bannerClass: 'orange',
            bannerMsg: '⚠️ ALERTA NARANJA: Vehículo con ACCESO DENEGADO — Placa '
        };
    }
    if (nivel === 3) {
        return {
            badgeClass: 'badge-dark',
            label: '⛔ LISTA NEGRA',
            rowClass: 'alert-black',
            bannerClass: 'black',
            bannerMsg: '⛔ ALERTA NEGRA (LISTA NEGRA): Vehículo boletinado detectado — Placa '
        };
    }
    if (esBitacora) {
        return { badgeClass: 'badge-green', label: 'OK', rowClass: '', bannerClass: '', bannerMsg: '' };
    }
    if (horaSalida) {
        return { badgeClass: 'badge-blue', label: 'SALIDA', rowClass: '', bannerClass: '', bannerMsg: '' };
    }
    return { badgeClass: 'badge-green', label: 'OK · INGRESO', rowClass: '', bannerClass: '', bannerMsg: '' };
}

function showSecurityBanner(meta, placa) {
    const banner = document.getElementById('securityAlertBanner');
    const textEl = document.getElementById('securityAlertText');
    if (!banner || !textEl || !meta.bannerClass) return;

    banner.className = `alert-banner show ${meta.bannerClass}`;
    textEl.textContent = `${meta.bannerMsg}${placa}`;
}

function dismissSecurityBanner() {
    const banner = document.getElementById('securityAlertBanner');
    if (banner) banner.classList.remove('show');
}

function resolveEvidencePath(src) {
    if (!src) return null;
    const clean = String(src).trim();
    if (!clean) return null;
    if (clean.startsWith('data:image/') || clean.startsWith('http://') || clean.startsWith('https://') || clean.startsWith('../')) {
        return clean;
    }
    if (clean.startsWith('assets/')) {
        return '../' + clean;
    }
    return '../assets/img/evidencias/' + encodeURIComponent(clean);
}

/** Consulta las estadísticas del dashboard */
async function fetchStats() {
    if (!document.getElementById('statAforo')) return;
    try {
        const res = await fetch('../api/dashboard_stats.php');
        if (!res.ok) return;
        const data = await res.json();

        animateNumber('statAforo', data.aforo || 0);
        animateNumber('statIngresos', data.ingresos_hoy || 0);
        animateNumber('statSalidas', data.salidas_hoy || 0);
        animateNumber('statAlertas', data.alertas || 0);
    } catch (e) {
        console.warn('[SIGAV] Error al obtener estadísticas:', e);
    }
}

/** Consulta los últimos accesos nuevos */
async function fetchAccesos() {
    const tbody = document.getElementById('liveTableBody');
    if (!tbody) return;

    try {
        const isFirstLoad = (lastId === 0);
        const res = await fetch(`../api/ultimos_accesos.php?last_id=${lastId}`);
        if (!res.ok) return;
        const rows = await res.json();

        if (!Array.isArray(rows) || rows.length === 0) return;

        // Limpiar mensaje de "esperando..."
        if (isFirstLoad && tbody.querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }

        // Insertar en orden cronológico ascendente para que el más reciente quede arriba
        const ordered = [...rows].reverse();

        ordered.forEach(row => {
            const tr = document.createElement('tr');
            tr.id = `access-row-${row.id}`;
            tr.dataset.salida = row.hora_salida ? '1' : '0';

            const nivelAlerta = parseInt(row.alerta_disparada, 10) || 0;
            const meta = getAlertMeta(nivelAlerta, row.hora_salida, false);

            if (meta.rowClass) {
                tr.classList.add(meta.rowClass);
                if (!isFirstLoad) {
                    try { ALERT_SOUND.play(); } catch (e) {}
                    showSecurityBanner(meta, row.placa);
                }
            } else {
                tr.classList.add('new-entry');
                setTimeout(() => {
                    tr.classList.remove('new-entry');
                }, 3000);
            }

            const hora = row.hora_salida || row.hora_ingreso;
            const puerta = row.hora_salida ? (row.puerta_salida || '-') : row.puerta_ingreso;

            tr.innerHTML = `
                <td>${formatTime(hora)}</td>
                <td style="font-weight: 700; letter-spacing: 0.05em;">
                    <span class="plate-cell-wrap">
                        <span id="plate-text-${row.id}">${esc(row.placa)}</span>
                        <button type="button" class="btn-icon-edit"
                                id="edit-plate-btn-${row.id}"
                                title="Corregir lectura de placa"
                                onclick="openCorregirModal(${row.id}, '${escAttr(row.placa)}')">✏️</button>
                    </span>
                </td>
                <td>${esc(row.tipo_vehiculo_ia || '-')}</td>
                <td>${esc(row.color_vehiculo || '-')}</td>
                <td>${row.confianza_ia ? row.confianza_ia + '%' : '-'}</td>
                <td>${esc(puerta)}</td>
                <td><span class="badge ${meta.badgeClass}" id="status-badge-${row.id}">${meta.label}</span></td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline" onclick="showEvidence(${row.id})" 
                            id="evidence-btn-${row.id}">📷 Ver</button>
                </td>
            `;

            tbody.insertBefore(tr, tbody.firstChild);

            if (Number(row.id) > lastId) lastId = Number(row.id);
        });

        // Limitar filas visibles
        while (tbody.children.length > MAX_ROWS) {
            tbody.removeChild(tbody.lastChild);
        }

    } catch (e) {
        console.warn('[SIGAV] Error al obtener accesos:', e);
    }
}

/** Modal de evidencia fotográfica (RF8 / RF11) */
async function showEvidence(id) {
    try {
        const res = await fetch(`../api/evidencia.php?id=${id}`);
        if (!res.ok) return;
        const data = await res.json();

        document.getElementById('modalTitle').textContent = `Evidencia - Placa: ${data.placa}`;
        
        const grid = document.getElementById('evidenceGrid');
        grid.innerHTML = '';

        const fotos = [
            { src: resolveEvidencePath(data.evidencia_ingreso_foto), label: '📸 Foto Panorámica (Ingreso)' },
            { src: resolveEvidencePath(data.evidencia_ingreso_placa), label: '🔍 Recorte de Placa' },
            { src: resolveEvidencePath(data.evidencia_salida_foto), label: '📸 Foto Panorámica (Salida)' },
        ];

        let count = 0;
        fotos.forEach(f => {
            if (!f.src) return;
            count++;
            const card = document.createElement('div');
            card.className = 'evidence-card';
            card.innerHTML = `
                <img src="${escAttr(f.src)}" alt="${escAttr(f.label)}" 
                     onerror="this.src='../assets/img/uagrm_logo.png'">
                <div class="evidence-label">${esc(f.label)}</div>
            `;
            grid.appendChild(card);
        });

        if (count === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--on-surface-muted);padding:24px;">No existen capturas fotográficas almacenadas para este registro.</div>';
        }

        document.getElementById('evidenceModal').classList.add('show');
    } catch (e) {
        console.warn('[SIGAV] Error al cargar evidencia:', e);
    }
}

function closeModal() {
    const modal = document.getElementById('evidenceModal');
    if (modal) modal.classList.remove('show');
}

/** Modal de Corrección Manual de Placa (RF12) */
async function openCorregirModal(id, fallbackPlaca) {
    const modal = document.getElementById('corregirPlacaModal');
    if (!modal) return;

    const spanPlaca = document.getElementById(`plate-text-${id}`);
    const placaActual = spanPlaca ? spanPlaca.textContent.trim() : (fallbackPlaca || '');

    document.getElementById('corregirRegistroId').value = id;
    const input = document.getElementById('placaCorregidaInput');
    input.value = placaActual;

    const feedback = document.getElementById('corregirPlacaFeedback');
    if (feedback) {
        feedback.style.display = 'none';
        feedback.textContent = '';
    }

    // Mostrar vista previa del recorte de placa si existe para facilitar la verificación visual
    const previewWrap = document.getElementById('corregirPlacaPreview');
    const previewImg = document.getElementById('corregirPlacaImg');
    if (previewWrap && previewImg) {
        previewWrap.style.display = 'none';
        fetch(`../api/evidencia.php?id=${id}`)
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                const imgPath = resolveEvidencePath(data.evidencia_ingreso_placa || data.evidencia_ingreso_foto);
                if (imgPath) {
                    previewImg.src = imgPath;
                    previewWrap.style.display = 'block';
                }
            })
            .catch(() => {});
    }

    modal.classList.add('show');
    setTimeout(() => {
        input.focus();
        input.select();
    }, 60);
}

function closeCorregirModal() {
    const modal = document.getElementById('corregirPlacaModal');
    if (modal) modal.classList.remove('show');
}

async function submitCorregirPlaca(event) {
    event.preventDefault();
    const id = parseInt(document.getElementById('corregirRegistroId').value, 10);
    const input = document.getElementById('placaCorregidaInput');
    const placa = (input.value || '').trim().toUpperCase();
    const feedback = document.getElementById('corregirPlacaFeedback');
    const btn = document.getElementById('guardarCorreccionBtn');

    if (!id || !/^[A-Z0-9\-]{3,15}$/.test(placa)) {
        if (feedback) {
            feedback.textContent = 'Ingrese una placa alfanumérica válida (3 a 15 caracteres).';
            feedback.style.display = 'block';
        }
        return;
    }

    if (btn) btn.disabled = true;
    try {
        const res = await fetch('../api/corregir_placa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, placa })
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            if (feedback) {
                feedback.textContent = data.error || 'No se pudo guardar la corrección.';
                feedback.style.display = 'block';
            }
            return;
        }

        // Actualizar texto de placa en la fila y en la tarjeta de galería sin recargar
        const spanPlaca = document.getElementById(`plate-text-${id}`);
        if (spanPlaca) spanPlaca.textContent = data.placa;
        const galleryPlaca = document.getElementById(`gallery-plate-text-${id}`);
        if (galleryPlaca) galleryPlaca.textContent = data.placa;

        // Actualizar insignia y color de alerta recalculado (RF10)
        const tr = document.getElementById(`access-row-${id}`);
        const badge = document.getElementById(`status-badge-${id}`);
        const gCard = document.getElementById(`gallery-card-${id}`);
        const gBadge = document.getElementById(`gallery-status-badge-${id}`);
        const esBitacora = !!document.getElementById('bitacoraTable');
        const tieneSalida = tr ? (tr.dataset.salida === '1' || !!data.hora_salida) : !!data.hora_salida;
        const meta = getAlertMeta(data.alerta_disparada, tieneSalida, esBitacora);

        if (tr) {
            tr.classList.remove('alert-row', 'alert-red', 'alert-orange', 'alert-black');
            if (meta.rowClass) tr.classList.add(meta.rowClass);
        }
        if (badge) {
            badge.className = `badge ${meta.badgeClass}`;
            badge.textContent = meta.label;
        }
        if (gCard) {
            gCard.classList.remove('alert-row', 'alert-red', 'alert-orange', 'alert-black');
            if (meta.rowClass) gCard.classList.add(meta.rowClass);
        }
        if (gBadge) {
            gBadge.className = `badge ${meta.badgeClass} gallery-stage-alert`;
            gBadge.textContent = meta.label;
        }

        closeCorregirModal();
        fetchStats();
    } catch (e) {
        if (feedback) {
            feedback.textContent = 'Error de conexión con el servidor.';
            feedback.style.display = 'block';
        }
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Cerrar modales con Escape o clic fuera
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal();
        closeCorregirModal();
    }
});

const evidenceModalEl = document.getElementById('evidenceModal');
if (evidenceModalEl) {
    evidenceModalEl.addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
}

const corregirModalEl = document.getElementById('corregirPlacaModal');
if (corregirModalEl) {
    corregirModalEl.addEventListener('click', function(e) {
        if (e.target === this) closeCorregirModal();
    });
}

/** Utilidades */
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function escAttr(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function formatTime(datetime) {
    if (!datetime) return '-';
    const d = new Date(datetime.replace(' ', 'T'));
    if (isNaN(d.getTime())) return datetime;
    return d.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function animateNumber(elementId, targetValue) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const current = parseInt(el.textContent, 10) || 0;
    if (current === targetValue) return;

    const diff = targetValue - current;
    const steps = 15;
    const increment = diff / steps;
    let step = 0;

    const timer = setInterval(() => {
        step++;
        el.textContent = Math.round(current + (increment * step));
        if (step >= steps) {
            el.textContent = targetValue;
            clearInterval(timer);
        }
    }, 30);
}

/** Conmutar panel de filtros avanzados en Bitácora */
function toggleAdvancedFilters() {
    const panel = document.getElementById('advancedFiltersPanel');
    const icon = document.getElementById('filterToggleIcon');
    if (!panel) return;
    panel.classList.toggle('open');
    if (icon) {
        icon.textContent = panel.classList.contains('open') ? '▴' : '▾';
    }
}

/** Alternar instantáneamente entre Vista Tabla y Vista Galería en Bitácora */
function setBitacoraView(mode) {
    const view = (mode === 'galeria') ? 'galeria' : 'tabla';
    const tableView = document.getElementById('bitacoraTableView');
    const galleryView = document.getElementById('bitacoraGalleryView');
    const btnTabla = document.getElementById('btnVistaTabla');
    const btnGaleria = document.getElementById('btnVistaGaleria');
    const vistaInput = document.getElementById('vistaInput');

    if (tableView) tableView.style.display = (view === 'tabla') ? '' : 'none';
    if (galleryView) galleryView.style.display = (view === 'galeria') ? '' : 'none';
    if (btnTabla) btnTabla.classList.toggle('active', view === 'tabla');
    if (btnGaleria) btnGaleria.classList.toggle('active', view === 'galeria');
    if (vistaInput) vistaInput.value = view;

    // Actualizar URL actual sin recargar para persistir si el usuario presiona F5
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('vista', view);
        window.history.replaceState({}, '', url.toString());
    } catch (e) {}

    // Sincronizar enlaces de paginación y restablecimiento de filtros
    document.querySelectorAll('.pagination a.page-btn').forEach(link => {
        try {
            const linkUrl = new URL(link.href, window.location.origin);
            linkUrl.searchParams.set('vista', view);
            link.setAttribute('href', linkUrl.search);
        } catch (e) {}
    });
    document.querySelectorAll('.js-reset-filters-link').forEach(link => {
        link.setAttribute('href', view === 'galeria' ? 'bitacora.php?vista=galeria' : 'bitacora.php');
    });
}

/** Ir a una captura específica dentro de una tarjeta de Galería */
function goToGallerySlide(id, targetIndex, event) {
    if (event) event.stopPropagation();
    const card = document.getElementById(`gallery-card-${id}`);
    if (!card) return;

    const count = parseInt(card.dataset.slideCount, 10) || 0;
    if (count <= 1) return;

    const nextIdx = ((targetIndex % count) + count) % count;
    card.dataset.slideIndex = String(nextIdx);

    const imgs = card.querySelectorAll('.gallery-slide-img');
    let activeCaption = '';
    imgs.forEach((img, idx) => {
        const isActive = (idx === nextIdx);
        img.classList.toggle('active', isActive);
        if (isActive) {
            activeCaption = img.dataset.caption || '';
        }
    });

    card.querySelectorAll('.gallery-dot-btn').forEach((dot, idx) => {
        dot.classList.toggle('active', idx === nextIdx);
    });

    const captionEl = document.getElementById(`gallery-caption-${id}`);
    if (captionEl && activeCaption) {
        captionEl.textContent = `${activeCaption} (${nextIdx + 1}/${count})`;
    }
}

/** Avanzar o retroceder entre las capturas de una tarjeta de Galería */
function stepGallerySlide(id, delta, event) {
    if (event) event.stopPropagation();
    const card = document.getElementById(`gallery-card-${id}`);
    if (!card) return;

    const current = parseInt(card.dataset.slideIndex, 10) || 0;
    goToGallerySlide(id, current + delta, null);
}

/** Iniciar polling únicamente si estamos en el Monitor en Vivo */
if (document.getElementById('liveTableBody')) {
    fetchStats();
    fetchAccesos();
    setInterval(() => {
        fetchStats();
        fetchAccesos();
    }, REFRESH_MS);
}
