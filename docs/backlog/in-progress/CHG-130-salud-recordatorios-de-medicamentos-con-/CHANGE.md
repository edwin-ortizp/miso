# CHG-130 · Salud: recordatorios de medicamentos con el cron existente
**Estado:** En verificación
**Nivel:** M
**Modelo sugerido:** estandar
<!-- Estados y nivel se cambian SOLO con node .claude/team.mjs. Modelo: un perfil de docs/TEAM_MODELS.md -->
**Creado:** 2026-11-16 · **Aprobado:** 2026-11-16 · **Cerrado:** —
**Relacionado:** CHG-129 (depende de él: medicamentos y tomas)

## 0. En palabras simples
- Qué vas a ver distinto: a la hora de cada toma de un medicamento programado, MiSo te avisa en la campana de la app y, si lo tienes activado, con una notificación push. El aviso trae un botón «Tomado»: lo tocas y la toma queda registrada sin abrir la app. Si ya registraste la toma, no te avisa. En Ajustes → Notificaciones puedes apagar estos avisos.
- Cómo comprobarlo tú: crea un medicamento con una toma dentro de unos minutos; al llegar la hora recibes un solo aviso (aunque el servidor corra varias veces). Toca «Tomado» en el aviso y comprueba que la toma aparece como tomada en Salud → Medicamentos (y que tocarlo otra vez no la duplica). Márcala como tomada antes de la hora y comprueba que no llega aviso. En iPhone el aviso no muestra botones: abre la app como siempre.

## 1. Resumen
Agrega recordatorios a los medicamentos de CHG-129 reutilizando el sistema de notificaciones y el cron que ya existen (`schedule:run` → `notifications:generate` y `notifications:dispatch`, cada minuto). No se crea ningún cron ni proceso nuevo. Cada toma esperada genera como máximo una notificación `medication_due`, identificada por su hora, de modo que correr la tarea varias veces no duplica avisos. MiSo recuerda, no prescribe: no sugiere dosis ni cambia horarios.

## 2. Producto (product-manager)
- Problema / usuario: tomar un medicamento a tiempo se olvida con facilidad; registrar las tomas sin aviso depende de acordarse.
- Incluye:
  - Aviso a la hora de cada toma programada pendiente (campana en la app y push si está habilitado).
  - Ventana de vigencia de 60 minutos: pasada esa ventana el aviso no se envía por push.
  - Cancelación del aviso si la toma ya se registró (tomada u omitida) o si el medicamento se finalizó.
  - Interruptor «Medicamentos» en Ajustes → Notificaciones.
  - Texto del aviso con nombre, dosis y hora.
  - Botón «Tomado» en la notificación push (y «Abrir»): registra la toma sin abrir la app, con enlace firmado de un solo uso (patrón de CHG-069, `QuickWaterController`).
- No incluye: aviso de reposición o de medicamento por agotarse, alertas de interacciones o dosis máximas, aviso a terceros (familiares), zona horaria por usuario, canales nuevos (correo, Telegram, WhatsApp).  Botón «Omitir» en la notificación (la omisión se registra desde la app).
- Criterios de aceptación (Dado/Cuando/Entonces):
  - CA1. Dado un medicamento con toma a las 08:00 y sin registrar, cuando el cron corre a partir de las 08:00, entonces se crea una notificación para esa toma.
  - CA2. Dado que `notifications:generate` corre dos o más veces, cuando se evalúa la misma toma, entonces existe una sola notificación (idempotencia).
  - CA3. Dado que la toma se registró como tomada u omitida, cuando llega o pasa la hora, entonces no hay aviso; si ya existía uno sin leer, queda cancelado.
  - CA4. Dado que el cron se retrasó más de 60 minutos, cuando corre, entonces se crea la notificación en la campana pero no se envía por push.
  - CA5. Dado que el usuario apagó «Medicamentos», cuando llega la hora, entonces no recibe push, pero el aviso sigue apareciendo en la campana.
  - CA6. Dado otro usuario, cuando llega la hora, entonces solo recibe aviso quien tiene el medicamento.
  - CA7. Dado un medicamento finalizado o con fecha de fin pasada, cuando corre el cron, entonces no genera avisos.
  - CA8. Dado un aviso con botón «Tomado», cuando lo toco, entonces la toma queda registrada como tomada con la hora actual, igual que desde la app (misma Action `LogMedicationIntake`) y el aviso se marca como atendido.
  - CA9. Dado que repito la acción «Tomado» (doble toque, reenvío), cuando se procesa, entonces no se crea una segunda toma y la respuesta es «ya registrada».
  - CA10. Dado un enlace de acción vencido (pasado el fin del día de la toma), manipulado o de otro usuario, cuando se usa, entonces se rechaza y no registra nada.

