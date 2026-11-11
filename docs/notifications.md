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

El cron no se instala automáticamente. Desde CHG-067 la línea única es `php artisan schedule:run` (ver `docs/DEPLOY.md`), que ejecuta este comando, `miso:guardian-nudges` y `notifications:dispatch`. La generación solo inserta en la base; el envío externo lo hace `notifications:dispatch`.

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


## Canal push y avisos del guardián (CHG-067)

- **Patrón de canales:** cada canal tiene su columna `*_sent_at` en `notifications` (`telegram_sent_at`, `push_sent_at`) y su tabla de vinculación (`push_subscriptions`). La entrega pasa por `PushGateway` (`WebPushGateway` con VAPID). Para Telegram o WhatsApp: otra implementación del gateway, su columna y su vinculación. Con más de tres canales conviene pasar a una tabla `notification_deliveries`.
- **Aviso del guardián:** tipo `guardian_nudge`, origen `guardian` con `source_id` = UUID v5 del módulo, ocurrencia `{fecha}:{franja}`. Lo crea `GuardianNudgePlanner` solo si el usuario activó los avisos, tiene un dispositivo vinculado, la franja está activa, no superó el tope diario y queda algo pendiente en hábitos, agua, tareas o ejercicio. Aparece también en la campana y lleva al módulo.
- **Envío:** `NotificationDispatcher` toma lo disponible, no cancelado, no leído, de las últimas 12 h y sin `push_sent_at`; lo reclama con un `UPDATE` condicional y envía a cada dispositivo. Un 404/410 borra la suscripción. Un fallo no se reintenta.
- **Preferencias** (`module_settings`, módulo `notifications`): `guardian_nudges`, `nudge_morning|afternoon|night` y `push_birthday|task_due|plan_due|reminder` (qué otros tipos van al celular; apagados por defecto).
- **Pruebas:** `php artisan test --filter='GuardianNudges|NotificationDispatch'`.

### Acciones del aviso (CHG-068)

- El payload push incluye `actions`, `checkin_url` y, en avisos de agua, `quick_url`: URL firmada (12 h) a `POST /api/push/quick-water/{notificación}` que registra 250 ml de agua sin abrir la app. La columna `action_done_at` se reclama con un UPDATE condicional: repetir la petición no suma otro vaso.
- "Check-in" abre `/?checkin=1&via=checkin`, la hoja de 20 s de Inicio. iPhone no muestra botones: el aviso abre la app como siempre.
