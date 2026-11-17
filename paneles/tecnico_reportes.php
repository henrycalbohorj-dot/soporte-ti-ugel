<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['tecnico', 'admin']);

$rol   = $_SESSION['rol'] ?? '';
$id_yo = (int)($_SESSION['id_usuario'] ?? 0);

// ---- Alcance: un técnico solo ve lo suyo; el admin elige "Todos" o uno específico ----
$tecnicos_lista = ($rol === 'admin') ? traer_tecnicos($pdo) : [];
if ($rol === 'tecnico') {
    $id_tecnico_filtro = (string)$id_yo; // forzado, no se puede cambiar por GET
} else {
    $id_tecnico_filtro = trim($_GET['tecnico'] ?? '');
    if ($id_tecnico_filtro !== '' && !ctype_digit($id_tecnico_filtro)) { $id_tecnico_filtro = ''; }
}
$vista_global = ($id_tecnico_filtro === '');

$tecnico_nombre = null;
if (!$vista_global) {
    if ($rol === 'tecnico') {
        $tecnico_nombre = nombre_usuario();
    } else {
        foreach ($tecnicos_lista as $tc) {
            if ((int)$tc['id_usuario'] === (int)$id_tecnico_filtro) { $tecnico_nombre = trim($tc['nombres'].' '.$tc['apellidos']); break; }
        }
    }
}

// ---- Rango de fechas (por defecto: últimos 30 días) ----
$hoy = date('Y-m-d');
$hace30 = date('Y-m-d', strtotime('-30 days'));
$desde = $_GET['desde'] ?? $hace30;
$hasta = $_GET['hasta'] ?? $hoy;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = $hace30; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = $hoy; }
$desde_ts = $desde . ' 00:00:00';
$hasta_ts = $hasta . ' 23:59:59';

$params = [':d'=>$desde_ts, ':h'=>$hasta_ts];
$cond_tecnico = '';
if (!$vista_global) { $cond_tecnico = ' AND t.id_tecnico = :tec'; $params[':tec'] = (int)$id_tecnico_filtro; }

// ---- Totales del rango, por estado (ya filtrado por técnico si aplica) ----
$sql = "SELECT t.estado, COUNT(*) n FROM tickets t
        WHERE t.fecha_creacion BETWEEN :d AND :h $cond_tecnico
        GROUP BY t.estado";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conteos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_rango = array_sum($conteos);
$n_completados = (int)($conteos['completado'] ?? 0);
$n_cancelados  = (int)($conteos['cancelado'] ?? 0);
$n_rechazados  = (int)($conteos['rechazado'] ?? 0);

$tasa_completados = $total_rango > 0 ? round($n_completados / $total_rango * 100, 1) : 0.0;
$tasa_cancelados  = $total_rango > 0 ? round($n_cancelados  / $total_rango * 100, 1) : 0.0;
$tasa_rechazados  = $total_rango > 0 ? round($n_rechazados  / $total_rango * 100, 1) : 0.0;

// ---- Pendientes: mochila compartida de la entidad (nunca tienen técnico asignado). ----
// Solo tiene sentido mostrarla en la vista global (Todos), no atribuida a un técnico.
$n_pendientes = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE estado='pendiente'")->fetchColumn();

// ---- Tiempo promedio de atención (horas), completados con fecha_cierre, dentro del rango ----
$sql = "SELECT AVG(EXTRACT(EPOCH FROM (t.fecha_cierre - t.fecha_asignacion)) / 3600.0) AS horas
        FROM tickets t
        WHERE t.estado = 'completado' AND t.fecha_cierre IS NOT NULL AND t.fecha_asignacion IS NOT NULL
          AND t.fecha_creacion BETWEEN :d AND :h $cond_tecnico";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$horas_prom = (float)($stmt->fetchColumn() ?: 0);

// ---- Niveles de semáforo ----
$niv_pendientes  = semaforo_nivel($n_pendientes, 5, 15);
$niv_completados = semaforo_nivel($tasa_completados, 70, 40, true);
$niv_cancelados  = semaforo_nivel($tasa_cancelados, 10, 25);
$niv_rechazados  = semaforo_nivel($tasa_rechazados, 10, 25);
$niv_tiempo      = semaforo_nivel($horas_prom, 4, 24);