## 3. UX/UI
<!-- Sin pantallas: escribe "No aplica — motivo" en cada parte. Con UI, approve exige un boceto ASCII en un bloque de código. Si hay flujo, agrega un diagrama en un bloque mermaid. -->
### 3.1 Experiencia (ux-designer)
- Plantilla: no aplica una plantilla de página; es un aviso (push y campana) más un interruptor en la pantalla existente de Ajustes → Notificaciones.
- Flujo y cantidad de pasos: la persona no hace nada para recibir avisos más allá de tener medicamentos con horario. Apagar el push: Ajustes → Notificaciones → interruptor «Medicamentos» (1 toque). Atender un aviso: «Tomado» en la notificación (1 toque, sin abrir la app) o «Abrir» para ir a Salud → Medicamentos.
- Qué contiene cada pantalla y qué es lo más importante: el aviso dice qué medicamento, qué dosis y a qué hora; el botón principal es «Tomado». En la campana el aviso lleva a Medicamentos.
- Estados (vacío / carga / error / éxito / validaciones):
  - Aviso nuevo, aviso atendido (queda como leído y marcado), aviso vencido (más de 60 min: solo en la campana).
  - Éxito de «Tomado»: la notificación se cierra; al abrir la app la toma figura como tomada. Enlace vencido: al abrir la app se ofrece registrar la toma ahí.
  - Sin permiso de push: el interruptor sigue disponible y se muestra el estado actual de los permisos como en los otros avisos de Ajustes.
- Información necesaria y momento de presentación: nombre, dosis y hora en el título y el cuerpo; sin datos clínicos adicionales en la pantalla bloqueada del teléfono (privacidad: no se incluyen indicaciones ni notas).
- Accesibilidad de uso (teclado, foco, errores): el interruptor tiene etiqueta y estado anunciado; la campana ya es operable con teclado; los botones del aviso los presenta el sistema operativo. iPhone no muestra botones: el aviso abre la app.

```mermaid
flowchart LR
  A[Cron cada minuto] --> B[Aviso a la hora de la toma]
  B --> C{Push activo y aviso reciente}
  C -->|Sí| D[Push con Tomado y Abrir]
  C -->|No| E[Solo campana]
  D -->|Tomado| F[Toma registrada]
  D -->|Abrir| G[Salud › Medicamentos]
  E --> G
```

Boceto del aviso y de Ajustes:

```
Notificación push (Android)            Campana (en la app)
┌────────────────────────────┐         ┌──────────────────────────────┐
│ MiSo · Salud               │         │ 🔔 Avisos                     │
│ Es hora de tu Losartán     │         │ ● Es hora de tu Losartán      │
│ 50 mg (8:00)               │         │   50 mg (8:00)       hace 2 min│
│ [ Tomado ]  [ Abrir ]      │         │ ○ Resumen de la semana        │
└────────────────────────────┘         └──────────────────────────────┘

Ajustes ▸ Notificaciones
┌─────────────────────────────────────────────────────────┐
│ Avisos al celular (push)                                │
│  Cumpleaños ................................ [ ○── ]     │
│  Tareas por vencer ......................... [ ○── ]     │
│  Medicamentos .............................. [ ──● ]     │
│  Resumen de la semana ...................... [ ──● ]     │
│ Los avisos de medicamentos siempre se ven en la campana.│
└─────────────────────────────────────────────────────────┘
```

### 3.2 Visual (ui-designer)
- Distribución: el interruptor se suma a la lista de avisos al celular de `resources/views/livewire/notifications/settings.blade.php`, con el mismo componente y espaciado que `push_weekly_insights`. La notificación en la campana usa la fila existente de `bell.blade.php`.
- Componentes existentes a reutilizar (ruta): `settings.blade.php`, `bell.blade.php`, íconos y color del módulo salud desde `config/modules.php`.
- Jerarquía, color, tipografía y espaciado (tokens): sin estilos nuevos; el ícono del aviso es el del módulo salud. El texto del push es corto (título y una línea).
- Responsive y contraste: sin cambios de layout; verificar contraste AA del interruptor en claro y oscuro.

