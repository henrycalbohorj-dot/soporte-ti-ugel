<?php
/**
 * EDITAR LA FICHA DE MANTENIMIENTO de un registro ya guardado.
 * Se llega aquí automáticamente después de "Guardar mantenimiento" (quick
 * form en admin_equipo_detalle.php), y también desde "Editar ficha" en el
 * historial. Aquí se completan los datos que el formulario rápido no pide:
 * datos de red/BIOS, evaluación por componente, asunto/código de ficha y
 * las firmas de conformidad — y también se puede corregir lo demás
 * (técnico, fecha, tipo, partes, conclusión, condición final).
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['admin', 'tecnico']);

$rol = $_SESSION['rol'] ?? '';
$es_admin = $rol === 'admin';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

$sql = "SELECT m.*, e.id_equipo, e.nombre AS eq_nombre, e.tipo AS eq_tipo, e.marca, e.modelo,
               e.numero_serie, e.procesador, e.sistema_operativo, e.mac_lan, e.mac_wifi,
               e.id_area, a.nombre AS area,
               resp.nombres AS resp_n, resp.apellidos AS resp_a,
               tec.nombres AS tec_n, tec.apellidos AS tec_a
        FROM equipo_mantenimientos m
        JOIN equipos e ON e.id_equipo = m.id_equipo
        LEFT JOIN areas a ON a.id_area = e.id_area
        LEFT JOIN usuarios resp ON resp.id_usuario = e.id_responsable
        LEFT JOIN usuarios tec ON tec.id_usuario = m.id_tecnico
        WHERE m.id_mantenimiento = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$mant = $stmt->fetch();
if (!$mant) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

// Un técnico (no admin) solo puede editar la ficha de SU PROPIO mantenimiento.
if (!$es_admin && (int)$mant['id_tecnico'] !== (int)$_SESSION['id_usuario']) {
    header('Location: /tickets/paneles/admin_equipo_detalle.php?id=' . (int)$mant['id_equipo']);
    exit;
}

$ficha = $pdo->prepare("SELECT * FROM equipo_mantenimiento_ficha WHERE id_mantenimiento = :id");
$ficha->execute([':id' => $id]);
$ficha = $ficha->fetch() ?: null;

$compStmt = $pdo->prepare("SELECT * FROM equipo_mantenimiento_componentes WHERE id_mantenimiento = :id ORDER BY orden, id_componente");
$compStmt->execute([':id' => $id]);
$componentes = $compStmt->fetchAll();

// Primera vez: no hay filas de componentes todavía -> se sugieren a partir
// de las especificaciones ya cargadas del equipo (editable antes de guardar).
if (!$componentes) {
    $orden = 0;
    if (!empty($mant['procesador'])) {
        $componentes[] = ['categoria' => 'procesador', 'componente' => $mant['procesador'], 'estado' => 'bueno', 'descripcion' => '', 'orden' => $orden++];
    }
    $ramStmt = $pdo->prepare("SELECT * FROM equipo_ram WHERE id_equipo = :id ORDER BY orden, id_ram");
    $ramStmt->execute([':id' => $mant['id_equipo']]);
    foreach ($ramStmt->fetchAll() as $r) {
        $txt = trim(implode(' ', array_filter([$r['capacidad'], $r['tipo'], $r['frecuencia']])));
        if ($txt !== '') $componentes[] = ['categoria' => 'ram', 'componente' => $txt, 'estado' => 'bueno', 'descripcion' => '', 'orden' => $orden++];
    }
    $almStmt = $pdo->prepare("SELECT * FROM equipo_almacenamiento WHERE id_equipo = :id ORDER BY orden, id_almacenamiento");
    $almStmt->execute([':id' => $mant['id_equipo']]);
    foreach ($almStmt->fetchAll() as $a) {
        $txt = trim(implode(' ', array_filter([$a['capacidad'], $a['tipo'], $a['interfaz']])));
        if ($txt !== '') $componentes[] = ['categoria' => 'almacenamiento', 'componente' => $txt, 'estado' => 'bueno', 'descripcion' => '', 'orden' => $orden++];
    }
    $gpuStmt = $pdo->prepare("SELECT * FROM equipo_gpu WHERE id_equipo = :id ORDER BY orden, id_gpu");
    $gpuStmt->execute([':id' => $mant['id_equipo']]);
    foreach ($gpuStmt->fetchAll() as $g) {
        $txt = trim(implode(' ', array_filter([$g['marca'], $g['modelo'], $g['capacidad'], $g['tipo']])));
        if ($txt !== '') $componentes[] = ['categoria' => 'grafico', 'componente' => $txt, 'estado' => 'bueno', 'descripcion' => '', 'orden' => $orden++];
    }
    if (!empty($mant['sistema_operativo'])) {
        $componentes[] = ['categoria' => 'so', 'componente' => $mant['sistema_operativo'], 'estado' => 'bueno', 'descripcion' => '', 'orden' => $orden++];
    }
}

$partesDisponibles = $pdo->query("SELECT nombre FROM partes_catalogo ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$partesActuales = $mant['partes'] ? array_map('trim', explode(',', $mant['partes'])) : [];
$partesExtra = array_values(array_diff($partesActuales, $partesDisponibles));

$tecnicosDisponibles = $es_admin ? traer_tecnicos($pdo) : [];

$tecnicoNombre = $mant['tec_n'] ? trim($mant['tec_n'] . ' ' . $mant['tec_a']) : null;
$responsableNombre = $mant['resp_n'] ? trim($mant['resp_n'] . ' ' . $mant['resp_a']) : null;

// ---- Código de ficha sugerido (editable): iniciales del técnico + correlativo ----
if ($ficha && !empty($ficha['codigo_ficha'])) {
    $codigoSugerido = $ficha['codigo_ficha'];
} else {
    $iniciales = '—';
    if ($tecnicoNombre) {
        $palabras = preg_split('/\s+/', trim($tecnicoNombre));
        $iniciales = mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), $palabras)));
    }
    $corr = 1;
    if ($mant['id_tecnico']) {
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM equipo_mantenimientos WHERE id_tecnico = :t AND id_mantenimiento <= :id");
        $cStmt->execute([':t' => $mant['id_tecnico'], ':id' => $id]);
        $corr = (int)$cStmt->fetchColumn();
    }
    $codigoSugerido = $iniciales . '-' . $corr;
}

$evStmt = $pdo->prepare("SELECT * FROM equipo_mantenimiento_evidencias WHERE id_mantenimiento = :id ORDER BY fecha_subida ASC");
$evStmt->execute([':id' => $id]);
$evidencias = $evStmt->fetchAll();

$msg = $_GET['msg'] ?? '';
$esNueva = ($_GET['nueva'] ?? '') === '1';

$categoriasLabel = ['procesador' => 'Procesador', 'ram' => 'Memoria RAM', 'almacenamiento' => 'Almacenamiento', 'grafico' => 'Gráficos', 'so' => 'Sistema operativo', 'red' => 'Red', 'otro' => 'Otro'];

$titulo_pagina = 'Ficha de mantenimiento · ' . $mant['eq_nombre'];
$seccion_activa = 'equipos';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= icono('clipboard', 20) ?> Ficha de mantenimiento</h2>
        <p><?= e($mant['eq_nombre']) ?> · <?= e($mant['marca']) ?> <?= e($mant['modelo'] ?? '') ?> · <?= e(fecha_legible_corta($mant['fecha_mantenimiento'])) ?></p>
    </div>
    <div class="acciones-fila">
        <a href="/tickets/paneles/equipo_mantenimiento_reporte.php?id=<?= (int)$id ?>" target="_blank" class="btn btn-sec"><?= icono('printer', 16) ?> Ver / Imprimir ficha</a>
        <a href="/tickets/paneles/admin_equipo_detalle.php?id=<?= (int)$mant['id_equipo'] ?>" class="btn btn-sec"><?= icono('arrow-left') ?> Volver al equipo</a>
    </div>
</div>

<?php if ($esNueva): ?>
<div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Mantenimiento registrado. Completa aquí la ficha (puedes guardar y volver a editarla cuando quieras).</div>
<?php endif; ?>
<?php if ($msg === 'guardado'): ?>
<div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Ficha guardada correctamente.</div>
<?php endif; ?>
<?php if (($_GET['error'] ?? '') === '1'): ?>
<div class="aviso aviso-error">No se pudo guardar la ficha. Intenta de nuevo.</div>
<?php endif; ?>

<form action="/tickets/paneles/equipo_ficha_guardar.php" method="POST" id="form-ficha">
<input type="hidden" name="id_mantenimiento" value="<?= (int)$id ?>">

<div class="panel">
    <div class="panel-titulo">Datos del mantenimiento</div>
    <div class="form-grid">
        <div class="campo">
            <label>Técnico</label>
            <?php if ($es_admin): ?>
                <select name="id_tecnico">
                    <?php foreach ($tecnicosDisponibles as $tc): ?>
                        <option value="<?= (int)$tc['id_usuario'] ?>" <?= (int)$mant['id_tecnico']===(int)$tc['id_usuario']?'selected':'' ?>><?= e($tc['nombres'].' '.$tc['apellidos']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" value="<?= e($tecnicoNombre ?? '—') ?>" disabled>
            <?php endif; ?>
        </div>
        <div class="campo">
            <label>Tipo</label>
            <select name="tipo">
                <option value="preventivo" <?= $mant['tipo']==='preventivo'?'selected':'' ?>>Preventivo</option>
                <option value="correctivo" <?= $mant['tipo']==='correctivo'?'selected':'' ?>>Correctivo</option>
            </select>
        </div>
        <div class="campo">
            <label>Fecha</label>
            <input type="date" name="fecha_mantenimiento" value="<?= e($mant['fecha_mantenimiento']) ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="campo">
            <label>Condición final</label>
            <select name="estado">
                <option value="operativo" <?= $mant['estado']==='operativo'?'selected':'' ?>>Operativo</option>
                <option value="mantenimiento" <?= $mant['estado']==='mantenimiento'?'selected':'' ?>>En mantenimiento</option>
                <option value="malogrado" <?= $mant['estado']==='malogrado'?'selected':'' ?>>Malogrado</option>
            </select>
        </div>
        <div class="campo full">
            <label>Partes intervenidas</label>
            <div class="partes-grid">
                <?php foreach ($partesDisponibles as $p): ?>
                    <label class="parte-check"><input type="checkbox" name="partes[]" value="<?= e($p) ?>" <?= in_array($p, $partesActuales, true)?'checked':'' ?>> <?= e($p) ?></label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="parte_otro" placeholder="Otra parte (se guarda para la próxima vez)" value="<?= e(implode(', ', $partesExtra)) ?>" style="margin-top:10px;">
        </div>
        <div class="campo full">
            <label>Conclusión / Solución</label>
            <textarea name="descripcion" placeholder="Diagnóstico, qué se hizo, recomendaciones..."><?= e($mant['descripcion'] ?? '') ?></textarea>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-titulo">Datos del equipo</div>
    <p style="color:var(--texto-sec); font-size:.85rem; margin-top:-6px;">Se muestran los datos que ya tiene el equipo en el Kardex. Si falta alguno o cambió, corrígelo aquí — se actualiza también en el Kardex para los próximos mantenimientos.</p>
    <div class="form-grid">
        <div class="campo">
            <label>Hostname / Nombre del equipo</label>
            <input type="text" name="eq_hostname" value="<?= e($mant['eq_nombre'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>Marca</label>
            <input type="text" name="eq_marca" value="<?= e($mant['marca'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>Modelo</label>
            <input type="text" name="eq_modelo" value="<?= e($mant['modelo'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>N.° de serie</label>
            <input type="text" name="eq_serie" value="<?= e($mant['numero_serie'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>Sistema operativo</label>
            <input type="text" name="eq_so" value="<?= e($mant['sistema_operativo'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>MAC LAN</label>
            <input type="text" name="eq_mac_lan" value="<?= e($mant['mac_lan'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>MAC WiFi</label>
            <input type="text" name="eq_mac_wifi" value="<?= e($mant['mac_wifi'] ?? '') ?>">
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-titulo">Datos de la ficha</div>
    <div class="form-grid">
        <div class="campo">
            <label>Código de ficha</label>
            <input type="text" name="codigo_ficha" value="<?= e($codigoSugerido) ?>" placeholder="Ej: PVG-16">
        </div>
        <div class="campo full">
            <label>Asunto / Referencia (oficio, etc.)</label>
            <input type="text" name="asunto" value="<?= e($ficha['asunto'] ?? '') ?>" placeholder="Ej: Inspección y mantenimiento. Ref. OFICIO 513-26-...">
        </div>
        <div class="campo">
            <label>Equipo de trabajo</label>
            <input type="text" name="eq_trabajo" value="<?= e($ficha['eq_trabajo'] ?? $mant['area'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>Subequipo</label>
            <input type="text" name="subequipo" value="<?= e($ficha['subequipo'] ?? $mant['area'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>Fecha BIOS</label>
            <input type="date" name="fecha_bios" value="<?= e($ficha['fecha_bios'] ?? '') ?>">
        </div>
        <div class="campo">
            <label>Conformidad del responsable</label>
            <input type="text" name="conformidad_responsable" value="<?= e($ficha['conformidad_responsable'] ?? $responsableNombre ?? '') ?>">
        </div>
        <div class="campo">
            <label>Conformidad del técnico</label>
            <input type="text" name="conformidad_tecnico" value="<?= e($ficha['conformidad_tecnico'] ?? $tecnicoNombre ?? '') ?>">
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-titulo">Evaluación por componente</div>
    <p style="color:var(--texto-sec); font-size:.85rem; margin-top:-6px;">Se sugieren a partir de las especificaciones del equipo. Edita el estado y la descripción, o agrega/quita filas.</p>
    <div id="filas-componentes">
        <?php foreach ($componentes as $c): ?>
            <?= render_fila_componente($c, $categoriasLabel) ?>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sec btn-sm" id="btn-agregar-componente" style="margin-top:8px;"><?= icono('plus', 15) ?> Agregar componente</button>
</div>

<div class="panel">
    <div class="panel-titulo">Evidencia adjunta</div>
    <?php if (!$evidencias): ?>
        <p style="color:var(--texto-sec); font-size:.88rem;">No se adjuntó evidencia al registrar este mantenimiento.</p>
    <?php else: ?>
        <div class="mant-evidencia-mini">
            <?php foreach ($evidencias as $ev): ?>
                <?php if ($ev['tipo_archivo'] === 'pdf'): ?>
                    <a href="<?= e($ev['ruta_archivo']) ?>" target="_blank" class="mant-ev-pdf"><?= icono('file-pdf', 15) ?> PDF</a>
                <?php else: ?>
                    <a href="<?= e($ev['ruta_archivo']) ?>" target="_blank"><img src="<?= e($ev['ruta_archivo']) ?>" alt="Evidencia" loading="lazy"></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="panel" style="display:flex; gap:12px; flex-wrap:wrap;">
    <button type="submit" class="btn"><?= icono('check', 16) ?> Guardar ficha</button>
    <a href="/tickets/paneles/equipo_mantenimiento_reporte.php?id=<?= (int)$id ?>" target="_blank" class="btn btn-sec"><?= icono('printer', 16) ?> Ver / Imprimir ficha</a>
</div>
</form>

<template id="plantilla-fila-componente">
<?= render_fila_componente(['categoria'=>'otro','componente'=>'','estado'=>'bueno','descripcion'=>''], $categoriasLabel) ?>
</template>

<script>
(function(){
    var cont = document.getElementById('filas-componentes');
    var plantilla = document.getElementById('plantilla-fila-componente').innerHTML;

    document.getElementById('btn-agregar-componente').addEventListener('click', function(){
        var div = document.createElement('div');
        div.innerHTML = plantilla.trim();
        cont.appendChild(div.firstElementChild);
    });

    cont.addEventListener('click', function(ev){
        var btn = ev.target.closest('.btn-quitar-componente');
        if (!btn) return;
        var fila = btn.closest('.fila-componente');
        if (fila) fila.remove();
    });
})();
</script>

<?php
/**
 * Dibuja una fila editable del "Evaluación por componente" (usada tanto
 * para las filas existentes como para la plantilla de filas nuevas).
 */
