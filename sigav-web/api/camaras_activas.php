<?php
/**
 * API: Obtener Cámaras Activas
 * Usado por el lanzador de Python para saber a qué IPs conectarse.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    // Validar existencia de tabla por si se llama antes de abrir la vista
    $pdo->exec("CREATE TABLE IF NOT EXISTS camaras (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, ip_url VARCHAR(255) NOT NULL, sentido ENUM('ingreso', 'salida') NOT NULL, activa BOOLEAN DEFAULT TRUE)");
    
    $stmt = $pdo->query("SELECT id, nombre, ip_url, sentido FROM camaras WHERE activa = 1");
    $camaras = $stmt->fetchAll();
    
    echo json_encode(["status" => "success", "camaras" => $camaras]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de BD."]);
}
