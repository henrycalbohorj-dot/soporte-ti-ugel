<?php
/**
 * GUARDAR un registro de mantenimiento para un equipo.
 * Lo registran los técnicos (el día a día) y también el administrador,
 * para poder cargar el historial de mantenimientos del sistema anterior
 * (con fecha pasada y eligiendo a qué técnico se le atribuye).
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['tecnico', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tickets/paneles/admin_equipos.php');
    exit;
}

$rol       = $_SESSION['rol'] ?? '';
$id_yo     = (int)$_SESSION['id_usuario'];
$id_equipo = (int)($_POST['id_equipo'] ?? 0);
$tipo      = $_POST['tipo'] ?? '';
$estado    = $_POST['estado'] ?? '';
$fecha     = $_POST['fecha_mantenimiento'] ?? '';
$desc      = trim($_POST['descripcion'] ?? '');

// El técnico siempre queda como él mismo. El admin, al cargar histórico,
// elige a qué técnico se le atribuye el mantenimiento.
if ($rol === 'admin') {
    $id_tecnico = (int)($_POST['id_tecnico'] ?? 0);
    if ($id_tecnico <= 0) $id_tecnico = null;
} else {
    $id_tecnico = $id_yo;
}

// Partes intervenidas: checkboxes (del catálogo) + un campo "otro" libre.
// Si se escribe una parte nueva en "otro", se agrega al catálogo para que
// quede disponible como checkbox en los próximos mantenimientos.
$partesSeleccionadas = array_filter(array_map('trim', $_POST['partes'] ?? []), fn($v) => $v !== '');
$parteOtro = trim($_POST['parte_otro'] ?? '');

$tiposValidos   = ['preventivo', 'correctivo'];
$estadosValidos = ['operativo', 'mantenimiento', 'malogrado'];

if ($id_equipo <= 0 || !in_array($tipo, $tiposValidos, true) || !in_array($estado, $estadosValidos, true) || $fecha === '') {
    header("Location: /tickets/paneles/admin_equipo_detalle.php?id=$id_equipo&error=faltan");
    exit;
}

/**
 * Guarda (de forma opcional) las fotos o PDFs de evidencia adjuntados al
 * registrar el mantenimiento. Valida el tipo real del archivo (no solo la
 * extensión), tamaño y cantidad, descartando en silencio lo inválido.
 */
function guardar_evidencias_mantenimiento(PDO $pdo, int $id_mantenimiento, int $id_usuario): void {
    if (empty($_FILES['evidencias_mantenimiento']) || empty($_FILES['evidencias_mantenimiento']['name'][0])) return;

    $permitidosImg = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $max_bytes = 8 * 1024 * 1024; // 8 MB por archivo
    $max_archivos = 8;

    $nombres = $_FILES['evidencias_mantenimiento']['name'];
    $tmp     = $_FILES['evidencias_mantenimiento']['tmp_name'];
    $errores = $_FILES['evidencias_mantenimiento']['error'];
    $tamanos = $_FILES['evidencias_mantenimiento']['size'];
    $n       = min(count($nombres), $max_archivos);

    $dir_base = __DIR__ . '/../assets/uploads/mantenimientos/' . $id_mantenimiento;

    for ($i = 0; $i < $n; $i++) {
        if (($errores[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        if (empty($tmp[$i]) || !is_uploaded_file($tmp[$i])) continue;
        if (($tamanos[$i] ?? 0) <= 0 || $tamanos[$i] > $max_bytes) continue;

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp[$i]);
        $extension = null;
        $tipoArchivo = null;

        if (isset($permitidosImg[$mime]) && @getimagesize($tmp[$i]) !== false) {
            $extension = $permitidosImg[$mime];
            $tipoArchivo = 'imagen';
        } elseif ($mime === 'application/pdf') {
            // Verificación mínima de autenticidad: todo PDF real empieza con "%PDF-".
            $cabecera = @file_get_contents($tmp[$i], false, null, 0, 5);
            if ($cabecera !== '%PDF-') continue;
            $extension = 'pdf';
            $tipoArchivo = 'pdf';
        } else {
            continue; // tipo de archivo no permitido
        }

        if (!is_dir($dir_base) && !@mkdir($dir_base, 0755, true) && !is_dir($dir_base)) continue;

        $nombre_archivo = 'mt_' . bin2hex(random_bytes(8)) . '.' . $extension;
        if (!move_uploaded_file($tmp[$i], $dir_base . '/' . $nombre_archivo)) continue;

        $ruta_relativa = '/tickets/assets/uploads/mantenimientos/' . $id_mantenimiento . '/' . $nombre_archivo;
        $ins = $pdo->prepare(
            "INSERT INTO equipo_mantenimiento_evidencias (id_mantenimiento, id_usuario, ruta_archivo, tipo_archivo)
             VALUES (:m, :u, :r, :t)");
        $ins->execute([':m' => $id_mantenimiento, ':u' => $id_usuario, ':r' => $ruta_relativa, ':t' => $tipoArchivo]);
    }
}

try {
    $pdo->beginTransaction();

    // Si escribieron una parte nueva en "otro", se agrega al catálogo
    // compartido (si ya existe una con ese nombre, no se duplica).
    if ($parteOtro !== '') {
        $ins = $pdo->prepare(
            "INSERT INTO partes_catalogo (nombre, creado_por) VALUES (:n, :u)
             ON CONFLICT (nombre) DO NOTHING");
        $ins->execute([':n' => $parteOtro, ':u' => $id_yo]);
        $partesSeleccionadas[] = $parteOtro;
    }
    $partes = $partesSeleccionadas ? implode(', ', $partesSeleccionadas) : null;

    $stmt = $pdo->prepare("INSERT INTO equipo_mantenimientos
        (id_equipo, tipo, estado, fecha_mantenimiento, descripcion, partes, id_tecnico)
        VALUES (:eq, :tipo, :estado, :fecha, :desc, :partes, :tec)
        RETURNING id_mantenimiento");
    $stmt->execute([
        ':eq' => $id_equipo, ':tipo' => $tipo, ':estado' => $estado,
        ':fecha' => $fecha, ':desc' => $desc ?: null, ':partes' => $partes, ':tec' => $id_tecnico,
    ]);
    $id_mantenimiento = $stmt->fetchColumn();

    guardar_evidencias_mantenimiento($pdo, (int)$id_mantenimiento, $id_yo);

    $pdo->commit();

    // Se pasa directo al editor de la ficha: ahí se completan los datos
    // que este formulario rápido no pide (componentes, asunto, código, etc.)
    header("Location: /tickets/paneles/equipo_ficha_editar.php?id={$id_mantenimiento}&nueva=1");
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header("Location: /tickets/paneles/admin_equipo_detalle.php?id=$id_equipo&error=faltan");
    exit;
}
