</main>
    </div>
</div>
<script>
    function cambiarTema(){
        var a=document.documentElement.getAttribute('data-tema');
        var n=a==='oscuro'?'claro':'oscuro';
        document.documentElement.setAttribute('data-tema',n);
        localStorage.setItem('tema',n);
    }

    // ---- Evitar doble clic / doble envío en formularios ----
    // Al enviar un formulario (Guardar, Cancelar ticket, etc.) se deshabilitan
    // sus botones de envío, así un doble clic (o un clic mientras la página
    // todavía está procesando) no genera un registro duplicado.
    // IMPORTANTE: el bloqueo de "dataset.enviando" se marca de inmediato (para
    // cortar un segundo clic ya mismo), pero deshabilitar los botones se retrasa
    // al siguiente ciclo (setTimeout 0). Si se deshabilita el propio botón que
    // disparó el envío de forma síncrona dentro de su mismo evento "submit", en
    // formularios multipart/form-data (los que tienen un campo de archivo, como
    // "Completar" con evidencia fotográfica) el navegador puede cancelar el
    // envío en silencio — sin error y sin que llegue nada al servidor. Retrasarlo
    // evita ese problema y el doble envío sigue bloqueado igual.
    document.addEventListener('submit', function(ev){
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.enviando === '1') { ev.preventDefault(); return; }
        form.dataset.enviando = '1';
        setTimeout(function(){
            form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])').forEach(function(b){
                b.disabled = true;
            });
        }, 0);
    }, true);
    // ---- Campos de fecha: abrir el calendario nativo al hacer clic en cualquier
    //      parte del campo, no solo en el pequeño ícono (más fácil de usar). ----
    document.addEventListener('click', function(ev){
        var el = ev.target.closest ? ev.target.closest('input[type="date"]') : null;
        if (el && typeof el.showPicker === 'function') {
            try { el.showPicker(); } catch(e){}
        }
    });

    function abrirMenu(){ document.getElementById('sidebar').classList.add('abierto'); document.getElementById('overlay').classList.add('activo'); }
    function cerrarMenu(){ document.getElementById('sidebar').classList.remove('abierto'); document.getElementById('overlay').classList.remove('activo'); }

    // ---- Botón ☰: en pantallas angostas abre/cierra el cajón (drawer);
    //      en escritorio oculta/muestra el sidebar por completo y lo recuerda ----
    function toggleSidebar(){
        if (window.matchMedia('(max-width:900px)').matches){
            var sb = document.getElementById('sidebar');
            if (sb.classList.contains('abierto')) { cerrarMenu(); } else { abrirMenu(); }
            return;
        }
        var colapsado = document.documentElement.getAttribute('data-sidebar') === 'colapsado';
        var nuevo = !colapsado;
        document.documentElement.setAttribute('data-sidebar', nuevo ? 'colapsado' : 'expandido');
        try{ localStorage.setItem('sidebar_colapsado', nuevo ? '1' : '0'); }catch(e){}
    }

    // ---- Sidebar tipo acordeón (grupos desplegables) ----
    function guardarEstadoGrupos(){
        var estado = {};
        document.querySelectorAll('.grupo-toggle').forEach(function(b){
            estado[b.dataset.target] = b.classList.contains('abierto');
        });
        try{ localStorage.setItem('sidebar_grupos', JSON.stringify(estado)); }catch(e){}
    }
    function toggleGrupo(btn){
        var panel = document.getElementById(btn.dataset.target);
        var abierto = !btn.classList.contains('abierto');
        btn.classList.toggle('abierto', abierto);
        panel.classList.toggle('abierto', abierto);
        guardarEstadoGrupos();
    }
    document.addEventListener('DOMContentLoaded', function(){
        var estado = {};
        try{ estado = JSON.parse(localStorage.getItem('sidebar_grupos') || '{}'); }catch(e){}
        document.querySelectorAll('.grupo-toggle').forEach(function(b){
            var panel = document.getElementById(b.dataset.target);
            // Si el grupo ya viene abierto por PHP (contiene la página activa), se respeta y no se toca.
            if (b.classList.contains('abierto')) return;
            if (estado[b.dataset.target]) {
                b.classList.add('abierto');
                panel.classList.add('abierto');
            }
        });
    });
