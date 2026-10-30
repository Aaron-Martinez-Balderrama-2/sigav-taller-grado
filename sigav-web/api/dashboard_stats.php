<?php
/**
 * SIGAV-UAGRM | API: Estadísticas del Dashboard
 * Devuelve JSON con aforo, ingresos, salidas y alertas del día.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();

    // Aforo = vehículos adentro (tienen hora_ingreso pero NO hora_salida)
    $stmt = $pdo->query("SELECT COUNT(*) FROM bitacora_accesos WHERE hora_salida IS NULL");
    $aforo = (int) $stmt->fetchColumn();

    // Ingresos de hoy
    $stmt = $pdo->query("SELECT COUNT(*) FROM bitacora_accesos WHERE DATE(hora_ingreso) = CURDATE()");
    $ingresos = (int) $stmt->fetchColumn();

    // Salidas de hoy
    $stmt = $pdo->query("SELECT COUNT(*) FROM bitacora_accesos WHERE DATE(hora_salida) = CURDATE()");
    $salidas = (int) $stmt->fetchColumn();

    // Alertas de hoy (niveles 1 = No empadronado, 2 = Denegado, 3 = Lista negra)
    $stmt = $pdo->query("SELECT COUNT(*) FROM bitacora_accesos WHERE alerta_disparada > 0 AND DATE(hora_ingreso) = CURDATE()");
    $alertas = (int) $stmt->fetchColumn();

    echo json_encode([
        'aforo'        => $aforo,
        'ingresos_hoy' => $ingresos,
        'salidas_hoy'  => $salidas,
        'alertas'      => $alertas,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
