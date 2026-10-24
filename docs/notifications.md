# Notificaciones

MiSo utiliza dos tablas: `notifications` contiene los avisos y `reminders` guarda las anticipaciones personalizadas. No requiere runner externo, API de entregas, workers ni conexión con Telegram.

## Ejecución

```sh
php artisan notifications:generate
php artisan notifications:generate --user=ID
```

El comando llama a `GenerateNotifications::run(User $user)`. Sin `--user`, procesa todos los usuarios por lotes y delimita explícitamente cada consulta por propietario. Puede ejecutarse desde un cron del servidor:

```cron
* * * * * cd /ruta/a/miso && php artisan notifications:generate
```

El cron no se instala automáticamente. La generación solo inserta en la base: no envía mensajes externos.

## Reglas y fechas

Ajustes → Notificaciones permite activar cumpleaños de hoy, tareas incompletas que vencen mañana y planes programados para mañana. Estas reglas empiezan desactivadas; sus preferencias se conservan en `module_settings`.

Las reglas automáticas están disponibles desde las 09:00 de hoy. Los cumpleaños del 29 de febrero se celebran el 28 en años no bisiestos. No hay reglas automáticas para metas ni hábitos.

Tareas, planes y metas permiten varias anticipaciones personalizadas: cantidad entera no negativa y unidad (minutos, horas, días, semanas, meses o años). No se permite repetir una pareja cantidad/unidad. Se exige una fecha del origen; las tareas con hora la respetan y los demás casos usan las 09:00. Meses y años se restan sin desbordar el calendario; la fecha resultante debe estar entre 1970 y 9999.

Guardar un elemento conserva la configuración, sin generar avisos. Cada ejecución calcula las fechas desde el origen vigente y genera únicamente avisos de hoy cuya hora ya llegó. No reconstruye días anteriores. Si el cron se interrumpe y vuelve ese mismo día, recupera los avisos vencidos de hoy.

Las reglas usan `America/Bogota`; los instantes de notificación se guardan en UTC. No cambia el almacenamiento temporal de los módulos existentes.

## Modelo e idempotencia

`UserNotification` usa la tabla `notifications`: UUID, propietario, relación polimórfica `source`, tipo, `occurrence_key`, título, cuerpo, `scheduled_for`, lectura, cancelación, `telegram_sent_at` y timestamps. Las relaciones usan los alias del morph map vigente, incluido `plan`.

El índice único combina propietario, origen, tipo y ocurrencia. Un cumpleaños usa su fecha anual; tareas y planes usan la fecha del evento; cada recordatorio usa su UUID y el instante calculado. Repetir el comando, incluso con productores simultáneos, no crea duplicados. Una ocurrencia cancelada que vuelve a ser válida recupera su misma fila y conserva su lectura.

`Reminder` contiene UUID, propietario, origen polimórfico, cantidad, unidad y timestamps. No almacena fechas calculadas ni revisiones. Su índice único impide anticipaciones idénticas para el mismo origen.

El observer cancela avisos todavía no enviados cuando cambian las fechas o el estado del origen y elimina sus recordatorios si se elimina el origen. Cambiar o quitar una anticipación cancela sus avisos. Los cambios por modelo desde web, MCP y CalDAV pasan por el observer; las actualizaciones SQL masivas deben invocar explícitamente `ManageReminders::refresh` porque no disparan eventos Eloquent.

## Bandeja y Telegram

La campana presenta avisos disponibles y no cancelados, filtros, paginación y enlaces al origen. Abrirla no marca leído. Cada escritura de lectura queda limitada al usuario autenticado. Si el origen fue eliminado, el enlace vuelve a su módulo.

`read_at` y `telegram_sent_at` son independientes. El envío Telegram queda pendiente: no hay credenciales, vinculación, endpoints ni controles de envío. `telegram_sent_at` permanece nulo. El historial se conserva; esta versión no incorpora poda.

## Validación

- PHP: `php artisan test --filter='NotificationsTest|NotificationGenerationTest'`.
- Navegador aislado: `npx playwright test --config=tests/Browser/notifications.config.js` (base exclusiva `storage/framework/testing/notifications-browser.sqlite`).
- MySQL: ejecutar las pruebas en una base desechable `miso_notifications_test_*`; nunca usar `RefreshDatabase` contra la base personal.
- Concurrencia: `php tests/Support/notifications-concurrency.php miso_notifications_test_20261024 3307`, después de migrar esa base desechable.
- Assets e interfaz: `npm run build`, `php artisan ui:conformance` y `git diff --check`.

## Reemplazo local del 24/10/2026

Se verificó que las siete tablas anteriores estaban vacías y se revirtieron exclusivamente sus tres migraciones. La migración local nueva crea las dos tablas; no sirve como actualización directa de una instalación que conserve las migraciones antiguas aplicadas. Las credenciales del propósito retirado `notifications-runner` se revocaron sin modificar otras integraciones. Se preservaron las preferencias de las reglas.

La copia recuperable previa está en `storage/framework/testing/notifications-before-simplify-20261024-121451.zip` e incluye los cambios locales y el diff anterior. No contiene una copia de la base personal; las tablas retiradas estaban vacías. El cron y el envío Telegram quedan sin configurar.

### Resultado de la validación

- SQLite: 29 pruebas seleccionadas, 157 aserciones, incluidas notificaciones, ajustes, editor de tareas, Obsidian y CalDAV.
- MySQL 8.4.3 desechable: 11 pruebas específicas, 77 aserciones; migración reversible conservando las demás tablas; cuatro productores simultáneos generan una única fila.
- Navegador: tres escenarios completos a 390, 768 y 1440 px, con filtros, lectura, Escape y retorno del foco, ajustes y campos de recordatorio. Axe no encontró infracciones en bandeja ni ajustes. Sin errores JavaScript ni desbordamiento horizontal.
- Build y conformidad UI correctos; sin deuda visual nueva. El build conserva el aviso de chunks mayores de 500 kB.
- La base local contiene las dos tablas nuevas y no conserva las seis tablas retiradas; la tabla `reminders` se recreó con la estructura simplificada. No existen rutas del runner.
