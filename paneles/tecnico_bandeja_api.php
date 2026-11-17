<?php
/**
 * API: datos completos de la bandeja (para refrescar la tabla sin recargar).
 * Devuelve conteos + lista de tickets según el filtro. Solo técnicos.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';

header('Content-Type: application/json; charset=utf-8');

if (!hay_sesion() || ($_SESSION['rol'] ?? '') !== 'tecnico') {
    echo json_encode(['ok'=>false]); exit;
}

$id_yo = $_SESSION['id_usuario'];
$filtro = $_GET['estado'] ?? 'todos';
$ev = ['pendiente','en_atencion','completado','cancelado','rechazado'];

$where=''; $params=[];
if (in_array($filtro,$ev,true)) { $where='WHERE t.estado=:estado'; $params[':estado']=$filtro; }
elseif ($filtro==='mios') { $where='WHERE t.id_tecnico=:yo'; $params[':yo']=$id_yo; }

try {
    $sql = "SELECT t.codigo, t.titulo, t.estado, t.fecha_creacion,
                   p.nombre AS prioridad, p.color AS prioridad_color,
                   sol.nombres AS sol_nombres, sol.apellidos AS sol_apellidos,
                   tec.nombres AS tec_nombres, tec.apellidos AS tec_apellidos
            FROM tickets t
            LEFT JOIN prioridades p   ON p.id_prioridad = t.id_prioridad
            LEFT JOIN usuarios    sol ON sol.id_usuario = t.id_solicitante
            LEFT JOIN usuarios    tec ON tec.id_usuario = t.id_tecnico
            $where
            ORDER BY CASE t.estado WHEN 'pendiente' THEN 0 WHEN 'en_atencion' THEN 1 ELSE 2 END,
                     p.nivel DESC NULLS LAST, t.fecha_creacion DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $lista = array_map(function($t){
        return [
            'codigo'    => $t['codigo'],
            'codigo_fmt'=> formatear_codigo($t['codigo'], $t['fecha_creacion']),
            'titulo'    => $t['titulo'],
            'estado'    => $t['estado'],
            'estado_txt'=> estado_legible($t['estado']),
            'prioridad' => $t['prioridad'] ?? '—',
            'prioridad_color' => $t['prioridad_color'] ?? '#999',
            'solicitante' => trim(($t['sol_nombres']??'').' '.($t['sol_apellidos']??'')),
            'tecnico'   => $t['tec_nombres'] ? trim($t['tec_nombres'].' '.$t['tec_apellidos']) : null,
        ];
    }, $rows);

    // Conteos globales
    $c = $pdo->query("SELECT estado, COUNT(*) n FROM tickets GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'ok' => true,
        'conteos' => [
            'pendiente'   => (int)($c['pendiente']??0),
            'en_atencion' => (int)($c['en_atencion']??0),
            'completado'  => (int)($c['completado']??0),
            'cancelado'   => (int)($c['cancelado']??0),
            'rechazado'   => (int)($c['rechazado']??0),
        ],
        'tickets' => $lista,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['ok'=>false]);
}