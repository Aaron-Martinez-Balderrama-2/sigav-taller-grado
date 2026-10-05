<?php
/**
 * SIGAV-UAGRM | API: Estadísticas del Dashboard
 * Devuelve JSON con aforo, ingresos, salidas y alertas del día.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    $today = date('Y-m-d');

    // Aforo = vehículos adentro (tienen hora_ingreso HOY pero NO hora_salida)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bitacora_accesos WHERE hora_salida IS NULL");
    $stmt->execute();
    $aforo = (int) $stmt->fetchColumn();

    // Ingresos de hoy
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bitacora_accesos WHERE DATE(hora_ingreso) = :today");
    $stmt->execute([':today' => $today]);
    $ingresos = (int) $stmt->fetchColumn();

    // Salidas de hoy
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bitacora_accesos WHERE DATE(hora_salida) = :today");
    $stmt->execute([':today' => $today]);
    $salidas = (int) $stmt->fetchColumn();

    // Alertas de hoy
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bitacora_accesos WHERE alerta_disparada = 1 AND DATE(hora_ingreso) = :today");
    $stmt->execute([':today' => $today]);
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