### 3.3 Escritura (ux-writer)
- Ubicación y estado: título y cuerpo del push y de la campana; etiqueta y ayuda del interruptor en Ajustes.
- Textos finales (variables y variantes):
  - Push y campana: título «Es hora de tu {medicamento} {dosis}», cuerpo «Toma programada a las {hora}.»
  - Botones: «Tomado», «Abrir».
  - Interruptor: «Medicamentos». Ayuda: «Te avisamos a la hora de cada toma programada. En la campana siempre verás el aviso.»
  - Enlace vencido al abrir: «El botón ya no está disponible. Registra la toma desde Medicamentos.»
- Reglas de docs/UX-WRITING.md aplicadas: tuteo neutro; tono sereno y sin humor en salud; botones con verbo o estado concreto; no se culpa por no haber tomado; en la pantalla bloqueada solo se muestra lo mínimo.
- Criterios de aceptación de los textos: ningún aviso incluye instrucciones médicas, indicaciones ni notas; el texto cabe en dos líneas en el push; los nombres largos de medicamento se acortan con «…» sin perder la hora.

## 4. Diseño técnico (software-architect)
- Archivos a crear / modificar:
  - `app/Services/Notifications/GenerateNotifications.php`: rama o servicio nuevo (`GenerateMedicationReminders`) invocado desde `notifications:generate`. Usa `MedicationSchedule` de CHG-129 para calcular las tomas del día y `CreateNotification::create` (`app/Services/Notifications/CreateNotification.php:12-25`) con tipo `medication_due`, origen el medicamento y `occurrence_key = "{Y-m-d}:{HH:MM}"`. La clave única existente `notifications_occurrence_unique` garantiza una sola notificación por (usuario, medicamento, toma).
  - Recuperación tras un cron caído: recorrer las tomas del día cuya hora ya llegó; las de más de 60 min se crean sin push.
  - `NotificationPreferences::DEFAULTS` (líneas 11-19): `push_medication_due` en `true` (solo controla el push; la campana siempre muestra el aviso). Interruptor en `resources/views/livewire/notifications/settings.blade.php` y regla `boolean` en `NotificationSettings`.
  - Despacho: `notifications:dispatch` ya envía por push y marca `push_sent_at`; agregar la ventana corta de vigencia para `medication_due` (el máximo general es 12 h, `config/game.php`).
  - Acción «Tomado»: `actions()` de `NotificationDispatcher` (`app/Services/Notifications/Push/NotificationDispatcher.php:101`) devuelve, para `medication_due`, `[['action' => 'quick-medication', 'title' => 'Tomado'], ['action' => 'open', 'title' => 'Abrir']]` y `quick_url` con `URL::temporarySignedRoute('push.quick-medication', …)`. Nuevo `app/Http/Controllers/Push/QuickMedicationController.php`, copia del patrón de `QuickWaterController`: reclama `action_done_at` con UPDATE condicional (repetir devuelve `already-done`), fija el usuario dueño de la notificación y llama a `LogMedicationIntake` con la toma esperada (`scheduled_for`). Ruta junto a `push.quick-water`. El service worker debe manejar la acción `quick-medication` como maneja `quick-water`. El enlace vence al final del día local (`America/Bogota`) de la toma, a diferencia del de agua (12 h); pasado ese momento responde que ya no está disponible y la toma se registra desde la app.
  - Cancelación: al registrar una toma o finalizar un medicamento, cancelar la notificación pendiente (`cancelled_at`), siguiendo `NotificationSourceObserver`. Registrar `medication` en el morph map si aún no lo está.
