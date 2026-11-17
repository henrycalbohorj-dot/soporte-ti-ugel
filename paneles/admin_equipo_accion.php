<?php
/**
 * ACCIONES SOBRE EQUIPOS (activar/desactivar, borrar) - solo admin
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);

$accion = $_GET['accion'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

try {
    if ($accion === 'estado') {
        $stmt = $pdo->prepare("UPDATE equipos SET activo = NOT activo WHERE id_equipo = :id");
        $stmt->execute([':id' => $id]);
        header('Location: /tickets/paneles/admin_equipos.php?msg=estado');
        exit;

    } elseif ($accion === 'borrar') {
        // Si el equipo tiene mantenimientos registrados, no se borra (se conserva el historial)
        $chk = $pdo->prepare("SELECT COUNT(*) FROM equipo_mantenimientos WHERE id_equipo = :id");
        $chk->execute([':id' => $id]);
        $tiene = (int)$chk->fetchColumn();

        if ($tiene > 0) {
            $stmt = $pdo->prepare("UPDATE equipos SET activo = FALSE WHERE id_equipo = :id");
            $stmt->execute([':id' => $id]);
            header('Location: /tickets/paneles/admin_equipos.php?msg=desactivado');
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM equipos WHERE id_equipo = :id");
        $stmt->execute([':id' => $id]);
        header('Location: /tickets/paneles/admin_equipos.php?msg=borrado');
        exit;
    }

    header('Location: /tickets/paneles/admin_equipos.php');
    exit;

} catch (PDOException $e) {
    header('Location: /tickets/paneles/admin_equipos.php');
    exit;
}
