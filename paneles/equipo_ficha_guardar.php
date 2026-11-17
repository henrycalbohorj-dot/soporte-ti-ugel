<?php
/**
 * GUARDAR la ficha de mantenimiento: actualiza el registro de mantenimiento
 * (técnico, tipo, fecha, partes, conclusión, condición final), guarda los
 * datos propios de la ficha (código, asunto, red/BIOS, firmas) y reemplaza
 * la evaluación por componente con las filas recibidas.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin', 'tecnico']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/paneles/admin_equipos.php');
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$es_admin = $rol === 'admin';

$id = (int)($_POST['id_mantenimiento'] ?? 0);
if ($id <= 0) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

$chk = $pdo->prepare("SELECT id_equipo, id_tecnico FROM equipo_mantenimientos WHERE id_mantenimiento = :id");
$chk->execute([':id' => $id]);
$actual = $chk->fetch();
if (!$actual) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

// Un técnico (no admin) solo puede editar la ficha de su propio mantenimiento.
if (!$es_admin && (int)$actual['id_tecnico'] !== (int)$_SESSION['id_usuario']) {
    header('Location: /tickets/paneles/admin_equipo_detalle.php?id=' . (int)$actual['id_equipo']);
    exit;
}

$id_equipo = (int)$actual['id_equipo'];
$volver = "/tickets/paneles/equipo_ficha_editar.php?id=$id";

$tipo   = $_POST['tipo'] ?? 'correctivo';
$estado = $_POST['estado'] ?? 'operativo';
$fecha  = $_POST['fecha_mantenimiento'] ?? '';
$desc   = trim($_POST['descripcion'] ?? '');

$tiposValidos   = ['preventivo', 'correctivo'];
$estadosValidos = ['operativo', 'mantenimiento', 'malogrado'];
if (!in_array($tipo, $tiposValidos, true)) $tipo = 'correctivo';
if (!in_array($estado, $estadosValidos, true)) $estado = 'operativo';
if ($fecha === '' || !strtotime($fecha)) $fecha = date('Y-m-d');

$id_tecnico = $actual['id_tecnico'];
if ($es_admin) {
    $nuevoTecnico = (int)($_POST['id_tecnico'] ?? 0);
    if ($nuevoTecnico > 0) $id_tecnico = $nuevoTecnico;
}

$partesSeleccionadas = array_filter(array_map('trim', $_POST['partes'] ?? []), fn($v) => $v !== '');
$parteOtro = trim($_POST['parte_otro'] ?? '');

$codigo_ficha  = trim($_POST['codigo_ficha'] ?? '') ?: null;
$asunto        = trim($_POST['asunto'] ?? '') ?: null;
$eq_trabajo    = trim($_POST['eq_trabajo'] ?? '') ?: null;
$subequipo     = trim($_POST['subequipo'] ?? '') ?: null;
$fecha_bios    = trim($_POST['fecha_bios'] ?? '');
$fecha_bios    = ($fecha_bios !== '' && strtotime($fecha_bios)) ? $fecha_bios : null;
$conf_resp     = trim($_POST['conformidad_responsable'] ?? '') ?: null;
$conf_tec      = trim($_POST['conformidad_tecnico'] ?? '') ?: null;

// Datos del equipo (Kardex): si el técnico los completa o corrige aquí,
// se actualizan también en "equipos" — así quedan disponibles para los
// próximos mantenimientos sin tener que volver a escribirlos. Si deja un
// campo en blanco, se conserva lo que ya tenía el equipo (no se borra).
$eq_hostname = trim($_POST['eq_hostname'] ?? '');
$eq_marca    = trim($_POST['eq_marca'] ?? '');
$eq_modelo   = trim($_POST['eq_modelo'] ?? '');
$eq_serie    = trim($_POST['eq_serie'] ?? '');
$eq_so       = trim($_POST['eq_so'] ?? '');
$eq_mac_lan  = trim($_POST['eq_mac_lan'] ?? '');
$eq_mac_wifi = trim($_POST['eq_mac_wifi'] ?? '');

$comp_categoria    = $_POST['comp_categoria'] ?? [];
$comp_nombre       = $_POST['comp_nombre'] ?? [];
$comp_estado       = $_POST['comp_estado'] ?? [];
$comp_descripcion  = $_POST['comp_descripcion'] ?? [];
$categoriasValidas = ['procesador', 'ram', 'almacenamiento', 'grafico', 'so', 'red', 'otro'];
$estadosCompValidos = ['bueno', 'regular', 'danado'];

try {
    $pdo->beginTransaction();

    if ($parteOtro !== '') {
        $insParte = $pdo->prepare(
            "INSERT INTO partes_catalogo (nombre, creado_por) VALUES (:n, :u) ON CONFLICT (nombre) DO NOTHING");
        $insParte->execute([':n' => $parteOtro, ':u' => (int)$_SESSION['id_usuario']]);
        $partesSeleccionadas[] = $parteOtro;
    }
    $partes = $partesSeleccionadas ? implode(', ', $partesSeleccionadas) : null;

    $up = $pdo->prepare(
        "UPDATE equipo_mantenimientos
         SET tipo=:tipo, estado=:estado, fecha_mantenimiento=:fecha, descripcion=:desc, partes=:partes, id_tecnico=:tec
         WHERE id_mantenimiento=:id");
    $up->execute([
        ':tipo' => $tipo, ':estado' => $estado, ':fecha' => $fecha, ':desc' => $desc ?: null,
        ':partes' => $partes, ':tec' => $id_tecnico ?: null, ':id' => $id,
    ]);

    // Completa/corrige los datos del Kardex del equipo con lo que el técnico
    // escribió aquí, sin borrar un dato bueno que ya tenía si este campo
    // llega vacío (NULLIF + COALESCE: vacío -> se queda con el valor actual).
    $upEquipo = $pdo->prepare(
        "UPDATE equipos SET
            nombre = COALESCE(NULLIF(:nombre, ''), nombre),
            marca = COALESCE(NULLIF(:marca, ''), marca),
            modelo = COALESCE(NULLIF(:modelo, ''), modelo),
            numero_serie = COALESCE(NULLIF(:serie, ''), numero_serie),
            sistema_operativo = COALESCE(NULLIF(:so, ''), sistema_operativo),
            mac_lan = COALESCE(NULLIF(:mac_lan, ''), mac_lan),
            mac_wifi = COALESCE(NULLIF(:mac_wifi, ''), mac_wifi)
         WHERE id_equipo = :id_equipo");
    $upEquipo->execute([
        ':nombre' => $eq_hostname, ':marca' => $eq_marca, ':modelo' => $eq_modelo,
        ':serie' => $eq_serie, ':so' => $eq_so, ':mac_lan' => $eq_mac_lan, ':mac_wifi' => $eq_mac_wifi,
        ':id_equipo' => $id_equipo,
    ]);

    $upFicha = $pdo->prepare(
        "INSERT INTO equipo_mantenimiento_ficha
            (id_mantenimiento, codigo_ficha, asunto, eq_trabajo, subequipo, fecha_bios, conformidad_responsable, conformidad_tecnico, actualizado_en)
         VALUES (:id, :cod, :asu, :eqt, :sub, :bios, :cr, :ct, now())
         ON CONFLICT (id_mantenimiento) DO UPDATE SET
            codigo_ficha=:cod, asunto=:asu, eq_trabajo=:eqt, subequipo=:sub, fecha_bios=:bios,
            conformidad_responsable=:cr, conformidad_tecnico=:ct, actualizado_en=now()");
    $upFicha->execute([
        ':id' => $id, ':cod' => $codigo_ficha, ':asu' => $asunto, ':eqt' => $eq_trabajo, ':sub' => $subequipo,
        ':bios' => $fecha_bios, ':cr' => $conf_resp, ':ct' => $conf_tec,
    ]);

    // Reemplaza por completo la evaluación por componente con lo recibido
    // (es más simple y seguro que intentar "diffear" filas existentes).
    $delComp = $pdo->prepare("DELETE FROM equipo_mantenimiento_componentes WHERE id_mantenimiento = :id");
    $delComp->execute([':id' => $id]);

    $insComp = $pdo->prepare(
        "INSERT INTO equipo_mantenimiento_componentes (id_mantenimiento, categoria, componente, estado, descripcion, orden)
         VALUES (:id, :cat, :comp, :est, :desc, :orden)");
    $orden = 0;
    $n = max(count($comp_nombre), count($comp_categoria));
    for ($i = 0; $i < $n; $i++) {
        $nombre = trim($comp_nombre[$i] ?? '');
        if ($nombre === '') continue; // fila vacía (ej. se agregó y no se llenó), se ignora
        $cat = $comp_categoria[$i] ?? 'otro';
        if (!in_array($cat, $categoriasValidas, true)) $cat = 'otro';
        $est = $comp_estado[$i] ?? 'bueno';
        if (!in_array($est, $estadosCompValidos, true)) $est = 'bueno';
        $descComp = trim($comp_descripcion[$i] ?? '');
        $insComp->execute([
            ':id' => $id, ':cat' => $cat, ':comp' => $nombre, ':est' => $est,
            ':desc' => $descComp ?: null, ':orden' => $orden++,
        ]);
    }

    $pdo->commit();
    header("Location: $volver&msg=guardado");
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header("Location: $volver&error=1");
    exit;
}
