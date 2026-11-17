<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['tecnico', 'admin']);

$rol    = $_SESSION['rol'] ?? '';
$id_yo  = $_SESSION['id_usuario'];
$codigo = trim($_GET['codigo'] ?? '');
$volver_bandeja = '/tickets/paneles/tecnico_historial.php';

// Traer el ticket completo
$sql = "SELECT t.*, c.nombre AS categoria,
               p.nombre AS prioridad, p.color AS prioridad_color,
               a.nombre AS area, u.nombre AS ubicacion,
               sol.nombres AS sol_nombres, sol.apellidos AS sol_apellidos,
               tec.nombres AS tec_nombres, tec.apellidos AS tec_apellidos
        FROM tickets t
        LEFT JOIN categorias  c   ON c.id_categoria = t.id_categoria
        LEFT JOIN prioridades p   ON p.id_prioridad = t.id_prioridad
        LEFT JOIN areas       a   ON a.id_area = t.id_area
        LEFT JOIN ubicaciones u   ON u.id_ubicacion = t.id_ubicacion
        LEFT JOIN usuarios    sol ON sol.id_usuario = t.id_solicitante
        LEFT JOIN usuarios    tec ON tec.id_usuario = t.id_tecnico
        WHERE t.codigo = :c LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':c' => $codigo]);
$t = $stmt->fetch();
if (!$t) { header("Location: $volver_bandeja"); exit; }

// Técnicos para el desplegable de asignación
$tecnicos = traer_tecnicos($pdo);

