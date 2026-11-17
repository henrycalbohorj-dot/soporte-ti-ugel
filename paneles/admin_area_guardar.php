<?php
/**
 * GUARDAR ÁREA (crear o editar) - solo admin
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/paneles/admin_areas.php');
    exit;
}

$id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$editando  = $id > 0;
$nombre    = trim($_POST['nombre'] ?? '');
$id_jefe   = ($_POST['id_jefe'] ?? '') !== '' ? (int)$_POST['id_jefe'] : null;
$ubicacion = trim($_POST['ubicacion'] ?? '');
$detalle   = trim($_POST['detalle'] ?? '');

if ($nombre === '') {
    $destino = $editando ? "?id=$id&error=faltan" : "?error=faltan";
    header("Location: /tickets/paneles/admin_area_form.php$destino");
    exit;
}

try {
    // Verificar nombre duplicado (excluyendo la propia si edita)
    $q = $pdo->prepare("SELECT id_area FROM areas WHERE LOWER(nombre) = LOWER(:n) AND id_area <> :id");
    $q->execute([':n' => $nombre, ':id' => $id]);
    if ($q->fetch()) {
        $destino = $editando ? "?id=$id&error=repetida" : "?error=repetida";
        header("Location: /tickets/paneles/admin_area_form.php$destino");
        exit;
    }

    $params = [
        ':n' => $nombre, ':jefe' => $id_jefe,
        ':ubic' => $ubicacion ?: null, ':det' => $detalle ?: null,
    ];

    if ($editando) {
        $params[':id'] = $id;
        $stmt = $pdo->prepare("UPDATE areas SET nombre = :n, id_jefe = :jefe, ubicacion = :ubic, detalle = :det WHERE id_area = :id");
        $stmt->execute($params);
        header('Location: /tickets/paneles/admin_areas.php?msg=editada');
        exit;
    } else {
        $stmt = $pdo->prepare("INSERT INTO areas (nombre, id_jefe, ubicacion, detalle, activo) VALUES (:n, :jefe, :ubic, :det, TRUE)");
        $stmt->execute($params);
        header('Location: /tickets/paneles/admin_areas.php?msg=creada');
        exit;
    }

} catch (PDOException $e) {
    $destino = $editando ? "?id=$id&error=guardar" : "?error=guardar";
    header("Location: /tickets/paneles/admin_area_form.php$destino");
    exit;
}
