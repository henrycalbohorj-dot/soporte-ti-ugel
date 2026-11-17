<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['admin']);

$total_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$total_users   = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo=TRUE")->fetchColumn();
$pendientes    = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE estado='pendiente'")->fetchColumn();
$en_atencion   = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE estado='en_atencion'")->fetchColumn();

$titulo_pagina  = 'Inicio';
$seccion_activa = 'inicio';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2>PANEL PRINCIPAL</h2>
        <p>Resumen general del sistema de soporte.</p>
    </div>
</div>

<div class="kpis">
    <a href="/tickets/paneles/admin_usuarios.php" class="kpi">
        <span class="barra" style="background:#3b6fd4;"></span>
        <div class="valor"><?= $total_users ?></div>
        <div class="label">Usuarios activos</div>
    </a>
    <div class="kpi">
        <span class="barra" style="background:#64748b;"></span>
        <div class="valor"><?= $total_tickets ?></div>
        <div class="label">Tickets en total</div>
    </div>
    <div class="kpi">
        <span class="barra" style="background:#2563eb;"></span>
        <div class="valor"><?= $pendientes ?></div>
        <div class="label">Pendientes</div>
    </div>
    <div class="kpi">
        <span class="barra" style="background:#d97706;"></span>
        <div class="valor"><?= $en_atencion ?></div>
        <div class="label">En atención</div>
    </div>
</div>

<div class="panel">
    <div class="panel-titulo">Accesos rápidos</div>
    <div class="acciones-fila">
        <a href="/tickets/paneles/admin_usuarios.php" class="btn"><?= icono('users') ?> Gestionar usuarios</a>
    </div>
</div>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>