// Seguimiento
$segStmt = $pdo->prepare(
    "SELECT s.comentario, s.estado_nuevo, s.fecha, us.nombres, us.apellidos, us.rol
     FROM ticket_seguimiento s JOIN usuarios us ON us.id_usuario = s.id_usuario
     WHERE s.id_ticket = :t ORDER BY s.fecha ASC");
$segStmt->execute([':t' => $t['id_ticket']]);
$seguimientos = $segStmt->fetchAll();

// Evidencia fotográfica adjuntada por el técnico
$evStmt = $pdo->prepare("SELECT ruta_archivo FROM ticket_evidencias WHERE id_ticket = :t ORDER BY fecha_subida ASC");
$evStmt->execute([':t' => $t['id_ticket']]);
$evidencias = $evStmt->fetchAll(PDO::FETCH_COLUMN);

$asignado_a_mi = ((int)$t['id_tecnico'] === (int)$id_yo);
$sin_asignar   = empty($t['id_tecnico']);
$es_final      = in_array($t['estado'], ['completado','cancelado','rechazado'], true);
$tecnico_txt   = $t['tec_nombres'] ? ($t['tec_nombres'].' '.$t['tec_apellidos']) : 'Sin asignar';

$msg = $_GET['msg'] ?? ''; $err = $_GET['error'] ?? '';

$titulo_pagina = formatear_codigo($t['codigo'], $t['fecha_creacion']);
$seccion_activa = 'historial';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= e(formatear_codigo($t['codigo'], $t['fecha_creacion'])) ?> · <?= e($t['titulo']) ?></h2>
        <p>
            <span class="badge badge-<?= e($t['estado']) ?>" id="badge-estado"><?= e(estado_legible($t['estado'])) ?></span>
            &nbsp; Creado el <?= fecha_legible($t['fecha_creacion']) ?>
        </p>
    </div>
    <a href="<?= e($volver_bandeja) ?>" class="btn btn-sec"><?= icono('arrow-left') ?> Volver al historial</a>
</div>

<?php if ($msg==='asignado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Te asignaste este ticket. Ahora está <strong>En atención</strong>.</div>
<?php elseif ($msg==='reasignado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Ticket asignado correctamente.</div>
<?php elseif ($msg==='completado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Ticket marcado como <strong>Completado</strong>.</div>
<?php elseif ($msg==='cancelado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Ticket <strong>Cancelado</strong>.</div>
<?php elseif ($msg==='rechazado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Ticket <strong>Rechazado</strong>.</div>
<?php elseif ($msg==='comentario'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Comentario agregado.</div>
<?php endif; ?>
<?php if ($err==='sol_obligatoria'): ?><div class="aviso aviso-error">Para completar el ticket debes escribir la situación final (qué se hizo).</div>
<?php elseif ($err==='motivo_obligatorio'): ?><div class="aviso aviso-error">Para rechazar el ticket debes indicar el motivo.</div>
<?php elseif ($err==='no_asignado'): ?><div class="aviso aviso-error">Solo el técnico asignado puede cerrar este ticket.</div><?php endif; ?>

<div class="detalle-layout">

    <!-- Columna izquierda: detalle -->
    <div>
        <div class="panel">
            <div class="panel-titulo">Detalle del problema</div>
            <div class="detalle-grid">
                <div class="detalle-item"><div class="lbl">Solicitante</div><div class="val"><?= e($t['sol_nombres'].' '.$t['sol_apellidos']) ?></div></div>
                <div class="detalle-item"><div class="lbl">Prioridad</div><div class="val"><span class="punto" style="background:<?= e($t['prioridad_color']??'#999') ?>;"></span><?= e($t['prioridad']??'—') ?></div></div>
                <div class="detalle-item"><div class="lbl">Categoría</div><div class="val"><?= e($t['categoria']??'—') ?></div></div>
                <div class="detalle-item"><div class="lbl">Área</div><div class="val"><?= e($t['area']??'—') ?></div></div>
                <div class="detalle-item"><div class="lbl">Ubicación</div><div class="val"><?= e($t['ubicacion']??'—') ?></div></div>
                <div class="detalle-item"><div class="lbl">Técnico</div><div class="val"><?= e($tecnico_txt) ?></div></div>
            </div>
            <div class="detalle-item" style="margin-bottom:0;">
                <div class="lbl">Descripción (del solicitante)</div>
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
            <?php if (count($seguimientos)===0): ?>
                <p style="color:var(--texto-sec);">Sin movimientos.</p>
            <?php else: foreach ($seguimientos as $s): ?>
                <div class="seguimiento">
                    <div class="meta"><strong><?= e($s['nombres'].' '.$s['apellidos']) ?></strong> (<?= e(ucfirst($s['rol'])) ?>) · <?= fecha_legible($s['fecha']) ?>
                    <?php if ($s['estado_nuevo']): ?> · <span class="badge badge-<?= e($s['estado_nuevo']) ?>"><?= e(estado_legible($s['estado_nuevo'])) ?></span><?php endif; ?></div>
                    <div><?= e($s['comentario']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Columna derecha: acciones -->
    <div>
        <div class="panel" id="panel-acciones">
            <div class="panel-titulo">Atención</div>

            <?php if ($es_final): ?>
                <div class="vacio" style="padding:24px 10px;">
                    <div class="ic"><?= icono(['completado'=>'check-circle','cancelado'=>'ban','rechazado'=>'x-circle'][$t['estado']] ?? 'lock') ?></div>
                    <p>Este ticket está <strong><?= e(estado_legible($t['estado'])) ?></strong>.</p>
                </div>
            <?php else: ?>

                <?php if ($sin_asignar || $asignado_a_mi || $rol === 'admin'): ?>
                <!-- Rechazar: disponible antes de asignar, para el técnico ya asignado, o para el admin -->
                <div style="border:1px solid var(--borde); border-radius:9px; padding:14px; margin-bottom:18px; background:var(--bg-hover);">
                    <form action="/tickets/paneles/tecnico_accion.php" method="POST">
                        <input type="hidden" name="accion" value="rechazar">
                        <input type="hidden" name="codigo" value="<?= e($t['codigo']) ?>">
                        <div class="campo" style="margin-bottom:10px;">
                            <label>¿Ticket por error o innecesario?</label>
                            <textarea name="motivo" placeholder="Motivo del rechazo (obligatorio)..." style="min-height:64px;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-peligro" style="width:100%;"
                                onclick="return confirm('¿Rechazar este ticket? Quedará marcado como no válido.');"><?= icono('ban') ?> Rechazar ticket</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Asignar técnico -->
                <form action="/tickets/paneles/tecnico_accion.php" method="POST" style="margin-bottom:22px;">
                    <input type="hidden" name="accion" value="asignar">
                    <input type="hidden" name="codigo" value="<?= e($t['codigo']) ?>">
                    <div class="campo" style="margin-bottom:12px;">
                        <label>Asignar a técnico</label>
                        <select name="id_tecnico" required>
                            <?php if ($rol === 'tecnico'): ?>
                                <option value="<?= (int)$id_yo ?>">— Asignarme a mí —</option>
                            <?php else: ?>
                                <option value="" disabled selected>— Selecciona un técnico —</option>
                            <?php endif; ?>
                            <?php foreach ($tecnicos as $tc): if ($rol === 'tecnico' && (int)$tc['id_usuario']===(int)$id_yo) continue; ?>
                                <option value="<?= (int)$tc['id_usuario'] ?>" <?= (int)$t['id_tecnico']===(int)$tc['id_usuario']?'selected':'' ?>>
                                    <?= e($tc['nombres'].' '.$tc['apellidos']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn" style="width:100%;">
                        <?= $sin_asignar ? icono('wrench') . ' Asignar y atender' : 'Reasignar' ?>
                    </button>
                </form>

                <!-- Cierre: el técnico asignado, o el admin (puede cerrar cualquier ticket ya asignado) -->
                <?php if ($asignado_a_mi || ($rol === 'admin' && !$sin_asignar)): ?>
                    <div style="border-top:1px solid var(--borde); padding-top:18px;">
                        <form action="/tickets/paneles/tecnico_accion.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="codigo" value="<?= e($t['codigo']) ?>">
                            <div class="campo" style="margin-bottom:12px;">
                                <label>Diagnóstico</label>
                                <textarea name="diagnostico" placeholder="¿Cuál fue la causa del problema?"><?= e($t['diagnostico']??'') ?></textarea>
                            </div>
                            <div class="campo" style="margin-bottom:12px;">
                                <label>Situación final (qué se hizo)</label>
                                <textarea name="solucion" placeholder="Describe cómo quedó resuelto el problema..."><?= e($t['solucion']??'') ?></textarea>
                            </div>
                            <div class="campo" style="margin-bottom:12px;">
                                <label>Recomendación <span style="color:var(--texto-tenue); font-weight:400;">(opcional)</span></label>
                                <textarea name="recomendacion" placeholder="Alguna recomendación para evitar que vuelva a pasar..."><?= e($t['recomendacion']??'') ?></textarea>
                            </div>
                            <div class="campo" style="margin-bottom:12px;">
                                <label><?= icono('camera', 14) ?> Evidencia fotográfica <span style="color:var(--texto-tenue); font-weight:400;">(opcional)</span></label>
                                <input type="file" name="evidencias[]" accept="image/*" multiple>
                                <span class="ayuda-archivo">Hasta 6 fotos, 4&nbsp;MB cada una.</span>
                            </div>
                            <button type="submit" name="accion" value="completar" class="btn" style="width:100%; background:var(--exito);"><?= icono('check') ?> Completar</button>
                        </form>
                    </div>
                <?php elseif (!$sin_asignar): ?>
                    <p style="color:var(--texto-sec); font-size:.85rem; border-top:1px solid var(--borde); padding-top:16px;">
                        Este ticket está asignado a <strong><?= e($tecnico_txt) ?></strong>. Solo esa persona (o un administrador) puede cerrarlo.
                    </p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Resalte animado cuando el ticket está en atención */
.badge-en_atencion { animation: pulso 2s ease-in-out infinite; }
@keyframes pulso {
    0%,100% { box-shadow: 0 0 0 0 rgba(217,119,6,.4); }
    50%     { box-shadow: 0 0 0 6px rgba(217,119,6,0); }
}
@media (max-width:900px){ div[style*="grid-template-columns:1.6fr"]{ grid-template-columns:1fr !important; } }
</style>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>