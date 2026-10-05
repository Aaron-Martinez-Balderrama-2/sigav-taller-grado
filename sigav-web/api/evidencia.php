<?php
/**
 * SIGAV-UAGRM | API: Evidencia Fotográfica
 * Devuelve los datos de un registro específico para el modal.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID inválido.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT placa, tipo_vehiculo_ia, confianza_ia,
               hora_ingreso, puerta_ingreso,
               evidencia_ingreso_foto, evidencia_ingreso_placa,
               hora_salida, puerta_salida, evidencia_salida_foto,
               alerta_disparada
        FROM bitacora_accesos
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Registro no encontrado.']);
        exit;
    }

    echo json_encode($row);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
