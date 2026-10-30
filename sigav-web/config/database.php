<?php
/**
 * SIGAV-UAGRM | Conexión Segura a la Base de Datos
 * Usa PDO con Prepared Statements para prevenir Inyecciones SQL.
 * Sincroniza zona horaria oficial de Bolivia (America/La_Paz, UTC-4).
 */
date_default_timezone_set('America/La_Paz');

define('DB_HOST', 'localhost');
define('DB_NAME', 'uagrm_control_vehicular');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '-04:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Error de conexión a la base de datos.']));
        }
    }
    return $pdo;
}
