<?php
/**
 * SIGAV-UAGRM | API: Registrar Acceso (Puente para Python)
 * Este archivo recibe un POST en formato JSON desde prueba_ia.py
 * y registra el ingreso o la salida del vehículo en la BD.
 * 
 * SEGURIDAD: Usa PDO Prepared Statements. Sanitiza todas las entradas.
 */
header('Content-Type: application/json; charset=utf-8');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();

    // Leer JSON del cuerpo de la petición
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['placa'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos. Se requiere al menos "placa".']);
        exit;
    }

    // Sanitizar entradas
    $placa             = strtoupper(trim($input['placa']));
    $tipo_vehiculo     = htmlspecialchars(trim($input['tipo_vehiculo'] ?? ''));
    $confianza         = isset($input['confianza']) ? (float) $input['confianza'] : null;
    $puerta            = htmlspecialchars(trim($input['puerta'] ?? 'Cámara Principal'));
    $foto_panoramica   = htmlspecialchars(trim($input['foto_panoramica'] ?? ''));
    $foto_placa        = htmlspecialchars(trim($input['foto_placa'] ?? ''));

    $sentido = strtolower(trim($input['sentido'] ?? 'auto')); // "ingreso", "salida" o "auto"

    // Verificar si la placa tiene una visita abierta (ya está adentro)
    $stmt = $pdo->prepare("
        SELECT id FROM bitacora_accesos 
        WHERE placa = :placa AND hora_salida IS NULL
        ORDER BY hora_ingreso DESC
        LIMIT 1
    ");
    $stmt->execute([':placa' => $placa]);
    $visitaAbierta = $stmt->fetch();

    $esSalida = false;
    if ($sentido === 'salida') {
        $esSalida = true;
    } elseif ($sentido === 'ingreso') {
        $esSalida = false;
    } else {
        // Fallback: Adivinar por estado (comportamiento anterior)
        $esSalida = $visitaAbierta ? true : false;
    }

    if ($esSalida) {
        // ====== ES UNA SALIDA ======
        if ($visitaAbierta) {
            // Actualizar el registro existente
            $stmt = $pdo->prepare("
                UPDATE bitacora_accesos SET
                    hora_salida          = NOW(),
                    puerta_salida        = :puerta,
                    evidencia_salida_foto = :foto
                WHERE id = :id
            ");
            $stmt->execute([
                ':puerta' => $puerta,
                ':foto'   => $foto_panoramica,
                ':id'     => $visitaAbierta['id'],
            ]);
            $recordId = $visitaAbierta['id'];
        } else {
            // El auto está saliendo pero nunca registramos su ingreso (ej: el sistema estaba apagado)
            // Creamos un registro "fantasma" de ingreso y salida inmediata
            $stmt = $pdo->prepare("
                INSERT INTO bitacora_accesos 
                    (placa, tipo_vehiculo_ia, confianza_ia, hora_ingreso, puerta_ingreso, 
                     hora_salida, puerta_salida, evidencia_salida_foto, alerta_disparada)
                VALUES 
                    (:placa, :tipo, :confianza, NOW(), 'No registrada (Sistema offline)', 
                     NOW(), :puerta, :foto, 0)
            ");
            $stmt->execute([
                ':placa'     => $placa,
                ':tipo'      => $tipo_vehiculo,
                ':confianza' => $confianza,
                ':puerta'    => $puerta,
                ':foto'      => $foto_panoramica,
            ]);
            $recordId = $pdo->lastInsertId();
        }

        echo json_encode([
            'status'  => 'SALIDA',
            'message' => "Salida registrada explícitamente para placa {$placa}.",
            'id'      => $recordId,
        ]);

    } else {
        // ====== ES UN INGRESO ======

        // Si ya había una visita abierta y vuelve a entrar por otra cámara de ingreso, 
        // podríamos cerrarla automáticamente o simplemente ignorarla (se asume que salió sin ser visto).
        if ($visitaAbierta) {
            $pdo->prepare("UPDATE bitacora_accesos SET hora_salida = NOW(), puerta_salida = 'Cierre automático (Nuevo Ingreso)' WHERE id = ?")->execute([$visitaAbierta['id']]);
        }

        // Verificar si la placa está en la lista negra del padrón
        $alerta = 0;
        $stmtPadron = $pdo->prepare("
            SELECT estado_acceso FROM padron_vehicular WHERE placa = :placa
        ");
        $stmtPadron->execute([':placa' => $placa]);
        $padron = $stmtPadron->fetch();

        if ($padron && $padron['estado_acceso'] === 'LISTA_NEGRA') {
            $alerta = 1;
        }

        $stmt = $pdo->prepare("
            INSERT INTO bitacora_accesos 
                (placa, tipo_vehiculo_ia, confianza_ia, hora_ingreso, puerta_ingreso, 
                 evidencia_ingreso_foto, evidencia_ingreso_placa, alerta_disparada)
            VALUES 
                (:placa, :tipo, :confianza, NOW(), :puerta, 
                 :foto_panoramica, :foto_placa, :alerta)
        ");
        $stmt->execute([
            ':placa'           => $placa,
            ':tipo'            => $tipo_vehiculo,
            ':confianza'       => $confianza,
            ':puerta'          => $puerta,
            ':foto_panoramica' => $foto_panoramica,
            ':foto_placa'      => $foto_placa,
            ':alerta'          => $alerta,
        ]);

        $newId = $pdo->lastInsertId();
        $statusMsg = $alerta ? 'ALERTA' : 'INGRESO';

        echo json_encode([
            'status'  => $statusMsg,
            'message' => "Ingreso registrado para placa {$placa}.",
            'id'      => $newId,
            'alerta'  => (bool) $alerta,
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
