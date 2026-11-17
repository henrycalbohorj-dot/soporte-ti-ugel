<?php
/**
 * ACCIONES DEL SOLICITANTE sobre su propio ticket.
 * Por ahora, la única acción disponible es "cancelar": el solicitante puede
 * cancelar su ticket (con motivo obligatorio) mientras esté Pendiente o En
 * atención, por ejemplo si resolvió el problema por su cuenta antes de que
 * el técnico llegue. El técnico NUNCA cancela un ticket, solo lo Rechaza.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['solicitante']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/paneles/usuario.php'); exit;
}

$id_yo  = $_SESSION['id_usuario'];
$codigo = trim($_POST['codigo'] ?? '');
$accion = $_POST['accion'] ?? '';

$stmt = $pdo->prepare("SELECT id_ticket, estado, id_solicitante FROM tickets WHERE codigo = :c LIMIT 1");
$stmt->execute([':c' => $codigo]);
$t = $stmt->fetch();

// Debe existir y pertenecer a este solicitante
if (!$t || (int)$t['id_solicitante'] !== (int)$id_yo) {
    header('Location: /tickets/paneles/usuario.php'); exit;
}

$id_ticket = (int)$t['id_ticket'];
$volver    = '/tickets/paneles/usuario_detalle.php?codigo=' . urlencode($codigo);

try {
    if ($accion === 'cancelar') {
        // Solo se puede cancelar mientras no haya un cierre ya registrado
        if (!in_array($t['estado'], ['pendiente', 'en_atencion'], true)) {
            header("Location: $volver"); exit;
        }

        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') { header("Location: $volver&error=motivo_obligatorio"); exit; }

        $up = $pdo->prepare(
            "UPDATE tickets SET estado='cancelado', fecha_cierre=CURRENT_TIMESTAMP WHERE id_ticket=:id");
        $up->execute([':id' => $id_ticket]);

        $seg = $pdo->prepare(
            "INSERT INTO ticket_seguimiento (id_ticket, id_usuario, comentario, estado_nuevo)
             VALUES (:t, :u, :c, 'cancelado')");
        $seg->execute([':t' => $id_ticket, ':u' => $id_yo, ':c' => 'Ticket cancelado por el solicitante. Motivo: ' . $motivo]);

        header("Location: $volver&msg=cancelado"); exit;
    }

    header("Location: $volver"); exit;

} catch (PDOException $e) {
    header("Location: $volver"); exit;
}
