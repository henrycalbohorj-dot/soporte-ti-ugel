<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;
$a = ['nombre' => '', 'id_jefe' => '', 'ubicacion' => '', 'detalle' => ''];

if ($editando) {
    $stmt = $pdo->prepare("SELECT * FROM areas WHERE id_area = :id");
    $stmt->execute([':id' => $id]);
    $a = $stmt->fetch();
    if (!$a) { header('Location: /tickets/paneles/admin_areas.php'); exit; }
}
$error = $_GET['error'] ?? '';

$usuarios = $pdo->query("SELECT id_usuario, nombres, apellidos FROM usuarios WHERE activo = TRUE ORDER BY nombres, apellidos")->fetchAll();

$titulo_pagina  = $editando ? 'Editar área' : 'Nueva área';
$seccion_activa = 'areas';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= $editando ? 'Editar área' : 'Nueva área' ?></h2>
        <p><?= $editando ? 'Modifica los datos del área.' : 'Registra una nueva área o departamento.' ?></p>
    </div>
    <a href="/tickets/paneles/admin_areas.php" class="btn btn-sec"><?= icono('arrow-left') ?> Volver</a>
</div>

<?php if ($error==='faltan'): ?><div class="aviso aviso-error">Escribe el nombre del área.</div>
<?php elseif ($error==='repetida'): ?><div class="aviso aviso-error">Ya existe un área con ese nombre.</div>
<?php elseif ($error==='guardar'): ?><div class="aviso aviso-error">No se pudo guardar. Revisa los datos.</div>
<?php endif; ?>

<div class="panel">
    <form action="/tickets/paneles/admin_area_guardar.php" method="POST">
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo full">
                <label>Nombre *</label>
                <input type="text" name="nombre" maxlength="100" value="<?= e($a['nombre']) ?>" required autofocus>
            </div>
            <div class="campo">
                <label>Jefe a cargo</label>
                <select name="id_jefe">
                    <option value="">Sin asignar</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id_usuario'] ?>" <?= (string)($a['id_jefe'] ?? '')===(string)$u['id_usuario']?'selected':'' ?>><?= e($u['nombres'].' '.$u['apellidos']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label>Ubicación</label>
                <input type="text" name="ubicacion" maxlength="150" value="<?= e($a['ubicacion'] ?? '') ?>" placeholder="Sede Central - Piso 2">
            </div>
            <div class="campo full">
                <label>Detalle</label>
                <textarea name="detalle" placeholder="Notas adicionales sobre el área..."><?= e($a['detalle'] ?? '') ?></textarea>
            </div>
            <div class="campo full">
                <button type="submit" class="btn"><?= $editando ? 'Guardar cambios' : 'Crear área' ?></button>
            </div>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
