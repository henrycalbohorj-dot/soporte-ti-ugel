<?php
/**
 * Panel dedicado: muestra en grande toda la evidencia fotográfica
 * adjuntada a un ticket. Accesible para técnicos/admin (cualquier ticket)
 * y para el solicitante dueño del ticket (solo el suyo).
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_login();

$rol    = $_SESSION['rol'] ?? '';
$id_yo  = $_SESSION['id_usuario'];
$codigo = trim($_GET['codigo'] ?? '');

$sql = "SELECT t.id_ticket, t.codigo, t.titulo, t.fecha_creacion, t.id_solicitante,
               tec.nombres AS tec_nombres, tec.apellidos AS tec_apellidos
        FROM tickets t
        LEFT JOIN usuarios tec ON tec.id_usuario = t.id_tecnico
        WHERE t.codigo = :c LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':c' => $codigo]);
$t = $stmt->fetch();
if (!$t) { redirigir_a_su_panel(); exit; }

// Permisos: técnicos/admin pueden ver la evidencia de cualquier ticket;
// el solicitante solo puede ver la del suyo.
$puede_ver = in_array($rol, ['tecnico', 'admin'], true) || ((int)$t['id_solicitante'] === (int)$id_yo);
if (!$puede_ver) { redirigir_a_su_panel(); exit; }

$volver = $rol === 'solicitante'
    ? '/tickets/paneles/usuario_detalle.php?codigo=' . urlencode($codigo)
    : '/tickets/paneles/tecnico_detalle.php?codigo=' . urlencode($codigo);

$evStmt = $pdo->prepare(
    "SELECT e.ruta_archivo, e.fecha_subida, u.nombres, u.apellidos
     FROM ticket_evidencias e
     LEFT JOIN usuarios u ON u.id_usuario = e.id_usuario
     WHERE e.id_ticket = :t ORDER BY e.fecha_subida ASC");
$evStmt->execute([':t' => $t['id_ticket']]);
$evidencias = $evStmt->fetchAll();

// Si no hay fotos (ej. se accedió directo por URL), no tiene sentido este panel.
if (count($evidencias) === 0) { header("Location: $volver"); exit; }

$titulo_pagina  = 'Evidencia fotográfica';
$seccion_activa = $rol === 'solicitante' ? 'tickets' : 'historial';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= icono('camera', 22) ?> Evidencia fotográfica</h2>
        <p><?= e(formatear_codigo($t['codigo'], $t['fecha_creacion'])) ?> · <?= e($t['titulo']) ?>
            &nbsp;·&nbsp; <?= count($evidencias) ?> foto<?= count($evidencias) === 1 ? '' : 's' ?></p>
    </div>
    <a href="<?= e($volver) ?>" class="btn btn-sec"><?= icono('arrow-left') ?> Volver al ticket</a>
</div>

<div class="galeria-evidencia">
    <?php foreach ($evidencias as $i => $ev):
        $autor = trim(($ev['nombres'] ?? '') . ' ' . ($ev['apellidos'] ?? ''));
    ?>
    <div class="galeria-item">
        <a href="<?= e($ev['ruta_archivo']) ?>" class="galeria-foto" data-idx="<?= $i ?>">
            <img src="<?= e($ev['ruta_archivo']) ?>" alt="Evidencia <?= $i + 1 ?>" loading="lazy">
        </a>
        <div class="galeria-meta">
            <span class="g-num"><?= icono('camera', 13) ?> Foto <?= $i + 1 ?></span>
            <span class="g-fecha"><?= fecha_legible($ev['fecha_subida']) ?><?php if ($autor !== ''): ?><br><?= e($autor) ?><?php endif; ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Visor a pantalla completa -->
<div class="lightbox-overlay" id="lightboxEvidencia">
    <button type="button" class="lightbox-cerrar" onclick="cerrarLightbox()" aria-label="Cerrar"><?= icono('x', 20) ?></button>
    <?php if (count($evidencias) > 1): ?>
    <button type="button" class="lightbox-nav lightbox-prev" onclick="moverLightbox(-1)" aria-label="Anterior"><?= icono('arrow-left', 22) ?></button>
    <button type="button" class="lightbox-nav lightbox-next" onclick="moverLightbox(1)" aria-label="Siguiente"><?= icono('arrow-right', 22) ?></button>
    <?php endif; ?>
    <img src="" alt="Evidencia ampliada">
    <div class="lightbox-contador"></div>
</div>
<script>
(function(){
    var fotos = <?= json_encode(array_values(array_map(function($e){ return $e['ruta_archivo']; }, $evidencias))) ?>;
    var idxActual = 0;
    var overlay = document.getElementById('lightboxEvidencia');
    var imgEl = overlay.querySelector('img');
    var contadorEl = overlay.querySelector('.lightbox-contador');

    function abrir(i){
        idxActual = i;
        imgEl.src = fotos[idxActual];
        contadorEl.textContent = (idxActual + 1) + ' / ' + fotos.length;
        overlay.classList.add('activo');
    }
    window.cerrarLightbox = function(){ overlay.classList.remove('activo'); };
    window.moverLightbox = function(delta){
        idxActual = (idxActual + delta + fotos.length) % fotos.length;
        abrir(idxActual);
    };

    document.querySelectorAll('.galeria-foto').forEach(function(a){
        a.addEventListener('click', function(ev){
            ev.preventDefault();
            abrir(parseInt(this.dataset.idx, 10));
        });
    });
    // Cerrar al hacer clic en el fondo (no en la foto ni en los botones)
    overlay.addEventListener('click', function(ev){
        if (ev.target === overlay) cerrarLightbox();
    });
    document.addEventListener('keydown', function(ev){
        if (!overlay.classList.contains('activo')) return;
        if (ev.key === 'Escape') cerrarLightbox();
        if (ev.key === 'ArrowLeft') moverLightbox(-1);
        if (ev.key === 'ArrowRight') moverLightbox(1);
    });
})();
</script>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