// ---- Gráfico: "Tickets por técnico" en vista global; "Tickets por categoría" cuando ya
//      se está viendo a un técnico en particular (compararlo consigo mismo no aporta). ----
if ($vista_global) {
    $sql = "SELECT COALESCE(TRIM(u.nombres || ' ' || u.apellidos), 'Sin asignar') AS etiqueta, COUNT(*) n
            FROM tickets t
            LEFT JOIN usuarios u ON u.id_usuario = t.id_tecnico
            WHERE t.fecha_creacion BETWEEN :d AND :h
            GROUP BY etiqueta
            ORDER BY n DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':d'=>$desde_ts, ':h'=>$hasta_ts]);
    $titulo_grafico = 'Tickets por técnico';
} else {
    $sql = "SELECT COALESCE(c.nombre, 'Sin categoría') AS etiqueta, COUNT(*) n
            FROM tickets t
            LEFT JOIN categorias c ON c.id_categoria = t.id_categoria
            WHERE t.fecha_creacion BETWEEN :d AND :h $cond_tecnico
            GROUP BY etiqueta
            ORDER BY n DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $titulo_grafico = 'Tickets por categoría';
}
$datos_grafico = $stmt->fetchAll();
$max_grafico = 0;
foreach ($datos_grafico as $row) { $max_grafico = max($max_grafico, (int)$row['n']); }

// ---- Tickets por área solicitante ----
$sql = "SELECT COALESCE(a.nombre, 'Sin área') AS etiqueta, COUNT(*) n
        FROM tickets t
        LEFT JOIN areas a ON a.id_area = t.id_area
        WHERE t.fecha_creacion BETWEEN :d AND :h $cond_tecnico
        GROUP BY etiqueta
        ORDER BY n DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$datos_area = $stmt->fetchAll();
$max_area = 0;
foreach ($datos_area as $row) { $max_area = max($max_area, (int)$row['n']); }

// ---- Tickets por usuario solicitante (top 10; se avisa si hay más) ----
$sql = "SELECT COALESCE(NULLIF(TRIM(sol.nombres || ' ' || sol.apellidos), ''), 'Desconocido') AS etiqueta, COUNT(*) n
        FROM tickets t
        LEFT JOIN usuarios sol ON sol.id_usuario = t.id_solicitante
        WHERE t.fecha_creacion BETWEEN :d AND :h $cond_tecnico
        GROUP BY etiqueta
        ORDER BY n DESC
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$datos_solicitante = $stmt->fetchAll();
$max_solicitante = 0;
foreach ($datos_solicitante as $row) { $max_solicitante = max($max_solicitante, (int)$row['n']); }

$sql = "SELECT COUNT(DISTINCT t.id_solicitante) FROM tickets t WHERE t.fecha_creacion BETWEEN :d AND :h $cond_tecnico";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$total_solicitantes = (int)$stmt->fetchColumn();
$solicitantes_ocultos = max(0, $total_solicitantes - count($datos_solicitante));

// ---- Tendencia de tickets creados en el tiempo (bucket automático según el largo del rango) ----
$sql = "SELECT DATE(t.fecha_creacion) AS dia, COUNT(*) n
        FROM tickets t
        WHERE t.fecha_creacion BETWEEN :d AND :h $cond_tecnico
        GROUP BY dia";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conteo_por_dia = [];
foreach ($stmt->fetchAll() as $r) { $conteo_por_dia[$r['dia']] = (int)$r['n']; }

$dt_desde = new DateTime($desde);
$dt_hasta = new DateTime($hasta);
$dias_totales = (int)$dt_desde->diff($dt_hasta)->days + 1;
$meses_abrev = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

$fechas_grafico = [];
$valores_tendencia = [];

