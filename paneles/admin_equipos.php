<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['admin', 'tecnico']);

// ---- KPIs y gráfico "equipos por área" (se calculan una vez, al cargar la página) ----
$total = (int)$pdo->query("SELECT COUNT(*) FROM equipos WHERE activo = TRUE")->fetchColumn();

$areasCubiertas = (int)$pdo->query(
    "SELECT COUNT(DISTINCT id_area) FROM equipos WHERE activo = TRUE AND id_area IS NOT NULL"
)->fetchColumn();

// Requieren atención = sin ningún mantenimiento, o el más reciente tiene > 180 días
$sql_alerta = "
    SELECT
        COUNT(*) FILTER (WHERE um.fecha_mantenimiento IS NULL) AS sin_registro,
        COUNT(*) FILTER (WHERE um.fecha_mantenimiento IS NOT NULL AND um.fecha_mantenimiento < CURRENT_DATE - INTERVAL '180 days') AS vencidos
    FROM equipos e
    LEFT JOIN LATERAL (
        SELECT MAX(fecha_mantenimiento) AS fecha_mantenimiento
        FROM equipo_mantenimientos m WHERE m.id_equipo = e.id_equipo
    ) um ON TRUE
    WHERE e.activo = TRUE";
$r = $pdo->query($sql_alerta)->fetch();
$sinRegistro = (int)($r['sin_registro'] ?? 0);
$vencidos    = (int)($r['vencidos'] ?? 0);
$requierenAtencion = $sinRegistro + $vencidos;

$porArea = $pdo->query(
    "SELECT COALESCE(a.nombre, 'Sin área asignada') AS area, COUNT(*) AS n
     FROM equipos e LEFT JOIN areas a ON a.id_area = e.id_area
     WHERE e.activo = TRUE
     GROUP BY a.nombre ORDER BY n DESC LIMIT 8"
)->fetchAll();
$maxArea = $porArea ? max(array_column($porArea, 'n')) : 0;

