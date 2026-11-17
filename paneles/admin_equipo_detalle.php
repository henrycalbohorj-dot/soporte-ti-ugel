<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['admin', 'tecnico']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

$sql = "SELECT e.*, a.nombre AS area, resp.nombres AS resp_n, resp.apellidos AS resp_a
        FROM equipos e
        LEFT JOIN areas a ON a.id_area = e.id_area
        LEFT JOIN usuarios resp ON resp.id_usuario = e.id_responsable
        WHERE e.id_equipo = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$eq = $stmt->fetch();
if (!$eq) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

$histSql = "SELECT m.*, t.nombres AS tec_n, t.apellidos AS tec_a,
                   f.codigo_ficha, f.asunto
            FROM equipo_mantenimientos m
            LEFT JOIN usuarios t ON t.id_usuario = m.id_tecnico
            LEFT JOIN equipo_mantenimiento_ficha f ON f.id_mantenimiento = m.id_mantenimiento
            WHERE m.id_equipo = :id
            ORDER BY m.fecha_mantenimiento DESC, m.id_mantenimiento DESC";
$hstmt = $pdo->prepare($histSql);
$hstmt->execute([':id' => $id]);
$historial = $hstmt->fetchAll();

// Evidencias de todos los mantenimientos de este equipo, agrupadas por id_mantenimiento
$evidenciasPorMant = [];
if ($historial) {
    $ids = array_column($historial, 'id_mantenimiento');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $evq = $pdo->prepare("SELECT id_mantenimiento, ruta_archivo, tipo_archivo FROM equipo_mantenimiento_evidencias WHERE id_mantenimiento IN ($marcadores) ORDER BY fecha_subida ASC");
    $evq->execute($ids);
    foreach ($evq->fetchAll() as $ev) {
        $evidenciasPorMant[$ev['id_mantenimiento']][] = $ev;
    }
}

$rol = $_SESSION['rol'] ?? '';
$es_tecnico = $rol === 'tecnico';
$es_admin   = $rol === 'admin';
$puede_mantenimiento = $es_tecnico || $es_admin;

