<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['admin', 'tecnico']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;
$eq = [
    'nombre' => '', 'tipo' => '', 'marca' => '', 'modelo' => '', 'numero_serie' => '',
    'id_area' => '', 'id_responsable' => '', 'procesador' => '',
    'sistema_operativo' => '', 'mac_lan' => '', 'mac_wifi' => '',
    'observaciones' => '',
];
$ramRows = [];
$almRows = [];
$gpuRows = [];

if ($editando) {
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE id_equipo = :id");
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    if (!$fila) { header('Location: /tickets/paneles/admin_equipos.php'); exit; }
    $eq = $fila;

    $r = $pdo->prepare("SELECT * FROM equipo_ram WHERE id_equipo = :id ORDER BY orden, id_ram");
    $r->execute([':id' => $id]);
    $ramRows = $r->fetchAll();

    $a = $pdo->prepare("SELECT * FROM equipo_almacenamiento WHERE id_equipo = :id ORDER BY orden, id_almacenamiento");
    $a->execute([':id' => $id]);
    $almRows = $a->fetchAll();

    $g = $pdo->prepare("SELECT * FROM equipo_gpu WHERE id_equipo = :id ORDER BY orden, id_gpu");
    $g->execute([':id' => $id]);
    $gpuRows = $g->fetchAll();
}

// Si no hay ninguna fila (equipo nuevo, o editando uno sin componentes aún), arrancar con una vacía
if (!$ramRows) $ramRows = [['capacidad' => '', 'tipo' => '', 'frecuencia' => '']];
if (!$almRows) $almRows = [['tipo' => '', 'capacidad' => '', 'interfaz' => '']];
if (!$gpuRows) $gpuRows = [];

$areas = $pdo->query("SELECT id_area, nombre, id_jefe FROM areas WHERE activo = TRUE ORDER BY nombre")->fetchAll();
$usuarios = $pdo->query("SELECT id_usuario, nombres, apellidos FROM usuarios WHERE activo = TRUE ORDER BY nombres, apellidos")->fetchAll();

$error = $_GET['error'] ?? '';

$titulo_pagina  = $editando ? 'Editar equipo' : 'Nuevo equipo';
$seccion_activa = 'equipos';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado">
    <div>
        <h2><?= $editando ? 'Editar equipo' : 'Nuevo equipo' ?></h2>
        <p><?= $editando ? 'Modifica los datos del equipo.' : 'Registra un nuevo equipo en el kardex.' ?></p>
    </div>
    <a href="/tickets/paneles/admin_equipos.php" class="btn btn-sec"><?= icono('arrow-left') ?> Volver</a>
</div>

<?php if ($error==='faltan'): ?><div class="aviso aviso-error">Completa los campos obligatorios (nombre, tipo y marca).</div>
<?php elseif ($error==='serie_repetida'): ?><div class="aviso aviso-error">Ya existe un equipo con ese número de serie.</div>
<?php elseif ($error==='guardar'): ?><div class="aviso aviso-error">No se pudo guardar. Revisa los datos.</div>
<?php endif; ?>

