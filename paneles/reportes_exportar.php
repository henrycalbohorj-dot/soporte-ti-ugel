<?php
/**
 * Exportación de Reportes en Excel (.xlsx real) o PDF, respetando el rango
 * de fechas Desde/Hasta elegido en tecnico_reportes.php.
 * Usa librerías vendorizadas sin Composer: SimpleXLSXGen y FPDF.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['tecnico', 'admin']);

$rol   = $_SESSION['rol'] ?? '';
$id_yo = (int)($_SESSION['id_usuario'] ?? 0);

// ---- Mismo alcance que tecnico_reportes.php: un técnico solo exporta lo suyo ----
if ($rol === 'tecnico') {
    $id_tecnico_filtro = (string)$id_yo;
} else {
    $id_tecnico_filtro = trim($_GET['tecnico'] ?? '');
    if ($id_tecnico_filtro !== '' && !ctype_digit($id_tecnico_filtro)) { $id_tecnico_filtro = ''; }
}
$vista_global = ($id_tecnico_filtro === '');
$tecnico_nombre = null;
if (!$vista_global) {
    if ($rol === 'tecnico') {
        $tecnico_nombre = nombre_usuario();
    } else {
        $stmtTec = $pdo->prepare("SELECT nombres, apellidos FROM usuarios WHERE id_usuario = :id");
        $stmtTec->execute([':id' => (int)$id_tecnico_filtro]);
        $tc = $stmtTec->fetch();
        if ($tc) { $tecnico_nombre = trim($tc['nombres'].' '.$tc['apellidos']); }
    }
}

$formato = $_GET['formato'] ?? 'xlsx';
$hoy = date('Y-m-d');
$hace30 = date('Y-m-d', strtotime('-30 days'));
$desde = $_GET['desde'] ?? $hace30;
$hasta = $_GET['hasta'] ?? $hoy;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = $hace30; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = $hoy; }
$desde_ts = $desde . ' 00:00:00';
$hasta_ts = $hasta . ' 23:59:59';

$params = [':d'=>$desde_ts, ':h'=>$hasta_ts];
$cond_tecnico = '';
if (!$vista_global) { $cond_tecnico = ' AND t.id_tecnico = :tec'; $params[':tec'] = (int)$id_tecnico_filtro; }

$sql = "SELECT t.codigo, t.titulo, t.estado, t.fecha_creacion, t.fecha_asignacion, t.fecha_cierre,
               p.nombre AS prioridad, c.nombre AS categoria, a.nombre AS area,
               sol.nombres AS sol_n, sol.apellidos AS sol_a,
               tec.nombres AS tec_n, tec.apellidos AS tec_a
        FROM tickets t
        LEFT JOIN prioridades p  ON p.id_prioridad = t.id_prioridad
        LEFT JOIN categorias  c  ON c.id_categoria = t.id_categoria
        LEFT JOIN areas       a  ON a.id_area = t.id_area
        LEFT JOIN usuarios    sol ON sol.id_usuario = t.id_solicitante
        LEFT JOIN usuarios    tec ON tec.id_usuario = t.id_tecnico
        WHERE t.fecha_creacion BETWEEN :d AND :h $cond_tecnico
        ORDER BY t.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$nombre_archivo = 'reporte_tickets_' . $desde . '_a_' . $hasta;
if (!$vista_global && $tecnico_nombre) {
    $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($tecnico_nombre));
    $nombre_archivo .= '_' . trim($slug, '_');
}

function horas_atencion(array $t): string {
    if ($t['estado'] !== 'completado' || empty($t['fecha_cierre']) || empty($t['fecha_asignacion'])) return '—';
    $h = (strtotime($t['fecha_cierre']) - strtotime($t['fecha_asignacion'])) / 3600.0;
    return number_format(max($h, 0), 1) . 'h';
}

if ($formato === 'xlsx') {
    require_once __DIR__ . '/../config/vendor/SimpleXLSXGen.php';

    $quien = $vista_global ? 'Toda la entidad' : ('Técnico: ' . $tecnico_nombre);
    $datos = [];
    $datos[] = [$quien . ' · Del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta))];
    $datos[] = [];
    $datos[] = ['Código', 'Título', 'Estado', 'Categoría', 'Área', 'Prioridad', 'Solicitante', 'Técnico', 'Creado', 'Tiempo de atención'];
    foreach ($rows as $t) {
        $datos[] = [
            formatear_codigo($t['codigo'], $t['fecha_creacion']),
            $t['titulo'],
            estado_legible($t['estado']),
            $t['categoria'] ?? '—',
            $t['area'] ?? '—',
            $t['prioridad'] ?? '—',
            trim(($t['sol_n'] ?? '') . ' ' . ($t['sol_a'] ?? '')) ?: '—',
            $t['tec_n'] ? trim($t['tec_n'] . ' ' . $t['tec_a']) : 'Sin asignar',
            fecha_legible($t['fecha_creacion']),
            horas_atencion($t),
        ];
    }

    \Shuchkin\SimpleXLSXGen::fromArray($datos, 'Reporte de tickets')->downloadAs($nombre_archivo . '.xlsx');
    exit;
}

if ($formato === 'pdf') {
    require_once __DIR__ . '/../config/vendor/fpdf.php';

    function tx(?string $s): string {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s ?? '');
    }

    /**
     * Recorta un texto (ya transliterado con tx()) para que quepa dentro del
     * ancho de columna, agregando "..." si no entra. Evita que nombres largos
     * (solicitante, técnico, título) se salgan de su celda y se encimen con
     * la columna siguiente.
     */
    function recortar(FPDF $pdf, string $texto, float $anchoCelda): string {
        $anchoDisponible = $anchoCelda - 2; // margen interno
        if ($pdf->GetStringWidth($texto) <= $anchoDisponible) return $texto;
        $puntos = '...';
        while ($texto !== '' && $pdf->GetStringWidth($texto . $puntos) > $anchoDisponible) {
            $texto = substr($texto, 0, -1);
        }
        return rtrim($texto) . $puntos;
    }

    class ReportePDF extends FPDF {
        public string $rangoTexto = '';
        function Header() {
            $this->SetFont('Helvetica', 'B', 15);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(0, 9, tx('Reporte de tickets - Soporte T.I.'), 0, 1);
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 6, tx($this->rangoTexto), 0, 1);
            $this->Ln(3);
            $this->SetTextColor(0, 0, 0);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Helvetica', 'I', 8);
            $this->SetTextColor(140, 140, 140);
            $this->Cell(0, 10, tx('Página ' . $this->PageNo()), 0, 0, 'C');
        }
    }

    $pdf = new ReportePDF('L', 'mm', 'A4');
    $quien = $vista_global ? 'Toda la entidad' : ('Técnico: ' . $tecnico_nombre);
    $pdf->rangoTexto = $quien . ' · Del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)) . ' · ' . count($rows) . ' tickets';
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $cols = [
        ['t'=>'Código',      'w'=>24],
        ['t'=>'Título',      'w'=>46],
        ['t'=>'Estado',      'w'=>22],
        ['t'=>'Categoría',   'w'=>26],
        ['t'=>'Área',        'w'=>24],
        ['t'=>'Solicitante', 'w'=>36],
        ['t'=>'Técnico',     'w'=>36],
        ['t'=>'Creado',      'w'=>26],
        ['t'=>'Atención',    'w'=>22],
    ];

    function encabezado_tabla(FPDF $pdf, array $cols): void {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetFillColor(37, 99, 235);
        $pdf->SetTextColor(255, 255, 255);
        foreach ($cols as $c) { $pdf->Cell($c['w'], 8, recortar($pdf, tx($c['t']), $c['w']), 0, 0, 'L', true); }
        $pdf->Ln();
    }

    encabezado_tabla($pdf, $cols);

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(30, 30, 30);
    $fill = false;
    foreach ($rows as $t) {
        if ($pdf->GetY() > 190) {
            $pdf->AddPage();
            encabezado_tabla($pdf, $cols);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(30, 30, 30);
        }
        $pdf->SetFillColor(245, 247, 250);
        $vals = [
            formatear_codigo($t['codigo'], $t['fecha_creacion']),
            $t['titulo'],
            estado_legible($t['estado']),
            $t['categoria'] ?? '—',
            $t['area'] ?? '—',
            trim(($t['sol_n'] ?? '') . ' ' . ($t['sol_a'] ?? '')) ?: '—',
            $t['tec_n'] ? trim($t['tec_n'] . ' ' . $t['tec_a']) : 'Sin asignar',
            fecha_legible($t['fecha_creacion']),
            horas_atencion($t),
        ];
        foreach ($vals as $i => $v) {
            $txt = recortar($pdf, tx((string)$v), $cols[$i]['w']);
            $pdf->Cell($cols[$i]['w'], 7, $txt, 0, 0, 'L', $fill);
        }
        $pdf->Ln();
        $fill = !$fill;
    }

    if (empty($rows)) {
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'I', 10);
        $pdf->Cell(0, 8, tx('No hay tickets creados en este rango de fechas.'), 0, 1);
    }

    $pdf->Output('D', $nombre_archivo . '.pdf');
    exit;
}

http_response_code(400);
echo 'Formato no válido.';
