<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
exigir_rol(['tecnico']);
$id_yo=$_SESSION['id_usuario'];

// Conteos globales (vista de solo lectura, sin tabla ni acciones)
$c=$pdo->query("SELECT estado,COUNT(*) n FROM tickets GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$np=(int)($c['pendiente']??0); $na=(int)($c['en_atencion']??0); $nc=(int)($c['completado']??0); $nx=(int)($c['cancelado']??0);

// Estadística personal: tickets asignados a este técnico (cualquier estado)
$stmtMios = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE id_tecnico = :yo");
$stmtMios->execute([':yo' => $id_yo]);
$mis_asignados = (int)$stmtMios->fetchColumn();

$titulo_pagina='Inicio'; $seccion_activa='inicio';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="bienvenida-tec">
    <div class="bienvenida-tec-icono"><?= icono('wrench', 26) ?></div>
    <div class="bienvenida-tec-txt">
        <h1>Hola, <?= e(nombre_usuario()) ?></h1>
        <p>Este es un resumen general de todo el sistema. Para atender tickets, entra a <a href="/tickets/paneles/tecnico_historial.php">Historial</a>.</p>
    </div>
    <div class="bienvenida-tec-stat">
        <span class="valor" id="mis-asignados"><?= $mis_asignados ?></span>
        <span class="label">Asignados a ti</span>
    </div>
</div>
<div class="encabezado">
    <div><h2>Resumen de tickets</h2><p>Vista general de toda la entidad. Solo lectura.</p></div>
</div>
<div class="kpis">
    <div class="kpi"><span class="barra" style="background:#2563eb;"></span><div class="valor" id="kpi-pendiente"><?= $np ?></div><div class="label">Pendientes</div></div>
    <div class="kpi"><span class="barra" style="background:#d97706;"></span><div class="valor" id="kpi-en_atencion"><?= $na ?></div><div class="label">En atención</div></div>
    <div class="kpi"><span class="barra" style="background:#16a34a;"></span><div class="valor" id="kpi-completado"><?= $nc ?></div><div class="label">Completados</div></div>
    <div class="kpi"><span class="barra" style="background:#dc2626;"></span><div class="valor" id="kpi-cancelado"><?= $nx ?></div><div class="label">Cancelados</div></div>
</div>

<script>
// ---- Auto-refresco de los contadores (vista de solo lectura) ----
(function(){
    function refrescar(){
        fetch('/tickets/paneles/tecnico_bandeja_api.php?estado=todos', {cache:'no-store'})
        .then(r=>r.json()).then(function(d){
            if(!d.ok) return;
            ['pendiente','en_atencion','completado','cancelado'].forEach(function(k){
                var el=document.getElementById('kpi-'+k); if(el) el.textContent=d.conteos[k];
            });
        }).catch(function(err){ console.error('Error al refrescar resumen:', err); });
    }
    refrescar();
    setInterval(refrescar, 5000);
})();
</script>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