function render_fila_componente(array $c, array $categoriasLabel): string {
    ob_start();
    ?>
    <div class="fila-componente">
        <div class="campo">
            <label>Categoría</label>
            <select name="comp_categoria[]">
                <?php foreach ($categoriasLabel as $val => $lbl): ?>
                    <option value="<?= e($val) ?>" <?= ($c['categoria'] ?? '')===$val?'selected':'' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>Componente</label>
            <input type="text" name="comp_nombre[]" value="<?= e($c['componente'] ?? '') ?>" placeholder="Ej: Intel Core i7-7700T 2.9 GHz">
        </div>
        <div class="campo">
            <label>Estado</label>
            <select name="comp_estado[]">
                <option value="bueno"  <?= ($c['estado'] ?? '')==='bueno'?'selected':'' ?>>Bueno</option>
                <option value="regular" <?= ($c['estado'] ?? '')==='regular'?'selected':'' ?>>Regular</option>
                <option value="danado" <?= ($c['estado'] ?? '')==='danado'?'selected':'' ?>>Dañado</option>
            </select>
        </div>
        <div class="campo full">
            <label>Descripción</label>
            <input type="text" name="comp_descripcion[]" value="<?= e($c['descripcion'] ?? '') ?>" placeholder="Observación puntual de este componente...">
        </div>
        <button type="button" class="btn-quitar-componente" title="Quitar este componente"><?= icono('x', 15) ?></button>
    </div>
    <?php
    return ob_get_clean();
}

require __DIR__ . '/../config/layout_bottom.php';