- Enfoque y patrones a seguir: `WeeklyInsights::notify()` (`firstOrCreate` por clave de ocurrencia) y las pruebas de doble corrida (`WeeklyInsightsTest.php:88-98`, `GuardianNudgesTest.php:60`).
- Riesgos y mitigación:
  - Zona horaria: `users` no tiene columna `timezone`; todo usa `America/Bogota` (`config/app.php:73`). Mitigación: calcular siempre en esa zona y guardar `scheduled_for` en UTC; documentar la limitación.
  - Precisión: el aviso puede llegar con hasta el retraso del cron (1 min, o el que permita el hosting). Documentar en `docs/DEPLOY.md`.
  - Registro por enlace firmado sin sesión: el enlace es de un solo uso y por notificación; solo registra la toma esperada de esa notificación, nunca datos libres. Probar que no sirve para otro usuario ni para otra toma.
  - Ruido: un medicamento de varias tomas al día produce varios avisos. Mitigación: respetar el interruptor y no avisar tomas ya registradas.
- Qué NO tocar: el cron/crontab ni `routes/console.php` (no se agrega ningún schedule), el guardián y los avisos de tareas, planes y cumpleaños.

## 5. Pruebas (qa-tester)
| Caso | Tipo | Nivel | Test |
|---|---|---|---|
| Crea el aviso a la hora de la toma (CA1) | Feature | Servicio + comando | `MedicationReminderTest` |
| Dos corridas, una notificación (CA2) | Feature | Comando | `MedicationReminderTest` (modelo: `WeeklyInsightsTest.php:88-98`) |
| Toma registrada cancela o evita el aviso (CA3) | Feature | Servicio | `MedicationReminderTest` |
| Cron retrasado: campana sin push (CA4) | Feature | Despacho | `MedicationReminderTest` (modelo: `NotificationDispatchTest.php:52`) |
| Preferencia apagada (CA5) | Feature | Despacho | `MedicationReminderTest` |
| «Tomado» registra la toma y marca atendido (CA8) | Feature | Controlador | `QuickMedicationTest` (modelo: pruebas de `QuickWaterController`) |
| Repetir «Tomado» no duplica (CA9) | Feature | Controlador | `QuickMedicationTest` |
| Enlace vencido, alterado o ajeno (CA10) | Feature | Controlador | `QuickMedicationTest` |
| Aislamiento entre usuarios (CA6) | Feature | Servicio | `MedicationReminderTest` |
| Medicamento finalizado (CA7) | Feature | Servicio | `MedicationReminderTest` |
| Cambio de día en `America/Bogota` | Unitaria | Support | `MedicationScheduleTest` |
- Comando para verificar: `php artisan test --filter='MedicationReminder|QuickMedication'`, `php artisan schedule:list` (sin schedules nuevos), `php artisan ui:conformance`, `npm run build`.
- Regresión a vigilar: `NotificationGenerationTest`, `NotificationDispatchTest`, `GuardianNudgesTest`, `WeeklyInsightsTest`.

## 6. Documentación (tech-writer)
- Docs a actualizar: `docs/notifications.md` (sección «Acciones del aviso»: añadir «Tomado» y su ruta firmada), `docs/DEPLOY.md` (tabla de tareas, ~línea 26: «medicamentos corren dentro de `notifications:generate`», límite de precisión y zona horaria), `docs/notifications.md` (nuevo tipo `medication_due` y preferencias), `docs/UX-WRITING.md` (texto del aviso).

## 7. Tareas
- [x] T1 · Recibir un aviso a la hora de cada toma pendiente — Resp: developer — Modelo: estandar — Archivos: `GenerateMedicationReminders`, `GenerateNotifications`, observer de cancelación — Hecho cuando: CA1, CA2, CA3 y CA7 pasan en pruebas.
- [x] T2 · Tocar «Tomado» en el aviso y registrar la toma sin abrir la app — Resp: developer — Modelo: estandar — Archivos: `NotificationDispatcher::actions`, `QuickMedicationController`, rutas, service worker — Hecho cuando: CA8, CA9 y CA10 pasan en pruebas.
- [x] T3 · Push solo si el aviso es reciente y puedo apagarlo en Ajustes — Resp: developer — Modelo: estandar — Archivos: `NotificationPreferences`, `NotificationSettings`, `settings.blade.php`, despacho — Hecho cuando: CA4 y CA5 pasan en pruebas.
- [x] T4 · Aislamiento, documentación y verificación — Resp: developer — Modelo: estandar — Archivos: `tests/Feature/*`, `docs/DEPLOY.md`, `docs/notifications.md` — Hecho cuando: CA6 y los comandos de §5 pasan.

