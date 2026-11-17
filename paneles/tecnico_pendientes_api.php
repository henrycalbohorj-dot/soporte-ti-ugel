<?php
/**
 * API: tickets pendientes SIN asignar (para la notificación por voz).
 * Devuelve JSON. Lo consulta el navegador del técnico/admin cada pocos segundos.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Solo técnicos y administradores con sesión
if (!hay_sesion() || !in_array($_SESSION['rol'] ?? '', ['tecnico', 'admin'], true)) {
    echo json_encode(['ok' => false, 'tickets' => []]);
    exit;
}

try {
    // Tickets pendientes SIN técnico asignado
    $sql = "SELECT t.codigo, t.titulo,
                   p.nombre AS prioridad,
                   u.nombre AS ubicacion
            FROM tickets t
            LEFT JOIN prioridades p ON p.id_prioridad = t.id_prioridad
            LEFT JOIN ubicaciones u ON u.id_ubicacion = t.id_ubicacion
            WHERE t.estado = 'pendiente' AND t.id_tecnico IS NULL
            ORDER BY t.fecha_creacion DESC";
    $tickets = $pdo->query($sql)->fetchAll();

    // Solo el número (para el mensaje de voz: "Ticket número XXX, PISO")
    $lista = array_map(function($t) {
        // Extraer el número del código TK-000037 -> 37
        $num = (int)preg_replace('/\D/', '', $t['codigo']);
        return [
            'codigo'    => $t['codigo'],
            'numero'    => $num,
            'titulo'    => $t['titulo'],
            'prioridad' => $t['prioridad'],
            'ubicacion' => $t['ubicacion'],
        ];
    }, $tickets);

    echo json_encode([
        'ok'      => true,
        'total'   => count($lista),
        'tickets' => $lista,
    ]);

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'tickets' => []]);
}