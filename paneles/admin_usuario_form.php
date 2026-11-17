<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);
$soy_tecnico = ($_SESSION['rol'] ?? '') === 'tecnico';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;
$u = ['nombres'=>'','apellidos'=>'','dni'=>'','correo'=>'','usuario'=>'','rol'=>'solicitante','id_area'=>'','telefono'=>''];

if ($editando) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
    $stmt->execute([':id'=>$id]);
    $u = $stmt->fetch();
    if (!$u) { header('Location: /tickets/paneles/admin_usuarios.php'); exit; }
    // Un técnico no puede editar cuentas de administrador.
    if ($soy_tecnico && $u['rol'] === 'admin') { header('Location: /tickets/paneles/admin_usuarios.php'); exit; }
}
$areas = $pdo->query("SELECT id_area, nombre FROM areas WHERE activo=TRUE ORDER BY nombre")->fetchAll();
$error = $_GET['error'] ?? '';

$titulo_pagina  = $editando ? 'Editar usuario' : 'Nuevo usuario';
$seccion_activa = 'usuarios';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= $editando?'Editar usuario':'Nuevo usuario' ?></h2>
        <p><?= $editando?'Modifica los datos del usuario.':'Registra una nueva persona en el sistema.' ?></p>
    </div>
    <a href="/tickets/paneles/admin_usuarios.php" class="btn btn-sec"><?= icono('arrow-left') ?> Volver</a>
</div>

<?php if ($error==='faltan'): ?><div class="aviso aviso-error">Completa los campos obligatorios (*).</div>
<?php elseif ($error==='usuario_repetido'): ?><div class="aviso aviso-error">Ese nombre de usuario ya existe.</div>
<?php elseif ($error==='correo_repetido'): ?><div class="aviso aviso-error">Ese correo ya está registrado.</div>
<?php elseif ($error==='guardar'): ?><div class="aviso aviso-error">No se pudo guardar. Revisa los datos.</div>
<?php elseif ($error==='no_permitido'): ?><div class="aviso aviso-error">Solo un administrador puede crear o editar cuentas de administrador.</div>
<?php endif; ?>

<div class="panel">
    <form action="/tickets/paneles/admin_usuario_guardar.php" method="POST">
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label>Nombres *</label><input type="text" name="nombres" maxlength="100" value="<?= e($u['nombres']) ?>" required></div>
            <div class="campo"><label>Apellidos *</label><input type="text" name="apellidos" maxlength="100" value="<?= e($u['apellidos']) ?>" required></div>
            <div class="campo"><label>Nombre de usuario *</label><input type="text" name="usuario" maxlength="50" value="<?= e($u['usuario']) ?>" required></div>
            <div class="campo"><label>Rol *</label>
                <select name="rol" required>
                    <option value="solicitante" <?= $u['rol']==='solicitante'?'selected':'' ?>>Solicitante</option>
                    <option value="tecnico" <?= $u['rol']==='tecnico'?'selected':'' ?>>Técnico</option>
                    <?php if (!$soy_tecnico): ?>
                    <option value="admin" <?= $u['rol']==='admin'?'selected':'' ?>>Administrador</option>
                    <?php endif; ?>
                </select>
                <?php if ($soy_tecnico): ?><small>Solo un administrador puede crear otras cuentas de administrador.</small><?php endif; ?>
            </div>
            <div class="campo"><label>Correo</label><input type="email" name="correo" maxlength="150" value="<?= e($u['correo']??'') ?>"></div>
            <div class="campo"><label>DNI</label><input type="text" name="dni" maxlength="15" value="<?= e($u['dni']??'') ?>"></div>
            <div class="campo"><label>Teléfono</label><input type="text" name="telefono" maxlength="20" value="<?= e($u['telefono']??'') ?>"></div>
            <div class="campo"><label>Área</label>
                <select name="id_area">
                    <option value="">— Selecciona —</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= (int)$a['id_area'] ?>" <?= ($u['id_area']??'')==$a['id_area']?'selected':'' ?>><?= e($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo full">
                <label><?= $editando?'Nueva contraseña (vacío = no cambiar)':'Contraseña inicial *' ?></label>
                <input type="password" name="password" autocomplete="new-password" placeholder="<?= $editando?'Solo si deseas cambiarla':'Escribe la contraseña inicial' ?>" <?= $editando?'':'required' ?>>
                <small>Se guarda encriptada. El usuario podrá cambiarla luego.</small>
            </div>
            <div class="campo full">
                <button type="submit" class="btn"><?= $editando?'Guardar cambios':'Crear usuario' ?></button>
            </div>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>