## 8. Bitácora de implementación (developer)
- 2026-11-16 — T1: `GenerateMedicationReminders` (llamado desde `GenerateNotifications::run`, sin tocar `routes/console.php`): crea `medication_due` por cada toma de hoy ya vencida y sin registro, con ocurrencia `{Y-m-d}:{HH:MM}` en hora de Bogotá y `scheduled_for` en UTC. `Medication` entra en `NotificationSource::TYPES` (con `active()`: activo y dentro de `ends_on`). No pasa por la compuerta de las 9:00 ni por las preferencias de creación.
- 2026-11-16 — T2: `QuickMedicationController` y ruta `push.quick-medication` (`signed`, `throttle:30,1`), `actions()` con «Tomado»/«Abrir» y enlace que vence al final del día local de la toma, y rama `quick-medication` en `public/sw.js` (con respaldo a `/health/medications`). El reclamo de `action_done_at` se libera si el registro falla o el medicamento ya no está activo; si la toma ya estaba tomada en la app no se duplica ni se cambia su hora.
- 2026-11-16 — T3: preferencia **`push_medication_due`** (en lugar de `push_medication`, porque `pushAllowed()` arma `push_{tipo}`), activada por defecto, con interruptor «Medicamentos» en Ajustes. Ventana de 60 minutos solo para este tipo en `NotificationDispatcher` y en `AndroidDispatcher`. Presentación: ícono `bi-capsule`, etiqueta «Medicamento», destino Salud › Medicamentos, y `medication_due` agregado a las dos listas de `NotificationBell`.
- 2026-11-16 — Cancelación: `LogMedicationIntake` cancela el aviso de la toma registrada; `MedicationReminderObserver` cancela los avisos de hoy al finalizar, eliminar o cambiar la programación.
- 2026-11-16 — T4: `MedicationReminderTest` (13) y `QuickMedicationTest` (6) pasan. Suite completa: 1679 pruebas, 46 fallas idénticas a las de la base sin estos cambios (entorno); `ui:conformance` no marca los archivos tocados. Documentado en `docs/DEPLOY.md`, `docs/notifications.md` y `docs/UX-WRITING.md`.
- Decisión nueva del usuario: los medicamentos sensibles avisan con texto genérico («Es hora de una toma programada»), sin nombre ni dosis, en push y campana.
- Nota de comportamiento: deshacer una toma borra el registro y el generador reabre el aviso de esa toma (misma clave); si ya se había enviado por push no se reenvía.
- No verificado: no se probó con un navegador ni un teléfono reales (ni en iPhone): se probaron el payload, la ruta firmada y el despacho con un doble del canal push, no el `sw.js` ni la notificación del sistema. Tampoco hay revisión visual de la pantalla de Ajustes ni de la campana. Cambié `public/sw.js` sin subir `VERSION`; el navegador lo actualiza al cambiar el archivo.
- Entorno: PHP 8.3 con un `vendor` resuelto aparte; `composer.lock` sin tocar. Inicio con `start --force` autorizado por el usuario (CHG-106 y CHG-108 siguen En desarrollo).

## 9. Preguntas abiertas, decisiones e historial
- 2026-11-16 — Creado en to-do/ (Borrador, nivel M). Decisión del usuario: incluir recordatorios usando el cron existente. Se separa de CHG-129 para mantener cada CHG en nivel M.
- Decidido: sin cron ni proceso nuevo; se reutiliza `notifications:generate` y `notifications:dispatch`.
- Decidido 2026-11-16: el usuario considera muy útil el botón de acción en la notificación; «Tomado» entra en este CHG con el patrón de CHG-069.
- Decidido 2026-11-16: el enlace de «Tomado» vale hasta el fin del día local de la toma.
- Decidido 2026-11-16: el aviso solo tiene «Tomado» y «Abrir»; omitir se hace desde la app.
- Decidido 2026-11-16: el interruptor «Medicamentos» apaga solo el push; la campana siempre muestra el aviso.
- Decidido 2026-11-16: zona horaria por usuario fuera de alcance; se mantiene `America/Bogota`.
- Decidido 2026-11-16: ventana de vigencia de 60 minutos.
- 2026-11-16 — Aprobado por el usuario (Compuerta 2)
- 2026-11-16 — Movido a in-progress/ (En desarrollo)
- 2026-11-16 — Implementación terminada → En verificación
