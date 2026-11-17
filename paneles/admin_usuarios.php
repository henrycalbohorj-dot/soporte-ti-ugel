<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);
$soy_tecnico = ($_SESSION['rol'] ?? '') === 'tecnico';

$sql = "SELECT u.id_usuario, u.nombres, u.apellidos, u.usuario, u.correo,
               u.rol, u.activo, a.nombre AS area
        FROM usuarios u
        LEFT JOIN areas a ON a.id_area = u.id_area
        ORDER BY u.rol, u.nombres";
$usuarios = $pdo->query($sql)->fetchAll();
$msg = $_GET['msg'] ?? '';

$titulo_pagina  = 'Usuarios';
$seccion_activa = 'usuarios';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2>Gestión de usuarios</h2>
        <p>Crea y administra a las personas que usan el sistema.</p>
    </div>
    <a href="/tickets/paneles/admin_usuario_form.php" class="btn"><?= icono('plus') ?> Nuevo usuario</a>
</div>

<?php if ($msg==='creado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Usuario creado correctamente.</div>
<?php elseif ($msg==='editado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Usuario actualizado.</div>
<?php elseif ($msg==='borrado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Usuario eliminado.</div>
<?php elseif ($msg==='desactivado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> El usuario tenía tickets, así que se desactivó en lugar de borrarlo (para conservar el historial).</div>
<?php elseif ($msg==='estado'): ?><div class="aviso aviso-exito"><?= icono('check-circle', 15) ?> Estado del usuario actualizado.</div>
<?php endif; ?>

<div class="panel">
    <table class="tabla">
        <thead>
            <tr>
                <th>Nombre</th><th>Usuario</th><th>Correo</th>
                <th>Rol</th><th>Área</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= e($u['nombres'].' '.$u['apellidos']) ?></td>
                <td class="mono"><?= e($u['usuario']) ?></td>
                <td><?= e($u['correo'] ?? '—') ?></td>
                <td><span class="badge badge-rol-<?= e($u['rol']) ?>"><?= e(ucfirst($u['rol'])) ?></span></td>
                <td><?= e($u['area'] ?? '—') ?></td>
                <td>
                    <?php if ($u['activo']): ?><span class="badge badge-activo">Activo</span>
                    <?php else: ?><span class="badge badge-inactivo">Inactivo</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($soy_tecnico && $u['rol'] === 'admin'): ?>
                        <span style="color:var(--texto-tenue);font-size:.8rem;">Solo un administrador puede editar esta cuenta</span>
                    <?php else: ?>
                    <div class="acciones-fila">
                        <a href="/tickets/paneles/admin_usuario_form.php?id=<?= (int)$u['id_usuario'] ?>" class="btn btn-sec btn-sm">Editar</a>
                        <?php if ($u['id_usuario'] != $_SESSION['id_usuario']): ?>
                            <a href="/tickets/paneles/admin_usuario_accion.php?accion=estado&id=<?= (int)$u['id_usuario'] ?>" class="btn btn-sec btn-sm"><?= $u['activo']?'Desactivar':'Activar' ?></a>
                            <a href="/tickets/paneles/admin_usuario_accion.php?accion=borrar&id=<?= (int)$u['id_usuario'] ?>" class="btn btn-peligro btn-sm"
                               onclick="return confirm('¿Eliminar a <?= e($u['nombres']) ?>? Si tiene tickets, se desactivará para conservar el historial.');">Borrar</a>
                        <?php else: ?><span style="color:var(--texto-tenue);font-size:.8rem;">(tú)</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>