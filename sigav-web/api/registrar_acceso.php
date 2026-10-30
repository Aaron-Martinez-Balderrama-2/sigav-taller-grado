<?php
/**
 * SIGAV-UAGRM | API: Registrar Acceso (Puente para Python)
 * Recibe un POST en formato JSON desde prueba_ia.py, almacena las evidencias
 * fotográficas físicas en disco (RF8), evalúa alertas diferenciadas (RF10)
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

/**
 * Decodifica una imagen en Base64 y la guarda físicamente en assets/img/evidencias/.
 * Devuelve únicamente el nombre del archivo generado para almacenar en VARCHAR(255).
 */
function guardarEvidenciaFisica(?string $raw, string $prefijo, string $placa): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    $ext = 'jpg';
    $esDataUri = false;
    if (preg_match('#^data:image/(\w+);base64,#i', $raw, $matches)) {
        $esDataUri = true;
        $formato = strtolower($matches[1]);
        if ($formato === 'png') {
            $ext = 'png';
        } elseif ($formato === 'webp') {
            $ext = 'webp';
        }
        $raw = preg_replace('#^data:image/\w+;base64,#i', '', $raw);
    }

    // Si es un Data URI o una cadena Base64 larga, decodificar y guardar en disco
    if ($esDataUri || strlen($raw) > 120) {
        $binario = base64_decode(str_replace(' ', '+', $raw), true);
        if ($binario === false || strlen($binario) === 0) {
            return null;
        }

        $dirDestino = __DIR__ . '/../assets/img/evidencias';
        if (!is_dir($dirDestino)) {
            mkdir($dirDestino, 0775, true);
        }

        $placaLimpia   = preg_replace('/[^A-Za-z0-9]/', '', $placa) ?: 'SINPLACA';
        $sufijoUnico   = substr(bin2hex(random_bytes(2)), 0, 4);
        $nombreArchivo = "{$prefijo}_{$placaLimpia}_" . time() . "_{$sufijoUnico}.{$ext}";
        $rutaCompleta  = $dirDestino . '/' . $nombreArchivo;

        if (file_put_contents($rutaCompleta, $binario) === false) {
            return null;
        }
        return $nombreArchivo;
    }

    // Si se envió directamente un nombre de archivo corto ya existente
    $limpio = preg_replace('/[^A-Za-z0-9._\-]/', '', basename($raw));
    return ($limpio !== '' && strlen($limpio) <= 255) ? $limpio : null;
}