if ($dias_totales <= 45) {
    // Bucket diario
    $cursor = clone $dt_desde;
    while ($cursor <= $dt_hasta) {
        $fechas_grafico[] = $cursor->format('d/m');
        $valores_tendencia[] = $conteo_por_dia[$cursor->format('Y-m-d')] ?? 0;
        $cursor->modify('+1 day');
    }
} elseif ($dias_totales <= 180) {
    // Bucket semanal (lunes a domingo)
    $cursor = clone $dt_desde;
    $iso_dow = (int)$cursor->format('N');
    if ($iso_dow > 1) { $cursor->modify('-' . ($iso_dow - 1) . ' days'); }
    while ($cursor <= $dt_hasta) {
        $suma = 0;
        $d = clone $cursor;
        for ($i = 0; $i < 7; $i++) {
            if ($d >= $dt_desde && $d <= $dt_hasta) { $suma += $conteo_por_dia[$d->format('Y-m-d')] ?? 0; }
            $d->modify('+1 day');
        }
        $fechas_grafico[] = 'Sem. ' . $cursor->format('d/m');
        $valores_tendencia[] = $suma;
        $cursor->modify('+7 days');
    }
} else {
    // Bucket mensual
    $cursor = new DateTime($dt_desde->format('Y-m-01'));
    while ($cursor <= $dt_hasta) {
        $fin_mes = (clone $cursor)->modify('+1 month')->modify('-1 day');
        $suma = 0;
        $d = clone $cursor;
        while ($d <= $fin_mes) {
            if ($d >= $dt_desde && $d <= $dt_hasta) { $suma += $conteo_por_dia[$d->format('Y-m-d')] ?? 0; }
            $d->modify('+1 day');
        }
        $fechas_grafico[] = $meses_abrev[(int)$cursor->format('n') - 1] . ' ' . $cursor->format('Y');
        $valores_tendencia[] = $suma;
        $cursor->modify('+1 month');
    }
}

// ---- Textos de encabezado según el alcance ----
if ($rol === 'tecnico') {
    $h2 = 'Mi reporte';
    $sub = 'Tu desempeño del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)) . '.';
} elseif ($vista_global) {
    $h2 = 'Reportes y estadísticas';
    $sub = 'Resumen de toda la entidad del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)) . '.';
} else {
    $h2 = 'Reporte de ' . $tecnico_nombre;
    $sub = 'Del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)) . '.';
}

$titulo_pagina = 'Reportes'; $seccion_activa = 'reportes';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div><h2><?= e($h2) ?></h2><p><?= e($sub) ?></p></div>
    <div class="reportes-acciones">
        <a href="/tickets/paneles/reportes_exportar.php?formato=xlsx&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&tecnico=<?= urlencode($id_tecnico_filtro) ?>" class="btn btn-sec"><?= icono('file-excel') ?> Exportar Excel</a>
        <a href="/tickets/paneles/reportes_exportar.php?formato=pdf&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&tecnico=<?= urlencode($id_tecnico_filtro) ?>" class="btn btn-sec"><?= icono('file-pdf') ?> Exportar PDF</a>
    </div>
</div>

