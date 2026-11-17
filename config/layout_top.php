<?php
/**
 * LAYOUT COMPARTIDO — sidebar (con grupos) + topbar + tema + notificaciones
 * Uso: definir $titulo_pagina y $seccion_activa, luego require este archivo.
 */
if (!isset($_SESSION)) { session_start(); }
require_once __DIR__ . '/utilidades.php';

$rol = $_SESSION['rol'] ?? '';
$titulo_pagina  = $titulo_pagina  ?? 'Soporte T.I.';
$seccion_activa = $seccion_activa ?? '';
$ini = strtoupper(mb_substr($_SESSION['nombres'] ?? '?', 0, 1) . mb_substr($_SESSION['apellidos'] ?? '', 0, 1));

/*
 * Menús por rol. Estructura: lista de GRUPOS.
 * Cada grupo: ['titulo'=>'...', 'items'=>[ [clave,url,icono,texto], ... ]]
 */
$menus = [
    'solicitante' => [
        ['titulo'=>'Menú', 'items'=>[
            ['tickets', '/tickets/paneles/usuario.php',       'ticket', 'Mis tickets'],
            ['nuevo',   '/tickets/paneles/usuario_crear.php', 'plus',   'Nuevo ticket'],
        ]],
    ],
    'tecnico' => [
        ['titulo'=>'Principal', 'items'=>[
            ['inicio', '/tickets/paneles/tecnico.php', 'home', 'Inicio'],
        ]],
        ['titulo'=>'Módulo de Ticket', 'items'=>[
            ['historial', '/tickets/paneles/tecnico_historial.php', 'clipboard', 'Historial'],
            ['reportes',  '/tickets/paneles/tecnico_reportes.php', 'chart',      'Reportes'],
        ]],
        ['titulo'=>'Catálogos', 'items'=>[
            ['areas',    '/tickets/paneles/admin_areas.php',    'folder', 'Áreas'],
            ['usuarios', '/tickets/paneles/admin_usuarios.php', 'users',  'Usuarios'],
            ['equipos',  '/tickets/paneles/admin_equipos.php', 'laptop', 'Equipos'],
        ]],
    ],
    'admin' => [
        ['titulo'=>'Menú', 'items'=>[
            ['inicio',    '/tickets/paneles/admin.php',            'home',      'Inicio'],
            ['historial', '/tickets/paneles/tecnico_historial.php', 'clipboard', 'Historial'],
            ['reportes',  '/tickets/paneles/tecnico_reportes.php', 'chart',     'Reportes'],
        ]],
        ['titulo'=>'Catálogos', 'items'=>[
            ['areas',    '/tickets/paneles/admin_areas.php',    'folder', 'Áreas'],
            ['usuarios', '/tickets/paneles/admin_usuarios.php', 'users',  'Usuarios'],
            ['equipos',  '/tickets/paneles/admin_equipos.php', 'laptop', 'Equipos'],
        ]],
    ],
];
$grupos = $menus[$rol] ?? [];
$rol_texto = ['admin'=>'Administrador','tecnico'=>'Técnico','solicitante'=>'Solicitante'][$rol] ?? '';
$es_tecnico = ($rol === 'tecnico');
$recibe_alertas_tickets = in_array($rol, ['tecnico', 'admin'], true);
?>
<!DOCTYPE html>
<html lang="es" data-tema="claro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo_pagina) ?> · Soporte T.I.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/tickets/assets/css/estilos.css">
    <script>
        (function(){
            var t = localStorage.getItem('tema') || 'claro';
            document.documentElement.setAttribute('data-tema', t);
            var col = localStorage.getItem('sidebar_colapsado') === '1';
            document.documentElement.setAttribute('data-sidebar', col ? 'colapsado' : 'expandido');
        })();
    </script>
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-marca">
            <div class="icono"><?= icono('wrench', 19) ?></div>
            <div class="txt"><strong>Soporte T.I.</strong><span>Atención de tickets</span></div>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($grupos as $gi => $g):
                $grupo_id = 'grupo-' . $gi;
                $grupo_activo = false;
                foreach ($g['items'] as $m) { if ($seccion_activa === $m[0]) { $grupo_activo = true; break; } }
            ?>
                <div class="grupo">
                    <button type="button" class="grupo-toggle<?= $grupo_activo ? ' abierto' : '' ?>" data-target="<?= $grupo_id ?>" onclick="toggleGrupo(this)">
                        <span class="grupo-titulo"><?= e($g['titulo']) ?></span>
                        <span class="grupo-flecha">▾</span>
                    </button>
                    <div class="grupo-items<?= $grupo_activo ? ' abierto' : '' ?>" id="<?= $grupo_id ?>">
                        <?php foreach ($g['items'] as $m): ?>
                            <a href="<?= $m[1] ?>" class="<?= $seccion_activa === $m[0] ? 'activo' : '' ?>">
                                <span class="ic"><?= icono($m[2], 18) ?></span> <?= e($m[3]) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-pie"><?= e($rol_texto) ?></div>
    </aside>
    <div class="overlay-sidebar" id="overlay" onclick="cerrarMenu()"></div>

    <div class="principal">
        <header class="topbar">
            <div class="izq">
                <button class="btn-menu" onclick="toggleSidebar()" title="Mostrar/ocultar menú"><?= icono('menu', 20) ?></button>
                <h1><?= e($titulo_pagina) ?></h1>
            </div>
            <div class="der">
                <?php if ($recibe_alertas_tickets): ?>
                <!-- Campana de notificaciones -->
                <div class="campana-wrap">
                    <button class="campana-btn" id="campanaBtn" title="Tickets pendientes" onclick="toggleVoz()">
                        <?= icono('bell', 17) ?><span class="campana-num" id="campanaNum" style="display:none;">0</span>
                    </button>
                </div>
                <?php endif; ?>
                <button class="toggle-tema" onclick="cambiarTema()" title="Cambiar tema"></button>
                <div class="usuario-chip">
                    <div class="datos"><strong><?= e(nombre_usuario()) ?></strong><span><?= e($rol_texto) ?></span></div>
                    <div class="avatar"><?= e($ini) ?></div>
                </div>
                <a href="/tickets/salir.php" class="btn-salir">Salir</a>
            </div>
        </header>
        <main class="contenido">