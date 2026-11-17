<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['solicitante']);
$cat = traer_catalogos($pdo);
$error = $_GET['error'] ?? '';

// El área del ticket es siempre la del solicitante que inició sesión (no se elige).
$stmtArea = $pdo->prepare("SELECT a.nombre FROM usuarios u LEFT JOIN areas a ON a.id_area = u.id_area WHERE u.id_usuario = :id");
$stmtArea->execute([':id' => $_SESSION['id_usuario']]);
$miArea = $stmtArea->fetchColumn() ?: 'Sin área asignada';

$titulo_pagina='Nuevo ticket'; $seccion_activa='nuevo';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div><h2>Nuevo ticket</h2><p>Describe tu problema y el equipo de soporte lo atenderá.</p></div>
    <a href="/tickets/paneles/usuario.php" class="btn btn-sec"><?= icono('arrow-left') ?> Volver</a>
</div>
<?php if ($error==='faltan'): ?><div class="aviso aviso-error">Escribe el motivo del ticket.</div>
<?php elseif ($error==='guardar'): ?><div class="aviso aviso-error">No se pudo guardar el ticket. Inténtalo de nuevo.</div><?php endif; ?>
<div class="panel">
    <form action="/tickets/paneles/usuario_guardar.php" method="POST">
        <div class="form-grid">
            <div class="campo full"><label>Motivo del ticket *</label>
                <input type="text" name="titulo" maxlength="150" placeholder="Ej: La impresora de mi oficina no imprime" required></div>
            <div class="campo full"><label>Descripción del problema <span style="color:var(--texto-sec); font-weight:400;">(opcional)</span></label>
                <textarea name="descripcion" placeholder="Explica con detalle qué ocurre, desde cuándo, y cualquier dato útil."></textarea></div>
            <div class="campo"><label>Categoría</label>
                <select name="id_categoria"><option value="">— Selecciona —</option>
                <?php foreach ($cat['categorias'] as $c): ?><option value="<?= (int)$c['id_categoria'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label>Prioridad</label>
                <select name="id_prioridad"><option value="">— Selecciona —</option>
                <?php foreach ($cat['prioridades'] as $p): ?><option value="<?= (int)$p['id_prioridad'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label>Área</label>
                <input type="text" value="<?= e($miArea) ?>" disabled></div>
            <div class="campo"><label>Ubicación</label>
                <select name="id_ubicacion"><option value="">— Selecciona —</option>
                <?php foreach ($cat['ubicaciones'] as $u): ?><option value="<?= (int)$u['id_ubicacion'] ?>"><?= e($u['nombre']) ?></option><?php endforeach; ?></select></div>
            <div class="campo full"><button type="submit" class="btn">Enviar ticket</button></div>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>