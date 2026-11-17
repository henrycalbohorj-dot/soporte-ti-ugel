<?php
/**
 * ============================================================
 *  CONEXIÓN A POSTGRESQL (PDO)
 *  Sistema de Tickets - Soporte T.I.
 * ------------------------------------------------------------
 *  Configuración por variables de entorno (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 * ============================================================
 */

// ---- Datos de conexión ----
// Se leen de variables de entorno (definidas en el pool de PHP-FPM o en Apache).
// Ver .env.example. Nunca escribas la contraseña real en este archivo.
$db_host     = getenv('DB_HOST') ?: 'localhost';
$db_port     = getenv('DB_PORT') ?: '5432';
$db_name     = getenv('DB_NAME') ?: 'soporte_ti';
$db_user     = getenv('DB_USER') ?: 'soporte_ti_app';
$db_password = getenv('DB_PASS') ?: '';

// ---- Cadena de conexión ----
$dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";

// ---- Opciones de PDO ----
$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // que los errores lancen excepción
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // resultados como arreglo asociativo
    PDO::ATTR_EMULATE_PREPARES   => false,                    // usar prepared statements reales
];

// ---- Crear la conexión ----
try {
    $pdo = new PDO($dsn, $db_user, $db_password, $opciones);
} catch (PDOException $e) {
    // Si falla la conexión, detener y mostrar un mensaje claro.
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// A partir de aquí, $pdo queda disponible para hacer consultas.