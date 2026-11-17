<?php
/**
 * ============================================================
 *  MANEJO DE SESIÓN Y PROTECCIÓN POR ROLES
 *  Incluir este archivo al inicio de cualquier página que
 *  requiera que el usuario haya iniciado sesión.
 * ============================================================
 */

// La sesión NUNCA debe expirar por tiempo/inactividad: solo se cierra cuando
// el propio usuario presiona "Salir". Se extiende la duración de la cookie y
// del almacenamiento de sesión en el servidor a 10 años.
if (session_status() === PHP_SESSION_NONE) {
    $duracion = 60 * 60 * 24 * 365 * 10; // 10 años, en segundos
    ini_set('session.gc_maxlifetime', (string)$duracion);
    session_set_cookie_params([
        'lifetime' => $duracion,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Renovar la cookie en cada visita para que la fecha de expiración
    // siempre esté "10 años a partir de hoy", no se vaya acercando.
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), $_COOKIE[session_name()], time() + $duracion, '/', '', false, true);
    }
}

/**
 * Devuelve true si hay un usuario con sesión activa.
 */
function hay_sesion(): bool {
    return isset($_SESSION['id_usuario']);
}

/**
 * Obliga a tener sesión iniciada. Si no la hay, manda al login.
 * Llamar al inicio de cada panel.
 */
function exigir_login(): void {
    if (!hay_sesion()) {
        header('Location: /tickets/index.php');
        exit;
    }
}

/**
 * Obliga a que el usuario tenga uno de los roles permitidos.
 * Ejemplo: exigir_rol(['admin']) o exigir_rol(['tecnico','admin'])
 * Si no cumple, lo devuelve a su propio panel.
 */
function exigir_rol(array $roles_permitidos): void {
    exigir_login();
    if (!in_array($_SESSION['rol'], $roles_permitidos, true)) {
        redirigir_a_su_panel();
        exit;
    }
}

/**
 * Redirige al usuario al panel que corresponde a su rol.
 */
function redirigir_a_su_panel(): void {
    switch ($_SESSION['rol'] ?? '') {
        case 'admin':
            header('Location: /tickets/paneles/admin.php');
            break;
        case 'tecnico':
            header('Location: /tickets/paneles/tecnico.php');
            break;
        default: // solicitante
            header('Location: /tickets/paneles/usuario.php');
            break;
    }
    exit;
}

/**
 * Devuelve el nombre completo del usuario en sesión.
 */
function nombre_usuario(): string {
    return trim(($_SESSION['nombres'] ?? '') . ' ' . ($_SESSION['apellidos'] ?? ''));
}