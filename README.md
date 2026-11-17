# Soporte T.I. · UGEL Chincha

Sistema web de **mesa de ayuda (tickets)** del Área de Informática de la UGEL Chincha.
Las áreas registran incidencias, los técnicos las atienden y el administrador gestiona usuarios, áreas y el inventario de equipos con sus fichas de mantenimiento.

**Stack:** PHP 8.5 (sin frameworks) · PostgreSQL · Nginx + PHP-FPM · Ubuntu.

## Roles

| Rol | Qué hace |
|---|---|
| `solicitante` | Crea tickets con descripción y evidencias, sigue su estado. |
| `tecnico` | Bandeja de pendientes, toma tickets, registra seguimiento, cierra; historial y reportes. |
| `admin` | Usuarios, áreas, equipos (inventario de PCs con RAM, GPU, almacenamiento), fichas de mantenimiento y exportación de reportes. |

## Estructura

```
index.php / autenticar.php / salir.php   Login y sesión
config/        conexión PDO, sesión, utilidades, layout
paneles/       pantallas y APIs JSON por rol (usuario_*, tecnico_*, admin_*)
assets/        estilos e imágenes
database/schema.sql   estructura completa de la base (sin datos)
```


## Instalación (Ubuntu)

```bash
sudo apt install nginx php8.5-fpm php8.5-pgsql postgresql
sudo -u postgres createuser -P soporte_ti_app
sudo -u postgres createdb -O soporte_ti_app soporte_ti
psql -h localhost -U soporte_ti_app -d soporte_ti -f database/schema.sql
sudo cp -r . /var/www/html/tickets
```

La conexión se configura con variables de entorno (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, ver `.env.example`). Con Nginx se pasan así:

```nginx
# /etc/nginx/snippets/tickets.conf  (incluido dentro del server {} por defecto)
location /tickets/ {
    alias /var/www/html/tickets/;
    index index.php;
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        # Credenciales: solo en el servidor, nunca en el código
        fastcgi_param DB_NAME soporte_ti;
        fastcgi_param DB_USER soporte_ti_app;
        fastcgi_param DB_PASS "********";
    }
    location ~ /(config|database)/ { deny all; }
}
```

Luego crea el primer usuario `admin` directamente en la tabla `usuarios` (contraseña con `password_hash()` de PHP).

## Integración

Forma parte del conjunto de apps del Área de Informática desplegadas en un único servidor Ubuntu. Ver [apps-ugel-chincha](https://github.com/henrycalbohorj-dot/apps-ugel-chincha).

---
Desarrollado por **Ing. Henry Braulio Pachas Dávalos** · Área de Informática, UGEL Chincha.
