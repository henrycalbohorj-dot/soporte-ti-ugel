<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);

$sql = "SELECT a.id_area, a.nombre, a.activo, a.ubicacion, a.detalle,
               j.nombres AS jefe_n, j.apellidos AS jefe_a,
               (SELECT COUNT(*) FROM usuarios u WHERE u.id_area = a.id_area) AS n_usuarios,
               (SELECT COUNT(*) FROM tickets t WHERE t.id_area = a.id_area) AS n_tickets
        FROM areas a
        LEFT JOIN usuarios j ON j.id_usuario = a.id_jefe
        ORDER BY a.nombre";
$areas = $pdo->query($sql)->fetchAll();
$msg = $_GET['msg'] ?? '';

$titulo_pagina = 'Áreas'; $seccion_activa = 'areas';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2>Gestión de áreas</h2>
        <p>Áreas o departamentos que pueden solicitar soporte.</p>
    </div>
    <a href="/tickets/paneles/admin_area_form.php" class="btn"><?= icono('plus') ?> Nueva área</a>
</div>

<?php if ($msg==='creada'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Área creada correctamente.</div>
<?php elseif ($msg==='editada'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Área actualizada.</div>
<?php elseif ($msg==='borrada'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Área eliminada.</div>
<?php elseif ($msg==='desactivada'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> El área está en uso (tiene tickets o usuarios), así que se desactivó en lugar de borrarla, para conservar el historial.</div>
<?php elseif ($msg==='estado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Estado del área actualizado.</div>
<?php endif; ?>

<div class="panel">
    <table class="tabla">
        <thead>
            <tr><th>Nombre</th><th>Jefe a cargo</th><th>Ubicación</th><th>Usuarios</th><th>Tickets</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php if (empty($areas)): ?>
            <tr><td colspan="7" style="text-align:center; color:var(--texto-tenue); padding:24px;">Aún no hay áreas registradas.</td></tr>
        <?php endif; ?>
        <?php foreach ($areas as $a): $jefe = $a['jefe_n'] ? trim($a['jefe_n'].' '.$a['jefe_a']) : null; ?>
            <tr>
                <td>
                    <?= e($a['nombre']) ?>
                    <?php if (!empty($a['detalle'])): ?>
                        <div class="sub" style="font-size:.78rem; color:var(--texto-sec); margin-top:2px;" title="<?= e($a['detalle']) ?>"><?= e(mb_strimwidth($a['detalle'], 0, 60, '…')) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= $jefe ? e($jefe) : '<span style="color:var(--texto-tenue);">Sin asignar</span>' ?></td>
                <td><?= !empty($a['ubicacion']) ? e($a['ubicacion']) : '<span style="color:var(--texto-tenue);">—</span>' ?></td>
                <td><?= (int)$a['n_usuarios'] ?></td>
                <td><?= (int)$a['n_tickets'] ?></td>
                <td>
                    <?php if ($a['activo']): ?><span class="badge badge-activo">Activo</span>
                    <?php else: ?><span class="badge badge-inactivo">Inactivo</span><?php endif; ?>
                </td>
                <td>
                    <div class="acciones-fila">
                        <a href="/tickets/paneles/admin_area_form.php?id=<?= (int)$a['id_area'] ?>" class="btn btn-sec btn-sm">Editar</a>
                        <a href="/tickets/paneles/admin_area_accion.php?accion=estado&id=<?= (int)$a['id_area'] ?>" class="btn btn-sec btn-sm"><?= $a['activo']?'Desactivar':'Activar' ?></a>
                        <a href="/tickets/paneles/admin_area_accion.php?accion=borrar&id=<?= (int)$a['id_area'] ?>" class="btn btn-peligro btn-sm"
                           onclick="return confirm('¿Eliminar el área <?= e($a['nombre']) ?>? Si está en uso, se desactivará para conservar el historial.');">Borrar</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
