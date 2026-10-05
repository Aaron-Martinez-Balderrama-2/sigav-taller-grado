<?php
/**
 * SIGAV-UAGRM | API: Últimos Accesos (Polling AJAX)
 * Devuelve los registros nuevos desde un ID dado.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    $lastId = (int) ($_GET['last_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT id, placa, tipo_vehiculo_ia, confianza_ia,
               hora_ingreso, puerta_ingreso, 
               hora_salida, puerta_salida,
               alerta_disparada
        FROM bitacora_accesos
        WHERE id > :last_id
        ORDER BY id DESC
        LIMIT 20
    ");
    $stmt->execute([':last_id' => $lastId]);
    $rows = $stmt->fetchAll();

    echo json_encode($rows);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