$partesDisponibles = $pdo->query("SELECT nombre FROM partes_catalogo ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
$tecnicosDisponibles = $es_admin ? traer_tecnicos($pdo) : [];

// ---- Semáforo de mantenimiento (política: cada 6 meses) ----
$ultimoMantStmt = $pdo->prepare(
    "SELECT m.fecha_mantenimiento, m.id_mantenimiento, t.nombres AS tec_n, t.apellidos AS tec_a
     FROM equipo_mantenimientos m
     LEFT JOIN usuarios t ON t.id_usuario = m.id_tecnico
     WHERE m.id_equipo = :id
     ORDER BY m.fecha_mantenimiento DESC, m.id_mantenimiento DESC
     LIMIT 1");
$ultimoMantStmt->execute([':id' => $id]);
$ultimoMant = $ultimoMantStmt->fetch();
$semaforo = semaforo_mantenimiento($ultimoMant['fecha_mantenimiento'] ?? null);
$ultimoTecNombre = $ultimoMant && $ultimoMant['tec_n'] ? trim($ultimoMant['tec_n'] . ' ' . $ultimoMant['tec_a']) : null;

$ramStmt = $pdo->prepare("SELECT * FROM equipo_ram WHERE id_equipo = :id ORDER BY orden, id_ram");
$ramStmt->execute([':id' => $id]);
$ramRows = $ramStmt->fetchAll();

$almStmt = $pdo->prepare("SELECT * FROM equipo_almacenamiento WHERE id_equipo = :id ORDER BY orden, id_almacenamiento");
$almStmt->execute([':id' => $id]);
$almRows = $almStmt->fetchAll();

$gpuStmt = $pdo->prepare("SELECT * FROM equipo_gpu WHERE id_equipo = :id ORDER BY orden, id_gpu");
$gpuStmt->execute([':id' => $id]);
$gpuRows = $gpuStmt->fetchAll();

$responsable = $eq['resp_n'] ? trim($eq['resp_n'].' '.$eq['resp_a']) : null;
$error = $_GET['error'] ?? '';

$titulo_pagina = $eq['nombre']; $seccion_activa = 'equipos';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= e($eq['nombre']) ?></h2>
        <p><?= e($eq['marca']) ?> <?= e($eq['modelo'] ?? '') ?> · S/N <?= e($eq['numero_serie'] ?: '—') ?></p>
    </div>
    <div class="acciones-fila">
        <a href="/tickets/paneles/admin_equipo_form.php?id=<?= (int)$id ?>" class="btn btn-sec">Editar</a>
        <a href="/tickets/paneles/admin_equipos.php" class="btn btn-sec"><?= icono('arrow-left') ?> Volver</a>
    </div>
</div>

<?php if ($error==='faltan'): ?><div class="aviso aviso-error">Completa el tipo, estado y fecha del mantenimiento.</div><?php endif; ?>
<?php if (($_GET['msg'] ?? '') === 'mantenimiento'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Mantenimiento registrado correctamente.</div><?php endif; ?>

<div class="panel semaforo-panel">
    <div class="semaforo-caja">
        <div class="semaforo-luces">
            <span class="luz luz-rojo <?= $semaforo['nivel']==='critico' ? 'activa' : '' ?>"></span>
            <span class="luz luz-ambar <?= $semaforo['nivel']==='regular' ? 'activa' : '' ?>"></span>
            <span class="luz luz-verde <?= $semaforo['nivel']==='bien' ? 'activa' : '' ?>"></span>
        </div>
    </div>
    <div class="semaforo-info">
        <div class="semaforo-titulo">Mantenimiento preventivo <span class="semaforo-chip semaforo-chip-<?= e($semaforo['nivel']) ?>"><?= e($semaforo['texto']) ?></span></div>
        <div class="semaforo-detalle"><?= e($semaforo['detalle']) ?> · Política: cada 6 meses.</div>
        <?php if ($ultimoMant): ?>
            <div class="semaforo-ultimo">
                Último mantenimiento: <strong><?= e(fecha_legible_corta($ultimoMant['fecha_mantenimiento'])) ?></strong>
                <?= $ultimoTecNombre ? ' · realizado por <strong>'.e($ultimoTecNombre).'</strong>' : '' ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-titulo">Ubicación y responsable</div>
    <div class="detalle-grid">
        <div class="detalle-item"><div class="lbl">Área</div><div class="val"><?= e($eq['area'] ?? '—') ?></div></div>
        <div class="detalle-item"><div class="lbl">Responsable</div><div class="val"><?= $responsable ? e($responsable) : '—' ?></div></div>
        <div class="detalle-item"><div class="lbl">Tipo</div><div class="val"><?= e($eq['tipo']) ?></div></div>
    </div>
</div>

<div class="panel">
    <div class="panel-titulo">Especificaciones técnicas</div>
    <div class="detalle-grid">
        <div class="detalle-item"><div class="lbl">Procesador</div><div class="val"><?= e($eq['procesador'] ?: '—') ?></div></div>
        <div class="detalle-item"><div class="lbl">Sistema operativo</div><div class="val"><?= e($eq['sistema_operativo'] ?: '—') ?></div></div>
        <div class="detalle-item"><div class="lbl">MAC LAN</div><div class="val"><?= e($eq['mac_lan'] ?: '—') ?></div></div>
        <div class="detalle-item"><div class="lbl">MAC WiFi</div><div class="val"><?= e($eq['mac_wifi'] ?: '—') ?></div></div>
    </div>
    <?php if (!empty($eq['observaciones'])): ?>
        <div class="detalle-item" style="margin-top:16px;">
            <div class="lbl">Observaciones</div>
            <div class="caja-texto"><?= e($eq['observaciones']) ?></div>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-titulo">Memoria RAM</div>
    <?php if (!$ramRows): ?>
        <p style="color:var(--texto-tenue); font-size:.9rem;">No hay memorias registradas.</p>
    <?php else: ?>
        <div class="detalle-grid">
            <?php foreach ($ramRows as $i => $r): ?>
                <div class="detalle-item">
                    <div class="lbl">Memoria <?= $i + 1 ?></div>
                    <div class="val"><?= e(trim(($r['capacidad'] ?? '').' '.($r['tipo'] ?? '')) ?: '—') ?><?= $r['frecuencia'] ? ' · '.e($r['frecuencia']) : '' ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-titulo">Almacenamiento</div>
    <?php if (!$almRows): ?>
        <p style="color:var(--texto-tenue); font-size:.9rem;">No hay unidades de almacenamiento registradas.</p>
    <?php else: ?>
        <div class="detalle-grid">
            <?php foreach ($almRows as $i => $a): ?>
                <div class="detalle-item">
                    <div class="lbl">Unidad <?= $i + 1 ?></div>
                    <div class="val"><?= e(trim(($a['capacidad'] ?? '').' '.($a['tipo'] ?? '')) ?: '—') ?><?= $a['interfaz'] ? ' · '.e($a['interfaz']) : '' ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($gpuRows): ?>
<div class="panel">
    <div class="panel-titulo">Tarjeta gráfica (GPU)</div>
    <div class="detalle-grid">
        <?php foreach ($gpuRows as $i => $g): ?>
            <div class="detalle-item">
                <div class="lbl">GPU <?= $i + 1 ?></div>
                <div class="val"><?= e(trim(($g['marca'] ?? '').' '.($g['modelo'] ?? '')) ?: '—') ?><?= $g['capacidad'] ? ' · '.e($g['capacidad']) : '' ?><?= $g['tipo'] ? ' · '.e($g['tipo']) : '' ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-titulo">Registrar mantenimiento</div>
    <?php if (!$puede_mantenimiento): ?>
        <p style="color:var(--texto-sec); font-size:.88rem;">No tienes permiso para registrar un mantenimiento.</p>
    <?php else: ?>
    <?php if ($es_admin): ?>
        <p style="color:var(--texto-sec); font-size:.85rem; margin-bottom:14px;">
            <?= icono('alert', 14) ?> Como administrador, esto es para cargar mantenimientos del sistema anterior (fecha pasada). Para el día a día, lo normal es que lo registre el técnico que lo realiza.
        </p>
    <?php endif; ?>
    <form action="/tickets/paneles/admin_equipo_mantenimiento_guardar.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_equipo" value="<?= (int)$id ?>">
        <div class="form-grid">
            <?php if ($es_admin): ?>
            <div class="campo">
                <label>Técnico que lo realizó *</label>
                <select name="id_tecnico" required>
                    <option value="" disabled selected>— Selecciona un técnico —</option>
                    <?php foreach ($tecnicosDisponibles as $tc): ?>
                        <option value="<?= (int)$tc['id_usuario'] ?>"><?= e($tc['nombres'].' '.$tc['apellidos']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="campo">
                <label>Tipo *</label>
                <select name="tipo" required>
                    <option value="preventivo">Preventivo</option>
                    <option value="correctivo">Correctivo</option>
                </select>
            </div>
            <div class="campo">
                <label>Estado resultante *</label>
                <select name="estado" required>
                    <option value="operativo">Operativo</option>
                    <option value="mantenimiento">En mantenimiento</option>
                    <option value="malogrado">Malogrado</option>
                </select>
            </div>
            <div class="campo">
                <label>Fecha *</label>
                <input type="date" name="fecha_mantenimiento" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="campo full">
                <label>Partes intervenidas</label>
                <div class="partes-grid">
                    <?php foreach ($partesDisponibles as $p): ?>
                        <label class="parte-check"><input type="checkbox" name="partes[]" value="<?= e($p) ?>"> <?= e($p) ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="text" name="parte_otro" placeholder="Otra parte (se guarda para la próxima vez)" style="margin-top:10px;">
            </div>
            <div class="campo full">
                <label>Conclusiones y observaciones</label>
                <textarea name="descripcion" placeholder="Diagnóstico, qué se hizo, recomendaciones..."></textarea>
            </div>
            <div class="campo full">
                <label>Evidencia (fotos o PDF)</label>
                <input type="file" name="evidencias_mantenimiento[]" accept="image/*,application/pdf" multiple>
                <span class="ayuda-archivo">Hasta 8 archivos, 8 MB cada uno.</span>
            </div>
            <div class="campo full">
                <button type="submit" class="btn"><?= icono('wrench', 16) ?> Guardar y completar ficha</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-titulo">Historial de mantenimiento</div>
    <?php if (!$historial): ?>
        <div class="vacio"><div class="ic"><?= icono('wrench') ?></div><p>Aún no hay mantenimientos registrados para este equipo.</p></div>
    <?php else: ?>
        <?php foreach ($historial as $h): $tec = $h['tec_n'] ? trim($h['tec_n'].' '.$h['tec_a']) : null;
            $evs = $evidenciasPorMant[$h['id_mantenimiento']] ?? [];
        ?>
            <div class="seguimiento">
                <div class="meta">
                    <?= e(fecha_legible_corta($h['fecha_mantenimiento'])) ?> ·
                    <span class="badge badge-<?= e($h['estado']) ?>"><?= e(estado_equipo_legible($h['estado'])) ?></span> ·
                    <?= e(tipo_mantenimiento_legible($h['tipo'])) ?> ·
                    <strong>Atendido por:</strong> <?= $tec ? e($tec) : 'Sin técnico asignado' ?>
                    <span style="color:var(--texto-tenue);">· Registrado <?= e(date('d/m/Y \a \l\a\s h:i a', strtotime($h['creado_en']))) ?></span>
                    <br>
                    <a href="/tickets/paneles/equipo_ficha_editar.php?id=<?= (int)$h['id_mantenimiento'] ?>"><?= icono('clipboard', 13) ?> Editar ficha</a>
                    · <a href="/tickets/paneles/equipo_mantenimiento_reporte.php?id=<?= (int)$h['id_mantenimiento'] ?>" target="_blank"><?= icono('printer', 13) ?> Generar reporte</a>
                </div>
                <?php if (!empty($h['codigo_ficha']) || !empty($h['asunto'])): ?>
                    <div style="margin-bottom:4px; font-size:.85rem; color:var(--texto-sec);">
                        <?php if (!empty($h['codigo_ficha'])): ?><strong>Ficha <?= e($h['codigo_ficha']) ?></strong><?php endif; ?>
                        <?php if (!empty($h['asunto'])): ?><?= !empty($h['codigo_ficha']) ? ' — ' : '' ?><?= e($h['asunto']) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($h['partes'])): ?><div style="margin-bottom:4px;"><strong>Partes:</strong> <?= e($h['partes']) ?></div><?php endif; ?>
                <?php if (!empty($h['descripcion'])): ?><div><?= nl2br(e($h['descripcion'])) ?></div><?php endif; ?>
                <?php if ($evs): ?>
                <div class="mant-evidencia-mini">
                    <?php foreach ($evs as $ev): ?>
                        <?php if ($ev['tipo_archivo'] === 'pdf'): ?>
                            <a href="<?= e($ev['ruta_archivo']) ?>" target="_blank" class="mant-ev-pdf"><?= icono('file-pdf', 15) ?> PDF</a>
                        <?php else: ?>
                            <a href="<?= e($ev['ruta_archivo']) ?>" target="_blank"><img src="<?= e($ev['ruta_archivo']) ?>" alt="Evidencia" loading="lazy"></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
