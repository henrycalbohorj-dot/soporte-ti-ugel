<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['solicitante']);

$id_yo  = $_SESSION['id_usuario'];
$codigo = trim($_GET['codigo'] ?? '');

// Traer el ticket, asegurando que pertenezca a este usuario
$sql = "SELECT t.*,
               c.nombre AS categoria,
               p.nombre AS prioridad, p.color AS prioridad_color,
               a.nombre AS area,
               u.nombre AS ubicacion,
               tec.nombres AS tec_nombres, tec.apellidos AS tec_apellidos
        FROM tickets t
        LEFT JOIN categorias  c  ON c.id_categoria = t.id_categoria
        LEFT JOIN prioridades p  ON p.id_prioridad = t.id_prioridad
        LEFT JOIN areas       a  ON a.id_area = t.id_area
        LEFT JOIN ubicaciones u  ON u.id_ubicacion = t.id_ubicacion
        LEFT JOIN usuarios    tec ON tec.id_usuario = t.id_tecnico
        WHERE t.codigo = :codigo AND t.id_solicitante = :id
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':codigo' => $codigo, ':id' => $id_yo]);
$t = $stmt->fetch();

// Si no existe o no es suyo, volver a la lista
if (!$t) {
    header('Location: /tickets/paneles/usuario.php');
    exit;
}

