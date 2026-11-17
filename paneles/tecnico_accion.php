<?php
/**
 * ACCIONES DEL TÉCNICO sobre un ticket: asignar, completar, cancelar, rechazar.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['tecnico', 'admin']);

$rol = $_SESSION['rol'] ?? '';
$destino_error = '/tickets/paneles/tecnico_historial.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $destino_error"); exit;
}

$id_yo  = $_SESSION['id_usuario'];
$codigo = trim($_POST['codigo'] ?? '');
$accion = $_POST['accion'] ?? '';

// Traer el ticket
$stmt = $pdo->prepare("SELECT id_ticket, estado, id_tecnico FROM tickets WHERE codigo = :c LIMIT 1");
$stmt->execute([':c' => $codigo]);
$t = $stmt->fetch();
if (!$t) { header("Location: $destino_error"); exit; }
$id_ticket = (int)$t['id_ticket'];

$volver = "/tickets/paneles/tecnico_detalle.php?codigo=" . urlencode($codigo);

/**
 * Guarda (de forma opcional) las fotos de evidencia adjuntadas por el técnico
 * al gestionar el ticket. Valida tipo real de archivo (no solo por extensión),
 * tamaño y cantidad, y descarta silenciosamente cualquier archivo inválido
 * sin interrumpir el resto de la acción (asignar/completar/cancelar/rechazar).
 */
