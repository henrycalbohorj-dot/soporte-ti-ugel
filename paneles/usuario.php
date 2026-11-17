<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['solicitante']);
$id_yo = $_SESSION['id_usuario'];

// ---- Filtros (por GET, para poder compartir/recargar la URL) ----
$f_estado    = $_GET['estado'] ?? '';
$f_categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$f_numero    = trim($_GET['numero'] ?? '');
$f_desde     = $_GET['desde'] ?? '';
$f_hasta     = $_GET['hasta'] ?? '';
$hay_filtros = ($f_estado !== '' || $f_categoria > 0 || $f_numero !== '' || $f_desde !== '' || $f_hasta !== '');

$estados_validos = ['pendiente', 'en_atencion', 'completado', 'cancelado', 'rechazado'];

$where  = ['t.id_solicitante = :id'];
$params = [':id' => $id_yo];

if (in_array($f_estado, $estados_validos, true)) {
    $where[] = 't.estado = :estado';
    $params[':estado'] = $f_estado;
}
if ($f_categoria > 0) {
    $where[] = 't.id_categoria = :categoria';
    $params[':categoria'] = $f_categoria;
}
if ($f_numero !== '') {
    $num = preg_replace('/\D/', '', $f_numero);
    if ($num !== '') {
        $where[] = "t.codigo ILIKE :numero";
        $params[':numero'] = '%' . $num;
    }
}
if ($f_desde !== '') {
    $where[] = 't.fecha_creacion >= :desde';
    $params[':desde'] = $f_desde . ' 00:00:00';
}
if ($f_hasta !== '') {
    $where[] = 't.fecha_creacion <= :hasta';
    $params[':hasta'] = $f_hasta . ' 23:59:59';
}

$sql = "SELECT t.codigo,t.titulo,t.estado,t.fecha_creacion,c.nombre AS categoria,
               p.nombre AS prioridad,p.color AS prioridad_color
        FROM tickets t
        LEFT JOIN categorias c ON c.id_categoria=t.id_categoria
        LEFT JOIN prioridades p ON p.id_prioridad=t.id_prioridad
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// ---- KPIs: sobre TODOS los tickets del usuario (no cambian según los filtros) ----
$stmtTodos = $pdo->prepare("SELECT estado, COUNT(*) n FROM tickets WHERE id_solicitante=:id GROUP BY estado");
$stmtTodos->execute([':id' => $id_yo]);
$conteos = $stmtTodos->fetchAll(PDO::FETCH_KEY_PAIR);
$total          = array_sum($conteos);
$n_pendientes   = (int)($conteos['pendiente'] ?? 0);
$n_atencion     = (int)($conteos['en_atencion'] ?? 0);
$n_completados  = (int)($conteos['completado'] ?? 0);
$n_otros_cierre = (int)($conteos['cancelado'] ?? 0) + (int)($conteos['rechazado'] ?? 0);

