<?php
/**
 * GUARDAR USUARIO (crear o editar) - solo admin
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);
$soy_tecnico = ($_SESSION['rol'] ?? '') === 'tecnico';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/paneles/admin_usuarios.php');
    exit;
}

$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$editando = $id > 0;

// Un técnico no puede crear/editar cuentas con rol admin, ni tocar una cuenta que ya es admin.
if ($soy_tecnico) {
    if (($_POST['rol'] ?? '') === 'admin') {
        $destino = $editando ? "?id=$id&error=no_permitido" : "?error=no_permitido";
        header("Location: /tickets/paneles/admin_usuario_form.php$destino");
        exit;
    }
    if ($editando) {
        $chk = $pdo->prepare("SELECT rol FROM usuarios WHERE id_usuario = :id");
        $chk->execute([':id' => $id]);
        if (($chk->fetchColumn() ?: '') === 'admin') {
            header('Location: /tickets/paneles/admin_usuarios.php');
            exit;
        }
    }
}

$nombres   = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$usuario   = trim($_POST['usuario'] ?? '');
$rol       = $_POST['rol'] ?? 'solicitante';
$correo    = trim($_POST['correo'] ?? '');
$dni       = trim($_POST['dni'] ?? '');
$telefono  = trim($_POST['telefono'] ?? '');
$id_area   = ($_POST['id_area'] ?? '') !== '' ? (int)$_POST['id_area'] : null;
$password  = $_POST['password'] ?? '';

// Normalizar vacíos a NULL
$correo   = $correo   !== '' ? $correo   : null;
$dni      = $dni      !== '' ? $dni      : null;
$telefono = $telefono !== '' ? $telefono : null;

// Validaciones
$roles_ok = ['solicitante','tecnico','admin'];
if ($nombres === '' || $apellidos === '' || $usuario === '' || !in_array($rol, $roles_ok, true)) {
    $destino = $editando ? "?id=$id&error=faltan" : "?error=faltan";
    header("Location: /tickets/paneles/admin_usuario_form.php$destino");
    exit;
}
if (!$editando && $password === '') {
    header("Location: /tickets/paneles/admin_usuario_form.php?error=faltan");
    exit;
}

try {
    // Verificar usuario duplicado (excluyendo el propio si edita)
    $q = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE usuario = :u AND id_usuario <> :id");
    $q->execute([':u' => $usuario, ':id' => $id]);
    if ($q->fetch()) {
        $destino = $editando ? "?id=$id&error=usuario_repetido" : "?error=usuario_repetido";
        header("Location: /tickets/paneles/admin_usuario_form.php$destino");
        exit;
    }

    // Verificar correo duplicado (si se dio)
    if ($correo !== null) {
        $q = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = :c AND id_usuario <> :id");
        $q->execute([':c' => $correo, ':id' => $id]);
        if ($q->fetch()) {
            $destino = $editando ? "?id=$id&error=correo_repetido" : "?error=correo_repetido";
            header("Location: /tickets/paneles/admin_usuario_form.php$destino");
            exit;
        }
    }

    if ($editando) {
        // Editar. La contraseña solo se actualiza si se escribió una nueva.
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "UPDATE usuarios SET nombres=:n, apellidos=:a, usuario=:u, rol=:r,
                        correo=:co, dni=:d, telefono=:t, id_area=:ar, password=:p
                    WHERE id_usuario=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':n'=>$nombres, ':a'=>$apellidos, ':u'=>$usuario, ':r'=>$rol,
                ':co'=>$correo, ':d'=>$dni, ':t'=>$telefono, ':ar'=>$id_area,
                ':p'=>$hash, ':id'=>$id
            ]);
        } else {
            $sql = "UPDATE usuarios SET nombres=:n, apellidos=:a, usuario=:u, rol=:r,
                        correo=:co, dni=:d, telefono=:t, id_area=:ar
                    WHERE id_usuario=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':n'=>$nombres, ':a'=>$apellidos, ':u'=>$usuario, ':r'=>$rol,
                ':co'=>$correo, ':d'=>$dni, ':t'=>$telefono, ':ar'=>$id_area, ':id'=>$id
            ]);
        }
        header('Location: /tickets/paneles/admin_usuarios.php?msg=editado');
        exit;

    } else {
        // Crear nuevo
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO usuarios (nombres, apellidos, usuario, password, rol, correo, dni, telefono, id_area, activo)
                VALUES (:n, :a, :u, :p, :r, :co, :d, :t, :ar, TRUE)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':n'=>$nombres, ':a'=>$apellidos, ':u'=>$usuario, ':p'=>$hash, ':r'=>$rol,
            ':co'=>$correo, ':d'=>$dni, ':t'=>$telefono, ':ar'=>$id_area
        ]);
        header('Location: /tickets/paneles/admin_usuarios.php?msg=creado');
        exit;
    }

} catch (PDOException $e) {
    $destino = $editando ? "?id=$id&error=guardar" : "?error=guardar";
    header("Location: /tickets/paneles/admin_usuario_form.php$destino");
    exit;
}