try {
    $pdo = getDBConnection();

    // Leer JSON del cuerpo de la petición
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['placa'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos. Se requiere al menos "placa".']);
        exit;
    }

    // Sanitizar entradas de texto (sin corromper el Base64 de las imágenes)
    $placa          = strtoupper(trim((string) $input['placa']));
    $tipo_vehiculo  = htmlspecialchars(trim((string) ($input['tipo_vehiculo'] ?? '')));
    $color_vehiculo = htmlspecialchars(trim((string) ($input['color_vehiculo'] ?? '')));
    $confianza      = isset($input['confianza']) ? (float) $input['confianza'] : null;
    $puerta         = htmlspecialchars(trim((string) ($input['puerta'] ?? 'Cámara Principal')));
    $raw_panoramica = (string) ($input['foto_panoramica'] ?? $input['evidencia_ingreso_foto'] ?? $input['evidencia_salida_foto'] ?? '');
    $raw_placa      = (string) ($input['foto_placa'] ?? $input['evidencia_ingreso_placa'] ?? '');

    $sentido = strtolower(trim((string) ($input['sentido'] ?? 'auto'))); // "ingreso", "salida" o "auto"

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
        $archivoSalida = guardarEvidenciaFisica($raw_panoramica, 'salida', $placa);

        if ($visitaAbierta) {
            // Actualizar el registro existente
            $stmt = $pdo->prepare("
                UPDATE bitacora_accesos SET
                    hora_salida           = NOW(),
                    puerta_salida         = :puerta,
                    evidencia_salida_foto = :foto
                WHERE id = :id
            ");
            $stmt->execute([
                ':puerta' => $puerta,
                ':foto'   => $archivoSalida,
                ':id'     => $visitaAbierta['id'],
            ]);
            $recordId = (int) $visitaAbierta['id'];
        } else {
            // El auto está saliendo pero nunca registramos su ingreso (ej: el sistema estaba apagado)
            $stmt = $pdo->prepare("
                INSERT INTO bitacora_accesos 
                    (placa, tipo_vehiculo_ia, color_vehiculo, confianza_ia, hora_ingreso, puerta_ingreso, 
                     hora_salida, puerta_salida, evidencia_salida_foto, alerta_disparada)
                VALUES 
                    (:placa, :tipo, :color, :confianza, NOW(), 'No registrada (Sistema offline)', 
                     NOW(), :puerta, :foto, 0)
            ");
            $stmt->execute([
                ':placa'     => $placa,
                ':tipo'      => $tipo_vehiculo,
                ':color'     => $color_vehiculo ?: null,
                ':confianza' => $confianza,
                ':puerta'    => $puerta,
                ':foto'      => $archivoSalida,
            ]);
            $recordId = (int) $pdo->lastInsertId();
        }

        echo json_encode([
            'status'         => 'SALIDA',
            'message'        => "Salida registrada explícitamente para placa {$placa}.",
            'id'             => $recordId,
            'color_vehiculo' => $color_vehiculo,
            'evidencia_foto' => $archivoSalida,
        ]);

    } else {
        // ====== ES UN INGRESO ======
        // Si ya había una visita abierta y vuelve a entrar por otra cámara de ingreso, cerrarla automáticamente
        if ($visitaAbierta) {
            $pdo->prepare("UPDATE bitacora_accesos SET hora_salida = NOW(), puerta_salida = 'Cierre automático (Nuevo Ingreso)' WHERE id = ?")->execute([$visitaAbierta['id']]);
        }

        // Guardar evidencias fotográficas físicas en disco (RF8)
        $archivoIngreso = guardarEvidenciaFisica($raw_panoramica, 'ingreso', $placa);
        $archivoPlaca   = guardarEvidenciaFisica($raw_placa, 'placa', $placa);

        // Clasificación de alerta diferenciada por niveles (RF10):
        // 0 = PERMITIDO (Verde), 1 = NO EMPADRONADO (Rojo), 2 = DENEGADO (Naranja), 3 = LISTA NEGRA (Negro)
        $stmtPadron = $pdo->prepare("
            SELECT estado_acceso FROM padron_vehicular WHERE placa = :placa
        ");
        $stmtPadron->execute([':placa' => $placa]);
        $padron = $stmtPadron->fetch();

        if (!$padron) {
            $alerta = 1; // 1 = ROJO (No Empadronado)
        } elseif ($padron['estado_acceso'] === 'DENEGADO') {
            $alerta = 2; // 2 = NARANJA (Restringido / Denegado)
        } elseif ($padron['estado_acceso'] === 'LISTA_NEGRA') {
            $alerta = 3; // 3 = NEGRO (Lista Negra / Boletinado)
        } else {
            $alerta = 0; // 0 = VERDE (Permitido)
        }

        $stmt = $pdo->prepare("
            INSERT INTO bitacora_accesos 
                (placa, tipo_vehiculo_ia, color_vehiculo, confianza_ia, hora_ingreso, puerta_ingreso, 
                 evidencia_ingreso_foto, evidencia_ingreso_placa, alerta_disparada)
            VALUES 
                (:placa, :tipo, :color, :confianza, NOW(), :puerta, 
                 :foto_panoramica, :foto_placa, :alerta)
        ");
        $stmt->execute([
            ':placa'           => $placa,
            ':tipo'            => $tipo_vehiculo,
            ':color'           => $color_vehiculo ?: null,
            ':confianza'       => $confianza,
            ':puerta'          => $puerta,
            ':foto_panoramica' => $archivoIngreso,
            ':foto_placa'      => $archivoPlaca,
            ':alerta'          => $alerta,
        ]);

        $newId = (int) $pdo->lastInsertId();
        $statusMsg = ($alerta > 0) ? 'ALERTA' : 'INGRESO';

        echo json_encode([
            'status'          => $statusMsg,
            'message'         => "Ingreso registrado para placa {$placa}.",
            'id'              => $newId,
            'alerta'          => ($alerta > 0),
            'nivel_alerta'    => $alerta,
            'color_vehiculo'  => $color_vehiculo,
            'evidencia_foto'  => $archivoIngreso,
            'evidencia_placa' => $archivoPlaca,
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
