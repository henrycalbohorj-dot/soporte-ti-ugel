<?php
/**
 * GUARDAR TICKET NUEVO (solicitante)
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['solicitante']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/paneles/usuario.php');
    exit;
}

// Campo obligatorio: solo el motivo (título). La descripción es opcional.
$titulo      = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

if ($titulo === '') {
    header('Location: /tickets/paneles/usuario_crear.php?error=faltan');
    exit;
}

// Campos opcionales (pueden venir vacíos -> NULL)
$id_categoria = ($_POST['id_categoria'] ?? '') !== '' ? (int)$_POST['id_categoria'] : null;
$id_prioridad = ($_POST['id_prioridad'] ?? '') !== '' ? (int)$_POST['id_prioridad'] : null;
$id_ubicacion = ($_POST['id_ubicacion'] ?? '') !== '' ? (int)$_POST['id_ubicacion'] : null;

$id_solicitante = $_SESSION['id_usuario'];
$descripcion    = $descripcion !== '' ? $descripcion : null;

// El área del ticket SIEMPRE es la del solicitante (no se confía en lo que
// mande el formulario) — se toma fresca de la base, no de la sesión.
$stmtArea = $pdo->prepare("SELECT id_area FROM usuarios WHERE id_usuario = :id");
$stmtArea->execute([':id' => $id_solicitante]);
$id_area = $stmtArea->fetchColumn() ?: null;

try {
    $sql = "INSERT INTO tickets
                (titulo, descripcion, id_solicitante, id_categoria, id_prioridad, id_area, id_ubicacion, estado)
            VALUES
                (:titulo, :descripcion, :solicitante, :categoria, :prioridad, :area, :ubicacion, 'pendiente')
            RETURNING codigo, id_ticket";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':titulo'      => $titulo,
        ':descripcion' => $descripcion,
        ':solicitante' => $id_solicitante,
        ':categoria'   => $id_categoria,
        ':prioridad'   => $id_prioridad,
        ':area'        => $id_area,
        ':ubicacion'   => $id_ubicacion,
    ]);
    $fila = $stmt->fetch();

    // Registrar el primer seguimiento (creación)
    if ($fila && isset($fila['id_ticket'])) {
        $seg = $pdo->prepare(
            "INSERT INTO ticket_seguimiento (id_ticket, id_usuario, comentario, estado_nuevo)
             VALUES (:t, :u, :c, 'pendiente')");
        $seg->execute([
            ':t' => $fila['id_ticket'],
            ':u' => $id_solicitante,
            ':c' => 'Ticket creado por el solicitante.',
        ]);
    }

    header('Location: /tickets/paneles/usuario.php?creado=1');
    exit;

} catch (PDOException $e) {
    header('Location: /tickets/paneles/usuario_crear.php?error=guardar');
    exit;
}