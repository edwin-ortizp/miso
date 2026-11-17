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
| `notifications:generate` | Crea avisos de cumpleaños, tareas, planes, recordatorios y, cada minuto, el aviso `medication_due` de cada toma de medicamento cuya hora ya llegó (CHG-134; sin cron nuevo) | Índice único de `notifications` (usuario, origen, tipo, ocurrencia `{fecha}:{HH:MM}`) |
| `miso:guardian-nudges` | Crea el aviso del guardián de la franja actual | Ocurrencia `{fecha}:{franja}` en el mismo índice único |
| `miso:weekly-insights` | Domingos 14–21 h: resumen semanal y su aviso | Único `weekly_insights` (usuario, semana) y el índice de `notifications` |
| `miso:friend-milestones` | Cada hora: avisa los hitos de racha compartida (7, 30, 100 días) entre amigos | Índice único de `notifications` con clave `streak:{menor}:{mayor}:{hito}`; se detiene a los 40 s y sigue desde un cursor en caché |
| `notifications:dispatch` | Envía por push lo disponible | Cada fila se reclama con `UPDATE … WHERE push_sent_at IS NULL` antes de enviar |

Precisión de los recordatorios de medicamentos (CHG-134): el aviso sale a la hora de la toma con el retraso del cron (hasta 1 minuto con la línea recomendada; hasta el intervalo del crontab si el hosting no permite cada minuto). Las horas y el «día» se calculan en `America/Bogota`; no hay zona horaria por usuario. Si el cron se cae, al volver se crean los avisos de las tomas de hoy ya vencidas, que quedan en la campana pero no salen por push pasados 60 minutos.

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
/ruta/php84 artisan schedule:list                 # las cinco tareas aparecen
/ruta/php84 artisan miso:push-check               # extensiones, claves, hora, salida HTTPS y última corrida
/ruta/php84 artisan miso:guardian-nudges --dry-run  # qué aviso recibiría cada usuario ahora
/ruta/php84 artisan notifications:dispatch --dry-run
/ruta/php84 artisan miso:friend-milestones --dry-run  # cuántas amistades están en un hito, sin escribir
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


## Colección por generaciones (CHG-104)

Publicar juntos el código, los assets compilados y `pokemon.json`. La migración `2026_11_14_000001_add_generation_to_game_species` añade e indexa `generation` y completa exclusivamente ese campo por número desde el JSON; valida nombres y cobertura de las especies existentes antes de modificar la tabla. Si hay discrepancias, se detiene: revisar el catálogo sin sobrescribir datos ni inventar generaciones. No volver a importar el catálogo completo para este llenado, porque el importador también actualiza otros campos.

Antes del despliegue autorizado, respaldar la base y comprobar la migración en una base desechable con `php tests/Support/verify-game-generations-mysql.php`. Aplicar la migración con el procedimiento habitual de despliegue solo después de la autorización del responsable; el código nuevo requiere la columna. No se ha aplicado a datos reales como parte del desarrollo.

La selección predeterminada es primera generación para todos los usuarios sin preferencia, incluidos los existentes. No se reescriben sus ajustes ni se eliminan especies obtenidas o Pokébolas pendientes. Después de desplegar, comprobar `/game/settings/generations`, guardar una selección con una cuenta autorizada y verificar colección, contador y encuentros nuevos. Más reglas y comandos de prueba en [Generaciones personales](game-generations.md).


## Objetivos conectados con hábitos (CHG-111)

Publicar juntos PHP, vistas y assets compilados. La migración `2026_11_15_000001_create_goal_habits_table` añade únicamente vínculos entre objetivos y hábitos, con pareja única y eliminación en cascada del vínculo. No convierte acciones en mediciones ni completa objetivos automáticamente.

Antes de habilitar el código, aplicar esa migración con el procedimiento de despliegue autorizado y respaldo habitual. El detalle requiere la tabla nueva. La migración se validó en MySQL 8.4 desechable con `php tests/Support/verify-goal-habits-mysql.php`; no se ha aplicado sobre datos personales ni producción. Al probar el script, usar una conexión MySQL de pruebas con permiso de crear una base desechable; solo elimina la base aleatoria que él mismo crea.

Tras desplegar, comprobar filtros móviles, apertura del Pokémon, lista → detalle, creación y vinculación de tareas/hábitos y desvinculación sin eliminar registros. Rollback de aplicación: conservar la tabla y los vínculos; no borrar esa información para apagar la funcionalidad.

## Formación · CHG-138

Después de desplegar el código y compilar assets (`npm run build`), ejecutar `php artisan migrate --force` con el PHP del servidor. La migración `2026_11_16_000020_create_learning_tables` agrega `learning_formations`, `learning_units` y `learning_shortcuts`; no importa datos ficticios ni transforma datos existentes. No agrega cron, jobs ni variables de entorno. Si se utilizan cachés de despliegue, regenerar las rutas y la configuración (`php artisan route:cache` y `php artisan config:cache`) para incluir el módulo nuevo.

Comprobar con una cuenta de prueba: activar Formación en Ajustes → Módulos si la selección está personalizada; abrir `/learning`, crear una carrera, un periodo y una materia; fijar la materia y recargar. Confirmar que el acceso abre la ubicación correcta y que no se puede eliminar un elemento con contenido. No ejecutar rollback tras registrar datos sin una copia: el rollback elimina las tres tablas del módulo.