// Traer el historial de seguimiento
$segStmt = $pdo->prepare(
    "SELECT s.comentario, s.estado_nuevo, s.fecha,
            us.nombres, us.apellidos, us.rol
     FROM ticket_seguimiento s
     JOIN usuarios us ON us.id_usuario = s.id_usuario
     WHERE s.id_ticket = :t
     ORDER BY s.fecha ASC");
$segStmt->execute([':t' => $t['id_ticket']]);
$seguimientos = $segStmt->fetchAll();

// Evidencia fotográfica adjuntada por el técnico
$evStmt = $pdo->prepare("SELECT ruta_archivo FROM ticket_evidencias WHERE id_ticket = :t ORDER BY fecha_subida ASC");
$evStmt->execute([':t' => $t['id_ticket']]);
$evidencias = $evStmt->fetchAll(PDO::FETCH_COLUMN);

$tecnico_txt = $t['tec_nombres'] ? ($t['tec_nombres'] . ' ' . $t['tec_apellidos']) : 'Aún sin asignar';

// El solicitante puede cancelar su propio ticket (con motivo obligatorio) mientras
// no haya sido cerrado todavía, por ejemplo si resolvió el problema por su cuenta.
$puede_cancelar = in_array($t['estado'], ['pendiente', 'en_atencion'], true);

$msg = $_GET['msg'] ?? ''; $err = $_GET['error'] ?? '';

$titulo_pagina  = formatear_codigo($t['codigo'], $t['fecha_creacion']);
$seccion_activa = 'tickets';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= e(formatear_codigo($t['codigo'], $t['fecha_creacion'])) ?> · <?= e($t['titulo']) ?></h2>
        <p>
            <span class="badge badge-<?= e($t['estado']) ?>"><?= e(estado_legible($t['estado'])) ?></span>
            &nbsp; Creado el <?= fecha_legible($t['fecha_creacion']) ?>
        </p>
    </div>
    <a href="/tickets/paneles/usuario.php" class="btn btn-sec"><?= icono('arrow-left') ?> Volver</a>
</div>

<?php if ($msg==='cancelado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Tu ticket fue <strong>cancelado</strong> correctamente.</div>
<?php endif; ?>
<?php if ($err==='motivo_obligatorio'): ?><div class="aviso aviso-error">Para cancelar el ticket debes indicar el motivo.</div><?php endif; ?>

<div class="detalle-layout">

    <!-- Columna izquierda: detalle -->
    <div>
        <div class="panel">
            <div class="panel-titulo">Detalle del ticket</div>
            <div class="detalle-grid">
                <div class="detalle-item"><div class="lbl">Prioridad</div><div class="val"><span class="punto" style="background:<?= e($t['prioridad_color'] ?? '#999') ?>;"></span><?= e($t['prioridad'] ?? '—') ?></div></div>
                <div class="detalle-item"><div class="lbl">Categoría</div><div class="val"><?= e($t['categoria'] ?? '—') ?></div></div>
                <div class="detalle-item"><div class="lbl">Área</div><div class="val"><?= e($t['area'] ?? '—') ?></div></div>
                <div class="detalle-item"><div class="lbl">Ubicación</div><div class="val"><?= e($t['ubicacion'] ?? '—') ?></div></div>
            </div>
            <div class="detalle-item" style="margin-bottom:0;">
                <div class="lbl">Descripción del problema</div>
                <div class="caja-texto"><?= $t['descripcion'] !== null && $t['descripcion'] !== '' ? e($t['descripcion']) : '<span style="color:var(--texto-sec);">El solicitante no agregó una descripción.</span>' ?></div>
            </div>
        </div>

        <?php if (!empty($t['diagnostico']) || !empty($t['solucion']) || !empty($t['recomendacion']) || count($evidencias) > 0): ?>
        <div class="panel">
            <div class="panel-titulo">Informe de atención</div>
            <div class="informe-atencion">
                <?php if (!empty($t['diagnostico'])): ?>
                <div class="informe-item">
                    <div class="lbl">Diagnóstico</div>
                    <div class="txt"><?= nl2br(e($t['diagnostico'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($t['solucion'])): ?>
                <div class="informe-item">
                    <div class="lbl">Situación final</div>
                    <div class="txt"><?= nl2br(e($t['solucion'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($t['recomendacion'])): ?>
                <div class="informe-item">
                    <div class="lbl">Recomendación</div>
                    <div class="txt"><?= nl2br(e($t['recomendacion'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if (count($evidencias) > 0): ?>
                <div class="informe-item">
                    <a href="/tickets/paneles/ticket_evidencia.php?codigo=<?= urlencode($t['codigo']) ?>" class="btn btn-evidencia">
                        <?= icono('camera', 16) ?> Ver evidencia fotográfica
                        <span class="chip-contador"><?= count($evidencias) ?></span>
                    </a>
                </div>
                <?php endif; ?>
                <div class="informe-firma">
                    Atendido por <strong><?= e($tecnico_txt) ?></strong><?php if (!empty($t['fecha_cierre'])): ?> · <?= fecha_legible($t['fecha_cierre']) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-titulo">Seguimiento</div>
            <?php if (count($seguimientos) === 0): ?>
                <p style="color:var(--texto-sec);">Aún no hay movimientos registrados.</p>
            <?php else: foreach ($seguimientos as $s): ?>
                <div class="seguimiento">
                    <div class="meta">
                        <strong><?= e($s['nombres'] . ' ' . $s['apellidos']) ?></strong>
                        (<?= e(ucfirst($s['rol'])) ?>) · <?= fecha_legible($s['fecha']) ?>
                        <?php if ($s['estado_nuevo']): ?>
                            · <span class="badge badge-<?= e($s['estado_nuevo']) ?>"><?= e(estado_legible($s['estado_nuevo'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div><?= e($s['comentario']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Columna derecha: resumen -->
    <div>
        <div class="panel">
            <div class="panel-titulo">Estado actual</div>
            <div class="detalle-item" style="margin-bottom:0;">
                <div class="lbl">Situación</div>
                <div class="val"><span class="badge badge-<?= e($t['estado']) ?>"><?= e(estado_legible($t['estado'])) ?></span></div>
            </div>
        </div>

        <?php if ($puede_cancelar): ?>
        <div class="panel">
            <div class="panel-titulo">¿Ya no necesitas este ticket?</div>
            <p style="color:var(--texto-sec); font-size:.85rem; margin:-6px 0 14px;">Si resolviste el problema por tu cuenta o ya no es necesario, puedes cancelarlo indicando el motivo.</p>
            <form action="/tickets/paneles/usuario_accion.php" method="POST">
                <input type="hidden" name="accion" value="cancelar">
                <input type="hidden" name="codigo" value="<?= e($t['codigo']) ?>">
                <div class="campo" style="margin-bottom:12px;">
                    <label>Motivo de la cancelación</label>
                    <textarea name="motivo" placeholder="Ejemplo: Ya resolví el problema por mi cuenta." style="min-height:64px;"></textarea>
                </div>
                <button type="submit" class="btn btn-peligro" style="width:100%;"
                        onclick="return confirm('¿Cancelar este ticket? Esta acción no se puede deshacer.');"><?= icono('ban') ?> Cancelar ticket</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-titulo">Atención</div>
            <div class="detalle-grid" style="grid-template-columns:1fr;">
                <div class="detalle-item"><div class="lbl">Técnico asignado</div><div class="val"><?= e($tecnico_txt) ?></div></div>
                <div class="detalle-item"><div class="lbl">Creado</div><div class="val"><?= fecha_legible($t['fecha_creacion']) ?></div></div>
                <?php if (!empty($t['fecha_asignacion'])): ?>
                <div class="detalle-item"><div class="lbl">Asignado</div><div class="val"><?= fecha_legible($t['fecha_asignacion']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($t['fecha_cierre'])): ?>
                <div class="detalle-item" style="margin-bottom:0;"><div class="lbl">Cerrado</div><div class="val"><?= fecha_legible($t['fecha_cierre']) ?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
