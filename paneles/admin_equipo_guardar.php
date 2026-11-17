<?php
/**
 * GUARDAR EQUIPO (crear o editar), junto con sus componentes
 * (RAM / Almacenamiento / GPU, que pueden ser varios por equipo) - solo admin
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/paneles/admin_equipos.php');
    exit;
}

$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$editando = $id > 0;

$nombre = trim($_POST['nombre'] ?? '');
$tipo   = trim($_POST['tipo'] ?? '');
$marca  = trim($_POST['marca'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$serie  = trim($_POST['numero_serie'] ?? '');
$id_area = ($_POST['id_area'] ?? '') !== '' ? (int)$_POST['id_area'] : null;
$id_resp = ($_POST['id_responsable'] ?? '') !== '' ? (int)$_POST['id_responsable'] : null;
$procesador = trim($_POST['procesador'] ?? '');
$so = trim($_POST['sistema_operativo'] ?? '');
$mac_lan = trim($_POST['mac_lan'] ?? '');
$mac_wifi = trim($_POST['mac_wifi'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');

if ($nombre === '' || $tipo === '' || $marca === '') {
    $destino = $editando ? "?id=$id&error=faltan" : "?error=faltan";
    header("Location: /tickets/paneles/admin_equipo_form.php$destino");
    exit;
}

/**
 * Junta varios campos array (ram_capacidad[], ram_tipo[], ...) en una lista
 * de filas, descartando las filas totalmente vacías.
 */
function filasDeComponente(array $post, array $columnas): array {
    $n = 0;
    foreach ($columnas as $campoPost => $col) {
        $n = max($n, count($post[$campoPost] ?? []));
    }
    $filas = [];
    for ($i = 0; $i < $n; $i++) {
        $fila = [];
        $vacia = true;
        foreach ($columnas as $campoPost => $col) {
            $v = trim($post[$campoPost][$i] ?? '');
            if ($v !== '') $vacia = false;
            $fila[$col] = $v !== '' ? $v : null;
        }
        if (!$vacia) $filas[] = $fila;
    }
    return $filas;
}

$filasRam = filasDeComponente($_POST, [
    'ram_capacidad' => 'capacidad', 'ram_tipo' => 'tipo', 'ram_frecuencia' => 'frecuencia',
]);
$filasAlm = filasDeComponente($_POST, [
    'alm_tipo' => 'tipo', 'alm_capacidad' => 'capacidad', 'alm_interfaz' => 'interfaz',
]);
$filasGpu = filasDeComponente($_POST, [
    'gpu_marca' => 'marca', 'gpu_modelo' => 'modelo', 'gpu_capacidad' => 'capacidad', 'gpu_tipo' => 'tipo',
]);

try {
    if ($serie !== '') {
        $q = $pdo->prepare("SELECT id_equipo FROM equipos WHERE numero_serie = :s AND id_equipo <> :id");
        $q->execute([':s' => $serie, ':id' => $id]);
        if ($q->fetch()) {
            $destino = $editando ? "?id=$id&error=serie_repetida" : "?error=serie_repetida";
            header("Location: /tickets/paneles/admin_equipo_form.php$destino");
            exit;
        }
    }

    $pdo->beginTransaction();

    $params = [
        ':nombre' => $nombre, ':tipo' => $tipo, ':marca' => $marca, ':modelo' => $modelo ?: null,
        ':serie' => $serie ?: null, ':area' => $id_area, ':resp' => $id_resp,
        ':proc' => $procesador ?: null, ':so' => $so ?: null,
        ':mlan' => $mac_lan ?: null, ':mwifi' => $mac_wifi ?: null, ':obs' => $observaciones ?: null,
    ];

    if ($editando) {
        $params[':id'] = $id;
        $stmt = $pdo->prepare("UPDATE equipos SET
            nombre=:nombre, tipo=:tipo, marca=:marca, modelo=:modelo, numero_serie=:serie,
            id_area=:area, id_responsable=:resp, procesador=:proc,
            sistema_operativo=:so, mac_lan=:mlan, mac_wifi=:mwifi, observaciones=:obs
            WHERE id_equipo=:id");
        $stmt->execute($params);
        $idEquipo = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO equipos
            (nombre, tipo, marca, modelo, numero_serie, id_area, id_responsable,
             procesador, sistema_operativo, mac_lan, mac_wifi, observaciones, activo)
            VALUES
            (:nombre, :tipo, :marca, :modelo, :serie, :area, :resp,
             :proc, :so, :mlan, :mwifi, :obs, TRUE)
            RETURNING id_equipo");
        $stmt->execute($params);
        $idEquipo = $stmt->fetchColumn();
    }

    // Componentes: se reemplazan por completo (borrar todo lo anterior, insertar lo actual)
    $pdo->prepare("DELETE FROM equipo_ram WHERE id_equipo = :id")->execute([':id' => $idEquipo]);
    $pdo->prepare("DELETE FROM equipo_almacenamiento WHERE id_equipo = :id")->execute([':id' => $idEquipo]);
    $pdo->prepare("DELETE FROM equipo_gpu WHERE id_equipo = :id")->execute([':id' => $idEquipo]);

    $insRam = $pdo->prepare("INSERT INTO equipo_ram (id_equipo, capacidad, tipo, frecuencia, orden) VALUES (:eq,:cap,:tipo,:frec,:orden)");
    foreach ($filasRam as $i => $f) {
        $insRam->execute([':eq' => $idEquipo, ':cap' => $f['capacidad'], ':tipo' => $f['tipo'], ':frec' => $f['frecuencia'], ':orden' => $i]);
    }

    $insAlm = $pdo->prepare("INSERT INTO equipo_almacenamiento (id_equipo, tipo, capacidad, interfaz, orden) VALUES (:eq,:tipo,:cap,:interfaz,:orden)");
    foreach ($filasAlm as $i => $f) {
        $insAlm->execute([':eq' => $idEquipo, ':tipo' => $f['tipo'], ':cap' => $f['capacidad'], ':interfaz' => $f['interfaz'], ':orden' => $i]);
    }

    $insGpu = $pdo->prepare("INSERT INTO equipo_gpu (id_equipo, marca, modelo, capacidad, tipo, orden) VALUES (:eq,:marca,:modelo,:cap,:tipo,:orden)");
    foreach ($filasGpu as $i => $f) {
        $insGpu->execute([':eq' => $idEquipo, ':marca' => $f['marca'], ':modelo' => $f['modelo'], ':cap' => $f['capacidad'], ':tipo' => $f['tipo'], ':orden' => $i]);
    }

    $pdo->commit();
    header('Location: /tickets/paneles/admin_equipos.php?msg=' . ($editando ? 'editado' : 'creado'));
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $destino = $editando ? "?id=$id&error=guardar" : "?error=guardar";
    header("Location: /tickets/paneles/admin_equipo_form.php$destino");
    exit;
}
