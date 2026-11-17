<?php
/**
 * PROCESADOR DE AUTENTICACIÓN
 * Recibe usuario + contraseña, valida contra la base y crea la sesión.
 */
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/config/conexion.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/index.php');
    exit;
}

$usuario  = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

// Validar que no vengan vacíos
if ($usuario === '' || $password === '') {
    header('Location: /tickets/index.php?error=vacio');
    exit;
}

try {
    // Buscar el usuario por su nombre de usuario
    $sql = "SELECT id_usuario, nombres, apellidos, usuario, password, rol, activo
            FROM usuarios
            WHERE usuario = :usuario
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario' => $usuario]);
    $u = $stmt->fetch();

    // ¿Existe y la contraseña coincide?
    if (!$u || !password_verify($password, $u['password'])) {
        header('Location: /tickets/index.php?error=credenciales');
        exit;
    }

    // ¿Está activo?
    if (!$u['activo']) {
        header('Location: /tickets/index.php?error=inactivo');
        exit;
    }

    // ---- Login correcto: guardar datos en la sesión ----
    session_regenerate_id(true); // seguridad: nuevo id de sesión
    $_SESSION['id_usuario'] = $u['id_usuario'];
    $_SESSION['nombres']    = $u['nombres'];
    $_SESSION['apellidos']  = $u['apellidos'];
    $_SESSION['usuario']    = $u['usuario'];
    $_SESSION['rol']        = $u['rol'];

    // Redirigir a su panel según el rol
    redirigir_a_su_panel();
    exit;

} catch (PDOException $e) {
    die('Error al autenticar: ' . $e->getMessage());
}