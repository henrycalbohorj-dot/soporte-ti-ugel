<?php
/**
 * ============================================================
 *  FUNCIONES DE UTILIDAD COMPARTIDAS
 * ============================================================
 */

/**
 * Escapa texto para mostrarlo en HTML de forma segura.
 */
function e(?string $texto): string {
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * ============================================================
 *  ÍCONOS SVG (línea, minimalistas) — reemplazan los emojis
 *  para un look corporativo/consistente en toda la app.
 *  Uso: <?= icono('home') ?>  o  <?= icono('plus', 16) ?>
 * ============================================================
 */
function icono(string $nombre, int $tam = 18): string {
    $trazos = [
        'home'         => '<path d="M4 11L12 4L20 11"/><path d="M6 9.5V20H18V9.5"/><path d="M10 20V14H14V20"/>',
        'clipboard'    => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="3.5" rx="1"/><line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="16" y2="15"/>',
        'chart'        => '<line x1="3" y1="21" x2="21" y2="21"/><rect x="5" y="13" width="3.5" height="8"/><rect x="10.25" y="8" width="3.5" height="13"/><rect x="15.5" y="4" width="3.5" height="17"/>',
        'folder'       => '<path d="M3 6H9L11 8.2H21V18H3V6Z"/>',
        'users'        => '<circle cx="9" cy="8.5" r="3"/><path d="M3.5 19.5C4 16 6.3 14 9 14C11.7 14 14 16 14.5 19.5"/><circle cx="17" cy="9" r="2.3"/><path d="M15.3 14.2C17.9 14.5 19.7 16.4 20.3 19.2"/>',
        'tag'          => '<path d="M13 3H20V10L11 19L4 12L13 3Z"/><circle cx="9" cy="8" r="1.4"/>',
        'laptop'       => '<rect x="3.5" y="4" width="17" height="12" rx="1.5"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="16" x2="12" y2="20"/>',
        'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'arrow-left'   => '<line x1="19" y1="12" x2="5" y2="12"/><path d="M11 6L5 12L11 18"/>',
        'check'        => '<path d="M4 12.5L9.5 18L20 6"/>',
        'x'            => '<line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/>',
        'bell'         => '<path d="M6.5 9.5C6.5 6.5 9 4 12 4C15 4 17.5 6.5 17.5 9.5C17.5 15 19.5 16.5 19.5 16.5H4.5C4.5 16.5 6.5 15 6.5 9.5Z"/><path d="M10 19.5C10 20.6 10.9 21.5 12 21.5C13.1 21.5 14 20.6 14 19.5"/>',
        'ticket'       => '<path d="M3 8C3 6.9 3.9 6 5 6H19C20.1 6 21 6.9 21 8V10C19.9 10 19 10.9 19 12C19 13.1 19.9 14 21 14V16C21 17.1 20.1 18 19 18H5C3.9 18 3 17.1 3 16V14C4.1 14 5 13.1 5 12C5 10.9 4.1 10 3 10V8Z"/><line x1="14" y1="6.5" x2="14" y2="17.5" stroke-dasharray="2 2"/>',
        'menu'         => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>',
        'alert'        => '<path d="M12 4L21.5 20H2.5L12 4Z"/><line x1="12" y1="10" x2="12" y2="14.5"/><circle cx="12" cy="17.2" r="0.9" fill="currentColor" stroke="none"/>',
        'ban'          => '<circle cx="12" cy="12" r="8.5"/><line x1="6.5" y1="17.5" x2="17.5" y2="6.5"/>',
        'monitor'      => '<rect x="3.5" y="4" width="17" height="12" rx="1.5"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="16" x2="12" y2="20"/>',
        'search'       => '<circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="20.5" y2="20.5"/>',
        'file-excel'   => '<path d="M6 3H14L19 8V21H6V3Z"/><path d="M14 3V8H19"/><line x1="9" y1="12" x2="16" y2="18"/><line x1="16" y1="12" x2="9" y2="18"/>',
        'file-pdf'     => '<path d="M6 3H14L19 8V21H6V3Z"/><path d="M14 3V8H19"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="16" x2="15" y2="16"/>',
        'undo'         => '<path d="M7 7L3 11L7 15"/><path d="M3 11H14C17.3 11 20 13.7 20 17V19"/>',
        'wrench'       => '<path d="M14.5 4.5L19.5 9.5L17 12L12 7L14.5 4.5Z"/><path d="M9.5 11.5L4.5 16.5C3.7 17.3 3.7 18.6 4.5 19.4C5.3 20.2 6.6 20.2 7.4 19.4L12.4 14.4"/>',
        'lock'         => '<rect x="6" y="10.5" width="12" height="9" rx="1.5"/><path d="M8.5 10.5V7.5C8.5 5.6 10 4 12 4C14 4 15.5 5.6 15.5 7.5V10.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="M8 12.5L11 15.5L16.5 9"/>',
        'x-circle'     => '<circle cx="12" cy="12" r="8.5"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/>',
        'camera'       => '<path d="M4 8H7.5L9 5.5H15L16.5 8H20V19H4V8Z"/><circle cx="12" cy="13.2" r="3.4"/>',
        'arrow-right'  => '<line x1="5" y1="12" x2="19" y2="12"/><path d="M13 6L19 12L13 18"/>',
        'printer'      => '<path d="M6 9V3H18V9"/><rect x="4" y="9" width="16" height="8" rx="1.5"/><rect x="6" y="14" width="12" height="7"/>',
    ];
    $svg = $trazos[$nombre] ?? $trazos['tag'];
    return '<svg class="icono-svg" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

/**
 * Devuelve una etiqueta legible para un estado interno.
 */
function estado_legible(string $estado): string {
    $mapa = [
        'pendiente'   => 'Pendiente',
        'en_atencion' => 'En atención',
        'completado'  => 'Completado',
        'cancelado'   => 'Cancelado',
        'rechazado'   => 'Rechazado',
    ];
    return $mapa[$estado] ?? ucfirst($estado);
}

/**
 * Da formato a una fecha de la base (YYYY-MM-DD HH:MM:SS) a algo legible.
 */
function fecha_legible(?string $fecha): string {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    if (!$ts) return e($fecha);
    return date('d/m/Y H:i', $ts);
}

/**
 * Trae los catálogos activos (áreas, categorías, ubicaciones, prioridades)
 * para llenar los <select> del formulario.
 */
function traer_catalogos(PDO $pdo): array {
    $areas = $pdo->query("SELECT id_area, nombre FROM areas WHERE activo = TRUE ORDER BY nombre")->fetchAll();
    $categorias = $pdo->query("SELECT id_categoria, nombre FROM categorias WHERE activo = TRUE ORDER BY nombre")->fetchAll();
    $ubicaciones = $pdo->query("SELECT id_ubicacion, nombre FROM ubicaciones WHERE activo = TRUE ORDER BY nombre")->fetchAll();
    $prioridades = $pdo->query("SELECT id_prioridad, nombre, color FROM prioridades ORDER BY nivel")->fetchAll();
    return compact('areas', 'categorias', 'ubicaciones', 'prioridades');
}

/**
 * Trae la lista de técnicos activos (para asignar tickets).
 */
function traer_tecnicos(PDO $pdo): array {
    $sql = "SELECT id_usuario, nombres, apellidos
            FROM usuarios
            WHERE rol = 'tecnico' AND activo = TRUE
            ORDER BY nombres, apellidos";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Devuelve la clase CSS del badge según el estado.
 */
function clase_badge(string $estado): string {
    return 'badge-' . $estado;
}

/**
 * Da formato "amigable" al código de un ticket para MOSTRARLO al usuario,
 * ej: codigo real "TK-000004" + fecha_creacion 2026-08-13 -> "TK-0004-2026".
 *
 * Importante: esto es solo cosmético. El código real guardado en la base
 * (columna `codigo`) NO cambia, y sigue siendo el que se usa para buscar
 * el ticket, armar enlaces (?codigo=...) y en los formularios de acción.
 * Tampoco afecta el anuncio por voz, que ya extrae el número por su cuenta.
 */
function formatear_codigo(?string $codigo, ?string $fecha_creacion = null): string {
    if (!$codigo) return '—';
    $num = preg_replace('/\D/', '', $codigo);
    $num = $num !== '' ? str_pad($num, 4, '0', STR_PAD_LEFT) : '0000';
    $anio = date('Y');
    if ($fecha_creacion) {
        $ts = strtotime($fecha_creacion);
        if ($ts) $anio = date('Y', $ts);
    }
    return 'TK-' . $num . '-' . $anio;
}

/**
 * Clasifica un valor numérico en un nivel de semáforo: 'bien' | 'regular' | 'critico'.
 * Por defecto, "menos es mejor" (ej: cantidad de pendientes, % cancelados, horas de espera).
 * Con $invertido=true, "más es mejor" (ej: % de tickets completados).
 */
function semaforo_nivel(float $valor, float $limiteBien, float $limiteRegular, bool $invertido = false): string {
    if ($invertido) {
        if ($valor >= $limiteBien) return 'bien';
        if ($valor >= $limiteRegular) return 'regular';
        return 'critico';
    }
    if ($valor <= $limiteBien) return 'bien';
    if ($valor <= $limiteRegular) return 'regular';
    return 'critico';
}

/**
 * Info visual (icono + texto) de un nivel de semáforo, para no depender solo del color.
 */
function semaforo_info(string $nivel): array {
    $mapa = [
        'bien'    => ['color' => '#187a44', 'texto' => 'Bien'],
        'regular' => ['color' => '#b5680a', 'texto' => 'Regular'],
        'critico' => ['color' => '#c62828', 'texto' => 'Crítico'],
    ];
    return $mapa[$nivel] ?? ['color' => '#93a0b2', 'texto' => '—'];
}

/**
 * Etiqueta legible para el estado de un equipo (Kardex de equipos).
 * 'sin_registro' = el equipo nunca tuvo un mantenimiento registrado.
 */
function estado_equipo_legible(string $estado): string {
    $mapa = [
        'operativo'     => 'Operativo',
        'mantenimiento' => 'En mantenimiento',
        'malogrado'     => 'Malogrado',
        'sin_registro'  => 'Sin registro',
    ];
    return $mapa[$estado] ?? ucfirst($estado);
}

/**
 * Etiqueta legible para el tipo de mantenimiento.
 */
function tipo_mantenimiento_legible(string $tipo): string {
    return ['preventivo' => 'Preventivo', 'correctivo' => 'Correctivo'][$tipo] ?? ucfirst($tipo);
}

/**
 * Da formato a una fecha (sin hora) tipo DATE, ej: mantenimientos de equipos.
 */
function fecha_legible_corta(?string $fecha): string {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    if (!$ts) return e($fecha);
    return date('d/m/Y', $ts);
}

/**
 * Semáforo de mantenimiento preventivo: la política es "cada 6 meses".
 * Recibe la fecha (YYYY-MM-DD) del último mantenimiento registrado (o null si
 * el equipo nunca tuvo uno) y devuelve todo lo necesario para pintar el
 * indicador: nivel ('bien'|'regular'|'critico'), color, texto corto, un
 * detalle más largo y en cuántos días vence (negativo = ya venció).
 *
 * bien (verde)    = dentro del plazo, falta más de 30 días para vencer.
 * regular (ámbar) = vence en los próximos 30 días.
 * critico (rojo)  = vencido, o el equipo nunca tuvo un mantenimiento.
 */
function semaforo_mantenimiento(?string $fechaUltimo): array {
    if (!$fechaUltimo) {
        return [
            'nivel' => 'critico', 'color' => semaforo_info('critico')['color'],
            'texto' => 'Sin mantenimiento', 'detalle' => 'Este equipo nunca tuvo un mantenimiento registrado.',
            'dias_restantes' => null, 'vencimiento' => null,
        ];
    }
    $hoy = new DateTime('today');
    $ultimo = new DateTime($fechaUltimo);
    $vencimiento = (clone $ultimo)->modify('+6 months');
    $diasRestantes = (int)$hoy->diff($vencimiento)->format('%r%a');

    if ($diasRestantes < 0) {
        $nivel = 'critico';
        $texto = 'Vencido';
        $detalle = 'El mantenimiento venció hace ' . abs($diasRestantes) . ' día' . (abs($diasRestantes) === 1 ? '' : 's') . '.';
    } elseif ($diasRestantes <= 30) {
        $nivel = 'regular';
        $texto = 'Vence pronto';
        $detalle = 'Vence en ' . $diasRestantes . ' día' . ($diasRestantes === 1 ? '' : 's') . ' (' . fecha_legible_corta($vencimiento->format('Y-m-d')) . ').';
    } else {
        $nivel = 'bien';
        $texto = 'Al día';
        $detalle = 'Vence el ' . fecha_legible_corta($vencimiento->format('Y-m-d')) . '.';
    }

    return [
        'nivel' => $nivel, 'color' => semaforo_info($nivel)['color'],
        'texto' => $texto, 'detalle' => $detalle,
        'dias_restantes' => $diasRestantes, 'vencimiento' => $vencimiento->format('Y-m-d'),
    ];
}

/**
 * Iniciales (1-2 letras) para el avatar de un nombre/etiqueta en un ranking.
 * "Juan Pérez" -> "JP", "Área de Sistemas" -> "ÁS", "Desconocido" -> "DE".
 */
function iniciales_desde_nombre(?string $texto): string {
    $texto = trim((string)$texto);
    if ($texto === '') return '?';
    $palabras = array_values(array_filter(preg_split('/\s+/', $texto)));
    if (count($palabras) === 1) {
        return mb_strtoupper(mb_substr($palabras[0], 0, 2));
    }
    return mb_strtoupper(mb_substr($palabras[0], 0, 1)) . mb_strtoupper(mb_substr($palabras[count($palabras) - 1], 0, 1));
}