<form action="/tickets/paneles/admin_equipo_guardar.php" method="POST" id="form-equipo">
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

    <div class="panel">
        <div class="panel-titulo">Datos generales</div>
        <div class="form-grid">
            <div class="campo">
                <label>Nombre / Hostname *</label>
                <input type="text" name="nombre" maxlength="100" value="<?= e($eq['nombre']) ?>" required autofocus>
            </div>
            <div class="campo">
                <label>Tipo *</label>
                <input type="text" name="tipo" maxlength="50" value="<?= e($eq['tipo']) ?>" placeholder="Laptop, Desktop, Impresora..." required>
            </div>
            <div class="campo">
                <label>Marca *</label>
                <input type="text" name="marca" maxlength="80" value="<?= e($eq['marca']) ?>" required>
            </div>
            <div class="campo">
                <label>Modelo</label>
                <input type="text" name="modelo" maxlength="80" value="<?= e($eq['modelo'] ?? '') ?>">
            </div>
            <div class="campo">
                <label>Número de serie</label>
                <input type="text" name="numero_serie" maxlength="100" value="<?= e($eq['numero_serie'] ?? '') ?>">
            </div>
            <div class="campo">
                <label>Área</label>
                <select name="id_area" id="sel-area-equipo">
                    <option value="">Sin asignar</option>
                    <?php foreach ($areas as $a2): ?>
                        <option value="<?= (int)$a2['id_area'] ?>" data-jefe="<?= (int)($a2['id_jefe'] ?? 0) ?>" <?= (string)$eq['id_area']===(string)$a2['id_area']?'selected':'' ?>><?= e($a2['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label>Responsable</label>
                <select name="id_responsable" id="sel-resp-equipo">
                    <option value="">Sin asignar</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id_usuario'] ?>" <?= (string)$eq['id_responsable']===(string)$u['id_usuario']?'selected':'' ?>><?= e($u['nombres'].' '.$u['apellidos']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label>Procesador</label>
                <input type="text" name="procesador" maxlength="120" value="<?= e($eq['procesador'] ?? '') ?>" placeholder="Intel i5-10400">
            </div>
            <div class="campo">
                <label>Sistema operativo</label>
                <input type="text" name="sistema_operativo" maxlength="80" value="<?= e($eq['sistema_operativo'] ?? '') ?>" placeholder="Windows 10 Pro">
            </div>
            <div class="campo">
                <label>MAC LAN</label>
                <input type="text" name="mac_lan" maxlength="40" value="<?= e($eq['mac_lan'] ?? '') ?>">
            </div>
            <div class="campo">
                <label>MAC WiFi</label>
                <input type="text" name="mac_wifi" maxlength="40" value="<?= e($eq['mac_wifi'] ?? '') ?>">
            </div>
            <div class="campo full">
                <label>Observaciones</label>
                <textarea name="observaciones"><?= e($eq['observaciones'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-titulo">Memoria RAM</div>
        <p style="color:var(--texto-sec); font-size:.85rem; margin-top:-10px; margin-bottom:14px;">
            Un equipo puede tener más de una memoria física instalada — agrega una fila por cada una.
        </p>
        <div id="grupo-ram" class="grupo-componentes"></div>
        <button type="button" class="btn btn-sec btn-sm" onclick="añadirComponente('ram')"><?= icono('plus') ?> Añadir memoria RAM</button>
    </div>

    <div class="panel">
        <div class="panel-titulo">Almacenamiento</div>
        <p style="color:var(--texto-sec); font-size:.85rem; margin-top:-10px; margin-bottom:14px;">
            Agrega una fila por cada disco/unidad (por ejemplo, un SSD y un HDD en el mismo equipo).
        </p>
        <div id="grupo-almacenamiento" class="grupo-componentes"></div>
        <button type="button" class="btn btn-sec btn-sm" onclick="añadirComponente('almacenamiento')"><?= icono('plus') ?> Añadir almacenamiento</button>
    </div>

    <div class="panel">
        <div class="panel-titulo">Tarjeta gráfica (GPU)</div>
        <p style="color:var(--texto-sec); font-size:.85rem; margin-top:-10px; margin-bottom:14px;">
            Opcional — solo si el equipo tiene GPU dedicada o quieres registrar la integrada.
        </p>
        <div id="grupo-gpu" class="grupo-componentes"></div>
        <button type="button" class="btn btn-sec btn-sm" onclick="añadirComponente('gpu')"><?= icono('plus') ?> Añadir GPU</button>
    </div>

    <div class="panel">
        <button type="submit" class="btn"><?= $editando ? 'Guardar cambios' : 'Crear equipo' ?></button>
    </div>
</form>

<!-- Plantillas de fila (ocultas, se clonan por JS) -->
<template id="plantilla-ram">
    <div class="fila-componente">
        <div class="campo"><label>Capacidad</label><input type="text" name="ram_capacidad[]" placeholder="8GB"></div>
        <div class="campo"><label>Tipo</label><input type="text" name="ram_tipo[]" placeholder="DDR4"></div>
        <div class="campo"><label>Frecuencia</label><input type="text" name="ram_frecuencia[]" placeholder="3200MHz"></div>
        <button type="button" class="btn btn-peligro btn-sm btn-quitar" onclick="this.closest('.fila-componente').remove()"><?= icono('x', 14) ?></button>
    </div>
</template>
<template id="plantilla-almacenamiento">
    <div class="fila-componente">
        <div class="campo"><label>Tipo</label><input type="text" name="alm_tipo[]" placeholder="SSD"></div>
        <div class="campo"><label>Capacidad</label><input type="text" name="alm_capacidad[]" placeholder="256GB"></div>
        <div class="campo"><label>Interfaz</label><input type="text" name="alm_interfaz[]" placeholder="NVMe"></div>
        <button type="button" class="btn btn-peligro btn-sm btn-quitar" onclick="this.closest('.fila-componente').remove()"><?= icono('x', 14) ?></button>
    </div>
</template>
<template id="plantilla-gpu">
    <div class="fila-componente">
        <div class="campo"><label>Marca</label><input type="text" name="gpu_marca[]" placeholder="NVIDIA"></div>
        <div class="campo"><label>Modelo</label><input type="text" name="gpu_modelo[]" placeholder="GTX 1650"></div>
        <div class="campo"><label>Memoria</label><input type="text" name="gpu_capacidad[]" placeholder="4GB"></div>
        <div class="campo"><label>Tipo</label><input type="text" name="gpu_tipo[]" placeholder="Dedicada / Integrada"></div>
        <button type="button" class="btn btn-peligro btn-sm btn-quitar" onclick="this.closest('.fila-componente').remove()"><?= icono('x', 14) ?></button>
    </div>
</template>

<style>
    .grupo-componentes { display:flex; flex-direction:column; gap:12px; margin-bottom:14px; }
    .fila-componente { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)) 40px; gap:12px; align-items:end; background:var(--bg); border:1px solid var(--borde); border-radius:9px; padding:12px; }
    .fila-componente .campo label { font-size:.72rem; }
    .btn-quitar { height:42px; align-self:end; }
    @media (max-width:700px){ .fila-componente { grid-template-columns:1fr 1fr; } .btn-quitar { grid-column: 1 / -1; width:100%; } }
</style>

<script>
var datosExistentes = {
    ram: <?= json_encode(array_map(fn($r) => ['capacidad'=>$r['capacidad'],'tipo'=>$r['tipo'],'frecuencia'=>$r['frecuencia']], $ramRows), JSON_UNESCAPED_UNICODE) ?>,
    almacenamiento: <?= json_encode(array_map(fn($r) => ['tipo'=>$r['tipo'],'capacidad'=>$r['capacidad'],'interfaz'=>$r['interfaz']], $almRows), JSON_UNESCAPED_UNICODE) ?>,
    gpu: <?= json_encode(array_map(fn($r) => ['marca'=>$r['marca'],'modelo'=>$r['modelo'],'capacidad'=>$r['capacidad'],'tipo'=>$r['tipo']], $gpuRows), JSON_UNESCAPED_UNICODE) ?>
};

function añadirComponente(grupo, valores){
    var plantilla = document.getElementById('plantilla-' + grupo);
    var contenedor = document.getElementById('grupo-' + grupo);
    var nodo = plantilla.content.cloneNode(true);
    if (valores) {
        nodo.querySelectorAll('input').forEach(function(input){
            var campo = input.name.replace(/^[a-z]+_/, '').replace('[]', '');
            if (valores[campo] !== undefined) input.value = valores[campo];
        });
    }
    contenedor.appendChild(nodo);
}

document.addEventListener('DOMContentLoaded', function(){
    ['ram', 'almacenamiento', 'gpu'].forEach(function(grupo){
        datosExistentes[grupo].forEach(function(valores){ añadirComponente(grupo, valores); });
    });

    // ---- Al elegir el Área, autocompletar el Responsable con el jefe de esa área ----
    // (el técnico puede cambiarlo después si el responsable real del equipo es otra persona)
    var selArea = document.getElementById('sel-area-equipo');
    var selResp = document.getElementById('sel-resp-equipo');
    if (selArea && selResp) {
        selArea.addEventListener('change', function(){
            var opt = selArea.options[selArea.selectedIndex];
            var jefe = opt ? opt.getAttribute('data-jefe') : '';
            if (jefe && jefe !== '0' && selResp.querySelector('option[value="' + jefe + '"]')) {
                selResp.value = jefe;
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../config/layout_bottom.php'; ?>
