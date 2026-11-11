# Despliegue en el servidor (hosting compartido)

Todo lo que corre solo en el servidor debe estar documentado aquí. Ver la regla en `docs/ARCHITECTURE.md` › Tareas programadas.

## 1. Encontrar la ruta de PHP 8.4

El proyecto exige PHP 8.4 también en la línea de comandos, y en hosting compartido el `php` del cron no siempre es el mismo de la web.

- Por SSH: `which php` y `php -v`. Si no es 8.4, busca la ruta en cPanel → *Seleccionar versión de PHP* o *MultiPHP Manager*. Suele ser `/opt/cpanel/ea-php84/root/usr/bin/php`, `/usr/local/bin/php84` o `/opt/alt/php84/usr/bin/php`.
- Comprueba: `/ruta/php84 -v`.

En los ejemplos, `PHP=/ruta/php84` y `APP=/home/usuario/miso` (la carpeta que contiene `artisan`).

## 2. Una sola línea de cron

En cPanel → *Trabajos de cron*, agrega:

```cron
* * * * * cd /home/usuario/miso && /ruta/php84 artisan schedule:run >> /dev/null 2>&1
```

- Si el hosting no permite cada minuto, usa `*/5` o `*/15` en el primer campo. Todo sigue funcionando: las tareas están programadas para correr en cada llamada y las franjas de aviso duran 2 horas.
- No agregues una línea por comando: la frecuencia de cada tarea vive en `routes/console.php`.
- No hace falta `queue:work` ni procesos permanentes: los comandos envían directo.

Tareas que ejecuta `schedule:run`:

| Comando | Qué hace | Por qué correrlo de más no repite nada |
|---|---|---|
| `notifications:generate` | Crea avisos de cumpleaños, tareas, planes y recordatorios | Índice único de `notifications` (usuario, origen, tipo, ocurrencia) |
| `miso:guardian-nudges` | Crea el aviso del guardián de la franja actual | Ocurrencia `{fecha}:{franja}` en el mismo índice único |
| `miso:weekly-insights` | Domingos 14–21 h: resumen semanal y su aviso | Único `weekly_insights` (usuario, semana) y el índice de `notifications` |
| `notifications:dispatch` | Envía por push lo disponible | Cada fila se reclama con `UPDATE … WHERE push_sent_at IS NULL` antes de enviar |

Además, cada tarea usa `withoutOverlapping`: si una corrida sigue viva, la siguiente no arranca. Cada corrida se detiene sola a los 40 s o 200 envíos (`config/game.php` › `nudges`) y la siguiente continúa.

## 3. Variables de `.env`

```dotenv
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:tu-correo@ejemplo.com
GUARDIAN_NUDGES_ENABLED=true
```

- Genera las claves **una sola vez** con `/ruta/php84 artisan miso:vapid` y cópialas. Cambiarlas después obliga a cada usuario a reactivar los avisos.
- Tras editar `.env`: `/ruta/php84 artisan config:clear` (o `config:cache` si usas caché de configuración).
- Dependencias tras actualizar el código: `composer install --no-dev --optimize-autoloader` y `php artisan migrate --force`.

## 4. Comprobar

```sh
/ruta/php84 artisan schedule:list                 # las cuatro tareas aparecen
/ruta/php84 artisan miso:push-check               # extensiones, claves, hora, salida HTTPS y última corrida
/ruta/php84 artisan miso:guardian-nudges --dry-run  # qué aviso recibiría cada usuario ahora
/ruta/php84 artisan notifications:dispatch --dry-run
```

`miso:push-check` necesita salida HTTPS (puerto 443) a `fcm.googleapis.com` (Android/Chrome), `updates.push.services.mozilla.com` (Firefox) y `web.push.apple.com` (iPhone). Si alguna falla, pide al hosting que la habilite.

Prueba real: en el celular, instala MiSo, ve a Ajustes → Notificaciones → *Activar en este dispositivo*, y espera a la siguiente franja con algo pendiente (agua, por ejemplo).

## 5. Si el cron corre dos veces

No pasa nada. Si se duplica la línea, se ejecuta a mano o se cruzan dos corridas, la base de datos impide crear un segundo aviso de la misma franja y enviar dos veces la misma notificación.

## 6. Registros y apagado

- Errores y avisos: `storage/logs/laravel.log` (o el visor `/log-viewer`).
- Apagar solo los avisos del guardián: `GUARDIAN_NUDGES_ENABLED=false` y `config:clear`.
- Apagar todo lo programado: comenta la línea de cron.