<div class="panel">
    <form class="reportes-filtros" method="GET">
        <div class="campo">
            <label>Desde</label>
            <input type="date" name="desde" value="<?= e($desde) ?>">
        </div>
        <div class="campo">
            <label>Hasta</label>
            <input type="date" name="hasta" value="<?= e($hasta) ?>">
        </div>
        <?php if ($rol === 'admin'): ?>
        <div class="campo">
            <label>Técnico</label>
            <select name="tecnico">
                <option value="">Todos</option>
                <?php foreach ($tecnicos_lista as $tc): ?>
                    <option value="<?= (int)$tc['id_usuario'] ?>" <?= ((string)$tc['id_usuario'] === $id_tecnico_filtro) ? 'selected' : '' ?>>
                        <?= e(trim($tc['nombres'].' '.$tc['apellidos'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="campo" style="flex:0;">
            <button type="submit" class="btn">Aplicar</button>
        </div>
    </form>
</div>

<div class="kpis-semaforo">
    <?php
    $tarjetas = [];
    if ($vista_global) {
        $tarjetas[] = ['nivel'=>$niv_pendientes, 'valor'=>$n_pendientes, 'label'=>'Pendientes en el sistema'];
    }
    $tarjetas[] = ['nivel'=>$niv_completados, 'valor'=>$tasa_completados.'%', 'label'=>'Completados ('.$n_completados.' de '.$total_rango.')'];
    $tarjetas[] = ['nivel'=>$niv_cancelados,  'valor'=>$tasa_cancelados.'%',  'label'=>'Cancelados ('.$n_cancelados.' de '.$total_rango.')'];
    $tarjetas[] = ['nivel'=>$niv_rechazados,  'valor'=>$tasa_rechazados.'%',  'label'=>'Rechazados ('.$n_rechazados.' de '.$total_rango.')'];
    $tarjetas[] = ['nivel'=>$niv_tiempo,      'valor'=>number_format($horas_prom,1).'h', 'label'=>'Tiempo promedio de atención'];
    foreach ($tarjetas as $k):
        $info = semaforo_info($k['nivel']);
    ?>
    <div class="kpi-semaforo kpi-<?= e($k['nivel']) ?>">
        <span class="barra"></span>
        <div class="kpi-semaforo-top">
            <span class="kpi-nivel"><span class="punto" style="background:<?= e($info['color']) ?>;"></span><?= e($info['texto']) ?></span>
        </div>
        <div class="valor"><?= e((string)$k['valor']) ?></div>
        <div class="label"><?= e($k['label']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php
// ---- Distribución por estado (para el círculo tipo dashboard): TODOS los tickets ingresados en el rango ----
$estados_orden = ['pendiente', 'en_atencion', 'completado', 'cancelado', 'rechazado'];
$segmentos_estado = [];
foreach ($estados_orden as $es) {
    $c = (int)($conteos[$es] ?? 0);
    if ($c > 0) {
        $segmentos_estado[] = ['estado' => $es, 'n' => $c, 'pct' => $total_rango > 0 ? round($c / $total_rango * 100, 1) : 0];
    }
}
?>
<div class="panel">
    <div class="lb-panel-cab">
        <h3>Tickets creados en el tiempo</h3>
        <span class="lb-panel-total"><?= $total_rango ?> tickets en el periodo</span>
    </div>
    <?php if ($total_rango === 0): ?>
        <div class="grafico-vacio">No hay tickets en este rango de fechas.</div>
    <?php else: ?>
        <div class="dash-split">
            <div class="dash-tendencia">
                <div class="grafico-lienzo"><canvas id="grafico-tendencia"></canvas></div>
            </div>
            <div class="dash-dona">
                <div class="dona-titulo">Total de tickets · por estado</div>
                <div class="dona-wrap">
                    <canvas id="dona-estado"></canvas>
                    <div class="dona-centro">
                        <span class="dona-centro-valor"><?= $total_rango ?></span>
                        <span class="dona-centro-label">tickets</span>
                    </div>
                </div>
                <div class="dona-leyenda">
                    <?php foreach ($segmentos_estado as $s): ?>
                    <div class="dona-leyenda-item">
                        <span class="dona-dot dona-dot-<?= e($s['estado']) ?>"></span>
                        <span class="dona-leyenda-nombre"><?= e(estado_legible($s['estado'])) ?></span>
                        <span class="dona-leyenda-valor"><?= $s['n'] ?> · <?= $s['pct'] ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="lb-panel-cab">
        <h3><?= e($titulo_grafico) ?></h3>
        <span class="lb-panel-total"><?= $total_rango ?> tickets en el periodo</span>
    </div>
    <?php if (empty($datos_grafico) || $total_rango === 0): ?>
        <div class="grafico-vacio">No hay tickets en este rango de fechas.</div>
    <?php else: ?>
        <div class="leaderboard">
            <div class="lb-encabezado"><span>#</span><span></span><span>Nombre</span><span>Tickets</span></div>
            <?php foreach ($datos_grafico as $i => $row):
                $n = (int)$row['n'];
                $pct = $max_grafico > 0 ? round($n / $max_grafico * 100, 1) : 0;
                $pct_total = $total_rango > 0 ? round($n / $total_rango * 100, 1) : 0;
                $rango = $i + 1;
                $es_lider = $rango === 1;
            ?>
            <div class="lb-item<?= $es_lider ? ' lb-lider' : '' ?>">
                <span class="lb-rank"><?= $rango ?></span>
                <span class="lb-avatar"><?= e(iniciales_desde_nombre($row['etiqueta'])) ?></span>
                <div class="lb-cuerpo">
                    <div class="lb-nombre-fila">
                        <span class="lb-nombre" title="<?= e($row['etiqueta']) ?>"><?= e($row['etiqueta']) ?></span>
                        <?php if ($es_lider): ?><span class="lb-tag-lider">Líder</span><?php endif; ?>
                    </div>
                    <div class="lb-barra-pista"><span class="lb-barra" style="width:<?= $pct ?>%;"></span></div>
                </div>
                <div class="lb-metricas">
                    <span class="lb-valor"><?= $n ?></span>
                    <span class="lb-pct-badge"><?= $pct_total ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="lb-panel-cab">
        <h3>Tickets por área</h3>
        <span class="lb-panel-total"><?= $total_rango ?> tickets en el periodo</span>
    </div>
    <?php if (empty($datos_area) || $total_rango === 0): ?>
        <div class="grafico-vacio">No hay tickets en este rango de fechas.</div>
    <?php else: ?>
        <div class="leaderboard">
            <div class="lb-encabezado"><span>#</span><span></span><span>Nombre</span><span>Tickets</span></div>
            <?php foreach ($datos_area as $i => $row):
                $n = (int)$row['n'];
                $pct = $max_area > 0 ? round($n / $max_area * 100, 1) : 0;
                $pct_total = $total_rango > 0 ? round($n / $total_rango * 100, 1) : 0;
                $rango = $i + 1;
                $es_lider = $rango === 1;
            ?>
            <div class="lb-item<?= $es_lider ? ' lb-lider' : '' ?>">
                <span class="lb-rank"><?= $rango ?></span>
                <span class="lb-avatar"><?= e(iniciales_desde_nombre($row['etiqueta'])) ?></span>
                <div class="lb-cuerpo">
                    <div class="lb-nombre-fila">
                        <span class="lb-nombre" title="<?= e($row['etiqueta']) ?>"><?= e($row['etiqueta']) ?></span>
                        <?php if ($es_lider): ?><span class="lb-tag-lider">Líder</span><?php endif; ?>
                    </div>
                    <div class="lb-barra-pista"><span class="lb-barra" style="width:<?= $pct ?>%;"></span></div>
                </div>
                <div class="lb-metricas">
                    <span class="lb-valor"><?= $n ?></span>
                    <span class="lb-pct-badge"><?= $pct_total ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="lb-panel-cab">
        <h3>Tickets por usuario solicitante</h3>
        <span class="lb-panel-total"><?= $total_rango ?> tickets en el periodo</span>
    </div>
    <?php if (empty($datos_solicitante) || $total_rango === 0): ?>
        <div class="grafico-vacio">No hay tickets en este rango de fechas.</div>
    <?php else: ?>
        <div class="leaderboard">
            <div class="lb-encabezado"><span>#</span><span></span><span>Nombre</span><span>Tickets</span></div>
            <?php foreach ($datos_solicitante as $i => $row):
                $n = (int)$row['n'];
                $pct = $max_solicitante > 0 ? round($n / $max_solicitante * 100, 1) : 0;
                $pct_total = $total_rango > 0 ? round($n / $total_rango * 100, 1) : 0;
                $rango = $i + 1;
                $es_lider = $rango === 1;
            ?>
            <div class="lb-item<?= $es_lider ? ' lb-lider' : '' ?>">
                <span class="lb-rank"><?= $rango ?></span>
                <span class="lb-avatar"><?= e(iniciales_desde_nombre($row['etiqueta'])) ?></span>
                <div class="lb-cuerpo">
                    <div class="lb-nombre-fila">
                        <span class="lb-nombre" title="<?= e($row['etiqueta']) ?>"><?= e($row['etiqueta']) ?></span>
                        <?php if ($es_lider): ?><span class="lb-tag-lider">Líder</span><?php endif; ?>
                    </div>
                    <div class="lb-barra-pista"><span class="lb-barra" style="width:<?= $pct ?>%;"></span></div>
                </div>
                <div class="lb-metricas">
                    <span class="lb-valor"><?= $n ?></span>
                    <span class="lb-pct-badge"><?= $pct_total ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($solicitantes_ocultos > 0): ?>
            <p style="margin-top:12px; color:var(--texto-tenue); font-size:.82rem;">Se muestran los 10 solicitantes con más tickets. Hay <?= $solicitantes_ocultos ?> más en este rango (exporta a Excel/PDF para verlos todos).</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($total_rango > 0): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
(function(){
    var lienzo = document.getElementById('grafico-tendencia');
    if (!lienzo || typeof Chart === 'undefined') return;

    function coloresTema(){
        var s = getComputedStyle(document.documentElement);
        var linea = (s.getPropertyValue('--acento') || '#3b6fd4').trim();
        return {
            linea: linea,
            relleno: linea + '26',
            texto: (s.getPropertyValue('--texto-sec') || '#64748b').trim(),
            grid: (s.getPropertyValue('--borde') || '#e2e8f0').trim(),
            tooltipBg: (s.getPropertyValue('--bg-panel') || '#ffffff').trim(),
        };
    }

    var datos = <?= json_encode($valores_tendencia, JSON_UNESCAPED_UNICODE) ?>;
    var etiquetas = <?= json_encode($fechas_grafico, JSON_UNESCAPED_UNICODE) ?>;
    var c = coloresTema();

    var grafico = new Chart(lienzo, {
        type: 'line',
        data: {
            labels: etiquetas,
            datasets: [{
                label: 'Tickets creados',
                data: datos,
                borderColor: c.linea,
                backgroundColor: c.relleno,
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: datos.length > 40 ? 0 : 3,
                pointHoverRadius: 5,
                pointBackgroundColor: c.linea,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: c.tooltipBg,
                    titleColor: c.texto,
                    bodyColor: c.texto,
                    borderColor: c.grid,
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        label: function(ctx){ return ctx.parsed.y + (ctx.parsed.y===1?' ticket':' tickets'); }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: c.texto, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                y: { beginAtZero: true, ticks: { color: c.texto, precision: 0 }, grid: { color: c.grid } }
            }
        }
    });

    // El gráfico usa los mismos colores del tema (claro/oscuro); si el usuario lo cambia, se re-pinta solo.
    new MutationObserver(function(){
        var nc = coloresTema();
        var ds = grafico.data.datasets[0];
        ds.borderColor = nc.linea; ds.backgroundColor = nc.relleno; ds.pointBackgroundColor = nc.linea;
        grafico.options.plugins.tooltip.backgroundColor = nc.tooltipBg;
        grafico.options.plugins.tooltip.titleColor = nc.texto;
        grafico.options.plugins.tooltip.bodyColor = nc.texto;
        grafico.options.plugins.tooltip.borderColor = nc.grid;
        grafico.options.scales.x.ticks.color = nc.texto;
        grafico.options.scales.y.ticks.color = nc.texto;
        grafico.options.scales.y.grid.color = nc.grid;
        grafico.update();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });
})();

(function(){
    var lienzo = document.getElementById('dona-estado');
    var segmentos = <?= json_encode($segmentos_estado, JSON_UNESCAPED_UNICODE) ?>;
    if (!lienzo || typeof Chart === 'undefined' || !segmentos.length) return;

    var etiquetasLegibles = { pendiente: 'Pendiente', en_atencion: 'En atención', completado: 'Completado', cancelado: 'Cancelado', rechazado: 'Rechazado' };

    function coloresDona(){
        var s = getComputedStyle(document.documentElement);
        return {
            porEstado: {
                pendiente:   (s.getPropertyValue('--info')   || '#2563eb').trim(),
                en_atencion: (s.getPropertyValue('--aviso')  || '#d97706').trim(),
                completado:  (s.getPropertyValue('--exito')  || '#16a34a').trim(),
                cancelado:   (s.getPropertyValue('--alerta') || '#dc2626').trim(),
                rechazado:   (s.getPropertyValue('--texto-sec')|| '#64748b').trim(),
            },
            superficie: (s.getPropertyValue('--bg-panel') || '#ffffff').trim(),
            texto: (s.getPropertyValue('--texto-sec') || '#64748b').trim(),
            grid: (s.getPropertyValue('--borde') || '#e2e8f0').trim(),
        };
    }

    var c = coloresDona();
    var labels = segmentos.map(function(s){ return etiquetasLegibles[s.estado] || s.estado; });
    var data = segmentos.map(function(s){ return s.n; });
    var colores = segmentos.map(function(s){ return c.porEstado[s.estado]; });

    var dona = new Chart(lienzo, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: colores, borderColor: c.superficie, borderWidth: 3, hoverOffset: 8, hoverBorderWidth: 3, borderRadius: 3 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            animation: { animateRotate: true, animateScale: true, duration: 900, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: c.superficie,
                    titleColor: c.texto,
                    bodyColor: c.texto,
                    borderColor: c.grid,
                    borderWidth: 1,
                    padding: 10,
                    displayColors: true,
                    callbacks: {
                        label: function(ctx){
                            var total = ctx.dataset.data.reduce(function(a,b){ return a + b; }, 0);
                            var pct = total ? Math.round((ctx.parsed / total) * 1000) / 10 : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });

    // Repinta con los colores del tema activo (claro/oscuro) cuando el usuario lo cambia.
    new MutationObserver(function(){
        var nc = coloresDona();
        dona.data.datasets[0].backgroundColor = segmentos.map(function(s){ return nc.porEstado[s.estado]; });
        dona.data.datasets[0].borderColor = nc.superficie;
        dona.options.plugins.tooltip.backgroundColor = nc.superficie;
        dona.options.plugins.tooltip.titleColor = nc.texto;
        dona.options.plugins.tooltip.bodyColor = nc.texto;
        dona.options.plugins.tooltip.borderColor = nc.grid;
        dona.update();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
