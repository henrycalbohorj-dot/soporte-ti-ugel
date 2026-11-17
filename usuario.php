<?php
/**
 * Archivo obsoleto de una etapa muy temprana del proyecto.
 * La pantalla real del solicitante es paneles/usuario.php — este
 * archivo se deja solo como redirección por si quedó algún
 * enlace o marcador viejo apuntando aquí.
 */
require_once __DIR__ . '/config/sesion.php';
exigir_rol(['solicitante']);
header('Location: /tickets/paneles/usuario.php');
exit;