</script>

<?php if (in_array($_SESSION['rol'] ?? '', ['tecnico', 'admin'], true)): ?>
<!-- ============ NOTIFICACIÓN POR VOZ (técnicos y administradores) ============ -->
<script>
(function(){
    var INTERVALO = 3000;   // revisar cada 3 segundos (casi instantáneo)
    var vozActiva = localStorage.getItem('voz_tickets') !== 'off';
    var audioDesbloqueado = false;
    var vozElegida = null;
    var primeraCarga = true;

    // Recordar qué códigos ya se anunciaron (persiste entre recargas de la sesión)
    var anunciados;
    try { anunciados = new Set(JSON.parse(sessionStorage.getItem('tickets_anunciados') || '[]')); }
    catch(e){ anunciados = new Set(); }
    function guardarAnunciados(){
        try { sessionStorage.setItem('tickets_anunciados', JSON.stringify(Array.from(anunciados))); } catch(e){}
    }

    var btn = document.getElementById('campanaBtn');
    var num = document.getElementById('campanaNum');

    // ---- Selección de voz masculina en español ----
    // Nota: el navegador solo permite elegir entre las voces ya instaladas en el
    // equipo/Windows — no es posible clonar ni imitar la voz real de una persona
    // específica. Se busca la voz masculina en español más formal/grave disponible.
    function cargarVoz(){
        if(!window.speechSynthesis) return;
        var voces = speechSynthesis.getVoices();
        vozElegida = voces.find(v => /es/i.test(v.lang) && /(jorge|pablo|raul|raúl|diego|carlos|enrique|male|hombre)/i.test(v.name))
              || voces.find(v => /es-PE/i.test(v.lang))
              || voces.find(v => /es-ES/i.test(v.lang))
              || voces.find(v => /es/i.test(v.lang))
              || null;
    }
    if (window.speechSynthesis){
        cargarVoz();
        speechSynthesis.onvoiceschanged = cargarVoz;
    }

    // ---- Sonido de campana (WebAudio) ----
    var audioCtx = null;
    function initAudio(){
        if(!audioCtx){
            try { audioCtx = new (window.AudioContext||window.webkitAudioContext)(); } catch(e){}
        }
        if(audioCtx && audioCtx.state === 'suspended'){ audioCtx.resume(); }
    }
    function campana(){
        initAudio();
        if(!audioCtx) return;
        try{
            var o=audioCtx.createOscillator(), g=audioCtx.createGain();
            o.type='sine'; o.frequency.value=880;
            o.connect(g); g.connect(audioCtx.destination);
            var t=audioCtx.currentTime;
            g.gain.setValueAtTime(0.001,t);
            g.gain.exponentialRampToValueAtTime(0.5,t+0.02);
            g.gain.exponentialRampToValueAtTime(0.001,t+1.0);
            o.start(t); o.stop(t+1.0);
        }catch(e){}
    }

    // ---- Voz ----
    function hablar(texto){
        if (!vozActiva || !window.speechSynthesis) return;
        try{
            // Chrome a veces deja la cola de síntesis de voz "atascada" (más común en Windows).
            // Reanudarla y limpiarla antes de hablar evita que el mensaje se pierda en silencio.
            if (speechSynthesis.paused) { speechSynthesis.resume(); }
            speechSynthesis.cancel();
            var u = new SpeechSynthesisUtterance(texto);
            u.lang='es-ES'; u.rate=0.95; u.pitch=0.82; u.volume=1;
            if (vozElegida) u.voice = vozElegida;
            var yaEmpezo = false;
            u.onstart = function(){ yaEmpezo = true; };
            u.onerror = function(ev){ console.error('No se pudo reproducir el aviso de voz:', ev.error || ev); };
            speechSynthesis.speak(u);
            // Watchdog: si Chrome no arrancó a hablar en ~400ms (cola atascada), reintenta UNA
            // sola vez — se guía por el evento "onstart" (no por .speaking/.pending, que pueden
            // quedar desactualizados un instante y provocar que el aviso se diga dos veces).
            setTimeout(function(){
                if (!yaEmpezo) {
                    try{ speechSynthesis.speak(u); }catch(e){}
                }
            }, 400);
        }catch(e){ console.error('Excepción al hablar:', e); }
    }

    // ---- Anuncio de un ticket nuevo ----
    function numeroDeTicket(t){
        // Usa t.numero si viene; si no, lo extrae del codigo (TK-000005 -> 5)
        if (t.numero !== undefined && t.numero !== null && t.numero !== '') return t.numero;
        var cod = (t.codigo || '').replace(/\D/g,'');
        return cod ? parseInt(cod, 10) : '';
    }
    function anunciar(t){
        campana();
        var n = numeroDeTicket(t);
        var piso = (t.ubicacion || '').trim();
        var texto = 'Atención, ticket número ' + n + (piso ? ', ' + piso : '') + '. Atender por favor.';
        setTimeout(function(){
            hablar(texto);
        }, 650);
    }

    function pintarCampana(total){
        if(!btn) return;
        if (total>0){ num.style.display='inline-block'; num.textContent = total; }
        else { num.style.display='none'; }
        btn.classList.toggle('con-pendientes', total>0);
        btn.style.opacity = vozActiva ? '1' : '0.45';
    }

    // ---- Revisión periódica ----
    function revisar(){
        fetch('/tickets/paneles/tecnico_pendientes_api.php', {cache:'no-store'})
        .then(r=>r.json())
        .then(function(d){
            if(!d.ok) return;
            pintarCampana(d.total);

            // Detectar tickets NUEVOS (no anunciados aún)
            var nuevos = [];
            d.tickets.forEach(function(t){
                if(!anunciados.has(t.codigo)){
                    anunciados.add(t.codigo);
                    nuevos.push(t);
                }
            });
            guardarAnunciados();

            // En la PRIMERA carga no anunciamos los que ya existían (evita avalancha),
            // salvo que nunca se hayan anunciado antes (sessionStorage vacío).
            if(primeraCarga){
                primeraCarga = false;
                return;
            }
            // Anunciar los nuevos, uno por uno
            nuevos.forEach(function(t, i){
                setTimeout(function(){ anunciar(t); }, i * 3500);
            });
        }).catch(function(err){ console.error('Error notif voz:', err); });
    }

    // ---- Botón activar/silenciar ----
    window.toggleVoz = function(){
        vozActiva = !vozActiva;
        localStorage.setItem('voz_tickets', vozActiva ? 'on':'off');
        if(btn) btn.style.opacity = vozActiva ? '1':'0.45';
        initAudio();
        if(vozActiva){ campana(); setTimeout(function(){ hablar('Notificaciones activadas.'); }, 500); }
        else { if(window.speechSynthesis) speechSynthesis.cancel(); }
    };

    // Desbloquear audio con el primer clic/tecla en cualquier parte (regla del navegador)
    function desbloquear(){
        if(audioDesbloqueado) return;
        audioDesbloqueado = true;
        initAudio();
        // "Calentar" la síntesis de voz: un texto vacío no siempre activa el motor de voz
        // en Windows/Chrome (a veces necesita descargar la voz de red la primera vez).
        // Se habla en volumen 0 para que el navegador inicialice el motor sin sonar.
        if(window.speechSynthesis){
            try{
                var w = new SpeechSynthesisUtterance('.');
                w.volume = 0; w.lang = 'es-ES';
                speechSynthesis.speak(w);
            }catch(e){}
        }
    }
    document.addEventListener('click', desbloquear, {once:false});
    document.addEventListener('keydown', desbloquear, {once:false});

    revisar();
    setInterval(revisar, INTERVALO);
})();
</script>
<?php endif; ?>
</body>
</html>