<?php
/**
 * FICHA de mantenimiento — versión imprimible / exportable a PDF.
 * Replica el formato de la ficha oficial en papel (datos de usuario, datos
 * del sistema, red, evaluación por componente, conclusión, condición final
 * y firmas de conformidad), con el logo de la entidad.
 */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['admin', 'tecnico']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

$sql = "SELECT m.*, e.nombre AS eq_nombre, e.tipo AS eq_tipo, e.marca AS eq_marca, e.modelo AS eq_modelo,
               e.numero_serie, e.id_equipo, e.sistema_operativo, e.mac_lan, e.mac_wifi,
               a.nombre AS area, resp.nombres AS resp_n, resp.apellidos AS resp_a,
               t.nombres AS tec_n, t.apellidos AS tec_a
        FROM equipo_mantenimientos m
        JOIN equipos e ON e.id_equipo = m.id_equipo
        LEFT JOIN areas a ON a.id_area = e.id_area
        LEFT JOIN usuarios resp ON resp.id_usuario = e.id_responsable
        LEFT JOIN usuarios t ON t.id_usuario = m.id_tecnico
        WHERE m.id_mantenimiento = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$mant = $stmt->fetch();
if (!$mant) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }

$fichaStmt = $pdo->prepare("SELECT * FROM equipo_mantenimiento_ficha WHERE id_mantenimiento = :id");
$fichaStmt->execute([':id' => $id]);
$ficha = $fichaStmt->fetch() ?: [];

$compStmt = $pdo->prepare("SELECT * FROM equipo_mantenimiento_componentes WHERE id_mantenimiento = :id ORDER BY orden, id_componente");
$compStmt->execute([':id' => $id]);
$componentes = $compStmt->fetchAll();

$evStmt = $pdo->prepare("SELECT ruta_archivo, tipo_archivo FROM equipo_mantenimiento_evidencias WHERE id_mantenimiento = :id ORDER BY fecha_subida ASC");
$evStmt->execute([':id' => $id]);
$evidencias = $evStmt->fetchAll();
$fotos = array_values(array_filter($evidencias, fn($e) => $e['tipo_archivo'] !== 'pdf'));
$pdfs  = array_values(array_filter($evidencias, fn($e) => $e['tipo_archivo'] === 'pdf'));

$responsable = $mant['resp_n'] ? trim($mant['resp_n'].' '.$mant['resp_a']) : null;
$tecnico     = $mant['tec_n'] ? trim($mant['tec_n'].' '.$mant['tec_a']) : 'Sin registrar';

$estadoCompLabel = ['bueno' => 'Bueno', 'regular' => 'Regular', 'danado' => 'Dañado'];

$titulo_pagina  = 'Ficha de mantenimiento';
$seccion_activa = 'equipos';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado no-imprimir">
    <div>
        <h2><?= icono('printer', 22) ?> Ficha de mantenimiento</h2>
        <p><?= e($mant['eq_nombre']) ?> · <?= e(fecha_legible_corta($mant['fecha_mantenimiento'])) ?></p>
    </div>
    <div class="acciones-fila">
        <button type="button" class="btn" onclick="window.print()"><?= icono('printer') ?> Imprimir / Guardar PDF</button>
        <a href="/tickets/paneles/equipo_ficha_editar.php?id=<?= (int)$id ?>" class="btn btn-sec"><?= icono('clipboard', 16) ?> Editar ficha</a>
        <a href="/tickets/paneles/admin_equipo_detalle.php?id=<?= (int)$mant['id_equipo'] ?>" class="btn btn-sec"><?= icono('arrow-left') ?> Volver al equipo</a>
    </div>
</div>

<?php
/**
 * Dibuja el contenido completo de la ficha (todas las secciones). Se usa
 * una vez para la vista normal en pantalla, y dos veces más —reducida—
 * para la hoja de impresión con las 2 copias (recepción / cargo).
 */