// ---- Opciones de filtro (se llenan con valores reales existentes) ----
$areas  = $pdo->query("SELECT id_area, nombre FROM areas WHERE activo = TRUE ORDER BY nombre")->fetchAll();
$marcas = $pdo->query("SELECT DISTINCT marca FROM equipos WHERE activo = TRUE AND marca <> '' ORDER BY marca")->fetchAll(PDO::FETCH_COLUMN);
$tipos  = $pdo->query("SELECT DISTINCT tipo FROM equipos WHERE activo = TRUE AND tipo <> '' ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);

$msg = $_GET['msg'] ?? '';

$titulo_pagina = 'Equipos'; $seccion_activa = 'equipos';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2>Kardex de equipos</h2>
        <p>Inventario y estado de mantenimiento de los equipos de la entidad.</p>
    </div>
    <a href="/tickets/paneles/admin_equipo_form.php" class="btn"><?= icono('plus') ?> Nuevo equipo</a>
</div>

<?php if ($msg==='creado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Equipo registrado correctamente.</div>
<?php elseif ($msg==='editado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Equipo actualizado.</div>
<?php elseif ($msg==='borrado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Equipo eliminado.</div>
<?php elseif ($msg==='desactivado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> El equipo tiene mantenimientos registrados, así que se desactivó en lugar de borrarlo, para conservar el historial.</div>
<?php elseif ($msg==='mantenimiento'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Mantenimiento registrado.</div>
<?php endif; ?>

<div class="kpis">
    <div class="kpi"><span class="barra" style="background:#3b6fd4;"></span><div class="valor"><?= $total ?></div><div class="label">Total de equipos</div></div>
    <div class="kpi"><span class="barra" style="background:#7c5cfc;"></span><div class="valor"><?= $areasCubiertas ?></div><div class="label">Áreas cubiertas</div></div>
    <div class="kpi"><span class="barra" style="background:#dc2626;"></span><div class="valor"><?= $requierenAtencion ?></div><div class="label">Requieren atención</div></div>
    <div class="kpi"><span class="barra" style="background:#94a3b8;"></span><div class="valor"><?= $sinRegistro ?></div><div class="label">Sin mantenimiento registrado</div></div>
</div>

<div class="panel">
    <div class="panel-titulo">Equipos por área</div>
    <?php if (!$porArea): ?>
        <div class="grafico-vacio">Aún no hay equipos registrados.</div>
    <?php else: ?>
        <div class="grafico-barras" style="grid-template-columns:none;">
            <?php foreach ($porArea as $fila): $pct = $maxArea ? round($fila['n'] / $maxArea * 100) : 0; ?>
                <div class="gb-fila" style="grid-template-columns:230px 1fr 46px; align-items:center;">
                    <div class="gb-nombre" style="white-space:normal; line-height:1.25;" title="<?= e($fila['area']) ?>"><?= e($fila['area']) ?></div>
                    <div class="gb-pista"><div class="gb-barra" style="width:<?= $pct ?>%"></div></div>
                    <div class="gb-valor"><?= (int)$fila['n'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="panel" id="panel-filtros-equipos">
    <div class="filtros-grid">
        <div class="campo full">
            <label>Buscar</label>
            <input type="text" id="f-buscar" placeholder="Nombre, serie, marca o modelo...">
        </div>
        <div class="campo">
            <label>Área</label>
            <select id="f-area">
                <option value="">Todas</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= (int)$a['id_area'] ?>"><?= e($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>Marca</label>
            <select id="f-marca">
                <option value="">Todas</option>
                <?php foreach ($marcas as $m): ?>
                    <option value="<?= e($m) ?>"><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>Tipo</label>
            <select id="f-tipo">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= e($t) ?>"><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>Estado</label>
            <select id="f-estado">
                <option value="">Todos</option>
                <option value="operativo">Operativo</option>
                <option value="mantenimiento">En mantenimiento</option>
                <option value="malogrado">Malogrado</option>
                <option value="sin_registro">Sin registro</option>
            </select>
        </div>
        <div class="campo" style="display:flex; align-items:flex-end;">
            <button class="btn btn-sec" style="width:100%;" onclick="limpiarFiltrosEquipos()">Limpiar</button>
        </div>
    </div>
</div>

<div id="conteo-equipos" style="color:var(--texto-sec); font-size:.9rem; margin-bottom:14px;"></div>

<div class="panel" style="padding:0; overflow:hidden;">
    <table class="tabla">
        <thead>
            <tr><th>Equipo</th><th>Área</th><th>Responsable</th><th>Especificaciones</th><th>Último mantenimiento</th><th>Acciones</th></tr>
        </thead>
        <tbody id="tabla-equipos"></tbody>
    </table>
</div>
<div class="vacio" id="equipos-vacio" style="display:none;">
    <div class="ic"><?= icono('laptop') ?></div><p>No se encontraron equipos con esos filtros.</p>
</div>

<script>
var ICONO_ALERTA = '<?= addslashes(icono('alert', 15)) ?>';
(function(){
    var buscar = document.getElementById('f-buscar');
    var area   = document.getElementById('f-area');
    var marca  = document.getElementById('f-marca');
    var tipo   = document.getElementById('f-tipo');
    var estado = document.getElementById('f-estado');
    var tbody  = document.getElementById('tabla-equipos');
    var vacio  = document.getElementById('equipos-vacio');
    var conteo = document.getElementById('conteo-equipos');
    var timer  = null;

    function esc(s){ var d=document.createElement('div'); d.textContent = s==null ? '' : s; return d.innerHTML; }

    function fila(eq){
        var resp = eq.responsable ? esc(eq.responsable) : '<span style="color:var(--texto-tenue);">Sin asignar</span>';
        var badge = '<span class="badge badge-' + eq.estado + '">' + esc(eq.estado_txt) + '</span>';
        var puntoClase = { bien:'punto-verde', regular:'punto-ambar', critico:'punto-rojo' }[eq.semaforo] || 'punto-rojo';
        var semaforoMini = '<span class="punto-semaforo ' + puntoClase + '" title="Mantenimiento: ' + esc(eq.semaforo_txt) + '"></span> ' + esc(eq.semaforo_txt);
        var fecha = eq.ultima_fecha ? '<div style="font-size:.78rem;color:var(--texto-sec);margin-top:3px;">' + esc(eq.ultima_fecha) + (eq.ultimo_tecnico ? ' · ' + esc(eq.ultimo_tecnico) : '') + '</div>' : '';
        return '<tr>' +
            '<td><strong>' + esc(eq.nombre) + '</strong><div style="font-size:.8rem;color:var(--texto-sec);">' + esc(eq.marca) + ' ' + esc(eq.modelo||'') + ' · S/N ' + esc(eq.serie||'—') + '</div></td>' +
            '<td>' + esc(eq.area) + '</td>' +
            '<td>' + resp + '</td>' +
            '<td style="font-size:.85rem;color:var(--texto-sec);">' + esc(eq.especificaciones) + '</td>' +
            '<td>' + badge + '<div style="font-size:.78rem;margin-top:5px;">' + semaforoMini + '</div>' + fecha + '</td>' +
            '<td><div class="acciones-fila">' +
                '<a href="/tickets/paneles/admin_equipo_detalle.php?id=' + eq.id + '" class="btn btn-sec btn-sm">Ver</a>' +
                '<a href="/tickets/paneles/admin_equipo_form.php?id=' + eq.id + '" class="btn btn-sec btn-sm">Editar</a>' +
            '</div></td>' +
        '</tr>';
    }

    function cargar(){
        var qs = new URLSearchParams();
        if (buscar.value.trim()) qs.set('q', buscar.value.trim());
        if (area.value) qs.set('area', area.value);
        if (marca.value) qs.set('marca', marca.value);
        if (tipo.value) qs.set('tipo', tipo.value);
        if (estado.value) qs.set('estado', estado.value);

        fetch('/tickets/paneles/admin_equipos_api.php?' + qs.toString(), {cache:'no-store'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok){ tbody.innerHTML=''; vacio.style.display='block'; conteo.textContent=''; return; }
                conteo.textContent = d.total + ' equipo' + (d.total===1?'':'s');
                if (!d.equipos.length){
                    tbody.innerHTML = '';
                    vacio.style.display = 'block';
                } else {
                    vacio.style.display = 'none';
                    tbody.innerHTML = d.equipos.map(fila).join('');
                }
            })
            .catch(function(){ conteo.textContent = 'No se pudo conectar con el servidor.'; });
    }

    function debounced(){
        clearTimeout(timer);
        timer = setTimeout(cargar, 300);
    }

    buscar.addEventListener('input', debounced);
    [area, marca, tipo, estado].forEach(function(sel){ sel.addEventListener('change', cargar); });

    window.limpiarFiltrosEquipos = function(){
        buscar.value=''; area.value=''; marca.value=''; tipo.value=''; estado.value='';
        cargar();
    };

    cargar();
})();
</script>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
