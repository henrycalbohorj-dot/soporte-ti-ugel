<?php
/**
 * PÁGINA DE INICIO DE SESIÓN (con animación de maletín)
 */
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/config/utilidades.php';

// Si ya tiene sesión, mandarlo directo a su panel.
if (hay_sesion()) {
    redirigir_a_su_panel();
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es" data-tema="claro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso · Soporte T.I.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/tickets/assets/css/estilos.css">
    <script>
        (function(){
            var t = localStorage.getItem('tema') || 'claro';
            document.documentElement.setAttribute('data-tema', t);
        })();
    </script>
    <style>
        /* ===== Página de login: escena centrada + animación de maletín ===== */
        .login-fondo {
            position: relative; overflow: hidden;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }

        .orbe { position: absolute; border-radius: 50%; filter: blur(70px); pointer-events: none; }
        .orbe-1 { width: 380px; height: 380px; background: var(--acento); opacity: .22; top: -110px; left: -90px; }
        .orbe-2 { width: 340px; height: 340px; background: var(--acento2); opacity: .18; bottom: -100px; right: -80px; }

        .escena {
            position: relative;
            width: 400px; max-width: 100%;
            min-height: 470px;
            display: flex; align-items: center; justify-content: center;
        }

        /* --- Maletín (detalle de marca, en tonos corporativos) --- */
        .maletin { position: absolute; z-index: 5; }
        .maletin-tapa {
            width:150px; height:70px; background:var(--acento2);
            border:3px solid var(--acento-h); border-radius:14px 14px 4px 4px;
            position:relative; margin:0 auto; transform-origin:bottom center;
        }
        .maletin-tapa .asa {
            width:46px; height:16px; background:var(--sidebar-bg); border-radius:8px 8px 0 0;
            position:absolute; top:-16px; left:50%; transform:translateX(-50%);
        }
        .maletin-tapa .cierre {
            width:30px; height:8px; background:#c9d3e3; border-radius:3px;
            position:absolute; bottom:8px; left:50%; transform:translateX(-50%);
        }
        .maletin-cuerpo {
            width:150px; height:96px; background:var(--acento);
            border:3px solid var(--acento-h); border-radius:4px 4px 14px 14px;
            margin:-3px auto 0; position:relative;
        }
        .maletin-cuerpo .franja {
            width:100%; height:14px; background:var(--acento-h); position:absolute; top:22px;
            border-top:2px solid var(--acento2); border-bottom:2px solid var(--acento2);
        }
        .maletin-cuerpo .candado {
            width:20px; height:20px; background:#c9d3e3; border:2px solid var(--acento-h);
            border-radius:4px; position:absolute; top:44px; left:50%; transform:translateX(-50%);
        }

        /* --- Tarjeta de login --- */
        .login-caja {
            position: relative; z-index: 6;
            width: 100%;
            background: var(--bg-panel); border:1px solid var(--borde);
            border-radius: var(--radio); box-shadow: var(--sombra-m);
            overflow: hidden;
        }
        .login-caja.animada { opacity: 0; transform: scale(.4) translateY(20px); }

        .login-cabecera {
            padding: 32px 32px 20px; text-align: center;
        }
        .login-cabecera .logo {
            width: 52px; height: 52px; border-radius: 12px;
            background: var(--acento-grad); color: #fff;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            box-shadow: var(--acento-glow);
        }
        .login-cabecera h1 { font-size: 1.3rem; font-weight: 800; letter-spacing: -.01em; margin-bottom: 4px; }
        .login-cabecera p { color: var(--texto-sec); font-size: .88rem; }

        .login-cuerpo { padding: 8px 32px 32px; }
        .login-cuerpo .campo { margin-bottom: 16px; }
        .login-cuerpo .btn { width: 100%; margin-top: 6px; }

        /* Keyframes (total ~1.2s) */
        @keyframes entra-maletin {
            0%   { transform: translateX(-440px) rotate(-22deg); }
            60%  { transform: translateX(0) rotate(0deg); }
            75%  { transform: translateX(0) translateY(-20px) rotate(3deg); }
            100% { transform: translateX(0) translateY(0) rotate(0deg); }
        }
        @keyframes abre-tapa {
            0%   { transform: rotateX(0deg); }
            100% { transform: rotateX(-150deg); }
        }
        @keyframes sale-caja {
            0%   { opacity:0; transform: scale(.4) translateY(20px); }
            60%  { opacity:1; transform: scale(1.04) translateY(-4px); }
            100% { opacity:1; transform: scale(1) translateY(0); }
        }
        @keyframes oculta-maletin {
            to { opacity:0; transform: scale(.6); }
        }

        .anim-maletin       { animation: entra-maletin .72s cubic-bezier(.34,1.4,.5,1) forwards; }
        .anim-tapa          { animation: abre-tapa .28s ease-in forwards; }
        .anim-oculta-maletin{ animation: oculta-maletin .3s ease forwards; }
        .anim-caja          { animation: sale-caja .42s cubic-bezier(.34,1.56,.5,1) forwards; }

        /* Si el usuario prefiere menos movimiento, mostrar la tarjeta directo */
        @media (prefers-reduced-motion: reduce) {
            .maletin { display: none; }
            .login-caja.animada { opacity:1 !important; transform:none !important; animation:none !important; }
        }
    </style>
</head>
<body>
    <div class="login-fondo">
        <div class="orbe orbe-1"></div>
        <div class="orbe orbe-2"></div>

        <div class="escena">

            <!-- Maletín (se anima y desaparece) -->
            <div class="maletin" id="maletin">
                <div class="maletin-tapa" id="maletin-tapa">
                    <div class="asa"></div>
                    <div class="cierre"></div>
                </div>
                <div class="maletin-cuerpo">
                    <div class="franja"></div>
                    <div class="candado"></div>
                </div>
            </div>

            <!-- Tarjeta de login (aparece al abrirse el maletín) -->
            <div class="login-caja animada" id="login-caja">
                <div class="login-cabecera">
                    <div class="logo"><?= icono('wrench', 26) ?></div>
                    <h1>Soporte T.I.</h1>
                    <p>Sistema de atención de tickets</p>
                </div>
                <div class="login-cuerpo">

                    <?php if ($error === 'credenciales'): ?>
                        <div class="aviso aviso-error">Usuario o contraseña incorrectos.</div>
                    <?php elseif ($error === 'inactivo'): ?>
                        <div class="aviso aviso-error">Tu cuenta está desactivada. Contacta al administrador.</div>
                    <?php elseif ($error === 'vacio'): ?>
                        <div class="aviso aviso-error">Completa usuario y contraseña.</div>
                    <?php endif; ?>

                    <form action="/tickets/autenticar.php" method="POST">
                        <div class="campo">
                            <label for="usuario">Usuario</label>
                            <input type="text" id="usuario" name="usuario"
                                   placeholder="Ej: admin" required>
                        </div>
                        <div class="campo">
                            <label for="password">Contraseña</label>
                            <input type="password" id="password" name="password"
                                   placeholder="Tu contraseña" required>
                        </div>
                        <button type="submit" class="btn">Ingresar</button>
                    </form>

                </div>
            </div>

        </div>
    </div>

    <script>
    (function(){
        var maletin = document.getElementById('maletin');
        var tapa    = document.getElementById('maletin-tapa');
        var caja    = document.getElementById('login-caja');
        var usuario = document.getElementById('usuario');

        // Si ya se mostró la animación en esta sesión del navegador, saltarla.
        if (sessionStorage.getItem('anim_maletin_vista')) {
            maletin.style.display = 'none';
            caja.style.opacity = '1';
            caja.style.transform = 'none';
            if (usuario) usuario.focus();
            return;
        }

        // Reproducir animación (~1.2s en total)
        maletin.classList.add('anim-maletin');
        setTimeout(function(){ tapa.classList.add('anim-tapa'); }, 720);
        setTimeout(function(){
            maletin.classList.add('anim-oculta-maletin');
            caja.classList.add('anim-caja');
        }, 1000);
        setTimeout(function(){
            sessionStorage.setItem('anim_maletin_vista', '1');
            if (usuario) usuario.focus();
        }, 1250);
    })();
    </script>
</body>
</html>
