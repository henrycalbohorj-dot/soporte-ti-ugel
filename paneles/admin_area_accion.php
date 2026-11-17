<?php
/**
 * ACCIONES SOBRE ÁREAS (activar/desactivar, borrar) - solo admin
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);

$accion = $_GET['accion'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /tickets/paneles/admin_areas.php'); exit; }

try {
    if ($accion === 'estado') {
        $stmt = $pdo->prepare("UPDATE areas SET activo = NOT activo WHERE id_area = :id");
        $stmt->execute([':id' => $id]);
        header('Location: /tickets/paneles/admin_areas.php?msg=estado');
        exit;

    } elseif ($accion === 'borrar') {
        // Verificar si el área está en uso (tickets o usuarios)
        $chk = $pdo->prepare(
            "SELECT (SELECT COUNT(*) FROM tickets WHERE id_area = :id1) +
                    (SELECT COUNT(*) FROM usuarios WHERE id_area = :id2) AS n");
        $chk->execute([':id1' => $id, ':id2' => $id]);
        $tiene = (int)$chk->fetchColumn();

        if ($tiene > 0) {
            // En uso: no borrar (para no romper el histórico). Desactivar en su lugar.
            $stmt = $pdo->prepare("UPDATE areas SET activo = FALSE WHERE id_area = :id");
            $stmt->execute([':id' => $id]);
            header('Location: /tickets/paneles/admin_areas.php?msg=desactivada');
            exit;
        }

        // Sin uso: se puede borrar de verdad
        $stmt = $pdo->prepare("DELETE FROM areas WHERE id_area = :id");
        $stmt->execute([':id' => $id]);
        header('Location: /tickets/paneles/admin_areas.php?msg=borrada');
        exit;
    }

    header('Location: /tickets/paneles/admin_areas.php');
    exit;

} catch (PDOException $e) {
    header('Location: /tickets/paneles/admin_areas.php');
    exit;
}