function guardar_evidencias(PDO $pdo, int $id_ticket, int $id_usuario): void {
    if (empty($_FILES['evidencias']) || empty($_FILES['evidencias']['name'][0])) return;

    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $max_bytes  = 4 * 1024 * 1024; // 4 MB por foto
    $max_fotos  = 6;

    $nombres = $_FILES['evidencias']['name'];
    $tmp     = $_FILES['evidencias']['tmp_name'];
    $errores = $_FILES['evidencias']['error'];
    $tamanos = $_FILES['evidencias']['size'];
    $n       = min(count($nombres), $max_fotos);

    $dir_base = __DIR__ . '/../assets/uploads/evidencias/' . $id_ticket;

    for ($i = 0; $i < $n; $i++) {
        if (($errores[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        if (empty($tmp[$i]) || !is_uploaded_file($tmp[$i])) continue;
        if (($tamanos[$i] ?? 0) <= 0 || $tamanos[$i] > $max_bytes) continue;

        // Verifica que sea realmente una imagen válida (no solo por su extensión o el
        // Content-Type que declara el navegador, que se puede falsificar fácilmente).
        if (@getimagesize($tmp[$i]) === false) continue;
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp[$i]);
        if (!isset($permitidos[$mime])) continue;

        if (!is_dir($dir_base) && !@mkdir($dir_base, 0755, true) && !is_dir($dir_base)) continue;

        $nombre_archivo = 'ev_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
        if (!move_uploaded_file($tmp[$i], $dir_base . '/' . $nombre_archivo)) continue;

        $ruta_relativa = '/tickets/assets/uploads/evidencias/' . $id_ticket . '/' . $nombre_archivo;
        $ins = $pdo->prepare(
            "INSERT INTO ticket_evidencias (id_ticket, id_usuario, ruta_archivo) VALUES (:t, :u, :r)");
        $ins->execute([':t' => $id_ticket, ':u' => $id_usuario, ':r' => $ruta_relativa]);
    }
}

try {
    if ($accion === 'asignar') {
        $id_tecnico = (int)($_POST['id_tecnico'] ?? 0);
        if ($id_tecnico <= 0) { header("Location: $volver"); exit; }

        // Asignar y pasar a "en_atencion"
        $up = $pdo->prepare(
            "UPDATE tickets
             SET id_tecnico = :tec, estado = 'en_atencion',
                 fecha_asignacion = COALESCE(fecha_asignacion, CURRENT_TIMESTAMP)
             WHERE id_ticket = :id");
        $up->execute([':tec' => $id_tecnico, ':id' => $id_ticket]);

        // Seguimiento
        $nota = ($id_tecnico === (int)$id_yo)
            ? 'El técnico se asignó el ticket y comenzó la atención.'
            : 'Ticket asignado a otro técnico.';
        $seg = $pdo->prepare(
            "INSERT INTO ticket_seguimiento (id_ticket, id_usuario, comentario, estado_nuevo)
             VALUES (:t, :u, :c, 'en_atencion')");
        $seg->execute([':t'=>$id_ticket, ':u'=>$id_yo, ':c'=>$nota]);

        $msg = ($id_tecnico === (int)$id_yo) ? 'asignado' : 'reasignado';
        header("Location: $volver&msg=$msg"); exit;
    }

    // Rechazar: lo puede hacer quien aún no lo tomó (nadie asignado), el técnico ya asignado, o el admin
    if ($accion === 'rechazar') {
        $puede = $rol === 'admin' || empty($t['id_tecnico']) || ((int)$t['id_tecnico'] === (int)$id_yo);
        if (!$puede) { header("Location: $volver&error=no_asignado"); exit; }

        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') { header("Location: $volver&error=motivo_obligatorio"); exit; }

        // Se guarda id_tecnico aunque el ticket no haya pasado por "asignar",
        // para que el rechazo quede atribuido a quien lo rechazó (reportes).
        $up = $pdo->prepare(
            "UPDATE tickets SET estado='rechazado', solucion=:s, id_tecnico=:tec, fecha_cierre=CURRENT_TIMESTAMP
             WHERE id_ticket=:id");
        $up->execute([':s'=>$motivo, ':tec'=>$id_yo, ':id'=>$id_ticket]);
        $seg = $pdo->prepare(
            "INSERT INTO ticket_seguimiento (id_ticket,id_usuario,comentario,estado_nuevo)
             VALUES (:t,:u,:c,'rechazado')");
        $seg->execute([':t'=>$id_ticket, ':u'=>$id_yo, ':c'=>'Ticket rechazado. Motivo: '.$motivo]);
        header("Location: $volver&msg=rechazado"); exit;
    }

    // Completar: el técnico asignado, o el admin (puede cerrar cualquiera).
    // El técnico NO puede cancelar el ticket: si no corresponde atenderlo, lo Rechaza
    // (arriba). Cancelar es una decisión exclusiva del solicitante (usuario_accion.php),
    // ya que implica que él mismo resolvió el problema antes de que el técnico llegue.
    if ($accion === 'completar') {
        if ($rol !== 'admin' && (int)$t['id_tecnico'] !== (int)$id_yo) {
            header("Location: $volver&error=no_asignado"); exit;
        }

        // Evidencia fotográfica (opcional)
        guardar_evidencias($pdo, $id_ticket, $id_yo);

        $solucion      = trim($_POST['solucion'] ?? '');
        $diagnostico   = trim($_POST['diagnostico'] ?? '');
        $recomendacion = trim($_POST['recomendacion'] ?? '');

        // Situación final obligatoria
        if ($solucion === '') { header("Location: $volver&error=sol_obligatoria"); exit; }
        $up = $pdo->prepare(
            "UPDATE tickets SET estado='completado', solucion=:s, diagnostico=:d, recomendacion=:r,
                    fecha_resolucion=CURRENT_TIMESTAMP, fecha_cierre=CURRENT_TIMESTAMP
             WHERE id_ticket=:id");
        $up->execute([':s'=>$solucion, ':d'=>($diagnostico!==''?$diagnostico:null), ':r'=>($recomendacion!==''?$recomendacion:null), ':id'=>$id_ticket]);
        $seg = $pdo->prepare(
            "INSERT INTO ticket_seguimiento (id_ticket,id_usuario,comentario,estado_nuevo)
             VALUES (:t,:u,:c,'completado')");
        $seg->execute([':t'=>$id_ticket, ':u'=>$id_yo, ':c'=>'Ticket completado. Situación final: '.$solucion]);
        header("Location: $volver&msg=completado"); exit;
    }

    header("Location: $volver"); exit;

} catch (PDOException $e) {
    header("Location: $volver"); exit;
}