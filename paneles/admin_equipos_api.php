<?php
/**
 * API: listado de equipos con filtros y búsqueda (para el filtrado al instante).
 * Filtros: q (búsqueda libre), area, marca, tipo, estado.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
header('Content-Type: application/json; charset=utf-8');

if (!hay_sesion() || !in_array($_SESSION['rol'] ?? '', ['admin', 'tecnico'], true)) {
    echo json_encode(['ok' => false]); exit;
}

$q      = trim($_GET['q'] ?? '');
$area   = $_GET['area'] ?? '';
$marca  = trim($_GET['marca'] ?? '');
$tipo   = trim($_GET['tipo'] ?? '');
$estado = $_GET['estado'] ?? ''; // operativo | mantenimiento | malogrado | sin_registro

$cond = ['e.activo = TRUE'];
$params = [];

if ($q !== '') {
    $cond[] = "(e.nombre ILIKE :q OR e.numero_serie ILIKE :q OR e.marca ILIKE :q OR e.modelo ILIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($area !== '' && ctype_digit($area)) {
    $cond[] = 'e.id_area = :area';
    $params[':area'] = (int)$area;
}
if ($marca !== '') {
    $cond[] = 'e.marca = :marca';
    $params[':marca'] = $marca;
}
if ($tipo !== '') {
    $cond[] = 'e.tipo = :tipo';
    $params[':tipo'] = $tipo;
}
$where = 'WHERE ' . implode(' AND ', $cond);

try {
    // Último mantenimiento de cada equipo (por fecha más reciente)
    $sql = "SELECT e.id_equipo, e.nombre, e.tipo, e.marca, e.modelo, e.numero_serie,
                   e.procesador, e.sistema_operativo,
                   a.nombre AS area,
                   resp.nombres AS resp_n, resp.apellidos AS resp_a,
                   um.tipo AS ult_tipo, um.estado AS ult_estado,
                   um.fecha_mantenimiento AS ult_fecha,
                   um.tec_n AS tec_n, um.tec_a AS tec_a,
                   ram.resumen AS ram_resumen,
                   alm.resumen AS alm_resumen
            FROM equipos e
            LEFT JOIN areas a ON a.id_area = e.id_area
            LEFT JOIN usuarios resp ON resp.id_usuario = e.id_responsable
            LEFT JOIN LATERAL (
                SELECT m.tipo, m.estado, m.fecha_mantenimiento, tec.nombres AS tec_n, tec.apellidos AS tec_a
                FROM equipo_mantenimientos m
                LEFT JOIN usuarios tec ON tec.id_usuario = m.id_tecnico
                WHERE m.id_equipo = e.id_equipo
                ORDER BY m.fecha_mantenimiento DESC, m.id_mantenimiento DESC
                LIMIT 1
            ) um ON TRUE
            LEFT JOIN LATERAL (
                SELECT string_agg(trim(concat_ws(' ', capacidad, tipo)), ' + ' ORDER BY orden) AS resumen
                FROM equipo_ram r WHERE r.id_equipo = e.id_equipo
            ) ram ON TRUE
            LEFT JOIN LATERAL (
                SELECT string_agg(trim(concat_ws(' ', capacidad, tipo)), ' + ' ORDER BY orden) AS resumen
                FROM equipo_almacenamiento al WHERE al.id_equipo = e.id_equipo
            ) alm ON TRUE
            $where
            ORDER BY e.nombre
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $lista = [];
    foreach ($rows as $r) {
        $sinRegistro = $r['ult_fecha'] === null;
        $estadoEquipo = $sinRegistro ? 'sin_registro' : $r['ult_estado'];

        // Filtro de estado se aplica aquí (calculado, no es columna directa)
        if ($estado !== '' && $estadoEquipo !== $estado) continue;

        $sem = semaforo_mantenimiento($r['ult_fecha']);
        $tecNombre = $r['tec_n'] ? trim($r['tec_n'] . ' ' . $r['tec_a']) : null;

        $especs = array_filter([$r['procesador'], $r['ram_resumen'], $r['alm_resumen'], $r['sistema_operativo']]);
        $lista[] = [
            'id'          => (int)$r['id_equipo'],
            'nombre'      => $r['nombre'],
            'tipo'        => $r['tipo'],
            'marca'       => $r['marca'],
            'modelo'      => $r['modelo'],
            'serie'       => $r['numero_serie'],
            'area'        => $r['area'] ?? '—',
            'responsable' => $r['resp_n'] ? trim($r['resp_n'] . ' ' . $r['resp_a']) : null,
            'especificaciones' => implode(' · ', $especs) ?: '—',
            'estado'      => $estadoEquipo,
            'estado_txt'  => estado_equipo_legible($estadoEquipo),
            'ultimo_tipo' => $r['ult_tipo'],
            'ultima_fecha'=> $r['ult_fecha'] ? fecha_legible_corta($r['ult_fecha']) : null,
            'ultimo_tecnico' => $tecNombre,
            'alerta'      => $sem['nivel'] === 'critico',
            'semaforo'    => $sem['nivel'],
            'semaforo_txt'=> $sem['texto'],
        ];
    }

    echo json_encode(['ok' => true, 'total' => count($lista), 'equipos' => $lista], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
