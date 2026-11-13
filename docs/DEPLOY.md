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


## Canal Android nativo · CHG-102/103

Este canal es independiente de Web Push/VAPID. Laravel sigue generando los mismos avisos y aplicando las preferencias existentes. `notifications:dispatch` ejecuta ambos transportes dentro del presupuesto compartido de tiempo/envíos; no se agrega otro cron ni se necesitan workers. Sin configuración Android, Web Push continúa funcionando.

Variables nuevas:

```dotenv
ANDROID_PUSH_ENABLED=false
FIREBASE_PROJECT_ID=proyecto-firebase
FIREBASE_CREDENTIALS=/ruta/privada/firebase-service-account.json
```

El JSON debe ser una cuenta de servicio autorizada a enviar por FCM HTTP v1 al proyecto (permiso `cloudmessaging.messages.create`). Guardarlo fuera de `public/`, con lectura limitada al usuario PHP, y nunca en Git ni en el APK. Mantener salida HTTPS a `oauth2.googleapis.com` y `fcm.googleapis.com` y reloj del servidor correcto. El archivo Android `google-services.json` pertenece al cliente y se configura aparte, según `Android/README.md`.

Procedimiento de activación, pendiente de autorización para producción:

1. Respaldar según el proceso habitual y desplegar la migración `2026_11_13_000001_create_android_push_tables.php`. Crea instalaciones y entregas Android sin modificar registros de notificaciones existentes. Fue verificada con MySQL 8.4 desechable.
2. Configurar proyecto/credenciales fuera del directorio público y activar `ANDROID_PUSH_ENABLED=true`; regenerar la caché de configuración si corresponde.
3. Ejecutar `php artisan miso:android-push-check` y `php artisan miso:android-push-check --dry-run`. No imprimen tokens ni claves y no envían. La comprobación de configuración no certifica permiso IAM ni recepción en el teléfono.
4. Mantener el cron existente `php artisan schedule:run`. Con una cuenta de prueba, activar en el APK y confirmar un envío de Laravel y su apertura en primer plano, segundo plano y proceso terminado. Un aviso de prueba debe crearse después de activar el dispositivo; no se reenvían avisos anteriores.

`android_installations` vincula usuario, token cifrado, secreto hasheado y versión de vínculo. `android_deliveries` registra una entrega única por aviso/instalación/vínculo. Las horas se almacenan en UTC. `accepted_at` significa que FCM aceptó el mensaje, no lectura ni recepción confirmada. Un fallo transitorio se reintenta al minuto y después a los cinco minutos; el tercer fallo termina el intento. Token `UNREGISTERED` desactiva la instalación. Una reclamación que quede en `sending` más de cinco minutos se termina como fallida, sin reenviarla: tras un crash no se puede saber si el proveedor la aceptó y se evita duplicarla.

El comando muestra conteos de aceptadas y fallidas por Android; `miso:last-run:dispatch` conserva el registro del transporte web existente. Para inspección detallada utilizar las entregas Android, sin exponer tokens en vistas o logs. El presupuesto es por corrida, no una promesa de entrega inmediata.

Rollback: desactivar `ANDROID_PUSH_ENABLED` y regenerar configuración detiene solo los envíos nativos. Mantener tablas para conservar vínculos/historial y no ejecutar `migrate:rollback` sobre datos reales como apagado. Web Push y la aplicación Laravel continúan. Para que el dispositivo deje de mostrar mensajes ya aceptados por FCM, usar Desactivar en este dispositivo; el interruptor del servidor no puede retirar mensajes ya en tránsito.

No se desplegó ni migró producción en esta implementación. La comprobación real queda pendiente de Firebase, cuenta de prueba y dispositivo físico.