function pintar_contenido_ficha(array $mant, array $ficha, array $componentes, array $fotos, array $pdfs, ?string $responsable, string $tecnico, array $estadoCompLabel): void {
    ?>
    <div class="ficha-cab">
        <img src="/tickets/assets/img/logo_ugel.png" alt="UGEL Chincha" class="ficha-logo">
        <div class="ficha-cab-titulo">
            <h1>FORMULARIO DE MANTENIMIENTO DE EQUIPO</h1>
            <p>Sistema de Soporte T.I. — UGEL Chincha</p>
        </div>
        <div class="ficha-cab-meta">
            <?php if (!empty($ficha['codigo_ficha'])): ?><div class="ficha-codigo"><?= e($ficha['codigo_ficha']) ?></div><?php endif; ?>
            <div class="ficha-fecha"><?= e(fecha_legible_corta($mant['fecha_mantenimiento'])) ?></div>
        </div>
    </div>

    <div class="ficha-seccion">
        <div class="ficha-seccion-titulo">Datos del usuario</div>
        <div class="ficha-grid ficha-grid-2">
            <div class="ficha-dato"><span class="ficha-lbl">Responsable</span><span class="ficha-val"><?= $responsable ? e($responsable) : '—' ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Área</span><span class="ficha-val"><?= e($mant['area'] ?? '—') ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Equipo de trabajo</span><span class="ficha-val"><?= e($ficha['eq_trabajo'] ?? $mant['area'] ?? '—') ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Subequipo</span><span class="ficha-val"><?= e($ficha['subequipo'] ?? $mant['area'] ?? '—') ?></span></div>
        </div>
    </div>

    <div class="ficha-seccion">
        <div class="ficha-seccion-titulo">Datos del sistema</div>
        <div class="ficha-grid ficha-grid-3">
            <div class="ficha-dato"><span class="ficha-lbl">Equipo / Hostname</span><span class="ficha-val"><?= e($mant['eq_nombre']) ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Marca / Modelo</span><span class="ficha-val"><?= e(trim($mant['eq_marca'].' '.($mant['eq_modelo'] ?? ''))) ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">N.° de serie</span><span class="ficha-val"><?= e($mant['numero_serie'] ?: '—') ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Sistema operativo</span><span class="ficha-val"><?= e($mant['sistema_operativo'] ?: '—') ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Fecha BIOS</span><span class="ficha-val"><?= !empty($ficha['fecha_bios']) ? e(fecha_legible_corta($ficha['fecha_bios'])) : '—' ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Tipo de equipo</span><span class="ficha-val"><?= e($mant['eq_tipo']) ?></span></div>
        </div>
    </div>

    <div class="ficha-seccion">
        <div class="ficha-seccion-titulo">Red y conectividad</div>
        <div class="ficha-grid ficha-grid-2">
            <div class="ficha-dato"><span class="ficha-lbl">MAC LAN</span><span class="ficha-val"><?= e($mant['mac_lan'] ?: '—') ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">MAC WiFi</span><span class="ficha-val"><?= e($mant['mac_wifi'] ?: '—') ?></span></div>
        </div>
    </div>

    <?php if ($componentes): ?>
    <div class="ficha-seccion">
        <div class="ficha-seccion-titulo">Evaluación por componente</div>
        <table class="ficha-tabla">
            <thead><tr><th>Componente</th><th>Estado</th><th>Descripción</th></tr></thead>
            <tbody>
            <?php foreach ($componentes as $c): ?>
                <tr>
                    <td><?= e($c['componente']) ?></td>
                    <td><span class="ficha-estado-chip ficha-estado-<?= e($c['estado']) ?>"><?= e($estadoCompLabel[$c['estado']] ?? ucfirst($c['estado'])) ?></span></td>
                    <td><?= nl2br(e($c['descripcion'] ?: '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="ficha-seccion">
        <div class="ficha-seccion-titulo">Mantenimiento <?= e(strtoupper(tipo_mantenimiento_legible($mant['tipo']))) ?></div>
        <div class="ficha-grid ficha-grid-2">
            <div class="ficha-dato"><span class="ficha-lbl">Realizado por</span><span class="ficha-val"><?= e($tecnico) ?></span></div>
            <div class="ficha-dato"><span class="ficha-lbl">Código de ficha</span><span class="ficha-val"><?= !empty($ficha['codigo_ficha']) ? e($ficha['codigo_ficha']) : '—' ?></span></div>
        </div>
        <?php if (!empty($ficha['asunto'])): ?>
            <div class="ficha-dato ficha-dato-full"><span class="ficha-lbl">Asunto</span><span class="ficha-val"><?= nl2br(e($ficha['asunto'])) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($mant['partes'])): ?>
            <div class="ficha-dato ficha-dato-full"><span class="ficha-lbl">Partes intervenidas</span><span class="ficha-val"><?= e($mant['partes']) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="ficha-seccion">
        <div class="ficha-seccion-titulo">Conclusión / Solución</div>
        <div class="ficha-texto-largo"><?= !empty($mant['descripcion']) ? nl2br(e($mant['descripcion'])) : '<span class="ficha-vacio">Sin observaciones adicionales.</span>' ?></div>
    </div>

    <?php if ($fotos): ?>
    <div class="ficha-seccion">
        <div class="ficha-seccion-titulo">Evidencia fotográfica</div>
        <div class="galeria-evidencia">
            <?php foreach ($fotos as $i => $f): ?>
            <div class="galeria-item"><div class="galeria-foto"><img src="<?= e($f['ruta_archivo']) ?>" alt="Evidencia <?= $i + 1 ?>"></div></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($pdfs): ?>
    <div class="ficha-seccion no-imprimir">
        <div class="ficha-seccion-titulo">Documentos PDF adjuntos</div>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($pdfs as $i => $p): ?>
                <a href="<?= e($p['ruta_archivo']) ?>" target="_blank" class="btn btn-sec btn-sm" style="align-self:flex-start;"><?= icono('file-pdf', 15) ?> Documento <?= $i + 1 ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="ficha-firmas">
        <div class="ficha-firma-caja">
            <div class="ficha-firma-linea"></div>
            <div class="ficha-firma-nombre"><?= !empty($ficha['conformidad_responsable']) ? e($ficha['conformidad_responsable']) : ($responsable ? e($responsable) : '—') ?></div>
            <div class="ficha-firma-cargo">V°B° Responsable</div>
        </div>
        <div class="ficha-firma-caja ficha-firma-condicion">
            <div class="ficha-condicion-final ficha-estado-<?= e($mant['estado']) ?>"><?= e(strtoupper(estado_equipo_legible($mant['estado']))) ?></div>
            <div class="ficha-firma-cargo">Condición final</div>
        </div>
        <div class="ficha-firma-caja">
            <div class="ficha-firma-linea"></div>
            <div class="ficha-firma-nombre"><?= !empty($ficha['conformidad_tecnico']) ? e($ficha['conformidad_tecnico']) : e($tecnico) ?></div>
            <div class="ficha-firma-cargo">Conformidad del técnico</div>
        </div>
    </div>

    <div class="ficha-pie no-imprimir">Generado el <?= date('d/m/Y H:i') ?> por <?= e(nombre_usuario()) ?>.</div>
    <div class="ficha-pie solo-imprimir">Documento generado por el Sistema de Soporte T.I. — <?= date('d/m/Y H:i') ?></div>
    <?php
}
?>

<!-- Vista normal en pantalla (se oculta al imprimir) -->
<div class="ficha-doc ficha-doc-pantalla">
    <?php pintar_contenido_ficha($mant, $ficha, $componentes, $fotos, $pdfs, $responsable, $tecnico, $estadoCompLabel); ?>
</div>

<!-- Hoja de impresión: la misma ficha completa, 2 veces, para cortar en recepción / cargo -->
<div class="ficha-impresion-x2">
    <div class="ficha-copia">
        <div class="ficha-copia-cinta">COPIA — RECEPCIÓN (queda con el área / usuario)</div>
        <div class="ficha-doc ficha-doc-mini">
            <?php pintar_contenido_ficha($mant, $ficha, $componentes, $fotos, $pdfs, $responsable, $tecnico, $estadoCompLabel); ?>
        </div>
    </div>
    <div class="ficha-corte" aria-hidden="true">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>
    <div class="ficha-copia">
        <div class="ficha-copia-cinta">COPIA — CARGO INFORMÁTICA (queda con Soporte T.I.)</div>
        <div class="ficha-doc ficha-doc-mini">
            <?php pintar_contenido_ficha($mant, $ficha, $componentes, $fotos, $pdfs, $responsable, $tecnico, $estadoCompLabel); ?>
        </div>
    </div>
</div>

<script>
/**
 * Ajusta en el momento real de imprimir (no antes) el tamaño de las 2 copias
 * para que SIEMPRE quepan en 1 sola hoja A4, sin importar cuánto texto tenga
 * esta ficha en particular (descripción larga, muchas partes, etc.) ni cómo
 * mida las fuentes el navegador/Windows del usuario — en vez de adivinar un
 * número de escala fijo (que puede fallar con fichas más largas o en otra
 * máquina), se mide el alto real aquí mismo y se calcula la escala exacta
 * que se necesita, con un margen de seguridad.
 */
(function () {
    function ajustarFichaImpresion() {
        var copias = document.querySelectorAll('.ficha-doc-mini');
        var wrap = document.querySelector('.ficha-impresion-x2');
        if (!copias.length || !wrap) return;

        // Alto disponible en una hoja A4 con margen de 8mm arriba/abajo
        // (297mm - 16mm, convertido a píxeles), con un poco de margen extra.
        var presupuesto = 1060;
        var escala = 0.32; // primer intento razonable
        var intentos = 0;

        function aplicar(e) {
            copias.forEach(function (c) { c.style.setProperty('--fe', e.toFixed(4)); });
            void wrap.offsetHeight; // fuerza a recalcular el alto antes de medir
            return wrap.scrollHeight;
        }

        // El texto se reacomoda distinto según cuánto se achica (no es una
        // simple regla de 3), así que en vez de calcular la escala una sola
        // vez, se prueba, se mide el alto real que resultó y se corrige,
        // repitiendo hasta que entre de verdad (o hasta un límite legible).
        var alto = aplicar(escala);
        var intentosMax = 8;
        while (alto > presupuesto * 0.95 && escala > 0.12 && intentos < intentosMax) {
            escala = Math.max(0.12, escala * (presupuesto * 0.93 / alto));
            alto = aplicar(escala);
            intentos++;
        }
    }
    // Se calcula apenas carga la página (el bloque de impresión está
    // posicionado fuera de pantalla, no con display:none, para poder
    // medirlo ya). No conviene esperar al evento "beforeprint": con el
    // flujo real de impresión de Chrome no siempre alcanza a aplicarse
    // a tiempo para la vista previa. Se repite además en "beforeprint"
    // como respaldo, por si cambia algo (zoom, fuentes) entre medio.
    if (document.readyState === 'complete') {
        ajustarFichaImpresion();
    } else {
        window.addEventListener('load', ajustarFichaImpresion);
    }
    window.addEventListener('beforeprint', ajustarFichaImpresion);
})();
</script>

<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
