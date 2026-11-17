<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/utilidades.php';
exigir_rol(['tecnico', 'admin']);
$tecnicos = traer_tecnicos($pdo);

$titulo_pagina='Historial'; $seccion_activa='historial';
require __DIR__ . '/../config/layout_top.php';
?>
<div class="encabezado" id="encabezado-historial">
    <div><h2>Historial de tickets</h2><p>Consulta y gestiona todos los tickets de la entidad.</p></div>
    <button type="button" class="btn btn-sec" id="btn-modo-tv" onclick="toggleModoTV()"><?= icono('monitor') ?> Modo TV</button>
</div>

<!-- Filtros -->
<div class="panel" id="panel-filtros-historial">
    <div class="filtros-grid">
        <div class="campo">
            <label>Estado</label>
            <select id="f-estado">
                <option value="activos" selected>Pendientes y en atención</option>
                <option value="">Todos los estados</option>
                <option value="pendiente">Solo pendiente</option>
                <option value="en_atencion">Solo en atención</option>
                <option value="completado">Completado</option>
                <option value="cancelado">Cancelado</option>
                <option value="rechazado">Rechazado</option>
            </select>
        </div>
        <div class="campo">
            <label>Técnico</label>
            <select id="f-tecnico">
                <option value="">Todos</option>
                <?php foreach ($tecnicos as $t): ?>
                    <option value="<?= (int)$t['id_usuario'] ?>"><?= e($t['nombres'].' '.$t['apellidos']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>N° de ticket</label>
            <input type="text" id="f-numero" placeholder="Ej: 5 o TK-000005">
        </div>
        <div class="campo">
            <label>Desde</label>
            <input type="date" id="f-desde">
        </div>
        <div class="campo">
            <label>Hasta</label>
            <input type="date" id="f-hasta">
        </div>
        <div class="campo" style="display:flex; align-items:flex-end;">
            <button class="btn btn-sec" style="width:100%;" onclick="limpiarFiltros()">Limpiar</button>
        </div>
    </div>
</div>

<div id="conteo-resultados" style="color:var(--texto-sec); font-size:.9rem; margin-bottom:14px;"></div>

<!-- Tarjetas -->
<div id="tarjetas-historial" class="tarjetas-tickets"></div>
<div class="vacio" id="historial-vacio" style="display:none;">
    <div class="ic"><?= icono('search') ?></div><p>No se encontraron tickets con esos filtros.</p>
</div>

<!-- Paginación -->
<div class="paginacion" id="paginacion" style="display:none;">
    <button type="button" class="btn btn-sec btn-sm" id="pg-inicio">Inicio</button>
    <button type="button" class="btn btn-sec btn-sm" id="pg-prev"><?= icono('arrow-left', 15) ?></button>
    <span class="paginacion-info">Página <strong id="pg-actual">1</strong> de <strong id="pg-total">1</strong></span>
    <button type="button" class="btn btn-sec btn-sm" id="pg-next"><?= icono('arrow-right', 15) ?></button>
    <button type="button" class="btn btn-sec btn-sm" id="pg-ultimo">Último</button>
    <label class="paginacion-tamano">Mostrar
        <select id="pg-tamano">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
    </label>
</div>

<!-- Mascota del Modo TV: solo visible en pantalla completa dedicada -->
<div class="mascota-tv" id="mascota-tv">
    <div class="mascota-burbuja" id="mascota-burbuja"></div>
    <div class="mascota-cuerpo" id="mascota-cuerpo">
        <video src="/tickets/paneles/asistente2.mp4" autoplay loop muted playsinline disablepictureinpicture></video>
    </div>
</div>

<script>
var ICONO_MONITOR = '<?= addslashes(icono('monitor')) ?>';
var ICONO_UNDO = '<?= addslashes(icono('undo')) ?>';
(function(){
    var estado=document.getElementById('f-estado');
    var tecnico=document.getElementById('f-tecnico');
    var numero=document.getElementById('f-numero');
    var desde=document.getElementById('f-desde');
    var hasta=document.getElementById('f-hasta');
    var cont=document.getElementById('tarjetas-historial');
    var vacio=document.getElementById('historial-vacio');
    var conteo=document.getElementById('conteo-resultados');

    // ---- Paginación ----
    var PP_KEY = 'historial_por_pagina';
    var paginaActual = 1;
    var totalPaginas = 1;
    var porPagina = 10;
    try {
        var ppGuardado = parseInt(localStorage.getItem(PP_KEY), 10);
        if ([10,25,50].indexOf(ppGuardado) !== -1) porPagina = ppGuardado;
    } catch(e){}

    var paginacionEl = document.getElementById('paginacion');
    var pgInicio = document.getElementById('pg-inicio');
    var pgPrev   = document.getElementById('pg-prev');
    var pgNext   = document.getElementById('pg-next');
    var pgUltimo = document.getElementById('pg-ultimo');
    var pgTamano = document.getElementById('pg-tamano');
    var pgActualEl = document.getElementById('pg-actual');
    var pgTotalEl  = document.getElementById('pg-total');
    pgTamano.value = String(porPagina);

    function irAPagina(p){
        p = Math.max(1, Math.min(p, totalPaginas));
        if (p === paginaActual) return;
        paginaActual = p;
        filtrar();
        var panel = document.getElementById('panel-filtros-historial');
        if (panel) panel.scrollIntoView({behavior:'smooth', block:'start'});
    }
    pgInicio.addEventListener('click', function(){ irAPagina(1); });
    pgPrev.addEventListener('click', function(){ irAPagina(paginaActual - 1); });
    pgNext.addEventListener('click', function(){ irAPagina(paginaActual + 1); });
    pgUltimo.addEventListener('click', function(){ irAPagina(totalPaginas); });
    pgTamano.addEventListener('change', function(){
        porPagina = parseInt(pgTamano.value, 10);
        try{ localStorage.setItem(PP_KEY, String(porPagina)); }catch(e){}
        paginaActual = 1;
        filtrar();
    });

    function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }

    var ESTADOS_FINALES = ['completado', 'cancelado', 'rechazado'];
    function tarjeta(t){
        var tec = t.tecnico ? esc(t.tecnico) : '<span style="color:var(--texto-tenue);">Sin asignar</span>';
        var esFinal = ESTADOS_FINALES.indexOf(t.estado) !== -1;
        var btnClase = esFinal ? 'btn btn-sec btn-sm' : 'btn btn-sm';
        var btnTexto = esFinal ? 'Ver detalle' : 'Gestionar';
        return '<div class="tk-card">' +
            '<div class="tk-card-top">' +
                '<span class="tk-codigo">'+t.codigo_fmt+'</span>' +
                '<span class="badge badge-'+t.estado+'">'+t.estado_txt+'</span>' +
            '</div>' +
            '<div class="tk-titulo">'+esc(t.titulo)+'</div>' +
            '<div class="tk-meta">' +
                '<span><b>Solicitante:</b> '+esc(t.solicitante)+'</span>' +
                '<span><b>Área:</b> '+esc(t.area)+' · '+esc(t.ubicacion)+'</span>' +
                '<span><b>Categoría:</b> '+esc(t.categoria)+'</span>' +
                '<span><b>Técnico:</b> '+tec+'</span>' +
            '</div>' +
            '<div class="tk-card-bot">' +
                '<span class="tk-prio"><span class="punto" style="background:'+t.prioridad_color+';"></span>'+esc(t.prioridad)+'</span>' +
                '<span class="tk-fecha">'+t.fecha+'</span>' +
                '<a href="/tickets/paneles/tecnico_detalle.php?codigo='+encodeURIComponent(t.codigo)+'" class="'+btnClase+'">'+btnTexto+'</a>' +
            '</div>' +
        '</div>';
    }

    // ---- Mascota del Modo TV: mensajes rotativos "pensando" + reacción con tickets nuevos ----
    var mascotaCuerpo = document.getElementById('mascota-cuerpo');
    var mascotaBurbuja = document.getElementById('mascota-burbuja');
    var tvVistos = null; // null = modo TV no activo o recién activado (no reaccionar la primera vez)
    var pensandoInterval = null;
    var mensajeIdx = 0;
    var hayPendientes = false; // los mensajes solo se lanzan si hay tickets pendientes en pantalla
    var MENSAJES_PENSANDO = [
        'Hay tickets pendientes por atender 🎫',
        'No olvides revisar la bandeja de soporte 🔎',
        'Cada minuto cuenta, ¡vamos al rescate! ⚡',
        'Los usuarios esperan una solución 🙌',
        'Entra a Historial para atenderlos 🧰',
        'Un ticket resuelto a tiempo, un día mejor 🌟'
    ];

    function mostrarBurbuja(texto, pensando){
        if (!mascotaBurbuja) return;
        mascotaBurbuja.classList.add('visible');
        if (pensando){
            mascotaBurbuja.classList.remove('pensando');
            void mascotaBurbuja.offsetWidth;
            mascotaBurbuja.classList.add('pensando');
            clearTimeout(mascotaBurbuja._t);
            mascotaBurbuja._t = setTimeout(function(){ mascotaBurbuja.textContent = texto; }, 480);
        } else {
            mascotaBurbuja.textContent = texto;
        }
    }
    function cicloPensando(){
        if (!hayPendientes) return; // vacío: la mascota se queda quieta, sin lanzar nada
        var texto = MENSAJES_PENSANDO[mensajeIdx % MENSAJES_PENSANDO.length];
        mensajeIdx++;
        mostrarBurbuja(texto, true);
    }
    function reaccionarMascota(cantidad){
        if (mascotaCuerpo){
            mascotaCuerpo.classList.remove('reacciona');
            void mascotaCuerpo.offsetWidth; // fuerza el reinicio de la animación
            mascotaCuerpo.classList.add('reacciona');
        }
        mostrarBurbuja(cantidad === 1 ? '¡Nuevo ticket! 🔔' : ('¡' + cantidad + ' tickets nuevos! 🔔'), false);
    }

    var timer=null;
    function filtrar(){
        var tvActivo = document.body.classList.contains('tv-modo');
        // En Modo TV se muestran todos los que apliquen, sin paginar (pantalla dedicada).
        var pp = tvActivo ? 500 : porPagina;
        var pg = tvActivo ? 1 : paginaActual;
        var q = new URLSearchParams({
            estado: estado.value, tecnico: tecnico.value,
            numero: numero.value, desde: desde.value, hasta: hasta.value,
            pagina: pg, por_pagina: pp
        });
        fetch('/tickets/paneles/tecnico_historial_api.php?'+q.toString(), {cache:'no-store'})
        .then(r=>r.json()).then(function(d){
            if(!d.ok){ cont.innerHTML=''; return; }
            conteo.textContent = d.total + (d.total===1?' ticket':' tickets');
            if(d.total===0){ cont.innerHTML=''; vacio.style.display='block'; }
            else { vacio.style.display='none'; cont.innerHTML = d.tickets.map(tarjeta).join(''); }

            totalPaginas = d.total_paginas || 1;
            paginaActual = d.pagina || 1;
            if (!tvActivo && d.total > 0) {
                paginacionEl.style.display = 'flex';
                pgActualEl.textContent = paginaActual;
                pgTotalEl.textContent = totalPaginas;
                pgInicio.disabled = pgPrev.disabled = paginaActual <= 1;
                pgUltimo.disabled = pgNext.disabled = paginaActual >= totalPaginas;
            } else {
                paginacionEl.style.display = 'none';
            }

            // Detectar tickets nuevos mientras el Modo TV está activo
            if (document.body.classList.contains('tv-modo')) {
                var actuales = new Set(d.tickets.map(function(t){ return t.codigo; }));
                if (tvVistos !== null) {
                    var nuevos = 0;
                    actuales.forEach(function(cod){ if(!tvVistos.has(cod)) nuevos++; });
                    if (nuevos > 0) reaccionarMascota(nuevos);
                }
                tvVistos = actuales;
                hayPendientes = d.total > 0;
                if (!hayPendientes && mascotaBurbuja) {
                    mascotaBurbuja.classList.remove('visible', 'pensando');
                }
            } else {
                tvVistos = null;
                hayPendientes = false;
            }
        }).catch(function(e){ console.error('Error historial:', e); });
    }
    function filtrarDebounce(){ clearTimeout(timer); timer=setTimeout(filtrar, 250); }

    window.limpiarFiltros=function(){
        estado.value='activos'; tecnico.value=''; numero.value=''; desde.value=''; hasta.value='';
        paginaActual = 1;
        filtrar();
    };

    function resetYFiltrar(){ paginaActual = 1; filtrar(); }
    function resetYFiltrarDebounce(){ paginaActual = 1; filtrarDebounce(); }
    [estado,tecnico].forEach(el=>el.addEventListener('change', resetYFiltrar));
    [numero,desde,hasta].forEach(el=>el.addEventListener('input', resetYFiltrarDebounce));

    // ---- Modo TV: pantalla dedicada que nadie toca ----
    // Oculta los filtros, centra las tarjetas y solo muestra lo que aún necesita
    // atención (pendientes y en atención), refrescando solo cada 5s.
    var TV_KEY = 'historial_modo_tv';
    var tvBtn = document.getElementById('btn-modo-tv');

    function aplicarModoTV(activo){
        document.body.classList.toggle('tv-modo', activo);
        if (tvBtn) tvBtn.innerHTML = activo ? (ICONO_UNDO + ' Salir de Modo TV') : (ICONO_MONITOR + ' Modo TV');
        if (activo) {
            estado.value = 'activos';
            if (!pensandoInterval) {
                mensajeIdx = 0;
                cicloPensando();
                pensandoInterval = setInterval(cicloPensando, 5000);
            }
        } else {
            if (pensandoInterval) { clearInterval(pensandoInterval); pensandoInterval = null; }
            if (mascotaBurbuja) { mascotaBurbuja.classList.remove('visible','pensando'); }
        }
    }
    window.toggleModoTV = function(){
        var activo = !document.body.classList.contains('tv-modo');
        try{ localStorage.setItem(TV_KEY, activo ? '1' : '0'); }catch(e){}
        aplicarModoTV(activo);
        filtrar();
    };

    // Si esta pantalla ya se dejó en Modo TV la última vez, se restaura solo
    var tvGuardado = false;
    try{ tvGuardado = localStorage.getItem(TV_KEY) === '1'; }catch(e){}
    if (tvGuardado) aplicarModoTV(true);

    filtrar();  // carga inicial

    // ---- Refresco automático (siempre, no solo en Modo TV) ----
    // Así los tickets nuevos del solicitante aparecen solos, sin necesitar F5.
    // No molesta lo que el técnico esté escribiendo: solo vuelve a pedir la
    // lista con los filtros actuales y la repinta.
    setInterval(filtrar, 6000);
})();
</script>
<?php require __DIR__ . '/../config/layout_bottom.php'; ?>