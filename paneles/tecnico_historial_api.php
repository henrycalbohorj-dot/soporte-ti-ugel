<?php
/**
 * API: historial de tickets con filtros (para el filtrado al instante).
 * Filtros: estado, id_tecnico, fecha_desde, fecha_hasta, numero.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
header('Content-Type: application/json; charset=utf-8');

if (!hay_sesion() || !in_array($_SESSION['rol'] ?? '', ['tecnico', 'admin'], true)) {
    echo json_encode(['ok'=>false]); exit;
}

$estado  = $_GET['estado']  ?? '';
$tecnico = $_GET['tecnico'] ?? '';
$desde   = $_GET['desde']   ?? '';
$hasta   = $_GET['hasta']   ?? '';
$numero  = trim($_GET['numero'] ?? '');

// Paginación
$por_pagina = (int)($_GET['por_pagina'] ?? 10);
if (!in_array($por_pagina, [10, 25, 50], true)) { $por_pagina = 10; }
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$cond = []; $params = [];
$ev = ['pendiente','en_atencion','completado','cancelado','rechazado'];
if ($estado === 'activos') {
    // Vista por defecto del historial: solo lo que aún necesita atención.
    $cond[] = "t.estado IN ('pendiente','en_atencion')";
} elseif (in_array($estado,$ev,true)) {
    $cond[]='t.estado = :estado'; $params[':estado']=$estado;
}
if ($tecnico !== '' && ctype_digit($tecnico)) { $cond[]='t.id_tecnico = :tec'; $params[':tec']=(int)$tecnico; }
if ($desde !== '') { $cond[]='t.fecha_creacion >= :desde'; $params[':desde']=$desde.' 00:00:00'; }
if ($hasta !== '') { $cond[]='t.fecha_creacion <= :hasta'; $params[':hasta']=$hasta.' 23:59:59'; }
if ($numero !== '') {
    $n = (int)preg_replace('/\D/','',$numero);
    $cond[]='t.id_ticket = :num'; $params[':num']=$n;
}
$where = $cond ? ('WHERE '.implode(' AND ', $cond)) : '';

try {
    // Total de tickets que cumplen los filtros (sin paginar), para calcular las páginas
    $sqlTotal = "SELECT COUNT(*) FROM tickets t $where";
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $total = (int)$stmtTotal->fetchColumn();

    $total_paginas = max(1, (int)ceil($total / $por_pagina));
    if ($pagina > $total_paginas) { $pagina = $total_paginas; }
    $offset = ($pagina - 1) * $por_pagina;

    // $por_pagina y $offset ya están forzados a enteros arriba: seguro interpolarlos directo.
    $sql = "SELECT t.codigo, t.titulo, t.estado, t.fecha_creacion,
                   p.nombre AS prioridad, p.color AS prioridad_color,
                   c.nombre AS categoria,
                   a.nombre AS area, ub.nombre AS ubicacion,
                   sol.nombres AS sol_n, sol.apellidos AS sol_a,
                   tec.nombres AS tec_n, tec.apellidos AS tec_a
            FROM tickets t
            LEFT JOIN prioridades p  ON p.id_prioridad = t.id_prioridad
            LEFT JOIN categorias  c  ON c.id_categoria = t.id_categoria
            LEFT JOIN areas       a  ON a.id_area = t.id_area
            LEFT JOIN ubicaciones ub ON ub.id_ubicacion = t.id_ubicacion
            LEFT JOIN usuarios    sol ON sol.id_usuario = t.id_solicitante
            LEFT JOIN usuarios    tec ON tec.id_usuario = t.id_tecnico
            $where
            ORDER BY t.fecha_creacion DESC
            LIMIT $por_pagina OFFSET $offset";
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
            'categoria' => $t['categoria'] ?? '—',
            'area'      => $t['area'] ?? '—',
            'ubicacion' => $t['ubicacion'] ?? '—',
            'solicitante' => trim(($t['sol_n']??'').' '.($t['sol_a']??'')),
            'tecnico'   => $t['tec_n'] ? trim($t['tec_n'].' '.$t['tec_a']) : null,
            'fecha'     => fecha_legible($t['fecha_creacion']),
        ];
    }, $rows);

    echo json_encode([
        'ok' => true,
        'total' => $total,
        'pagina' => $pagina,
        'total_paginas' => $total_paginas,
        'por_pagina' => $por_pagina,
        'tickets' => $lista,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}