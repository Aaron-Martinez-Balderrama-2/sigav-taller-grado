<?php
/**
 * SIGAV-UAGRM | API: Exportación de Reportes a CSV / Excel (RF13)
 * Genera un archivo CSV codificado en UTF-8 con BOM compatible con Microsoft Excel,
 * aplicando los mismos criterios de filtrado de la Bitácora Histórica.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Acceso denegado');
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();

    $search       = trim((string) ($_GET['q'] ?? ''));
    $fechaInicio  = trim((string) ($_GET['fecha_inicio'] ?? ''));
    $fechaFin     = trim((string) ($_GET['fecha_fin'] ?? ''));
    $alertaFiltro = trim((string) ($_GET['alerta'] ?? ''));
    $puertaFiltro = trim((string) ($_GET['puerta'] ?? ''));
    $tipoFiltro        = trim((string) ($_GET['tipo'] ?? ''));
    $colorFiltro       = trim((string) ($_GET['color'] ?? ''));
    $permanenciaFiltro = trim((string) ($_GET['permanencia'] ?? ''));

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]           = "b.placa LIKE :search";
        $params[':search'] = "%{$search}%";
    }
    if ($fechaInicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
        $where[]                 = "DATE(b.hora_ingreso) >= :fecha_inicio";
        $params[':fecha_inicio'] = $fechaInicio;
    }
    if ($fechaFin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
        $where[]              = "DATE(b.hora_ingreso) <= :fecha_fin";
        $params[':fecha_fin'] = $fechaFin;
    }
    if ($alertaFiltro === 'con_alerta') {
        $where[] = "b.alerta_disparada > 0";
    } elseif (in_array($alertaFiltro, ['0', '1', '2', '3'], true)) {
        $where[]           = "b.alerta_disparada = :alerta";
        $params[':alerta'] = (int) $alertaFiltro;
    }
    if ($puertaFiltro !== '') {
        $where[]               = "(b.puerta_ingreso = :puerta_ing OR b.puerta_salida = :puerta_sal)";
        $params[':puerta_ing'] = $puertaFiltro;
        $params[':puerta_sal'] = $puertaFiltro;
    }
    if ($tipoFiltro !== '') {
        $where[]         = "b.tipo_vehiculo_ia = :tipo";
        $params[':tipo'] = $tipoFiltro;
    }
    if ($colorFiltro !== '') {
        $where[]          = "b.color_vehiculo = :color";
        $params[':color'] = $colorFiltro;
    }
    if ($permanenciaFiltro === 'adentro') {
        $where[] = "b.hora_salida IS NULL";
    } elseif ($permanenciaFiltro === 'salida') {
        $where[] = "b.hora_salida IS NOT NULL";
    }

    $sql = "
        SELECT b.id, b.placa, p.propietario, b.tipo_vehiculo_ia, b.color_vehiculo, b.confianza_ia,
               b.hora_ingreso, b.puerta_ingreso, b.hora_salida, b.puerta_salida,
               b.alerta_disparada
        FROM bitacora_accesos b
        LEFT JOIN padron_vehicular p ON b.placa = p.placa
    ";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY b.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_accesos_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // BOM UTF-8 para visualización directa de tildes y caracteres especiales en Excel
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'ID',
        'Placa',
        'Propietario',
        'Tipo Vehículo IA',
        'Color Vehículo IA',
        'Confianza IA (%)',
        'Ingreso',
        'Puerta Ingreso',
        'Salida',
        'Puerta Salida',
        'Estado / Nivel de Alerta'
    ]);

    $mapaAlertas = [
        0 => 'NORMAL (PERMITIDO)',
        1 => 'ALERTA ROJA - NO EMPADRONADO',
        2 => 'ALERTA NARANJA - ACCESO DENEGADO',
        3 => 'ALERTA NEGRA - LISTA NEGRA',
    ];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nivel = (int) ($row['alerta_disparada'] ?? 0);
        $etiquetaAlerta = $mapaAlertas[$nivel] ?? ($nivel > 0 ? 'ALERTA CRÍTICA' : 'NORMAL');

        fputcsv($output, [
            $row['id'],
            $row['placa'],
            $row['propietario'] ?: 'No empadronado',
            $row['tipo_vehiculo_ia'] ?: '-',
            $row['color_vehiculo'] ?: '-',
            $row['confianza_ia'] !== null ? $row['confianza_ia'] : '-',
            $row['hora_ingreso'] ?: '-',
            $row['puerta_ingreso'] ?: '-',
            $row['hora_salida'] ?: 'Adentro',
            $row['puerta_salida'] ?: '-',
            $etiquetaAlerta,
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Error interno al generar el reporte.');
}
