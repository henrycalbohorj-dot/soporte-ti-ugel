<?php
/**
 * ACCIONES SOBRE USUARIOS (activar/desactivar, borrar) - solo admin
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);
$soy_tecnico = ($_SESSION['rol'] ?? '') === 'tecnico';

$accion = $_GET['accion'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// No permitir que el admin se afecte a sí mismo
if ($id === (int)$_SESSION['id_usuario'] || $id <= 0) {
    header('Location: /tickets/paneles/admin_usuarios.php');
    exit;
}

// Un técnico no puede activar/desactivar/borrar cuentas de administrador.
if ($soy_tecnico) {
    $chk = $pdo->prepare("SELECT rol FROM usuarios WHERE id_usuario = :id");
    $chk->execute([':id' => $id]);
    if (($chk->fetchColumn() ?: '') === 'admin') {
        header('Location: /tickets/paneles/admin_usuarios.php');
        exit;
    }
}

try {
    if ($accion === 'estado') {
        // Alternar activo/inactivo
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = NOT activo WHERE id_usuario = :id");
        $stmt->execute([':id' => $id]);
        header('Location: /tickets/paneles/admin_usuarios.php?msg=estado');
        exit;

    } elseif ($accion === 'borrar') {
        // Verificar si el usuario tiene tickets asociados
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM tickets
             WHERE id_solicitante = :id OR id_tecnico = :id");
        $chk->execute([':id' => $id]);
        $tiene = (int)$chk->fetchColumn();

        if ($tiene > 0) {
            // Tiene tickets: no borrar (para no romper el histórico). Desactivar en su lugar.
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = FALSE WHERE id_usuario = :id");
            $stmt->execute([':id' => $id]);
            header('Location: /tickets/paneles/admin_usuarios.php?msg=desactivado');
            exit;
        }

        // Sin tickets: se puede borrar de verdad
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
        $stmt->execute([':id' => $id]);
        header('Location: /tickets/paneles/admin_usuarios.php?msg=borrado');
        exit;
    }

    header('Location: /tickets/paneles/admin_usuarios.php');
    exit;

} catch (PDOException $e) {
    header('Location: /tickets/paneles/admin_usuarios.php');
    exit;
}