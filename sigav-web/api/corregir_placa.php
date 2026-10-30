<?php
/**
 * SIGAV-UAGRM | API: Corrección Manual de Placas (RF12)
 * Permite a los operadores autenticados (Guardia o Administrador) corregir
 * errores de lectura OCR en bitacora_accesos y recalcula el nivel de alerta (RF10).
 */
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado. Inicie sesión.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $id          = (int) ($data['id'] ?? 0);
    $placa_nueva = strtoupper(trim((string) ($data['placa'] ?? '')));

    if ($id <= 0 || !preg_match('/^[A-Z0-9\-]{3,15}$/', $placa_nueva)) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos inválidos. Ingrese una placa alfanumérica válida (3 a 15 caracteres).']);
        exit;
    }

    $pdo = getDBConnection();

    // Verificar existencia del registro en bitácora
    $check = $pdo->prepare("SELECT id, hora_salida FROM bitacora_accesos WHERE id = :id");
    $check->execute([':id' => $id]);
    $registro = $check->fetch();

    if (!$registro) {
        http_response_code(404);
        echo json_encode(['error' => 'Registro de acceso no encontrado.']);
        exit;
    }

    // Reevaluar placa corregida contra el padrón vehicular (RF10)
    $stmtPadron = $pdo->prepare("SELECT estado_acceso FROM padron_vehicular WHERE placa = :placa");
    $stmtPadron->execute([':placa' => $placa_nueva]);
    $padron = $stmtPadron->fetch();

    if (!$padron) {
        $alerta = 1; // 1 = ROJO (No Empadronado)
    } elseif ($padron['estado_acceso'] === 'DENEGADO') {
        $alerta = 2; // 2 = NARANJA (Acceso Denegado)
    } elseif ($padron['estado_acceso'] === 'LISTA_NEGRA') {
        $alerta = 3; // 3 = NEGRO (Lista Negra)
    } else {
        $alerta = 0; // 0 = VERDE (Permitido)
    }

    $stmt = $pdo->prepare("UPDATE bitacora_accesos SET placa = :placa, alerta_disparada = :alerta WHERE id = :id");
    $stmt->execute([
        ':placa'  => $placa_nueva,
        ':alerta' => $alerta,
        ':id'     => $id,
    ]);

    echo json_encode([
        'success'          => true,
        'id'               => $id,
        'placa'            => $placa_nueva,
        'alerta_disparada' => $alerta,
        'hora_salida'      => $registro['hora_salida'],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al actualizar la placa.']);
}
