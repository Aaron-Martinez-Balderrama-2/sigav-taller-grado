/**
 * SIGAV-UAGRM | Dashboard AJAX en Tiempo Real
 * Consulta al servidor cada 3 segundos para obtener nuevos accesos.
 */

let lastId = 0;              // Último ID recibido para no repetir filas
const MAX_ROWS = 15;          // Máximo de filas visibles en la tabla
const REFRESH_MS = 3000;      // Intervalo de actualización (3 segundos)
const ALERT_SOUND = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbsGY+K2GY2fDFdUQwT4PI5N+JXDg9cdDy6aFnRTpi0PTwrXVNPWPL7u+1g1hET8Dn8bqOY1BNz+vyt4pcRVbS8/a+k2VRVNfy98OYa1ha2/j8x51wW13g+/3JoHNfYeT+/syjdmFk5/7+zaZ5Y2bo/v7Op3pkaOj+/s6ne2Vp6P7+zqd7ZWno/v7Op3tlaej+/s+nfGZq6f7+0Kh8ZWrp/v7QqHxmaun+/tCofGZq6f7+');

/** Consulta las estadísticas del dashboard */
async function fetchStats() {
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
    try {
        const res = await fetch(`../api/ultimos_accesos.php?last_id=${lastId}`);
        if (!res.ok) return;
        const rows = await res.json();

        if (rows.length === 0) return;

        const tbody = document.getElementById('liveTableBody');

        // Limpiar mensaje de "esperando..."
        if (lastId === 0 && tbody.querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.classList.add('new-entry');

            // Si es alerta, cambiar la clase
            if (row.alerta_disparada == 1) {
                tr.classList.remove('new-entry');
                tr.classList.add('alert-row');
                try { ALERT_SOUND.play(); } catch(e) {}
            }

            const hora = row.hora_salida || row.hora_ingreso;
            const estado = row.hora_salida ? 'SALIDA' : 'INGRESO';
            const badgeClass = row.alerta_disparada == 1 ? 'badge-red' :
                               (row.hora_salida ? 'badge-amber' : 'badge-green');
            const puerta = row.hora_salida ? (row.puerta_salida || '-') : row.puerta_ingreso;

            tr.innerHTML = `
                <td>${formatTime(hora)}</td>
                <td style="font-weight: 700; letter-spacing: 0.05em;">${esc(row.placa)}</td>
                <td>${esc(row.tipo_vehiculo_ia || '-')}</td>
                <td>${row.confianza_ia ? row.confianza_ia + '%' : '-'}</td>
                <td>${esc(puerta)}</td>
                <td><span class="badge ${badgeClass}">${estado}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="showEvidence(${row.id})" 
                            id="evidence-btn-${row.id}">📷 Ver</button>
                </td>
            `;

            tbody.insertBefore(tr, tbody.firstChild);

            // Quitar la animación tras 3 segundos
            setTimeout(() => {
                tr.classList.remove('new-entry');
                tr.classList.remove('alert-row');
            }, 3000);

            if (row.id > lastId) lastId = row.id;
        });

        // Limitar filas visibles
        while (tbody.children.length > MAX_ROWS) {
            tbody.removeChild(tbody.lastChild);
        }

    } catch (e) {
        console.warn('[SIGAV] Error al obtener accesos:', e);
    }
}

/** Modal de evidencia fotográfica */
async function showEvidence(id) {
    try {
        const res = await fetch(`../api/evidencia.php?id=${id}`);
        if (!res.ok) return;
        const data = await res.json();

        document.getElementById('modalTitle').textContent = `Evidencia - Placa: ${data.placa}`;
        
        const grid = document.getElementById('evidenceGrid');
        grid.innerHTML = '';

        const fotos = [
            { src: data.evidencia_ingreso_foto, label: '📸 Foto Panorámica (Ingreso)' },
            { src: data.evidencia_ingreso_placa, label: '🔍 Recorte de Placa' },
            { src: data.evidencia_salida_foto, label: '📸 Foto Panorámica (Salida)' },
        ];

        fotos.forEach(f => {
            if (!f.src) return;
            const card = document.createElement('div');
            card.className = 'evidence-card';
            card.innerHTML = `
                <img src="../assets/img/evidencias/${esc(f.src)}" alt="${esc(f.label)}" 
                     onerror="this.src='../assets/img/uagrm_logo.png'">
                <div class="evidence-label">${esc(f.label)}</div>
            `;
            grid.appendChild(card);
        });

        document.getElementById('evidenceModal').classList.add('show');
    } catch (e) {
        console.warn('[SIGAV] Error al cargar evidencia:', e);
    }
}

function closeModal() {
    document.getElementById('evidenceModal').classList.remove('show');
}

// Cerrar modal con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

// Click fuera del modal
document.getElementById('evidenceModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/** Utilidades */
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function formatTime(datetime) {
    if (!datetime) return '-';
    const d = new Date(datetime);
    return d.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function animateNumber(elementId, targetValue) {
    const el = document.getElementById(elementId);
    const current = parseInt(el.textContent) || 0;
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

/** Iniciar polling */
fetchStats();
fetchAccesos();
setInterval(() => {
    fetchStats();
    fetchAccesos();
}, REFRESH_MS);