// ---- Categorías que el usuario ya ha usado, para el filtro ----
$stmtCat = $pdo->prepare(
    "SELECT DISTINCT c.id_categoria, c.nombre
     FROM tickets t JOIN categorias c ON c.id_categoria = t.id_categoria
     WHERE t.id_solicitante = :id ORDER BY c.nombre");
$stmtCat->execute([':id' => $id_yo]);
$categorias = $stmtCat->fetchAll();

$creado = isset($_GET['creado']);

$titulo_pagina  = 'Mis tickets';
$seccion_activa = 'tickets';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div><h2>Mis tickets</h2><p>Crea y sigue tus solicitudes de soporte.</p></div>
    <a href="/tickets/paneles/usuario_crear.php" class="btn"><?= icono('plus') ?> Nuevo ticket</a>
</div>
<?php if ($creado): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Tu ticket se creó correctamente. El equipo de soporte lo revisará pronto.</div><?php endif; ?>

<div class="kpis">
    <div class="kpi"><span class="barra" style="background:#64748b;"></span><div class="valor"><?= $total ?></div><div class="label">Tickets en total</div></div>
    <div class="kpi"><span class="barra" style="background:#2563eb;"></span><div class="valor"><?= $n_pendientes ?></div><div class="label">Pendientes</div></div>
    <div class="kpi"><span class="barra" style="background:#d97706;"></span><div class="valor"><?= $n_atencion ?></div><div class="label">En atención</div></div>
    <div class="kpi"><span class="barra" style="background:#16a34a;"></span><div class="valor"><?= $n_completados ?></div><div class="label">Completados</div></div>
    <div class="kpi"><span class="barra" style="background:#c62828;"></span><div class="valor"><?= $n_otros_cierre ?></div><div class="label">Cancelados / rechazados</div></div>
</div>

<!-- Filtros -->
<div class="panel">
    <form method="GET" class="filtros-grid">
        <div class="campo">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                <option value="pendiente" <?= $f_estado==='pendiente'?'selected':'' ?>>Pendiente</option>
                <option value="en_atencion" <?= $f_estado==='en_atencion'?'selected':'' ?>>En atención</option>
                <option value="completado" <?= $f_estado==='completado'?'selected':'' ?>>Completado</option>
                <option value="cancelado" <?= $f_estado==='cancelado'?'selected':'' ?>>Cancelado</option>
                <option value="rechazado" <?= $f_estado==='rechazado'?'selected':'' ?>>Rechazado</option>
            </select>
        </div>
        <div class="campo">
            <label>Categoría</label>
            <select name="categoria">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id_categoria'] ?>" <?= $f_categoria===(int)$c['id_categoria']?'selected':'' ?>><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>N° de ticket</label>
            <input type="text" name="numero" value="<?= e($f_numero) ?>" placeholder="Ej: 5 o TK-000005">
        </div>
        <div class="campo">
            <label>Desde</label>
            <input type="date" name="desde" value="<?= e($f_desde) ?>">
        </div>
        <div class="campo">
            <label>Hasta</label>
            <input type="date" name="hasta" value="<?= e($f_hasta) ?>">
        </div>
        <div class="campo" style="display:flex; align-items:flex-end; gap:8px;">
            <button type="submit" class="btn" style="flex:1;"><?= icono('search', 15) ?> Filtrar</button>
            <?php if ($hay_filtros): ?><a href="/tickets/paneles/usuario.php" class="btn btn-sec" style="flex:1; text-align:center;">Limpiar</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if ($hay_filtros): ?>
    <div style="color:var(--texto-sec); font-size:.9rem; margin-bottom:14px;"><?= count($tickets) ?> resultado<?= count($tickets)===1?'':'s' ?> con estos filtros.</div>
<?php endif; ?>

<div class="panel">
<?php if ($total === 0): ?>
    <div class="vacio"><div class="ic"><?= icono('ticket') ?></div><p>Aún no has creado ningún ticket.</p>
    <p style="margin-top:14px;"><a href="/tickets/paneles/usuario_crear.php" class="btn">Crear mi primer ticket</a></p></div>
<?php elseif (count($tickets) === 0): ?>
    <div class="vacio"><div class="ic"><?= icono('search') ?></div><p>No se encontraron tickets con esos filtros.</p></div>
<?php else: ?>
    <table class="tabla">
        <thead><tr><th>Código</th><th>Motivo</th><th>Categoría</th><th>Prioridad</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr>
                <td class="mono"><?= e(formatear_codigo($t['codigo'], $t['fecha_creacion'])) ?></td>
                <td><?= e($t['titulo']) ?></td>
                <td><?= e($t['categoria']??'—') ?></td>
                <td><span class="punto" style="background:<?= e($t['prioridad_color']??'#999') ?>;"></span><?= e($t['prioridad']??'—') ?></td>
                <td><span class="badge badge-<?= e($t['estado']) ?>"><?= e(estado_legible($t['estado'])) ?></span></td>
                <td><?= fecha_legible($t['fecha_creacion']) ?></td>
                <td><a href="/tickets/paneles/usuario_detalle.php?codigo=<?= urlencode($t['codigo']) ?>" class="btn btn-sec btn-sm">Ver</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
